<?php /** @noinspection PhpUnused */

namespace tests\functional\controllers;

use Codeception\Exception\ModuleException;
use FunctionalTester;
use tests\fixtures\ContextFixture;
use tests\fixtures\ProjectFixture;
use tests\fixtures\UserFixture;

class ContextControllerCest
{
    /**
     * @throws ModuleException
     */
    public function _before(FunctionalTester $I): void
    {
        $I->haveFixtures([
            'user' => UserFixture::class,
            'projects' => ProjectFixture::class,
            'contexts' => ContextFixture::class,
        ]);
        $I->amLoggedInAs(100);
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
        $I->see('This is a new test context');
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
        $I->see('Updated content for test context');
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
        $I->seeInDatabase('context', [
            'id' => $contextId,
        ]);
    }

    public function testDeleteContextErrorHandling(FunctionalTester $I): void
    {
        $I->amOnRoute('/context/delete', ['id' => 999]); // Non-existent ID
        $I->seeResponseCodeIs(404);
    }

}
