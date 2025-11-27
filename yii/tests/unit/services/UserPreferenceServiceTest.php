<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace tests\unit\services;

use app\models\UserPreference;
use app\services\UserPreferenceService;
use Codeception\Test\Unit;
use tests\fixtures\UserFixture;
use tests\fixtures\UserPreferenceFixture;
use UnitTester;
use yii\db\Exception;

/**
 * Tests UserPreferenceService CRUD operations against real DB fixtures.
 * Fixtures: user1 (id=100) has theme=dark, language=en; user2 (id=1) has theme=light.
 */
class UserPreferenceServiceTest extends Unit
{
    protected UnitTester $tester;

    private UserPreferenceService $service;

    public function _fixtures(): array
    {
        return [
            'users' => UserFixture::class,
            'userPreferences' => UserPreferenceFixture::class,
        ];
    }

    protected function _before(): void
    {
        parent::_before();
        $this->service = new UserPreferenceService();
    }

    private function findPreference(int $userId, string $key): ?UserPreference
    {
        return UserPreference::findOne([
            'user_id' => $userId,
            'pref_key' => $key,
        ]);
    }

    public function testGetValueReturnsExistingPreference(): void
    {
        $user = $this->tester->grabFixture('users', 'user1');
        $result = $this->service->getValue($user->id, 'theme');

        $this->assertSame('dark', $result);
    }

    public function testGetValueReturnsNullForNonExistingKey(): void
    {
        $user = $this->tester->grabFixture('users', 'user1');
        $result = $this->service->getValue($user->id, 'non_existing_key');

        $this->assertNull($result);
    }

    public function testGetValueReturnsNullForNonExistingUser(): void
    {
        $result = $this->service->getValue(999, 'theme');

        $this->assertNull($result);
    }

    public function testGetValueReturnsDefaultWhenPreferenceNotFound(): void
    {
        $user = $this->tester->grabFixture('users', 'user1');
        $result = $this->service->getValue($user->id, 'non_existing_key', 'default_value');

        $this->assertSame('default_value', $result);
    }

    public function testSetValueCreatesNewPreference(): void
    {
        $user = $this->tester->grabFixture('users', 'user1');
        $this->service->setValue($user->id, 'new_pref', 'new_value');

        $pref = $this->findPreference($user->id, 'new_pref');

        $this->assertNotNull($pref);
        $this->assertSame('new_value', $pref->pref_value);

        $pref->delete();
    }

    public function testSetValueUpdatesExistingPreference(): void
    {
        $user = $this->tester->grabFixture('users', 'user1');
        $this->service->setValue($user->id, 'theme', 'light');

        $pref = $this->findPreference($user->id, 'theme');

        $this->assertNotNull($pref);
        $this->assertSame('light', $pref->pref_value);
    }

    public function testSetValueAcceptsNullValue(): void
    {
        $user = $this->tester->grabFixture('users', 'user1');
        $this->service->setValue($user->id, 'theme', null);

        $pref = $this->findPreference($user->id, 'theme');

        $this->assertNotNull($pref);
        $this->assertNull($pref->pref_value);
    }

    public function testRemoveValueDeletesExistingPreference(): void
    {
        $user = $this->tester->grabFixture('users', 'user1');
        $this->service->removeValue($user->id, 'theme');

        $pref = $this->findPreference($user->id, 'theme');

        $this->assertNull($pref);
    }

    public function testRemoveValueDoesNotFailForNonExistingPreference(): void
    {
        $user = $this->tester->grabFixture('users', 'user1');
        $countBefore = UserPreference::find()->where(['user_id' => $user->id])->count();

        $this->service->removeValue($user->id, 'non_existing_key');

        $countAfter = UserPreference::find()->where(['user_id' => $user->id])->count();
        $this->assertSame($countBefore, $countAfter);
    }

    public function testRemoveValueDoesNotFailForNonExistingUser(): void
    {
        $this->expectNotToPerformAssertions();

        $this->service->removeValue(999, 'theme');
    }

    public function testSetValueAcceptsEmptyString(): void
    {
        $user = $this->tester->grabFixture('users', 'user1');
        $this->service->setValue($user->id, 'theme', '');

        $pref = $this->findPreference($user->id, 'theme');

        $this->assertNotNull($pref);
        $this->assertSame('', $pref->pref_value);
    }

    public function testGetValueReturnsCorrectValueForSpecificUser(): void
    {
        $user = $this->tester->grabFixture('users', 'user2');
        $result = $this->service->getValue($user->id, 'theme');

        $this->assertSame('light', $result);
    }

    public function testRemoveValueOnlyAffectsSpecificUser(): void
    {
        $user1 = $this->tester->grabFixture('users', 'user1');
        $user2 = $this->tester->grabFixture('users', 'user2');
        $this->service->removeValue($user1->id, 'theme');

        $user2Pref = $this->findPreference($user2->id, 'theme');

        $this->assertNotNull($user2Pref);
        $this->assertSame('light', $user2Pref->pref_value);
    }

    public function testSetValueCreatesNewPreferenceKeyForExistingUser(): void
    {
        $user = $this->tester->grabFixture('users', 'user2');
        $this->service->setValue($user->id, 'new_setting', 'value');

        $pref = $this->findPreference($user->id, 'new_setting');

        $this->assertNotNull($pref);
        $this->assertSame('value', $pref->pref_value);

        $pref->delete();
    }

    public function testSetValueThrowsExceptionForInvalidUserId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to save preference');

        $this->service->setValue(999, 'theme', 'dark');
    }

    public function testSetValueThrowsExceptionForTooLongKey(): void
    {
        $user = $this->tester->grabFixture('users', 'user1');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to save preference');

        $longKey = str_repeat('a', 256);
        $this->service->setValue($user->id, $longKey, 'value');
    }

    public function testSetValueThrowsExceptionForTooLongValue(): void
    {
        $user = $this->tester->grabFixture('users', 'user1');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to save preference');

        $longValue = str_repeat('a', 256);
        $this->service->setValue($user->id, 'new_pref', $longValue);
    }
}
