<?php

namespace tests\unit\services\copyformat;

use app\services\copyformat\LlmXmlWriter;
use Codeception\Test\Unit;

class LlmXmlWriterTest extends Unit
{
    private LlmXmlWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new LlmXmlWriter();
    }

    public function testWriteEmptyBlocksReturnsEmptyInstructions(): void
    {
        $blocks = [];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('<instructions></instructions>', $result);
    }

    public function testWriteSingleParagraph(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Do this task']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $expected = "<instructions>\n  <instruction>Do this task</instruction>\n</instructions>";
        $this->assertSame($expected, $result);
    }

    public function testWriteListItemsAsInstructions(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Step one']], 'attrs' => ['list' => 'bullet']],
            ['segments' => [['text' => 'Step two']], 'attrs' => ['list' => 'bullet']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertStringContainsString('<instruction>Step one</instruction>', $result);
        $this->assertStringContainsString('<instruction>Step two</instruction>', $result);
    }

    public function testWriteEscapesXmlSpecialChars(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Use <tag> & "quotes"']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertStringContainsString('&lt;tag&gt;', $result);
        $this->assertStringContainsString('&amp;', $result);
        $this->assertStringContainsString('&quot;quotes&quot;', $result);
    }

    public function testWriteFromPlainText(): void
    {
        $text = "First instruction\n\nSecond instruction";

        $result = $this->writer->writeFromPlainText($text);

        $this->assertStringContainsString('<instruction>First instruction</instruction>', $result);
        $this->assertStringContainsString('<instruction>Second instruction</instruction>', $result);
    }

    public function testWriteFromHtmlConvertsViaMarkdown(): void
    {
        $html = '<ul><li>Item one</li><li>Item two</li></ul>';

        $result = $this->writer->writeFromHtml($html);

        $this->assertStringContainsString('<instructions>', $result);
        $this->assertStringContainsString('</instructions>', $result);
    }

    public function testWriteEachBlockBecomesInstruction(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Line one']], 'attrs' => []],
            ['segments' => [['text' => 'Line two']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertStringContainsString('<instruction>Line one</instruction>', $result);
        $this->assertStringContainsString('<instruction>Line two</instruction>', $result);
    }
}
