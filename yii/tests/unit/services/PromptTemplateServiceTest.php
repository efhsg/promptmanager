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
}
