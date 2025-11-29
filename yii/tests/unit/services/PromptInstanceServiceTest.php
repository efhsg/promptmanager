<?php

namespace tests\unit\services;

use app\models\PromptInstance;
use app\services\PromptInstanceService;
use Codeception\Exception\ModuleException;
use Codeception\Test\Unit;
use tests\fixtures\ProjectFixture;
use tests\fixtures\PromptInstanceFixture;
use tests\fixtures\PromptTemplateFixture;
use tests\fixtures\UserFixture;
use UnitTester;
use Yii;
use yii\db\Exception as YiiDbException;
use yii\web\NotFoundHttpException;

/**
 * Tests for PromptInstanceService methods covering saving and owner-restricted retrieval.
 */
class PromptInstanceServiceTest extends Unit
{
    protected UnitTester $tester;

    private PromptInstanceService $service;

    private static bool $schemaRefreshed = false;

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'projects' => ProjectFixture::class,
            'templates' => PromptTemplateFixture::class,
            'instances' => PromptInstanceFixture::class,
        ];
    }

    protected function _before(): void
    {
        parent::_before();
        $this->service = new PromptInstanceService();
        if (!self::$schemaRefreshed && Yii::$app !== null && Yii::$app->has('db', true)) {
            Yii::$app->db->schema->refresh();
            self::$schemaRefreshed = true;
        }
    }

    /**
     * @throws YiiDbException
     */
    public function testSaveModelReturnsTrueOnSuccess(): void
    {
        $postData = ['PromptInstance' => ['name' => 'Test Name']];
        $promptInstanceMock = $this->createMock(PromptInstance::class);

        $promptInstanceMock->expects($this->once())
            ->method('load')
            ->with($postData)
            ->willReturn(true);

        $promptInstanceMock->expects($this->once())
            ->method('save')
            ->willReturn(true);

        $result = $this->service->saveModel($promptInstanceMock, $postData);

        $this->assertTrue($result);
    }

    /**
     * @throws YiiDbException
     */
    public function testSaveModelReturnsFalseWhenLoadFails(): void
    {
        $postData = ['PromptInstance' => ['name' => 'Test Name']];
        $promptInstanceMock = $this->createMock(PromptInstance::class);

        $promptInstanceMock->expects($this->once())
            ->method('load')
            ->with($postData)
            ->willReturn(false);

        $promptInstanceMock->expects($this->never())
            ->method('save');

        $result = $this->service->saveModel($promptInstanceMock, $postData);

        $this->assertFalse($result);
    }

    /**
     * @throws YiiDbException
     */
    public function testSaveModelReturnsFalseWhenSaveFails(): void
    {
        $postData = ['PromptInstance' => ['name' => 'Test Name']];
        $promptInstanceMock = $this->createMock(PromptInstance::class);

        $promptInstanceMock->expects($this->once())
            ->method('load')
            ->with($postData)
            ->willReturn(true);

        $promptInstanceMock->expects($this->once())
            ->method('save')
            ->willReturn(false);

        $result = $this->service->saveModel($promptInstanceMock, $postData);

        $this->assertFalse($result);
    }

    public function testSaveModelThrowsExceptionWhenSaveFailsWithDbException(): void
    {
        $postData = ['PromptInstance' => ['name' => 'Test Name']];
        $promptInstanceMock = $this->createMock(PromptInstance::class);

        $promptInstanceMock->expects($this->once())
            ->method('load')
            ->with($postData)
            ->willReturn(true);

        $promptInstanceMock->expects($this->once())
            ->method('save')
            ->willThrowException(new YiiDbException('DB error'));

        $this->expectException(YiiDbException::class);
        $this->expectExceptionMessage('DB error');

        $this->service->saveModel($promptInstanceMock, $postData);
    }

    /**
     * @throws YiiDbException
     */
    public function testSaveModelWithEmptyPostData(): void
    {
        $postData = [];
        $promptInstanceMock = $this->createMock(PromptInstance::class);

        $promptInstanceMock->expects($this->once())
            ->method('load')
            ->with($postData)
            ->willReturn(false);

        $promptInstanceMock->expects($this->never())
            ->method('save');

        $result = $this->service->saveModel($promptInstanceMock, $postData);

        $this->assertFalse($result);
    }

    /**
     * @throws NotFoundHttpException|ModuleException
     */
    public function testFindModelWithOwnerReturnsModelOnSuccess(): void
    {
        // Fixture 'user1' has ID 100.
        $instance = $this->tester->grabFixture('instances', 'instance1'); // ID 1, template_id 1
        $owner = $this->tester->grabFixture('users', 'user1'); // ID 100

        $model = $this->service->findModelWithOwner($instance->id, $owner->id);

        $this->assertSame($instance->id, $model->id);
    }

    /**
     * @throws ModuleException
     */
    public function testFindModelWithOwnerThrowsExceptionForNonExistentModel(): void
    {
        $nonExistentId = 999;
        $owner = $this->tester->grabFixture('users', 'user1'); // Using fixture reference

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The requested prompt instance does not exist or is not yours.');

        $this->service->findModelWithOwner($nonExistentId, $owner->id);
    }

    /**
     * @throws ModuleException
     */
    public function testFindModelWithOwnerThrowsExceptionForModelOwnedByAnotherUser(): void
    {
        $instance = $this->tester->grabFixture('instances', 'instance1'); // Instance ID 1, owned by user 100
        $nonOwner = $this->tester->grabFixture('users', 'user2'); // User ID 1

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The requested prompt instance does not exist or is not yours.');

        $this->service->findModelWithOwner($instance->id, $nonOwner->id);
    }

    public function testFindModelWithOwnerWithZeroIds(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The requested prompt instance does not exist or is not yours.');
        $this->service->findModelWithOwner(0, 0);
    }

    public function testFindModelWithOwnerWithNegativeIds(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The requested prompt instance does not exist or is not yours.');
        $this->service->findModelWithOwner(-1, -1);
    }
}
