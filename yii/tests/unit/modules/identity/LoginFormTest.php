<?php

namespace tests\unit\modules\identity;

use app\modules\identity\models\LoginForm;
use app\modules\identity\models\User;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Yii;
use yii\base\Application as BaseApplication;
use yii\web\User as WebUser;

class LoginFormTest extends Unit
{
    private ?BaseApplication $originalApp = null;
    private array|object|null $originalUserComponent = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalApp = Yii::$app;

        if (Yii::$app !== null) {
            $components = Yii::$app->getComponents(false);
            if (array_key_exists('user', $components)) {
                $this->originalUserComponent = $components['user'];
            } else {
                $definitions = Yii::$app->getComponents();
                $this->originalUserComponent = $definitions['user'] ?? null;
            }
        }
    }

    protected function tearDown(): void
    {
        Yii::$app = $this->originalApp;

        if (Yii::$app !== null) {
            Yii::$app->set('user', $this->originalUserComponent);
        }

        parent::tearDown();
    }

    public function testValidationFailsWhenUsernameAndPasswordMissing(): void
    {
        $form = new LoginForm();

        $isValid = $form->validate();

        self::assertSame(false, $isValid);
        self::assertArrayHasKey('username', $form->getErrors());
        self::assertArrayHasKey('password', $form->getErrors());
    }

    public function testLoginReturnsFalseWhenUserComponentRejectsLogin(): void
    {
        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var MockObject&WebUser $webUser */
        $webUser = $this->getMockBuilder(WebUser::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['login'])
            ->getMock();

        $webUser->expects($this->once())
            ->method('login')
            ->with($user, 3600 * 24 * 30)
            ->willReturn(false);

        Yii::$app->set('user', $webUser);

        /** @var MockObject&LoginForm $form */
        $form = $this->getMockBuilder(LoginForm::class)
            ->onlyMethods(['validate', 'getUser'])
            ->getMock();

        $form->rememberMe = true;

        $form->expects($this->once())->method('validate')->willReturn(true);
        $form->expects($this->once())->method('getUser')->willReturn($user);

        $result = $form->login();

        self::assertSame(false, $result);
    }

    public function testValidatePasswordAddsErrorWhenUserNotFound(): void
    {
        /** @var MockObject&LoginForm $form */
        $form = $this->getMockBuilder(LoginForm::class)
            ->onlyMethods(['getUser'])
            ->getMock();

        $form->username = 'missing';
        $form->password = 'secret';

        $form->expects($this->once())->method('getUser')->willReturn(null);

        $isValid = $form->validate();

        self::assertSame(false, $isValid);
        self::assertArrayHasKey('password', $form->getErrors());
    }

    public function testValidatePasswordAddsErrorWhenPasswordIsInvalid(): void
    {
        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['validatePassword'])
            ->getMock();

        $user->expects($this->once())
            ->method('validatePassword')
            ->with('wrong')
            ->willReturn(false);

        /** @var MockObject&LoginForm $form */
        $form = $this->getMockBuilder(LoginForm::class)
            ->onlyMethods(['getUser'])
            ->getMock();

        $form->username = 'demo';
        $form->password = 'wrong';

        $form->expects($this->once())->method('getUser')->willReturn($user);

        $isValid = $form->validate();

        self::assertSame(false, $isValid);
        self::assertArrayHasKey('password', $form->getErrors());
    }

    public function testValidatePasswordPassesWhenCredentialsAreValid(): void
    {
        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['validatePassword'])
            ->getMock();

        $user->expects($this->once())
            ->method('validatePassword')
            ->with('secret')
            ->willReturn(true);

        /** @var MockObject&LoginForm $form */
        $form = $this->getMockBuilder(LoginForm::class)
            ->onlyMethods(['getUser'])
            ->getMock();

        $form->username = 'demo';
        $form->password = 'secret';

        $form->expects($this->once())->method('getUser')->willReturn($user);

        $isValid = $form->validate();

        self::assertSame(true, $isValid);
        self::assertSame([], $form->getErrors());
    }

    public function testLoginUsesThirtyDayDurationWhenRememberMeIsEnabled(): void
    {
        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var MockObject&WebUser $webUser */
        $webUser = $this->getMockBuilder(WebUser::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['login'])
            ->getMock();

        $webUser->expects($this->once())
            ->method('login')
            ->with($user, 3600 * 24 * 30)
            ->willReturn(true);

        Yii::$app->set('user', $webUser);

        /** @var MockObject&LoginForm $form */
        $form = $this->getMockBuilder(LoginForm::class)
            ->onlyMethods(['validate', 'getUser'])
            ->getMock();

        $form->rememberMe = true;

        $form->expects($this->once())->method('validate')->willReturn(true);
        $form->expects($this->once())->method('getUser')->willReturn($user);

        $result = $form->login();

        self::assertSame(true, $result);
    }

    public function testLoginUsesZeroDurationWhenRememberMeIsDisabled(): void
    {
        /** @var MockObject&User $user */
        $user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var MockObject&WebUser $webUser */
        $webUser = $this->getMockBuilder(WebUser::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['login'])
            ->getMock();

        $webUser->expects($this->once())
            ->method('login')
            ->with($user, 0)
            ->willReturn(true);

        Yii::$app->set('user', $webUser);

        /** @var MockObject&LoginForm $form */
        $form = $this->getMockBuilder(LoginForm::class)
            ->onlyMethods(['validate', 'getUser'])
            ->getMock();

        $form->rememberMe = false;

        $form->expects($this->once())->method('validate')->willReturn(true);
        $form->expects($this->once())->method('getUser')->willReturn($user);

        $result = $form->login();

        self::assertSame(true, $result);
    }

    public function testLoginReturnsFalseWhenValidationFails(): void
    {
        /** @var MockObject&WebUser $webUser */
        $webUser = $this->getMockBuilder(WebUser::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['login'])
            ->getMock();

        $webUser->expects($this->never())->method('login');

        Yii::$app->set('user', $webUser);

        /** @var MockObject&LoginForm $form */
        $form = $this->getMockBuilder(LoginForm::class)
            ->onlyMethods(['validate', 'getUser'])
            ->getMock();

        $form->expects($this->once())->method('validate')->willReturn(false);
        $form->expects($this->never())->method('getUser');

        $result = $form->login();

        self::assertSame(false, $result);
    }
}
