<?php

namespace app\models;

use common\enums\ClaudeRunStatus;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * ClaudeRunSearch represents the model behind the search/filter form for ClaudeRun.
 */
class ClaudeRunSearch extends ClaudeRun
{
    public ?string $q = null;

    public function init(): void
    {
        parent::init();
        $this->status = null;
    }

    public function rules(): array
    {
        return [
            [['q', 'status'], 'safe'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    public function search(array $params, int $userId): ActiveDataProvider
    {
        $query = ClaudeRun::find()
            ->forUser($userId)
            ->sessionRepresentatives()
            ->withSessionAggregates()
            ->joinWith(['project']);

        $query->orderBy(['session_last_activity' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        if ($this->status !== null && $this->status !== '') {
            $query->hasLatestSessionStatus($this->status);
        }

        if ($this->q !== null && $this->q !== '') {
            $t = ClaudeRun::tableName();
            $query->andWhere([
                'or',
                ['like', "{$t}.prompt_summary", $this->q],
                ['like', "{$t}.session_summary", $this->q],
            ]);
        }

        return $dataProvider;
    }
}
