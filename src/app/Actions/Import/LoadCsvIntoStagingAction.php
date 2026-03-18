<?php

namespace App\Actions\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LoadCsvIntoStagingAction extends ImportAction
{
    public function handle(string $storagePath): array
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

        return [
            'load_time_ms' => round((hrtime(true) - $startTime) / 1e6, 2),
        ];
    }
}
