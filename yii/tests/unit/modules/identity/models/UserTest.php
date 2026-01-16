<?php

namespace tests\unit\modules\identity\models;

use app\modules\identity\models\User;
use Codeception\Test\Unit;

class UserTest extends Unit
{
    protected function _before(): void
    {
        // Clean up test users
        User::deleteAll(['like', 'username', 'tokentest%']);
    }

    protected function _after(): void
    {
        User::deleteAll(['like', 'username', 'tokentest%']);
    }

    public function testFindIdentityByAccessTokenReturnsUserWithValidToken(): void
    {
        $user = $this->createTestUser('tokentest1');
        $token = 'test_token_valid_123';
        $hash = hash('sha256', $token);

        $user->access_token_hash = $hash;
        $user->access_token_expires_at = time() + 86400; // expires tomorrow
        $user->save(false);

        $found = User::findIdentityByAccessToken($token);

        self::assertNotNull($found);
        self::assertSame($user->id, $found->id);
    }

    public function testFindIdentityByAccessTokenReturnsNullForInvalidToken(): void
    {
        $user = $this->createTestUser('tokentest2');
        $token = 'correct_token';
        $hash = hash('sha256', $token);

        $user->access_token_hash = $hash;
        $user->access_token_expires_at = time() + 86400;
        $user->save(false);

        $found = User::findIdentityByAccessToken('wrong_token');

        self::assertNull($found);
    }

    public function testFindIdentityByAccessTokenReturnsNullForExpiredToken(): void
    {
        $user = $this->createTestUser('tokentest3');
        $token = 'expired_token_123';
        $hash = hash('sha256', $token);

        $user->access_token_hash = $hash;
        $user->access_token_expires_at = time() - 1; // expired
        $user->save(false);

        $found = User::findIdentityByAccessToken($token);

        self::assertNull($found);
    }

    public function testFindIdentityByAccessTokenReturnsUserWithNullExpiry(): void
    {
        $user = $this->createTestUser('tokentest4');
        $token = 'no_expiry_token';
        $hash = hash('sha256', $token);

        $user->access_token_hash = $hash;
        $user->access_token_expires_at = null; // no expiry
        $user->save(false);

        $found = User::findIdentityByAccessToken($token);

        self::assertNotNull($found);
        self::assertSame($user->id, $found->id);
    }

    public function testFindIdentityByAccessTokenReturnsNullForInactiveUser(): void
    {
        $user = $this->createTestUser('tokentest5');
        $token = 'inactive_user_token';
        $hash = hash('sha256', $token);

        $user->access_token_hash = $hash;
        $user->access_token_expires_at = time() + 86400;
        $user->status = User::STATUS_INACTIVE;
        $user->save(false);

        $found = User::findIdentityByAccessToken($token);

        self::assertNull($found);
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
}
