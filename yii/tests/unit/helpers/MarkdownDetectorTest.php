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

    public function testReturnsTrueForMultipleHeaders(): void
    {
        $text = "# Main Title\n\nSome content.\n\n## Subtitle";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForHeadersWithOtherPatterns(): void
    {
        $text = "# Main Title\n\nSome **bold** content.\n\n## Subtitle";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForMultipleHeadersWithParagraphs(): void
    {
        $text = "## Summary\n\nThe application handles authentication.\n\n## Recommendations\n\nConsider rate limiting.\n\n## Next Steps\n\nReview the config.";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForBulletsOnly(): void
    {
        $text = "- First item\n- Second item\n- Third item";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForOrderedListOnly(): void
    {
        $text = "1. First step\n2. Second step\n3. Third step";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForHeaderWithHorizontalRule(): void
    {
        $text = "## Section One\n\nContent.\n\n---\n\n## Section Two\n\nMore content.";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsFalseForSingleBullet(): void
    {
        $this->assertFalse(MarkdownDetector::isMarkdown('- just one item'));
    }

    public function testReturnsFalseForSingleOrderedItem(): void
    {
        $this->assertFalse(MarkdownDetector::isMarkdown('1. just one item'));
    }

    public function testReturnsFalseForDashInProseText(): void
    {
        $text = "This is a sentence - with a dash.\nAnother line of text.";
        $this->assertFalse(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForReviewOutput(): void
    {
        $text = "### Critical\n\n- None.\n\n### High\n\n- Save result is ignored.\n- Update result is ignored.\n\n### Medium\n\n- Archive flow is incomplete.";
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForReviewOutputWithFilePaths(): void
    {
        $text = <<<'MD'
            ### Critical

            - None.

            ### High

            - application/modules/admin/controllers/TestPackController.php:80 — saveToModel() result is ignored in actionCreate(), so a failed save still shows success flash + redirect.

            - application/modules/admin/controllers/TestPackController.php:114 — updateAttributes() result is ignored in actionArchive(), so archive failure still shows success flash.

            ### Medium

            - application/modules/admin/views/test-pack/index.php:12 — archive flow is incomplete/inconsistent with existing patterns.

            - application/modules/admin/views/test-pack/index.php:37 — testDefinitionCount in grid triggers per-row counting via application/models/TestPack.php:73, causing N+1 queries on list pages.
            MD;
        $this->assertTrue(MarkdownDetector::isMarkdown($text));
    }

    public function testReturnsTrueForReviewOutputWithWrappedLines(): void
    {
        $text = <<<'MD'
            ### Critical

            - None.

            ### High

            - application/modules/admin/controllers/TestPackController.php:80 — saveToModel() result is ignored in actionCreate(), so a failed save still shows success flash + redirect.

            - application/modules/admin/controllers/TestPackController.php:114 — updateAttributes() result is ignored in actionArchive(), so archive failure still shows success flash.

            ### Medium

            - application/modules/admin/views/test-pack/index.php:12 — archive flow is incomplete/inconsistent with existing patterns: index is always "active only", and there is no archive toggle.

            sticky.

            - application/modules/admin/views/test-pack/index.php:37 — testDefinitionCount in grid triggers per-row counting via application/models/TestPack.php:73, causing N+1 queries on list

            pages.
            MD;
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
