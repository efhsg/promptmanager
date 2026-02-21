<?php

namespace app\models\query;

use app\models\AiRun;
use common\enums\AiRunStatus;
use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * @extends ActiveQuery<AiRun>
 */
class AiRunQuery extends ActiveQuery
{
    public function forUser(int $userId): self
    {
        return $this->andWhere([AiRun::tableName() . '.user_id' => $userId]);
    }

    public function forProject(int $projectId): self
    {
        return $this->andWhere([AiRun::tableName() . '.project_id' => $projectId]);
    }

    public function active(): self
    {
        return $this->andWhere([AiRun::tableName() . '.status' => AiRunStatus::activeValues()]);
    }

    public function terminal(): self
    {
        return $this->andWhere([AiRun::tableName() . '.status' => AiRunStatus::terminalValues()]);
    }

    public function withStatus(AiRunStatus $status): self
    {
        return $this->andWhere([AiRun::tableName() . '.status' => $status->value]);
    }

    public function forSession(string $sessionId): self
    {
        return $this->andWhere([AiRun::tableName() . '.session_id' => $sessionId]);
    }

    public function stale(int $thresholdMinutes = 5): self
    {
        return $this->withStatus(AiRunStatus::RUNNING)
            ->andWhere(['<', AiRun::tableName() . '.updated_at', date('Y-m-d H:i:s', time() - $thresholdMinutes * 60)]);
    }

    public function createdBefore(string $datetime): self
    {
        return $this->andWhere(['<', AiRun::tableName() . '.created_at', $datetime]);
    }

    public function orderedByCreated(): self
    {
        return $this->orderBy([AiRun::tableName() . '.created_at' => SORT_DESC]);
    }

    public function orderedByCreatedAsc(): self
    {
        return $this->orderBy([AiRun::tableName() . '.created_at' => SORT_ASC]);
    }

    // ---------------------------------------------------------------
    // Session grouping
    // ---------------------------------------------------------------

    /**
     * Returns only the first run per session (representative) plus standalone runs.
     */
    public function sessionRepresentatives(): self
    {
        $t = AiRun::tableName();

        return $this->andWhere([
            'or',
            ["{$t}.session_id" => null],
            ["{$t}.id" => new Expression("(SELECT MIN(r0.id) FROM {$t} r0 WHERE r0.session_id = {$t}.session_id)")],
        ]);
    }

    /**
     * Adds aggregated session columns via subqueries.
     */
    public function withSessionAggregates(): self
    {
        $t = AiRun::tableName();

        return $this->addSelect(["{$t}.*"])
            ->addSelect([
                'session_run_count' => new Expression(
                    "CASE WHEN {$t}.session_id IS NULL THEN 1"
                    . " ELSE (SELECT COUNT(*) FROM {$t} r1 WHERE r1.session_id = {$t}.session_id) END"
                ),
                'session_latest_status' => new Expression(
                    "CASE WHEN {$t}.session_id IS NULL THEN {$t}.status"
                    . " ELSE (SELECT r2.status FROM {$t} r2 WHERE r2.session_id = {$t}.session_id ORDER BY r2.created_at DESC LIMIT 1) END"
                ),
                'session_last_activity' => new Expression(
                    "CASE WHEN {$t}.session_id IS NULL THEN {$t}.created_at"
                    . " ELSE (SELECT MAX(r3.created_at) FROM {$t} r3 WHERE r3.session_id = {$t}.session_id) END"
                ),
                'session_total_cost' => new Expression(
                    "CASE WHEN {$t}.session_id IS NULL THEN"
                    . " COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$t}.result_metadata, '$.total_cost_usd')), 0)"
                    . " ELSE (SELECT SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(r4.result_metadata, '$.total_cost_usd')), 0))"
                    . " FROM {$t} r4 WHERE r4.session_id = {$t}.session_id) END"
                ),
                'session_total_duration' => new Expression(
                    "CASE WHEN {$t}.session_id IS NULL THEN"
                    . " CASE WHEN {$t}.started_at IS NOT NULL AND {$t}.completed_at IS NOT NULL"
                    . " THEN TIMESTAMPDIFF(SECOND, {$t}.started_at, {$t}.completed_at) ELSE NULL END"
                    . " ELSE (SELECT SUM(CASE WHEN r5.started_at IS NOT NULL AND r5.completed_at IS NOT NULL"
                    . " THEN TIMESTAMPDIFF(SECOND, r5.started_at, r5.completed_at) ELSE 0 END)"
                    . " FROM {$t} r5 WHERE r5.session_id = {$t}.session_id) END"
                ),
                'session_last_run_id' => new Expression(
                    "CASE WHEN {$t}.session_id IS NULL THEN {$t}.id"
                    . " ELSE (SELECT MAX(r6a.id) FROM {$t} r6a WHERE r6a.session_id = {$t}.session_id) END"
                ),
                'session_summary_latest' => new Expression(
                    "CASE WHEN {$t}.session_id IS NULL THEN {$t}.session_summary"
                    . " ELSE (SELECT r7.session_summary FROM {$t} r7 WHERE r7.session_id = {$t}.session_id"
                    . " AND r7.session_summary IS NOT NULL ORDER BY r7.created_at DESC LIMIT 1) END"
                ),
            ]);
    }

    /**
     * Searches across all runs in each session for the given term.
     * Matches on prompt_markdown, prompt_summary or session_summary of any run within the session.
     */
    public function searchByTerm(string $term): self
    {
        $t = AiRun::tableName();
        $likeTerm = '%' . addcslashes($term, '%_\\') . '%';
        $matchClause = "r_s.prompt_markdown LIKE :searchTerm"
            . " OR r_s.prompt_summary LIKE :searchTerm"
            . " OR r_s.session_summary LIKE :searchTerm";

        return $this->andWhere([
            'or',
            // Standalone runs (no session): search directly
            ['and',
                ["{$t}.session_id" => null],
                ['or',
                    ['like', "{$t}.prompt_markdown", $term],
                    ['like', "{$t}.prompt_summary", $term],
                    ['like', "{$t}.session_summary", $term],
                ],
            ],
            // Session runs: search across ALL runs in the session
            ['and',
                ['IS NOT', "{$t}.session_id", null],
                new Expression(
                    "EXISTS (SELECT 1 FROM {$t} r_s WHERE r_s.session_id = {$t}.session_id"
                    . " AND ({$matchClause}))",
                    [':searchTerm' => $likeTerm]
                ),
            ],
        ]);
    }

    /**
     * Filters sessions where the latest run has the given status.
     */
    public function hasLatestSessionStatus(string $status): self
    {
        $t = AiRun::tableName();

        return $this->andWhere([
            'or',
            ['and', ["{$t}.session_id" => null], ["{$t}.status" => $status]],
            ['and',
                ['IS NOT', "{$t}.session_id", null],
                new Expression(
                    "(SELECT r6.status FROM {$t} r6 WHERE r6.session_id = {$t}.session_id"
                    . " ORDER BY r6.created_at DESC LIMIT 1) = :latestStatus",
                    [':latestStatus' => $status]
                ),
            ],
        ]);
    }
}
