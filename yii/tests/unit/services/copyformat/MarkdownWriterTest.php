<?php

namespace tests\unit\services\copyformat;

use app\services\copyformat\MarkdownWriter;
use Codeception\Test\Unit;

class MarkdownWriterTest extends Unit
{
    private MarkdownWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new MarkdownWriter();
    }

    public function testWriteSimpleParagraph(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Hello world']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('Hello world', $result);
    }

    public function testWriteBoldText(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Bold', 'attrs' => ['bold' => true]]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('**Bold**', $result);
    }

    public function testWriteItalicText(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Italic', 'attrs' => ['italic' => true]]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('*Italic*', $result);
    }

    public function testWriteInlineCode(): void
    {
        $blocks = [
            ['segments' => [['text' => 'code', 'attrs' => ['code' => true]]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('`code`', $result);
    }

    public function testWriteLink(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Link', 'attrs' => ['link' => 'https://example.com']]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('[Link](https://example.com)', $result);
    }

    public function testWriteHeader(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Title']], 'attrs' => ['header' => 2]],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('## Title', $result);
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

    public function testWriteCheckedList(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Done']], 'attrs' => ['list' => 'checked']],
            ['segments' => [['text' => 'Todo']], 'attrs' => ['list' => 'unchecked']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame("- [x] Done\n- [ ] Todo", $result);
    }

    public function testWriteCodeBlock(): void
    {
        $blocks = [
            ['segments' => [['text' => 'echo "hi";']], 'attrs' => ['code-block' => 'php']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $expected = "```php\necho \"hi\";\n```";
        $this->assertSame($expected, $result);
    }

    public function testWriteBlockquote(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Quote text']], 'attrs' => ['blockquote' => true]],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('> Quote text', $result);
    }

    public function testWriteFromHtmlConvertsToMarkdown(): void
    {
        $html = '<p><strong>Bold</strong> text</p>';

        $result = $this->writer->writeFromHtml($html);

        $this->assertStringContainsString('**Bold**', $result);
    }

    public function testWriteImageEmbed(): void
    {
        $blocks = [
            ['segments' => [['embed' => ['image' => 'https://example.com/img.png']]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame('![](https://example.com/img.png)', $result);
    }

    // --- Nested/indented list tests ---

    public function testWriteNestedBulletList(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Parent']], 'attrs' => ['list' => 'bullet']],
            ['segments' => [['text' => 'Child']], 'attrs' => ['list' => 'bullet', 'indent' => 1]],
            ['segments' => [['text' => 'Grandchild']], 'attrs' => ['list' => 'bullet', 'indent' => 2]],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame("- Parent\n    - Child\n        - Grandchild", $result);
    }

    public function testWriteNestedOrderedList(): void
    {
        $blocks = [
            ['segments' => [['text' => 'First']], 'attrs' => ['list' => 'ordered']],
            ['segments' => [['text' => 'Sub A']], 'attrs' => ['list' => 'ordered', 'indent' => 1]],
            ['segments' => [['text' => 'Sub B']], 'attrs' => ['list' => 'ordered', 'indent' => 1]],
            ['segments' => [['text' => 'Second']], 'attrs' => ['list' => 'ordered']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame("1. First\n    1. Sub A\n    2. Sub B\n2. Second", $result);
    }

    // --- List type transition tests ---

    public function testWriteListTypeTransitionOrderedToBullet(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Ordered']], 'attrs' => ['list' => 'ordered']],
            ['segments' => [['text' => 'Bullet']], 'attrs' => ['list' => 'bullet']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame("1. Ordered\n- Bullet", $result);
    }

    public function testWriteListTypeTransitionBulletToChecked(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Bullet']], 'attrs' => ['list' => 'bullet']],
            ['segments' => [['text' => 'Checked']], 'attrs' => ['list' => 'checked']],
            ['segments' => [['text' => 'Unchecked']], 'attrs' => ['list' => 'unchecked']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame("- Bullet\n- [x] Checked\n- [ ] Unchecked", $result);
    }

    // --- Multi-line code block tests ---

    public function testWriteMultiLineCodeBlock(): void
    {
        $blocks = [
            ['segments' => [['text' => 'line 1']], 'attrs' => ['code-block' => 'php']],
            ['segments' => [['text' => 'line 2']], 'attrs' => ['code-block' => 'php']],
            ['segments' => [['text' => 'line 3']], 'attrs' => ['code-block' => 'php']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $expected = "```php\nline 1\nline 2\nline 3\n```";
        $this->assertSame($expected, $result);
    }

    public function testWriteCodeBlockLanguageChange(): void
    {
        $blocks = [
            ['segments' => [['text' => 'echo "php";']], 'attrs' => ['code-block' => 'php']],
            ['segments' => [['text' => 'console.log("js");']], 'attrs' => ['code-block' => 'javascript']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $expected = "```php\necho \"php\";\n```\n\n```javascript\nconsole.log(\"js\");\n```";
        $this->assertSame($expected, $result);
    }

    public function testWriteCodeBlockPlainLanguage(): void
    {
        $blocks = [
            ['segments' => [['text' => 'plain text']], 'attrs' => ['code-block' => 'plain']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $expected = "```\nplain text\n```";
        $this->assertSame($expected, $result);
    }

    public function testWriteCodeBlockEmptyLanguage(): void
    {
        $blocks = [
            ['segments' => [['text' => 'no language']], 'attrs' => ['code-block' => true]],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $expected = "```\nno language\n```";
        $this->assertSame($expected, $result);
    }

    // --- Multi-line blockquote tests ---

    public function testWriteBlockquoteWithNewlines(): void
    {
        $blocks = [
            ['segments' => [['text' => "Line one\nLine two\nLine three"]], 'attrs' => ['blockquote' => true]],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $expected = "> Line one\n> Line two\n> Line three";
        $this->assertSame($expected, $result);
    }

    public function testWriteConsecutiveBlockquotes(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Quote one']], 'attrs' => ['blockquote' => true]],
            ['segments' => [['text' => 'Quote two']], 'attrs' => ['blockquote' => true]],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $expected = "> Quote one\n\n> Quote two";
        $this->assertSame($expected, $result);
    }

    // --- Escaping edge case tests ---

    public function testWriteTextWithAsterisks(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Use *wildcards* for matching']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        // Current implementation: minimal escaping (backslashes only)
        // Asterisks are NOT escaped, so they become emphasis
        $this->assertSame('Use *wildcards* for matching', $result);
    }

    public function testWriteTextWithUnderscores(): void
    {
        $blocks = [
            ['segments' => [['text' => 'variable_name_here']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        // Current implementation: underscores are NOT escaped
        $this->assertSame('variable_name_here', $result);
    }

    public function testWriteTextWithBackslashes(): void
    {
        $blocks = [
            ['segments' => [['text' => 'path\\to\\file']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        // Current implementation: backslashes ARE escaped
        $this->assertSame('path\\\\to\\\\file', $result);
    }

    public function testWriteTextWithBrackets(): void
    {
        $blocks = [
            ['segments' => [['text' => 'See [section] for details']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        // Current implementation: brackets are NOT escaped
        $this->assertSame('See [section] for details', $result);
    }

    public function testWriteInlineCodeWithBackticks(): void
    {
        $blocks = [
            ['segments' => [['text' => 'code`with`ticks', 'attrs' => ['code' => true]]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        // Current implementation: backticks inside code are NOT escaped
        $this->assertSame('`code`with`ticks`', $result);
    }

    public function testWriteLinkWithSpecialCharsInUrl(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Wiki', 'attrs' => ['link' => 'https://example.com/page_(section)']]], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        // Current implementation: parentheses in URLs are NOT escaped
        $this->assertSame('[Wiki](https://example.com/page_(section))', $result);
    }

    // --- Content transition tests ---

    public function testWriteListToParagraphTransition(): void
    {
        $blocks = [
            ['segments' => [['text' => 'List item']], 'attrs' => ['list' => 'bullet']],
            ['segments' => [['text' => 'Paragraph after list']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        // Expect blank line between list and paragraph
        $this->assertSame("- List item\n\nParagraph after list", $result);
    }

    public function testWriteCodeBlockToParagraphTransition(): void
    {
        $blocks = [
            ['segments' => [['text' => 'code here']], 'attrs' => ['code-block' => 'php']],
            ['segments' => [['text' => 'Paragraph after code']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        // Expect blank line between code block and paragraph
        $expected = "```php\ncode here\n```\n\nParagraph after code";
        $this->assertSame($expected, $result);
    }

    public function testWriteHeaderToParagraphTransition(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Header']], 'attrs' => ['header' => 1]],
            ['segments' => [['text' => 'Paragraph after header']], 'attrs' => []],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        // Expect blank line between header and paragraph
        $this->assertSame("# Header\n\nParagraph after header", $result);
    }

    public function testWriteParagraphToListTransition(): void
    {
        $blocks = [
            ['segments' => [['text' => 'Intro paragraph']], 'attrs' => []],
            ['segments' => [['text' => 'First item']], 'attrs' => ['list' => 'bullet']],
            ['segments' => [['text' => 'Second item']], 'attrs' => ['list' => 'bullet']],
        ];

        $result = $this->writer->writeFromBlocks($blocks);

        $this->assertSame("Intro paragraph\n\n- First item\n- Second item", $result);
    }
}
