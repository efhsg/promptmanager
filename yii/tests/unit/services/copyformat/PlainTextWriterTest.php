<?php

namespace tests\unit\services\copyformat;

use app\services\copyformat\PlainTextWriter;
use Codeception\Test\Unit;

class PlainTextWriterTest extends Unit
{
    private PlainTextWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new PlainTextWriter();
    }

    public function testWriteSimpleParagraph(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Hello world']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('Hello world', $result);
    }

    public function testWriteStripsFormatting(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Bold', 'attrs' => ['bold' => true]]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('Bold', $result);
    }

    public function testWritePreservesInlineCodeMarkers(): void
    {
        $blocks = [
            ['segments' => [['text' => 'code', 'attrs' => ['code' => true]]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('`code`', $result);
    }

    public function testWriteOrderedList(): void
    {
        $blocks = [
            ['segments' => [['text' => 'First']], 'attrs' => ['list' => 'ordered']],
            ['segments' => [['text' => 'Second']], 'attrs' => ['list' => 'ordered']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame("1. First\n2. Second", $result);
    }

    public function testWriteBulletList(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Item A']], 'attrs' => ['list' => 'bullet']],
            ['segments' => [['text' => 'Item B']], 'attrs' => ['list' => 'bullet']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame("- Item A\n- Item B", $result);
    }

    public function testWriteCodeBlock(): void
    {
        $blocks = [
            ['segments' => [['text' => 'echo "hi";']], 'attrs' => ['code-block' => 'php']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('echo "hi";', $result);
    }

    public function testWriteFromHtmlStripsTagsAndDecodesEntities(): void
    {
        $html = '<p><strong>Bold</strong> &amp; text</p>';

        $result = $this->writer->writeFromHtml($html);

        $this->assertSame('Bold & text', $result);
    }

    public function testWriteMultipleLines(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Line 1']], 'attrs' => []],
            ['segments' => [['text' => 'Line 2']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame("Line 1\nLine 2", $result);
    }

    public function testWriteImageEmbedReturnsUrl(): void
    {
        $blocks = [
            ['segments' => [['embed' => ['image' => 'https://example.com/img.png']]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('https://example.com/img.png', $result);
    }
}
