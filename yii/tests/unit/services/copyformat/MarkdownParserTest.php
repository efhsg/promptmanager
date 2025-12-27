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

        // Should have: Title, blank, paragraph, blank, Item 1, Item 2 = 6 blocks
        $this->assertCount(6, $blocks);
        $this->assertSame(['header' => 1], $blocks[0]['attrs']);
        $this->assertSame([], $blocks[1]['attrs']); // blank line
        $this->assertSame('', $blocks[1]['segments'][0]['text']);
        $this->assertSame([], $blocks[2]['attrs']); // paragraph
        $this->assertStringContainsString('bold', $blocks[2]['segments'][1]['text']);
        $this->assertSame([], $blocks[3]['attrs']); // blank line
        $this->assertSame(['list' => 'bullet'], $blocks[4]['attrs']);
        $this->assertSame(['list' => 'bullet'], $blocks[5]['attrs']);
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

    public function testParseEmptyLinesPreserved(): void
    {
        $md = "Line 1\n\n\n\nLine 2";
        $blocks = $this->parser->parse($md);

        // Should have: Line 1, blank, blank, blank, Line 2 = 5 blocks
        $this->assertCount(5, $blocks);
        $this->assertSame('Line 1', $blocks[0]['segments'][0]['text']);
        $this->assertSame('', $blocks[1]['segments'][0]['text']);
        $this->assertSame([], $blocks[1]['attrs']);
        $this->assertSame('', $blocks[2]['segments'][0]['text']);
        $this->assertSame('', $blocks[3]['segments'][0]['text']);
        $this->assertSame('Line 2', $blocks[4]['segments'][0]['text']);
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

    public function testPreserveBlankLinesInComprehensiveMarkdown(): void
    {
        $md = <<<'MD'
            # Heading1

            Test normal

            ## Heading2

            text **bold**

            ### Heading3

            test `code`

            #### Heading4

            > test quote

            ##### Heading5

            ```
            <html lang="en">Code</html>
            ```

            ###### Heading6

            1. Numbered list 1
            2. Numbered list 2
            3. Numbered list 3

            - List item 1
            - List item 1
            - List item 1

            1. Level1
              1. level2
                1. level3
            MD;

        $blocks = $this->parser->parse($md);

        // Expected: 33 blocks total
        // - 6 headers
        // - 3 paragraphs (Test normal, text **bold**, test `code`)
        // - 1 blockquote
        // - 1 code-block line
        // - 6 list items (3 numbered + 3 bullet)
        // - 3 nested list items
        // - 13 blank lines (after each header, after code block, between lists)
        $this->assertCount(33, $blocks, 'Should preserve blank lines as empty paragraph blocks');

        // Verify blank lines are represented as empty paragraph blocks
        $emptyBlocks = array_filter($blocks, function ($block) {
            return $block['attrs'] === []
                && count($block['segments']) === 1
                && $block['segments'][0]['text'] === '';
        });

        $this->assertCount(13, $emptyBlocks, 'Should have 13 blank line blocks');

        // Verify structure: H1, blank, paragraph, blank, H2, blank, paragraph, ...
        $this->assertSame(['header' => 1], $blocks[0]['attrs'], 'First block should be H1');
        $this->assertSame([], $blocks[1]['attrs'], 'Second block should be blank line');
        $this->assertSame('', $blocks[1]['segments'][0]['text'], 'Blank line should have empty text');
        $this->assertSame([], $blocks[2]['attrs'], 'Third block should be paragraph');
        $this->assertSame('Test normal', $blocks[2]['segments'][0]['text']);
    }

    public function testBlankLinesAtDocumentStart(): void
    {
        $md = "\n\n\nContent here";
        $blocks = $this->parser->parse($md);

        $this->assertCount(4, $blocks);
        $this->assertSame('', $blocks[0]['segments'][0]['text'], 'First three blocks should be blank');
        $this->assertSame('', $blocks[1]['segments'][0]['text']);
        $this->assertSame('', $blocks[2]['segments'][0]['text']);
        $this->assertSame('Content here', $blocks[3]['segments'][0]['text']);
    }

    public function testBlankLinesAtDocumentEnd(): void
    {
        $md = "Content here\n\n\n";
        $blocks = $this->parser->parse($md);

        $this->assertCount(4, $blocks);
        $this->assertSame('Content here', $blocks[0]['segments'][0]['text']);
        $this->assertSame('', $blocks[1]['segments'][0]['text'], 'Last three blocks should be blank');
        $this->assertSame('', $blocks[2]['segments'][0]['text']);
        $this->assertSame('', $blocks[3]['segments'][0]['text']);
    }

    public function testBlankLinesWithinCodeBlock(): void
    {
        $md = "```\nline 1\n\nline 3\n```";
        $blocks = $this->parser->parse($md);

        // Code blocks should preserve internal blank lines as code content
        $this->assertCount(3, $blocks);
        $this->assertSame(['code-block' => true], $blocks[0]['attrs']);
        $this->assertSame('line 1', $blocks[0]['segments'][0]['text']);
        $this->assertSame(['code-block' => true], $blocks[1]['attrs']);
        $this->assertSame('', $blocks[1]['segments'][0]['text'], 'Blank line within code block');
        $this->assertSame(['code-block' => true], $blocks[2]['attrs']);
        $this->assertSame('line 3', $blocks[2]['segments'][0]['text']);
    }

    public function testWhitespaceOnlyLines(): void
    {
        $md = "Line 1\n   \n\t\nLine 2";
        $blocks = $this->parser->parse($md);

        // Lines with only spaces/tabs should be treated as blank (via trim())
        $this->assertCount(4, $blocks);
        $this->assertSame('Line 1', $blocks[0]['segments'][0]['text']);
        $this->assertSame('', $blocks[1]['segments'][0]['text'], 'Spaces-only line should be blank');
        $this->assertSame('', $blocks[2]['segments'][0]['text'], 'Tab-only line should be blank');
        $this->assertSame('Line 2', $blocks[3]['segments'][0]['text']);
    }

    public function testDocumentWithOnlyBlankLines(): void
    {
        $md = "\n\n\n";
        $blocks = $this->parser->parse($md);

        // preg_split on "\n\n\n" produces 4 elements: ["", "", "", ""]
        $this->assertCount(4, $blocks);
        foreach ($blocks as $block) {
            $this->assertSame([], $block['attrs']);
            $this->assertSame('', $block['segments'][0]['text']);
        }
    }

    public function testBlankLinesBetweenListItems(): void
    {
        $md = "- Item 1\n\n- Item 2\n\n- Item 3";
        $blocks = $this->parser->parse($md);

        // Blank lines between list items should be preserved
        $this->assertCount(5, $blocks);
        $this->assertSame(['list' => 'bullet'], $blocks[0]['attrs']);
        $this->assertSame('Item 1', $blocks[0]['segments'][0]['text']);
        $this->assertSame([], $blocks[1]['attrs'], 'Blank line between list items');
        $this->assertSame(['list' => 'bullet'], $blocks[2]['attrs']);
        $this->assertSame('Item 2', $blocks[2]['segments'][0]['text']);
        $this->assertSame([], $blocks[3]['attrs']);
        $this->assertSame(['list' => 'bullet'], $blocks[4]['attrs']);
        $this->assertSame('Item 3', $blocks[4]['segments'][0]['text']);
    }

    public function testBlankLinesBeforeAndAfterCodeBlock(): void
    {
        $md = "Text before\n\n```\ncode\n```\n\nText after";
        $blocks = $this->parser->parse($md);

        $this->assertCount(5, $blocks);
        $this->assertSame('Text before', $blocks[0]['segments'][0]['text']);
        $this->assertSame([], $blocks[1]['attrs'], 'Blank before code block');
        $this->assertSame(['code-block' => true], $blocks[2]['attrs']);
        $this->assertSame([], $blocks[3]['attrs'], 'Blank after code block');
        $this->assertSame('Text after', $blocks[4]['segments'][0]['text']);
    }

    public function testManyConsecutiveBlankLines(): void
    {
        $md = "Start\n\n\n\n\n\n\n\n\nEnd";
        $blocks = $this->parser->parse($md);

        // Should preserve all blank lines
        $this->assertCount(10, $blocks);
        $this->assertSame('Start', $blocks[0]['segments'][0]['text']);
        for ($i = 1; $i < 9; $i++) {
            $this->assertSame('', $blocks[$i]['segments'][0]['text'], "Block $i should be blank");
        }
        $this->assertSame('End', $blocks[9]['segments'][0]['text']);
    }

    public function testBlankLinesAroundBlockquote(): void
    {
        $md = "Before\n\n> Quote\n\nAfter";
        $blocks = $this->parser->parse($md);

        $this->assertCount(5, $blocks);
        $this->assertSame('Before', $blocks[0]['segments'][0]['text']);
        $this->assertSame([], $blocks[1]['attrs'], 'Blank before blockquote');
        $this->assertSame(['blockquote' => true], $blocks[2]['attrs']);
        $this->assertSame([], $blocks[3]['attrs'], 'Blank after blockquote');
        $this->assertSame('After', $blocks[4]['segments'][0]['text']);
    }
}
