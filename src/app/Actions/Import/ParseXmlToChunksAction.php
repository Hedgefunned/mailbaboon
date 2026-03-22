<?php

namespace App\Actions\Import;

use Illuminate\Http\UploadedFile;

class ParseXmlToChunksAction
{
    public function handle(UploadedFile $file, int $chunkSize): array
    {
        $startTime = hrtime(true);

        $validRecords = [];
        $invalidRows = [];
        $seen = [];

        $xml = fopen($file->getRealPath(), 'r');
        $buffer = '';
        $bytes = 4 * 1024;

        while (! feof($xml)) {
            $buffer .= fread($xml, $bytes);

            while (true) {
                $itemStart = strpos($buffer, '<item>');
                if ($itemStart === false) {
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

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $invalidRows[] = [
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'is_valid' => 0,
                        'failure_reason' => 'INVALID EMAIL',
                    ];
                    continue;
                }

                if (isset($seen[$email])) {
                    $invalidRows[] = [
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'is_valid' => 0,
                        'failure_reason' => 'DUPLICATE_IN_FILE',
                    ];
                    continue;
                }

                $seen[$email] = true;
                $validRecords[] = [
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ];
            }
        }

        fclose($xml);

        $invalidCount = count(array_filter($invalidRows, fn ($r) => $r['failure_reason'] === 'INVALID EMAIL'));
        $dupeCount = count(array_filter($invalidRows, fn ($r) => $r['failure_reason'] === 'DUPLICATE_IN_FILE'));

        return [
            'valid_chunks' => array_chunk($validRecords, $chunkSize),
            'invalid_rows' => $invalidRows,
            'total_records' => count($validRecords) + count($invalidRows),
            'invalid_records' => $invalidCount,
            'duplicates_in_file' => $dupeCount,
            'execution_time_ms' => round((hrtime(true) - $startTime) / 1e6, 2),
        ];
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
}
