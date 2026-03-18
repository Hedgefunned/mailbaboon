<?php

namespace App\Services;

use App\Actions\Import\DeduplicateExistingContactsAction;
use App\Actions\Import\DeduplicateInputAction;
use App\Actions\Import\InsertValidContactsAction;
use App\Actions\Import\LoadCsvIntoStagingAction;
use App\Actions\Import\ParseXmlToCsvAction;
use App\Contracts\ImportServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportService implements ImportServiceInterface
{
    private const PER_PAGE = 25;

    private const STAGING_TABLE = 'contact_imports';

    public function __construct(
        private ParseXmlToCsvAction $parseXmlToCsv,
        private LoadCsvIntoStagingAction $loadCsvIntoStaging,
        private DeduplicateInputAction $deduplicateInput,
        private DeduplicateExistingContactsAction $deduplicateExistingContacts,
        private InsertValidContactsAction $insertValidContacts,
    ) {}

    public function import(UploadedFile $file, bool $overwriteExisting = false, ?callable $onProgress = null): array
    {
        $startTime = hrtime(true);

        $parseResult = $this->parseXmlToCsv->handle($file);
        $onProgress && $onProgress('parse', 20, 'XML parsed');

        $loadResult = $this->loadCsvIntoStaging->handle($parseResult['csv_path']);
        $onProgress && $onProgress('load', 40, 'Loaded into staging');

        $inputDedupeResult = $this->deduplicateInput->handle();
        $onProgress && $onProgress('dedupe_input', 60, 'Deduplicated within file');

        $dbDedupeResult = $this->deduplicateExistingContacts->handle();
        $onProgress && $onProgress('dedupe_db', 80, 'Checked against database');

        $insertResult = $this->insertValidContacts->handle($overwriteExisting);
        $onProgress && $onProgress('insert', 100, 'Contacts inserted');
        $importResult = $this->prepareImportResult($overwriteExisting);

        $performanceMetrics = [
            'parse_time_ms' => $parseResult['execution_time_ms'],
            'execution_time_ms' => round((hrtime(true) - $startTime) / 1e6, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];

        $result = array_merge(
            $loadResult,
            $inputDedupeResult,
            $dbDedupeResult,
            $insertResult,
            $importResult,
            [
                'overwrite_existing' => $overwriteExisting,
            ],
            $performanceMetrics,
        );

        Log::info('Import (CSV) completed', $result);

        return $result;
    }

    private function prepareImportResult(bool $overwriteExisting = false): array
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
            'overwritten_records' => $overwriteExisting ? $duplicatesInFile + $duplicatesInDb : 'false',
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
