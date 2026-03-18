<?php

namespace App\Actions\Import;

use Illuminate\Support\Facades\DB;

class InsertValidContactsAction extends ImportAction
{
    public function handle(bool $overwriteExisting = false): array
    {
        $startTime = hrtime(true);

        $table = self::STAGING_TABLE;
        if ($overwriteExisting) {
            DB::statement("
                INSERT INTO contacts (email, first_name, last_name, created_at, updated_at)
                SELECT email, first_name, last_name, NOW(), NOW()
                FROM {$table}
                WHERE failure_reason != 'INVALID EMAIL'
                ON DUPLICATE KEY UPDATE
                    first_name = VALUES(first_name),
                    last_name = VALUES(last_name),
                    updated_at = VALUES(updated_at)
            ");
        } else {
            DB::statement("
                INSERT INTO contacts (email, first_name, last_name)
                SELECT email, first_name, last_name
                FROM {$table}
                WHERE is_valid = 1
            ");
        }

        return [
            'insert_valid_contacts_time_ms' => round((hrtime(true) - $startTime) / 1e6, 2),
        ];
    }
}
