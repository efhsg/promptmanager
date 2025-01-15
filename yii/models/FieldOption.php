<?php

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "field_option".
 *
 * @property int $id
 * @property int $field_id
 * @property string $value
 * @property string|null $label
 * @property int $selected_by_default
 * @property int|null $order
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Field $field
 */
class FieldOption extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'field_option';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['field_id', 'value', 'created_at', 'updated_at'], 'required'],
            [['field_id', 'selected_by_default', 'order', 'created_at', 'updated_at'], 'integer'],
            [['value', 'label'], 'string', 'max' => 255],
            [['field_id'], 'exist', 'skipOnError' => true, 'targetClass' => Field::class, 'targetAttribute' => ['field_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'field_id' => 'Field ID',
            'value' => 'Value',
            'label' => 'Label',
            'selected_by_default' => 'Selected By Default',
            'order' => 'Order',
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
}
