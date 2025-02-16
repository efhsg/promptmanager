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
        $postData = [
            'PromptTemplate' => [
                'template_body' => "Hello, GEN:{{codeType}} and PRJ:{{projectType}}!"
            ]
        ];
        $fieldsMapping = [
            'GEN:{{codeType}}' => ['id' => 3],
            'PRJ:{{projectType}}' => ['id' => 7]
        ];
        $convertedTemplate = "Hello, GEN:{{3}} and PRJ:{{7}}!";
        $model->expects($this->once())
            ->method('load')
            ->with(['PromptTemplate' => ['template_body' => $convertedTemplate]])
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
                'template_body' => "Hello, GEN:{{codeType}}!"
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
        Yii::configure((object)PromptTemplate::class, ['find' => fn() => $mockQuery]);
        $result = $this->service->getTemplatesByUser(999);
        $this->assertEquals([], $result);
    }

    public function testConvertPlaceholdersToIdsWithMultipleFields(): void
    {
        $template = "Hello, GEN:{{codeType}} and PRJ:{{projectType}}!";
        $fieldsMapping = [
            'GEN:{{codeType}}' => ['id' => 3],
            'PRJ:{{projectType}}' => ['id' => 7]
        ];
        $result = $this->service->convertPlaceholdersToIds($template, $fieldsMapping);
        $this->assertEquals("Hello, GEN:{{3}} and PRJ:{{7}}!", $result);
    }

    public function testConvertPlaceholdersToIdsReturnsOriginalWhenMappingMissing(): void
    {
        $template = "Hello, GEN:{{codeType}}!";
        $fieldsMapping = [];
        $result = $this->service->convertPlaceholdersToIds($template, $fieldsMapping);
        $this->assertEquals("Hello, GEN:{{codeType}}!", $result);
    }

    public function testConvertPlaceholdersToLabelsWithMultipleFields(): void
    {
        $template = "Welcome, GEN:{{3}} and PRJ:{{7}}!";
        $fieldsMapping = [
            'GEN:{{codeType}}' => ['id' => 3],
            'PRJ:{{projectType}}' => ['id' => 7]
        ];
        $result = $this->service->convertPlaceholdersToLabels($template, $fieldsMapping);
        $this->assertEquals("Welcome, GEN:{{codeType}} and PRJ:{{projectType}}!", $result);
    }

    public function testConvertPlaceholdersToLabelsReturnsOriginalWhenMappingMissing(): void
    {
        $template = "Welcome, GEN:{{3}}!";
        $fieldsMapping = [];
        $result = $this->service->convertPlaceholdersToLabels($template, $fieldsMapping);
        $this->assertEquals("Welcome, GEN:{{3}}!", $result);
    }
}
