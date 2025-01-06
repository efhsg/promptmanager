<?php

namespace tests\unit\identity\commands;

use app\modules\identity\commands\UserController;
use app\modules\identity\models\User;
use app\modules\identity\services\UserService;
use Codeception\Test\Unit;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use tests\fixtures\UserFixture;
use Yii;
use yii\base\InvalidConfigException;
use yii\console\ExitCode;
use yii\di\NotInstantiableException;

class UserControllerTest extends Unit
{
    /** @var UserController */
    private UserController $controller;

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
        ];
    }

    public function testActionCreateSuccess()
    {
        $username = 'newuser';
        $email = 'newuser@example.com';
        $password = 'securepassword123';

        $exitCode = $this->controller->actionCreate($username, $email, $password);

        $this->assertEquals(ExitCode::OK, $exitCode);

        $user = User::findOne(['username' => $username]);
        $this->assertNotNull($user);
        $this->assertEquals($email, $user->email);
    }

    public function testActionCreateWithValidationErrors()
    {
        $username = '';
        $email = 'invalid-email';
        $password = 'short';

        $exitCode = $this->controller->actionCreate($username, $email, $password);

        $this->assertEquals(ExitCode::UNSPECIFIED_ERROR, $exitCode);
    }

    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function testActionCreateWithException(): void
    {
        /** @var UserService|MockObject $mockUserService */
        $mockUserService = $this->createMock(UserService::class);
        $mockUserService->method('create')->willThrowException(new Exception('Database error'));

        /** @var UserController $controller */
        $controller = new class ('user', Yii::$app, $mockUserService) extends UserController {
            public function stdout($string)
            {
            }

            public function stderr($string)
            {
            }
        };

        $exitCode = $controller->actionCreate('testuser', 'test@example.com', 'password123');
        $this->assertEquals(ExitCode::UNSPECIFIED_ERROR, $exitCode);
    }

    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function testActionDeleteSoftDeleteSuccess()
    {
        $username = 'admin';
        $user = User::findOne(['username' => $username]);
        $this->assertNotNull($user, 'The user must exist before the test.');

        $mockUserService = $this->createMock(UserService::class);
        $mockUserService->method('softDelete')->willReturnCallback(function ($user) {
            $user->deleted_at = time();
            return $user->save(false);
        });

        $controller = new class ('user', Yii::$app, $mockUserService) extends UserController {
            public function stdout($string)
            {
            }

            public function stderr($string)
            {
            }

            public function prompt($text, $options = []): string
            {
                return 'soft';
            }
        };

        $exitCode = $controller->actionDelete($username);
        $this->assertEquals(ExitCode::OK, $exitCode, 'Soft delete should return ExitCode::OK');

        $user->refresh();
        $this->assertNotNull($user->deleted_at, 'The user should be marked as soft deleted.');
    }

    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    public function testActionDeleteHardDeleteSuccess()
    {
        $username = 'admin';
        $user = User::findOne(['username' => $username]);
        $this->assertNotNull($user, 'The user must exist before the test.');

        $mockUserService = $this->createMock(UserService::class);
        $mockUserService->method('hardDelete')->willReturnCallback(function ($user) {
            return $user->delete() !== false;
        });

        $controller = new class ('user', Yii::$app, $mockUserService) extends UserController {
            public function stdout($string)
            {
            }

            public function stderr($string)
            {
            }

            public function prompt($text, $options = []): string
            {
                return 'hard';
            }
        };

        $exitCode = $controller->actionDelete($username);
        $this->assertEquals(ExitCode::OK, $exitCode, 'Hard delete should return ExitCode::OK');

        $deletedUser = User::findOne(['username' => $username]);
        $this->assertNull($deletedUser, 'The user should be fully removed after a hard delete.');
    }

    /**
     * @throws NotInstantiableException
     * @throws InvalidConfigException
     */
    protected function _before(): void
    {
        $userService = Yii::$container->get(UserService::class);

        $this->controller = new class ('user', Yii::$app, $userService) extends UserController {
            public function stdout($string)
            {
            }

            public function stderr($string)
            {
            }
        };
    }


}
