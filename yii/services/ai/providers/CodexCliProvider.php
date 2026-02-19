<?php

namespace app\services\ai\providers;

use app\models\Project;
use app\services\ai\AiConfigProviderInterface;
use app\services\ai\AiProviderInterface;
use app\services\ai\AiStreamingProviderInterface;
use app\services\PathService;
use common\enums\LogCategory;
use RuntimeException;
use Yii;

/**
 * Codex CLI provider implementing core AI provider interfaces.
 *
 * Executes prompts via the OpenAI Codex CLI (`codex`) and parses its
 * NDJSON streaming output into the standardised result format.
 */
class CodexCliProvider implements
    AiProviderInterface,
    AiStreamingProviderInterface,
    AiConfigProviderInterface
{
    private readonly PathService $pathService;
    /** @var array<string, string> Model value => display label */
    private readonly array $models;

    /**
     * @param array<string, string> $models Model value => display label
     */
    public function __construct(PathService $pathService, array $models = [])
    {
        $this->pathService = $pathService;
        $this->models = $models;
    }

    // ──────────────────────────────────────────────────────────
    // AiProviderInterface
    // ──────────────────────────────────────────────────────────

    public function execute(
        string $prompt,
        string $workDir,
        int $timeout = 3600,
        array $options = [],
        ?Project $project = null,
        ?string $sessionId = null
    ): array {
        $containerPath = $this->resolveWorkingDirectory($workDir);

        if (!is_dir($containerPath)) {
            return [
                'success' => false,
                'output' => '',
                'error' => 'Working directory does not exist: ' . $workDir,
                'exitCode' => 1,
            ];
        }

        $command = $this->buildCommand($options, $sessionId);

        Yii::debug("CodexCliProvider::execute cmd={$command} cwd={$containerPath}", LogCategory::AI->value);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $containerPath);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'output' => '',
                'error' => 'Failed to start Codex CLI process',
                'exitCode' => 1,
            ];
        }

        fwrite($pipes[0], $prompt);
        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $error = '';
        $startTime = time();
        $exitCode = -1;

        while (true) {
            $status = proc_get_status($process);

            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                $output .= stream_get_contents($pipes[1]);
                $error .= stream_get_contents($pipes[2]);
                break;
            }

            if ((time() - $startTime) > $timeout) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return [
                    'success' => false,
                    'output' => $output,
                    'error' => 'Command timed out after ' . $timeout . ' seconds',
                    'exitCode' => 124,
                ];
            }

            $output .= fread($pipes[1], 8192);
            $error .= fread($pipes[2], 8192);

            usleep(100000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $rawOutput = trim($output) !== '' ? trim($output) : trim($error);
        $effectiveError = ($rawOutput === trim($error)) ? '' : trim($error);

        return [
            'success' => $exitCode === 0,
            'output' => $rawOutput,
            'error' => $effectiveError,
            'exitCode' => $exitCode,
            'requestedPath' => $workDir,
            'effectivePath' => $containerPath,
            'usedFallback' => false,
        ];
    }

    public function cancelProcess(string $streamToken): bool
    {
        if (!function_exists('posix_kill')) {
            return false;
        }

        $key = 'ai_codex_pid_' . Yii::$app->user->id . '_' . $streamToken;
        $pid = Yii::$app->cache->get($key);

        if ($pid === false) {
            return false;
        }

        Yii::$app->cache->delete($key);

        if (!posix_kill($pid, 15)) {
            return false;
        }

        usleep(200000);
        posix_kill($pid, 9);

        return true;
    }

    public function getName(): string
    {
        return 'Codex';
    }

    public function getIdentifier(): string
    {
        return 'codex';
    }

    // ──────────────────────────────────────────────────────────
    // AiStreamingProviderInterface
    // ──────────────────────────────────────────────────────────

    public function executeStreaming(
        string $prompt,
        string $workDir,
        callable $onLine,
        int $timeout = 3600,
        array $options = [],
        ?Project $project = null,
        ?string $sessionId = null,
        ?string $streamToken = null
    ): array {
        $containerPath = $this->resolveWorkingDirectory($workDir);

        if (!is_dir($containerPath)) {
            throw new RuntimeException('Working directory does not exist: ' . $workDir);
        }

        $command = $this->buildCommand($options, $sessionId);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $containerPath);

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start Codex CLI process');
        }

        fwrite($pipes[0], $prompt);
        fclose($pipes[0]);

        $status = proc_get_status($process);
        if ($streamToken !== null) {
            $key = 'ai_codex_pid_' . Yii::$app->user->id . '_' . $streamToken;
            Yii::$app->cache->set($key, $status['pid'], 3900);
        }

        stream_set_blocking($pipes[1], true);
        stream_set_timeout($pipes[1], 30);
        stream_set_blocking($pipes[2], false);

        $error = '';
        $startTime = time();
        $cancelled = false;

        try {
            while (true) {
                $line = fgets($pipes[1]);

                if ($line === false) {
                    $meta = stream_get_meta_data($pipes[1]);
                    if ($meta['timed_out']) {
                        if ((time() - $startTime) > $timeout) {
                            proc_terminate($process, 9);
                            fclose($pipes[1]);
                            fclose($pipes[2]);
                            proc_close($process);
                            if ($streamToken !== null) {
                                $this->clearProcessPid($streamToken);
                            }
                            return ['exitCode' => 124, 'error' => 'Command timed out after ' . $timeout . ' seconds'];
                        }

                        $onLine('');
                        continue;
                    }

                    break;
                }

                if ((time() - $startTime) > $timeout) {
                    proc_terminate($process, 9);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);
                    if ($streamToken !== null) {
                        $this->clearProcessPid($streamToken);
                    }
                    return ['exitCode' => 124, 'error' => 'Command timed out after ' . $timeout . ' seconds'];
                }

                if (PHP_SAPI !== 'cli' && connection_aborted()) {
                    proc_terminate($process, 15);
                    usleep(100000);
                    $s = proc_get_status($process);
                    if ($s['running']) {
                        proc_terminate($process, 9);
                    }
                    $cancelled = true;
                    break;
                }

                $line = trim($line);
                if ($line !== '') {
                    $onLine($line);
                }

                $error .= stream_get_contents($pipes[2]) ?: '';
            }
        } catch (RuntimeException $e) {
            proc_terminate($process, 15);
            usleep(100000);
            $s = proc_get_status($process);
            if ($s['running']) {
                proc_terminate($process, 9);
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            if ($streamToken !== null) {
                $this->clearProcessPid($streamToken);
            }
            throw $e;
        }

        $error .= stream_get_contents($pipes[2]) ?: '';

        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_get_status($process);
        $exitCode = $cancelled ? 130 : ($status['running'] ? -1 : $status['exitcode']);
        proc_close($process);
        if ($streamToken !== null) {
            $this->clearProcessPid($streamToken);
        }

        return ['exitCode' => $exitCode, 'error' => trim($error)];
    }

    public function parseStreamResult(?string $streamLog): array
    {
        $default = ['text' => '', 'session_id' => null, 'metadata' => [], 'error' => null];

        if ($streamLog === null || $streamLog === '') {
            return $default;
        }

        $lines = explode("\n", $streamLog);
        $text = '';
        $sessionId = null;
        $metadata = [];
        $errors = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === '[DONE]') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $type = $decoded['type'] ?? null;

            if ($type === 'thread.started' && $sessionId === null) {
                $sessionId = $decoded['thread_id'] ?? null;
            } elseif ($type === 'item.completed') {
                $item = $decoded['item'] ?? [];
                $itemType = $item['type'] ?? '';

                if ($itemType === 'agent_message') {
                    // Flat text field (codex-cli ≥ 0.104)
                    if (isset($item['text']) && is_string($item['text'])) {
                        $text .= ($text !== '' ? "\n" : '') . $item['text'];
                    } else {
                        // Content blocks format (earlier versions)
                        $content = $item['content'] ?? [];
                        foreach ($content as $block) {
                            if (($block['type'] ?? '') === 'text') {
                                $text .= ($text !== '' ? "\n" : '') . ($block['text'] ?? '');
                            }
                        }
                    }
                } elseif ($itemType === 'error' && isset($item['message'])) {
                    $errors[] = $item['message'];
                }
            } elseif ($type === 'error' && isset($decoded['message'])) {
                $errors[] = $decoded['message'];
            } elseif ($type === 'turn.failed') {
                $failedMsg = $decoded['error']['message'] ?? null;
                if ($failedMsg !== null) {
                    $errors[] = $failedMsg;
                }
            } elseif ($type === 'turn.completed') {
                $usage = $decoded['usage'] ?? [];
                if ($usage !== []) {
                    $metadata['input_tokens'] = $usage['input_tokens'] ?? null;
                    $metadata['output_tokens'] = $usage['output_tokens'] ?? null;
                    $metadata['total_tokens'] = $usage['total_tokens'] ?? null;
                }
            }
        }

        // Deduplicate error messages (turn.failed often repeats the error event message)
        $errors = array_unique($errors);
        $errorString = $errors !== [] ? implode('; ', $errors) : null;

        return ['text' => $text, 'session_id' => $sessionId, 'metadata' => $metadata, 'error' => $errorString];
    }

    // ──────────────────────────────────────────────────────────
    // AiConfigProviderInterface
    // ──────────────────────────────────────────────────────────

    public function hasConfig(string $path): array
    {
        $configFiles = ['codex.md', 'CODEX.md', 'AGENTS.md'];
        $hasConfigFile = false;
        foreach ($configFiles as $configFile) {
            if (file_exists($path . '/' . $configFile)) {
                $hasConfigFile = true;
                break;
            }
        }

        $hasCodexDir = is_dir($path . '/.codex');

        return [
            'hasConfigFile' => $hasConfigFile,
            'hasConfigDir' => $hasCodexDir,
            'hasAnyConfig' => $hasConfigFile || $hasCodexDir,
        ];
    }

    public function checkConfig(string $path): array
    {
        $containerPath = $this->pathService->translatePath($path);
        $pathMapped = ($containerPath !== $path);

        $base = [
            'hasConfigFile' => false,
            'hasConfigDir' => false,
            'hasAnyConfig' => false,
            'configFileName' => 'codex.md/CODEX.md/AGENTS.md',
            'configDirName' => '.codex/',
            'pathMapped' => $pathMapped,
            'requestedPath' => $path,
            'effectivePath' => $containerPath,
        ];

        if (!is_dir($containerPath)) {
            $base['pathStatus'] = $pathMapped ? 'not_accessible' : 'not_mapped';
            return $base;
        }

        $config = $this->hasConfig($containerPath);

        return array_merge($base, [
            'hasConfigFile' => $config['hasConfigFile'],
            'hasConfigDir' => $config['hasConfigDir'],
            'hasAnyConfig' => $config['hasAnyConfig'],
            'pathStatus' => $config['hasAnyConfig'] ? 'has_config' : 'no_config',
        ]);
    }

    public function loadCommands(string $directory): array
    {
        // Codex CLI does not support slash commands
        return [];
    }

    public function getSupportedPermissionModes(): array
    {
        // Codex uses approval-mode instead of permission-mode
        return [];
    }

    public function getSupportedModels(): array
    {
        return $this->models;
    }

    public function supportsSlashCommands(): bool
    {
        return false;
    }

    public function getConfigSchema(): array
    {
        return [
            'reasoning' => [
                'type' => 'select',
                'label' => 'Reasoning Level',
                'options' => [
                    'low' => 'Low — fast, lighter reasoning',
                    'medium' => 'Medium — balanced (default)',
                    'high' => 'High — deeper reasoning',
                    'extra_high' => 'Extra high — maximum reasoning',
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────

    private function resolveWorkingDirectory(string $workDir): string
    {
        if ($workDir === '') {
            return '/tmp';
        }

        return $this->pathService->translatePath($workDir);
    }

    private function buildCommand(array $options, ?string $sessionId = null): string
    {
        $cmd = 'codex exec';

        if ($sessionId !== null) {
            $cmd .= ' resume ' . escapeshellarg($sessionId);
        }

        $cmd .= ' --json --skip-git-repo-check --dangerously-bypass-approvals-and-sandbox';

        if (!empty($options['model'])) {
            $cmd .= ' --model ' . escapeshellarg($options['model']);
        }

        if (!empty($options['reasoning'])) {
            $level = str_replace('_', '-', $options['reasoning']);
            $cmd .= ' -c ' . escapeshellarg('reasoning=' . $level);
        }

        $cmd .= ' -';

        return $cmd;
    }

    private function clearProcessPid(string $streamToken): void
    {
        $key = 'ai_codex_pid_' . Yii::$app->user->id . '_' . $streamToken;
        Yii::$app->cache->delete($key);
    }
}
