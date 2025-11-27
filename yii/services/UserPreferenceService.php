<?php

namespace app\services;

use app\models\UserPreference;
use Throwable;
use yii\db\Exception;
use yii\db\StaleObjectException;

class UserPreferenceService
{
    /**
     * Retrieve a preference value for a given user and key.
     */
    public function getValue(int $userId, string $key, ?string $default = null): ?string
    {
        $pref = UserPreference::findOne([
            'user_id' => $userId,
            'pref_key' => $key,
        ]);
        return $pref?->pref_value ?? $default;
    }

    /**
     * Set (create or update) a preference value for a given user and key.
     *
     * @throws Exception
     */
    public function setValue(int $userId, string $key, ?string $value): void
    {
        $pref = $this->findOrCreatePreference($userId, $key);
        $pref->pref_value = $value;

        if (!$pref->save()) {
            throw new Exception('Failed to save preference: ' . json_encode($pref->errors));
        }
    }

    /**
     * Remove a preference for a given user and key.
     *
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function removeValue(int $userId, string $key): void
    {
        $pref = UserPreference::findOne([
            'user_id' => $userId,
            'pref_key' => $key,
        ]);

        if ($pref !== null) {
            $pref->delete();
        }
    }

    private function findOrCreatePreference(int $userId, string $key): UserPreference
    {
        $pref = UserPreference::findOne([
            'user_id' => $userId,
            'pref_key' => $key,
        ]);

        if ($pref !== null) {
            return $pref;
        }

        return new UserPreference([
            'user_id' => $userId,
            'pref_key' => $key,
        ]);
    }
}
