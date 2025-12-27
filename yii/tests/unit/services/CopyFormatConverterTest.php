<?php

namespace tests\unit\services;

use app\services\CopyFormatConverter;
use Codeception\Test\Unit;
use common\enums\CopyType;

class CopyFormatConverterTest extends Unit
{
    private CopyFormatConverter $converter;

    protected function _before(): void
    {
        $this->converter = new CopyFormatConverter();
    }

    public function testConvertsInlineFormattingToMarkdown(): void
    {
        $delta = json_encode([
            'ops' => [
                ['insert' => 'Hello '],
                ['insert' => 'world', 'attributes' => ['bold' => true]],
                ['insert' => "\n"],
            ],
        ]);

        $result = $this->converter->convertFromQuillDelta($delta, CopyType::MD);

        $this->assertSame('Hello **world**', $result);
    }

    public function testConvertsListsAndCodeBlocksToMarkdown(): void
    {
        $delta = json_encode([
            'ops' => [
                ['insert' => "Item 1\n", 'attributes' => ['list' => 'ordered']],
                ['insert' => "Item 2\n", 'attributes' => ['list' => 'checked']],
                ['insert' => "Snippet\n", 'attributes' => ['code-block' => 'php']],
                ['insert' => "More\n", 'attributes' => ['code-block' => 'php']],
            ],
        ]);

        $expected = <<<MD
            1. Item 1
            - [x] Item 2

            ```php
            Snippet
            More
            ```
            MD;

        $result = $this->converter->convertFromQuillDelta($delta, CopyType::MD);

        $this->assertSame(trim($expected), $result);
    }

    public function testBuildsLlmXmlFromMarkdown(): void
    {
        $delta = json_encode([
            'ops' => [
                ['insert' => "Step 1\n"],
                ['insert' => "Bullet item\n", 'attributes' => ['list' => 'bullet']],
                ['insert' => "Step 2\n"],
            ],
        ]);

        $result = $this->converter->convertFromQuillDelta($delta, CopyType::LLM_XML);

        $expected = <<<XML
            <instructions>
              <instruction>Step 1</instruction>
              <instruction>Bullet item</instruction>
              <instruction>Step 2</instruction>
            </instructions>
            XML;

        $this->assertSame($expected, $result);
    }

    public function testConvertsDeltaToHtml(): void
    {
        $delta = json_encode([
            'ops' => [
                ['insert' => "Title\n", 'attributes' => ['header' => 2]],
                ['insert' => 'Paragraph with '],
                ['insert' => 'link', 'attributes' => ['link' => 'https://example.com']],
                ['insert' => "\n"],
            ],
        ]);

        $result = $this->converter->convertFromQuillDelta($delta, CopyType::HTML);

        $expected = <<<HTML
            <h2>Title</h2>
            <p>Paragraph with <a href="https://example.com">link</a></p>
            HTML;

        $this->assertSame($expected, $result);
    }

    public function testConvertsDeltaToPlainText(): void
    {
        $delta = json_encode([
            'ops' => [
                ['insert' => 'Line '],
                ['insert' => "one\nLine two", 'attributes' => ['italic' => true]],
                ['insert' => "\n"],
            ],
        ]);

        $result = $this->converter->convertFromQuillDelta($delta, CopyType::TEXT);

        $this->assertSame("Line one\nLine two", $result);
    }
}
