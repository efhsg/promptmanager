<?php

namespace app\models;

use app\models\traits\TimestampTrait;
use app\modules\identity\models\User;
use common\constants\FieldConstants;
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
 * @property int $selected_by_default
 * @property string|null $label
 * @property int $created_at
 * @property int $updated_at
 *
 * @property FieldOption[] $fieldOptions
 * @property Project $project
 * @property TemplateField[] $templateFields
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
            'type' => 'Type',
            'selected_by_default' => 'Selected By Default',
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
     * Gets query for [[TemplateFields]].
     *
     * @return ActiveQuery
     */
    public function getTemplateFields(): ActiveQuery
    {
        return $this->hasMany(TemplateField::class, ['field_id' => 'id']);
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

        if (empty($this->label)) {
            $this->label = null;
        }

        return true;
    }

}
