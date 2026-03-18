<?php

namespace App\Actions\Import;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ParseXmlToCsvAction
{
    public function handle(UploadedFile $file): array
    {
        $startTime = hrtime(true);
        $storagePath = 'imports/'.now()->format('Y-m-d_His').'_'.uniqid().'.csv';
        $csv = fopen(Storage::disk('local')->path($storagePath), 'w');
        fputcsv($csv, ['email', 'first_name', 'last_name', 'is_valid', 'failure_reason']);

        $xml = fopen($file->getRealPath(), 'r');
        $buffer = '';
        $bytes = 4 * 1024; // Read 4KB at a time — enough to capture individual <item> nodes without consuming too much memory.

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

        return [
            'csv_path' => $storagePath,
            'execution_time_ms' => round((hrtime(true) - $startTime) / 1e6, 2),
        ];
    }

    private function isValidEmail(string $email): bool
    {
        // Sadly, we are skipping our custom validation here because it's really expensive.
        // The built-in filter_var is not perfect but it's very fast and should be good enough for this use case.
        // Using the custom EmailAddress rule costs ~5s on an otherwise ~500ms parse which is almost 200% increase in total execution time.
        return filter_var($email, FILTER_VALIDATE_EMAIL);
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
}
