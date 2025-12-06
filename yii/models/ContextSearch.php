<?php

namespace app\models;

use InvalidArgumentException;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * ContextSearch represents the model behind the search form of `app\models\Context`.
 */
class ContextSearch extends Context
{
    public string $projectName = '';

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['id', 'project_id', 'created_at', 'updated_at', 'order'], 'integer'],
            [['name', 'content'], 'safe'],
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
     * Creates data provider instance with search query applied.
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

        $query = Context::find()
            ->joinWith('project p')
            ->andWhere(['p.user_id' => $userId]);

        if ($projectId !== null) {
            $query->andWhere(['p.id' => $projectId]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,
            ],
            'sort' => [
                'defaultOrder' => [
                    'order' => SORT_ASC,
                    'is_default' => SORT_DESC,
                    'name' => SORT_ASC,
                ],
            ],
        ]);

        $dataProvider->sort->attributes['projectName'] = [
            'asc' => ['p.name' => SORT_ASC],
            'desc' => ['p.name' => SORT_DESC],
        ];

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query
            ->andFilterWhere(['like', 'name', $this->name])
            ->andFilterWhere(['like', 'content', $this->content])
            ->andFilterWhere(['like', 'p.name', $this->projectName]);

        return $dataProvider;
    }
}
