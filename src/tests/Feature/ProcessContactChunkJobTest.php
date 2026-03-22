<?php

namespace Tests\Feature;

use App\Jobs\ProcessContactChunkJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessContactChunkJobTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(array $chunk, bool $overwriteExisting = false): void
    {
        (new ProcessContactChunkJob($chunk, $overwriteExisting))->handle();
    }

    public function test_inserts_new_contacts(): void
    {
        $this->runJob([
            ['email' => 'alice@example.com', 'first_name' => 'Alice', 'last_name' => 'A'],
            ['email' => 'bob@example.com', 'first_name' => 'Bob', 'last_name' => 'B'],
        ]);

        $this->assertDatabaseCount('contacts', 2);
        $this->assertDatabaseHas('contacts', ['email' => 'alice@example.com']);
        $this->assertDatabaseHas('contacts', ['email' => 'bob@example.com']);
    }

    public function test_skips_existing_contacts_by_default(): void
    {
        \DB::table('contacts')->insert([
            'email' => 'existing@example.com',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runJob([
            ['email' => 'existing@example.com', 'first_name' => 'New', 'last_name' => 'Name'],
            ['email' => 'new@example.com', 'first_name' => 'New', 'last_name' => 'Contact'],
        ]);

        $this->assertDatabaseCount('contacts', 2);
        $this->assertDatabaseHas('contacts', ['email' => 'existing@example.com', 'first_name' => 'Old']);
        $this->assertDatabaseHas('contacts', ['email' => 'new@example.com']);
    }

    public function test_overwrites_existing_contacts_when_flag_set(): void
    {
        \DB::table('contacts')->insert([
            'email' => 'existing@example.com',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runJob([
            ['email' => 'existing@example.com', 'first_name' => 'Updated', 'last_name' => 'Name'],
        ], overwriteExisting: true);

        $this->assertDatabaseHas('contacts', ['email' => 'existing@example.com', 'first_name' => 'Updated']);
    }
}
