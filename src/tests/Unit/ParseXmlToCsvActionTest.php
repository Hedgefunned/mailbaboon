<?php

namespace Tests\Unit;

use App\Actions\Import\ParseXmlToCsvAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParseXmlToCsvActionTest extends TestCase
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

    /** Parse an XML string through the action, returns [result, parsed_csv_rows] */
    private function parse(string $xmlContent): array
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('imports');

        $tmp = tempnam(sys_get_temp_dir(), 'xml_test_');
        file_put_contents($tmp, $xmlContent);
        $file = new UploadedFile($tmp, 'test.xml', 'text/xml', null, true);

        $result = (new ParseXmlToCsvAction)->handle($file);

        $rows = array_values(array_map(
            'str_getcsv',
            array_filter(explode("\n", Storage::disk('local')->get($result['csv_path'])))
        ));

        return [$result, $rows];
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_valid_email_writes_valid_csv_row(): void
    {
        [, $rows] = $this->parse($this->xml($this->item('user@example.com', 'John', 'Doe')));

        $this->assertSame(['email', 'first_name', 'last_name', 'is_valid', 'failure_reason'], $rows[0]);
        $this->assertSame(['user@example.com', 'John', 'Doe', '1', ''], $rows[1]);
    }

    public function test_invalid_email_writes_rejected_csv_row(): void
    {
        [, $rows] = $this->parse($this->xml($this->item('not-an-email', 'Jane', 'Doe')));

        $this->assertSame(['not-an-email', 'Jane', 'Doe', '0', 'INVALID EMAIL'], $rows[1]);
    }

    public function test_email_is_lowercased(): void
    {
        [, $rows] = $this->parse($this->xml($this->item('USER@EXAMPLE.COM')));

        $this->assertSame('user@example.com', $rows[1][0]);
    }

    public function test_html_entities_in_names_are_decoded(): void
    {
        [, $rows] = $this->parse($this->xml($this->item('user@example.com', 'John &amp; Jane', 'O&apos;Brien')));

        $this->assertSame('John & Jane', $rows[1][1]);
        $this->assertSame("O'Brien", $rows[1][2]);
    }

    public function test_result_has_csv_path_and_execution_time(): void
    {
        [$result] = $this->parse($this->xml($this->item('user@example.com')));

        $this->assertStringStartsWith('imports/', $result['csv_path']);
        $this->assertIsFloat($result['execution_time_ms']);
    }

    public function test_handles_item_spanning_4kb_chunk_boundary(): void
    {
        // Craft XML so the second <item>'s opening tag straddles the 4096-byte
        // read boundary: bytes 4093-4095 contain "<it", bytes 4096-4098 contain "em>".
        // The parser keeps a 16-byte tail when no complete <item> is found, so the
        // split tag is reassembled on the next read and both items must be extracted.
        $header = '<?xml version="1.0"?><list>'; // 27 bytes
        $firstItem = $this->item('first@example.com', 'A', 'B'); // 95 bytes
        $padLength = 4093 - (strlen($header) + strlen($firstItem)); // = 3971
        $secondItem = $this->item('second@example.com', 'C', 'D');

        $xml = $header.str_repeat(' ', 0) // keep for readability
            .$firstItem
            .str_repeat(' ', $padLength)
            .$secondItem
            .'</list>';

        // Sanity: second <item> truly starts at byte 4093
        $this->assertSame(4093, strpos($xml, '<item>', 28));

        [, $rows] = $this->parse($xml);

        $this->assertCount(3, $rows); // header + 2 data rows
        $this->assertSame('first@example.com', $rows[1][0]);
        $this->assertSame('second@example.com', $rows[2][0]);
    }
}
