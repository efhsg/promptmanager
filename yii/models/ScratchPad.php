<?php

namespace app\models;

use app\models\query\ScratchPadQuery;
use app\models\traits\TimestampTrait;
use app\modules\identity\models\User;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "scratch_pad".
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $project_id
 * @property string $name
 * @property string|null $content Quill Delta JSON
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $user
 * @property Project|null $project
 */
class ScratchPad extends ActiveRecord
{
    use TimestampTrait;

    public static function tableName(): string
    {
        return 'scratch_pad';
    }

    public static function find(): ScratchPadQuery
    {
        return new ScratchPadQuery(static::class);
    }

    public function rules(): array
    {
        return [
            [['name', 'user_id'], 'required'],
            [['user_id', 'project_id', 'created_at', 'updated_at'], 'integer'],
            [['content'], 'string'],
            [['name'], 'string', 'max' => 255],
            [
                ['user_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => User::class,
                'targetAttribute' => ['user_id' => 'id'],
            ],
            [
                ['project_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Project::class,
                'targetAttribute' => ['project_id' => 'id'],
                'when' => fn($model) => $model->project_id !== null,
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_id' => 'User',
            'project_id' => 'Project',
            'name' => 'Name',
            'content' => 'Content',
            'created_at' => 'Created',
            'updated_at' => 'Updated',
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getProject(): ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function init(): void
    {
        parent::init();

        if ($this->isNewRecord && $this->user_id === null) {
            $this->user_id = Yii::$app->user->id;
        }
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
