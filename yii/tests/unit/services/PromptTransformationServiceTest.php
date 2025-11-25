<?php

namespace tests\unit\services;

use app\services\PromptTransformationService;
use Codeception\Test\Unit;

class PromptTransformationServiceTest extends Unit
{
    private PromptTransformationService $service;

    protected function _before(): void
    {
        $this->service = new PromptTransformationService();
    }

    public function testDetectCodeReturnsTrueWhenPhpTagPresent(): void
    {
        $result = $this->service->detectCode("Example <?PHP echo 'test'; ?> snippet");

        $this->assertTrue($result);
    }

    public function testDetectCodeReturnsFalseWhenPhpTagMissing(): void
    {
        $result = $this->service->detectCode('Plain text without code');

        $this->assertFalse($result);
    }

    public function testWrapCodeEncodesContentByDefault(): void
    {
        $value = "<p>Encode 'this' & more</p>";

        $result = $this->service->wrapCode($value);

        $expected = "<pre><code>&lt;p&gt;Encode &#039;this&#039; &amp; more&lt;/p&gt;</code></pre>";

        $this->assertSame($expected, $result);
    }

    public function testWrapCodePreservesContentWhenEncodingDisabled(): void
    {
        $value = "<p>Raw content</p>";

        $result = $this->service->wrapCode($value, false);

        $this->assertSame('<pre><code><p>Raw content</p></code></pre>', $result);
    }

    public function testTransformForAIModelReturnsEmptyStringForNullPrompt(): void
    {
        $result = $this->service->transformForAIModel(null);

        $this->assertSame('', $result);
    }

    public function testTransformForAIModelConvertsHtmlToMarkdownWithCodeBlock(): void
    {
        $prompt = '&lt;p&gt;Intro&lt;/p&gt;<p><strong>Bold</strong> text</p><pre><code><?php echo "hi"; ?></code></pre>';

        $result = $this->service->transformForAIModel($prompt);

        $expected = "Intro\n\n**Bold** text\n\n```\n<?php echo \"hi\"; ?>\n```";

        $this->assertSame($expected, $result);
    }

    public function testTransformForAIModelCondensesExcessiveBlankLines(): void
    {
        $prompt = '<p>Line one</p><p>Line two</p><br><br/>';

        $result = $this->service->transformForAIModel($prompt);

        $this->assertSame("Line one\n\nLine two", $result);
    }

    public function testTransformForAIModelConvertsListMarkupToMarkdownBullets(): void
    {
        $prompt = '<ul><li>First</li><li>Second</li></ul>';

        $result = $this->service->transformForAIModel($prompt);

        $this->assertSame("- First\n- Second", $result);
    }

    public function testTransformForAIModelHandlesUppercaseTagsAndBreakVariants(): void
    {
        $prompt = '<P>Intro</P><BR />Next line<br>Final';

        $result = $this->service->transformForAIModel($prompt);

        $this->assertSame("Intro\n\nNext line\nFinal", $result);
    }

    public function testTransformForAIModelTrimsWhitespaceOnlyInput(): void
    {
        $prompt = '   ';

        $result = $this->service->transformForAIModel($prompt);

        $this->assertSame('', $result);
    }
}
