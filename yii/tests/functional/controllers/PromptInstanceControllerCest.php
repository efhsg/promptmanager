<?php

namespace tests\functional\controllers;

use Codeception\Exception\ModuleException;
use FunctionalTester;
use tests\fixtures\AuthAssignmentFixture;
use tests\fixtures\AuthItemChildFixture;
use tests\fixtures\AuthItemFixture;
use tests\fixtures\AuthRuleFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\PromptInstanceFixture;
use tests\fixtures\PromptTemplateFixture;
use tests\fixtures\UserFixture;
use Yii;

class PromptInstanceControllerCest
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
            'prompt_instances' => PromptInstanceFixture::class,
        ]);
        $I->amLoggedInAs($this->userId);
    }

    public function testIndexAccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/prompt-instance/index');
        $I->seeResponseCodeIs(200);
        $I->see('Sample final prompt');
    }

    public function testViewAccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/prompt-instance/view', ['id' => 1]);
        $I->seeResponseCodeIs(200);
        $I->see('Sample final prompt');
    }

    public function testCreatePromptInstanceSuccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/prompt-instance/create');
        $I->submitForm('#prompt-instance-form', [
            'PromptInstanceForm[template_id]' => 1,
            'PromptInstanceForm[label]' => 'Test Instance',
            'PromptInstanceForm[final_prompt]' => '{"ops":[{"insert":"Test prompt instance\n"}]}',
        ]);
        $I->seeResponseCodeIsSuccessful();
        $I->see('Test prompt instance');
        $I->seeInDatabase('prompt_instance', [
            'template_id' => 1,
            'project_id' => 1,
            'final_prompt' => '{"ops":[{"insert":"Test prompt instance\n"}]}',
        ]);
    }

    // ------------------ Permission Tests ------------------

    public function testAuthorizedUserCanAccessPromptInstancePages(FunctionalTester $I): void
    {
        $restrictedRoutes = [
            '/prompt-instance/view?id=1',
            '/prompt-instance/create',
            '/prompt-instance/update?id=1',
            '/prompt-instance/delete?id=1',
        ];
        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeResponseCodeIs(200);
        }
    }

    public function testUnauthorizedUserCannotAccessPromptInstancePages(FunctionalTester $I): void
    {
        Yii::$app->permissionService->revokeAllUserPermissions($this->userId);
        $restrictedRoutes = [
            '/prompt-instance/view?id=1',
            '/prompt-instance/create',
            '/prompt-instance/update?id=1',
            '/prompt-instance/delete?id=1',
        ];
        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeResponseCodeIs(403);
            $I->see('Forbidden');
        }
    }

    public function testUserCannotAccessPromptInstancesOfOtherUsers(FunctionalTester $I): void
    {
        $otherInstanceId = 2;
        $restrictedRoutes = [
            "/prompt-instance/view?id=$otherInstanceId",
            "/prompt-instance/update?id=$otherInstanceId",
            "/prompt-instance/delete?id=$otherInstanceId",
        ];
        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeResponseCodeIs(404);
            $I->see('The requested prompt instance does not exist or is not yours.');
        }
    }

    public function testUnauthenticatedUserIsRedirectedFromPromptInstancePages(FunctionalTester $I): void
    {
        Yii::$app->user->logout();
        $restrictedRoutes = [
            '/prompt-instance/view?id=1',
            '/prompt-instance/create',
            '/prompt-instance/update?id=1',
            '/prompt-instance/delete?id=1',
        ];
        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeInCurrentUrl('/identity/auth/login');
        }
    }

    public function testNonExistentPromptInstanceAccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/prompt-instance/view', ['id' => 9999]);
        $I->seeResponseCodeIs(404);
    }


}
