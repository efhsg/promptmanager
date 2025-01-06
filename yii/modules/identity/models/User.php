<?php /** @noinspection PhpUnused */

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
 * @property int $status
 * @property int $created_at
 * @property int $updated_at
 * @property int $deleted_at
 */
class User extends ActiveRecord implements IdentityInterface
{


    const TOKEN_EXPIRATION_SECONDS = 3600;

    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 10;

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
        return static::find()->active()->andWhere(['access_token' => $token])->one();
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
        $timestamp = (int)end($parts);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'] ?? self::TOKEN_EXPIRATION_SECONDS;

        return $timestamp + $expire >= time();
    }

    public function behaviors(): array
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            [['username', 'email', 'password_hash'], 'required'],
            [['status', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['username', 'email', 'password_hash', 'password_reset_token', 'access_token'], 'string', 'min' => 3, 'max' => 255],
            [['auth_key'], 'string', 'max' => 32],
            [['username', 'email', 'password_reset_token', 'access_token'], 'unique'],
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
