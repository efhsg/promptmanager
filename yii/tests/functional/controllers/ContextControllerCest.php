<?php

/** @noinspection PhpPossiblePolymorphicInvocationInspection */
/** @noinspection PhpExpressionResultUnusedInspection */

/** @noinspection PhpUnused */

namespace tests\functional\controllers;

use Codeception\Exception\ModuleException;
use FunctionalTester;
use ReflectionClass;
use ReflectionException;
use tests\fixtures\AuthAssignmentFixture;
use tests\fixtures\AuthItemChildFixture;
use tests\fixtures\AuthItemFixture;
use tests\fixtures\AuthRuleFixture;
use tests\fixtures\ContextFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;

class ContextControllerCest
{
    private int $userId = 100;

    /**
     * @throws ModuleException
     */
    public function _before(FunctionalTester $I): void
    {
        $I->haveFixtures([
            'user' => UserFixture::class,
            'auth_rule' => AuthRuleFixture::class,
            'auth_item' => AuthItemFixture::class,
            'auth_item_child' => AuthItemChildFixture::class,
            'auth_assignment' => AuthAssignmentFixture::class,
            'projects' => ProjectFixture::class,
            'contexts' => ContextFixture::class,
        ]);

        $I->amLoggedInAs($this->userId);
    }
    public function testIndexAccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/context/index');
        $I->seeResponseCodeIs(200);
        $I->see('Test Project');
        $I->see('Test Context');
    }

    public function testViewAccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/context/view', ['id' => 1]);
        $I->seeResponseCodeIs(200);
        $I->see('Test Context');
        $I->see('Test Project');
    }

    public function testCreateContextSuccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/context/create');
        $I->seeResponseCodeIs(200);
        $I->see('Create Context');

        $I->submitForm('#context-form', [
            'Context[name]' => 'New Test Context',
            'Context[project_id]' => 1,
            'Context[content]' => 'This is a new test context',
        ]);

        $I->seeResponseCodeIs(200);
        $I->see('New Test Context');
        $I->seeInDatabase('context', [
            'name' => 'New Test Context',
            'project_id' => 1,
            'content' => 'This is a new test context',
        ]);
    }

    public function testCreateContextValidationError(FunctionalTester $I): void
    {
        $I->amOnRoute('/context/create');
        $I->seeResponseCodeIs(200);
        $I->see('Create Context');

        $I->submitForm('#context-form', [
            'Context[name]' => '',
            'Context[project_id]' => '',
            'Context[content]' => '',
        ]);

        $I->seeResponseCodeIs(200);
        $I->see('Create Context');
        $I->see('Name cannot be blank.');
        $I->see('Project cannot be blank.');
    }

    public function testUpdateContextSuccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/context/update', ['id' => 1]);
        $I->seeResponseCodeIs(200);
        $I->see('Update Test Context');

        $I->submitForm('#context-form', [
            'Context[name]' => 'Updated Test Context',
            'Context[content]' => 'Updated content for test context',
        ]);

        $I->seeResponseCodeIs(200);
        $I->see('Updated Test Context');
        $I->seeInDatabase('context', [
            'id' => 1,
            'name' => 'Updated Test Context',
            'content' => 'Updated content for test context',
        ]);
    }

    public function testDeleteContextWithConfirmation(FunctionalTester $I): void
    {
        $contextId = 1;
        $I->amOnRoute('/context/delete', ['id' => $contextId]);
        $I->seeResponseCodeIs(200);
        $I->submitForm('#delete-confirmation-form', ['confirm' => '1']);
        $I->dontSeeInDatabase('context', ['id' => $contextId]);
    }

    public function testDeleteContextWithoutConfirmation(FunctionalTester $I): void
    {
        $contextId = 1;
        $I->amOnRoute('/context/delete', ['id' => $contextId]);
        $I->seeResponseCodeIs(200);
        $I->submitForm('#delete-confirmation-form', ['confirm' => '0']);
        $I->seeInDatabase('context', ['id' => $contextId]);
    }

    public function testDeleteContextErrorHandling(FunctionalTester $I): void
    {
        $I->amOnRoute('/context/delete', ['id' => 999]);
        $I->seeResponseCodeIs(404);
    }
    public function testAuthorizedUserCanAccessContextPages(FunctionalTester $I): void
    {
        $restrictedRoutes = [
            '/context/view?id=1',
            '/context/create',
            '/context/update?id=1',
            '/context/delete?id=1',
        ];

        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeResponseCodeIs(200);
        }
    }

    public function testUnauthorizedUserCannotAccessContextPages(FunctionalTester $I): void
    {
        Yii::$app->permissionService->revokeAllUserPermissions($this->userId);

        $restrictedRoutes = [
            '/context/view?id=1',
            '/context/create',
            '/context/update?id=1',
            '/context/delete?id=1',
        ];

        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeResponseCodeIs(403);
            $I->see('Forbidden');
        }
    }

    public function testUserCannotAccessContextsOfOtherUsers(FunctionalTester $I): void
    {
        $otherUserContextId = 2;
        $restrictedRoutes = [
            "/context/view?id=$otherUserContextId",
            "/context/update?id=$otherUserContextId",
            "/context/delete?id=$otherUserContextId",
        ];

        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeResponseCodeIs(404);
            $I->see('The requested context does not exist or is not yours.');
        }
    }

    public function testUnauthenticatedUserIsRedirected(FunctionalTester $I): void
    {
        Yii::$app->user->logout();

        $restrictedRoutes = [
            '/context/view?id=1',
            '/context/create',
            '/context/update?id=1',
            '/context/delete?id=1',
        ];

        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeInCurrentUrl('/identity/auth/login');
        }
    }

    public function testNonExistentContextAccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/context/view', ['id' => 9999]);
        $I->seeResponseCodeIs(404);
    }

    /**
     * @throws InvalidConfigException
     */
    public function testHasActionPermissionReturnsFalseForInvalidAction(FunctionalTester $I): void
    {
        $I->amOnRoute('/context/index');

        $permissionService = Yii::$app->get('permissionService');

        $result = $permissionService->hasActionPermission('context', 'invalidAction');

        $I->assertFalse(
            $result,
            'hasActionPermission should return false for an invalid action'
        );
    }

    /**
     * @throws ReflectionException
     * @throws InvalidConfigException
     */
    public function testFindModelReturnsContextWithProjectRelation(FunctionalTester $I): void
    {
        $controller = Yii::$app->createController('context')[0];
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('findModel');
        $method->setAccessible(true);
        /** @var ActiveRecord $model */
        $model = $method->invokeArgs($controller, [1]);

        $I->assertNotNull($model, 'findModel should return a valid model instance');
        $I->assertEquals(1, $model->id, 'Model ID should match the requested ID');
        $I->assertNotNull($model->project, 'Model should have a related project');
        $I->assertEquals('Test Project', $model->project->name, 'Related project name should be correct');
    }
}
