<?php

namespace tests\unit\services\copyformat;

use app\services\copyformat\MarkdownParser;
use Codeception\Test\Unit;

class MarkdownParserTest extends Unit
{
    private MarkdownParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MarkdownParser();
    }

    public function testParseEmptyString(): void
    {
        $blocks = $this->parser->parse('');

        $this->assertCount(1, $blocks);
        $this->assertSame('', $blocks[0]['segments'][0]['text']);
    }

    public function testParsePlainParagraph(): void
    {
        $blocks = $this->parser->parse('Hello world');

        $this->assertCount(1, $blocks);
        $this->assertSame('Hello world', $blocks[0]['segments'][0]['text']);
        $this->assertSame([], $blocks[0]['attrs']);
    }

    public function testParseH1Header(): void
    {
        $blocks = $this->parser->parse('# Title');

        $this->assertCount(1, $blocks);
        $this->assertSame('Title', $blocks[0]['segments'][0]['text']);
        $this->assertSame(['header' => 1], $blocks[0]['attrs']);
    }

    public function testParseH2ToH6Headers(): void
    {
        $md = "## H2\n### H3\n#### H4\n##### H5\n###### H6";
        $blocks = $this->parser->parse($md);

        $this->assertCount(5, $blocks);
        $this->assertSame(['header' => 2], $blocks[0]['attrs']);
        $this->assertSame(['header' => 3], $blocks[1]['attrs']);
        $this->assertSame(['header' => 4], $blocks[2]['attrs']);
        $this->assertSame(['header' => 5], $blocks[3]['attrs']);
        $this->assertSame(['header' => 6], $blocks[4]['attrs']);
    }

    public function testParseBoldText(): void
    {
        $blocks = $this->parser->parse('This is **bold** text');

        $this->assertCount(1, $blocks);
        $this->assertCount(3, $blocks[0]['segments']);
        $this->assertSame('This is ', $blocks[0]['segments'][0]['text']);
        $this->assertSame('bold', $blocks[0]['segments'][1]['text']);
        $this->assertSame(['bold' => true], $blocks[0]['segments'][1]['attrs']);
        $this->assertSame(' text', $blocks[0]['segments'][2]['text']);
    }

    public function testParseItalicText(): void
    {
        $blocks = $this->parser->parse('This is *italic* text');

        $this->assertCount(1, $blocks);
        $this->assertCount(3, $blocks[0]['segments']);
        $this->assertSame('italic', $blocks[0]['segments'][1]['text']);
        $this->assertSame(['italic' => true], $blocks[0]['segments'][1]['attrs']);
    }

    public function testParseStrikethrough(): void
    {
        $blocks = $this->parser->parse('This is ~~strikethrough~~ text');

        $this->assertCount(1, $blocks);
        $this->assertCount(3, $blocks[0]['segments']);
        $this->assertSame('strikethrough', $blocks[0]['segments'][1]['text']);
        $this->assertSame(['strike' => true], $blocks[0]['segments'][1]['attrs']);
    }

    public function testParseInlineCode(): void
    {
        $blocks = $this->parser->parse('Use `code` here');

        $this->assertCount(1, $blocks);
        $this->assertCount(3, $blocks[0]['segments']);
        $this->assertSame('code', $blocks[0]['segments'][1]['text']);
        $this->assertSame(['code' => true], $blocks[0]['segments'][1]['attrs']);
    }

    public function testParseLink(): void
    {
        $blocks = $this->parser->parse('Click [here](https://example.com) now');

        $this->assertCount(1, $blocks);
        $this->assertCount(3, $blocks[0]['segments']);
        $this->assertSame('here', $blocks[0]['segments'][1]['text']);
        $this->assertSame(['link' => 'https://example.com'], $blocks[0]['segments'][1]['attrs']);
    }

    public function testParseOrderedList(): void
    {
        $md = "1. First\n2. Second\n3. Third";
        $blocks = $this->parser->parse($md);

        $this->assertCount(3, $blocks);
        $this->assertSame(['list' => 'ordered'], $blocks[0]['attrs']);
        $this->assertSame('First', $blocks[0]['segments'][0]['text']);
        $this->assertSame(['list' => 'ordered'], $blocks[1]['attrs']);
        $this->assertSame(['list' => 'ordered'], $blocks[2]['attrs']);
    }

    public function testParseUnorderedList(): void
    {
        $md = "- Item A\n- Item B";
        $blocks = $this->parser->parse($md);

        $this->assertCount(2, $blocks);
        $this->assertSame(['list' => 'bullet'], $blocks[0]['attrs']);
        $this->assertSame('Item A', $blocks[0]['segments'][0]['text']);
        $this->assertSame(['list' => 'bullet'], $blocks[1]['attrs']);
    }

    public function testParseNestedList(): void
    {
        $md = "- Parent\n  - Child\n    - Grandchild";
        $blocks = $this->parser->parse($md);

        $this->assertCount(3, $blocks);
        $this->assertSame(['list' => 'bullet'], $blocks[0]['attrs']);
        $this->assertSame(['list' => 'bullet', 'indent' => 1], $blocks[1]['attrs']);
        $this->assertSame(['list' => 'bullet', 'indent' => 2], $blocks[2]['attrs']);
    }

    public function testParseCheckedList(): void
    {
        $md = "- [x] Done\n- [ ] Todo";
        $blocks = $this->parser->parse($md);

        $this->assertCount(2, $blocks);
        $this->assertSame(['list' => 'checked'], $blocks[0]['attrs']);
        $this->assertSame('Done', $blocks[0]['segments'][0]['text']);
        $this->assertSame(['list' => 'unchecked'], $blocks[1]['attrs']);
        $this->assertSame('Todo', $blocks[1]['segments'][0]['text']);
    }

    public function testParseBlockquote(): void
    {
        $blocks = $this->parser->parse('> Quote text');

        $this->assertCount(1, $blocks);
        $this->assertSame(['blockquote' => true], $blocks[0]['attrs']);
        $this->assertSame('Quote text', $blocks[0]['segments'][0]['text']);
    }

    public function testParseFencedCodeBlock(): void
    {
        $md = "```\nline 1\nline 2\n```";
        $blocks = $this->parser->parse($md);

        $this->assertCount(2, $blocks);
        $this->assertSame(['code-block' => true], $blocks[0]['attrs']);
        $this->assertSame('line 1', $blocks[0]['segments'][0]['text']);
        $this->assertSame(['code-block' => true], $blocks[1]['attrs']);
        $this->assertSame('line 2', $blocks[1]['segments'][0]['text']);
    }

    public function testParseFencedCodeBlockWithLanguage(): void
    {
        $md = "```php\necho 'hi';\n```";
        $blocks = $this->parser->parse($md);

        $this->assertCount(1, $blocks);
        $this->assertSame(['code-block' => 'php'], $blocks[0]['attrs']);
        $this->assertSame("echo 'hi';", $blocks[0]['segments'][0]['text']);
    }

    public function testParseMixedContent(): void
    {
        $md = "# Title\n\nSome **bold** text.\n\n- Item 1\n- Item 2";
        $blocks = $this->parser->parse($md);

        $this->assertCount(4, $blocks);
        $this->assertSame(['header' => 1], $blocks[0]['attrs']);
        $this->assertSame([], $blocks[1]['attrs']);
        $this->assertSame(['list' => 'bullet'], $blocks[2]['attrs']);
        $this->assertSame(['list' => 'bullet'], $blocks[3]['attrs']);
    }

    public function testParseMultipleInlineFormats(): void
    {
        $blocks = $this->parser->parse('**bold** and *italic* and `code`');

        $this->assertCount(1, $blocks);
        $segments = $blocks[0]['segments'];
        $this->assertCount(5, $segments);
        $this->assertSame(['bold' => true], $segments[0]['attrs']);
        $this->assertSame(['italic' => true], $segments[2]['attrs']);
        $this->assertSame(['code' => true], $segments[4]['attrs']);
    }

    public function testParseHeaderWithFormatting(): void
    {
        $blocks = $this->parser->parse('## Header with **bold**');

        $this->assertCount(1, $blocks);
        $this->assertSame(['header' => 2], $blocks[0]['attrs']);
        $this->assertCount(2, $blocks[0]['segments']);
        $this->assertSame('Header with ', $blocks[0]['segments'][0]['text']);
        $this->assertSame('bold', $blocks[0]['segments'][1]['text']);
        $this->assertSame(['bold' => true], $blocks[0]['segments'][1]['attrs']);
    }

    public function testParseListWithFormatting(): void
    {
        $blocks = $this->parser->parse('- Item with **bold** text');

        $this->assertCount(1, $blocks);
        $this->assertSame(['list' => 'bullet'], $blocks[0]['attrs']);
        $this->assertCount(3, $blocks[0]['segments']);
        $this->assertSame(['bold' => true], $blocks[0]['segments'][1]['attrs']);
    }

    public function testParseEmptyLinesSkipped(): void
    {
        $md = "Line 1\n\n\n\nLine 2";
        $blocks = $this->parser->parse($md);

        $this->assertCount(2, $blocks);
        $this->assertSame('Line 1', $blocks[0]['segments'][0]['text']);
        $this->assertSame('Line 2', $blocks[1]['segments'][0]['text']);
    }

    public function testParseAsteriskListMarker(): void
    {
        $blocks = $this->parser->parse('* Item with asterisk');

        $this->assertCount(1, $blocks);
        $this->assertSame(['list' => 'bullet'], $blocks[0]['attrs']);
        $this->assertSame('Item with asterisk', $blocks[0]['segments'][0]['text']);
    }

    public function testParsePlusListMarker(): void
    {
        $blocks = $this->parser->parse('+ Item with plus');

        $this->assertCount(1, $blocks);
        $this->assertSame(['list' => 'bullet'], $blocks[0]['attrs']);
        $this->assertSame('Item with plus', $blocks[0]['segments'][0]['text']);
    }

    public function testParseUnclosedCodeBlock(): void
    {
        $md = "```php\ncode here";
        $blocks = $this->parser->parse($md);

        $this->assertCount(1, $blocks);
        $this->assertSame(['code-block' => 'php'], $blocks[0]['attrs']);
        $this->assertSame('code here', $blocks[0]['segments'][0]['text']);
    }

    public function testRoundTripWithMarkdownWriter(): void
    {
        $originalMd = "# Title\n\nSome **bold** text.\n\n- Item 1\n- Item 2";

        $blocks = $this->parser->parse($originalMd);

        $writer = new \app\services\copyformat\MarkdownWriter();
        $outputMd = $writer->writeFromBlocks($blocks);

        $this->assertStringContainsString('# Title', $outputMd);
        $this->assertStringContainsString('**bold**', $outputMd);
        $this->assertStringContainsString('- Item 1', $outputMd);
    }

    public function testRoundTripWithQuillDeltaWriter(): void
    {
        $md = "# Title\n\nParagraph text";

        $blocks = $this->parser->parse($md);

        $deltaWriter = new \app\services\copyformat\QuillDeltaWriter();
        $deltaJson = $deltaWriter->writeFromBlocks($blocks);

        $this->assertJson($deltaJson);
        $delta = json_decode($deltaJson, true);
        $this->assertArrayHasKey('ops', $delta);
    }
}
