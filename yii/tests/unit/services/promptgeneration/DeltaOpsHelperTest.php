<?php

namespace tests\unit\services\promptgeneration;

use app\services\promptgeneration\DeltaOpsHelper;
use Codeception\Test\Unit;

class DeltaOpsHelperTest extends Unit
{
    private DeltaOpsHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new DeltaOpsHelper();
    }

    // --- extractOpsFromDelta tests ---

    public function testExtractOpsFromValidDelta(): void
    {
        $json = '{"ops":[{"insert":"Hello\n"}]}';

        $result = $this->helper->extractOpsFromDelta($json);

        $this->assertSame([['insert' => "Hello\n"]], $result);
    }

    public function testExtractOpsFromEmptyStringReturnsEmptyArray(): void
    {
        $result = $this->helper->extractOpsFromDelta('');

        $this->assertSame([], $result);
    }

    public function testExtractOpsFromInvalidJsonReturnsFallbackOp(): void
    {
        $invalidJson = 'not valid json';

        $result = $this->helper->extractOpsFromDelta($invalidJson);

        $this->assertSame([['insert' => 'not valid json']], $result);
    }

    public function testExtractOpsFromJsonWithoutOpsKeyReturnsFallbackOp(): void
    {
        $json = '{"foo":"bar"}';

        $result = $this->helper->extractOpsFromDelta($json);

        $this->assertSame([['insert' => '{"foo":"bar"}']], $result);
    }

    public function testExtractOpsFromJsonWithNonArrayOpsReturnsFallbackOp(): void
    {
        $json = '{"ops":"not an array"}';

        $result = $this->helper->extractOpsFromDelta($json);

        $this->assertSame([['insert' => '{"ops":"not an array"}']], $result);
    }

    // --- extractPlainTextFromDelta tests ---

    public function testExtractPlainTextFromValidDelta(): void
    {
        $json = '{"ops":[{"insert":"Hello "},{"insert":"World\n"}]}';

        $result = $this->helper->extractPlainTextFromDelta($json);

        $this->assertSame('Hello World', $result);
    }

    public function testExtractPlainTextFromEmptyStringReturnsEmptyString(): void
    {
        $result = $this->helper->extractPlainTextFromDelta('');

        $this->assertSame('', $result);
    }

    public function testExtractPlainTextFromInvalidJsonReturnsOriginal(): void
    {
        $invalidJson = 'plain text fallback';

        $result = $this->helper->extractPlainTextFromDelta($invalidJson);

        $this->assertSame('plain text fallback', $result);
    }

    public function testExtractPlainTextFromJsonWithoutOpsReturnsOriginal(): void
    {
        $json = '{"foo":"bar"}';

        $result = $this->helper->extractPlainTextFromDelta($json);

        $this->assertSame('{"foo":"bar"}', $result);
    }

    public function testExtractPlainTextIgnoresNonStringInserts(): void
    {
        $json = '{"ops":[{"insert":"Text"},{"insert":{"image":"url"}},{"insert":" more\n"}]}';

        $result = $this->helper->extractPlainTextFromDelta($json);

        $this->assertSame('Text more', $result);
    }

    // --- analyzeFieldContent tests ---

    public function testAnalyzeFieldContentDetectsListBlock(): void
    {
        $ops = [
            ['insert' => "Item\n", 'attributes' => ['list' => 'bullet']],
        ];

        $result = $this->helper->analyzeFieldContent($ops);

        $this->assertTrue($result['isListBlock']);
        $this->assertFalse($result['isCodeBlock']);
    }

    public function testAnalyzeFieldContentDetectsCodeBlock(): void
    {
        $ops = [
            ['insert' => "code\n", 'attributes' => ['code-block' => 'php']],
        ];

        $result = $this->helper->analyzeFieldContent($ops);

        $this->assertFalse($result['isListBlock']);
        $this->assertTrue($result['isCodeBlock']);
    }

    public function testAnalyzeFieldContentDetectsBoth(): void
    {
        $ops = [
            ['insert' => "Item\n", 'attributes' => ['list' => 'bullet']],
            ['insert' => "code\n", 'attributes' => ['code-block' => 'php']],
        ];

        $result = $this->helper->analyzeFieldContent($ops);

        $this->assertTrue($result['isListBlock']);
        $this->assertTrue($result['isCodeBlock']);
    }

    public function testAnalyzeFieldContentDetectsNeither(): void
    {
        $ops = [
            ['insert' => "Plain text\n"],
        ];

        $result = $this->helper->analyzeFieldContent($ops);

        $this->assertFalse($result['isListBlock']);
        $this->assertFalse($result['isCodeBlock']);
    }

    public function testAnalyzeEmptyOps(): void
    {
        $result = $this->helper->analyzeFieldContent([]);

        $this->assertFalse($result['isListBlock']);
        $this->assertFalse($result['isCodeBlock']);
    }

    // --- removeConsecutiveNewlines tests ---

    public function testRemoveConsecutiveNewlinesCollapsesMultiple(): void
    {
        $ops = [
            ['insert' => "Line1\n\n\nLine2\n"],
        ];

        $result = $this->helper->removeConsecutiveNewlines($ops);

        $this->assertSame([['insert' => "Line1\nLine2\n"]], $result);
    }

    public function testRemoveConsecutiveNewlinesPreservesSingle(): void
    {
        $ops = [
            ['insert' => "Line1\nLine2\n"],
        ];

        $result = $this->helper->removeConsecutiveNewlines($ops);

        $this->assertSame([['insert' => "Line1\nLine2\n"]], $result);
    }

    public function testRemoveConsecutiveNewlinesPreservesNonStringOps(): void
    {
        $ops = [
            ['insert' => ['image' => 'url']],
        ];

        $result = $this->helper->removeConsecutiveNewlines($ops);

        $this->assertSame([['insert' => ['image' => 'url']]], $result);
    }

    public function testRemoveConsecutiveNewlinesRemovesEmptyStringOps(): void
    {
        $ops = [
            ['insert' => ''],
            ['insert' => 'text'],
        ];

        $result = $this->helper->removeConsecutiveNewlines($ops);

        $this->assertSame([['insert' => 'text']], $result);
    }

    public function testRemoveConsecutiveNewlinesKeepsEmptyWithAttributes(): void
    {
        $ops = [
            ['insert' => '', 'attributes' => ['bold' => true]],
            ['insert' => 'text'],
        ];

        $result = $this->helper->removeConsecutiveNewlines($ops);

        $this->assertCount(2, $result);
        $this->assertSame(['insert' => '', 'attributes' => ['bold' => true]], $result[0]);
    }

    public function testRemoveConsecutiveNewlinesHandlesMultipleOps(): void
    {
        $ops = [
            ['insert' => "A\n\n"],
            ['insert' => "\n\nB\n"],
        ];

        $result = $this->helper->removeConsecutiveNewlines($ops);

        $this->assertSame([['insert' => "A\n"], ['insert' => "\nB\n"]], $result);
    }

    // --- removeEmptyLinesBetweenListItems tests (called by removeConsecutiveNewlines) ---

    public function testRemoveConsecutiveNewlinesStripsEmbeddedLeadingNewlinesAfterListItems(): void
    {
        // Actual pattern from production: leading newline EMBEDDED in text content
        $ops = [
            ['insert' => 'First item'],
            ['insert' => "\n", 'attributes' => ['list' => 'ordered']],
            ['insert' => "\nSecond item"],  // Leading \n embedded - THIS is the real pattern
            ['insert' => "\n", 'attributes' => ['list' => 'ordered']],
        ];

        $result = $this->helper->removeConsecutiveNewlines($ops);

        // Leading newline should be stripped from "Second item"
        $this->assertCount(4, $result);
        $this->assertSame('First item', $result[0]['insert']);
        $this->assertSame('Second item', $result[2]['insert']); // No leading \n
        $this->assertSame('ordered', $result[3]['attributes']['list']);
    }

    public function testRemoveConsecutiveNewlinesKeepsNewlinesOutsideLists(): void
    {
        // Plain newline NOT between list items should be kept
        $ops = [
            ['insert' => "Paragraph\n"],
            ['insert' => "\n"],  // blank line between paragraphs - should be kept (after removeConsecutive... collapses it)
            ['insert' => "Another paragraph\n"],
        ];

        $result = $this->helper->removeConsecutiveNewlines($ops);

        // The structure should be preserved (though consecutive newlines collapsed)
        $plainText = '';
        foreach ($result as $op) {
            if (is_string($op['insert'])) {
                $plainText .= $op['insert'];
            }
        }
        $this->assertStringContainsString('Paragraph', $plainText);
        $this->assertStringContainsString('Another paragraph', $plainText);
    }

    public function testRemoveConsecutiveNewlinesHandlesMultiplePlainNewlinesBetweenListItems(): void
    {
        // Multiple plain newlines between list items
        $ops = [
            ['insert' => 'Item 1'],
            ['insert' => "\n", 'attributes' => ['list' => 'ordered']],
            ['insert' => "\n"],  // first plain newline
            ['insert' => "\n"],  // second plain newline
            ['insert' => 'Item 2'],
            ['insert' => "\n", 'attributes' => ['list' => 'ordered']],
        ];

        $result = $this->helper->removeConsecutiveNewlines($ops);

        // All plain newlines between list items should be removed
        $listCount = 0;
        foreach ($result as $op) {
            if (isset($op['attributes']['list'])) {
                $listCount++;
            }
        }
        $this->assertSame(2, $listCount, 'Should have exactly 2 list-attributed newlines');
    }
}
