<?php
namespace app\models;

use app\models\traits\TimestampTrait;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "prompt_template".
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $template_body
 * @property string|null $description
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Project $project
 * @property PromptInstance[] $promptInstances
 * @property Field[] $fields   <-- New virtual attribute for related fields
 */
class PromptTemplate extends ActiveRecord
{
    use TimestampTrait;

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'prompt_template';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['project_id', 'name'], 'required'],
            [['project_id', 'created_at', 'updated_at'], 'integer'],
            [['template_body', 'description'], 'string'],
            [['name'], 'string', 'max' => 255],
            [
                ['name', 'project_id'],
                'unique',
                'targetAttribute' => ['name', 'project_id'],
                'message' => 'The template name has already been taken in this project.'
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
            'template_body' => 'Template Body',
            'description' => 'Description',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
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
     * Gets the name of the related project.
     *
     * @return string|null
     */
    public function getProjectName(): ?string
    {
        return $this->project->name ?? null;
    }

    /**
     * Gets query for [[PromptInstances]].
     *
     * @return ActiveQuery
     */
    public function getPromptInstances(): ActiveQuery
    {
        return $this->hasMany(PromptInstance::class, ['template_id' => 'id']);
    }

    /**
     * Gets query for the [[Field]] models related via the pivot table.
     *
     * This virtual attribute will return all the fields associated with this prompt template.
     *
     * @return ActiveQuery
     * @throws InvalidConfigException
     */
    public function getFields(): ActiveQuery
    {
        return $this->hasMany(Field::class, ['id' => 'field_id'])
            ->viaTable('{{%template_field}}', ['template_id' => 'id']);
    }

    public function init(): void
    {
        parent::init();

        if ($this->isNewRecord) {
            if ($this->project_id === null) {
                $projectContext = Yii::$app->projectContext;
                $currentProject = $projectContext->getCurrentProject();
                $this->project_id = $currentProject ? $currentProject['id'] : null;
            }
        }
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
