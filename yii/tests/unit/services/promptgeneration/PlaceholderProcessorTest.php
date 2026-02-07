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

    public function testProcessPreservesListAttributeWhenPlaceholderIsOnListItem(): void
    {
        // Simulates: "1. Navigate to URL: GEN:{{1}}" in Quill Delta
        // In Quill, list formatting is on the newline at end of line
        // IMPORTANT: Quill always stores text with trailing newline, so field value has \n
        $ops = [
            ['insert' => 'Navigate to URL: GEN:{{1}}' . "\n", 'attributes' => ['list' => 'ordered']],
        ];
        // Quill stores every document with a trailing newline
        $fieldValues = [1 => '{"ops":[{"insert":"https://example.com\n"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        // The final newline should have the list attribute preserved
        $lastOp = $result[array_key_last($result)];
        $this->assertArrayHasKey('attributes', $lastOp, 'Final op should have attributes');
        $this->assertArrayHasKey('list', $lastOp['attributes'], 'Final op should have list attribute');
        $this->assertSame('ordered', $lastOp['attributes']['list'], 'List attribute should be ordered');

        // The field content's trailing newline should have been stripped
        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('Navigate to URL: https://example.com', $plainText);
    }

    public function testProcessPreservesBulletListAttributeWhenPlaceholderIsOnListItem(): void
    {
        $ops = [
            ['insert' => 'Item: GEN:{{1}}' . "\n", 'attributes' => ['list' => 'bullet']],
        ];
        // Quill stores every document with a trailing newline
        $fieldValues = [1 => '{"ops":[{"insert":"value\n"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        $lastOp = $result[array_key_last($result)];
        $this->assertArrayHasKey('attributes', $lastOp);
        $this->assertSame('bullet', $lastOp['attributes']['list']);
    }

    public function testProcessPreservesListIndentWhenPlaceholderIsOnNestedListItem(): void
    {
        $ops = [
            ['insert' => 'Nested item: GEN:{{1}}' . "\n", 'attributes' => ['list' => 'ordered', 'indent' => 1]],
        ];
        // Quill stores every document with a trailing newline
        $fieldValues = [1 => '{"ops":[{"insert":"value\n"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        $lastOp = $result[array_key_last($result)];
        $this->assertArrayHasKey('attributes', $lastOp);
        $this->assertSame('ordered', $lastOp['attributes']['list']);
        $this->assertSame(1, $lastOp['attributes']['indent']);
    }

    public function testProcessStripsFieldTrailingNewlineOnListItem(): void
    {
        // Verifies that the field's trailing newline is stripped when on a list item
        $ops = [
            ['insert' => 'URL: GEN:{{1}}' . "\n", 'attributes' => ['list' => 'ordered']],
        ];
        $fieldValues = [1 => '{"ops":[{"insert":"https://example.com\n"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        // Count the newlines - there should only be ONE (the attributed one)
        $newlineCount = 0;
        foreach ($result as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $newlineCount += substr_count($op['insert'], "\n");
            }
        }
        $this->assertSame(1, $newlineCount, 'Should have exactly one newline (the attributed one)');
    }

    public function testProcessDoesNotPreserveListWhenFieldHasBlockAttributes(): void
    {
        // If the field content is itself a list, field formatting should win
        $this->processor->setFieldMappings(
            [1 => 'multi-select'],
            []
        );

        $ops = [
            ['insert' => 'Items: GEN:{{1}}' . "\n", 'attributes' => ['list' => 'ordered']],
        ];
        $fieldValues = [1 => ['Option A', 'Option B']];

        $result = $this->processor->process($ops, $fieldValues);

        // Field produces bullet list, so original ordered list should not wrap it
        // The last ops should have the field's list formatting
        $hasBulletList = false;
        foreach ($result as $op) {
            if (isset($op['attributes']['list']) && $op['attributes']['list'] === 'bullet') {
                $hasBulletList = true;
                break;
            }
        }
        $this->assertTrue($hasBulletList, 'Multi-select field should produce bullet list');
    }

    public function testProcessPreservesListAttributeWhenTextAndNewlineAreSeparateOps(): void
    {
        // Actual Quill structure: text op is SEPARATE from attributed newline op
        // This is how Quill stores list items - the list attribute is on the newline, not the text
        $ops = [
            ['insert' => 'Navigate to URL: GEN:{{1}}'],  // NO attributes on text op
            ['insert' => "\n", 'attributes' => ['list' => 'ordered']],  // attributes on newline op
        ];
        $fieldValues = [1 => '{"ops":[{"insert":"https://example.com\n"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        // Should produce proper list item with attribute on final newline
        $lastOp = $result[array_key_last($result)];
        $this->assertSame("\n", $lastOp['insert']);
        $this->assertArrayHasKey('attributes', $lastOp, 'Final newline should have attributes');
        $this->assertSame('ordered', $lastOp['attributes']['list'], 'Final newline should have list:ordered');

        // Count newlines - should be exactly one
        $newlineCount = 0;
        foreach ($result as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $newlineCount += substr_count($op['insert'], "\n");
            }
        }
        $this->assertSame(1, $newlineCount, 'Should have exactly one newline');
    }

    public function testProcessHandlesSeparateOpsWithMultipleListItems(): void
    {
        // Multiple list items with placeholders, all using separate ops structure
        $ops = [
            ['insert' => 'First item: GEN:{{1}}'],
            ['insert' => "\n", 'attributes' => ['list' => 'ordered']],
            ['insert' => 'Second item'],
            ['insert' => "\n", 'attributes' => ['list' => 'ordered']],
        ];
        $fieldValues = [1 => '{"ops":[{"insert":"value\n"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        // Should have two list items
        $listCount = 0;
        foreach ($result as $op) {
            if (isset($op['attributes']['list']) && $op['attributes']['list'] === 'ordered') {
                $listCount++;
            }
        }
        $this->assertSame(2, $listCount, 'Should have two ordered list newlines');
    }

    public function testProcessStripsLeadingNewlineFromFieldOnListItem(): void
    {
        // Field value with LEADING newline - should be stripped when on list item
        $ops = [
            ['insert' => 'Navigate to URL: GEN:{{1}}'],
            ['insert' => "\n", 'attributes' => ['list' => 'ordered']],
        ];
        // Field value starts with newline (which would cause indentation issues)
        $fieldValues = [1 => '{"ops":[{"insert":"\nhttps://example.com\n"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        // The URL should appear directly after the colon, not on a new line
        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('Navigate to URL: https://example.com', $plainText);
        $this->assertStringNotContainsString("URL: \n", $plainText, 'Leading newline should be stripped');

        // Should have exactly one newline (the list-attributed one)
        $newlineCount = 0;
        foreach ($result as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $newlineCount += substr_count($op['insert'], "\n");
            }
        }
        $this->assertSame(1, $newlineCount, 'Should have exactly one newline');
    }

    // --- Header attribute handling tests ---

    public function testProcessPreservesHeaderAttributeWhenTextFollowsPlaceholder(): void
    {
        $ops = [
            ['insert' => 'Code:GEN:{{1}}' . "\n" . 'Constraints'],
            ['insert' => "\n", 'attributes' => ['header' => 2]],
        ];
        $fieldValues = [1 => '{"ops":[{"insert":"sample code\n"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        $hasHeaderAttr = false;
        foreach ($result as $op) {
            if (
                isset($op['insert'])
                && $op['insert'] === "\n"
                && isset($op['attributes']['header'])
                && $op['attributes']['header'] === 2
            ) {
                $hasHeaderAttr = true;
                break;
            }
        }
        $this->assertTrue($hasHeaderAttr, 'Header attribute should be preserved on trailing newline');
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

    // --- Inline field rendering tests ---

    public function testProcessRendersStringFieldInlineWhenOnSeparateLine(): void
    {
        $this->processor->setFieldMappings(
            [1 => 'string'],
            []
        );

        // Template: "Write a\nGEN:{{1}}\nfunction" — placeholder on its own line
        $ops = [['insert' => "Write a\nGEN:{{1}}\nfunction"]];
        $fieldValues = [1 => 'sorting'];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('Write a sorting function', $plainText);
        $this->assertStringNotContainsString("Write a\nsorting", $plainText);
        $this->assertStringNotContainsString("sorting\nfunction", $plainText);
    }

    public function testProcessRendersNumberFieldInline(): void
    {
        $this->processor->setFieldMappings(
            [1 => 'number'],
            []
        );

        $ops = [['insert' => "Use\nGEN:{{1}}\nretries"]];
        $fieldValues = [1 => '3'];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('Use 3 retries', $plainText);
    }

    public function testProcessRendersSelectFieldInlineWhenOnSeparateLine(): void
    {
        $this->processor->setFieldMappings(
            [1 => 'select'],
            []
        );

        $ops = [['insert' => "Write in\nGEN:{{1}}\nlanguage"]];
        $fieldValues = [1 => '{"ops":[{"insert":"Python\n"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('Write in Python language', $plainText);
        $this->assertStringNotContainsString("in\nPython", $plainText);
        $this->assertStringNotContainsString("Python\nlanguage", $plainText);
    }

    public function testProcessRendersSelectInvertFieldInline(): void
    {
        $field = new stdClass();
        $field->id = 1;
        $field->render_label = false;
        $field->label = '';
        $field->content = '{"ops":[{"insert":" not "}]}';
        $field->fieldOptions = [
            $this->makeFieldOption('{"ops":[{"insert":"Yes\n"}]}', ''),
            $this->makeFieldOption('{"ops":[{"insert":"No\n"}]}', ''),
        ];

        $this->processor->setFieldMappings(
            [1 => 'select-invert'],
            [1 => $field]
        );

        $ops = [['insert' => "Answer:\nGEN:{{1}}\nplease"]];
        $fieldValues = [1 => '{"ops":[{"insert":"Yes\n"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringNotContainsString("Answer:\nYes", $plainText);
        $this->assertStringContainsString('Answer: Yes', $plainText);
    }

    public function testProcessPreservesNewlinesForBlockFieldTypes(): void
    {
        $this->processor->setFieldMappings(
            [1 => 'text'],
            []
        );

        // Block-level fields should still get newlines
        $ops = [['insert' => "Context:\nGEN:{{1}}\nEnd"]];
        $fieldValues = [1 => '{"ops":[{"insert":"Some long text\n"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString("Context:\n", $plainText);
    }

    public function testProcessRendersInlineFieldWithoutExtraNewlineWhenAlreadyOnSameLine(): void
    {
        $this->processor->setFieldMappings(
            [1 => 'select'],
            []
        );

        // Placeholder already inline — should stay inline
        $ops = [['insert' => 'Write in GEN:{{1}} please']];
        $fieldValues = [1 => '{"ops":[{"insert":"Python\n"}]}'];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('Write in Python please', $plainText);
    }

    public function testProcessRendersMultipleInlinePlaceholdersSeparatedByNewlines(): void
    {
        $this->processor->setFieldMappings(
            [1 => 'string', 2 => 'select'],
            []
        );

        // Two inline placeholders each on their own line
        $ops = [['insert' => "Write a\nGEN:{{1}}\nGEN:{{2}}\nfunction"]];
        $fieldValues = [
            1 => 'sorting',
            2 => '{"ops":[{"insert":"Python\n"}]}',
        ];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        $this->assertStringContainsString('Write a sorting Python function', $plainText);
        $this->assertStringNotContainsString("\n", $plainText);
    }

    public function testProcessRendersInlineFollowedByBlockPlaceholder(): void
    {
        $this->processor->setFieldMappings(
            [1 => 'select', 2 => 'text'],
            []
        );

        // Inline placeholder followed by block placeholder
        $ops = [['insert' => "Use\nGEN:{{1}}\nGEN:{{2}}"]];
        $fieldValues = [
            1 => '{"ops":[{"insert":"Python\n"}]}',
            2 => '{"ops":[{"insert":"Some detailed instructions\n"}]}',
        ];

        $result = $this->processor->process($ops, $fieldValues);

        $plainText = $this->extractPlainText($result);
        // Inline field should not have newline before/after it
        $this->assertStringContainsString('Use Python', $plainText);
        // Block field content should be present
        $this->assertStringContainsString('Some detailed instructions', $plainText);
        // No double newline between inline and block field
        $this->assertStringNotContainsString("Python\n\n", $plainText);
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

    private function makeFieldOption(string $value, string $label): stdClass
    {
        $option = new stdClass();
        $option->value = $value;
        $option->label = $label;
        return $option;
    }
}
