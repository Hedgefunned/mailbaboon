<?php

namespace App\Services;

use App\Contracts\ImportServiceInterface;
use App\Models\Contact;
use App\Rules\EmailAddress;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Pretty much the worst case scenario.
 * Runs for 90-ish seconds and results in 97972/2028 imported/skipped.
 * Surprisingly, it only uses ~20MB of memory though, so that's something.
 * Lets keep it around for comparison and possibly some fooling around with jobs and progress bars.
 */
class BaboonImportService implements ImportServiceInterface
{
    public function import(UploadedFile $file): array
    {
        $startTime = hrtime(true);

        $xml = simplexml_load_string($file->get());

        $skipped = 0;

        foreach ($xml->item as $item) {
            $email = trim((string) $item->email);
            $firstName = trim((string) $item->first_name);
            $lastName = trim((string) $item->last_name);

            if (! $this->isValidEmail($email)) {
                $skipped++;

                continue;
            }

            $key = strtolower($email);

            if (isset($seen[$key])) {
                $skipped++;

                continue;
            }

            $seen[$key] = true;

            Contact::updateOrCreate(['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email]);

        }

        $executionTimeMs = round((hrtime(true) - $startTime) / 1e6, 2);
        $memoryPeakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        Log::info('Import completed', [
            'imported' => count($seen),
            'skipped' => $skipped,
            'execution_time_ms' => $executionTimeMs,
            'memory_peak_mb' => $memoryPeakMb,
        ]);

        return [
            'imported' => count($seen),
            'skipped' => $skipped,
            'execution_time_ms' => $executionTimeMs,
            'memory_peak_mb' => $memoryPeakMb,
        ];
    }

    private function isValidEmail(string $email): bool
    {
        $failed = false;

        (new EmailAddress)->validate('email', $email, function () use (&$failed) {
            $failed = true;
        });

        return ! $failed;
    }
}
