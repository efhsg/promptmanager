<?php

namespace app\models;

use app\models\traits\TimestampTrait;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "field_option".
 *
 * @property int $id
 * @property int $field_id
 * @property string $value
 * @property string|null $label
 * @property bool $selected_by_default
 * @property int|null $order
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Field $field
 */
class FieldOption extends ActiveRecord
{

    use TimestampTrait;

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
            [['value'], 'required'],
            [['field_id', 'order', 'created_at', 'updated_at'], 'integer'],
            [['selected_by_default'], 'boolean'],
            [['value'], 'string',],
            [['label'], 'string', 'max' => 255],
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
            'selected_by_default' => 'Default on',
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

    public function init(): void
    {
        parent::init();

        if ($this->isNewRecord && $this->order === null) {
            $this->order = 10;
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
}
