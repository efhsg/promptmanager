<?php

namespace app\models;

use app\components\ProjectContext;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * NoteSearch represents the model behind the search form about `app\models\Note`.
 */
class NoteSearch extends Note
{
    public ?string $q = null;
    public ?int $project_id = null;

    public function rules(): array
    {
        return [
            [['name', 'type', 'q'], 'safe'],
            [['project_id'], 'integer'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    /**
     * @param array $params Request parameters
     * @param int $userId Current user ID
     * @param int|null $currentProjectId Context project (used as default for project_id)
     * @param bool $isAllProjects True if context = "All Projects"
     * @param bool $showChildren Include child notes
     */
    public function search(
        array $params,
        int $userId,
        ?int $currentProjectId = null,
        bool $isAllProjects = false,
        bool $showChildren = false
    ): ActiveDataProvider {
        // Load form data FIRST to populate $this->project_id
        $this->load($params);

        // Determine effective project_id:
        // 1. Form input (if present)
        // 2. Otherwise: context default
        $effectiveProjectId = $this->project_id;
        if ($effectiveProjectId === null) {
            $effectiveProjectId = $isAllProjects
                ? ProjectContext::ALL_PROJECTS_ID
                : $currentProjectId;
            // Set project_id so form shows correct default
            $this->project_id = $effectiveProjectId;
        }

        // Build query based on project selection
        if ($effectiveProjectId === ProjectContext::ALL_PROJECTS_ID || $effectiveProjectId === null) {
            $query = Note::find()->forUser($userId);
        } else {
            $query = Note::find()->forUserWithProject($userId, $effectiveProjectId);
        }

        if (!$showChildren) {
            $query->topLevel();
        }

        $query->withChildCount();

        // Join project table for sorting by project name
        $query->joinWith(['project']);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 10,
            ],
            'sort' => [
                'defaultOrder' => ['updated_at' => SORT_DESC],
                'attributes' => [
                    'name' => [
                        'asc' => [Note::tableName() . '.name' => SORT_ASC],
                        'desc' => [Note::tableName() . '.name' => SORT_DESC],
                    ],
                    'type',
                    'project_id' => [
                        'asc' => [Project::tableName() . '.name' => SORT_ASC],
                        'desc' => [Project::tableName() . '.name' => SORT_DESC],
                    ],
                    'updated_at' => [
                        'asc' => [Note::tableName() . '.updated_at' => SORT_ASC],
                        'desc' => [Note::tableName() . '.updated_at' => SORT_DESC],
                    ],
                ],
            ],
        ]);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['type' => $this->type]);

        if ($this->q !== null && $this->q !== '') {
            $query->searchByTerm($this->q);
        }

        return $dataProvider;
    }
}
