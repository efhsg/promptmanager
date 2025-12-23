<?php

namespace tests\unit\services\copyformat;

use app\services\copyformat\DeltaParser;
use Codeception\Test\Unit;

class DeltaParserTest extends Unit
{
    private DeltaParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DeltaParser();
    }

    public function testDecodeValidDeltaWithOps(): void
    {
        $json = json_encode(['ops' => [['insert' => 'Hello']]]);

        $result = $this->parser->decode($json);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ops', $result);
        $this->assertSame([['insert' => 'Hello']], $result['ops']);
    }

    public function testDecodeArrayWithoutOpsKey(): void
    {
        $json = json_encode([['insert' => 'Hello']]);

        $result = $this->parser->decode($json);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ops', $result);
        $this->assertSame([['insert' => 'Hello']], $result['ops']);
    }

    public function testDecodeInvalidJsonReturnsNull(): void
    {
        $result = $this->parser->decode('not valid json');

        $this->assertNull($result);
    }

    public function testDecodeEmptyStringReturnsNull(): void
    {
        $result = $this->parser->decode('');

        $this->assertNull($result);
    }

    public function testEncodeProducesValidJson(): void
    {
        $delta = ['ops' => [['insert' => 'Hello']]];

        $result = $this->parser->encode($delta);

        $this->assertSame('{"ops":[{"insert":"Hello"}]}', $result);
    }

    public function testEncodePreservesUnicodeAndSlashes(): void
    {
        $delta = ['ops' => [['insert' => 'Hello/World élève']]];

        $result = $this->parser->encode($delta);

        $this->assertStringContainsString('Hello/World', $result);
        $this->assertStringContainsString('élève', $result);
        $this->assertStringNotContainsString('\/', $result);
    }

    public function testBuildBlocksFromSimpleText(): void
    {
        $ops = [['insert' => "Hello\n"]];

        $result = $this->parser->buildBlocks($ops);

        $this->assertCount(1, $result);
        $this->assertSame('Hello', $result[0]['segments'][0]['text']);
    }

    public function testBuildBlocksWithInlineAttributes(): void
    {
        $ops = [
            ['insert' => 'Bold', 'attributes' => ['bold' => true]],
            ['insert' => "\n"],
        ];

        $result = $this->parser->buildBlocks($ops);

        $this->assertCount(1, $result);
        $this->assertSame('Bold', $result[0]['segments'][0]['text']);
        $this->assertSame(['bold' => true], $result[0]['segments'][0]['attrs']);
    }

    public function testBuildBlocksWithBlockAttributes(): void
    {
        $ops = [
            ['insert' => "Header\n", 'attributes' => ['header' => 2]],
        ];

        $result = $this->parser->buildBlocks($ops);

        $this->assertCount(1, $result);
        $this->assertSame(['header' => 2], $result[0]['attrs']);
    }

    public function testBuildBlocksWithEmbed(): void
    {
        $ops = [
            ['insert' => ['image' => 'https://example.com/img.png']],
            ['insert' => "\n"],
        ];

        $result = $this->parser->buildBlocks($ops);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('embed', $result[0]['segments'][0]);
        $this->assertSame(['image' => 'https://example.com/img.png'], $result[0]['segments'][0]['embed']);
    }

    public function testBuildBlocksMultipleLines(): void
    {
        $ops = [
            ['insert' => "Line 1\nLine 2\n"],
        ];

        $result = $this->parser->buildBlocks($ops);

        $this->assertCount(2, $result);
        $this->assertSame('Line 1', $result[0]['segments'][0]['text']);
        $this->assertSame('Line 2', $result[1]['segments'][0]['text']);
    }
}
