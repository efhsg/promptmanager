<?php

namespace app\models;

use app\models\query\FieldQuery;
use app\models\traits\TimestampTrait;
use app\modules\identity\models\User;
use app\services\PathService;
use common\constants\FieldConstants;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "field".
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $project_id
 * @property string $name
 * @property string $type
 * @property string|null $content
 * @property bool $selected_by_default
 * @property bool $share
 * @property string|null $label
 * @property int $created_at
 * @property int $updated_at
 *
 * @property FieldOption[] $fieldOptions
 * @property Project $project
 * @property User $user
 */
class Field extends ActiveRecord
{

    use TimestampTrait;

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'field';
    }

    public static function find(): FieldQuery
    {
        return new FieldQuery(static::class);
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['name', 'type'], 'required'],
            [['user_id', 'project_id', 'created_at', 'updated_at'], 'integer'],
            [['selected_by_default', 'share'], 'boolean'],
            [['type'], 'string'],
            [['type'], 'in', 'range' => FieldConstants::TYPES],
            [['name', 'label'], 'string', 'max' => 255],
            ['name', 'validateUniqueNameWithinProject', 'skipOnError' => true],
            [
                ['label'],
                'unique',
                'targetAttribute' => ['project_id', 'label', 'user_id'],
                'filter' => ['not', ['label' => null]],
                'message' => 'Label must be unique within the project.',
            ],
            [['content'], 'string'],
            [['content'], 'validatePathContent'],
            [['share'], 'default', 'value' => false],
            [['project_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Project::class,
                'targetAttribute' => ['project_id' => 'id'],
                'when' => function ($model) {
                    return $model->project_id !== null;
                }
            ],
            [['user_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => User::class,
                'targetAttribute' => ['user_id' => 'id']],
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
            'project_id' => 'Project ID',
            'name' => 'Name',
            'content' => 'Content',
            'type' => 'Type',
            'selected_by_default' => 'Default on',
            'share' => 'Share with linked projects',
            'label' => 'Label',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * Gets query for [[FieldOptions]].
     *
     * @return ActiveQuery
     */
    public function getFieldOptions(): ActiveQuery
    {
        return $this->hasMany(FieldOption::class, ['field_id' => 'id'])
            ->orderBy(['order' => SORT_ASC, 'id' => SORT_ASC]);
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
     * Gets query for [[User]].
     *
     * @return ActiveQuery
     */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function init(): void
    {
        parent::init();

        if ($this->isNewRecord) {
            if ($this->type === null) {
                $this->type = FieldConstants::TYPES[0];
            }

            if ($this->project_id === null) {
                $projectContext = Yii::$app->projectContext;
                $currentProject = $projectContext->getCurrentProject();
                $this->project_id = $currentProject ? $currentProject['id'] : null;
            }
        }
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->handleTimestamps($insert);

        if (empty($this->label)) {
            $this->label = null;
        }

        return true;
    }

    public function validateUniqueNameWithinProject($attribute): void
    {
        $query = self::find()->where(['name' => $this->name]);

        if ($this->project_id === null || $this->project_id === '') {
            $query->andWhere(['project_id' => null]);
        } else {
            $query->andWhere(['project_id' => $this->project_id]);
        }

        if (!$this->isNewRecord) {
            $query->andWhere(['<>', 'id', $this->id]);
        }

        if ($query->exists()) {
            $this->addError($attribute, 'Duplicate name.');
        }
    }

    public function validatePathContent(string $attribute): void
    {
        if (!in_array($this->type, FieldConstants::PATH_FIELD_TYPES, true)) {
            return;
        }

        $relativePath = trim((string)$this->$attribute);
        if ($relativePath === '') {
            return;
        }

        $project = $this->project;
        if ($project === null && $this->project_id !== null) {
            $project = Project::findOne($this->project_id);
        }

        if ($project === null || $project->user_id !== $this->user_id) {
            $this->addError('project_id', 'Select a valid project for file and directory fields.');
            return;
        }

        if (empty($project->root_directory)) {
            $this->addError('project_id', 'The selected project must have a root directory.');
            return;
        }

        if (!is_dir($project->root_directory)) {
            $this->addError('project_id', 'The configured project root directory is not accessible.');
            return;
        }

        $pathService = new PathService();
        $absolutePath = $pathService->resolveRequestedPath(
            $project->root_directory,
            $relativePath,
            $project->getBlacklistedDirectories()
        );

        if ($absolutePath === null) {
            $this->addError($attribute, 'The selected path is not allowed for this project.');
            return;
        }

        if ($this->type === 'directory') {
            if (!is_dir($absolutePath)) {
                $this->addError($attribute, 'The selected directory does not exist.');
                return;
            }
        } else {
            if (!is_file($absolutePath)) {
                $this->addError($attribute, 'The selected file does not exist.');
                return;
            }

            if (!$project->isFileExtensionAllowed(pathinfo($absolutePath, PATHINFO_EXTENSION))) {
                $this->addError($attribute, 'File extension not allowed for this project.');
                return;
            }
        }

        $this->$attribute = ltrim(str_replace('\\', '/', $relativePath), '/');
    }

}
