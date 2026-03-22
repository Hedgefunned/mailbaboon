<?php

namespace Tests\Unit;

use App\Actions\Import\ParseXmlToChunksAction;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ParseXmlToChunksActionTest extends TestCase
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    private function item(string $email, string $first = 'A', string $last = 'B'): string
    {
        return "<item><email>{$email}</email><first_name>{$first}</first_name><last_name>{$last}</last_name></item>";
    }

    private function xml(string ...$items): string
    {
        return '<?xml version="1.0"?><list>'.implode('', $items).'</list>';
    }

    private function parse(string $xmlContent, int $chunkSize = 100): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xml_test_');
        file_put_contents($tmp, $xmlContent);
        $file = new UploadedFile($tmp, 'test.xml', 'text/xml', null, true);

        return (new ParseXmlToChunksAction)->handle($file, $chunkSize);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_valid_email_appears_in_valid_chunks(): void
    {
        $result = $this->parse($this->xml($this->item('user@example.com', 'John', 'Doe')));

        $this->assertCount(1, $result['valid_chunks']);
        $this->assertCount(1, $result['valid_chunks'][0]);
        $this->assertSame([
            'email' => 'user@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ], $result['valid_chunks'][0][0]);
    }

    public function test_invalid_email_appears_in_invalid_rows(): void
    {
        $result = $this->parse($this->xml($this->item('not-an-email', 'Jane', 'Doe')));

        $this->assertEmpty($result['valid_chunks']);
        $this->assertCount(1, $result['invalid_rows']);
        $this->assertSame('INVALID EMAIL', $result['invalid_rows'][0]['failure_reason']);
        $this->assertSame(0, $result['invalid_rows'][0]['is_valid']);
    }

    public function test_duplicate_email_in_file_marked_as_duplicate(): void
    {
        $result = $this->parse($this->xml(
            $this->item('user@example.com', 'First', 'One'),
            $this->item('user@example.com', 'Second', 'Two'),
        ));

        $this->assertCount(1, $result['valid_chunks'][0]); // only one valid
        $this->assertCount(1, $result['invalid_rows']);
        $this->assertSame('DUPLICATE_IN_FILE', $result['invalid_rows'][0]['failure_reason']);
    }

    public function test_email_is_lowercased(): void
    {
        $result = $this->parse($this->xml($this->item('USER@EXAMPLE.COM')));

        $this->assertSame('user@example.com', $result['valid_chunks'][0][0]['email']);
    }

    public function test_html_entities_in_names_are_decoded(): void
    {
        $result = $this->parse($this->xml($this->item('user@example.com', 'John &amp; Jane', 'O&apos;Brien')));

        $this->assertSame('John & Jane', $result['valid_chunks'][0][0]['first_name']);
        $this->assertSame("O'Brien", $result['valid_chunks'][0][0]['last_name']);
    }

    public function test_chunks_respect_chunk_size(): void
    {
        $items = $this->xml(
            $this->item('a@example.com'),
            $this->item('b@example.com'),
            $this->item('c@example.com'),
        );

        $result = $this->parse($items, chunkSize: 2);

        $this->assertCount(2, $result['valid_chunks']);
        $this->assertCount(2, $result['valid_chunks'][0]);
        $this->assertCount(1, $result['valid_chunks'][1]);
    }

    public function test_returns_correct_counts(): void
    {
        $result = $this->parse($this->xml(
            $this->item('valid@example.com'),
            $this->item('not-an-email'),
            $this->item('valid@example.com'), // duplicate
        ));

        $this->assertSame(3, $result['total_records']);
        $this->assertSame(1, $result['invalid_records']);
        $this->assertSame(1, $result['duplicates_in_file']);
        $this->assertIsFloat($result['execution_time_ms']);
    }

    public function test_handles_item_spanning_4kb_chunk_boundary(): void
    {
        $header = '<?xml version="1.0"?><list>';
        $firstItem = $this->item('first@example.com', 'A', 'B');
        $padLength = 4093 - (strlen($header) + strlen($firstItem));
        $secondItem = $this->item('second@example.com', 'C', 'D');

        $xml = $header
            .$firstItem
            .str_repeat(' ', $padLength)
            .$secondItem
            .'</list>';

        $this->assertSame(4093, strpos($xml, '<item>', 28));

        $result = $this->parse($xml);

        $allValid = array_merge(...$result['valid_chunks']);
        $this->assertCount(2, $allValid);
        $this->assertSame('first@example.com', $allValid[0]['email']);
        $this->assertSame('second@example.com', $allValid[1]['email']);
    }
}
