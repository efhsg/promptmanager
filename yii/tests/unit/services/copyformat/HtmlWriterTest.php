<?php

namespace tests\unit\services\copyformat;

use app\services\copyformat\HtmlWriter;
use Codeception\Test\Unit;

class HtmlWriterTest extends Unit
{
    private HtmlWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new HtmlWriter();
    }

    public function testWriteSimpleParagraph(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Hello world']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('<p>Hello world</p>', $result);
    }

    public function testWriteBoldText(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Bold', 'attrs' => ['bold' => true]]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('<p><strong>Bold</strong></p>', $result);
    }

    public function testWriteItalicText(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Italic', 'attrs' => ['italic' => true]]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('<p><em>Italic</em></p>', $result);
    }

    public function testWriteInlineCode(): void
    {
        $blocks = [
            ['segments' => [['text' => 'code', 'attrs' => ['code' => true]]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('<p><code>code</code></p>', $result);
    }

    public function testWriteLink(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Link', 'attrs' => ['link' => 'https://example.com']]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('<p><a href="https://example.com">Link</a></p>', $result);
    }

    public function testWriteHeader(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Title']], 'attrs' => ['header' => 2]],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('<h2>Title</h2>', $result);
    }

    public function testWriteOrderedList(): void
    {
        $blocks = [
            ['segments' => [['text' => 'First']], 'attrs' => ['list' => 'ordered']],
            ['segments' => [['text' => 'Second']], 'attrs' => ['list' => 'ordered']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertStringContainsString('<ol>', $result);
        $this->assertStringContainsString('<li>First</li>', $result);
        $this->assertStringContainsString('<li>Second</li>', $result);
        $this->assertStringContainsString('</ol>', $result);
    }

    public function testWriteBulletList(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Item A']], 'attrs' => ['list' => 'bullet']],
            ['segments' => [['text' => 'Item B']], 'attrs' => ['list' => 'bullet']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>Item A</li>', $result);
        $this->assertStringContainsString('<li>Item B</li>', $result);
        $this->assertStringContainsString('</ul>', $result);
    }

    public function testWriteCodeBlock(): void
    {
        $blocks = [
            ['segments' => [['text' => 'echo "hi";']], 'attrs' => ['code-block' => 'php']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertStringContainsString('<pre><code class="language-php">', $result);
        $this->assertStringContainsString('echo &quot;hi&quot;;', $result);
        $this->assertStringContainsString('</code></pre>', $result);
    }

    public function testWriteBlockquote(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Quote text']], 'attrs' => ['blockquote' => true]],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('<blockquote><p>Quote text</p></blockquote>', $result);
    }

    public function testWriteFromPlainTextEscapesHtml(): void
    {
        $text = '<script>alert("xss")</script>';

        $result = $this->writer->writeFromPlainText($text);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testWriteFromPlainTextConvertsNewlines(): void
    {
        $text = "Line 1\nLine 2";

        $result = $this->writer->writeFromPlainText($text);

        $this->assertStringContainsString('<br />', $result);
    }

    public function testWriteImageEmbed(): void
    {
        $blocks = [
            ['segments' => [['embed' => ['image' => 'https://example.com/img.png']]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertStringContainsString('<img src="https://example.com/img.png"', $result);
    }
}
