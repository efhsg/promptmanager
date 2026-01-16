<?php

/** @noinspection PhpUnused */

namespace app\modules\identity\models;

use Yii;
use yii\base\Exception;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $password_hash
 * @property string $auth_key
 * @property string|null $password_reset_token
 * @property string|null $access_token
 * @property string|null $access_token_hash
 * @property string|null $access_token_expires_at
 * @property int $status
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 */
class User extends ActiveRecord implements IdentityInterface
{
    public const TOKEN_EXPIRATION_SECONDS = 3600;

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 10;

    public static function tableName(): string
    {
        return '{{%user}}';
    }

    public static function findIdentity($id): ?self
    {
        return static::find()->active()->andWhere(['id' => $id])->one();
    }

    public static function find(): UserQuery
    {
        return new UserQuery(get_called_class());
    }

    public static function findIdentityByAccessToken($token, $type = null): ?self
    {
        $hash = hash('sha256', $token);
        return static::find()
            ->active()
            ->andWhere(['access_token_hash' => $hash])
            ->andWhere(['or',
                ['access_token_expires_at' => null],
                ['>', 'access_token_expires_at', date('Y-m-d H:i:s')],
            ])
            ->one();
    }

    public static function findByUsername(string $username): ?self
    {
        return static::find()->active()->byUsername($username)->one();
    }

    public static function findByPasswordResetToken(string $token): ?self
    {
        if (!self::isPasswordResetTokenValid($token)) {
            return null;
        }
        return static::find()->active()->byPasswordResetToken($token)->one();
    }

    private static function isPasswordResetTokenValid($token): bool
    {
        if (!$token || !str_contains($token, '_')) {
            return false;
        }
        $parts = explode('_', $token);
        $timestamp = (int) end($parts);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'] ?? self::TOKEN_EXPIRATION_SECONDS;

        return $timestamp + $expire >= time();
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => fn() => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['username', 'email', 'password_hash'], 'required'],
            [['status'], 'integer'],
            [['created_at', 'updated_at', 'deleted_at', 'access_token_expires_at'], 'string'],
            [['username', 'email', 'password_hash', 'password_reset_token', 'access_token'], 'string', 'min' => 3, 'max' => 255],
            [['access_token_hash'], 'string', 'max' => 255],
            [['auth_key'], 'string', 'max' => 32],
            [['username', 'email', 'password_reset_token', 'access_token', 'access_token_hash'], 'unique'],
            [['status'], 'default', 'value' => self::STATUS_ACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE]],
            [['username', 'email'], 'trim'],
            [['email'], 'filter', 'filter' => 'strtolower'],
            [['email'], 'email'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'username' => 'Username',
            'email' => 'Email',
            'password_hash' => 'Password Hash',
            'auth_key' => 'Auth Key',
            'password_reset_token' => 'Password Reset Token',
            'access_token' => 'Access Token',
            'access_token_hash' => 'Access Token Hash',
            'access_token_expires_at' => 'Access Token Expires At',
            'status' => 'Status',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
        ];
    }

    public function getId(): int|string
    {
        return $this->getPrimaryKey();
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->getAuthKey() === $authKey;
    }

    public function getAuthKey(): string
    {
        return $this->auth_key;
    }

    /**
     * @throws Exception
     */
    public function setPassword($password): void
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function validatePassword($password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * @throws Exception
     */
    public function generateAuthKey(): void
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }
}
