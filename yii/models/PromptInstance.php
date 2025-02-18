<?php

namespace app\models;

use app\models\traits\TimestampTrait;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "prompt_instance".
 *
 * @property int $id
 * @property int $template_id
 * @property string $final_prompt
 * @property int $created_at
 * @property int $updated_at
 *
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
            [['template_id', 'final_prompt'], 'required'],
            [['template_id'], 'integer'],
            [['final_prompt'], 'string'],
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
}
