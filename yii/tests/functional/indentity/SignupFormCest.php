<?php /** @noinspection PhpUnused */

namespace tests\functional\indentity;

use app\modules\identity\models\User;
use app\modules\identity\services\UserService;
use FunctionalTester;
use tests\fixtures\UserFixture;
use Yii;
use yii\base\InvalidConfigException;
use yii\di\NotInstantiableException;

class SignupFormCest
{

    private UserService $userService;

    /**
     * @throws NotInstantiableException
     * @throws InvalidConfigException
     */
    public function _before(FunctionalTester $I): void
    {
        $this->userService = Yii::$container->get(UserService::class);
        $I->haveFixtures(['user' => UserFixture::class]);
        $I->amOnRoute('/identity/auth/signup');
    }

    public function openSignupPage(FunctionalTester $I): void
    {
        $I->see('Signup', 'h1');
    }

    public function signupWithEmptyFields(FunctionalTester $I): void
    {
        $I->seeElement('#signup-form');
        $I->submitForm('#signup-form', []);
        $I->expectTo('see validation errors');
        $this->seeValidationErrors($I, [
            'Username cannot be blank.',
            'Email cannot be blank.',
            'Password cannot be blank.',
        ]);
    }

    private function seeValidationErrors(FunctionalTester $I, array $errors): void
    {
        foreach ($errors as $error) {
            $I->see($error);
        }
    }

    public function signupWithInvalidEmail(FunctionalTester $I): void
    {
        $I->submitForm('#signup-form', [
            'SignupForm[username]' => 'newuser',
            'SignupForm[email]' => 'invalid-email',
            'SignupForm[password]' => 'password123',
        ]);
        $I->expectTo('see validation errors');
        $this->seeValidationErrors($I, ['Email is not a valid email address.']);
    }

    public function signupSuccessfully(FunctionalTester $I): void
    {
        $I->submitForm('#signup-form', [
            'SignupForm[username]' => 'uniqueuser456',
            'SignupForm[email]' => 'uniqueuser456@example.com',
            'SignupForm[password]' => 'securepassword123',
            'SignupForm[captcha]' => 'testme',
        ]);

        $I->seeInDatabase('user', ['username' => 'uniqueuser456', 'email' => 'uniqueuser456@example.com']);
        $I->see('Login', 'h1');
        $I->see('Registration successful! You can now log in.', 'div.alert-success.alert-dismissible');

        $user = User::findOne(['username' => 'uniqueuser456']);
        if ($user) {
            $this->userService->hardDelete($user);
        }

    }


}
