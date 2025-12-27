<?php

namespace tests\unit\services;

use app\models\PromptTemplate;
use app\services\PromptTemplateService;
use Codeception\Test\Unit;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Exception;

class PromptTemplateServiceTest extends Unit
{
    private PromptTemplateService $service;

    protected function _before(): void
    {
        $this->service = new PromptTemplateService();
    }

    /**
     * @throws Exception
     */
    public function testSaveTemplateWithFieldsSuccessful(): void
    {
        $model = $this->getMockBuilder(PromptTemplate::class)
            ->onlyMethods(['load', 'save'])
            ->getMock();

        // Delta format input
        $deltaInput = '{"ops":[{"insert":"Hello, GEN:{{codeType}} and PRJ:{{projectType}}!\n"}]}';
        $postData = [
            'PromptTemplate' => [
                'template_body' => $deltaInput,
            ],
        ];
        $fieldsMapping = [
            'GEN:{{codeType}}' => ['id' => 3],
            'PRJ:{{projectType}}' => ['id' => 7],
        ];

        // Expected converted delta format
        $expectedDelta = '{"ops":[{"insert":"Hello, GEN:{{3}} and PRJ:{{7}}!\n"}]}';

        $model->expects($this->once())
            ->method('load')
            ->with(['PromptTemplate' => ['template_body' => $expectedDelta]])
            ->willReturn(true);
        $model->expects($this->once())
            ->method('save')
            ->willReturn(true);
        $model->id = 1;
        Yii::$app->db->createCommand('SET FOREIGN_KEY_CHECKS=0')->execute();
        $result = $this->service->saveTemplateWithFields($model, $postData, $fieldsMapping);
        Yii::$app->db->createCommand('SET FOREIGN_KEY_CHECKS=1')->execute();
        $this->assertTrue($result);
    }

    /**
     * @throws Exception
     */
    public function testSaveTemplateWithFieldsFailsOnInvalidData(): void
    {
        $model = $this->getMockBuilder(PromptTemplate::class)
            ->onlyMethods(['load'])
            ->getMock();
        $postData = [
            'PromptTemplate' => [
                'template_body' => '{"ops":[{"insert":"Hello, GEN:{{codeType}}!\n"}]}',
            ],
        ];
        $fieldsMapping = [];
        $model->expects($this->once())
            ->method('load')
            ->with($postData)
            ->willReturn(false);
        $result = $this->service->saveTemplateWithFields($model, $postData, $fieldsMapping);
        $this->assertFalse($result);
    }

    public function testGetTemplatesByUserReturnsEmptyArrayForNonExistingUser(): void
    {
        $mockQuery = $this->getMockBuilder(ActiveQuery::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockQuery->method('joinWith')->willReturnSelf();
        $mockQuery->method('where')->willReturnSelf();
        $mockQuery->method('all')->willReturn([]);
        $mockQuery->method('orderBy')->willReturnSelf();

        Yii::configure((object) PromptTemplate::class, ['find' => fn() => $mockQuery]);
        $result = $this->service->getTemplatesByUser(999);
        $this->assertEquals([], $result);
    }

    public function testConvertPlaceholdersToIdsWithMultipleFields(): void
    {
        $template = '{"ops":[{"insert":"Hello, GEN:{{codeType}} and PRJ:{{projectType}}!\n"}]}';

        $fieldsMapping = [
            'GEN:{{codeType}}' => ['id' => 3],
            'PRJ:{{projectType}}' => ['id' => 7],
        ];
        $result = $this->service->convertPlaceholdersToIds($template, $fieldsMapping);

        $expected = '{"ops":[{"insert":"Hello, GEN:{{3}} and PRJ:{{7}}!\n"}]}';

        $this->assertEquals($expected, $result);
    }

    public function testConvertPlaceholdersToIdsReturnsOriginalWhenMappingMissing(): void
    {
        $template = '{"ops":[{"insert":"Hello, GEN:{{codeType}}!\n"}]}';

        $fieldsMapping = [];
        $result = $this->service->convertPlaceholdersToIds($template, $fieldsMapping);

        $this->assertEquals($template, $result);
    }

    public function testConvertPlaceholdersToLabelsWithMultipleFields(): void
    {
        $template = '{"ops":[{"insert":"Welcome, GEN:{{3}} and PRJ:{{7}}!\n"}]}';

        $fieldsMapping = [
            'GEN:{{codeType}}' => ['id' => 3],
            'PRJ:{{projectType}}' => ['id' => 7],
        ];
        $result = $this->service->convertPlaceholdersToLabels($template, $fieldsMapping);

        $expected = '{"ops":[{"insert":"Welcome, GEN:{{codeType}} and PRJ:{{projectType}}!\n"}]}';

        $this->assertEquals($expected, $result);
    }

    public function testConvertPlaceholdersToLabelsReturnsOriginalWhenMappingMissing(): void
    {
        $template = '{"ops":[{"insert":"Welcome, GEN:{{3}}!\n"}]}';

        $fieldsMapping = [];
        $result = $this->service->convertPlaceholdersToLabels($template, $fieldsMapping);

        $this->assertEquals($template, $result);
    }

    public function testConvertPlaceholdersToIdsSupportsExternalFields(): void
    {
        $template = '{"ops":[{"insert":"Use EXT:{{Project Alpha: externalField}} and GEN:{{codeType}}.\n"}]}';

        $fieldsMapping = [
            'EXT:{{Project Alpha: externalField}}' => ['id' => 9],
            'GEN:{{codeType}}' => ['id' => 3],
        ];

        $result = $this->service->convertPlaceholdersToIds($template, $fieldsMapping);

        $expected = '{"ops":[{"insert":"Use EXT:{{9}} and GEN:{{3}}.\n"}]}';

        $this->assertEquals($expected, $result);
    }

    public function testConvertPlaceholdersToLabelsSupportsExternalFields(): void
    {
        $template = '{"ops":[{"insert":"Reference EXT:{{9}} here.\n"}]}';

        $fieldsMapping = [
            'EXT:{{Project Alpha: externalField}}' => ['id' => 9],
        ];

        $result = $this->service->convertPlaceholdersToLabels($template, $fieldsMapping);

        $expected = '{"ops":[{"insert":"Reference EXT:{{Project Alpha: externalField}} here.\n"}]}';

        $this->assertEquals($expected, $result);
    }

    public function testFallbackToLegacyFormat(): void
    {
        $template = "Hello, GEN:{{codeType}} and PRJ:{{projectType}}!";

        $fieldsMapping = [
            'GEN:{{codeType}}' => ['id' => 3],
            'PRJ:{{projectType}}' => ['id' => 7],
        ];

        $result = $this->service->convertPlaceholdersToIds($template, $fieldsMapping);

        $this->assertEquals($template, $result);
    }

    public function validateTemplatePlaceholdersDataProvider(): array
    {
        return [
            'valid fields' => [
                'template' => '{"ops":[{"insert":"Hello, GEN:{{codeType}} and PRJ:{{projectType}}!\n"}]}',
                'fieldsMapping' => [
                    'GEN:{{codeType}}' => ['id' => 3],
                    'PRJ:{{projectType}}' => ['id' => 7],
                ],
                'expectedInvalid' => [],
            ],
            'invalid fields' => [
                'template' => '{"ops":[{"insert":"Hello, GEN:{{invalidField}} and PRJ:{{anotherInvalid}}!\n"}]}',
                'fieldsMapping' => [
                    'GEN:{{codeType}}' => ['id' => 3],
                ],
                'expectedInvalid' => ['GEN:{{invalidField}}', 'PRJ:{{anotherInvalid}}'],
            ],
            'mixed valid and invalid' => [
                'template' => '{"ops":[{"insert":"Use GEN:{{codeType}} and GEN:{{invalidField}}.\n"}]}',
                'fieldsMapping' => [
                    'GEN:{{codeType}}' => ['id' => 3],
                ],
                'expectedInvalid' => ['GEN:{{invalidField}}'],
            ],
            'external fields' => [
                'template' => '{"ops":[{"insert":"Use EXT:{{Project Alpha: externalField}} and EXT:{{Invalid: field}}.\n"}]}',
                'fieldsMapping' => [
                    'EXT:{{Project Alpha: externalField}}' => ['id' => 9],
                ],
                'expectedInvalid' => ['EXT:{{Invalid: field}}'],
            ],
            'duplicate valid fields - not checked here' => [
                'template' => '{"ops":[{"insert":"GEN:{{codeType}}, GEN:{{codeType}}.\n"}]}',
                'fieldsMapping' => [
                    'GEN:{{codeType}}' => ['id' => 3],
                ],
                'expectedInvalid' => [],
            ],
            'empty template' => [
                'template' => '{"ops":[{"insert":"\n"}]}',
                'fieldsMapping' => [],
                'expectedInvalid' => [],
            ],
            'no placeholders' => [
                'template' => '{"ops":[{"insert":"Just plain text with no placeholders.\n"}]}',
                'fieldsMapping' => [
                    'GEN:{{codeType}}' => ['id' => 3],
                ],
                'expectedInvalid' => [],
            ],
            'invalid json' => [
                'template' => 'not valid json',
                'fieldsMapping' => [],
                'expectedInvalid' => [],
            ],
        ];
    }

    /**
     * @dataProvider validateTemplatePlaceholdersDataProvider
     */
    public function testValidateTemplatePlaceholders(string $template, array $fieldsMapping, array $expectedInvalid): void
    {
        $result = $this->service->validateTemplatePlaceholders($template, $fieldsMapping);

        $this->assertCount(count($expectedInvalid), $result);
        foreach ($expectedInvalid as $invalid) {
            $this->assertContains($invalid, $result);
        }
    }

    /**
     * @throws Exception
     */
    public function testSaveTemplateWithFieldsFailsWithInvalidPlaceholders(): void
    {
        $model = new PromptTemplate();
        $model->id = 1;
        $model->name = 'Test Template';
        $model->project_id = 1;

        $deltaInput = '{"ops":[{"insert":"Hello, GEN:{{invalidField}}!\n"}]}';
        $postData = [
            'PromptTemplate' => [
                'name' => 'Test Template',
                'project_id' => 1,
                'template_body' => $deltaInput,
            ],
        ];

        $fieldsMapping = [
            'GEN:{{validField}}' => ['id' => 3],
        ];

        $result = $this->service->saveTemplateWithFields($model, $postData, $fieldsMapping);

        $this->assertFalse($result);
        $this->assertTrue($model->hasErrors('template_body'));
        $errors = $model->getErrors('template_body');
        $this->assertStringContainsString('Invalid field placeholders found', $errors[0]);
        $this->assertStringContainsString('GEN:{{invalidField}}', $errors[0]);
    }

    public function findDuplicatePlaceholdersDataProvider(): array
    {
        return [
            'no duplicates' => [
                'template' => '{"ops":[{"insert":"Use GEN:{{field1}} and PRJ:{{field2}}.\n"}]}',
                'expectedDuplicates' => [],
            ],
            'single duplicate' => [
                'template' => '{"ops":[{"insert":"Use GEN:{{field1}} and GEN:{{field1}} again.\n"}]}',
                'expectedDuplicates' => ['GEN:{{field1}}'],
            ],
            'multiple duplicates' => [
                'template' => '{"ops":[{"insert":"GEN:{{field1}}, GEN:{{field1}}, PRJ:{{field2}}, PRJ:{{field2}}.\n"}]}',
                'expectedDuplicates' => ['GEN:{{field1}}', 'PRJ:{{field2}}'],
            ],
            'external field duplicates' => [
                'template' => '{"ops":[{"insert":"EXT:{{Project: field}} and EXT:{{Project: field}} again.\n"}]}',
                'expectedDuplicates' => ['EXT:{{Project: field}}'],
            ],
            'triplicate' => [
                'template' => '{"ops":[{"insert":"GEN:{{field1}}, GEN:{{field1}}, GEN:{{field1}}.\n"}]}',
                'expectedDuplicates' => ['GEN:{{field1}}'],
            ],
            'empty template' => [
                'template' => '{"ops":[{"insert":"\n"}]}',
                'expectedDuplicates' => [],
            ],
            'invalid json' => [
                'template' => 'not valid json',
                'expectedDuplicates' => [],
            ],
        ];
    }

    /**
     * @dataProvider findDuplicatePlaceholdersDataProvider
     */
    public function testFindDuplicatePlaceholders(string $template, array $expectedDuplicates): void
    {
        $result = $this->service->findDuplicatePlaceholders($template);

        $this->assertCount(count($expectedDuplicates), $result);
        foreach ($expectedDuplicates as $duplicate) {
            $this->assertContains($duplicate, $result);
        }
    }

    public function saveTemplateValidationFailureDataProvider(): array
    {
        return [
            'duplicate placeholders' => [
                'deltaInput' => '{"ops":[{"insert":"Use GEN:{{codeType}} and GEN:{{codeType}} again.\n"}]}',
                'fieldsMapping' => ['GEN:{{codeType}}' => ['id' => 3]],
                'expectedErrorSubstring' => 'Duplicate field placeholders found',
                'expectedErrorContains' => ['GEN:{{codeType}}'],
                'expectedErrorNotContains' => [],
            ],
            'multiple duplicates' => [
                'deltaInput' => '{"ops":[{"insert":"GEN:{{field1}}, GEN:{{field1}}, PRJ:{{field2}}, PRJ:{{field2}}.\n"}]}',
                'fieldsMapping' => [
                    'GEN:{{field1}}' => ['id' => 3],
                    'PRJ:{{field2}}' => ['id' => 7],
                ],
                'expectedErrorSubstring' => 'Duplicate field placeholders found',
                'expectedErrorContains' => ['GEN:{{field1}}', 'PRJ:{{field2}}'],
                'expectedErrorNotContains' => [],
            ],
            'invalid fields before duplicates' => [
                'deltaInput' => '{"ops":[{"insert":"GEN:{{invalid}}, GEN:{{invalid}}.\n"}]}',
                'fieldsMapping' => ['GEN:{{valid}}' => ['id' => 3]],
                'expectedErrorSubstring' => 'Invalid field placeholders found',
                'expectedErrorContains' => [],
                'expectedErrorNotContains' => ['Duplicate'],
            ],
            'mixed invalid and duplicate shows invalid first' => [
                'deltaInput' => '{"ops":[{"insert":"GEN:{{valid}}, GEN:{{valid}}, GEN:{{invalid}}.\n"}]}',
                'fieldsMapping' => ['GEN:{{valid}}' => ['id' => 3]],
                'expectedErrorSubstring' => 'Invalid field placeholders found',
                'expectedErrorContains' => ['GEN:{{invalid}}'],
                'expectedErrorNotContains' => [],
            ],
            'only duplicate valid fields shows duplicate error' => [
                'deltaInput' => '{"ops":[{"insert":"GEN:{{valid}}, GEN:{{valid}}.\n"}]}',
                'fieldsMapping' => ['GEN:{{valid}}' => ['id' => 3]],
                'expectedErrorSubstring' => 'Duplicate field placeholders found',
                'expectedErrorContains' => ['GEN:{{valid}}'],
                'expectedErrorNotContains' => ['Invalid'],
            ],
            'duplicate external fields' => [
                'deltaInput' => '{"ops":[{"insert":"EXT:{{Alpha: shared}}, GEN:{{local}}, EXT:{{Alpha: shared}}.\n"}]}',
                'fieldsMapping' => [
                    'GEN:{{local}}' => ['id' => 3],
                    'EXT:{{Alpha: shared}}' => ['id' => 9],
                ],
                'expectedErrorSubstring' => 'Duplicate field placeholders found',
                'expectedErrorContains' => ['EXT:{{Alpha: shared}}'],
                'expectedErrorNotContains' => [],
            ],
            'complex delta with duplicates' => [
                'deltaInput' => '{"ops":[{"insert":"First: "},{"insert":"GEN:{{field}}","attributes":{"bold":true}},{"insert":", Second: GEN:{{field}}.\n"}]}',
                'fieldsMapping' => ['GEN:{{field}}' => ['id' => 3]],
                'expectedErrorSubstring' => 'Duplicate field placeholders found',
                'expectedErrorContains' => ['GEN:{{field}}'],
                'expectedErrorNotContains' => [],
            ],
        ];
    }

    /**
     * @dataProvider saveTemplateValidationFailureDataProvider
     * @throws Exception
     */
    public function testSaveTemplateValidationFailures(
        string $deltaInput,
        array $fieldsMapping,
        string $expectedErrorSubstring,
        array $expectedErrorContains,
        array $expectedErrorNotContains
    ): void {
        $model = new PromptTemplate();
        $model->id = 1;
        $model->name = 'Test Template';
        $model->project_id = 1;

        $postData = [
            'PromptTemplate' => [
                'name' => 'Test Template',
                'project_id' => 1,
                'template_body' => $deltaInput,
            ],
        ];

        $result = $this->service->saveTemplateWithFields($model, $postData, $fieldsMapping);

        $this->assertFalse($result);
        $this->assertTrue($model->hasErrors('template_body'));
        $errors = $model->getErrors('template_body');
        $this->assertStringContainsString($expectedErrorSubstring, $errors[0]);

        foreach ($expectedErrorContains as $substring) {
            $this->assertStringContainsString($substring, $errors[0]);
        }

        foreach ($expectedErrorNotContains as $substring) {
            $this->assertStringNotContainsString($substring, $errors[0]);
        }
    }

    /**
     * @throws Exception
     */
    public function testSaveTemplateSucceedsWithValidUniqueFields(): void
    {
        $model = $this->getMockBuilder(PromptTemplate::class)
            ->onlyMethods(['load', 'save'])
            ->getMock();

        $deltaInput = '{"ops":[{"insert":"Use GEN:{{field1}}, PRJ:{{field2}}, and EXT:{{Project: field3}}.\n"}]}';
        $postData = [
            'PromptTemplate' => [
                'name' => 'Test Template',
                'project_id' => 1,
                'template_body' => $deltaInput,
            ],
        ];

        $fieldsMapping = [
            'GEN:{{field1}}' => ['id' => 3],
            'PRJ:{{field2}}' => ['id' => 7],
            'EXT:{{Project: field3}}' => ['id' => 9],
        ];

        $expectedDelta = '{"ops":[{"insert":"Use GEN:{{3}}, PRJ:{{7}}, and EXT:{{9}}.\n"}]}';
        $expectedPostData = [
            'PromptTemplate' => [
                'name' => 'Test Template',
                'project_id' => 1,
                'template_body' => $expectedDelta,
            ],
        ];

        $model->expects($this->once())
            ->method('load')
            ->with($expectedPostData)
            ->willReturn(true);
        $model->expects($this->once())
            ->method('save')
            ->willReturn(true);
        $model->id = 1;

        Yii::$app->db->createCommand('SET FOREIGN_KEY_CHECKS=0')->execute();
        $result = $this->service->saveTemplateWithFields($model, $postData, $fieldsMapping);
        Yii::$app->db->createCommand('SET FOREIGN_KEY_CHECKS=1')->execute();

        $this->assertTrue($result);
    }

    /**
     * @throws Exception
     */
    public function testSaveTemplatePreservesModelDataOnValidationFailure(): void
    {
        $model = new PromptTemplate();
        $model->id = 1;
        $model->name = 'Original Name';
        $model->project_id = 1;

        $deltaInput = '{"ops":[{"insert":"GEN:{{invalid}}.\n"}]}';
        $postData = [
            'PromptTemplate' => [
                'name' => 'New Name',
                'project_id' => 2,
                'template_body' => $deltaInput,
            ],
        ];

        $fieldsMapping = [
            'GEN:{{valid}}' => ['id' => 3],
        ];

        $result = $this->service->saveTemplateWithFields($model, $postData, $fieldsMapping);

        $this->assertFalse($result);
        $this->assertEquals('New Name', $model->name);
        $this->assertEquals(2, $model->project_id);
    }

}
