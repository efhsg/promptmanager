<?php

namespace app\services;

use app\models\Project;
use common\enums\CopyType;
use Yii;
use RuntimeException;

/**
 * Service for executing Claude CLI commands.
 */
class ClaudeCliService
{
    private CopyFormatConverter $formatConverter;
    private ClaudeWorkspaceService $workspaceService;

    public function __construct(
        ?CopyFormatConverter $formatConverter = null,
        ?ClaudeWorkspaceService $workspaceService = null
    ) {
        $this->formatConverter = $formatConverter ?? new CopyFormatConverter();
        $this->workspaceService = $workspaceService ?? new ClaudeWorkspaceService();
    }

    /**
     * Translates a host path to a container path using configured mappings.
     */
    private function translatePath(string $hostPath): string
    {
        $mappings = Yii::$app->params['pathMappings'] ?? [];
        foreach ($mappings as $hostPrefix => $containerPrefix) {
            if (str_starts_with($hostPath, $hostPrefix)) {
                return $containerPrefix . substr($hostPath, strlen($hostPrefix));
            }
        }
        return $hostPath;
    }

    /**
     * Converts Quill Delta JSON to markdown.
     */
    public function convertToMarkdown(string $deltaJson): string
    {
        return $this->formatConverter->convertFromQuillDelta($deltaJson, CopyType::MD);
    }

    /**
     * Executes the Claude CLI with the given prompt in the specified working directory.
     *
     * @param string $prompt The prompt content (already converted to markdown)
     * @param string $workingDirectory The directory to run Claude from (may be overridden by managed workspace)
     * @param int $timeout Maximum execution time in seconds
     * @param array $options Claude CLI options (permissionMode, model, appendSystemPrompt, allowedTools, disallowedTools)
     * @param Project|null $project Optional project for workspace resolution
     * @param string|null $sessionId Optional session ID to continue a previous conversation
     * @return array{success: bool, output: string, error: string, exitCode: int, duration_ms?: int, model?: string, input_tokens?: int, cache_tokens?: int, output_tokens?: int, context_window?: int, num_turns?: int, tool_uses?: string[], configSource?: string, session_id?: string, requestedPath: string, effectivePath: string, usedFallback: bool}
     */
    public function execute(
        string $prompt,
        string $workingDirectory,
        int $timeout = 300,
        array $options = [],
        ?Project $project = null,
        ?string $sessionId = null
    ): array {
        // Determine effective working directory (managed workspace vs project's own)
        $effectiveWorkDir = $this->determineWorkingDirectory($workingDirectory, $project);
        $usedFallback = ($effectiveWorkDir !== $workingDirectory);
        $containerPath = $this->translatePath($effectiveWorkDir);

        if (!is_dir($containerPath)) {
            return [
                'success' => false,
                'output' => '',
                'error' => 'Working directory does not exist: ' . $effectiveWorkDir,
                'exitCode' => 1,
            ];
        }

        // Determine config source for reporting
        $configSource = $this->determineConfigSource($effectiveWorkDir, $workingDirectory, $project);

        $command = $this->buildCommand($options, $sessionId);

        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
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

        // Write prompt to stdin (avoids command-line arg length limits)
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

        $parsedOutput = $this->parseStreamJsonOutput(trim($output));

        return [
            'success' => $exitCode === 0 && !($parsedOutput['is_error'] ?? false),
            'output' => $parsedOutput['result'] ?? trim($output),
            'error' => trim($error),
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
            'requestedPath' => $workingDirectory,
            'effectivePath' => $effectiveWorkDir,
            'usedFallback' => $usedFallback,
        ];
    }

    /**
     * Executes Claude CLI with streaming output, calling $onLine for each stdout line.
     *
     * @param callable(string): void $onLine Callback invoked for each line of stdout
     * @return array{exitCode: int, error: string}
     * @throws RuntimeException
     */
    public function executeStreaming(
        string $prompt,
        string $workingDirectory,
        callable $onLine,
        int $timeout = 300,
        array $options = [],
        ?Project $project = null,
        ?string $sessionId = null
    ): array {
        $effectiveWorkDir = $this->determineWorkingDirectory($workingDirectory, $project);
        $containerPath = $this->translatePath($effectiveWorkDir);

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

        // Store PID in session so the cancel endpoint can terminate the process
        $status = proc_get_status($process);
        $this->storeProcessPid($status['pid']);

        stream_set_blocking($pipes[1], true);
        stream_set_timeout($pipes[1], 30);
        stream_set_blocking($pipes[2], false);

        $error = '';
        $startTime = time();
        $cancelled = false;

        while (($line = fgets($pipes[1])) !== false) {
            if ((time() - $startTime) > $timeout) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                $this->clearProcessPid();
                return ['exitCode' => 124, 'error' => 'Command timed out after ' . $timeout . ' seconds'];
            }

            // Detect client disconnect (browser aborted the fetch)
            if (connection_aborted()) {
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

        $error .= stream_get_contents($pipes[2]) ?: '';

        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_get_status($process);
        $exitCode = $cancelled ? 130 : ($status['running'] ? -1 : $status['exitcode']);
        proc_close($process);
        $this->clearProcessPid();

        return ['exitCode' => $exitCode, 'error' => trim($error)];
    }

    /**
     * Terminates a running Claude CLI process by its PID.
     * Requires the POSIX extension for process signalling.
     *
     * @return bool True if a process was found and signalled
     */
    public function cancelRunningProcess(): bool
    {
        if (!function_exists('posix_kill')) {
            Yii::warning('posix_kill not available — cannot cancel Claude CLI process', __METHOD__);
            return false;
        }

        $key = 'claude_cli_pid_' . Yii::$app->user->id;
        $pid = Yii::$app->cache->get($key);

        if ($pid === false) {
            return false;
        }

        Yii::$app->cache->delete($key);

        // Send SIGTERM — posix_kill returns false if process doesn't exist (ESRCH)
        if (!posix_kill($pid, 15)) {
            return false;
        }

        // Give process time to exit gracefully, then force-kill if still alive
        usleep(200000);
        posix_kill($pid, 9);

        return true;
    }

    private function storeProcessPid(int $pid): void
    {
        $key = 'claude_cli_pid_' . Yii::$app->user->id;
        Yii::$app->cache->set($key, $pid, 3900);
    }

    private function clearProcessPid(): void
    {
        $key = 'claude_cli_pid_' . Yii::$app->user->id;
        Yii::$app->cache->delete($key);
    }

    /**
     * Determines the effective working directory based on config availability.
     *
     * Priority:
     * 1. Project's own root_directory IF it has Claude config
     * 2. Managed workspace for project
     * 3. Default workspace (for scratch pads without project)
     */
    private function determineWorkingDirectory(string $requestedDir, ?Project $project): string
    {
        // Check if requested directory exists and has Claude config
        $containerPath = $this->translatePath($requestedDir);
        $pathMapped = ($containerPath !== $requestedDir);

        if (is_dir($containerPath)) {
            $configStatus = $this->hasClaudeConfig($containerPath);
            if ($configStatus['hasAnyConfig']) {
                return $requestedDir;
            }
            Yii::warning(
                "Directory '{$containerPath}' exists but has no Claude config, falling through to managed workspace",
                'claude'
            );
        } else {
            $mappingNote = $pathMapped
                ? "mapped from '{$requestedDir}' to '{$containerPath}'"
                : "no path mapping applied for '{$requestedDir}'";
            Yii::warning(
                "Directory not accessible in container ({$mappingNote}), falling through to managed workspace",
                'claude'
            );
        }

        // If project exists, use its managed workspace
        if ($project !== null) {
            $workspacePath = $this->workspaceService->getWorkspacePath($project);
            if (is_dir($workspacePath)) {
                return $workspacePath;
            }
            // Ensure workspace exists
            return $this->workspaceService->ensureWorkspace($project);
        }

        // Fallback to default workspace
        return $this->workspaceService->getDefaultWorkspacePath();
    }

    /**
     * Builds the Claude CLI command with options.
     *
     * Prompt is passed via stdin to avoid command-line argument length limits.
     */
    private function buildCommand(array $options, ?string $sessionId = null, bool $streaming = false): string
    {
        $cmd = 'claude --output-format stream-json --verbose';

        if ($streaming) {
            $cmd .= ' --include-partial-messages';
        }

        if ($sessionId !== null) {
            $cmd .= ' --continue ' . escapeshellarg($sessionId);
        }

        $mode = $options['permissionMode'] ?? 'plan';
        $cmd .= ' --permission-mode ' . escapeshellarg($mode);

        if (!empty($options['model'])) {
            $cmd .= ' --model ' . escapeshellarg($options['model']);
        }

        if (!empty($options['appendSystemPrompt'])) {
            $cmd .= ' --append-system-prompt ' . escapeshellarg($options['appendSystemPrompt']);
        }

        if (!empty($options['allowedTools'])) {
            $cmd .= ' --allowedTools ' . escapeshellarg($options['allowedTools']);
        }

        if (!empty($options['disallowedTools'])) {
            $cmd .= ' --disallowedTools ' . escapeshellarg($options['disallowedTools']);
        }

        // Read prompt from stdin to avoid arg length limits
        $cmd .= ' -p -';

        return $cmd;
    }

    /**
     * Parses NDJSON output from Claude CLI --output-format stream-json.
     *
     * Uses the last non-sidechain assistant message's per-call usage for accurate
     * context fill, and the result message for session metadata.
     *
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

        // Context fill from last assistant message (per-call input tokens)
        if ($lastAssistantUsage !== null) {
            $parsed['input_tokens'] = $lastAssistantUsage['input_tokens'] ?? 0;
            $parsed['cache_tokens'] = ($lastAssistantUsage['cache_read_input_tokens'] ?? 0)
                + ($lastAssistantUsage['cache_creation_input_tokens'] ?? 0);
        }

        // Total output tokens from result message (cumulative across all turns)
        $resultUsage = $resultMessage['usage'] ?? [];
        if ($resultUsage !== []) {
            $parsed['output_tokens'] = $resultUsage['output_tokens'] ?? 0;
        }

        return $parsed;
    }

    /**
     * Extracts model name and context window from modelUsage data.
     */
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

    /**
     * Converts a full model ID to a short display name.
     *
     * E.g. "claude-opus-4-5-20251101" → "opus-4.5"
     */
    private function formatModelName(string $modelId): string
    {
        if (preg_match('/claude-(\w+)-(\d+)-(\d+)/', $modelId, $m)) {
            return $m[1] . '-' . $m[2] . '.' . $m[3];
        }

        return $modelId;
    }

    /**
     * Checks if the target directory has its own Claude configuration.
     *
     * @return array{hasCLAUDE_MD: bool, hasClaudeDir: bool, hasAnyConfig: bool}
     */
    public function hasClaudeConfig(string $containerPath): array
    {
        $hasCLAUDE_MD = file_exists($containerPath . '/CLAUDE.md');
        $hasClaudeDir = is_dir($containerPath . '/.claude');

        return [
            'hasCLAUDE_MD' => $hasCLAUDE_MD,
            'hasClaudeDir' => $hasClaudeDir,
            'hasAnyConfig' => $hasCLAUDE_MD || $hasClaudeDir,
        ];
    }

    /**
     * Checks Claude config status for a given host path with diagnostics.
     *
     * @return array{hasCLAUDE_MD: bool, hasClaudeDir: bool, hasAnyConfig: bool, pathStatus: string, pathMapped: bool, requestedPath: string, effectivePath: string}
     */
    public function checkClaudeConfigForPath(string $hostPath): array
    {
        $containerPath = $this->translatePath($hostPath);
        $pathMapped = ($containerPath !== $hostPath);

        $base = [
            'hasCLAUDE_MD' => false,
            'hasClaudeDir' => false,
            'hasAnyConfig' => false,
            'pathMapped' => $pathMapped,
            'requestedPath' => $hostPath,
            'effectivePath' => $containerPath,
        ];

        if (!is_dir($containerPath)) {
            $base['pathStatus'] = $pathMapped ? 'not_accessible' : 'not_mapped';
            return $base;
        }

        $config = $this->hasClaudeConfig($containerPath);

        return array_merge($base, $config, [
            'pathStatus' => $config['hasAnyConfig'] ? 'has_config' : 'no_config',
        ]);
    }

    /**
     * Determines the config source string for display purposes.
     */
    private function determineConfigSource(string $effectiveDir, string $requestedDir, ?Project $project): string
    {
        // Check if we're using project's own directory
        if ($effectiveDir === $requestedDir) {
            $containerPath = $this->translatePath($effectiveDir);
            $configStatus = $this->hasClaudeConfig($containerPath);
            if ($configStatus['hasAnyConfig']) {
                $parts = [];
                if ($configStatus['hasCLAUDE_MD']) {
                    $parts[] = 'CLAUDE.md';
                }
                if ($configStatus['hasClaudeDir']) {
                    $parts[] = '.claude/';
                }
                return 'project_own:' . implode('+', $parts);
            }
        }

        // Check if we're using a managed workspace
        if ($project !== null) {
            $workspacePath = $this->workspaceService->getWorkspacePath($project);
            if (str_starts_with($effectiveDir, $workspacePath) || $effectiveDir === $workspacePath) {
                return 'managed_workspace';
            }
        }

        // Default workspace
        $defaultPath = $this->workspaceService->getDefaultWorkspacePath();
        if ($effectiveDir === $defaultPath) {
            return 'default_workspace';
        }

        return 'unknown';
    }
}
