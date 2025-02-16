<?php


namespace app\models;

use InvalidArgumentException;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * PromptInstanceSearch represents the model behind the search form of `app\models\PromptInstance`.
 */
class PromptInstanceSearch extends PromptInstance
{
    /**
     * This property allows filtering by the name of the related project.
     *
     * @var string
     */
    public string $projectName = '';

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['id', 'template_id', 'created_at', 'updated_at'], 'integer'],
            [['final_prompt'], 'safe'],
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
     * @return ActiveDataProvider
     * @throws InvalidArgumentException if no user ID is provided.
     */
    public function search(array $params, ?int $userId = null): ActiveDataProvider
    {
        if (!$userId) {
            throw new InvalidArgumentException('User ID is required.');
        }

        $query = PromptInstance::find()
            ->joinWith(['template.project p'])
            ->andWhere(['p.user_id' => $userId]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);

        // Allow sorting by the related project's name.
        $dataProvider->sort->attributes['projectName'] = [
            'asc' => ['p.name' => SORT_ASC],
            'desc' => ['p.name' => SORT_DESC],
        ];

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['like', 'final_prompt', $this->final_prompt])
            ->andFilterWhere(['like', 'p.name', $this->projectName]);

        return $dataProvider;
    }
}
