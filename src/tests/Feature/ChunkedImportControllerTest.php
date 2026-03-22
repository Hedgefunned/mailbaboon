<?php

namespace Tests\Feature;

use App\Services\ChunkedImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ChunkedImportControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── store() ───────────────────────────────────────────────────────────────

    public function test_store_returns_batch_id_on_success(): void
    {
        Bus::fake();

        $this->mock(ChunkedImportService::class)
            ->expects('import')
            ->andReturn([
                'batch_id' => 'fake-batch-id',
                'total_records' => 10,
                'valid_records' => 8,
                'invalid_records' => 2,
                'duplicates_in_file' => 0,
                'parse_time_ms' => 12.5,
                'overwrite_existing' => false,
                'chunks_dispatched' => 1,
            ]);

        $response = $this->postJson('/api/import/chunked', [
            'file' => UploadedFile::fake()->create('contacts.xml', 0, 'text/xml'),
        ]);

        $response->assertOk()->assertJsonFragment(['batch_id' => 'fake-batch-id']);
    }

    public function test_store_requires_file(): void
    {
        $response = $this->postJson('/api/import/chunked', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file']);
    }

    public function test_store_rejects_non_xml_file(): void
    {
        $response = $this->postJson('/api/import/chunked', [
            'file' => UploadedFile::fake()->create('contacts.csv', 0, 'text/csv'),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file']);
    }

    // ── status() ──────────────────────────────────────────────────────────────

    public function test_status_returns_404_for_unknown_batch(): void
    {
        $response = $this->getJson('/api/import/batch/nonexistent-id');

        $response->assertNotFound();
    }

    public function test_status_returns_batch_progress(): void
    {
        // Create a real batch so it is persisted to the job_batches table
        // and findable by the status endpoint.
        $batch = Bus::batch([])->name('import')->dispatch();

        $response = $this->getJson("/api/import/batch/{$batch->id}");

        $response->assertOk()->assertJsonStructure([
            'id',
            'total_jobs',
            'pending_jobs',
            'failed_jobs',
            'progress',
            'finished_at',
            'cancelled_at',
        ]);
    }
}
