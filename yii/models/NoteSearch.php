<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * NoteSearch represents the model behind the search form about `app\models\Note`.
 */
class NoteSearch extends Note
{
    public function rules(): array
    {
        return [
            [['name', 'type'], 'safe'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    public function search(
        array $params,
        int $userId,
        ?int $currentProjectId = null,
        bool $isAllProjects = false,
        bool $showChildren = false
    ): ActiveDataProvider {
        $query = $isAllProjects
            ? Note::find()->forUser($userId)
            : Note::find()->forUserWithProject($userId, $currentProjectId);

        if (!$showChildren) {
            $query->topLevel();
        }

        $query->withChildCount()->orderedByUpdated();

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
        $query->andFilterWhere(['type' => $this->type]);

        return $dataProvider;
    }
}
