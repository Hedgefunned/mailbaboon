<?php

namespace Tests\Feature;

use App\Actions\Import\ParseXmlToChunksAction;
use App\Services\ChunkedImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ChunkedImportServiceTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeParseResult(array $overrides = []): array
    {
        return array_merge([
            'valid_chunks' => [[
                ['email' => 'alice@example.com', 'first_name' => 'Alice', 'last_name' => 'A'],
                ['email' => 'bob@example.com', 'first_name' => 'Bob', 'last_name' => 'B'],
            ]],
            'invalid_rows' => [
                ['email' => 'bad', 'first_name' => 'X', 'last_name' => 'Y', 'is_valid' => 0, 'failure_reason' => 'INVALID EMAIL'],
            ],
            'total_records' => 3,
            'invalid_records' => 1,
            'duplicates_in_file' => 0,
            'execution_time_ms' => 5.0,
        ], $overrides);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_dispatches_batch_with_one_job_per_chunk(): void
    {
        Bus::fake();

        $this->mock(ParseXmlToChunksAction::class)
            ->expects('handle')
            ->andReturn($this->makeParseResult([
                'valid_chunks' => [
                    [['email' => 'a@example.com', 'first_name' => 'A', 'last_name' => 'A']],
                    [['email' => 'b@example.com', 'first_name' => 'B', 'last_name' => 'B']],
                ],
            ]));

        $this->app->make(ChunkedImportService::class)
            ->import(UploadedFile::fake()->create('contacts.xml'));

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 2);
    }

    public function test_invalid_rows_written_to_staging_table_synchronously(): void
    {
        Bus::fake();

        $this->mock(ParseXmlToChunksAction::class)
            ->expects('handle')
            ->andReturn($this->makeParseResult());

        $this->app->make(ChunkedImportService::class)
            ->import(UploadedFile::fake()->create('contacts.xml'));

        $this->assertDatabaseCount('contact_imports', 1);
        $this->assertDatabaseHas('contact_imports', ['failure_reason' => 'INVALID EMAIL']);
    }

    public function test_result_contains_expected_keys(): void
    {
        Bus::fake();

        $this->mock(ParseXmlToChunksAction::class)
            ->expects('handle')
            ->andReturn($this->makeParseResult());

        $result = $this->app->make(ChunkedImportService::class)
            ->import(UploadedFile::fake()->create('contacts.xml'));

        foreach (['batch_id', 'total_records', 'invalid_records', 'duplicates_in_file', 'chunks_dispatched', 'parse_time_ms'] as $key) {
            $this->assertArrayHasKey($key, $result, "Result is missing key: {$key}");
        }
    }

    public function test_chunks_dispatched_count_matches_chunks(): void
    {
        Bus::fake();

        $this->mock(ParseXmlToChunksAction::class)
            ->expects('handle')
            ->andReturn($this->makeParseResult([
                'valid_chunks' => [
                    [['email' => 'a@example.com', 'first_name' => 'A', 'last_name' => 'A']],
                    [['email' => 'b@example.com', 'first_name' => 'B', 'last_name' => 'B']],
                    [['email' => 'c@example.com', 'first_name' => 'C', 'last_name' => 'C']],
                ],
            ]));

        $result = $this->app->make(ChunkedImportService::class)
            ->import(UploadedFile::fake()->create('contacts.xml'));

        $this->assertSame(3, $result['chunks_dispatched']);
    }

    public function test_no_jobs_dispatched_when_all_records_invalid(): void
    {
        Bus::fake();

        $this->mock(ParseXmlToChunksAction::class)
            ->expects('handle')
            ->andReturn($this->makeParseResult([
                'valid_chunks' => [],
                'invalid_rows' => [
                    ['email' => 'bad', 'first_name' => 'X', 'last_name' => 'Y', 'is_valid' => 0, 'failure_reason' => 'INVALID EMAIL'],
                ],
                'total_records' => 1,
                'invalid_records' => 1,
            ]));

        $result = $this->app->make(ChunkedImportService::class)
            ->import(UploadedFile::fake()->create('contacts.xml'));

        $this->assertSame(0, $result['chunks_dispatched']);
    }

    public function test_logs_dispatch_completion(): void
    {
        Bus::fake();
        Log::spy();

        $this->mock(ParseXmlToChunksAction::class)
            ->expects('handle')
            ->andReturn($this->makeParseResult());

        $this->app->make(ChunkedImportService::class)
            ->import(UploadedFile::fake()->create('contacts.xml'));

        Log::assertLogged('info', fn ($message) => str_contains($message, 'chunked'));
    }

    public function test_list_rejected_returns_staging_records(): void
    {
        \DB::table('contact_imports')->insert([
            ['email' => 'bad@example.com', 'first_name' => 'X', 'last_name' => 'Y', 'is_valid' => 0, 'failure_reason' => 'INVALID EMAIL'],
        ]);

        $result = $this->app->make(ChunkedImportService::class)->listRejected([]);

        $this->assertSame(1, $result->total());
        $this->assertSame('bad@example.com', $result->items()[0]->email);
    }

    public function test_truncate_contacts_empties_contacts_table(): void
    {
        \DB::table('contacts')->insert([
            'email' => 'someone@example.com', 'first_name' => 'A', 'last_name' => 'B',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->app->make(ChunkedImportService::class)->truncateContacts();

        $this->assertDatabaseCount('contacts', 0);
    }
}
