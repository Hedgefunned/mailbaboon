<?php

namespace App\Actions\Import;

use Illuminate\Support\Facades\DB;

class DeduplicateExistingContactsAction extends ImportAction
{
    public function handle(): array
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

        return [
            'existing_contacts_dedupe_time_ms' => round((hrtime(true) - $startTime) / 1e6, 2),
        ];
    }
}
