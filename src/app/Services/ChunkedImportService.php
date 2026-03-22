<?php

namespace App\Services;

use App\Actions\Import\ParseXmlToChunksAction;
use App\Contracts\ImportServiceInterface;
use App\Jobs\ProcessContactChunkJob;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChunkedImportService implements ImportServiceInterface
{
    private const PER_PAGE = 25;

    private const STAGING_TABLE = 'contact_imports';

    public function __construct(
        private readonly ParseXmlToChunksAction $parseXmlToChunks,
    ) {}

    public function import(UploadedFile $file, bool $overwriteExisting = false, ?callable $onProgress = null): array
    {
        $chunkSize = config('import.chunk_size', 1000);

        $parseResult = $this->parseXmlToChunks->handle($file, $chunkSize);

        // Write invalid/rejected rows synchronously so listRejected() works immediately.
        DB::table(self::STAGING_TABLE)->truncate();
        if (! empty($parseResult['invalid_rows'])) {
            DB::table(self::STAGING_TABLE)->insert($parseResult['invalid_rows']);
        }

        $jobs = array_map(
            fn (array $chunk) => new ProcessContactChunkJob($chunk, $overwriteExisting),
            $parseResult['valid_chunks'],
        );

        $validCount = array_sum(array_map('count', $parseResult['valid_chunks']));

        $initialStats = [
            'total_records' => $parseResult['total_records'],
            'valid_records' => $validCount,
            'invalid_records' => $parseResult['invalid_records'],
            'duplicates_in_file' => $parseResult['duplicates_in_file'],
            'parse_time_ms' => $parseResult['execution_time_ms'],
            'overwrite_existing' => $overwriteExisting,
            'chunks_dispatched' => count($jobs),
        ];

        $startedAt = microtime(true);

        $batch = Bus::batch($jobs)
            ->name('import')
            ->allowFailures()
            ->finally(function (Batch $batch) use ($initialStats, $startedAt): void {
                $batchId = $batch->id;
                $inserted = (int) Cache::get("import_{$batchId}_inserted", 0);

                Cache::put("import_{$batchId}_result", array_merge($initialStats, [
                    'new_records' => $inserted,
                    'duplicates_in_db' => $initialStats['valid_records'] - $inserted,
                    'execution_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                    'finished' => true,
                ]), now()->addHour());
            })
            ->dispatch();

        $result = array_merge($initialStats, [
            'batch_id' => $batch->id,
            'chunks_dispatched' => count($jobs),
        ]);

        Log::info('Import (chunked) dispatched', $result);

        return $result;
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
