<?php

namespace tests\functional\controllers;

use Codeception\Exception\ModuleException;
use FunctionalTester;
use tests\fixtures\AuthAssignmentFixture;
use tests\fixtures\AuthItemChildFixture;
use tests\fixtures\AuthItemFixture;
use tests\fixtures\AuthRuleFixture;
use tests\fixtures\FieldFixture;
use tests\fixtures\UserFixture;
use Yii;

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


}