<?php

namespace identity\tests\unit\services;

use app\modules\identity\exceptions\UserCreationException;
use app\modules\identity\models\User;
use app\modules\identity\services\UserDataSeederInterface;
use app\modules\identity\services\UserService;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\Exception as MockException;
use PHPUnit\Framework\MockObject\MockObject;
use tests\fixtures\UserFixture;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\di\NotInstantiableException;
use yii\rbac\ManagerInterface;
use yii\rbac\Role;

class UserServiceTest extends Unit
{
    private UserService $userService;

    /**
     * @var ManagerInterface|MockObject
     */
    private ManagerInterface|MockObject $authManagerMock;

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
        ];
    }

    /**
     * @throws NotInstantiableException
     * @throws InvalidConfigException
     * @throws MockException
     */
    protected function _before(): void
    {
        parent::_before();

        $this->authManagerMock = $this->createMock(ManagerInterface::class);
        Yii::$app->set('authManager', $this->authManagerMock);

        $this->userService = Yii::$container->get(UserService::class);
    }

    /**
     * @throws InvalidConfigException
     */
    protected function _after(): void
    {
        Yii::$app->set('authManager', null);

        parent::_after();
    }

    /**
     * @throws UserCreationException
     * @throws MockException
     */
    public function testCreateUser(): void
    {
        $username = 'newuser';
        $email = 'newuser@example.com';
        $password = 'securepassword123';

        $roleMock = $this->createMock(Role::class);
        $roleMock->name = 'user';

        $this->authManagerMock
            ->expects($this->once())
            ->method('getRole')
            ->with('user')
            ->willReturn($roleMock);

        $this->authManagerMock
            ->expects($this->once())
            ->method('assign')
            ->with(
                $this->equalTo($roleMock),
                $this->isInt()
            )
            ->willReturn(true);

        $user = $this->userService->create($username, $email, $password);

        verify($user)->notEmpty();
        verify($user->username)->equals($username);
        verify($user->email)->equals($email);
        verify(Yii::$app->security->validatePassword($password, $user->password_hash))->true();
        verify($user->status)->equals(User::STATUS_ACTIVE);
    }

    /**
     * @throws UserCreationException
     * @throws MockException
     * @throws InvalidConfigException
     */
    public function testCreateUserWithoutRbac(): void
    {
        Yii::$app->set('authManager', null);

        $username = 'newuser';
        $email = 'newuser@example.com';
        $password = 'securepassword123';

        $this->authManagerMock
            ->expects($this->never())
            ->method('assign');

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
    public function testCreateUserWithInvalidData(): void
    {
        $this->expectException(UserCreationException::class);

        $this->userService->create('invaliduser', 'invalid-email', 'password123');
    }

    /**
     * @throws Exception
     */
    public function testGeneratePasswordResetToken(): void
    {
        $user = User::findOne(100);
        $this->assertNotNull($user);

        $result = $this->userService->generatePasswordResetToken($user);

        verify($result)->true();
        verify($user->password_reset_token)->notEmpty();
        verify(strpos($user->password_reset_token, '_'))->notFalse();
    }

    /**
     * @throws Exception
     */
    public function testGeneratePasswordResetTokenFailure(): void
    {
        /** @var User|MockObject $user */
        $user = $this->createMock(User::class);
        $user->id = 999;

        $user
            ->expects($this->once())
            ->method('save')
            ->with(false, ['password_reset_token'])
            ->willThrowException(new Exception('Save failed'));

        $result = $this->userService->generatePasswordResetToken($user);

        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     */
    public function testRemovePasswordResetToken(): void
    {
        $user = User::findOne(100);
        $this->assertNotNull($user);

        $this->userService->generatePasswordResetToken($user);
        verify($user->password_reset_token)->notEmpty();

        $result = $this->userService->removePasswordResetToken($user);

        verify($result)->true();
        verify($user->password_reset_token)->null();
    }

    public function testSoftDelete(): void
    {
        $user = User::findOne(100);
        $this->assertNotNull($user);

        verify($user->deleted_at)->null();

        $result = $this->userService->softDelete($user);

        verify($result)->true();
        verify($user->deleted_at)->notNull();
    }

    public function testSoftDeleteAlreadyDeleted(): void
    {
        $user = User::findOne(100);
        $this->assertNotNull($user);

        $this->userService->softDelete($user);

        $result = $this->userService->softDelete($user);

        verify($result)->false();
    }

    public function testRestoreSoftDelete(): void
    {
        $user = User::findOne(100);
        $this->assertNotNull($user);

        $this->userService->softDelete($user);
        verify($user->deleted_at)->notNull();

        $result = $this->userService->restoreSoftDelete($user);

        verify($result)->true();
        verify($user->deleted_at)->null();
    }

    public function testRestoreSoftDeleteNotDeleted(): void
    {
        $user = User::findOne(100);
        $this->assertNotNull($user);

        verify($user->deleted_at)->null();

        $result = $this->userService->restoreSoftDelete($user);

        verify($result)->false();
    }

    public function testHardDelete(): void
    {
        $user = User::findOne(100);
        $this->assertNotNull($user);

        $this->authManagerMock
            ->expects($this->once())
            ->method('revokeAll')
            ->with($this->equalTo($user->id))
            ->willReturn(true);

        $result = $this->userService->hardDelete($user);

        $this->assertTrue($result);

        $deletedUser = User::findOne(100);
        $this->assertNull($deletedUser);
    }

    /**
     * @throws InvalidConfigException
     */
    public function testHardDeleteWithoutRbac(): void
    {
        $user = User::findOne(100);
        $this->assertNotNull($user);

        Yii::$app->set('authManager', null);

        $this->authManagerMock
            ->expects($this->never())
            ->method('revokeAll');

        $result = $this->userService->hardDelete($user);

        $this->assertTrue($result);

        $deletedUser = User::findOne(100);
        $this->assertNull($deletedUser);
    }

    /**
     * @throws MockException
     */
    public function testHardDeleteFails(): void
    {
        /** @var User|MockObject $user */
        $user = $this->createMock(User::class);
        $user->id = 100;

        $user
            ->method('delete')
            ->willThrowException(new \Exception('Delete operation failed'));

        $this->authManagerMock
            ->expects($this->once())
            ->method('revokeAll')
            ->with($this->equalTo($user->id))
            ->willReturn(true);

        $result = $this->userService->hardDelete($user);

        $this->assertFalse($result);
    }

    /**
     * @throws MockException
     */
    public function testHardDeleteFailsWhenDeleteReturnsFalse(): void
    {
        /** @var User|MockObject $user */
        $user = $this->createMock(User::class);
        $user->id = 100;

        $user
            ->expects($this->once())
            ->method('delete')
            ->willReturn(0);

        $this->authManagerMock
            ->expects($this->once())
            ->method('revokeAll')
            ->with($this->equalTo($user->id))
            ->willReturn(true);

        $result = $this->userService->hardDelete($user);

        $this->assertFalse($result);
    }

    /**
     * @throws UserCreationException
     */
    public function testCreateUserCallsSeederOnSuccess(): void
    {
        /** @var UserDataSeederInterface|MockObject $seederMock */
        $seederMock = $this->createMock(UserDataSeederInterface::class);

        $seederMock
            ->expects($this->once())
            ->method('seed')
            ->with($this->isInt());

        $this->userService = new UserService($seederMock);

        $username = 'seededuser';
        $email = 'seededuser@example.com';
        $password = 'securepassword123';

        $user = $this->userService->create($username, $email, $password);

        verify($user)->notEmpty();
        verify($user->email)->equals($email);
    }

    public function testCreateUserRollsBackTransactionWhenSeederThrows(): void
    {
        /** @var UserDataSeederInterface|MockObject $seederMock */
        $seederMock = $this->createMock(UserDataSeederInterface::class);

        $seederMock
            ->expects($this->once())
            ->method('seed')
            ->willThrowException(new Exception('Seeder failed'));

        $this->userService = new UserService($seederMock);

        $username = 'seedfailuser';
        $email = 'seedfailuser@example.com';
        $password = 'securepassword123';

        try {
            $this->userService->create($username, $email, $password);
            $this->fail('Expected UserCreationException was not thrown.');
        } catch (UserCreationException $exception) {
            $user = User::findOne(['email' => $email]);
            $this->assertNull($user);
        }
    }
}
