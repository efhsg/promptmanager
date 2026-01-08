<?php

namespace tests\unit\helpers;

use app\helpers\TooltipHelper;
use Codeception\Test\Unit;

class TooltipHelperTest extends Unit
{
    public function testPrepareTextsWithQuillDelta(): void
    {
        $json = json_encode(['ops' => [['insert' => 'Hello '], ['insert' => 'World']]]);
        $result = TooltipHelper::prepareTexts([$json], 50);
        $this->assertEquals(['Hello World'], $result);
    }

    public function testPrepareTextsWithPlainString(): void
    {
        $result = TooltipHelper::prepareTexts(['Hello World'], 50);
        $this->assertEquals(['Hello World'], $result);
    }

    public function testPrepareTextsWithHtml(): void
    {
        $result = TooltipHelper::prepareTexts(['<p>Hello World</p>'], 50);
        $this->assertEquals(['Hello World'], $result);
    }

    public function testPrepareTextsTruncatesLongContent(): void
    {
        $result = TooltipHelper::prepareTexts(['Hello World'], 5);
        $this->assertEquals(['Hello...',], $result);
    }

    public function testPrepareTextsWithComplexDelta(): void
    {
        $json = json_encode([
            'ops' => [
                ['insert' => 'Hello '],
                ['attributes' => ['bold' => true], 'insert' => 'World'],
                ['insert' => "\n"],
            ],
        ]);
        $result = TooltipHelper::prepareTexts([$json], 50);
        $this->assertEquals(['Hello World'], $result);
    }

    public function testPrepareTextsWithNonStringInserts(): void
    {
        $json = json_encode([
            'ops' => [
                ['insert' => 'Image '],
                ['insert' => ['image' => 'https://example.com/image.png']],
                ['insert' => ' End'],
            ],
        ]);
        $result = TooltipHelper::prepareTexts([$json], 50);
        $this->assertEquals(['Image  End'], $result); // Expecting text only, trimmed spaces might vary but logic trims final result
    }

    public function testPrepareTextsWithInvalidJson(): void
    {
        $invalidJson = '{"ops": [{"insert": "Hello"'; // Missing closing braces
        $result = TooltipHelper::prepareTexts([$invalidJson], 50);
        $this->assertEquals(['{"ops": [{"insert": "Hello"'], $result); // Should return original stripped (which is same here)
    }
}
