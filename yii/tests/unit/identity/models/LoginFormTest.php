<?php

namespace tests\unit\identity\models;

use app\modules\identity\models\LoginForm;
use Codeception\Test\Unit;
use tests\fixtures\UserFixture;
use Yii;

class LoginFormTest extends Unit
{

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
        ];
    }

    public function testLoginSuccess()
    {
        $model = new LoginForm([
            'username' => 'admin',
            'password' => 'admin',
        ]);

        verify($model->login())->true();
        verify(Yii::$app->user->isGuest)->false();
        verify(Yii::$app->user->identity->username)->equals('admin');
    }

    public function testLoginFailureWithIncorrectPassword()
    {
        $model = new LoginForm([
            'username' => 'admin',
            'password' => 'wrong_password',
        ]);

        verify($model->login())->false();
        verify($model->hasErrors('password'))->true();
        verify($model->getFirstError('password'))->equals('Incorrect username or password.');
    }

    public function testLoginFailureWithNonExistingUser()
    {
        $model = new LoginForm([
            'username' => 'non_existing_user',
            'password' => 'some_password',
        ]);

        verify($model->login())->false();
        verify($model->hasErrors('password'))->true();
        verify($model->getFirstError('password'))->equals('Incorrect username or password.');
    }

    public function testLoginWithRememberMe()
    {
        $model = new LoginForm([
            'username' => 'admin',
            'password' => 'admin',
            'rememberMe' => true,
        ]);

        verify($model->login())->true();
        $identity = Yii::$app->user->identity;
        verify($identity)->notEmpty();
        verify(Yii::$app->user->isGuest)->false();
        verify(Yii::$app->user->identity->username)->equals('admin');

        // Verify that the session cookie is set for 30 days (3600 * 24 * 30 seconds)
        $duration = Yii::$app->user->authTimeout;
        verify($duration)->equals(3600 * 24 * 30);
    }

    public function testLoginWithEmptyFields()
    {
        $model = new LoginForm([
            'username' => '',
            'password' => '',
        ]);

        verify($model->validate())->false();
        verify($model->hasErrors('username'))->true();
        verify($model->hasErrors('password'))->true();
    }

    public function testGetUser()
    {
        $model = new LoginForm(['username' => 'admin']);
        $user = $model->getUser();

        verify($user)->notEmpty();
        verify($user->username)->equals('admin');

        $nonExistentModel = new LoginForm(['username' => 'non_existing_user']);
        verify($nonExistentModel->getUser())->empty();
    }
}
