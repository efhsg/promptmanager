<?php

namespace tests\functional\controllers;

use Codeception\Exception\ModuleException;
use FunctionalTester;
use tests\fixtures\FieldFixture;
use tests\fixtures\UserFixture;

class FieldControllerCest
{
    /**
     * @throws ModuleException
     */
    public function _before(FunctionalTester $I): void
    {
        $I->haveFixtures([
            'users' => UserFixture::class,
            'fields' => FieldFixture::class,
        ]);
        $I->amLoggedInAs(1); // Log in as 'userWithField'
    }

    public function testIndexDisplaysAllFields(FunctionalTester $I): void
    {
        $I->amOnRoute('/field/index'); // Navigate to the index page
        $I->seeResponseCodeIs(200); // Ensure the page loads successfully
        $I->see('codeBlock'); // Check if field 'codeBlock' is displayed
        $I->see('codeType'); // Check if field 'codeType' is displayed
        $I->see('extraCriteria'); // Check if field 'extraCriteria' is displayed
        $I->dontSee('unitTest'); // Ensure 'unitTest', which belongs to another user, is not displayed
    }
}