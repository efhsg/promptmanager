<?php

namespace app\models;

use app\modules\identity\models\User;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
  *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property int $created_at
 * @property int $updated_at
 * @property int|null $deleted_at
 *
 * @property User $user
 */
class Project extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'project';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['user_id', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['description'], 'string'],
            [['name'], 'string', 'max' => 255],
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
            'name' => 'Name',
            'description' => 'Description',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
        ];
    }

    /**
     * Before saving, set timestamps and user_id (on create).
     *
     * @param bool $insert whether this is a new record
     * @return bool
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $time = time();
        if ($insert) {
            $this->created_at = $time;
            $this->user_id = Yii::$app->user->id;
        }
        $this->updated_at = $time;

        return true;
    }

    /**
     * Gets query for [[User]].
     */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }


}
