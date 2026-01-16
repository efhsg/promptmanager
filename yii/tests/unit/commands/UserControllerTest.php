<?php

namespace tests\unit\commands;

use app\commands\UserController;
use app\modules\identity\models\User;
use app\modules\identity\services\UserService;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Yii;
use yii\console\ExitCode;

class UserControllerTest extends Unit
{
    protected function _before(): void
    {
        User::deleteAll(['like', 'username', 'cmdtest%']);
    }

    protected function _after(): void
    {
        User::deleteAll(['like', 'username', 'cmdtest%']);
    }

    public function testGenerateTokenReturnsTokenForValidUser(): void
    {
        $user = $this->createTestUser('cmdtest1');

        $controller = $this->createController();
        $exitCode = $controller->actionGenerateToken($user->id);

        self::assertSame(ExitCode::OK, $exitCode);

        // Verify token was stored
        $user->refresh();
        self::assertNotNull($user->access_token_hash);
        self::assertNotNull($user->access_token_expires_at);
    }

    public function testGenerateTokenReturnsErrorForNonexistentUser(): void
    {
        $controller = $this->createController();
        $exitCode = $controller->actionGenerateToken(999999);

        self::assertSame(ExitCode::DATAERR, $exitCode);
    }

    public function testGenerateTokenUsesCustomExpiry(): void
    {
        $user = $this->createTestUser('cmdtest2');

        $controller = $this->createController();
        $controller->actionGenerateToken($user->id, 30);

        $user->refresh();
        $expectedExpiry = time() + (30 * 86400);
        self::assertEqualsWithDelta($expectedExpiry, $user->access_token_expires_at, 5);
    }

    public function testRotateTokenReplacesExistingToken(): void
    {
        $user = $this->createTestUser('cmdtest3');

        // Generate initial token
        $userService = new UserService();
        $userService->generateAccessToken($user);
        $oldHash = $user->access_token_hash;

        $controller = $this->createController();
        $exitCode = $controller->actionRotateToken($user->id);

        self::assertSame(ExitCode::OK, $exitCode);

        $user->refresh();
        self::assertNotSame($oldHash, $user->access_token_hash);
    }

    public function testRotateTokenReturnsErrorForNonexistentUser(): void
    {
        $controller = $this->createController();
        $exitCode = $controller->actionRotateToken(999999);

        self::assertSame(ExitCode::DATAERR, $exitCode);
    }

    public function testRevokeTokenClearsToken(): void
    {
        $user = $this->createTestUser('cmdtest4');

        // Generate token first
        $userService = new UserService();
        $userService->generateAccessToken($user);
        self::assertNotNull($user->access_token_hash);

        $controller = $this->createController();
        $exitCode = $controller->actionRevokeToken($user->id);

        self::assertSame(ExitCode::OK, $exitCode);

        $user->refresh();
        self::assertNull($user->access_token_hash);
        self::assertNull($user->access_token_expires_at);
    }

    public function testRevokeTokenReturnsErrorForNonexistentUser(): void
    {
        $controller = $this->createController();
        $exitCode = $controller->actionRevokeToken(999999);

        self::assertSame(ExitCode::DATAERR, $exitCode);
    }

    public function testRevokeTokenReturnsErrorOnFailure(): void
    {
        $user = $this->createTestUser('cmdtest5');

        /** @var MockObject&UserService $mockService */
        $mockService = $this->createMock(UserService::class);
        $mockService->method('revokeAccessToken')
            ->willThrowException(new RuntimeException('DB error'));

        $controller = $this->createController($mockService);
        $exitCode = $controller->actionRevokeToken($user->id);

        self::assertSame(ExitCode::UNSPECIFIED_ERROR, $exitCode);
    }

    private function createTestUser(string $username): User
    {
        $user = new User();
        $user->username = $username;
        $user->email = $username . '@example.com';
        $user->setPassword('secret123');
        $user->generateAuthKey();
        $user->status = User::STATUS_ACTIVE;
        $user->save(false);

        return $user;
    }

    private function createController(?UserService $userService = null): UserController
    {
        $service = $userService ?? new UserService();

        return new class ('user', Yii::$app, $service) extends UserController {
            public function stdout($string): void {}

            public function stderr($string): void {}
        };
    }
}
