<?php

/** @noinspection PhpUnused */

namespace app\models;

use app\models\traits\TimestampTrait;
use app\models\query\ContextQuery;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "context".
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $content
 * @property bool $is_default
 * @property bool $share
 * @property int $order
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Project $project
 */
class Context extends ActiveRecord
{
    use TimestampTrait;

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'context';
    }

    public static function find(): ContextQuery
    {
        return new ContextQuery(static::class);
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['name', 'project_id'], 'required'],
            [['project_id', 'created_at', 'updated_at', 'order'], 'integer'],
            [['content'], 'string'],
            [['is_default', 'share'], 'boolean'],
            [['share'], 'default', 'value' => false],
            [['order'], 'default', 'value' => 0],
            [['name'], 'string', 'max' => 255],
            [
                ['project_id', 'name'],
                'unique',
                'targetAttribute' => ['project_id', 'name'],
                'message' => 'The context name has already been taken in this sector.',
            ],
            [['project_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Project::class,
                'targetAttribute' => ['project_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'project_id' => 'Project',
            'name' => 'Name',
            'content' => 'Content',
            'is_default' => 'Default',
            'share' => 'Share',
            'order' => 'Order',
            'created_at' => 'Created',
            'updated_at' => 'Updated',
        ];
    }

    /**
     * Gets query for [[Project]].
     *
     * @return ActiveQuery
     */
    public function getProject(): ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    /**
     * Gets the name of the related project.
     *
     * @return string|null
     */
    public function getProjectName(): ?string
    {
        return $this->project->name ?? null;
    }

    public function init(): void
    {
        parent::init();

        if ($this->isNewRecord && $this->project_id === null) {
            $currentProject = (Yii::$app->projectContext)->getCurrentProject();
            $this->project_id = $currentProject ? $currentProject['id'] : null;
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
