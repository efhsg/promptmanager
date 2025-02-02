<?php /** @noinspection PhpExpressionResultUnusedInspection */

namespace tests\functional\controllers;

use Codeception\Exception\ModuleException;
use FunctionalTester;
use ReflectionClass;
use ReflectionException;
use tests\fixtures\AuthAssignmentFixture;
use tests\fixtures\AuthItemChildFixture;
use tests\fixtures\AuthItemFixture;
use tests\fixtures\AuthRuleFixture;
use tests\fixtures\FieldFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;
use Yii;
use yii\base\InvalidConfigException;

class FieldControllerCest
{

    private int $userId = 1;

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
            'fields' => FieldFixture::class,
        ]);

        $I->amLoggedInAs($this->userId);
    }

    public function testIndexDisplaysAllFields(FunctionalTester $I): void
    {
        $I->amOnRoute('/field/index');
        $I->seeResponseCodeIs(200);
        $I->see('codeBlock');
        $I->see('codeType');
        $I->see('extraCriteria');
        $I->dontSee('unitTest');
    }

    public function testViewDisplaysCorrectField(FunctionalTester $I): void
    {
        $I->amOnRoute('/field/view', ['id' => 1]);
        $I->seeResponseCodeIs(200);
        $I->see('Field Details');
        $I->see('codeBlock');
        $I->see('Type');
        $I->see('text');
    }

    public function testCreateNewFieldSuccessfully(FunctionalTester $I): void
    {
        $I->amOnRoute('/field/create');
        $I->seeResponseCodeIs(200);
        $I->see('Create Field');

        $I->submitForm('#field-form', [
            'Field[name]' => 'NewField',
            'Field[type]' => 'text',
            'FieldOption[0][value]' => 'Option1',
            'FieldOption[1][value]' => 'Option2',
        ]);

        $I->seeResponseCodeIsSuccessful();
        $I->seeInDatabase('field', ['name' => 'NewField', 'type' => 'text']);
        $I->seeInDatabase('field_option', ['value' => 'Option1']);
        $I->seeInDatabase('field_option', ['value' => 'Option2']);
        $I->see('Field Details');
        $I->see('NewField');
        $I->see('Type');
        $I->see('text');
    }

    public function testCreateFieldWithInvalidDataFails(FunctionalTester $I): void
    {
        $I->amOnRoute('/field/create');
        $I->seeResponseCodeIs(200);
        $I->see('Create Field');

        $I->submitForm('#field-form', [
            'Field[name]' => '',
            'Field[type]' => '',
        ]);

        $I->seeResponseCodeIsSuccessful();
        $I->see('Create Field');
        $I->see('Name cannot be blank.');
        $I->see('Type cannot be blank.');
    }


    public function testUpdateExistingFieldSuccessfully(FunctionalTester $I): void
    {
        $I->amOnRoute('/field/update', ['id' => 1]);
        $I->seeResponseCodeIs(200);
        $I->see('Update', 'h3');

        $I->submitForm('#field-form', [
            'Field[name]' => 'UpdatedField',
            'Field[type]' => 'select',
            'FieldOption[0][value]' => 'UpdatedOption1',
            'FieldOption[1][value]' => 'UpdatedOption2',
        ]);

        $I->seeResponseCodeIsSuccessful();
        $I->seeInDatabase('field', ['id' => 1, 'name' => 'UpdatedField', 'type' => 'select']);
        $I->seeInDatabase('field_option', ['field_id' => 1, 'value' => 'UpdatedOption1']);
        $I->seeInDatabase('field_option', ['field_id' => 1, 'value' => 'UpdatedOption2']);
        $I->see('Field Details');
        $I->see('UpdatedField');
        $I->see('Type');
        $I->see('select');
    }

    public function testUpdateFieldWithInvalidDataFails(FunctionalTester $I): void
    {
        $I->amOnRoute('/field/update', ['id' => 1]);
        $I->seeResponseCodeIs(200);
        $I->see('Update', 'h3');

        $I->submitForm('#field-form', [
            'Field[name]' => '',
            'Field[type]' => '',
        ]);

        $I->seeResponseCodeIsSuccessful();
        $I->see('Update', 'h3');
        $I->see('Name cannot be blank.');
        $I->see('Type cannot be blank.');
    }

    public function testDeleteFieldSuccessfully(FunctionalTester $I): void
    {
        $I->amOnRoute('/field/delete', ['id' => 1]);
        $I->seeResponseCodeIs(200);
        $I->see('Are you sure you want to delete');

        $I->submitForm('#delete-confirmation-form', [
            'confirm' => 1,
        ]);

        $I->seeResponseCodeIsSuccessful();
        $I->dontSeeInDatabase('field', ['id' => 1]);
        $I->see('Manage Fields');
    }

    public function testDeleteFieldWithoutConfirmationFails(FunctionalTester $I): void
    {
        $I->amOnRoute('/field/delete', ['id' => 1]);
        $I->seeResponseCodeIs(200);
        $I->see('Are you sure you want to delete');

        $I->click('Cancel');

        $I->seeResponseCodeIsSuccessful();
        $I->seeInDatabase('field', ['id' => 1]);
        $I->see('Manage Fields');
    }

    public function testDeleteActionRequiresPost(FunctionalTester $I): void
    {
        // First, perform a GET request to /field/delete to display confirmation.
        $I->amOnRoute('/field/delete', ['id' => 1]);
        $I->seeResponseCodeIs(200);
        $I->see('Are you sure you want to delete');

        // Now simulate a GET request without confirmation, ensuring the field still exists.
        $I->amOnRoute('/field/delete', ['id' => 1]);
        $I->seeInDatabase('field', ['id' => 1]);
    }

    public function testAuthorizedUserCanAccessFieldPages(FunctionalTester $I): void
    {
        $restrictedRoutes = [
            '/field/view?id=1',
            '/field/create',
            '/field/update?id=1',
            '/field/delete?id=1',
        ];

        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeResponseCodeIs(200);
        }
    }

    public function testUnauthorizedUserCannotAccessFieldPages(FunctionalTester $I): void
    {
        Yii::$app->permissionService->revokeAllUserPermissions($this->userId);

        $restrictedRoutes = [
            '/field/view?id=1',
            '/field/create',
            '/field/update?id=1',
            '/field/delete?id=1',
        ];

        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeResponseCodeIs(403);
            $I->see('Forbidden');
        }
    }

    public function testUserCannotAccessFieldsOfOtherUsers(FunctionalTester $I): void
    {
        $otherUserFieldId = 4;
        $restrictedRoutes = [
            "/field/view?id=$otherUserFieldId",
            "/field/update?id=$otherUserFieldId",
            "/field/delete?id=$otherUserFieldId",
        ];

        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeResponseCodeIs(404);
            $I->see('The requested field does not exist or is not yours');
        }
    }

    public function testUnauthenticatedUserIsRedirected(FunctionalTester $I): void
    {
        Yii::$app->user->logout();

        $restrictedRoutes = [
            '/field/view?id=1',
            '/field/create',
            '/field/update?id=1',
            '/field/delete?id=1',
        ];

        foreach ($restrictedRoutes as $route) {
            $I->amOnRoute($route);
            $I->seeInCurrentUrl('/identity/auth/login');
        }
    }

    public function testNonExistentFieldAccess(FunctionalTester $I): void
    {
        $I->amOnRoute('/field/view', ['id' => 9999]);
        $I->seeResponseCodeIs(404);
    }


    /**
     * @throws ReflectionException
     * @throws InvalidConfigException
     */
    public function testHasPermissionReturnsFalseForInvalidAction(FunctionalTester $I): void
    {
        $I->amOnRoute('/field/index');

        $controller = Yii::$app->createController('field')[0];

        $I->assertFalse(
            $this->invokeMethod($controller, 'hasPermission', ['invalidAction']),
            'hasPermission should return false for an invalid action'
        );
    }

    /**
     * @throws ReflectionException
     * @throws InvalidConfigException
     */
    public function testFindModelReturnsFieldWithProjectRelation(FunctionalTester $I): void
    {
        $controller = Yii::$app->createController('field')[0];

        $model = $this->invokeMethod($controller, 'findModel', [2]);

        $I->assertNotNull($model, 'findModel should return a valid model instance');
        $I->assertEquals(2, $model->id, 'Model ID should match the requested ID');
        $I->assertNotNull($model->project, 'Model should have a related project');
        $I->assertEquals('Test Project', $model->project->name, 'Related project name should be correct');
    }

    /**
     * @throws ReflectionException
     */
    private function invokeMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

}