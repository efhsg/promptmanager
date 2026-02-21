<?php

namespace app\models;

use app\components\ProjectContext;
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
            [['id', 'project_id', 'order'], 'integer'],
            [['name', 'content', 'created_at', 'updated_at'], 'safe'],
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

        $query = Context::find()->joinWith('project p');

        if ($projectId === ProjectContext::NO_PROJECT_ID) {
            $query->andWhere(['{{%context}}.project_id' => null]);
        } else {
            $query->andWhere(['p.user_id' => $userId]);
            if ($projectId !== null) {
                $query->andWhere(['p.id' => $projectId]);
            }
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

        $dataProvider->sort->attributes['name'] = [
            'asc' => ['{{%context}}.name' => SORT_ASC],
            'desc' => ['{{%context}}.name' => SORT_DESC],
        ];

        $dataProvider->sort->attributes['order'] = [
            'asc' => ['{{%context}}.order' => SORT_ASC],
            'desc' => ['{{%context}}.order' => SORT_DESC],
        ];

        $dataProvider->sort->attributes['is_default'] = [
            'asc' => ['{{%context}}.is_default' => SORT_ASC],
            'desc' => ['{{%context}}.is_default' => SORT_DESC],
        ];

        $dataProvider->sort->attributes['share'] = [
            'asc' => ['{{%context}}.share' => SORT_ASC],
            'desc' => ['{{%context}}.share' => SORT_DESC],
        ];

        $dataProvider->sort->attributes['updated_at'] = [
            'asc' => ['{{%context}}.updated_at' => SORT_ASC],
            'desc' => ['{{%context}}.updated_at' => SORT_DESC],
        ];

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query
            ->andFilterWhere(['like', '{{%context}}.name', $this->name])
            ->andFilterWhere(['like', '{{%context}}.search_text', $this->content])
            ->andFilterWhere(['like', 'p.name', $this->projectName]);

        return $dataProvider;
    }
}
