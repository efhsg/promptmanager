<?php

namespace app\models;

use app\models\query\ProjectWorktreeQuery;
use app\models\traits\TimestampTrait;
use common\enums\WorktreePurpose;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Represents a git worktree managed by PromptManager.
 *
 * @property int $id
 * @property int $project_id
 * @property string $purpose
 * @property string $branch
 * @property string $path_suffix
 * @property string $source_branch
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Project $project
 */
class ProjectWorktree extends ActiveRecord
{
    use TimestampTrait;

    public static function tableName(): string
    {
        return '{{%project_worktree}}';
    }

    public static function find(): ProjectWorktreeQuery
    {
        return new ProjectWorktreeQuery(static::class);
    }

    public function rules(): array
    {
        return [
            [['source_branch'], 'default', 'value' => 'main'],
            [['project_id', 'purpose', 'branch', 'path_suffix', 'source_branch'], 'required'],
            [['project_id'], 'integer'],
            [['project_id'], 'exist', 'targetClass' => Project::class, 'targetAttribute' => 'id'],
            [['purpose'], 'in', 'range' => WorktreePurpose::values()],
            [['branch'], 'string', 'max' => 255],
            [['branch'], 'match', 'pattern' => '/^[a-zA-Z0-9\/_.-]+$/'],
            [['branch'], 'rejectDoubleDots'],
            [['path_suffix'], 'string', 'max' => 100],
            [['path_suffix'], 'match', 'pattern' => '/^[a-zA-Z0-9_-]+$/'],
            [
                ['path_suffix'],
                'unique',
                'targetAttribute' => ['project_id', 'path_suffix'],
                'message' => 'This suffix is already in use for this project.',
            ],
            [['source_branch'], 'string', 'max' => 255],
            [['source_branch'], 'match', 'pattern' => '/^[a-zA-Z0-9\/_.-]+$/'],
            [['source_branch'], 'rejectDoubleDots'],
        ];
    }

    /**
     * Custom validator: reject values containing '..' (path traversal prevention).
     */
    public function rejectDoubleDots(string $attribute): void
    {
        if (str_contains((string) $this->$attribute, '..')) {
            $this->addError($attribute, ucfirst($attribute) . ' must not contain "..".');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'project_id' => 'Project',
            'purpose' => 'Purpose',
            'branch' => 'Branch',
            'path_suffix' => 'Path Suffix',
            'source_branch' => 'Source Branch',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Returns the full worktree path: <project.root_directory>-<path_suffix>.
     */
    public function getFullPath(): ?string
    {
        if (!$this->project || !$this->project->root_directory) {
            return null;
        }

        return rtrim($this->project->root_directory, '/') . '-' . $this->path_suffix;
    }

    public function getProject(): ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getPurposeEnum(): WorktreePurpose
    {
        return WorktreePurpose::from($this->purpose);
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
