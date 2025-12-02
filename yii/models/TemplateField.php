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
    public static function tableName(): string
    {
        return 'template_field';
    }

    public function rules(): array
    {
        return [
            [['template_id', 'field_id'], 'required'],
            [['template_id', 'field_id'], 'integer'],
            [['field_id'], 'exist', 'skipOnError' => true, 'targetClass' => Field::class, 'targetAttribute' => ['field_id' => 'id']],
            [['template_id'], 'exist', 'skipOnError' => true, 'targetClass' => PromptTemplate::class, 'targetAttribute' => ['template_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'template_id' => 'Template ID',
            'field_id' => 'Field ID',
        ];
    }

    public function getField(): ActiveQuery
    {
        return $this->hasOne(Field::class, ['id' => 'field_id']);
    }

    public function getTemplate(): ActiveQuery
    {
        return $this->hasOne(PromptTemplate::class, ['id' => 'template_id']);
    }
}
