<?php

namespace app\models;

use app\models\traits\TimestampTrait;
use app\modules\identity\models\User;
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
 * @property int $selected_by_default
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

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['name', 'type'], 'required'],
            [['user_id', 'project_id', 'selected_by_default', 'created_at', 'updated_at'], 'integer'],
            [['type'], 'string'],
            [['type'], 'in', 'range' => FieldConstants::TYPES],
            [['name', 'label'], 'string', 'max' => 255],
            ['name', 'validateUniqueNameWithinProject', 'skipOnError' => true],
            [['content'], 'string'],
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
        return $this->hasMany(FieldOption::class, ['field_id' => 'id']);
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



}
