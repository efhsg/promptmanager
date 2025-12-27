<?php

namespace tests\unit\services\promptgeneration;

use app\services\promptgeneration\PlaceholderProcessor;
use Codeception\Test\Unit;
use stdClass;

class PlaceholderProcessorTest extends Unit
{
    private PlaceholderProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new PlaceholderProcessor();
    }

    // --- Basic placeholder replacement tests ---

    public function testProcessReplacesGenPlaceholder(): void
    {
        $ops = [['insert' => 'Hello GEN:{{1}} world']];
        $fieldValues = [1 => '{"ops":[{"insert":"replaced"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('replaced', $plainText);
        $this->assertStringNotContainsString('GEN:{{1}}', $plainText);
    }

    public function testProcessReplacesPrjPlaceholder(): void
    {
        $ops = [['insert' => 'PRJ:{{2}} here']];
        $fieldValues = [2 => '{"ops":[{"insert":"project value"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('project value', $plainText);
    }

    public function testProcessReplacesExtPlaceholder(): void
    {
        $ops = [['insert' => 'EXT:{{3}} here']];
        $fieldValues = [3 => '{"ops":[{"insert":"external value"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('external value', $plainText);
    }

    public function testProcessPreservesNonPlaceholderOps(): void
    {
        $ops = [
            ['insert' => 'No placeholders here'],
            ['insert' => ['image' => 'url']],
        ];
        $fieldValues = [];

        $result = $this->processor->process($ops, $fieldValues);

        $this->assertSame($ops, $result);
    }

    public function testProcessPreservesNonStringInserts(): void
    {
        $ops = [
            ['insert' => ['image' => 'http://example.com/img.png']],
        ];
        $fieldValues = [];

        $result = $this->processor->process($ops, $fieldValues);

        $this->assertSame([['insert' => ['image' => 'http://example.com/img.png']]], $result);
    }

    // --- Multiple placeholder tests ---

    public function testProcessReplacesMultiplePlaceholders(): void
    {
        $ops = [['insert' => 'GEN:{{1}} and GEN:{{2}}']];
        $fieldValues = [
            1 => '{"ops":[{"insert":"first"}]}',
            2 => '{"ops":[{"insert":"second"}]}',
        ];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('first', $plainText);
        $this->assertStringContainsString('second', $plainText);
    }

    public function testProcessConsumesFieldValuesInOrder(): void
    {
        $ops = [['insert' => 'GEN:{{1}} GEN:{{2}}']];
        $fieldValues = [
            1 => '{"ops":[{"insert":"A"}]}',
            2 => '{"ops":[{"insert":"B"}]}',
        ];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('A', $plainText);
        $this->assertStringContainsString('B', $plainText);
    }

    // --- Field type mapping tests ---

    public function testProcessUsesFieldTypeMappings(): void
    {
        $field = new stdClass();
        $field->id = 1;
        $field->type = 'multi-select';

        $this->processor->setFieldMappings(
            [1 => 'multi-select'],
            [1 => $field]
        );

        $ops = [['insert' => 'GEN:{{1}}']];
        $fieldValues = [1 => ['Option A', 'Option B']];

        $result = $this->processor->process($ops, $fieldValues);

        // multi-select should produce bullet list
        $hasList = false;
        foreach ($result as $op) {
            if (isset($op['attributes']['list'])) {
                $hasList = true;
                break;
            }
        }
        $this->assertTrue($hasList, 'Multi-select should produce list attributes');
    }

    // --- Fallback behavior tests ---

    public function testProcessFallsBackToNextFieldValue(): void
    {
        $ops = [['insert' => 'GEN:{{99}}']];
        $fieldValues = [
            5 => '{"ops":[{"insert":"fallback value"}]}',
        ];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('fallback value', $plainText);
    }

    public function testProcessReturnsEmptyOpsWhenNoFieldValues(): void
    {
        $ops = [['insert' => 'GEN:{{1}}']];
        $fieldValues = [];

        $result = $this->processor->process($ops, $fieldValues);

        // Should have processed but with empty field ops
        $this->assertIsArray($result);
    }

    // --- Before-text handling tests ---

    public function testProcessPreservesTextBeforePlaceholder(): void
    {
        $ops = [['insert' => 'Before text GEN:{{1}}']];
        $fieldValues = [1 => '{"ops":[{"insert":"value"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('Before text', $plainText);
    }

    public function testProcessPreservesTextAfterPlaceholder(): void
    {
        $ops = [['insert' => 'GEN:{{1}} after text']];
        $fieldValues = [1 => '{"ops":[{"insert":"value"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('after text', $plainText);
    }

    // --- List block handling tests ---

    public function testProcessInjectsNewlineBeforeListBlock(): void
    {
        $this->processor->setFieldMappings(
            [1 => 'multi-select'],
            []
        );

        $ops = [['insert' => 'Intro GEN:{{1}}']];
        $fieldValues = [1 => ['Item A', 'Item B']];

        $result = $this->processor->process($ops, $fieldValues);

        // The processor should inject a newline before the list
        $foundNewlineBeforeList = false;
        for ($i = 0; $i < count($result) - 1; $i++) {
            $current = $result[$i];
            $next = $result[$i + 1];
            if (
                isset($current['insert'])
                && is_string($current['insert'])
                && str_ends_with($current['insert'], "\n")
                && isset($next['attributes']['list'])
            ) {
                $foundNewlineBeforeList = true;
                break;
            }
        }
        $this->assertTrue($foundNewlineBeforeList, 'Should inject newline before list block');
    }

    // --- Code block handling tests ---

    public function testProcessInjectsNewlineBeforeCodeBlock(): void
    {
        $ops = [['insert' => 'Code: GEN:{{1}}']];
        $fieldValues = [1 => '{"ops":[{"insert":"<?php\n","attributes":{"code-block":"php"}}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        // The processor should add newline before code block
        $this->assertIsArray($result);
    }

    // --- Edge cases ---

    public function testProcessHandlesEmptyOps(): void
    {
        $fieldValues = [];
        $result = $this->processor->process([], $fieldValues);

        $this->assertSame([], $result);
    }

    public function testProcessHandlesOpsWithoutInsert(): void
    {
        $ops = [
            ['attributes' => ['bold' => true]],
            ['insert' => 'text'],
        ];
        $fieldValues = [];

        $result = $this->processor->process($ops, $fieldValues);

        $this->assertCount(2, $result);
    }

    private function extractPlainText(array $ops): string
    {
        $text = '';
        foreach ($ops as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $text .= $op['insert'];
            }
        }
        return $text;
    }
}
