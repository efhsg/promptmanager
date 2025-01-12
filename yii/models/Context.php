<?php /** @noinspection PhpUnused */

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "context".
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $content
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Project $project
 */
class Context extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'context';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['name', 'project_id'], 'required'],
            [['project_id', 'created_at', 'updated_at'], 'integer'],
            [['content'], 'string'],
            [['name'], 'string', 'max' => 255],
            [
                ['project_id', 'name'],
                'unique',
                'targetAttribute' => ['project_id', 'name'],
                'message' => 'The context name has already been taken in this sector.'
            ],            
            [['project_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Project::class,
                'targetAttribute' => ['project_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'project_id' => 'Project',
            'name' => 'Name',
            'content' => 'Content',
            'created_at' => 'Created',
            'updated_at' => 'Updated',
        ];
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
     * Before saving, set timestamps.
     *
     * @param bool $insert whether this is a new record
     * @return bool
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $time = time();
        if ($insert) {
            $this->created_at = $time;
        }
        $this->updated_at = $time;

        return true;
    }
}
