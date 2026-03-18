<?php

namespace Tests\Feature;

use App\Actions\Import\InsertValidContactsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InsertValidContactsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inserts_only_valid_contacts(): void
    {
        DB::table('contact_imports')->insert([
            ['email' => 'alice@example.com', 'first_name' => 'Alice', 'last_name' => 'A', 'is_valid' => 1, 'failure_reason' => null],
            ['email' => 'bob@example.com',   'first_name' => 'Bob',   'last_name' => 'B', 'is_valid' => 1, 'failure_reason' => null],
            ['email' => 'bad@example.com',   'first_name' => 'Eve',   'last_name' => 'E', 'is_valid' => 0, 'failure_reason' => 'INVALID EMAIL'],
        ]);

        (new InsertValidContactsAction)->handle();

        $this->assertDatabaseCount('contacts', 2);
        $this->assertDatabaseHas('contacts', ['email' => 'alice@example.com']);
        $this->assertDatabaseHas('contacts', ['email' => 'bob@example.com']);
        $this->assertDatabaseMissing('contacts', ['email' => 'bad@example.com']);
    }

    public function test_returns_insert_time_ms(): void
    {
        $result = (new InsertValidContactsAction)->handle();

        $this->assertArrayHasKey('insert_valid_contacts_time_ms', $result);
        $this->assertIsFloat($result['insert_valid_contacts_time_ms']);
    }
}
