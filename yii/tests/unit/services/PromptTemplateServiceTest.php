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
                'template_body' => $deltaInput
            ]
        ];
        $fieldsMapping = [
            'GEN:{{codeType}}' => ['id' => 3],
            'PRJ:{{projectType}}' => ['id' => 7]
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
                'template_body' => '{"ops":[{"insert":"Hello, GEN:{{codeType}}!\n"}]}'
            ]
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

        Yii::configure((object)PromptTemplate::class, ['find' => fn() => $mockQuery]);
        $result = $this->service->getTemplatesByUser(999);
        $this->assertEquals([], $result);
    }

    public function testConvertPlaceholdersToIdsWithMultipleFields(): void
    {
        $template = '{"ops":[{"insert":"Hello, GEN:{{codeType}} and PRJ:{{projectType}}!\n"}]}';

        $fieldsMapping = [
            'GEN:{{codeType}}' => ['id' => 3],
            'PRJ:{{projectType}}' => ['id' => 7]
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
            'PRJ:{{projectType}}' => ['id' => 7]
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
            'PRJ:{{projectType}}' => ['id' => 7]
        ];

        $result = $this->service->convertPlaceholdersToIds($template, $fieldsMapping);

        $this->assertEquals($template, $result);
    }

    public function testValidateTemplatePlaceholdersWithValidFields(): void
    {
        $template = '{"ops":[{"insert":"Hello, GEN:{{codeType}} and PRJ:{{projectType}}!\n"}]}';

        $fieldsMapping = [
            'GEN:{{codeType}}' => ['id' => 3],
            'PRJ:{{projectType}}' => ['id' => 7]
        ];

        $result = $this->service->validateTemplatePlaceholders($template, $fieldsMapping);

        $this->assertEmpty($result);
    }

    public function testValidateTemplatePlaceholdersWithInvalidFields(): void
    {
        $template = '{"ops":[{"insert":"Hello, GEN:{{invalidField}} and PRJ:{{anotherInvalid}}!\n"}]}';

        $fieldsMapping = [
            'GEN:{{codeType}}' => ['id' => 3]
        ];

        $result = $this->service->validateTemplatePlaceholders($template, $fieldsMapping);

        $this->assertCount(2, $result);
        $this->assertContains('GEN:{{invalidField}}', $result);
        $this->assertContains('PRJ:{{anotherInvalid}}', $result);
    }

    public function testValidateTemplatePlaceholdersWithMixedValidAndInvalid(): void
    {
        $template = '{"ops":[{"insert":"Use GEN:{{codeType}} and GEN:{{invalidField}}.\n"}]}';

        $fieldsMapping = [
            'GEN:{{codeType}}' => ['id' => 3]
        ];

        $result = $this->service->validateTemplatePlaceholders($template, $fieldsMapping);

        $this->assertCount(1, $result);
        $this->assertContains('GEN:{{invalidField}}', $result);
    }

    public function testValidateTemplatePlaceholdersWithExternalFields(): void
    {
        $template = '{"ops":[{"insert":"Use EXT:{{Project Alpha: externalField}} and EXT:{{Invalid: field}}.\n"}]}';

        $fieldsMapping = [
            'EXT:{{Project Alpha: externalField}}' => ['id' => 9]
        ];

        $result = $this->service->validateTemplatePlaceholders($template, $fieldsMapping);

        $this->assertCount(1, $result);
        $this->assertContains('EXT:{{Invalid: field}}', $result);
    }

    public function testValidateTemplatePlaceholdersWithEmptyTemplate(): void
    {
        $template = '{"ops":[{"insert":"\n"}]}';
        $fieldsMapping = [];

        $result = $this->service->validateTemplatePlaceholders($template, $fieldsMapping);

        $this->assertEmpty($result);
    }

    public function testValidateTemplatePlaceholdersWithNoPlaceholders(): void
    {
        $template = '{"ops":[{"insert":"Just plain text with no placeholders.\n"}]}';

        $fieldsMapping = [
            'GEN:{{codeType}}' => ['id' => 3]
        ];

        $result = $this->service->validateTemplatePlaceholders($template, $fieldsMapping);

        $this->assertEmpty($result);
    }

    public function testValidateTemplatePlaceholdersWithInvalidJson(): void
    {
        $template = 'not valid json';
        $fieldsMapping = [];

        $result = $this->service->validateTemplatePlaceholders($template, $fieldsMapping);

        $this->assertEmpty($result);
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
                'template_body' => $deltaInput
            ]
        ];

        $fieldsMapping = [
            'GEN:{{validField}}' => ['id' => 3]
        ];

        $result = $this->service->saveTemplateWithFields($model, $postData, $fieldsMapping);

        $this->assertFalse($result);
        $this->assertTrue($model->hasErrors('template_body'));
        $errors = $model->getErrors('template_body');
        $this->assertStringContainsString('Invalid field placeholders found', $errors[0]);
        $this->assertStringContainsString('GEN:{{invalidField}}', $errors[0]);
    }
}
