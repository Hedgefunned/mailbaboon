<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProcessContactChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(
        private readonly array $chunk,
        private readonly bool $overwriteExisting,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $now = now();
        $rows = array_map(
            fn (array $record) => array_merge($record, ['created_at' => $now, 'updated_at' => $now]),
            $this->chunk,
        );

        if ($this->overwriteExisting) {
            DB::table('contacts')->upsert($rows, ['email'], ['first_name', 'last_name', 'updated_at']);
            // MySQL/MariaDB counts updated rows as 2 in affected_rows for ON DUPLICATE KEY UPDATE,
            // so we use chunk size directly: all records were either inserted or updated.
            $processed = count($rows);
        } else {
            $processed = DB::table('contacts')->insertOrIgnore($rows);
        }

        if ($batchId = $this->batch()?->id) {
            Cache::increment("import_{$batchId}_inserted", $processed);
        }
    }
}
