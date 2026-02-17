<?php

namespace app\services\ai;

use app\models\Project;

/**
 * Minimal contract that every AI CLI provider must implement.
 *
 * Provider-specific features (streaming, workspace management, usage tracking,
 * config/commands) are defined in optional interfaces that providers may also implement.
 * Controllers detect optional capabilities via `instanceof` checks.
 *
 * @see AiStreamingProviderInterface
 * @see AiWorkspaceProviderInterface
 * @see AiUsageProviderInterface
 * @see AiConfigProviderInterface
 */
interface AiProviderInterface
{
    /**
     * Executes a prompt synchronously and returns the result.
     *
     * @param string $prompt The prompt content (already converted to markdown)
     * @param string $workDir The directory to run the CLI from (may be overridden by managed workspace)
     * @param int $timeout Maximum execution time in seconds
     * @param array $options Provider-specific CLI options (permissionMode, model, appendSystemPrompt, allowedTools, disallowedTools, etc.)
     * @param Project|null $project Optional project for workspace resolution
     * @param string|null $sessionId Optional session ID to continue a previous conversation
     * @return array{success: bool, output: string, error: string, exitCode: int, duration_ms?: int, model?: string, input_tokens?: int, cache_tokens?: int, output_tokens?: int, context_window?: int, num_turns?: int, tool_uses?: string[], configSource?: string, session_id?: string, requestedPath: string, effectivePath: string, usedFallback: bool}
     */
    public function execute(
        string $prompt,
        string $workDir,
        int $timeout = 3600,
        array $options = [],
        ?Project $project = null,
        ?string $sessionId = null
    ): array;

    /**
     * Terminates a running CLI process by its stream token.
     *
     * @return bool True if a process was found and signalled
     */
    public function cancelProcess(string $streamToken): bool;

    /**
     * Returns the provider display name (e.g. "Claude", "Codex", "Gemini").
     */
    public function getName(): string;

    /**
     * Returns the provider identifier for database storage.
     *
     * Must match pattern: /^[a-z][a-z0-9-]{1,48}$/
     */
    public function getIdentifier(): string;
}
