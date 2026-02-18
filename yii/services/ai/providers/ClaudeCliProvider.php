<?php

namespace app\services\ai\providers;

use app\models\Project;
use app\services\ai\AiConfigProviderInterface;
use app\services\ai\AiProviderInterface;
use app\services\ai\AiStreamingProviderInterface;
use app\services\ai\AiUsageProviderInterface;
use app\services\ai\AiWorkspaceProviderInterface;
use app\services\CopyFormatConverter;
use app\services\PathService;
use common\enums\AiPermissionMode;
use common\enums\CopyType;
use common\enums\LogCategory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use Yii;

/**
 * Claude CLI provider implementing all AI provider interfaces.
 *
 * Composes functionality from ClaudeCliService (execute, streaming, config, usage)
 * and ClaudeWorkspaceService (workspace management) into a single provider.
 */
class ClaudeCliProvider implements
    AiProviderInterface,
    AiStreamingProviderInterface,
    AiWorkspaceProviderInterface,
    AiUsageProviderInterface,
    AiConfigProviderInterface
{
    /** Instructs Claude not to ask interactive questions (no TTY in -p mode). */
    private const NO_INTERACTIVE_QUESTIONS_PROMPT
        = 'Do not ask the user any questions. If you need to make a choice, '
        . 'pick the most pragmatic option and document your assumptions.';

    private const WORKSPACE_BASE = '@app/storage/projects';

    private readonly PathService $pathService;
    private CopyFormatConverter $formatConverter;

    public function __construct(PathService $pathService, ?CopyFormatConverter $formatConverter = null)
    {
        $this->pathService = $pathService;
        $this->formatConverter = $formatConverter ?? new CopyFormatConverter();
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
        $skipWorkspaceResolution = !empty($options['rawWorkingDirectory']);
        $effectiveWorkDir = $skipWorkspaceResolution
            ? $workDir
            : $this->determineWorkingDirectory($workDir, $project);
        $usedFallback = ($effectiveWorkDir !== $workDir);
        $containerPath = $skipWorkspaceResolution
            ? $effectiveWorkDir
            : $this->pathService->translatePath($effectiveWorkDir);

        if (!is_dir($containerPath)) {
            return [
                'success' => false,
                'output' => '',
                'error' => 'Working directory does not exist: ' . $effectiveWorkDir,
                'exitCode' => 1,
            ];
        }

        $configSource = $this->determineConfigSource($effectiveWorkDir, $workDir, $project);
        $command = $this->buildCommand($options, $sessionId);

        Yii::debug("ClaudeCliProvider::execute cmd={$command} cwd={$containerPath}", LogCategory::AI->value);

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
                'error' => 'Failed to start Claude CLI process',
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
                Yii::warning("ClaudeCliProvider timeout after {$timeout}s. stdout so far: " . mb_substr($output, 0, 500) . " | stderr so far: " . mb_substr($error, 0, 500), LogCategory::AI->value);
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

        $outputFormat = $options['outputFormat'] ?? 'stream-json';

        $rawOutput = trim($output) !== '' ? trim($output) : trim($error);

        $parsedOutput = match ($outputFormat) {
            'text' => ['result' => $rawOutput],
            'json' => $this->parseJsonOutput($rawOutput),
            default => $this->parseStreamJsonOutput($rawOutput),
        };

        $effectiveError = ($rawOutput === trim($error)) ? '' : trim($error);

        return [
            'success' => $exitCode === 0 && !($parsedOutput['is_error'] ?? false),
            'output' => $parsedOutput['result'] ?? $rawOutput,
            'error' => $effectiveError,
            'exitCode' => $exitCode,
            'duration_ms' => $parsedOutput['duration_ms'] ?? null,
            'model' => $parsedOutput['model'] ?? null,
            'input_tokens' => $parsedOutput['input_tokens'] ?? null,
            'cache_tokens' => $parsedOutput['cache_tokens'] ?? null,
            'output_tokens' => $parsedOutput['output_tokens'] ?? null,
            'context_window' => $parsedOutput['context_window'] ?? null,
            'num_turns' => $parsedOutput['num_turns'] ?? null,
            'tool_uses' => $parsedOutput['tool_uses'] ?? [],
            'configSource' => $configSource,
            'session_id' => $parsedOutput['session_id'] ?? null,
            'requestedPath' => $workDir,
            'effectivePath' => $effectiveWorkDir,
            'usedFallback' => $usedFallback,
        ];
    }

    public function cancelProcess(string $streamToken): bool
    {
        if (!function_exists('posix_kill')) {
            Yii::warning('posix_kill not available — cannot cancel Claude CLI process', LogCategory::AI->value);
            return false;
        }

        $key = $this->buildPidCacheKey($streamToken);
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
        return 'Claude';
    }

    public function getIdentifier(): string
    {
        return 'claude';
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
        $effectiveWorkDir = $this->determineWorkingDirectory($workDir, $project);
        $containerPath = $this->pathService->translatePath($effectiveWorkDir);

        if (!is_dir($containerPath)) {
            throw new RuntimeException('Working directory does not exist: ' . $effectiveWorkDir);
        }

        $command = $this->buildCommand($options, $sessionId, true);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $containerPath);

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start Claude CLI process');
        }

        fwrite($pipes[0], $prompt);
        fclose($pipes[0]);

        $status = proc_get_status($process);
        if ($streamToken !== null) {
            $this->storeProcessPid($status['pid'], $streamToken);
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
        $default = ['text' => '', 'session_id' => null, 'metadata' => []];

        if ($streamLog === null || $streamLog === '') {
            return $default;
        }

        $lines = explode("\n", $streamLog);
        $text = '';
        $sessionId = null;
        $metadata = [];

        // Extract session_id from first system message
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (($decoded['type'] ?? null) === 'system' && isset($decoded['session_id'])) {
                $sessionId = $decoded['session_id'];
                break;
            }
        }

        // Extract result text and metadata from last result message
        foreach (array_reverse($lines) as $line) {
            $line = trim($line);
            if ($line === '' || $line === '[DONE]') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (($decoded['type'] ?? null) === 'result') {
                $text = $decoded['result'] ?? '';
                $metadata['duration_ms'] = $decoded['duration_ms'] ?? null;
                $metadata['num_turns'] = $decoded['num_turns'] ?? null;
                $metadata['modelUsage'] = $decoded['modelUsage'] ?? null;
                // Prefer session_id from result if not found in system message
                if ($sessionId === null && isset($decoded['session_id'])) {
                    $sessionId = $decoded['session_id'];
                }
                break;
            }
        }

        return ['text' => $text, 'session_id' => $sessionId, 'metadata' => $metadata];
    }

    // ──────────────────────────────────────────────────────────
    // AiWorkspaceProviderInterface
    // ──────────────────────────────────────────────────────────

    public function ensureWorkspace(Project $project): string
    {
        $path = $this->getWorkspacePath($project);

        if (!is_dir($path)) {
            $this->createDirectory($path);
            $this->createDirectory($path . '/.claude');
        }

        return $path;
    }

    public function syncConfig(Project $project): void
    {
        $path = $this->ensureWorkspace($project);

        $claudeMd = $this->generateClaudeMd($project);
        file_put_contents($path . '/CLAUDE.md', $claudeMd);

        $settings = $this->generateSettingsJson($project);
        file_put_contents(
            $path . '/.claude/settings.local.json',
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function deleteWorkspace(Project $project): void
    {
        $path = $this->getWorkspacePath($project);

        if (is_dir($path)) {
            $this->removeDirectory($path);
        }
    }

    public function getWorkspacePath(Project $project): string
    {
        $base = Yii::getAlias(self::WORKSPACE_BASE) . '/' . $project->id;
        $newPath = $base . '/claude';

        if (!is_dir($newPath) && is_dir($base) && file_exists($base . '/CLAUDE.md')) {
            $this->migrateWorkspace($base, $newPath);
        }

        return $newPath;
    }

    public function getDefaultWorkspacePath(): string
    {
        $path = Yii::getAlias(self::WORKSPACE_BASE) . '/default';

        if (!is_dir($path)) {
            $this->createDirectory($path);
            $this->createDirectory($path . '/.claude');

            file_put_contents($path . '/CLAUDE.md', $this->generateDefaultClaudeMd());
            file_put_contents($path . '/.claude/settings.local.json', '{}');
        }

        return $path;
    }

    // ──────────────────────────────────────────────────────────
    // AiUsageProviderInterface
    // ──────────────────────────────────────────────────────────

    public function getUsage(): array
    {
        $credentialsPath = $this->resolveCredentialsPath();
        if ($credentialsPath === null || !is_file($credentialsPath)) {
            return ['success' => false, 'error' => 'Claude credentials file not found'];
        }

        $raw = file_get_contents($credentialsPath);
        if ($raw === false) {
            return ['success' => false, 'error' => 'Could not read credentials file'];
        }

        $credentials = json_decode($raw, true);
        $accessToken = $credentials['claudeAiOauth']['accessToken'] ?? null;
        if ($accessToken === null) {
            return ['success' => false, 'error' => 'No OAuth access token found in credentials'];
        }

        $fetch = $this->fetchSubscriptionUsage($accessToken);
        if (!$fetch['success']) {
            return ['success' => false, 'error' => 'API request failed: ' . $fetch['error']];
        }

        $httpCode = $fetch['status'] ?? 0;
        $response = $fetch['body'] ?? '';
        if ($httpCode !== 200) {
            Yii::warning("Claude usage API returned HTTP {$httpCode}", LogCategory::AI->value);
            return ['success' => false, 'error' => 'API returned HTTP ' . $httpCode];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['success' => false, 'error' => 'Invalid JSON response from API'];
        }

        return [
            'success' => true,
            'data' => $this->normalizeUsageData($data),
        ];
    }

    // ──────────────────────────────────────────────────────────
    // AiConfigProviderInterface
    // ──────────────────────────────────────────────────────────

    public function hasConfig(string $path): array
    {
        $hasCLAUDE_MD = file_exists($path . '/CLAUDE.md');
        $hasClaudeDir = is_dir($path . '/.claude');

        return [
            'hasConfigFile' => $hasCLAUDE_MD,
            'hasConfigDir' => $hasClaudeDir,
            'hasAnyConfig' => $hasCLAUDE_MD || $hasClaudeDir,
            // Claude-specific keys for backwards compatibility
            'hasCLAUDE_MD' => $hasCLAUDE_MD,
            'hasClaudeDir' => $hasClaudeDir,
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
        if (trim($directory) === '') {
            return [];
        }

        $containerPath = $this->pathService->translatePath($directory);
        $commandsDir = rtrim($containerPath, '/') . '/.claude/commands';
        if (!is_dir($commandsDir)) {
            Yii::debug("Claude commands dir not found: '$commandsDir' (root: '$directory', mapped: '$containerPath')", LogCategory::AI->value);
            return [];
        }

        $files = glob($commandsDir . '/*.md');
        if ($files === false) {
            return [];
        }

        $commands = [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $description = $this->parseCommandDescription($file);
            $commands[$name] = $description;
        }

        ksort($commands);

        return $commands;
    }

    public function getSupportedPermissionModes(): array
    {
        return AiPermissionMode::values();
    }

    public function getSupportedModels(): array
    {
        return [
            'sonnet' => 'Sonnet',
            'opus' => 'Opus',
            'haiku' => 'Haiku',
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'allowedTools' => [
                'type' => 'text',
                'label' => 'Allowed Tools',
                'hint' => 'Comma-separated list of tools Claude is allowed to use (e.g. Read,Glob,Grep)',
                'placeholder' => 'Read,Glob,Grep',
            ],
            'disallowedTools' => [
                'type' => 'text',
                'label' => 'Disallowed Tools',
                'hint' => 'Comma-separated list of tools Claude is not allowed to use (e.g. Bash,Write)',
                'placeholder' => 'Bash,Write',
            ],
            'appendSystemPrompt' => [
                'type' => 'textarea',
                'label' => 'Append System Prompt',
                'hint' => 'Additional instructions appended to the system prompt for every run',
                'placeholder' => 'Additional instructions for Claude...',
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Public helpers (Claude-specific, not on interface)
    // ──────────────────────────────────────────────────────────

    /**
     * Converts Quill Delta JSON to markdown.
     */
    public function convertToMarkdown(string $deltaJson): string
    {
        return $this->formatConverter->convertFromQuillDelta($deltaJson, CopyType::MD);
    }

    /**
     * Gets the current git branch for a host path.
     */
    public function getGitBranch(string $hostPath): ?string
    {
        $containerPath = $this->pathService->translatePath($hostPath);

        if (!is_dir($containerPath)) {
            return null;
        }

        $command = 'git -C ' . escapeshellarg($containerPath) . ' rev-parse --abbrev-ref HEAD 2>/dev/null';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || empty($output[0])) {
            return null;
        }

        return trim($output[0]);
    }

    /**
     * Generates CLAUDE.md content from project data.
     */
    public function generateClaudeMd(Project $project): string
    {
        $lines = [];

        $lines[] = '# CLAUDE.md';
        $lines[] = '';
        $lines[] = "Project: **{$project->name}**";
        $lines[] = '';

        if ($project->hasAiContext()) {
            $lines[] = '## Project Context';
            $lines[] = '';
            $lines[] = $project->getAiContextAsMarkdown();
            $lines[] = '';
        }

        $extensions = $project->getAllowedFileExtensions();
        if ($extensions !== []) {
            $lines[] = '## File Patterns';
            $lines[] = '';
            $lines[] = 'Focus on files with these extensions: ' . implode(', ', array_map(fn($e) => "`.{$e}`", $extensions));
            $lines[] = '';
        }

        $blacklisted = $project->getBlacklistedDirectories();
        if ($blacklisted !== []) {
            $lines[] = '## Excluded Directories';
            $lines[] = '';
            $lines[] = 'Do not modify files in these directories:';
            foreach ($blacklisted as $rule) {
                $path = $rule['path'];
                if ($rule['exceptions'] !== []) {
                    $exceptions = implode(', ', $rule['exceptions']);
                    $lines[] = "- `{$path}/` (except: {$exceptions})";
                } else {
                    $lines[] = "- `{$path}/`";
                }
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Generates settings.local.json content from project's ai_options.
     *
     * @return array Settings array suitable for JSON encoding
     */
    public function generateSettingsJson(Project $project): array
    {
        $options = $project->getAiOptions();
        $settings = [];

        if (!empty($options['permissionMode'])) {
            $settings['permissions'] = [
                'defaultMode' => $options['permissionMode'],
            ];
        }

        if (!empty($options['model'])) {
            $settings['model'] = $options['model'];
        }

        if (!empty($options['allowedTools'])) {
            $tools = array_map('trim', explode(',', $options['allowedTools']));
            $settings['allowedTools'] = array_values(array_filter($tools));
        }

        if (!empty($options['disallowedTools'])) {
            $tools = array_map('trim', explode(',', $options['disallowedTools']));
            $settings['disallowedTools'] = array_values(array_filter($tools));
        }

        return $settings;
    }

    // ──────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────

    private function determineWorkingDirectory(string $requestedDir, ?Project $project): string
    {
        $containerPath = $this->pathService->translatePath($requestedDir);
        $pathMapped = ($containerPath !== $requestedDir);

        if (is_dir($containerPath)) {
            $configStatus = $this->hasConfig($containerPath);
            if ($configStatus['hasAnyConfig']) {
                return $requestedDir;
            }
            Yii::warning(
                "Directory '{$containerPath}' exists but has no Claude config, falling through to managed workspace",
                LogCategory::AI->value
            );
        } else {
            $mappingNote = $pathMapped
                ? "mapped from '{$requestedDir}' to '{$containerPath}'"
                : "no path mapping applied for '{$requestedDir}'";
            Yii::warning(
                "Directory not accessible in container ({$mappingNote}), falling through to managed workspace",
                LogCategory::AI->value
            );
        }

        if ($project !== null) {
            $workspacePath = $this->getWorkspacePath($project);
            if (is_dir($workspacePath)) {
                return $workspacePath;
            }
            return $this->ensureWorkspace($project);
        }

        return $this->getDefaultWorkspacePath();
    }

    private function buildCommand(array $options, ?string $sessionId = null, bool $streaming = false): string
    {
        $outputFormat = $options['outputFormat'] ?? 'stream-json';
        $cmd = 'claude --output-format ' . escapeshellarg($outputFormat);

        if (($options['verbose'] ?? true) !== false) {
            $cmd .= ' --verbose';
        }

        if (!empty($options['noSessionPersistence'])) {
            $cmd .= ' --no-session-persistence';
        }

        if (array_key_exists('tools', $options)) {
            $cmd .= ' --tools ' . escapeshellarg($options['tools']);
        }

        if (!empty($options['settingSources'])) {
            $cmd .= ' --setting-sources ' . escapeshellarg($options['settingSources']);
        }

        if ($streaming) {
            $cmd .= ' --include-partial-messages';
        }

        if ($sessionId !== null) {
            $cmd .= ' --resume ' . escapeshellarg($sessionId);
        }

        if (!empty($options['permissionMode'])) {
            $cmd .= ' --permission-mode ' . escapeshellarg($options['permissionMode']);
        }

        if (!empty($options['model'])) {
            $cmd .= ' --model ' . escapeshellarg($options['model']);
        }

        if (!empty($options['systemPromptFile'])) {
            $cmd .= ' --system-prompt-file ' . escapeshellarg($options['systemPromptFile']);
        } elseif (!empty($options['systemPrompt'])) {
            $cmd .= ' --system-prompt ' . escapeshellarg($options['systemPrompt']);
        }

        $appendPrompt = trim(
            ($options['appendSystemPrompt'] ?? '') . "\n\n" . self::NO_INTERACTIVE_QUESTIONS_PROMPT
        );
        $cmd .= ' --append-system-prompt ' . escapeshellarg($appendPrompt);

        if (!empty($options['maxTurns'])) {
            $cmd .= ' --max-turns ' . (int) $options['maxTurns'];
        }

        if (!empty($options['allowedTools'])) {
            $cmd .= ' --allowedTools ' . escapeshellarg($options['allowedTools']);
        }

        if (!empty($options['disallowedTools'])) {
            $cmd .= ' --disallowedTools ' . escapeshellarg($options['disallowedTools']);
        }

        $cmd .= ' -p -';

        return $cmd;
    }

    /**
     * @return array{result?: string, is_error?: bool, duration_ms?: int, model?: string, input_tokens?: int, cache_tokens?: int, output_tokens?: int, context_window?: int, session_id?: string}
     */
    private function parseStreamJsonOutput(string $output): array
    {
        if ($output === '') {
            return [];
        }

        $lines = explode("\n", $output);
        $lastAssistantUsage = null;
        $resultMessage = null;
        $mainTurnCount = 0;
        $toolUses = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            $type = $decoded['type'] ?? null;

            if ($type === 'assistant' && empty($decoded['isSidechain'])) {
                $lastAssistantUsage = $decoded['message']['usage'] ?? null;
                $mainTurnCount++;
                $toolUses = array_merge($toolUses, $this->extractToolUses($decoded['message']['content'] ?? []));
            } elseif ($type === 'result') {
                $resultMessage = $decoded;
            }
        }

        if ($resultMessage === null) {
            return [];
        }

        $parsed = [
            'result' => $resultMessage['result'] ?? null,
            'is_error' => $resultMessage['is_error'] ?? false,
            'duration_ms' => $resultMessage['duration_ms'] ?? null,
            'session_id' => $resultMessage['session_id'] ?? null,
            'num_turns' => $resultMessage['num_turns'] ?? $mainTurnCount,
            'tool_uses' => $toolUses,
        ];

        $this->extractModelInfo($parsed, $resultMessage['modelUsage'] ?? []);

        if ($lastAssistantUsage !== null) {
            $parsed['input_tokens'] = $lastAssistantUsage['input_tokens'] ?? 0;
            $parsed['cache_tokens'] = ($lastAssistantUsage['cache_read_input_tokens'] ?? 0)
                + ($lastAssistantUsage['cache_creation_input_tokens'] ?? 0);
        }

        $resultUsage = $resultMessage['usage'] ?? [];
        if ($resultUsage !== []) {
            $parsed['output_tokens'] = $resultUsage['output_tokens'] ?? 0;
        }

        return $parsed;
    }

    /**
     * @return array{result?: string, is_error?: bool, duration_ms?: int, session_id?: string}
     */
    private function parseJsonOutput(string $output): array
    {
        if ($output === '') {
            return [];
        }

        $decoded = json_decode($output, true);
        if (!is_array($decoded)) {
            return [];
        }

        return [
            'result' => $decoded['result'] ?? null,
            'is_error' => $decoded['is_error'] ?? false,
            'duration_ms' => $decoded['duration_ms'] ?? null,
            'session_id' => $decoded['session_id'] ?? null,
            'num_turns' => $decoded['num_turns'] ?? null,
        ];
    }

    private function extractModelInfo(array &$parsed, array $modelUsage): void
    {
        if ($modelUsage === []) {
            return;
        }

        $primaryModelId = array_key_first($modelUsage);
        $primary = $modelUsage[$primaryModelId];
        if (!is_array($primary)) {
            return;
        }

        $parsed['model'] = $this->formatModelName($primaryModelId);
        if (isset($primary['contextWindow'])) {
            $parsed['context_window'] = (int) $primary['contextWindow'];
        }
    }

    /**
     * @return string[] e.g. ["Read: /path/to/file", "Grep: pattern"]
     */
    private function extractToolUses(array $content): array
    {
        $uses = [];
        foreach ($content as $block) {
            if (($block['type'] ?? null) !== 'tool_use') {
                continue;
            }

            $name = $block['name'] ?? 'unknown';
            $input = $block['input'] ?? [];
            $target = match ($name) {
                'Read', 'Edit', 'Write' => $input['file_path'] ?? null,
                'Glob', 'Grep' => $input['pattern'] ?? null,
                'Bash' => isset($input['command']) ? mb_substr($input['command'], 0, 80) : null,
                'Task' => $input['description'] ?? null,
                default => null,
            };
            $uses[] = $target !== null ? "{$name}: {$target}" : $name;
        }
        return $uses;
    }

    private function formatModelName(string $modelId): string
    {
        if (preg_match('/claude-(\w+)-(\d+)-(\d+)/', $modelId, $m)) {
            return $m[1] . '-' . $m[2] . '.' . $m[3];
        }

        return $modelId;
    }

    private function parseCommandDescription(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return '';
        }

        if (preg_match('/^---\s*\n(.*?)\n---/s', $content, $matches)) {
            if (preg_match('/^description:\s*(.+)$/m', $matches[1], $descMatch)) {
                return trim($descMatch[1]);
            }
        }

        return '';
    }

    private function determineConfigSource(string $effectiveDir, string $requestedDir, ?Project $project): string
    {
        if ($effectiveDir === $requestedDir) {
            $containerPath = $this->pathService->translatePath($effectiveDir);
            $configStatus = $this->hasConfig($containerPath);
            if ($configStatus['hasAnyConfig']) {
                $parts = [];
                if ($configStatus['hasConfigFile']) {
                    $parts[] = 'CLAUDE.md';
                }
                if ($configStatus['hasConfigDir']) {
                    $parts[] = '.claude/';
                }
                return 'project_own:' . implode('+', $parts);
            }
        }

        if ($project !== null) {
            $workspacePath = $this->getWorkspacePath($project);
            if (str_starts_with($effectiveDir, $workspacePath) || $effectiveDir === $workspacePath) {
                return 'managed_workspace';
            }
        }

        $defaultPath = $this->getDefaultWorkspacePath();
        if ($effectiveDir === $defaultPath) {
            return 'default_workspace';
        }

        return 'unknown';
    }

    private function storeProcessPid(int $pid, string $streamToken): void
    {
        $key = $this->buildPidCacheKey($streamToken);
        Yii::$app->cache->set($key, $pid, 3900);
    }

    private function clearProcessPid(string $streamToken): void
    {
        $key = $this->buildPidCacheKey($streamToken);
        Yii::$app->cache->delete($key);
    }

    private function buildPidCacheKey(string $streamToken): string
    {
        return 'ai_cli_pid_' . Yii::$app->user->id . '_' . $streamToken;
    }

    protected function fetchSubscriptionUsage(string $accessToken): array
    {
        $ch = curl_init('https://api.anthropic.com/api/oauth/usage');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'User-Agent: claude-code/2.1.31',
                'anthropic-beta: oauth-2025-04-20',
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => $curlError];
        }

        return [
            'success' => true,
            'status' => $httpCode,
            'body' => $response,
        ];
    }

    private function normalizeUsageData(array $raw): array
    {
        $windows = [];
        $windowKeys = [
            'five_hour' => '5h limit',
            'seven_day' => '7d limit',
            'seven_day_opus' => '7d Opus',
            'seven_day_sonnet' => '7d Sonnet',
        ];

        foreach ($windowKeys as $key => $label) {
            if (!isset($raw[$key]) || !is_array($raw[$key])) {
                continue;
            }

            $windows[] = [
                'key' => $key,
                'label' => $label,
                'utilization' => (float) ($raw[$key]['utilization'] ?? 0),
                'resets_at' => $raw[$key]['resets_at'] ?? null,
            ];
        }

        return ['windows' => $windows];
    }

    private function resolveCredentialsPath(): ?string
    {
        $configured = Yii::$app->params['claudeCredentialsPath'] ?? null;
        if ($configured !== null) {
            return $configured;
        }

        $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: '/root');

        return $home . '/.claude/.credentials.json';
    }

    private function generateDefaultClaudeMd(): string
    {
        return "# Default Workspace\n\nGeneral purpose workspace for notes without a project assignment.\n";
    }

    private function createDirectory(string $path): void
    {
        if (!mkdir($path, 0o755, true) && !is_dir($path)) {
            throw new RuntimeException("Failed to create directory: {$path}");
        }
    }

    private function migrateWorkspace(string $oldPath, string $newPath): void
    {
        try {
            $this->createDirectory($newPath);

            $items = scandir($oldPath);
            if ($items === false) {
                return;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $source = $oldPath . '/' . $item;
                // Skip subdirectories that are other provider workspaces
                if (is_dir($source) && $item !== '.claude') {
                    continue;
                }

                rename($source, $newPath . '/' . $item);
            }

            Yii::debug("Migrated workspace from {$oldPath} to {$newPath}", LogCategory::AI->value);
        } catch (Throwable $e) {
            Yii::warning("Workspace migration failed from {$oldPath} to {$newPath}: {$e->getMessage()}", LogCategory::AI->value);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
