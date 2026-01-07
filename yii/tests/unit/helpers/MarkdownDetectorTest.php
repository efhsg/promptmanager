<?php

namespace tests\unit\helpers;

use app\helpers\MarkdownDetector;
use Codeception\Test\Unit;

class MarkdownDetectorTest extends Unit
{
    public function testReturnsFalseForEmptyString(): void
    {
        $this->assertFalse(MarkdownDetector::isMarkdown(''));
        $this->assertFalse(MarkdownDetector::isMarkdown('   '));
    }

    public function testReturnsFalseForPlainText(): void
    {
        $text = "This is just plain text.\nNo markdown here.\nJust regular content.";
        $this->assertFalse(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsFalseForSingleMarkdownPattern(): void
    {
        $this->assertFalse(MarkdownDetector::isMarkdown('# Just a header'));
        $this->assertFalse(MarkdownDetector::isMarkdown('**bold only**'));
        $this->assertFalse(MarkdownDetector::isMarkdown('- just a list item'));
    }

    public function testReturnsTrueForHeadersAndBold(): void
    {
        $text = "# Header\nSome **bold** text here.";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForHeadersAndLists(): void
    {
        $text = "# Title\n\n- Item 1\n- Item 2";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForCodeBlockAndHeaders(): void
    {
        $text = "## Usage\n\n```php\necho 'hello';\n```";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForLinksAndBold(): void
    {
        $text = "Check out **this** [link](https://example.com) for more info.";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForBlockquoteAndItalic(): void
    {
        $text = "> This is a quote\n\n*Italic* emphasis here.";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForOrderedListAndInlineCode(): void
    {
        $text = "1. First step\n2. Use `command`\n3. Done";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsFalseForMultipleHeadersOnly(): void
    {
        $text = "# Main Title\n\nSome content.\n\n## Subtitle";
        $this->assertFalse(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForHeadersWithOtherPatterns(): void
    {
        $text = "# Main Title\n\nSome **bold** content.\n\n## Subtitle";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForComplexMarkdown(): void
    {
        $text = <<<MD
            # Project README

            ## Overview
            This project uses **modern** PHP patterns.

            ### Features
            - Fast performance
            - Easy to use
            - Well tested

            ```php
            \$app = new App();
            ```

            For more info, see [documentation](https://docs.example.com).
            MD;
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }
}
