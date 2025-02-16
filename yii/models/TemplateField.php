<?php

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "template_field".
 *
 * @property int $template_id
 * @property int $field_id
 *
 * @property Field $field
 * @property PromptTemplate $template
 */
class TemplateField extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'template_field';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['template_id', 'field_id'], 'required'],
            [['template_id', 'field_id'], 'integer'],
            [['field_id'], 'exist', 'skipOnError' => true, 'targetClass' => Field::class, 'targetAttribute' => ['field_id' => 'id']],
            [['template_id'], 'exist', 'skipOnError' => true, 'targetClass' => PromptTemplate::class, 'targetAttribute' => ['template_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'template_id' => 'Template ID',
            'field_id' => 'Field ID',
        ];
    }

    /**
     * Gets query for [[Field]].
     *
     * @return ActiveQuery
     */
    public function getField(): ActiveQuery
    {
        return $this->hasOne(Field::class, ['id' => 'field_id']);
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
}
