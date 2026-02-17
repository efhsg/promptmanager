<?php

namespace app\models;

use app\models\query\AiRunQuery;
use app\models\traits\TimestampTrait;
use app\modules\identity\models\User;
use common\enums\AiRunStatus;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Model for the ai_run table.
 *
 * @property int $id
 * @property int $user_id
 * @property int $project_id
 * @property string|null $session_id
 * @property string $status
 * @property string $prompt_markdown
 * @property string|null $prompt_summary
 * @property string|null $session_summary
 * @property string|null $options JSON
 * @property string|null $working_directory
 * @property string|null $stream_log NDJSON
 * @property string|null $result_text
 * @property string|null $result_metadata JSON
 * @property string|null $error_message
 * @property int|null $pid
 * @property string|null $started_at
 * @property string|null $completed_at
 * @property string $created_at
 * @property string $updated_at
 *
 * @property User $user
 * @property Project $project
 */
class AiRun extends ActiveRecord
{
    use TimestampTrait;

    public const MAX_CONCURRENT_RUNS = 3;

    /** @var int|string|null Virtual column from withSessionAggregates() */
    public int|string|null $session_run_count = null;

    /** @var string|null Virtual column from withSessionAggregates() */
    public ?string $session_latest_status = null;

    /** @var string|null Virtual column from withSessionAggregates() */
    public ?string $session_last_activity = null;

    /** @var float|string|null Virtual column from withSessionAggregates() */
    public float|string|null $session_total_cost = null;

    /** @var int|string|null Virtual column from withSessionAggregates() */
    public int|string|null $session_total_duration = null;

    /** @var int|string|null Virtual column from withSessionAggregates() */
    public int|string|null $session_last_run_id = null;

    /** @var string|null Virtual column from withSessionAggregates() */
    public ?string $session_summary_latest = null;

    public static function tableName(): string
    {
        return '{{%ai_run}}';
    }

    public static function find(): AiRunQuery
    {
        return new AiRunQuery(static::class);
    }

    public function rules(): array
    {
        return [
            [['user_id', 'project_id', 'prompt_markdown'], 'required'],
            [['user_id', 'project_id', 'pid'], 'integer'],
            [['prompt_markdown', 'stream_log', 'result_text', 'options', 'result_metadata'], 'string'],
            [['error_message'], 'string'],
            [['session_id'], 'string', 'max' => 191],
            [['prompt_summary', 'session_summary'], 'string', 'max' => 255],
            [['working_directory'], 'string', 'max' => 500],
            [['status'], 'string'],
            [['status'], 'in', 'range' => AiRunStatus::values()],
            [['status'], 'default', 'value' => AiRunStatus::PENDING->value],
            [['started_at', 'completed_at', 'created_at', 'updated_at'], 'string'],
            [
                ['user_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => User::class,
                'targetAttribute' => ['user_id' => 'id'],
            ],
            [
                ['project_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Project::class,
                'targetAttribute' => ['project_id' => 'id'],
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_id' => 'User',
            'project_id' => 'Project',
            'session_id' => 'Session',
            'status' => 'Status',
            'prompt_markdown' => 'Prompt',
            'prompt_summary' => 'Prompt Summary',
            'session_summary' => 'Session Summary',
            'started_at' => 'Started',
            'completed_at' => 'Completed',
            'created_at' => 'Created',
            'updated_at' => 'Updated',
        ];
    }

    public function init(): void
    {
        parent::init();

        if ($this->isNewRecord && $this->status === null) {
            $this->status = AiRunStatus::PENDING->value;
        }
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->handleTimestamps($insert);

        return true;
    }

    // ---------------------------------------------------------------
    // Relations
    // ---------------------------------------------------------------

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getProject(): ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    // ---------------------------------------------------------------
    // Status helpers
    // ---------------------------------------------------------------

    public function isActive(): bool
    {
        return in_array($this->status, AiRunStatus::activeValues(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, AiRunStatus::terminalValues(), true);
    }

    public function getStatusEnum(): AiRunStatus
    {
        return AiRunStatus::from($this->status);
    }

    // ---------------------------------------------------------------
    // Status transitions
    // ---------------------------------------------------------------

    /**
     * Atomically claim this run for processing. Returns true if this worker won the race.
     */
    public function claimForProcessing(int $pid): bool
    {
        $affected = static::updateAll(
            [
                'status' => AiRunStatus::RUNNING->value,
                'pid' => $pid,
                'started_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            ['id' => $this->id, 'status' => AiRunStatus::PENDING->value]
        );

        if ($affected === 1) {
            $this->refresh();
            return true;
        }

        return false;
    }

    public function markRunning(int $pid): void
    {
        $this->status = AiRunStatus::RUNNING->value;
        $this->pid = $pid;
        $this->started_at = date('Y-m-d H:i:s');
        $this->save(false);
    }

    public function markCompleted(string $resultText, ?array $metadata = null, ?string $streamLog = null): void
    {
        $this->status = AiRunStatus::COMPLETED->value;
        $this->result_text = $resultText;
        $this->result_metadata = $metadata !== null ? json_encode($metadata) : null;
        $this->stream_log = $streamLog;
        $this->pid = null;
        $this->completed_at = date('Y-m-d H:i:s');
        $this->save(false);
    }

    public function markFailed(string $errorMessage, ?string $streamLog = null): void
    {
        $this->status = AiRunStatus::FAILED->value;
        $this->error_message = $errorMessage;
        $this->stream_log = $streamLog;
        $this->pid = null;
        $this->completed_at = date('Y-m-d H:i:s');
        $this->save(false);
    }

    public function markCancelled(?string $streamLog = null): void
    {
        $this->status = AiRunStatus::CANCELLED->value;
        $this->stream_log = $streamLog;
        $this->pid = null;
        $this->completed_at = date('Y-m-d H:i:s');
        $this->save(false);
    }

    /**
     * Updates the heartbeat timestamp (used by stale-run detector).
     */
    public function heartbeat(): void
    {
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save(false, ['updated_at']);
    }

    /**
     * Updates the session_id if it was discovered during execution.
     */
    public function setSessionIdFromResult(string $sessionId): void
    {
        if ($this->session_id === null) {
            $this->session_id = $sessionId;
            $this->save(false, ['session_id']);
        }
    }

    // ---------------------------------------------------------------
    // Display helpers
    // ---------------------------------------------------------------

    public function getStatusBadgeClass(): string
    {
        return $this->getStatusEnum()->badgeClass();
    }

    public function getDuration(): ?int
    {
        if ($this->started_at === null || $this->completed_at === null) {
            return null;
        }

        return strtotime($this->completed_at) - strtotime($this->started_at);
    }

    public function getFormattedDuration(): string
    {
        $seconds = $this->getDuration();
        if ($seconds === null) {
            return '-';
        }

        if ($seconds < 60) {
            return $seconds . 's';
        }

        return intdiv($seconds, 60) . 'm ' . ($seconds % 60) . 's';
    }

    public function getCostUsd(): ?float
    {
        $meta = $this->getDecodedResultMetadata();

        return isset($meta['total_cost_usd']) ? (float) $meta['total_cost_usd'] : null;
    }

    // ---------------------------------------------------------------
    // Session display helpers (virtual attributes from withSessionAggregates)
    // ---------------------------------------------------------------

    public function getSessionRunCount(): int
    {
        return (int) ($this->session_run_count ?? 1);
    }

    public function getSessionLatestStatus(): string
    {
        return $this->session_latest_status ?? $this->status;
    }

    public function getSessionLatestStatusEnum(): AiRunStatus
    {
        return AiRunStatus::from($this->getSessionLatestStatus());
    }

    public function getSessionStatusBadgeClass(): string
    {
        return $this->getSessionLatestStatusEnum()->badgeClass();
    }

    public function getSessionTotalCost(): ?float
    {
        return $this->session_total_cost !== null ? (float) $this->session_total_cost : null;
    }

    public function getSessionLastRunId(): int
    {
        return (int) ($this->session_last_run_id ?? $this->id);
    }

    public function getSessionTotalDuration(): ?int
    {
        return $this->session_total_duration !== null ? (int) $this->session_total_duration : null;
    }

    /**
     * Returns the best display summary for the runs overview.
     * Prefers the AI-generated session summary (from the latest run via withSessionAggregates),
     * falling back to the own session_summary, then prompt_summary.
     */
    public function getDisplaySummary(): string
    {
        return $this->session_summary_latest
            ?? $this->session_summary
            ?? $this->prompt_summary
            ?? '-';
    }

    public function getFormattedSessionDuration(): string
    {
        $seconds = $this->getSessionTotalDuration();
        if ($seconds === null) {
            return '-';
        }

        if ($seconds < 60) {
            return $seconds . 's';
        }

        return intdiv($seconds, 60) . 'm ' . ($seconds % 60) . 's';
    }

    // ---------------------------------------------------------------
    // Stream file
    // ---------------------------------------------------------------

    public function getStreamFilePath(): string
    {
        return Yii::getAlias('@app/storage/ai-runs/' . $this->id . '.ndjson');
    }

    // ---------------------------------------------------------------
    // Decoded accessors
    // ---------------------------------------------------------------

    public function getDecodedOptions(): array
    {
        if ($this->options === null) {
            return [];
        }

        return json_decode($this->options, true) ?: [];
    }

    public function getDecodedResultMetadata(): array
    {
        if ($this->result_metadata === null) {
            return [];
        }

        return json_decode($this->result_metadata, true) ?: [];
    }
}
