<?php

namespace app\models;

use app\components\ProjectContext;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * AiRunSearch represents the model behind the search/filter form for AiRun.
 */
class AiRunSearch extends AiRun
{
    public ?string $q = null;
    public $project_id;

    public function init(): void
    {
        parent::init();
        $this->status = null;
    }

    public function rules(): array
    {
        return [
            [['q', 'status'], 'safe'],
            [['project_id'], 'integer'],
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
        bool $isAllProjects = false
    ): ActiveDataProvider {
        $this->load($params);

        if (!$this->validate()) {
            $effectiveProjectId = $isAllProjects
                ? ProjectContext::ALL_PROJECTS_ID
                : $currentProjectId;
            $this->project_id = $effectiveProjectId;
        }

        $effectiveProjectId = $this->project_id;
        if ($effectiveProjectId === null) {
            $effectiveProjectId = $isAllProjects
                ? ProjectContext::ALL_PROJECTS_ID
                : $currentProjectId;
            $this->project_id = $effectiveProjectId;
        }

        $query = AiRun::find()
            ->forUser($userId)
            ->sessionRepresentatives()
            ->withSessionAggregates()
            ->joinWith(['project']);

        if ($effectiveProjectId !== ProjectContext::ALL_PROJECTS_ID && $effectiveProjectId !== null) {
            $query->forProject((int) $effectiveProjectId);
        }

        $t = AiRun::tableName();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'defaultOrder' => ['session_last_activity' => SORT_DESC],
                'attributes' => [
                    'session_latest_status' => [
                        'asc' => ['session_latest_status' => SORT_ASC],
                        'desc' => ['session_latest_status' => SORT_DESC],
                    ],
                    'project_name' => [
                        'asc' => ['project.name' => SORT_ASC],
                        'desc' => ['project.name' => SORT_DESC],
                    ],
                    'provider' => [
                        'asc' => ["{$t}.provider" => SORT_ASC],
                        'desc' => ["{$t}.provider" => SORT_DESC],
                    ],
                    'prompt_summary' => [
                        'asc' => ["{$t}.prompt_summary" => SORT_ASC],
                        'desc' => ["{$t}.prompt_summary" => SORT_DESC],
                    ],
                    'session_run_count' => [
                        'asc' => ['session_run_count' => SORT_ASC],
                        'desc' => ['session_run_count' => SORT_DESC],
                    ],
                    'created_at' => [
                        'asc' => ["{$t}.created_at" => SORT_ASC],
                        'desc' => ["{$t}.created_at" => SORT_DESC],
                    ],
                    'session_total_duration' => [
                        'asc' => ['session_total_duration' => SORT_ASC],
                        'desc' => ['session_total_duration' => SORT_DESC],
                    ],
                    'session_last_activity' => [
                        'asc' => ['session_last_activity' => SORT_ASC],
                        'desc' => ['session_last_activity' => SORT_DESC],
                    ],
                ],
            ],
        ]);

        if ($this->hasErrors()) {
            return $dataProvider;
        }

        if ($this->status !== null && $this->status !== '') {
            $query->hasLatestSessionStatus($this->status);
        }

        if ($this->q !== null && $this->q !== '') {
            $query->searchByTerm($this->q);
        }

        return $dataProvider;
    }
}
