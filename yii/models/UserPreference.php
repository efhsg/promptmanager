<?php

namespace app\models;

use app\models\traits\TimestampTrait;
use app\modules\identity\models\User;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 *
 * @property int $id
 * @property int $user_id
 * @property string $pref_key
 * @property string|null $pref_value
 * @property string $created_at
 * @property string $updated_at
 *
 * @property User $user
 */
class UserPreference extends ActiveRecord
{
    use TimestampTrait;

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'user_preference';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['user_id', 'pref_key'], 'required'],
            [['user_id'], 'integer'],
            [['created_at', 'updated_at'], 'string'],
            [['pref_key', 'pref_value'], 'string', 'max' => 255],
            [['user_id', 'pref_key'], 'unique', 'targetAttribute' => ['user_id', 'pref_key']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'pref_key' => 'Pref Key',
            'pref_value' => 'Pref Value',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return ActiveQuery
     */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->handleTimestamps($insert);

        return true;
    }

}
