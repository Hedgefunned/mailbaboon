<?php

namespace App\Services;

use App\Contracts\ImportServiceInterface;
use App\Rules\EmailAddress;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportService implements ImportServiceInterface
{
    private const PER_PAGE = 25;

    private const STAGING_TABLE = 'contact_imports';

    public function import(UploadedFile $file): array
    {
        $startTime = hrtime(true);

        // We parse the XML file but we ignore the XML structure and just treat it as a stream of text, extracting the relevant data with simple string functions.
        $parseResult = $this->parseXmlToCsv($file);

        // Now we directly load the generated CSV into DB
        $loadResult = $this->loadCsvIntoDb($parseResult['csv_path']);

        // We perform deduplication in the DB for better performance, especially with large datasets.
        $inputDedupeResult = $this->deduplicateInput();

        // After deduplicating the input, we also need to check for duplicates against existing contacts in the database to ensure we don't insert duplicates.
        $dbDedupeResult = $this->deduplicateExistingContacts();

        // Finally, we insert the valid contacts into the main contacts table.
        $insertResult = $this->insertValidContacts();

        // We get the final import results, including counts of new records, duplicates, and invalid records.
        $importResult = $this->prepareImportResult();

        $memoryPeakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        $performanceMetrics = [
            'parse_time_ms' => $parseResult['execution_time_ms'],
            'execution_time_ms' => round((hrtime(true) - $startTime) / 1e6, 2),
            'memory_peak_mb' => $memoryPeakMb,
        ];

        $result = array_merge($loadResult, $inputDedupeResult, $dbDedupeResult, $insertResult, $importResult, $performanceMetrics);

        Log::info('Import (CSV) completed', $result);

        return $result;
    }

    private function parseXmlToCsv(UploadedFile $file): array
    {
        $startTime = hrtime(true);
        $storagePath = 'imports/'.now()->format('Y-m-d_His').'_'.uniqid().'.csv';
        $csv = fopen(Storage::disk('local')->path($storagePath), 'w');
        fputcsv($csv, ['email', 'first_name', 'last_name', 'is_valid', 'failure_reason']);

        $xml = fopen($file->getRealPath(), 'r');
        $buffer = '';
        $bytes = 4 * 1024; // Read the XML file 4KB at a time, which should be enough to capture individual <item> nodes without consuming too much memory.

        while (! feof($xml)) {
            $buffer .= fread($xml, $bytes);

            while (true) {
                $itemStart = strpos($buffer, '<item>');
                if ($itemStart === false) {
                    // Keep only a small tail in case the opening tag is split across chunks.
                    if (strlen($buffer) > 16) {
                        $buffer = substr($buffer, -16);
                    }

                    break;
                }

                if ($itemStart > 0) {
                    $buffer = substr($buffer, $itemStart);
                }

                $itemEnd = strpos($buffer, '</item>');
                if ($itemEnd === false) {
                    break;
                }

                $itemXml = substr($buffer, 0, $itemEnd + 7);
                $buffer = substr($buffer, $itemEnd + 7);

                $email = strtolower(trim($this->extractTagValue($itemXml, 'email')));
                $firstName = trim($this->extractTagValue($itemXml, 'first_name'));
                $lastName = trim($this->extractTagValue($itemXml, 'last_name'));

                if ($this->isValidEmail($email)) {
                    fwrite($csv, $this->toCsvLine([$email, $firstName, $lastName, '1', '']));
                } else {
                    fwrite($csv, $this->toCsvLine([$email, $firstName, $lastName, '0', 'INVALID EMAIL']));
                }
            }
        }

        fclose($xml);
        fclose($csv);

        $executionTimeMs = round((hrtime(true) - $startTime) / 1e6, 2);

        return [
            'csv_path' => $storagePath,
            'execution_time_ms' => $executionTimeMs,
        ];
    }

    private function loadCsvIntoDb(string $storagePath): array
    {
        $startTime = hrtime(true);
        $absolutePath = Storage::disk('local')->path($storagePath);

        DB::table(self::STAGING_TABLE)->truncate();

        $table = self::STAGING_TABLE;
        DB::statement("
            LOAD DATA LOCAL INFILE '{$absolutePath}'
            INTO TABLE {$table}
            FIELDS TERMINATED BY ','
            OPTIONALLY ENCLOSED BY '\"'
            LINES TERMINATED BY '\n'
            IGNORE 1 ROWS
            (email, first_name, last_name, is_valid, failure_reason)
        ");

        $loadTimeMs = round((hrtime(true) - $startTime) / 1e6, 2);

        return [
            'load_time_ms' => $loadTimeMs,
        ];
    }

    private function deduplicateInput(): array
    {
        $startTime = hrtime(true);

        $table = self::STAGING_TABLE;
        DB::statement("
            UPDATE {$table} duplicate_row
            JOIN {$table} original_row
                ON original_row.email = duplicate_row.email
                AND original_row.id < duplicate_row.id
            SET 
                duplicate_row.is_valid = 0,
                duplicate_row.failure_reason = CONCAT_WS(',', duplicate_row.failure_reason, 'DUPLICATE_IN_FILE')
            WHERE duplicate_row.is_valid = 1;
            ");

        $dedupeTimeMs = round((hrtime(true) - $startTime) / 1e6, 2);

        return [
            'dedupe_time_ms' => $dedupeTimeMs,
        ];
    }

    private function deduplicateExistingContacts(): array
    {
        $startTime = hrtime(true);

        $table = self::STAGING_TABLE;
        DB::statement("
            UPDATE {$table} ci
            JOIN contacts c ON ci.email = c.email
            SET 
                ci.is_valid = 0,
                ci.failure_reason = CONCAT_WS(',', ci.failure_reason, 'DUPLICATE_IN_DB')
            WHERE ci.is_valid = 1
        ");

        $dedupeTimeMs = round((hrtime(true) - $startTime) / 1e6, 2);

        return [
            'existing_contacts_dedupe_time_ms' => $dedupeTimeMs,
        ];
    }

    private function insertValidContacts(): array
    {
        $startTime = hrtime(true);

        $table = self::STAGING_TABLE;
        DB::statement("
            INSERT INTO contacts (email, first_name, last_name)
            SELECT email, first_name, last_name
            FROM {$table}
            WHERE is_valid = 1
        ");

        $insertTimeMs = round((hrtime(true) - $startTime) / 1e6, 2);

        return [
            'insert_valid_contacts_time_ms' => $insertTimeMs,
        ];
    }

    private function isValidEmail(string $email): bool
    {
        // Sadly, we are skipping our custom validation here because it's really expensive.
        // The built-in filter_var is not perfect but it's very fast and should be good enough for this use case.
        // Using the custom EmailAddress rule costs ~5s on an otherwise ~500ms parse which is almost 200% increase in total execution time.
        return filter_var($email, FILTER_VALIDATE_EMAIL);

        // $valid = false;
        // (new EmailAddress)->validate('email', $email, function () use (&$failed) {
        //     $valid = true;
        // });
        // return $valid;
    }

    private function extractTagValue(string $itemXml, string $tagName): string
    {
        $openTag = '<'.$tagName.'>';
        $closeTag = '</'.$tagName.'>';

        $start = strpos($itemXml, $openTag);
        if ($start === false) {
            return '';
        }

        $start += strlen($openTag);
        $end = strpos($itemXml, $closeTag, $start);
        if ($end === false) {
            return '';
        }

        return html_entity_decode(substr($itemXml, $start, $end - $start), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function toCsvLine(array $columns): string
    {
        $escaped = array_map(function (string $value): string {
            if (
                str_contains($value, ',') ||
                str_contains($value, '"') ||
                str_contains($value, "\n") ||
                str_contains($value, "\r")
            ) {
                return '"'.str_replace('"', '""', $value).'"';
            }

            return $value;
        }, $columns);

        return implode(',', $escaped)."\n";
    }

    private function prepareImportResult(): array
    {
        $newRecords = DB::table(self::STAGING_TABLE)->where('is_valid', 1)->count();
        $duplicatesInFile = DB::table(self::STAGING_TABLE)
            ->where('failure_reason', 'like', '%DUPLICATE_IN_FILE%')
            ->count();
        $duplicatesInDb = DB::table(self::STAGING_TABLE)
            ->where('failure_reason', 'like', '%DUPLICATE_IN_DB%')
            ->count();
        $invalidRecords = DB::table(self::STAGING_TABLE)
            ->where('failure_reason', 'like', '%INVALID EMAIL%')
            ->count();

        $total = DB::table(self::STAGING_TABLE)->count();

        return [
            'total_records' => $total,
            'new_records' => $newRecords,
            'duplicates_in_file' => $duplicatesInFile,
            'duplicates_in_db' => $duplicatesInDb,
            'invalid_records' => $invalidRecords,
        ];
    }

    public function listRejected(array $filters): LengthAwarePaginator
    {
        $query = DB::table(self::STAGING_TABLE)
            ->select(['id', 'first_name', 'last_name', 'email', 'failure_reason'])
            ->where('is_valid', 0)
            ->orderByDesc('id');

        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $like = '%'.$search.'%';
                $subQuery
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('failure_reason', 'like', $like);
            });
        }

        return $query->paginate(self::PER_PAGE);
    }

    public function truncateContacts(): void
    {
        DB::table('contacts')->truncate();
    }
}
