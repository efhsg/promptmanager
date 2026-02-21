<?php

namespace app\models;

use app\models\query\NoteQuery;
use app\models\traits\TimestampTrait;
use app\modules\identity\models\User;
use app\services\SearchTextExtractor;
use common\enums\NoteType;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "note".
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $project_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $type
 * @property string|null $content Quill Delta JSON
 * @property string|null $search_text
 * @property string $created_at
 * @property string $updated_at
 *
 * @property User $user
 * @property Project|null $project
 * @property Note|null $parent
 * @property Note[] $children
 */
class Note extends ActiveRecord
{
    use TimestampTrait;

    /** Virtual column populated by {@see NoteQuery::withChildCount()} */
    public ?int $child_count = null;

    public static function tableName(): string
    {
        return 'note';
    }

    public static function find(): NoteQuery
    {
        return new NoteQuery(static::class);
    }

    public function rules(): array
    {
        return [
            [['name', 'user_id'], 'required'],
            [['user_id', 'project_id', 'parent_id'], 'integer'],
            [['created_at', 'updated_at'], 'string'],
            [['content'], 'string'],
            [['name'], 'string', 'max' => 255],
            [['type'], 'string', 'max' => 50],
            [['type'], 'in', 'range' => NoteType::values()],
            [['type'], 'default', 'value' => NoteType::NOTE->value],
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
            [
                ['parent_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => self::class,
                'targetAttribute' => ['parent_id' => 'id'],
                'when' => fn($model) => $model->parent_id !== null,
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_id' => 'User',
            'project_id' => 'Project',
            'parent_id' => 'Parent',
            'name' => 'Name',
            'type' => 'Type',
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

    public function getParent(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getChildren(): ActiveQuery
    {
        return $this->hasMany(self::class, ['parent_id' => 'id']);
    }

    public function init(): void
    {
        parent::init();

        if ($this->isNewRecord) {
            if ($this->user_id === null) {
                $this->user_id = Yii::$app->user->id;
            }
            if ($this->type === null) {
                $this->type = NoteType::NOTE->value;
            }
        }
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->handleTimestamps($insert);
        $this->search_text = SearchTextExtractor::extract($this->content) ?: null;

        return true;
    }
}
