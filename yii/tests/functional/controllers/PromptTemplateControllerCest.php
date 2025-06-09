<?php

/** @noinspection PhpUnused */

/** @noinspection PhpExpressionResultUnusedInspection */

namespace tests\functional\controllers;

use Codeception\Exception\ModuleException;
use FunctionalTester;
use tests\fixtures\AuthAssignmentFixture;
use tests\fixtures\AuthItemChildFixture;
use tests\fixtures\AuthItemFixture;
use tests\fixtures\AuthRuleFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\PromptTemplateFixture;
use tests\fixtures\UserFixture;
use Yii;
use yii\base\InvalidConfigException;

class PromptTemplateControllerCest
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
            'prompt_templates' => PromptTemplateFixture::class,
        ]);

        $I->amLoggedInAs($this->userId);
    }

    public function testIndexAccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/prompt-template/index');
        $I->seeResponseCodeIs(200);
        $I->see('Default Template');
        $I->see('Test Project'); // from the ProjectFixture
    }

    public function testViewAccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/prompt-template/view', ['id' => 1]);
        $I->seeResponseCodeIs(200);
        $I->see('Default Template');
        $I->see('Test Project');
    }

    public function testCreatePromptTemplateSuccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/prompt-template/create');
        $I->seeResponseCodeIs(200);
        $I->see('Create Template');
        $I->see('Project');
        $I->see('Name');
        $I->submitForm('#prompt-template-form', [
            'PromptTemplate[name]' => 'New Test Template',
            'PromptTemplate[project_id]' => 1,
            'PromptTemplate[template_body]' => 'This is a new test prompt template.',
        ]);

        $I->seeResponseCodeIsSuccessful();
        $I->see('New Test Template');
        $I->seeInDatabase('prompt_template', [
            'name' => 'New Test Template',
            'project_id' => 1,
            'template_body' => 'This is a new test prompt template.',
        ]);
    }

    public function testCreatePromptTemplateValidationError(FunctionalTester $I): void
    {
        $I->amOnRoute('/prompt-template/create');
        $I->seeResponseCodeIs(200);

        $I->submitForm('#prompt-template-form', [
            'PromptTemplate[name]' => '',
            'PromptTemplate[project_id]' => '',
            'PromptTemplate[template_body]' => '',
        ]);

        $I->seeResponseCodeIsSuccessful();
        $I->see('Name cannot be blank.');
        $I->see('Project cannot be blank.');
    }

    public function testUpdatePromptTemplateSuccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/prompt-template/update', ['id' => 1]);
        $I->seeResponseCodeIs(200);
        $I->see('Update - Default Template');
        $I->see('Project');
        $I->see('Name');
        $I->submitForm('#prompt-template-form', [
            'PromptTemplate[name]' => 'Updated Test Template',
            'PromptTemplate[template_body]' => 'Updated content for test prompt template.',
        ]);

        $I->seeResponseCodeIsSuccessful();
        $I->see('Updated Test Template');
        $I->seeInDatabase('prompt_template', [
            'id' => 1,
            'name' => 'Updated Test Template',
            'template_body' => 'Updated content for test prompt template.',
        ]);
    }

    public function testDeletePromptTemplateWithConfirmation(FunctionalTester $I): void
    {
        $templateId = 1;
        $I->amOnRoute('/prompt-template/delete', ['id' => $templateId]);
        $I->seeResponseCodeIs(200);
        $I->submitForm('#delete-confirmation-form', ['confirm' => '1']);
        $I->dontSeeInDatabase('prompt_template', ['id' => $templateId]);
    }

    public function testDeletePromptTemplateWithoutConfirmation(FunctionalTester $I): void
    {
        $templateId = 1;
        $I->amOnRoute('/prompt-template/delete', ['id' => $templateId]);
        $I->seeResponseCodeIs(200);
        $I->submitForm('#delete-confirmation-form', ['confirm' => '0']);
        $I->seeInDatabase('prompt_template', ['id' => $templateId]);
    }

    public function testDeletePromptTemplateErrorHandling(FunctionalTester $I): void
    {
        $I->amOnRoute('/prompt-template/delete', ['id' => 999]); // Non-existent ID
        $I->seeResponseCodeIs(404);
    }

    // ------------------ Permission Tests ------------------

    public function testAuthorizedUserCanAccessPromptTemplatePages(FunctionalTester $I): void
    {
        $restrictedRoutes = [
            '/prompt-template/view?id=1',
            '/prompt-template/create',
            '/prompt-template/update?id=1',
            '/prompt-template/delete?id=1',
        ];

        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeResponseCodeIs(200);
        }
    }

    public function testUnauthorizedUserCannotAccessPromptTemplatePages(FunctionalTester $I): void
    {
        // Revoke all permissions from the current user.
        Yii::$app->permissionService->revokeAllUserPermissions($this->userId);

        $restrictedRoutes = [
            '/prompt-template/view?id=1',
            '/prompt-template/create',
            '/prompt-template/update?id=1',
            '/prompt-template/delete?id=1',
        ];

        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeResponseCodeIs(403);
            $I->see('Forbidden');
        }
    }

    public function testUserCannotAccessPromptTemplatesOfOtherUsers(FunctionalTester $I): void
    {
        // Assuming prompt template with id=2 belongs to a project owned by another user.
        $otherTemplateId = 2;
        $restrictedRoutes = [
            "/prompt-template/view?id=$otherTemplateId",
            "/prompt-template/update?id=$otherTemplateId",
            "/prompt-template/delete?id=$otherTemplateId",
        ];

        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeResponseCodeIs(404);
            $I->see('The requested prompt template does not exist or is not yours.');
        }
    }

    public function testUnauthenticatedUserIsRedirected(FunctionalTester $I): void
    {
        Yii::$app->user->logout();

        $restrictedRoutes = [
            '/prompt-template/view?id=1',
            '/prompt-template/create',
            '/prompt-template/update?id=1',
            '/prompt-template/delete?id=1',
        ];

        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeInCurrentUrl('/identity/auth/login');
        }
    }

    public function testNonExistentPromptTemplateAccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/prompt-template/view', ['id' => 9999]);
        $I->seeResponseCodeIs(404);
    }

    /**
     * @throws InvalidConfigException
     */
    public function testHasActionPermissionReturnsFalseForInvalidAction(FunctionalTester $I): void
    {
        $I->amOnRoute('/prompt-template/index');

        $permissionService = Yii::$app->get('permissionService');
        $result = $permissionService->hasActionPermission('promptTemplate', 'invalidAction');

        $I->assertFalse(
            $result,
            'hasActionPermission should return false for an invalid action'
        );
    }

}
