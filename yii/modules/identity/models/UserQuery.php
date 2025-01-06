<?php

namespace app\modules\identity\models;

use yii\db\ActiveQuery;
use yii\db\Connection;

class UserQuery extends ActiveQuery
{
    /**
     * Filters active users.
     *
     * @return $this
     */
    public function active(): self
    {
        return $this->withoutDeleted()->andWhere(['status' => User::STATUS_ACTIVE]);
    }

    /**
     * Excludes deleted users.
     *
     * @return $this
     */
    public function withoutDeleted(): self
    {
        return $this->andWhere(['deleted_at' => null]);
    }

    /**
     * Finds users by username.
     *
     * @param string $username
     * @return $this
     */
    public function byUsername(string $username): self
    {
        return $this->andWhere(['username' => $username]);
    }

    /**
     * Finds users by password reset token.
     *
     * @param string $token
     * @return $this
     */
    public function byPasswordResetToken(string $token): self
    {
        return $this->andWhere(['password_reset_token' => $token]);
    }

    /**
     * Ensures the return type for `one()`.
     *
     * @param null|Connection $db
     * @return User|null
     */
    public function one($db = null): ?User
    {
        $result = parent::one($db);
        return $result instanceof User ? $result : null;
    }

    /**
     * Ensures the return type for `all()`.
     *
     * @param null|Connection $db
     * @return User[]
     */
    public function all($db = null): array
    {
        return parent::all($db);
    }
}
