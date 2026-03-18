<?php

namespace App\Actions\Import;

use Illuminate\Support\Facades\DB;

class InsertValidContactsAction extends ImportAction
{
    public function handle(): array
    {
        $startTime = hrtime(true);

        $table = self::STAGING_TABLE;
        DB::statement("
            INSERT INTO contacts (email, first_name, last_name)
            SELECT email, first_name, last_name
            FROM {$table}
            WHERE is_valid = 1
        ");

        return [
            'insert_valid_contacts_time_ms' => round((hrtime(true) - $startTime) / 1e6, 2),
        ];
    }
}
