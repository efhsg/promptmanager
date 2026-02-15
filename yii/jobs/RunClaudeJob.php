<?php

namespace app\jobs;

use app\handlers\ClaudeQuickHandler;
use app\models\ClaudeRun;
use app\services\ClaudeCliService;
use common\enums\ClaudeRunStatus;
use RuntimeException;
use Throwable;
use Yii;
use yii\queue\RetryableJobInterface;

/**
 * Background job that executes a Claude CLI inference run.
 *
 * Writes streaming output to a NDJSON file on disk. The SSE relay endpoint
 * reads this file to forward events to the browser.
 */
class RunClaudeJob implements RetryableJobInterface
{
    public int $runId;

    /**
     * @throws RuntimeException
     */
    public function execute($queue): void
    {
        $run = ClaudeRun::findOne($this->runId);

        if ($run === null) {
            Yii::warning("RunClaudeJob: run {$this->runId} not found, skipping", __METHOD__);
            return;
        }

        if ($run->status !== ClaudeRunStatus::PENDING->value) {
            Yii::warning("RunClaudeJob: run {$this->runId} is not pending (status={$run->status}), skipping", __METHOD__);
            return;
        }

        $streamFilePath = $run->getStreamFilePath();
        $streamDir = dirname($streamFilePath);
        if (!is_dir($streamDir)) {
            mkdir($streamDir, 0o775, true);
        }

        $streamFile = fopen($streamFilePath, 'wb');
        if ($streamFile === false) {
            $run->markFailed('Could not open stream file for writing: ' . $streamFilePath);
            return;
        }

        $cliService = $this->createCliService();
        $options = $run->getDecodedOptions();
        $project = $run->project;

        // Write stream events to file and update heartbeat
        $lastHeartbeat = time();
        $onLine = function (string $line) use ($streamFile, $run, &$lastHeartbeat): void {
            // Empty lines are heartbeat-only signals (read timeout during extended thinking)
            if ($line !== '') {
                fwrite($streamFile, $line . "\n");
                fflush($streamFile);
            }

            // Heartbeat every 30 seconds
            if (time() - $lastHeartbeat >= 30) {
                $run->heartbeat();
                $lastHeartbeat = time();

                // Check for cancellation
                $run->refresh();
                if ($run->status === ClaudeRunStatus::CANCELLED->value) {
                    throw new RuntimeException('Run cancelled by user');
                }
            }
        };

        try {
            // Claim the run atomically
            if (!$run->claimForProcessing(getmypid())) {
                Yii::warning("RunClaudeJob: run {$this->runId} already claimed by another worker", __METHOD__);
                return;
            }

            $result = $cliService->executeStreaming(
                $run->prompt_markdown,
                $run->working_directory ?? '',
                $onLine,
                3600,
                $options,
                $project,
                $run->session_id,
                null
            );

            // Read the full stream log for DB storage
            $streamLog = file_get_contents($streamFilePath) ?: null;

            // Extract session_id from stream log
            $this->extractSessionId($run, $streamLog);

            if ($result['exitCode'] === 0) {
                $resultText = $this->extractResultText($streamLog);
                $metadata = $this->extractMetadata($streamLog, $result);
                $run->markCompleted($resultText, $metadata, $streamLog);
                $this->generateSessionSummary($run);
            } else {
                $errorMessage = $result['error'] ?: 'Claude CLI exited with code ' . $result['exitCode'];
                $run->markFailed($errorMessage, $streamLog);
            }
        } catch (RuntimeException $e) {
            $streamLog = file_get_contents($streamFilePath) ?: null;

            if ($run->status === ClaudeRunStatus::CANCELLED->value) {
                $run->markCancelled($streamLog);
            } else {
                $run->markFailed($e->getMessage(), $streamLog);
            }
        } finally {
            fclose($streamFile);

            // Write [DONE] marker to stream file so relay knows it's finished
            $doneFile = fopen($streamFilePath, 'ab');
            if ($doneFile !== false) {
                fwrite($doneFile, "[DONE]\n");
                fclose($doneFile);
            }
        }
    }

    public function getTtr(): int
    {
        return 3900; // 65 min (> max Claude timeout 3600s)
    }

    public function canRetry($attempt, $error): bool
    {
        return false; // Inference is not idempotent
    }

    /**
     * Extracts the result text from the NDJSON stream log.
     */
    private function extractResultText(?string $streamLog): string
    {
        if ($streamLog === null || $streamLog === '') {
            return '';
        }

        $lines = explode("\n", $streamLog);
        foreach (array_reverse($lines) as $line) {
            $line = trim($line);
            if ($line === '' || $line === '[DONE]') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (($decoded['type'] ?? null) === 'result') {
                return $decoded['result'] ?? '';
            }
        }

        return '';
    }

    /**
     * Extracts metadata from the NDJSON stream log.
     */
    private function extractMetadata(?string $streamLog, array $cliResult): array
    {
        $metadata = [];

        if ($streamLog !== null && $streamLog !== '') {
            $lines = explode("\n", $streamLog);
            foreach (array_reverse($lines) as $line) {
                $line = trim($line);
                if ($line === '' || $line === '[DONE]') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (($decoded['type'] ?? null) === 'result') {
                    $metadata['duration_ms'] = $decoded['duration_ms'] ?? null;
                    $metadata['session_id'] = $decoded['session_id'] ?? null;
                    $metadata['num_turns'] = $decoded['num_turns'] ?? null;
                    $metadata['modelUsage'] = $decoded['modelUsage'] ?? null;
                    break;
                }
            }
        }

        return $metadata;
    }

    /**
     * Extracts the session_id from the stream log and updates the run.
     */
    private function extractSessionId(ClaudeRun $run, ?string $streamLog): void
    {
        if ($streamLog === null || $run->session_id !== null) {
            return;
        }

        $lines = explode("\n", $streamLog);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (($decoded['type'] ?? null) === 'system' && isset($decoded['session_id'])) {
                $run->setSessionIdFromResult($decoded['session_id']);
                return;
            }
            if (($decoded['type'] ?? null) === 'result' && isset($decoded['session_id'])) {
                $run->setSessionIdFromResult($decoded['session_id']);
                return;
            }
        }
    }

    /**
     * Generates an AI summary of what the run accomplished and stores it on the run.
     * Best-effort: failures are logged but do not affect the run's terminal state.
     */
    private function generateSessionSummary(ClaudeRun $run): void
    {
        $input = $this->buildSessionDialog($run);
        if ($input === '') {
            return;
        }

        try {
            $handler = $this->createQuickHandler();
            $result = $handler->run('session-summary', $input);

            if ($result['success'] && !empty($result['output'])) {
                $run->session_summary = mb_substr(trim($result['output']), 0, 255);
                $run->save(false, ['session_summary']);
            }
        } catch (Throwable $e) {
            Yii::warning(
                "Session summary generation failed for run {$run->id}: " . $e->getMessage(),
                __METHOD__
            );
        }
    }

    /**
     * Builds a chronological dialog of all runs in the session, truncated to fit maxChars.
     */
    private function buildSessionDialog(ClaudeRun $run): string
    {
        $maxChars = 5000;

        if ($run->session_id !== null) {
            $runs = ClaudeRun::find()
                ->forSession($run->session_id)
                ->orderedByCreatedAsc()
                ->all();
        } else {
            $runs = [$run];
        }

        $parts = [];
        foreach ($runs as $r) {
            if ($r->prompt_markdown !== null && trim($r->prompt_markdown) !== '') {
                $parts[] = "User: " . trim($r->prompt_markdown);
            }
            if ($r->result_text !== null && trim($r->result_text) !== '') {
                $parts[] = "Assistant: " . trim($r->result_text);
            }
        }

        if ($parts === []) {
            return '';
        }

        $dialog = implode("\n\n", $parts);

        // Truncate from the start to keep the most recent exchanges
        if (mb_strlen($dialog) > $maxChars) {
            $dialog = '...' . mb_substr($dialog, -($maxChars - 3));
        }

        return $dialog;
    }

    protected function createCliService(): ClaudeCliService
    {
        return Yii::$container->get(ClaudeCliService::class);
    }

    protected function createQuickHandler(): ClaudeQuickHandler
    {
        return Yii::$container->get(ClaudeQuickHandler::class);
    }
}
