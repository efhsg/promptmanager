<?php

namespace tests\unit\identity\models;

use app\modules\identity\exceptions\UserCreationException;
use app\modules\identity\models\SignupForm;
use app\modules\identity\models\User;
use app\modules\identity\services\UserService;
use Codeception\Stub;
use Codeception\Test\Unit;
use Exception;
use Yii;
use yii\captcha\CaptchaValidator;

class SignupFormTest extends Unit
{

    /**
     * @throws Exception
     */
    public function testSignupSuccess()
    {
        /** @var UserService $userService */
        $userService = Stub::make(UserService::class, [
            'create' => function ($username, $email, $password) {
                $user = new User([
                    'username' => $username,
                    'email' => $email,
                ]);
                $user->setPassword($password);
                return $user;
            }
        ]);

        $captchaValidator = Stub::make(CaptchaValidator::class, [
            'validateAttribute' => function () {
                return true;
            }
        ]);

        Yii::$container->set(CaptchaValidator::class, $captchaValidator);

        $model = new SignupForm($userService, [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'securepassword',
            'captcha' => 'correct-captcha-value',
        ]);

        $user = $model->signup();

        verify($user)->notEmpty();
        verify($user->username)->equals('newuser');
        verify($user->email)->equals('newuser@example.com');
        verify($user->validatePassword('securepassword'))->true();
    }

    /**
     * @throws Exception
     */
    public function testSignupFailureWithEmptyFields()
    {
        /** @var UserService $userService */
        $userService = Stub::make(UserService::class);

        $model = new SignupForm($userService, [
            'username' => '',
            'email' => '',
            'password' => '',
        ]);

        $user = $model->signup();

        verify($user)->null();
        verify($model->hasErrors('username'))->true();
        verify($model->hasErrors('email'))->true();
        verify($model->hasErrors('password'))->true();
    }

    /**
     * @throws Exception
     */
    public function testSignupFailureWithInvalidPasswordLength()
    {
        $userService = Stub::make(UserService::class);

        /** @var UserService $userService */
        $model = new SignupForm($userService, [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'ab', // Too short
        ]);

        $user = $model->signup();

        verify($user)->null();
        verify($model->hasErrors('password'))->true();
    }

    /**
     * @throws Exception
     */
    public function testSignupFailureWhenUserServiceThrowsException()
    {
        $userService = Stub::make(UserService::class, [
            'create' => function () {
                throw new UserCreationException('User creation failed');
            }
        ]);

        /** @var UserService $userService */
        $model = new SignupForm($userService, [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'securepassword',
        ]);

        $user = $model->signup();

        verify($user)->null();
        verify($model->hasErrors('username'))->true();
        verify($model->getFirstError('username'))->equals('User creation failed');
    }


}
