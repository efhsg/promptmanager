<?php

namespace identity\tests\unit\models;

use app\modules\identity\models\ChangePasswordForm;
use app\modules\identity\models\User;
use app\modules\identity\services\UserService;
use Codeception\Stub;
use Codeception\Test\Unit;
use Exception;
use tests\fixtures\UserFixture;
use Yii;

class ChangePasswordFormTest extends Unit
{
    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
        ];
    }

    private function loginFixtureUser(): User
    {
        $user = User::findByUsername('admin');
        Yii::$app->user->login($user);

        return $user;
    }

    /**
     * @throws Exception
     */
    public function testValidationFailsWhenFieldsEmpty(): void
    {
        $this->loginFixtureUser();
        $userService = Stub::make(UserService::class);

        $model = new ChangePasswordForm($userService, [
            'currentPassword' => '',
            'newPassword' => '',
            'confirmPassword' => '',
        ]);

        verify($model->validate())->false();
        verify($model->hasErrors('currentPassword'))->true();
        verify($model->hasErrors('newPassword'))->true();
        verify($model->hasErrors('confirmPassword'))->true();
    }

    /**
     * @throws Exception
     */
    public function testValidationFailsWhenCurrentPasswordWrong(): void
    {
        $this->loginFixtureUser();
        $userService = Stub::make(UserService::class);

        $model = new ChangePasswordForm($userService, [
            'currentPassword' => 'wrong_password',
            'newPassword' => 'newpass123',
            'confirmPassword' => 'newpass123',
        ]);

        verify($model->validate())->false();
        verify($model->hasErrors('currentPassword'))->true();
        verify($model->getFirstError('currentPassword'))->equals('Current password is incorrect.');
    }

    /**
     * @throws Exception
     */
    public function testValidationFailsWhenNewPasswordTooShort(): void
    {
        $this->loginFixtureUser();
        $userService = Stub::make(UserService::class);

        $model = new ChangePasswordForm($userService, [
            'currentPassword' => 'admin',
            'newPassword' => 'ab',
            'confirmPassword' => 'ab',
        ]);

        verify($model->validate())->false();
        verify($model->hasErrors('newPassword'))->true();
    }

    /**
     * @throws Exception
     */
    public function testValidationFailsWhenConfirmDoesNotMatch(): void
    {
        $this->loginFixtureUser();
        $userService = Stub::make(UserService::class);

        $model = new ChangePasswordForm($userService, [
            'currentPassword' => 'admin',
            'newPassword' => 'newpass123',
            'confirmPassword' => 'different',
        ]);

        verify($model->validate())->false();
        verify($model->hasErrors('confirmPassword'))->true();
        verify($model->getFirstError('confirmPassword'))->equals('Passwords do not match.');
    }

    /**
     * @throws Exception
     */
    public function testValidationFailsWhenNewPasswordSameAsCurrent(): void
    {
        $this->loginFixtureUser();
        $userService = Stub::make(UserService::class);

        $model = new ChangePasswordForm($userService, [
            'currentPassword' => 'admin',
            'newPassword' => 'admin',
            'confirmPassword' => 'admin',
        ]);

        verify($model->validate())->false();
        verify($model->hasErrors('newPassword'))->true();
        verify($model->getFirstError('newPassword'))->equals('New password must differ from your current password.');
    }

    /**
     * @throws Exception
     */
    public function testChangePasswordSuccess(): void
    {
        $user = $this->loginFixtureUser();

        $userService = Stub::make(UserService::class, [
            'changePassword' => function (User $u, string $newPassword) use ($user) {
                verify($u->id)->equals($user->id);
                verify($newPassword)->equals('newpass123');
                return true;
            },
        ]);

        $model = new ChangePasswordForm($userService, [
            'currentPassword' => 'admin',
            'newPassword' => 'newpass123',
            'confirmPassword' => 'newpass123',
        ]);

        verify($model->changePassword())->true();
    }

    /**
     * @throws Exception
     */
    public function testChangePasswordFailsWhenServiceReturnsFalse(): void
    {
        $this->loginFixtureUser();

        $userService = Stub::make(UserService::class, [
            'changePassword' => false,
        ]);

        $model = new ChangePasswordForm($userService, [
            'currentPassword' => 'admin',
            'newPassword' => 'newpass123',
            'confirmPassword' => 'newpass123',
        ]);

        verify($model->changePassword())->false();
    }
}
