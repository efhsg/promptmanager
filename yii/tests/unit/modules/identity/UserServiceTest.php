<?php

namespace tests\unit\modules\identity;

use app\modules\identity\exceptions\UserCreationException;
use app\modules\identity\models\User;
use app\modules\identity\services\UserDataSeederInterface;
use app\modules\identity\services\UserService;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Yii;
use yii\base\Application as BaseApplication;
use yii\console\Application;
use yii\db\Connection;
use yii\rbac\ManagerInterface;
use yii\rbac\Role;
use RuntimeException;

class UserServiceTest extends Unit
{
    private ?BaseApplication $previousApp = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousApp = Yii::$app;
    }

    protected function tearDown(): void
    {
        Yii::$app = $this->previousApp;
        parent::tearDown();
    }

    public function testCreatePersistsUserAssignsRoleAndSeedsData(): void
    {
        $authManager = $this->createAuthManagerMockWithRoleExpectations();

        /** @var MockObject&UserDataSeederInterface $seeder */
        $seeder = $this->getMockBuilder(UserDataSeederInterface::class)->getMock();
        $seeder->expects($this->once())->method('seed')->with(self::greaterThan(0));

        $this->bootstrapConsoleApp($authManager);

        $service = new UserService($seeder);

        $user = $service->create('demo', 'demo@example.com', 'secret');

        self::assertInstanceOf(User::class, $user);
        self::assertSame('demo', $user->username);
        self::assertSame('demo@example.com', $user->email);
        self::assertSame(User::STATUS_ACTIVE, $user->status);
        self::assertGreaterThan(0, $user->id);
    }

    public function testCreateThrowsWhenValidationFails(): void
    {
        $authManager = $this->createAuthManagerMockWithoutAssignments();

        /** @var MockObject&UserDataSeederInterface $seeder */
        $seeder = $this->getMockBuilder(UserDataSeederInterface::class)->getMock();
        $seeder->expects($this->never())->method('seed');

        $this->bootstrapConsoleApp($authManager);

        $service = new UserService($seeder);

        $this->expectException(UserCreationException::class);

        try {
            $service->create('demo', 'not-an-email', 'secret');
        } finally {
            $count = (int) Yii::$app->db->createCommand('SELECT COUNT(*) FROM user')->queryScalar();
            self::assertSame(0, $count);
        }
    }

    public function testGeneratePasswordResetTokenPersistsToken(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['save'])
            ->getMock();
        $user->id = 10;
        $user->password_reset_token = null;

        $user->expects($this->once())
            ->method('save')
            ->with(false, ['password_reset_token'])
            ->willReturn(true);

        $service = new UserService();

        $result = $service->generatePasswordResetToken($user);

        self::assertSame(true, $result);
        self::assertNotNull($user->password_reset_token);
        self::assertSame(1, substr_count($user->password_reset_token, '_'));
    }

    public function testRemovePasswordResetTokenClearsToken(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['save'])
            ->getMock();
        $user->id = 20;
        $user->password_reset_token = 'token_123';

        $user->expects($this->once())
            ->method('save')
            ->with(false, ['password_reset_token'])
            ->willReturn(true);

        $service = new UserService();

        $result = $service->removePasswordResetToken($user);

        self::assertSame(true, $result);
        self::assertNull($user->password_reset_token);
    }

    public function testSoftDeleteSetsDeletedTimestampWhenActive(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['save'])
            ->getMock();
        $user->id = 30;
        $user->deleted_at = null;

        $user->expects($this->once())
            ->method('save')
            ->with(false, ['deleted_at'])
            ->willReturn(true);

        $service = new UserService();

        $result = $service->softDelete($user);

        self::assertSame(true, $result);
        self::assertIsString($user->deleted_at);
        self::assertNotEmpty($user->deleted_at);
    }

    public function testSoftDeleteReturnsFalseWhenAlreadyDeleted(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        $user = new User();
        $user->deleted_at = date('Y-m-d H:i:s');

        $service = new UserService();

        $result = $service->softDelete($user);

        self::assertSame(false, $result);
    }

    public function testRestoreSoftDeleteReturnsFalseWhenNotDeleted(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        $user = new User();
        $user->deleted_at = null;

        $service = new UserService();

        $result = $service->restoreSoftDelete($user);

        self::assertSame(false, $result);
    }

    public function testRestoreSoftDeleteClearsDeletedTimestamp(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['save'])
            ->getMock();
        $user->id = 40;
        $user->deleted_at = date('Y-m-d H:i:s');

        $user->expects($this->once())
            ->method('save')
            ->with(false, ['deleted_at'])
            ->willReturn(true);

        $service = new UserService();

        $result = $service->restoreSoftDelete($user);

        self::assertSame(true, $result);
        self::assertNull($user->deleted_at);
    }

    public function testHardDeleteRevokesRolesAndDeletesUser(): void
    {
        $authManager = $this->createAuthManagerMockWithoutAssignments();
        $authManager->expects($this->once())->method('revokeAll')->with(55);

        $this->bootstrapConsoleApp($authManager);

        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['delete'])
            ->getMock();
        $user->id = 55;

        $user->expects($this->once())->method('delete')->willReturn(true);

        $service = new UserService();

        $result = $service->hardDelete($user);

        self::assertSame(true, $result);
    }

    public function testHardDeleteReturnsFalseWhenDeleteFails(): void
    {
        $authManager = $this->createAuthManagerMockWithoutAssignments();
        $authManager->expects($this->once())->method('revokeAll')->with(65);

        $this->bootstrapConsoleApp($authManager);

        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['delete'])
            ->getMock();
        $user->id = 65;

        $user->expects($this->once())->method('delete')->willReturn(false);

        $service = new UserService();

        $result = $service->hardDelete($user);

        self::assertSame(false, $result);
    }

    public function testGenerateAccessTokenReturnsTokenAndStoresHash(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['save'])
            ->getMock();
        $user->id = 70;

        $user->expects($this->once())
            ->method('save')
            ->with(false, ['access_token_hash', 'access_token_expires_at'])
            ->willReturn(true);

        $service = new UserService();

        $token = $service->generateAccessToken($user);

        self::assertSame(64, strlen($token));
        self::assertNotNull($user->access_token_hash);
        self::assertSame(64, strlen($user->access_token_hash));
        self::assertSame(hash('sha256', $token), $user->access_token_hash);
        self::assertNotNull($user->access_token_expires_at);
        self::assertGreaterThan(date('Y-m-d H:i:s'), $user->access_token_expires_at);
    }

    public function testGenerateAccessTokenUsesCustomExpiry(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['save'])
            ->getMock();
        $user->id = 71;

        $user->expects($this->once())->method('save')->willReturn(true);

        $service = new UserService();

        $service->generateAccessToken($user, 30);

        $expectedExpiry = time() + (30 * 86400);
        self::assertEqualsWithDelta($expectedExpiry, strtotime($user->access_token_expires_at), 5);
    }

    public function testGenerateAccessTokenThrowsOnSaveFailure(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['save'])
            ->getMock();
        $user->id = 72;

        $user->expects($this->once())->method('save')->willReturn(false);

        $service = new UserService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to save access token');

        $service->generateAccessToken($user);
    }

    public function testRotateAccessTokenGeneratesNewToken(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['save'])
            ->getMock();
        $user->id = 73;
        $user->access_token_hash = 'old_hash';

        $user->expects($this->once())->method('save')->willReturn(true);

        $service = new UserService();

        $newToken = $service->rotateAccessToken($user);

        self::assertSame(64, strlen($newToken));
        self::assertNotSame('old_hash', $user->access_token_hash);
    }

    public function testRevokeAccessTokenClearsTokenFields(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['save'])
            ->getMock();
        $user->id = 74;
        $user->access_token_hash = 'some_hash';
        $user->access_token_expires_at = date('Y-m-d H:i:s', time() + 86400);

        $user->expects($this->once())
            ->method('save')
            ->with(false, ['access_token_hash', 'access_token_expires_at'])
            ->willReturn(true);

        $service = new UserService();

        $service->revokeAccessToken($user);

        self::assertNull($user->access_token_hash);
        self::assertNull($user->access_token_expires_at);
    }

    public function testRevokeAccessTokenThrowsOnSaveFailure(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['save'])
            ->getMock();
        $user->id = 75;

        $user->expects($this->once())->method('save')->willReturn(false);

        $service = new UserService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to revoke access token');

        $service->revokeAccessToken($user);
    }

    public function testIsAccessTokenExpiredReturnsFalseWhenNull(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        $user = new User();
        $user->access_token_expires_at = null;

        $service = new UserService();

        self::assertFalse($service->isAccessTokenExpired($user));
    }

    public function testIsAccessTokenExpiredReturnsFalseWhenNotExpired(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        $user = new User();
        $user->access_token_expires_at = date('Y-m-d H:i:s', time() + 86400);

        $service = new UserService();

        self::assertFalse($service->isAccessTokenExpired($user));
    }

    public function testIsAccessTokenExpiredReturnsTrueWhenExpired(): void
    {
        $this->bootstrapConsoleApp($this->createAuthManagerMockWithoutAssignments());

        $user = new User();
        $user->access_token_expires_at = date('Y-m-d H:i:s', time() - 1);

        $service = new UserService();

        self::assertTrue($service->isAccessTokenExpired($user));
    }

    private function bootstrapConsoleApp(ManagerInterface $authManager): void
    {
        $config = [
            'id' => 'test-app',
            'basePath' => dirname(__DIR__, 4),
            'components' => [
                'db' => $this->createInMemoryConnection(),
                'security' => ['class' => 'yii\base\Security'],
                'authManager' => $authManager,
                'log' => [
                    'targets' => [],
                ],
            ],
        ];

        Yii::$app = new Application($config);
        $this->createUserTable(Yii::$app->db);
    }

    private function createInMemoryConnection(): Connection
    {
        return new Connection([
            'dsn' => 'sqlite::memory:',
        ]);
    }

    private function createUserTable(Connection $connection): void
    {
        $connection->createCommand(
            <<<'SQL'
                CREATE TABLE user (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL,
                    email TEXT NOT NULL,
                    password_hash TEXT NOT NULL,
                    auth_key TEXT,
                    password_reset_token TEXT,
                    access_token TEXT,
                    access_token_hash TEXT,
                    access_token_expires_at TEXT,
                    status INTEGER,
                    created_at TEXT,
                    updated_at TEXT,
                    deleted_at TEXT
                )
                SQL
        )->execute();
    }

    private function createAuthManagerMockWithRoleExpectations(): ManagerInterface
    {
        /** @var MockObject&ManagerInterface $authManager */
        $authManager = $this->getMockBuilder(ManagerInterface::class)->getMock();

        /** @var MockObject&Role $role */
        $role = $this->getMockBuilder(Role::class)
            ->disableOriginalConstructor()
            ->getMock();

        $authManager->expects($this->once())
            ->method('getRole')
            ->with('user')
            ->willReturn($role);

        $authManager->expects($this->once())
            ->method('assign')
            ->with($role, self::greaterThan(0));

        return $authManager;
    }

    private function createAuthManagerMockWithoutAssignments(): ManagerInterface
    {
        /** @var MockObject&ManagerInterface $authManager */
        $authManager = $this->getMockBuilder(ManagerInterface::class)->getMock();
        $authManager->method('getRole')->willReturn(null);

        return $authManager;
    }
}
