<?php

namespace app\models;

use InvalidArgumentException;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * FieldSearch represents the model behind the search form of `app\models\Field`.
 */
class FieldSearch extends Field
{
    public string $projectName = "";

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['id', 'user_id', 'project_id', 'selected_by_default', 'created_at', 'updated_at'], 'integer'],
            [['name', 'content', 'type', 'label'], 'safe'],
            ['projectName', 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios(): array
    {
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @param int|null $userId
     * @param int|null $projectId
     * @return ActiveDataProvider
     */
    public function search(array $params, ?int $userId = null, ?int $projectId = null): ActiveDataProvider
    {
        if (!$userId) {
            throw new InvalidArgumentException('User ID must be provided for ContextSearch.');
        }
        $query = Field::find()
            ->joinWith('user u', false, 'INNER JOIN')
            ->joinWith('project')
            ->andWhere(['u.id' => $userId]);
        if ($projectId !== null) {
            $query->andWhere(['project.id' => $projectId]);
        }
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
        $dataProvider->sort->attributes['projectName'] = [
            'asc' => ['project.name' => SORT_ASC],
            'desc' => ['project.name' => SORT_DESC],
        ];
        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }
        $query
            ->andFilterWhere(['like', 'name', $this->name])
            ->andFilterWhere(['like', 'content', $this->content])
            ->andFilterWhere(['like', 'type', $this->type])
            ->andFilterWhere(['like', 'project.name', $this->projectName]);
        return $dataProvider;
    }

    public function init(): void
    {
        ActiveRecord::init();
        $this->isNewRecord = false;
    }
}
