<?php /** @noinspection PhpPossiblePolymorphicInvocationInspection */

namespace tests\unit\identity\models;

use app\modules\identity\exceptions\UserCreationException;
use app\modules\identity\models\User;
use app\modules\identity\services\UserService;
use Codeception\Test\Unit;
use tests\fixtures\UserFixture;
use Throwable;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\db\StaleObjectException;
use yii\di\NotInstantiableException;

class UserTest extends Unit
{

    private UserService $userService;

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
        ];
    }

    public function testFindUserById()
    {
        verify($user = User::findIdentity(100))->notEmpty();
        verify($user->username)->equals('admin');

        verify(User::findIdentity(999))->empty();
    }

    public function testFindUserByAccessToken()
    {
        verify($user = User::findIdentityByAccessToken('100_access_token'))->notEmpty();
        verify($user->username)->equals('admin');

        verify(User::findIdentityByAccessToken('non-existing'))->empty();
    }

    public function testFindUserByUsername()
    {
        verify($user = User::findByUsername('admin'))->notEmpty();
        verify($user->email)->equals('admin@example.com');

        verify(User::findByUsername('not-admin'))->empty();
    }

    /**
     * @throws Exception
     * @throws Throwable
     * @throws StaleObjectException
     * @throws UserCreationException
     */
    public function testFindByPasswordResetToken()
    {

        $user = $this->userService->create('testuser', 'testuser@example.com', 'password123');

        $this->userService->generatePasswordResetToken($user);
        $validToken = $user->password_reset_token;

        // 1. Test with a valid token and an existing user
        verify($foundUser = User::findByPasswordResetToken($validToken))->notEmpty();
        verify($foundUser->username)->equals('testuser');

        // 2. Test with a valid token and a non-existing user
        $user->delete();
        verify(User::findByPasswordResetToken($validToken))->empty();

        // 3. Test with a valid token for an existing user, but the token is expired
        $expiredToken = 'ExpiredToken_' . (time() - User::TOKEN_EXPIRATION_SECONDS - 1);
        $user = $this->userService->create('expireduser', 'expireduser@example.com', 'password123');
        $user->password_reset_token = $expiredToken;
        $user->save(false);

        verify(User::findByPasswordResetToken($expiredToken))->empty();

        // 4. Test with an invalid token format
        verify(User::findByPasswordResetToken('InvalidTokenFormat'))->empty();
    }

    /**
     * @depends testFindUserByUsername
     */
    public function testValidateUser()
    {
        $user = User::find()->active()->byUsername('admin')->one();
        verify($user->validateAuthKey('test100key'))->notEmpty();
        verify($user->validateAuthKey('test102key'))->empty();

        verify($user->validatePassword('admin'))->notEmpty();
        verify($user->validatePassword('123456'))->empty();
    }

    /**
     * @throws \yii\base\Exception
     */
    public function testSetPassword()
    {
        $user = new User();
        $password = 'new_secure_password';
        $user->setPassword($password);

        verify($user->password_hash)->notEmpty();
        verify(Yii::$app->security->validatePassword($password, $user->password_hash))->true();
        verify(Yii::$app->security->validatePassword('wrong_password', $user->password_hash))->false();
    }

    /**
     * @throws \yii\base\Exception
     */
    public function testGenerateAuthKey()
    {
        $user = new User();
        $user->generateAuthKey();

        verify($user->auth_key)->notEmpty();
        verify(strlen($user->auth_key))->equals(32);
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
