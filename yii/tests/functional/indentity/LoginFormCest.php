<?php /** @noinspection PhpUnused */

namespace tests\functional\indentity;

use app\modules\identity\models\User;
use Codeception\Exception\ModuleException;
use FunctionalTester;
use tests\fixtures\UserFixture;

class LoginFormCest
{
    private const TEST_USER_ID = 100;

    public function _before(FunctionalTester $I): void
    {
        $I->haveFixtures(['user' => UserFixture::class]);
        $I->amOnRoute('/identity/auth/login');
    }

    public function openLoginPage(FunctionalTester $I): void
    {
        $I->see('Login', 'h1');
    }

    /**
     * @throws ModuleException
     */
    public function internalLoginById(FunctionalTester $I): void
    {
        $I->amLoggedInAs(self::TEST_USER_ID);
        $I->amOnPage('/');
        $I->see('Logout (admin)');
    }

    /**
     * @throws ModuleException
     */
    public function internalLoginByInstance(FunctionalTester $I): void
    {
        $user = User::findByUsername('admin');
        if ($user === null) {
            $I->fail("User with username 'admin' does not exist.");
        } else {
            $I->amLoggedInAs($user);
            $I->amOnPage('/');
            $I->see('Logout (admin)');
        }
    }

    public function loginWithEmptyCredentials(FunctionalTester $I): void
    {
        $I->seeElement('#login-form');
        $I->submitForm('#login-form', []);
        $I->expectTo('see validations errors');
        $this->seeValidationErrors($I, [
            'Username cannot be blank.',
            'Password cannot be blank.'
        ]);
    }

    private function seeValidationErrors(FunctionalTester $I, array $errors): void
    {
        foreach ($errors as $error) {
            $I->see($error);
        }
    }

    public function loginWithWrongCredentials(FunctionalTester $I): void
    {
        $I->submitForm('#login-form', [
            'LoginForm[username]' => 'admin',
            'LoginForm[password]' => 'wrong',
        ]);
        $I->expectTo('see validations errors');
        $this->seeValidationErrors($I, ['Incorrect username or password.']);
    }

    public function loginSuccessfully(FunctionalTester $I): void
    {
        $I->submitForm('#login-form', [
            'LoginForm[username]' => 'admin',
            'LoginForm[password]' => 'admin',
        ]);
        $I->see('Logout (admin)');
        $I->dontSeeElement('form#login-form');
        $I->seeElement('button.logout');
    }
}
