<?php

namespace app\services;

use app\models\Project;
use common\enums\CopyType;
use Yii;

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
     * @return array{success: bool, output: string, error: string, exitCode: int, configSource?: string, session_id?: string}
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

        $parsedOutput = $this->parseJsonOutput(trim($output));

        return [
            'success' => $exitCode === 0 && !($parsedOutput['is_error'] ?? false),
            'output' => $parsedOutput['result'] ?? trim($output),
            'error' => trim($error),
            'exitCode' => $exitCode,
            'cost_usd' => $parsedOutput['total_cost_usd'] ?? null,
            'duration_ms' => $parsedOutput['duration_ms'] ?? null,
            'configSource' => $configSource,
            'session_id' => $parsedOutput['session_id'] ?? null,
        ];
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
        if (is_dir($containerPath)) {
            $configStatus = $this->hasClaudeConfig($containerPath);
            if ($configStatus['hasAnyConfig']) {
                return $requestedDir;
            }
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
    private function buildCommand(array $options, ?string $sessionId = null): string
    {
        $cmd = 'claude --output-format json';

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
     * Parses the JSON output from Claude CLI --output-format json.
     *
     * @return array{result?: string, is_error?: bool, total_cost_usd?: float, duration_ms?: int}
     */
    private function parseJsonOutput(string $output): array
    {
        if ($output === '') {
            return [];
        }

        $decoded = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $decoded;
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
     * Checks Claude config status for a given host path.
     *
     * @return array{hasCLAUDE_MD: bool, hasClaudeDir: bool, hasAnyConfig: bool}
     */
    public function checkClaudeConfigForPath(string $hostPath): array
    {
        $containerPath = $this->translatePath($hostPath);
        if (!is_dir($containerPath)) {
            return [
                'hasCLAUDE_MD' => false,
                'hasClaudeDir' => false,
                'hasAnyConfig' => false,
            ];
        }

        return $this->hasClaudeConfig($containerPath);
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
