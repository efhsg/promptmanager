<?php

namespace app\jobs;

use app\handlers\AiQuickHandler;
use app\models\AiRun;
use app\services\ai\AiConfigProviderInterface;
use app\services\ai\AiProviderInterface;
use app\services\ai\AiProviderRegistry;
use app\services\ai\AiStreamingProviderInterface;
use app\services\ai\PromptCommandSubstituter;
use app\services\PathService;
use common\enums\AiRunStatus;
use common\enums\LogCategory;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Yii;
use yii\queue\RetryableJobInterface;

/**
 * Background job that executes an AI CLI inference run.
 *
 * Writes streaming output to a NDJSON file on disk. The SSE relay endpoint
 * reads this file to forward events to the browser.
 */
class RunAiJob implements RetryableJobInterface
{
    public int $runId;

    /**
     * @throws RuntimeException
     */
    public function execute($queue): void
    {
        $run = AiRun::findOne($this->runId);

        if ($run === null) {
            Yii::warning("RunAiJob: run {$this->runId} not found, skipping", LogCategory::AI->value);
            return;
        }

        if ($run->status !== AiRunStatus::PENDING->value) {
            Yii::warning("RunAiJob: run {$this->runId} is not pending (status={$run->status}), skipping", LogCategory::AI->value);
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

        try {
            $provider = $this->resolveProvider($run);
        } catch (InvalidArgumentException $e) {
            $run->markFailed("Provider '{$run->provider}' is not configured");
            $this->writeDoneMarker($streamFile, $streamFilePath);
            if (is_resource($streamFile)) {
                fclose($streamFile);
            }
            return;
        }

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
                if ($run->status === AiRunStatus::CANCELLED->value) {
                    throw new RuntimeException('Run cancelled by user');
                }
            }
        };

        $doneWritten = false;

        try {
            // Claim the run atomically
            if (!$run->claimForProcessing(getmypid())) {
                Yii::warning("RunAiJob: run {$this->runId} already claimed by another worker", LogCategory::AI->value);
                return;
            }

            $prompt = $run->prompt_markdown;

            if ($provider instanceof AiConfigProviderInterface && !$provider->supportsSlashCommands()) {
                $commandContents = $this->loadCommandContents($run);
                if ($commandContents !== []) {
                    $substituter = Yii::$container->get(PromptCommandSubstituter::class);
                    $prompt = $substituter->substitute($prompt, $commandContents);
                }
            }

            if ($provider instanceof AiStreamingProviderInterface) {
                $result = $provider->executeStreaming(
                    $prompt,
                    $run->working_directory ?? '',
                    $onLine,
                    3600,
                    $options,
                    $project,
                    $run->session_id,
                    null
                );

                // Read the full stream log for DB storage (file may have been
                // removed by a concurrent cleanup while the run was in progress)
                $streamLog = @file_get_contents($streamFilePath) ?: null;

                // Delegate result extraction to provider
                $parsed = $provider->parseStreamResult($streamLog);
                if ($run->session_id === null && $parsed['session_id'] !== null) {
                    $run->setSessionIdFromResult($parsed['session_id']);
                }

                $this->writeDoneMarker($streamFile, $streamFilePath);
                $doneWritten = true;

                if ($result['exitCode'] === 0) {
                    $run->markCompleted($parsed['text'], $parsed['metadata'], $streamLog);
                    $this->generateSessionSummary($run);
                } else {
                    $errorMessage = $result['error']
                        ?: ($parsed['error'] ?? null)
                        ?: 'AI CLI exited with code ' . $result['exitCode'];
                    $run->markFailed($errorMessage, $streamLog);
                }
            } else {
                // Sync fallback for non-streaming providers
                $syncResult = $provider->execute(
                    $prompt,
                    $run->working_directory ?? '',
                    3600,
                    $options,
                    $project,
                    $run->session_id
                );

                // Write sync result event for SSE relay
                $syncEvent = json_encode([
                    'type' => 'sync_result',
                    'text' => $syncResult['output'] ?? '',
                ]);
                fwrite($streamFile, $syncEvent . "\n");
                fflush($streamFile);

                $streamLog = @file_get_contents($streamFilePath) ?: null;

                if ($run->session_id === null && !empty($syncResult['session_id'])) {
                    $run->setSessionIdFromResult($syncResult['session_id']);
                }

                $this->writeDoneMarker($streamFile, $streamFilePath);
                $doneWritten = true;

                if ($syncResult['exitCode'] === 0) {
                    $metadata = array_filter([
                        'duration_ms' => $syncResult['duration_ms'] ?? null,
                        'num_turns' => $syncResult['num_turns'] ?? null,
                        'modelUsage' => $syncResult['modelUsage'] ?? null,
                    ], fn($v) => $v !== null);
                    $run->markCompleted($syncResult['output'] ?? '', $metadata, $streamLog);
                    $this->generateSessionSummary($run);
                } else {
                    $errorMessage = $syncResult['error'] ?: 'AI CLI exited with code ' . $syncResult['exitCode'];
                    $run->markFailed($errorMessage, $streamLog);
                }
            }
        } catch (Throwable $e) {
            if (!$doneWritten) {
                $this->writeDoneMarker($streamFile, $streamFilePath);
            }
            $streamLog = @file_get_contents($streamFilePath) ?: null;

            if ($run->status === AiRunStatus::CANCELLED->value) {
                $run->markCancelled($streamLog);
            } else {
                $run->markFailed($e->getMessage(), $streamLog);
            }
        } finally {
            if (is_resource($streamFile)) {
                fclose($streamFile);
            }
        }
    }

    public function getTtr(): int
    {
        return 3900; // 65 min (> max AI run timeout 3600s)
    }

    public function canRetry($attempt, $error): bool
    {
        return false; // Inference is not idempotent
    }

    /**
     * Closes the stream file handle and appends the [DONE] marker.
     *
     * Must be called BEFORE marking the run as terminal so the relay sees
     * [DONE] in the file before it detects the terminal DB status.
     *
     * @param resource|null $streamFile Open file handle (closed by this method)
     */
    private function writeDoneMarker(&$streamFile, string $streamFilePath): void
    {
        if (is_resource($streamFile)) {
            fclose($streamFile);
            $streamFile = null;
        }

        $doneFile = @fopen($streamFilePath, 'ab');
        if ($doneFile !== false) {
            fwrite($doneFile, "[DONE]\n");
            fclose($doneFile);
        } else {
            Yii::warning("Could not write [DONE] marker to stream file: {$streamFilePath}", LogCategory::AI->value);
        }
    }

    /**
     * Generates an AI summary of what the run accomplished and stores it on the run.
     * Best-effort: failures are logged but do not affect the run's terminal state.
     */
    private function generateSessionSummary(AiRun $run): void
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
                LogCategory::AI->value
            );
        }
    }

    /**
     * Builds a chronological dialog of all runs in the session, truncated to fit maxChars.
     */
    private function buildSessionDialog(AiRun $run): string
    {
        $maxChars = 5000;

        if ($run->session_id !== null) {
            $runs = AiRun::find()
                ->forUser($run->user_id)
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

    /**
     * @throws InvalidArgumentException When provider is not configured
     */
    protected function resolveProvider(AiRun $run): AiProviderInterface
    {
        $registry = Yii::$container->get(AiProviderRegistry::class);
        return $registry->get($run->provider);
    }

    /**
     * Loads command file contents from the provider that supports slash commands.
     *
     * @return array<string, string> Command name => file content
     */
    protected function loadCommandContents(AiRun $run): array
    {
        $workDir = $run->working_directory ?? '';
        if ($workDir === '') {
            return [];
        }

        $registry = Yii::$container->get(AiProviderRegistry::class);

        $commandNames = [];
        foreach ($registry->all() as $candidate) {
            if ($candidate instanceof AiConfigProviderInterface && $candidate->supportsSlashCommands()) {
                $commandNames = array_keys($candidate->loadCommands($workDir));
                break;
            }
        }

        if ($commandNames === []) {
            return [];
        }

        $pathService = Yii::$container->get(PathService::class);
        $containerPath = $pathService->translatePath($workDir);
        $commandsDir = rtrim($containerPath, '/') . '/.claude/commands';

        $contents = [];
        foreach ($commandNames as $name) {
            $filePath = $commandsDir . '/' . $name . '.md';
            if (is_file($filePath)) {
                $content = file_get_contents($filePath);
                if ($content !== false) {
                    $contents[$name] = $content;
                }
            }
        }

        return $contents;
    }

    protected function createQuickHandler(): AiQuickHandler
    {
        return Yii::$container->get(AiQuickHandler::class);
    }
}
