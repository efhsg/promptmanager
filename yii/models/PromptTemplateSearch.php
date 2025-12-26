<?php

namespace app\models;

use app\components\ProjectContext;
use InvalidArgumentException;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * PromptTemplateSearch represents the model behind the search form of `app\models\PromptTemplate`.
 */
class PromptTemplateSearch extends PromptTemplate
{
    public string $projectName = '';

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['id', 'project_id', 'created_at', 'updated_at'], 'integer'],
            [['name', 'template_body'], 'safe'],
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
            throw new InvalidArgumentException('User ID is required.');
        }

        $query = PromptTemplate::find()->joinWith('project p');

        if ($projectId === ProjectContext::NO_PROJECT_ID) {
            $query->andWhere(['{{%prompt_template}}.project_id' => null]);
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
        ]);

        $dataProvider->sort->defaultOrder = [
            'name' => SORT_ASC,
        ];

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
            ->andFilterWhere(['like', 'template_body', $this->template_body])
            ->andFilterWhere(['like', 'p.name', $this->projectName]);

        return $dataProvider;
    }
}
