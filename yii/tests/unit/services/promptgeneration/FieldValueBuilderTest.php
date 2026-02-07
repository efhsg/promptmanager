<?php

namespace tests\unit\services\promptgeneration;

use app\services\promptgeneration\FieldValueBuilder;
use Codeception\Test\Unit;
use stdClass;

class FieldValueBuilderTest extends Unit
{
    private FieldValueBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new FieldValueBuilder();
    }

    // --- Label rendering tests ---

    public function testBuildAddsLabelHeaderWhenRenderLabelTrue(): void
    {
        $field = new stdClass();
        $field->render_label = true;
        $field->label = 'My Label';

        $result = $this->builder->build('value', 'text', $field);

        $this->assertSame('My Label', rtrim($result[0]['insert'], "\n"));
        $this->assertSame(['header' => 2], $result[0]['attributes']);
    }

    public function testBuildSkipsLabelWhenRenderLabelFalse(): void
    {
        $field = new stdClass();
        $field->render_label = false;
        $field->label = 'My Label';

        $result = $this->builder->build('value', 'text', $field);

        $this->assertSame([['insert' => 'value']], $result);
    }

    public function testBuildSkipsLabelWhenFieldNull(): void
    {
        $result = $this->builder->build('value', 'text', null);

        $this->assertSame([['insert' => 'value']], $result);
    }

    public function testBuildSelectInvertWithLabelAddsInlineBoldLabel(): void
    {
        $field = new stdClass();
        $field->render_label = true;
        $field->label = 'My Label';
        $field->fieldOptions = [];
        $field->content = '';

        $result = $this->builder->build('value', 'select-invert', $field);

        $this->assertSame('My Label: ', $result[0]['insert']);
        $this->assertSame(['bold' => true], $result[0]['attributes']);
    }

    public function testBuildSkipsLabelWhenLabelEmpty(): void
    {
        $field = new stdClass();
        $field->render_label = true;
        $field->label = '';

        $result = $this->builder->build('value', 'text', $field);

        $this->assertSame([['insert' => 'value']], $result);
    }

    public function testBuildSkipsLabelWhenLabelWhitespace(): void
    {
        $field = new stdClass();
        $field->render_label = true;
        $field->label = '   ';

        $result = $this->builder->build('value', 'text', $field);

        $this->assertSame([['insert' => 'value']], $result);
    }

    // --- Multi-select array tests ---

    public function testBuildMultiSelectCreatesBulletList(): void
    {
        $result = $this->builder->build(['Option A', 'Option B'], 'multi-select', null);

        $this->assertCount(2, $result);
        $this->assertSame("Option A\n", $result[0]['insert']);
        $this->assertSame(['list' => 'bullet'], $result[0]['attributes']);
        $this->assertSame("Option B\n", $result[1]['insert']);
        $this->assertSame(['list' => 'bullet'], $result[1]['attributes']);
    }

    public function testBuildMultiSelectSkipsEmptyValues(): void
    {
        $result = $this->builder->build(['Option A', '', '  ', 'Option B'], 'multi-select', null);

        $this->assertCount(2, $result);
        $this->assertSame("Option A\n", $result[0]['insert']);
        $this->assertSame("Option B\n", $result[1]['insert']);
    }

    public function testBuildMultiSelectTrimsValues(): void
    {
        $result = $this->builder->build(['  Trimmed  '], 'multi-select', null);

        $this->assertSame("Trimmed\n", $result[0]['insert']);
    }

    public function testBuildMultiSelectHandlesDeltaJsonValues(): void
    {
        $deltaValue = json_encode(['ops' => [
            ['insert' => 'Badge Text', 'attributes' => ['badge' => true]],
            ['insert' => "\n"],
        ]]);

        $result = $this->builder->build([$deltaValue], 'multi-select', null);

        // Should extract ops and apply list bullet
        $this->assertGreaterThanOrEqual(1, count($result));

        // Find the insert with "Badge Text"
        $foundBadge = false;
        $foundListBullet = false;
        foreach ($result as $op) {
            if (isset($op['insert']) && str_contains($op['insert'], 'Badge Text')) {
                $foundBadge = true;
                if (isset($op['attributes']['badge'])) {
                    $this->assertTrue($op['attributes']['badge']);
                }
            }
            if (isset($op['attributes']['list']) && $op['attributes']['list'] === 'bullet') {
                $foundListBullet = true;
            }
        }
        $this->assertTrue($foundBadge, 'Should contain Badge Text');
        $this->assertTrue($foundListBullet, 'Should have list bullet attribute');
    }

    public function testBuildMultiSelectMixedPlainAndDeltaValues(): void
    {
        $deltaValue = json_encode(['ops' => [
            ['insert' => 'Formatted', 'attributes' => ['bold' => true]],
            ['insert' => "\n"],
        ]]);

        $result = $this->builder->build(['Plain text', $deltaValue], 'multi-select', null);

        // Should have both items as bullet list
        $listBulletCount = 0;
        foreach ($result as $op) {
            if (isset($op['attributes']['list']) && $op['attributes']['list'] === 'bullet') {
                $listBulletCount++;
            }
        }
        $this->assertSame(2, $listBulletCount, 'Should have 2 bullet list items');
    }

    public function testBuildMultiSelectPreservesFormattingFromDelta(): void
    {
        $deltaValue = json_encode(['ops' => [
            ['insert' => 'Bold ', 'attributes' => ['bold' => true]],
            ['insert' => 'Normal'],
            ['insert' => "\n"],
        ]]);

        $result = $this->builder->build([$deltaValue], 'multi-select', null);

        // Find bold insert
        $foundBold = false;
        foreach ($result as $op) {
            if (
                isset($op['insert'])
                && str_contains($op['insert'], 'Bold')
                && isset($op['attributes']['bold'])
                && $op['attributes']['bold'] === true
            ) {
                $foundBold = true;
                break;
            }
        }
        $this->assertTrue($foundBold, 'Should preserve bold formatting from Delta JSON');
    }

    // --- Sequential array (non-multi-select) tests ---

    public function testBuildSequentialArrayAddsPeriodSuffix(): void
    {
        $result = $this->builder->build(['First line', 'Second line'], 'text', null);

        $this->assertSame("First line.\n", $result[0]['insert']);
        $this->assertSame("Second line.\n", $result[1]['insert']);
    }

    public function testBuildSequentialArrayPreservesExistingPeriod(): void
    {
        $result = $this->builder->build(['Already has period.', 'No period'], 'text', null);

        $this->assertSame("Already has period.\n", $result[0]['insert']);
        $this->assertSame("No period.\n", $result[1]['insert']);
    }

    // --- Associative array tests ---

    public function testBuildAssociativeArrayFormatsKeyValue(): void
    {
        $result = $this->builder->build(['key1' => 'value1', 'key2' => 'value2'], 'text', null);

        $this->assertSame("key1: value1\n", $result[0]['insert']);
        $this->assertSame("key2: value2\n", $result[1]['insert']);
    }

    // --- JSON delta tests ---

    public function testBuildFromJsonExtractsOps(): void
    {
        $json = '{"ops":[{"insert":"Hello\n"}]}';

        $result = $this->builder->build($json, 'text', null);

        $this->assertSame([['insert' => "Hello\n"]], $result);
    }

    public function testBuildFromInvalidJsonReturnsFallback(): void
    {
        $result = $this->builder->build('not json', 'text', null);

        $this->assertSame([['insert' => 'not json']], $result);
    }

    public function testBuildFromJsonWithoutOpsReturnsEmpty(): void
    {
        $json = '{"foo":"bar"}';

        $result = $this->builder->build($json, 'text', null);

        $this->assertSame([], $result);
    }

    // --- Select-invert tests ---

    public function testBuildSelectInvertBasic(): void
    {
        $option1 = new stdClass();
        $option1->value = '{"ops":[{"insert":"A\n"}]}';
        $option1->label = '';

        $option2 = new stdClass();
        $option2->value = '{"ops":[{"insert":"B\n"}]}';
        $option2->label = '';

        $field = new stdClass();
        $field->content = '{"ops":[{"insert":" vs "}]}';
        $field->fieldOptions = [$option1, $option2];

        $result = $this->builder->build('A', 'select-invert', $field);

        $plainText = $this->extractPlainText($result);
        $this->assertSame('A vs B', $plainText);
    }

    public function testBuildSelectInvertUsesLabels(): void
    {
        $option1 = new stdClass();
        $option1->value = '{"ops":[{"insert":"val1\n"}]}';
        $option1->label = 'Label One';

        $option2 = new stdClass();
        $option2->value = '{"ops":[{"insert":"val2\n"}]}';
        $option2->label = 'Label Two';

        $field = new stdClass();
        $field->content = '{"ops":[{"insert":" compared to "}]}';
        $field->fieldOptions = [$option1, $option2];

        $result = $this->builder->build('val1', 'select-invert', $field);

        $plainText = $this->extractPlainText($result);
        $this->assertSame('Label One compared to Label Two', $plainText);
    }

    public function testBuildSelectInvertWithNullField(): void
    {
        $result = $this->builder->build('selected', 'select-invert', null);

        $this->assertSame([['insert' => 'selected']], $result);
    }

    public function testBuildSelectInvertStripsNewlinesFromLabels(): void
    {
        $option1 = new stdClass();
        $option1->value = '{"ops":[{"insert":"A\n"}]}';
        $option1->label = "Label\nWith\nNewlines";

        $option2 = new stdClass();
        $option2->value = '{"ops":[{"insert":"B\n"}]}';
        $option2->label = '';

        $field = new stdClass();
        $field->content = '{"ops":[{"insert":" vs "}]}';
        $field->fieldOptions = [$option1, $option2];

        $result = $this->builder->build('A', 'select-invert', $field);

        $plainText = $this->extractPlainText($result);
        $this->assertStringNotContainsString("\n", $plainText);
    }

    public function testBuildSelectInvertMultipleUnselected(): void
    {
        $option1 = new stdClass();
        $option1->value = '{"ops":[{"insert":"A\n"}]}';
        $option1->label = '';

        $option2 = new stdClass();
        $option2->value = '{"ops":[{"insert":"B\n"}]}';
        $option2->label = '';

        $option3 = new stdClass();
        $option3->value = '{"ops":[{"insert":"C\n"}]}';
        $option3->label = '';

        $field = new stdClass();
        $field->content = '{"ops":[{"insert":" vs "}]}';
        $field->fieldOptions = [$option1, $option2, $option3];

        $result = $this->builder->build('B', 'select-invert', $field);

        $plainText = $this->extractPlainText($result);
        $this->assertSame('B vs A,C', $plainText);
    }

    public function testBuildSelectInvertWithDeltaJsonSelectedValue(): void
    {
        $option1 = new stdClass();
        $option1->value = '{"ops":[{"insert":"A\n"}]}';
        $option1->label = '';

        $option2 = new stdClass();
        $option2->value = '{"ops":[{"insert":"B\n"}]}';
        $option2->label = '';

        $field = new stdClass();
        $field->content = '{"ops":[{"insert":" vs "}]}';
        $field->fieldOptions = [$option1, $option2];

        $result = $this->builder->build('{"ops":[{"insert":"A\n"}]}', 'select-invert', $field);

        $plainText = $this->extractPlainText($result);
        $this->assertSame('A vs B', $plainText);
    }

    // --- Inline field type tests ---

    public function testBuildInlineStringReturnsSimpleInsert(): void
    {
        $result = $this->builder->build('Hello World', 'string', null);

        $this->assertSame([['insert' => 'Hello World']], $result);
    }

    public function testBuildInlineNumberReturnsSimpleInsert(): void
    {
        $result = $this->builder->build('42.5', 'number', null);

        $this->assertSame([['insert' => '42.5']], $result);
    }

    public function testBuildInlineStringTrimsValue(): void
    {
        $result = $this->builder->build('  trimmed  ', 'string', null);

        $this->assertSame([['insert' => 'trimmed']], $result);
    }

    public function testBuildInlineStringReturnsEmptyWhenEmpty(): void
    {
        $result = $this->builder->build('', 'string', null);

        $this->assertSame([], $result);
    }

    public function testBuildInlineStringReturnsEmptyWhenWhitespace(): void
    {
        $result = $this->builder->build('   ', 'string', null);

        $this->assertSame([], $result);
    }

    public function testBuildInlineStringWithLabelAddsBoldInlineLabel(): void
    {
        $field = new stdClass();
        $field->render_label = true;
        $field->label = 'My String';

        $result = $this->builder->build('Value', 'string', $field);

        $this->assertCount(2, $result);
        $this->assertSame('My String: ', $result[0]['insert']);
        $this->assertSame(['bold' => true], $result[0]['attributes']);
        $this->assertSame('Value', $result[1]['insert']);
    }

    public function testBuildInlineNumberWithLabelAddsBoldInlineLabel(): void
    {
        $field = new stdClass();
        $field->render_label = true;
        $field->label = 'My Number';

        $result = $this->builder->build('123', 'number', $field);

        $this->assertCount(2, $result);
        $this->assertSame('My Number: ', $result[0]['insert']);
        $this->assertSame(['bold' => true], $result[0]['attributes']);
        $this->assertSame('123', $result[1]['insert']);
    }

    public function testBuildSelectWithLabelAddsBoldInlineLabel(): void
    {
        $field = new stdClass();
        $field->render_label = true;
        $field->label = 'My Select';

        $delta = '{"ops":[{"insert":"Option A\n"}]}';
        $result = $this->builder->build($delta, 'select', $field);

        $this->assertCount(2, $result);
        $this->assertSame('My Select: ', $result[0]['insert']);
        $this->assertSame(['bold' => true], $result[0]['attributes']);
        $this->assertSame('Option A', $result[1]['insert']);
    }

    public function testBuildSelectWithoutLabelKeepsTrailingNewline(): void
    {
        $delta = '{"ops":[{"insert":"Option A\n"}]}';
        $result = $this->builder->build($delta, 'select', null);

        $this->assertSame([['insert' => "Option A\n"]], $result);
    }

    private function extractPlainText(array $ops): string
    {
        $text = '';
        foreach ($ops as $op) {
            if (isset($op['insert']) && is_string($op['insert'])) {
                $text .= $op['insert'];
            }
        }
        return trim($text);
    }
}
