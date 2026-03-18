<?php

namespace Tests\Feature;

use App\Actions\Import\DeduplicateExistingContactsAction;
use App\Actions\Import\DeduplicateInputAction;
use App\Actions\Import\InsertValidContactsAction;
use App\Actions\Import\LoadCsvIntoStagingAction;
use App\Actions\Import\ParseXmlToCsvAction;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ImportServiceTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Bind mocks for all 5 pipeline actions with minimal stub return values. */
    private function mockAllActions(string $csvPath = 'imports/test.csv'): void
    {
        $this->mock(ParseXmlToCsvAction::class)
            ->expects('handle')
            ->andReturn(['csv_path' => $csvPath, 'execution_time_ms' => 1.0]);

        $this->mock(LoadCsvIntoStagingAction::class)
            ->expects('handle')
            ->andReturn(['load_time_ms' => 1.0]);

        $this->mock(DeduplicateInputAction::class)
            ->expects('handle')
            ->andReturn(['dedupe_time_ms' => 1.0]);

        $this->mock(DeduplicateExistingContactsAction::class)
            ->expects('handle')
            ->andReturn(['existing_contacts_dedupe_time_ms' => 1.0]);

        $this->mock(InsertValidContactsAction::class)
            ->expects('handle')
            ->andReturn(['insert_valid_contacts_time_ms' => 1.0]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_passes_csv_path_from_parser_to_loader(): void
    {
        $csvPath = 'imports/wired-path.csv';

        $this->mock(ParseXmlToCsvAction::class)
            ->expects('handle')
            ->andReturn(['csv_path' => $csvPath, 'execution_time_ms' => 42.0]);

        // The loader must receive exactly the path the parser returned.
        $this->mock(LoadCsvIntoStagingAction::class)
            ->expects('handle')
            ->with($csvPath)
            ->andReturn(['load_time_ms' => 10.0]);

        $this->mock(DeduplicateInputAction::class)
            ->expects('handle')
            ->andReturn(['dedupe_time_ms' => 5.0]);

        $this->mock(DeduplicateExistingContactsAction::class)
            ->expects('handle')
            ->andReturn(['existing_contacts_dedupe_time_ms' => 3.0]);

        $this->mock(InsertValidContactsAction::class)
            ->expects('handle')
            ->andReturn(['insert_valid_contacts_time_ms' => 7.0]);

        $result = $this->app->make(ImportService::class)
            ->import(UploadedFile::fake()->create('contacts.xml'));

        $this->assertSame(42.0, $result['parse_time_ms']);
        $this->assertSame(10.0, $result['load_time_ms']);
    }

    public function test_result_contains_all_expected_keys(): void
    {
        $this->mockAllActions();

        $result = $this->app->make(ImportService::class)
            ->import(UploadedFile::fake()->create('contacts.xml'));

        $expectedKeys = [
            'load_time_ms',
            'dedupe_time_ms',
            'existing_contacts_dedupe_time_ms',
            'insert_valid_contacts_time_ms',
            'parse_time_ms',
            'execution_time_ms',
            'memory_peak_mb',
            'total_records',
            'new_records',
            'duplicates_in_file',
            'duplicates_in_db',
            'invalid_records',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Result is missing key: {$key}");
        }
    }

    public function test_logs_import_completion(): void
    {
        Log::spy();
        $this->mockAllActions();

        $this->app->make(ImportService::class)
            ->import(UploadedFile::fake()->create('contacts.xml'));

        Log::assertLogged('info', fn ($message) => str_contains($message, 'Import'));
    }

    public function test_overwrite_mode_reports_overwritten_records(): void
    {
        // Seed staging rows that simulate records marked as duplicates.
        // In overwrite mode, current implementation reports these as overwritten.
        \DB::table('contact_imports')->insert([
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'is_valid' => 0,
                'failure_reason' => 'DUPLICATE_IN_FILE',
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'is_valid' => 0,
                'failure_reason' => 'DUPLICATE_IN_DB',
            ],
        ]);

        $this->mock(ParseXmlToCsvAction::class)
            ->expects('handle')
            ->andReturn(['csv_path' => 'imports/test.csv', 'execution_time_ms' => 1.0]);

        $this->mock(LoadCsvIntoStagingAction::class)
            ->expects('handle')
            ->andReturn(['load_time_ms' => 1.0]);

        $this->mock(DeduplicateInputAction::class)
            ->expects('handle')
            ->andReturn(['dedupe_time_ms' => 1.0]);

        $this->mock(DeduplicateExistingContactsAction::class)
            ->expects('handle')
            ->andReturn(['existing_contacts_dedupe_time_ms' => 1.0]);

        $this->mock(InsertValidContactsAction::class)
            ->expects('handle')
            ->with(true)
            ->andReturn(['insert_valid_contacts_time_ms' => 1.0]);

        $result = $this->app->make(ImportService::class)
            ->import(UploadedFile::fake()->create('contacts.xml'), true);

        $this->assertTrue($result['overwrite_existing']);
        $this->assertSame(2, $result['overwritten_records']);
    }
}
