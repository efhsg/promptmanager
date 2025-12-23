<?php

namespace tests\unit\services\copyformat;

use app\services\copyformat\QuillDeltaWriter;
use Codeception\Test\Unit;

class QuillDeltaWriterTest extends Unit
{
    private QuillDeltaWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new QuillDeltaWriter();
    }

    public function testWriteFromBlocksProducesValidJson(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Hello']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('ops', $decoded);
    }

    public function testWriteFromHtmlReturnsUnchanged(): void
    {
        $html = '<p>Hello</p>';

        $result = $this->writer->writeFromHtml($html);

        $this->assertSame($html, $result);
    }

    public function testWriteFromPlainTextReturnsTrimmed(): void
    {
        $text = '  Hello world  ';

        $result = $this->writer->writeFromPlainText($text);

        $this->assertSame('Hello world', $result);
    }

    public function testWritePreservesBlockAttributes(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Header']], 'attrs' => ['header' => 1]],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $decoded = json_decode($result, true);
        $this->assertArrayHasKey('ops', $decoded);
        $op = $decoded['ops'][0] ?? [];
        $this->assertArrayHasKey('attributes', $op);
        $this->assertSame(1, $op['attributes']['header']);
    }

    public function testWritePreservesInlineAttributes(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Bold', 'attrs' => ['bold' => true]]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $decoded = json_decode($result, true);
        $op = $decoded['ops'][0] ?? [];
        $this->assertArrayHasKey('attributes', $op);
        $this->assertTrue($op['attributes']['bold']);
    }
}
