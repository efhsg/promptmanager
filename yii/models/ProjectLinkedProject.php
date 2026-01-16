<?php

namespace app\models;

use app\models\query\ProjectLinkedProjectQuery;
use app\models\traits\TimestampTrait;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Class ProjectLinkedProject
 *
 * Represents the relationship between a project and its linked external projects.
 *
 * @property int $id
 * @property int $project_id
 * @property int $linked_project_id
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Project $project
 * @property Project $linkedProject
 */
class ProjectLinkedProject extends ActiveRecord
{
    use TimestampTrait;

    public static function tableName(): string
    {
        return 'project_linked_project';
    }

    public function rules(): array
    {
        return [
            [['project_id', 'linked_project_id'], 'required'],
            [['project_id', 'linked_project_id'], 'integer'],
            [['created_at', 'updated_at'], 'string'],
            [
                ['project_id', 'linked_project_id'],
                'unique',
                'targetAttribute' => ['project_id', 'linked_project_id'],
                'message' => 'This project is already linked.',
            ],
            [
                ['project_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Project::class,
                'targetAttribute' => ['project_id' => 'id'],
            ],
            [
                ['linked_project_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Project::class,
                'targetAttribute' => ['linked_project_id' => 'id'],
            ],
            ['linked_project_id', 'validateNotSameAsProject'],
        ];
    }

    public function validateNotSameAsProject(string $attribute): void
    {
        if ($this->$attribute === $this->project_id) {
            $this->addError($attribute, 'A project cannot be linked to itself.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'project_id' => 'Project ID',
            'linked_project_id' => 'Linked Project ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    public function getProject(): ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getLinkedProject(): ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'linked_project_id']);
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->handleTimestamps($insert);

        return true;
    }

    public static function find(): ProjectLinkedProjectQuery
    {
        return new ProjectLinkedProjectQuery(get_called_class());
    }

}
