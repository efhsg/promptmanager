<?php /** @noinspection PhpUnused */

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * ProjectSearch represents the model behind the search form of `app\modules\project\models\Project`.
 */
class ProjectSearch extends Project
{
    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['id', 'user_id'], 'integer'],
            [['name', 'description'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios(): array
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @param int|null $userId If we need to filter by a specific user
     * @return ActiveDataProvider
     */
    public function search(array $params, ?int $userId = null): ActiveDataProvider
    {
        $query = Project::find();

        // If each user can only see their own Projects, enforce user_id
        if ($userId) {
            $query->andWhere(['user_id' => $userId]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,  // Adjust as needed
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // If validation fails, we can optionally return no records.
            // $query->where('0=1');
            return $dataProvider;
        }

        // Apply filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'deleted_at' => $this->deleted_at,
        ]);

        $query->andFilterWhere(['like', 'name', $this->name])
            ->andFilterWhere(['like', 'description', $this->description]);

        return $dataProvider;
    }
}
