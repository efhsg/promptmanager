<?php

namespace app\models;

use app\models\traits\TimestampTrait;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "template_field".
 *
 * @property int $id
 * @property int $template_id
 * @property int $field_id
 * @property int|null $order
 * @property string|null $override_label
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Field $field
 * @property PromptTemplate $template
 */
class TemplateField extends ActiveRecord
{
    use TimestampTrait;
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
            [['template_id', 'field_id', 'order', 'created_at', 'updated_at'], 'integer'],
            [['override_label'], 'string', 'max' => 255],
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
            'id' => 'ID',
            'template_id' => 'Template ID',
            'field_id' => 'Field ID',
            'order' => 'Order',
            'override_label' => 'Override Label',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
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

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->handleTimestamps($insert);

        return true;
    }
}
