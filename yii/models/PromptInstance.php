<?php

namespace app\models;

use app\models\Project;
use app\models\PromptTemplate;
use app\models\traits\TimestampTrait;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "prompt_instance".
 *
 * @property int $id
 * @property int $template_id
 * @property int $project_id
 * @property string $label
 * @property string $final_prompt
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Project $project
 * @property PromptTemplate $template
 */
class PromptInstance extends ActiveRecord
{
    use TimestampTrait;

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'prompt_instance';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['template_id', 'project_id', 'label', 'final_prompt'], 'required'],
            [['template_id', 'project_id'], 'integer'],
            [['label'], 'string', 'max' => 255],
            [['final_prompt'], 'string'],
            [
                ['label', 'project_id'],
                'unique',
                'targetAttribute' => ['label', 'project_id'],
                'message' => 'Label must be unique within the project.'
            ],
            [['template_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => PromptTemplate::class,
                'targetAttribute' => ['template_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'template_id' => 'Template',
            'project_id' => 'Project',
            'label' => 'Label',
            'final_prompt' => 'Prompt',
            'created_at' => 'Created',
            'updated_at' => 'Updated',
        ];
    }

    /**
     * Gets query for [[Template]].
     *
     * @return ActiveQuery
     */
    public function getTemplate(): ActiveQuery
    {
        return $this->hasOne(PromptTemplate::class, ['id' => 'template_id']);
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
     * Handles timestamps before saving the record.
     *
     * @param bool $insert whether this is a new record insertion.
     * @return bool
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->handleTimestamps($insert);

        return true;
    }

    public function beforeValidate(): bool
    {
        if ($this->project_id === null && $this->template_id) {
            $this->project_id = PromptTemplate::find()
                ->select('project_id')
                ->where(['id' => $this->template_id])
                ->scalar();
        }

        return parent::beforeValidate();
    }
}
