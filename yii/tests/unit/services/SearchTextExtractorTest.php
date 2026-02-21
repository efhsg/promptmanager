<?php

namespace tests\unit\services;

use app\services\SearchTextExtractor;
use Codeception\Test\Unit;

class SearchTextExtractorTest extends Unit
{
    public function testExtractsTextFromSimpleDelta(): void
    {
        $delta = json_encode(['ops' => [
            ['insert' => "Hello world\n"],
        ]]);

        $this->assertSame('Hello world', SearchTextExtractor::extract($delta));
    }

    public function testExtractsTextFromFormattedDelta(): void
    {
        $delta = json_encode(['ops' => [
            ['insert' => 'Hello '],
            ['insert' => 'bold', 'attributes' => ['bold' => true]],
            ['insert' => " world\n"],
        ]]);

        $this->assertSame('Hello bold world', SearchTextExtractor::extract($delta));
    }

    public function testExtractsTextSplitAcrossMultipleOps(): void
    {
        $delta = json_encode(['ops' => [
            ['insert' => 'Wat zijn de '],
            ['insert' => 'Consequen', 'attributes' => ['bold' => true]],
            ['insert' => 'ties', 'attributes' => ['italic' => true]],
            ['insert' => " van deze aanpak?\n"],
        ]]);

        $result = SearchTextExtractor::extract($delta);

        $this->assertStringContainsString('Consequenties', $result);
    }

    public function testExtractsTextWithHeaders(): void
    {
        $delta = json_encode(['ops' => [
            ['insert' => 'Title'],
            ['attributes' => ['header' => 1], 'insert' => "\n"],
            ['insert' => "Body text\n"],
        ]]);

        $result = SearchTextExtractor::extract($delta);

        $this->assertStringContainsString('Title', $result);
        $this->assertStringContainsString('Body text', $result);
    }

    public function testReturnsEmptyStringForNull(): void
    {
        $this->assertSame('', SearchTextExtractor::extract(null));
    }

    public function testReturnsEmptyStringForEmptyString(): void
    {
        $this->assertSame('', SearchTextExtractor::extract(''));
    }

    public function testReturnsFallbackForNonJsonContent(): void
    {
        $this->assertSame('plain text value', SearchTextExtractor::extract('plain text value'));
    }

    public function testReturnsFallbackForInvalidJson(): void
    {
        $this->assertSame('{invalid json', SearchTextExtractor::extract('{invalid json'));
    }

    public function testFromDeltaReturnsEmptyForNonDeltaJson(): void
    {
        $this->assertSame('', SearchTextExtractor::fromDelta('{"key":"value"}'));
    }

    public function testFromDeltaSkipsEmbeds(): void
    {
        $delta = json_encode(['ops' => [
            ['insert' => 'Before '],
            ['insert' => ['image' => 'url.png']],
            ['insert' => " after\n"],
        ]]);

        $this->assertSame('Before  after', SearchTextExtractor::extract($delta));
    }

    public function testExtractsMultilineContent(): void
    {
        $delta = json_encode(['ops' => [
            ['insert' => "Line 1\nLine 2\nLine 3\n"],
        ]]);

        $result = SearchTextExtractor::extract($delta);

        $this->assertStringContainsString('Line 1', $result);
        $this->assertStringContainsString('Line 2', $result);
        $this->assertStringContainsString('Line 3', $result);
    }
}
