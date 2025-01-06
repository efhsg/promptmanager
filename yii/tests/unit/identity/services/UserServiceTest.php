<?php

namespace tests\unit\identity\services;

use app\modules\identity\exceptions\UserCreationException;
use app\modules\identity\models\User;
use app\modules\identity\services\UserService;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\Exception;
use tests\fixtures\UserFixture;
use Yii;
use yii\base\InvalidConfigException;
use yii\di\NotInstantiableException;

class UserServiceTest extends Unit
{
    private UserService $userService;

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
        ];
    }

    /**
     * @throws UserCreationException
     */
    public function testCreateUser()
    {
        $username = 'newuser';
        $email = 'newuser@example.com';
        $password = 'securepassword123';

        $user = $this->userService->create($username, $email, $password);

        verify($user)->notEmpty();
        verify($user->username)->equals($username);
        verify($user->email)->equals($email);
        verify(Yii::$app->security->validatePassword($password, $user->password_hash))->true();
        verify($user->status)->equals(User::STATUS_ACTIVE);
    }

    /**
     * @throws UserCreationException
     */
    public function testCreateUserWithException()
    {
        $this->expectException(\Exception::class);

        $this->userService->create('invaliduser', 'invalid-email', 'password123');
    }

    /**
     * @throws \yii\base\Exception
     */
    public function testGeneratePasswordResetToken()
    {
        $user = User::findOne(100);

        $result = $this->userService->generatePasswordResetToken($user);

        verify($result)->true();
        verify($user->password_reset_token)->notEmpty();
        verify(strpos($user->password_reset_token, '_'))->notFalse();
    }

    /**
     * @throws \yii\base\Exception
     */
    public function testRemovePasswordResetToken()
    {
        $user = User::findOne(100);
        $this->userService->generatePasswordResetToken($user);

        verify($user->password_reset_token)->notEmpty();

        $result = $this->userService->removePasswordResetToken($user);

        verify($result)->true();
        verify($user->password_reset_token)->null();
    }

    public function testSoftDelete()
    {
        $user = User::findOne(100);
        verify($user->deleted_at)->null();

        $result = $this->userService->softDelete($user);

        verify($result)->true();
        verify($user->deleted_at)->notNull();
    }

    public function testSoftDeleteAlreadyDeleted()
    {
        $user = User::findOne(100);
        $this->userService->softDelete($user);

        $result = $this->userService->softDelete($user);

        verify($result)->false();
    }

    public function testRestoreSoftDelete()
    {
        $user = User::findOne(100);
        $this->userService->softDelete($user);

        verify($user->deleted_at)->notNull();

        $result = $this->userService->restoreSoftDelete($user);

        verify($result)->true();
        verify($user->deleted_at)->null();
    }

    public function testRestoreSoftDeleteNotDeleted()
    {
        $user = User::findOne(100);
        verify($user->deleted_at)->null();

        $result = $this->userService->restoreSoftDelete($user);

        verify($result)->false();
    }

    public function testHardDelete()
    {
        $user = User::findOne(100);
        $this->assertNotNull($user);

        $result = $this->userService->hardDelete($user);

        $this->assertTrue($result);
        $deletedUser = User::findOne(100);
        $this->assertNull($deletedUser);
    }

    /**
     * @throws Exception
     */
    public function testHardDeleteFails()
    {
        $user = $this->createMock(User::class);
        $user->method('delete')->willThrowException(new \Exception('Delete operation failed'));

        $result = $this->userService->hardDelete($user);

        $this->assertFalse($result);
    }

    /**
     * @throws NotInstantiableException
     * @throws InvalidConfigException
     */
    protected function _before(): void
    {
        $this->userService = Yii::$container->get(UserService::class);
    }


}
