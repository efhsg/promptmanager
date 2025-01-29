<?php

namespace tests\unit\identity\services;

use app\modules\identity\exceptions\UserCreationException;
use app\modules\identity\models\User;
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
    /**
     * @var UserService
     */
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
     * Sets up the test environment before each test.
     *
     * @throws NotInstantiableException
     * @throws InvalidConfigException|MockException
     */
    protected function _before(): void
    {
        parent::_before();

        $this->authManagerMock = $this->createMock(ManagerInterface::class);
        Yii::$app->set('authManager', $this->authManagerMock);

        $this->userService = Yii::$container->get(UserService::class);
    }

    /**
     * Cleans up after each test.
     * @throws InvalidConfigException
     */
    protected function _after(): void
    {
        Yii::$app->set('authManager', null);
        parent::_after();
    }

    /**
     * Tests the creation of a user and verifies RBAC role assignment.
     *
     * @throws UserCreationException
     * @throws MockException
     */
    public function testCreateUser()
    {
        $username = 'newuser';
        $email = 'newuser@example.com';
        $password = 'securepassword123';

        $roleMock = $this->createMock(Role::class);
        $roleMock->name = 'user';

        $this->authManagerMock->expects($this->once())
            ->method('getRole')
            ->with('user')
            ->willReturn($roleMock);

        $this->authManagerMock->expects($this->once())
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
     * Tests user creation when RBAC is not available.
     *
     * @throws UserCreationException
     * @throws MockException|InvalidConfigException
     */
    public function testCreateUserWithoutRbac()
    {
        Yii::$app->set('authManager', null);

        $username = 'newuser';
        $email = 'newuser@example.com';
        $password = 'securepassword123';

        $this->authManagerMock->expects($this->never())
            ->method('assign');

        $user = $this->userService->create($username, $email, $password);

        verify($user)->notEmpty();
        verify($user->username)->equals($username);
        verify($user->email)->equals($email);
        verify(Yii::$app->security->validatePassword($password, $user->password_hash))->true();
        verify($user->status)->equals(User::STATUS_ACTIVE);
    }

    /**
     * Tests that an exception is thrown when user creation fails.
     *
     * @throws UserCreationException
     */
    public function testCreateUserWithException()
    {
        $this->expectException(UserCreationException::class);

        $this->userService->create('invaliduser', 'invalid-email', 'password123');
    }

    /**
     * Tests generating a password reset token.
     *
     * @throws Exception
     */
    public function testGeneratePasswordResetToken()
    {
        $user = User::findOne(100);
        $this->assertNotNull($user);

        $result = $this->userService->generatePasswordResetToken($user);

        verify($result)->true();
        verify($user->password_reset_token)->notEmpty();
        verify(strpos($user->password_reset_token, '_'))->notFalse();
    }

    /**
     * Tests removing a password reset token.
     *
     * @throws Exception
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

    /**
     * Tests soft deleting a user.
     */
    public function testSoftDelete()
    {
        $user = User::findOne(100);
        $this->assertNotNull($user);
        verify($user->deleted_at)->null();

        $result = $this->userService->softDelete($user);

        verify($result)->true();
        verify($user->deleted_at)->notNull();
    }

    /**
     * Tests soft deleting an already deleted user.
     */
    public function testSoftDeleteAlreadyDeleted()
    {
        $user = User::findOne(100);
        $this->userService->softDelete($user);

        $result = $this->userService->softDelete($user);

        verify($result)->false();
    }

    /**
     * Tests restoring a soft-deleted user.
     */
    public function testRestoreSoftDelete()
    {
        $user = User::findOne(100);
        $this->userService->softDelete($user);

        verify($user->deleted_at)->notNull();

        $result = $this->userService->restoreSoftDelete($user);

        verify($result)->true();
        verify($user->deleted_at)->null();
    }

    /**
     * Tests restoring a user that is not deleted.
     */
    public function testRestoreSoftDeleteNotDeleted()
    {
        $user = User::findOne(100);
        verify($user->deleted_at)->null();

        $result = $this->userService->restoreSoftDelete($user);

        verify($result)->false();
    }

    /**
     * Tests hard deleting a user and verifies RBAC role revocation.
     */
    public function testHardDelete()
    {
        $user = User::findOne(100);
        $this->assertNotNull($user);

        $this->authManagerMock->expects($this->once())
            ->method('revokeAll')
            ->with($this->equalTo($user->id))
            ->willReturn(true);

        $result = $this->userService->hardDelete($user);

        $this->assertTrue($result);
        $deletedUser = User::findOne(100);
        $this->assertNull($deletedUser);
    }

    /**
     * Tests hard deleting a user when RBAC is not available.
     * @throws InvalidConfigException
     */
    public function testHardDeleteWithoutRbac()
    {
        $user = User::findOne(100);
        $this->assertNotNull($user);

        Yii::$app->set('authManager', null);

        $this->authManagerMock->expects($this->never())
            ->method('revokeAll');

        $result = $this->userService->hardDelete($user);

        $this->assertTrue($result);
        $deletedUser = User::findOne(100);
        $this->assertNull($deletedUser);
    }

    /**
     * Tests that hard deletion fails gracefully when an exception occurs.
     *
     * @throws MockException
     */
    public function testHardDeleteFails()
    {
        $user = $this->createMock(User::class);
        $user->id = 100;
        $user->method('delete')->willThrowException(new \Exception('Delete operation failed'));

        $this->authManagerMock->expects($this->once())
            ->method('revokeAll')
            ->with($this->equalTo($user->id))
            ->willReturn(true);

        $result = $this->userService->hardDelete($user);

        $this->assertFalse($result);
    }

    /**
     * Tests that an exception is thrown when a user with invalid data is created.
     *
     * @throws UserCreationException
     */
    public function testCreateUserWithInvalidData()
    {
        $this->expectException(UserCreationException::class);

        $this->userService->create('invaliduser', 'invalid-email', 'password123');
    }
}
