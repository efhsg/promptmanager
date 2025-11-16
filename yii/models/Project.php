<?php

namespace app\models;

use app\models\traits\TimestampTrait;
use app\modules\identity\models\User;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Class Project
 *
 * Represents a project entity with attributes such as name, description, and timestamps.
 * Provides relationships and validation rules for the project model.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property string|null $root_directory
 * @property int $created_at
 * @property int $updated_at
 * @property int|null $deleted_at
 *
 * @property User $user
 */
class Project extends ActiveRecord
{
    use TimestampTrait;

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
            [['name', 'user_id'], 'required'],
            [['user_id', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['description'], 'string'],
            [['root_directory'], 'string', 'max' => 1024],
            [['name'], 'string', 'max' => 255],
            [['user_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
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
            'root_directory' => 'Root Directory',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
        ];
    }

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
