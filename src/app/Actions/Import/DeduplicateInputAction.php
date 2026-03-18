<?php

namespace App\Actions\Import;

use Illuminate\Support\Facades\DB;

class DeduplicateInputAction extends ImportAction
{
    public function handle(): array
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

        return [
            'dedupe_time_ms' => round((hrtime(true) - $startTime) / 1e6, 2),
        ];
    }
}
