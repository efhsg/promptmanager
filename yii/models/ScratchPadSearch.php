<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * ScratchPadSearch represents the model behind the search form about `app\models\ScratchPad`.
 */
class ScratchPadSearch extends ScratchPad
{
    public function rules(): array
    {
        return [
            [['name'], 'safe'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    public function search(array $params, int $userId, ?int $currentProjectId = null): ActiveDataProvider
    {
        $query = ScratchPad::find()
            ->forUserWithProject($userId, $currentProjectId)
            ->orderedByUpdated();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['like', 'name', $this->name]);

        return $dataProvider;
    }
}
