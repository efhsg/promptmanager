<?php

namespace app\services\ai;

use app\models\Project;

/**
 * Optional interface for providers that support streaming output.
 *
 * Providers that implement this interface can deliver incremental output
 * via a line callback, and parse provider-specific NDJSON stream logs
 * into a standardised result array.
 */
interface AiStreamingProviderInterface
{
    /**
     * Executes a prompt with streaming output, calling $onLine for each stdout line.
     *
     * @param string $prompt The prompt content (already converted to markdown)
     * @param string $workDir The directory to run the CLI from
     * @param callable(string): void $onLine Callback invoked for each line of stdout
     * @param int $timeout Maximum execution time in seconds
     * @param array $options Provider-specific CLI options
     * @param Project|null $project Optional project for workspace resolution
     * @param string|null $sessionId Optional session ID to continue a previous conversation
     * @param string|null $streamToken Token for cancel support (PID tracking)
     * @return array{exitCode: int, error: string}
     * @throws \RuntimeException
     */
    public function executeStreaming(
        string $prompt,
        string $workDir,
        callable $onLine,
        int $timeout = 3600,
        array $options = [],
        ?Project $project = null,
        ?string $sessionId = null,
        ?string $streamToken = null
    ): array;

    /**
     * Parses a provider-specific stream log into a standardised result.
     *
     * @param string|null $streamLog Raw NDJSON stream log content
     * @return array{text: string, session_id: ?string, metadata: array}
     */
    public function parseStreamResult(?string $streamLog): array;
}
