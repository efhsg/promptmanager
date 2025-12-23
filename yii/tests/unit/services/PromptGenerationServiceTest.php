<?php

namespace tests\unit\services;

use app\models\PromptTemplate;
use app\services\PromptGenerationService;
use app\services\PromptTemplateService;
use Codeception\Stub;
use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Exception;
use stdClass;
use yii\web\NotFoundHttpException;

class PromptGenerationServiceTest extends Unit
{

    private const USER_ID = 100;

    /**
     * @dataProvider promptCasesProvider
     * @throws Exception
     */
    public function testGenerateFinalPrompt(
        string $templateContent,
        array $contexts,
        array $fieldValues,
        string $expectedOutput,
        array $fieldTypes
    ): void {
        $fields = [];
        foreach ($fieldTypes as $id => $type) {
            $field = new stdClass();
            $field->id = $id;
            $field->type = $type;
            $fields[] = $field;
        }

        $template = Stub::make(
            PromptTemplate::class,
            [
                '__get' => function ($property) use ($templateContent, $fields) {
                    if ($property === 'template_body') {
                        return $templateContent;
                    }
                    if ($property === 'fields') {
                        return $fields;
                    }
                    return null;
                },
            ]
        );

        $templateService = Stub::make(
            PromptTemplateService::class,
            ['getTemplateById' => Expected::once($template)],
            $this
        );

        $service = new PromptGenerationService($templateService);

        $result = $service->generateFinalPrompt(
            1,
            $contexts,
            $fieldValues,
            self::USER_ID
        );

        // Verify output is valid JSON
        $decoded = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('ops', $decoded);

        // Compare normalized plain text content
        $normalizedResult = preg_replace('/\n+/', "\n", $this->getPlainTextFromQuillDelta($result));
        $normalizedExpected = preg_replace('/\n+/', "\n", $this->getPlainTextFromQuillDelta($expectedOutput));

        $this->assertSame($normalizedExpected, $normalizedResult, 'Plain text content should match');
    }

    private function getPlainTextFromQuillDelta(string $jsonString): string
    {
        $text = '';

        try {
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($data) && isset($data['ops'])) {
                foreach ($data['ops'] as $op) {
                    if (isset($op['insert']) && is_string($op['insert'])) {
                        $text .= $op['insert'];
                    }
                }
            }
        } catch (Exception) {
            // Fall back to simplified check if JSON parsing fails
            return $jsonString;
        }

        return rtrim($text);
    }

    /**
     * Data provider for prompt generation test cases
     */
    public static function promptCasesProvider(): array
    {
        $ctxA = '{"ops":[{"insert":"Context A line 1\n"},{"insert":"Context A line 2\n"}]}';
        $ctxB = '{"ops":[{"insert":"// Sample code context\nfunction foo() { return true; }\n"}]}';
        $ctxC = '{"ops":[{"insert":"List context:"},{"attributes":{"list":"bullet"},"insert":"\n"},{"insert":"Item 1"},{"attributes":{"list":"bullet"},"insert":"\n"},{"insert":"Item 2"},{"attributes":{"list":"bullet"},"insert":"\n"}]}';
        $ctxD = '{"ops":[{"insert":"Context with "},{"attributes":{"bold":true},"insert":"formatting"},{"insert":" and "},{"attributes":{"italic":true},"insert":"styles"},{"insert":".\n"}]}';

        return [
            'Change functionality' => [
                '{"ops":[{"insert":"This is the code we want to change:GEN:{{1}}\nAnd this is the change I want: GEN:{{4}}\nGEN:{{3}}\n"}]}',
                [],
                [
                    1 => '{"ops":[{"insert":"a\n"}]}',
                    6 => '{"ops":[{"insert":"b\n"}]}',
                    3 => [0 => 'do not write any comment', 1 => 'use SOLID, DRY, YAGNI principles'],
                ],
                '{"ops":[
                    {"insert":"This is the code we want to change:a\nAnd this is the change I want: b\n"},
                    {"insert":"do not write any comment\n","attributes":{"list":"bullet"}},
                    {"insert":"use SOLID, DRY, YAGNI principles\n","attributes":{"list":"bullet"}}
                ]}',
                [
                    1 => 'text',
                    3 => 'multi-select',
                    4 => 'text',
                    6 => 'text',
                ],
            ],
            'Simple code block with placeholders' => [
                '{"ops":[{"insert":"Code:GEN:{{1}}\nTask: GEN:{{2}}\n"}]}',
                [],
                [
                    1 => '{"ops":[{"insert":"function test() {}"},{"attributes":{"code-block":"javascript"},"insert":"\\n"}]}',
                    2 => '{"ops":[{"insert":"Refactor this.\\n"}]}',
                ],
                '{"ops":[{"insert":"Code:\\nfunction test() {}\\nTask: Refactor this.\\n"}]}',
                [
                    1 => 'code',
                    2 => 'text',
                ],
            ],
        ];
    }

    /**
     * @throws Exception
     */
    public function testTemplateNotFoundThrows(): void
    {
        $templateService = Stub::make(
            PromptTemplateService::class,
            ['getTemplateById' => Expected::once(null)],
            $this
        );

        $service = new PromptGenerationService($templateService);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Template not found or access denied.');

        $service->generateFinalPrompt(999, [], [], self::USER_ID);
    }

    /**
     * @throws Exception
     */
    public function testRenderLabelAddsHeadingForMultiSelect(): void
    {
        $field = new stdClass();
        $field->id = 1;
        $field->type = 'multi-select';
        $field->label = 'Choices';
        $field->render_label = true;

        $templateBody = '{"ops":[{"insert":"GEN:{{1}}"}]}';

        $template = Stub::make(
            PromptTemplate::class,
            [
                '__get' => function ($property) use ($templateBody, $field) {
                    if ($property === 'template_body') {
                        return $templateBody;
                    }
                    if ($property === 'fields') {
                        return [$field];
                    }
                    return null;
                },
                'getAttribute' => function ($name) use ($templateBody) {
                    if ($name === 'template_body') {
                        return $templateBody;
                    }
                    return null;
                }
            ]
        );

        $templateService = Stub::make(
            PromptTemplateService::class,
            ['getTemplateById' => Expected::once($template)],
            $this
        );

        $service = new PromptGenerationService($templateService);

        $result = $service->generateFinalPrompt(
            1,
            [],
            [1 => ['Option A', 'Option B']],
            self::USER_ID
        );

        $plainText = $this->getPlainTextFromQuillDelta($result);
        $this->assertSame("Choices\nOption A\nOption B", $plainText);
    }

    /**
     * @throws Exception
     */
    public function testRenderLabelAddsHeadingForText(): void
    {
        $field = new stdClass();
        $field->id = 1;
        $field->type = 'text';
        $field->label = 'Notes';
        $field->render_label = true;

        $templateBody = '{"ops":[{"insert":"GEN:{{1}}"}]}';

        $template = Stub::make(
            PromptTemplate::class,
            [
                '__get' => function ($property) use ($templateBody, $field) {
                    if ($property === 'template_body') {
                        return $templateBody;
                    }
                    if ($property === 'fields') {
                        return [$field];
                    }
                    return null;
                },
                'getAttribute' => function ($name) use ($templateBody) {
                    if ($name === 'template_body') {
                        return $templateBody;
                    }
                    return null;
                }
            ]
        );

        $templateService = Stub::make(
            PromptTemplateService::class,
            ['getTemplateById' => Expected::once($template)],
            $this
        );

        $service = new PromptGenerationService($templateService);

        $result = $service->generateFinalPrompt(
            1,
            [],
            [1 => 'Hello'],
            self::USER_ID
        );

        $plainText = $this->getPlainTextFromQuillDelta($result);
        $this->assertSame("Notes\nHello", $plainText);
    }

    /**
     * @throws Exception
     */
    public function testConsecutivePlaceholdersDoNotAddBlankLine(): void
    {
        $field1 = new stdClass();
        $field1->id = 1;
        $field1->type = 'text';

        $field2 = new stdClass();
        $field2->id = 2;
        $field2->type = 'text';

        $templateBody = '{"ops":[{"insert":"PRJ:{{1}}\\nPRJ:{{2}}\\n"}]}';

        $template = Stub::make(
            PromptTemplate::class,
            [
                '__get' => function ($property) use ($templateBody, $field1, $field2) {
                    if ($property === 'template_body') {
                        return $templateBody;
                    }
                    if ($property === 'fields') {
                        return [$field1, $field2];
                    }
                    return null;
                },
                'getAttribute' => function ($name) use ($templateBody) {
                    if ($name === 'template_body') {
                        return $templateBody;
                    }
                    return null;
                }
            ]
        );

        $templateService = Stub::make(
            PromptTemplateService::class,
            ['getTemplateById' => Expected::once($template)],
            $this
        );

        $service = new PromptGenerationService($templateService);

        $result = $service->generateFinalPrompt(
            1,
            [],
            [
                1 => '{"ops":[{"insert":"First\\n"}]}',
                2 => '{"ops":[{"insert":"Second\\n"}]}',
            ],
            self::USER_ID
        );

        $plainText = $this->getPlainTextFromQuillDelta($result);
        $this->assertSame("First\nSecond", $plainText);
    }

    /**
     * @dataProvider selectInvertCasesProvider
     * @throws Exception
     */
    public function testSelectInvertFieldType(
        string $fieldContent,
        array $optionValues,
        string $selectedValue,
        string $expectedOutput
    ): void {
        $fieldOptions = [];
        foreach ($optionValues as $value) {
            $option = new stdClass();
            $option->value = $value;
            $fieldOptions[] = $option;
        }

        $field = new stdClass();
        $field->id = 1;
        $field->type = 'select-invert';
        $field->content = $fieldContent;
        $field->fieldOptions = $fieldOptions;

        $templateBody = '{"ops":[{"insert":"GEN:{{1}}"}]}';

        $template = Stub::make(
            PromptTemplate::class,
            [
                '__get' => function ($property) use ($templateBody, $field) {
                    if ($property === 'template_body') {
                        return $templateBody;
                    }
                    if ($property === 'fields') {
                        return [$field];
                    }
                    return null;
                },
                'getAttribute' => function ($name) use ($templateBody) {
                    if ($name === 'template_body') {
                        return $templateBody;
                    }
                    return null;
                }
            ]
        );

        $templateService = Stub::make(
            PromptTemplateService::class,
            ['getTemplateById' => Expected::once($template)],
            $this
        );

        $service = new PromptGenerationService($templateService);

        $result = $service->generateFinalPrompt(
            1,
            [],
            [1 => $selectedValue],
            self::USER_ID
        );

        $plainText = $this->getPlainTextFromQuillDelta($result);
        $this->assertSame($expectedOutput, $plainText);
    }

    public static function selectInvertCasesProvider(): array
    {
        return [
            'Basic example with simple content' => [
                'fieldContent' => '{"ops":[{"insert":" compared to "}]}',
                'optionValues' => ['{"ops":[{"insert":"Shell\n"}]}', '{"ops":[{"insert":"Exxon\n"}]}', '{"ops":[{"insert":"BP\n"}]}'],
                'selectedValue' => 'Shell',
                'expectedOutput' => 'Shell compared to Exxon,BP',
            ],
            'Multi-word option names' => [
                'fieldContent' => '{"ops":[{"insert":" compared to "}]}',
                'optionValues' => ['{"ops":[{"insert":"Exxon Mobil\n"}]}', '{"ops":[{"insert":"Shell\n"}]}', '{"ops":[{"insert":"BP\n"}]}', '{"ops":[{"insert":"TotalEnergies\n"}]}', '{"ops":[{"insert":"Chevron\n"}]}'],
                'selectedValue' => 'Exxon Mobil',
                'expectedOutput' => 'Exxon Mobil compared to Shell,BP,TotalEnergies,Chevron',
            ],
            'Content with newline' => [
                'fieldContent' => '{"ops":[{"insert":"compared to\n"}]}',
                'optionValues' => ['{"ops":[{"insert":"Exxon Mobil\n"}]}', '{"ops":[{"insert":"Shell\n"}]}', '{"ops":[{"insert":"BP\n"}]}', '{"ops":[{"insert":"TotalEnergies\n"}]}', '{"ops":[{"insert":"Chevron\n"}]}'],
                'selectedValue' => 'Exxon Mobil',
                'expectedOutput' => 'Exxon Mobil compared toShell,BP,TotalEnergies,Chevron',
            ],
            'Empty content' => [
                'fieldContent' => '{"ops":[{"insert":""}]}',
                'optionValues' => ['{"ops":[{"insert":"Option1\n"}]}', '{"ops":[{"insert":"Option2\n"}]}', '{"ops":[{"insert":"Option3\n"}]}'],
                'selectedValue' => 'Option1',
                'expectedOutput' => 'Option1Option2,Option3',
            ],
            'Last option selected' => [
                'fieldContent' => '{"ops":[{"insert":" vs "}]}',
                'optionValues' => ['{"ops":[{"insert":"A\n"}]}', '{"ops":[{"insert":"B\n"}]}', '{"ops":[{"insert":"C\n"}]}'],
                'selectedValue' => 'C',
                'expectedOutput' => 'C vs A,B',
            ],
            'Middle option selected' => [
                'fieldContent' => '{"ops":[{"insert":" vs "}]}',
                'optionValues' => ['{"ops":[{"insert":"A\n"}]}', '{"ops":[{"insert":"B\n"}]}', '{"ops":[{"insert":"C\n"}]}'],
                'selectedValue' => 'B',
                'expectedOutput' => 'B vs A,C',
            ],
            'Content with spaces' => [
                'fieldContent' => '{"ops":[{"insert":"  compared to  "}]}',
                'optionValues' => ['{"ops":[{"insert":"X\n"}]}', '{"ops":[{"insert":"Y\n"}]}', '{"ops":[{"insert":"Z\n"}]}'],
                'selectedValue' => 'X',
                'expectedOutput' => 'X  compared to  Y,Z',
            ],
        ];
    }

    /**
     * Test with separate value and label (like real database records with Quill Delta format)
     * @throws Exception
     */
    public function testSelectInvertWithValueAndLabel(): void
    {
        // Create field options with VALUE (Quill Delta) and LABEL (like real field_option records)
        $option1 = new stdClass();
        $option1->value = '{"ops":[{"insert":"shell\n"}]}';
        $option1->label = 'Shell';
        $option1->selected_by_default = 1;

        $option2 = new stdClass();
        $option2->value = '{"ops":[{"insert":"exxon\n"}]}';
        $option2->label = 'Exxon Mobil';
        $option2->selected_by_default = 0;

        $option3 = new stdClass();
        $option3->value = '{"ops":[{"insert":"bp\n"}]}';
        $option3->label = 'BP';
        $option3->selected_by_default = 0;

        $option4 = new stdClass();
        $option4->value = '{"ops":[{"insert":"total\n"}]}';
        $option4->label = 'TotalEnergies';
        $option4->selected_by_default = 0;

        $option5 = new stdClass();
        $option5->value = '{"ops":[{"insert":"chevron\n"}]}';
        $option5->label = 'Chevron';
        $option5->selected_by_default = 0;

        $field = new stdClass();
        $field->id = 1;
        $field->type = 'select-invert';
        $field->content = '{"ops":[{"insert":" compared to "}]}';
        $field->fieldOptions = [$option1, $option2, $option3, $option4, $option5];

        $templateBody = '{"ops":[{"insert":"GEN:{{1}}"}]}';

        $template = Stub::make(
            PromptTemplate::class,
            [
                '__get' => function ($property) use ($templateBody, $field) {
                    if ($property === 'template_body') {
                        return $templateBody;
                    }
                    if ($property === 'fields') {
                        return [$field];
                    }
                    return null;
                },
                'getAttribute' => function ($name) use ($templateBody) {
                    if ($name === 'template_body') {
                        return $templateBody;
                    }
                    return null;
                }
            ]
        );

        $templateService = Stub::make(
            PromptTemplateService::class,
            ['getTemplateById' => Expected::once($template)],
            $this
        );

        $service = new PromptGenerationService($templateService);

        // User selects 'exxon' (plain text VALUE from the form after conversion)
        $result = $service->generateFinalPrompt(
            1,
            [],
            [1 => 'exxon'],
            self::USER_ID
        );

        $plainText = $this->getPlainTextFromQuillDelta($result);

        // Should output: "Exxon Mobil compared to Shell,BP,TotalEnergies,Chevron"
        $this->assertSame('Exxon Mobil compared to Shell,BP,TotalEnergies,Chevron', $plainText);
    }

    // --- Edge case tests for service orchestration ---

    /**
     * @throws Exception
     */
    public function testInvalidTemplateJsonReturnsEmptyOps(): void
    {
        $template = Stub::make(
            PromptTemplate::class,
            [
                '__get' => function ($property) {
                    if ($property === 'template_body') {
                        return '{invalid json';
                    }
                    if ($property === 'fields') {
                        return [];
                    }
                    return null;
                },
            ]
        );

        $templateService = Stub::make(
            PromptTemplateService::class,
            ['getTemplateById' => Expected::once($template)],
            $this
        );

        $service = new PromptGenerationService($templateService);

        $result = $service->generateFinalPrompt(1, [], [], self::USER_ID);

        $decoded = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('ops', $decoded);
        $this->assertSame([], $decoded['ops']);
    }

    /**
     * @throws Exception
     */
    public function testTemplateJsonWithoutOpsReturnsEmptyOps(): void
    {
        $template = Stub::make(
            PromptTemplate::class,
            [
                '__get' => function ($property) {
                    if ($property === 'template_body') {
                        return '{"foo":"bar"}';
                    }
                    if ($property === 'fields') {
                        return [];
                    }
                    return null;
                },
            ]
        );

        $templateService = Stub::make(
            PromptTemplateService::class,
            ['getTemplateById' => Expected::once($template)],
            $this
        );

        $service = new PromptGenerationService($templateService);

        $result = $service->generateFinalPrompt(1, [], [], self::USER_ID);

        $decoded = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('ops', $decoded);
        $this->assertSame([], $decoded['ops']);
    }

    /**
     * @throws Exception
     */
    public function testInvalidContextJsonIsIgnored(): void
    {
        $template = Stub::make(
            PromptTemplate::class,
            [
                '__get' => function ($property) {
                    if ($property === 'template_body') {
                        return '{"ops":[{"insert":"Template\n"}]}';
                    }
                    if ($property === 'fields') {
                        return [];
                    }
                    return null;
                },
            ]
        );

        $templateService = Stub::make(
            PromptTemplateService::class,
            ['getTemplateById' => Expected::once($template)],
            $this
        );

        $service = new PromptGenerationService($templateService);

        $contexts = [
            '{invalid json',
            '{"ops":[{"insert":"Valid context\n"}]}',
            'also invalid',
        ];

        $result = $service->generateFinalPrompt(1, $contexts, [], self::USER_ID);

        $plainText = $this->getPlainTextFromQuillDelta($result);
        $this->assertStringContainsString('Valid context', $plainText);
        $this->assertStringContainsString('Template', $plainText);
    }

    /**
     * @throws Exception
     */
    public function testContextOrderIsPreserved(): void
    {
        $template = Stub::make(
            PromptTemplate::class,
            [
                '__get' => function ($property) {
                    if ($property === 'template_body') {
                        return '{"ops":[{"insert":"End\n"}]}';
                    }
                    if ($property === 'fields') {
                        return [];
                    }
                    return null;
                },
            ]
        );

        $templateService = Stub::make(
            PromptTemplateService::class,
            ['getTemplateById' => Expected::once($template)],
            $this
        );

        $service = new PromptGenerationService($templateService);

        $contexts = [
            '{"ops":[{"insert":"First\n"}]}',
            '{"ops":[{"insert":"Second\n"}]}',
            '{"ops":[{"insert":"Third\n"}]}',
        ];

        $result = $service->generateFinalPrompt(1, $contexts, [], self::USER_ID);

        $plainText = $this->getPlainTextFromQuillDelta($result);

        // Verify order: First, Second, Third, End
        $posFirst = strpos($plainText, 'First');
        $posSecond = strpos($plainText, 'Second');
        $posThird = strpos($plainText, 'Third');
        $posEnd = strpos($plainText, 'End');

        $this->assertLessThan($posSecond, $posFirst, 'First should come before Second');
        $this->assertLessThan($posThird, $posSecond, 'Second should come before Third');
        $this->assertLessThan($posEnd, $posThird, 'Third should come before End');
    }

    /**
     * @throws Exception
     */
    public function testOutputIsValidJson(): void
    {
        $template = Stub::make(
            PromptTemplate::class,
            [
                '__get' => function ($property) {
                    if ($property === 'template_body') {
                        return '{"ops":[{"insert":"Hello\n"}]}';
                    }
                    if ($property === 'fields') {
                        return [];
                    }
                    return null;
                },
            ]
        );

        $templateService = Stub::make(
            PromptTemplateService::class,
            ['getTemplateById' => Expected::once($template)],
            $this
        );

        $service = new PromptGenerationService($templateService);

        $result = $service->generateFinalPrompt(1, [], [], self::USER_ID);

        // Should not throw
        $decoded = json_decode($result, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('ops', $decoded);
        $this->assertIsArray($decoded['ops']);
    }

    /**
     * @throws Exception
     */
    public function testContextWithoutOpsArrayIsIgnored(): void
    {
        $template = Stub::make(
            PromptTemplate::class,
            [
                '__get' => function ($property) {
                    if ($property === 'template_body') {
                        return '{"ops":[{"insert":"Template\n"}]}';
                    }
                    if ($property === 'fields') {
                        return [];
                    }
                    return null;
                },
            ]
        );

        $templateService = Stub::make(
            PromptTemplateService::class,
            ['getTemplateById' => Expected::once($template)],
            $this
        );

        $service = new PromptGenerationService($templateService);

        $contexts = [
            '{"foo":"bar"}',
            '{"ops":"not an array"}',
        ];

        $result = $service->generateFinalPrompt(1, $contexts, [], self::USER_ID);

        $plainText = $this->getPlainTextFromQuillDelta($result);
        $this->assertSame('Template', $plainText);
    }
}
