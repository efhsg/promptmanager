<?php

namespace app\services;

/**
 * Contract for single-turn AI text completion.
 *
 * Implementations may wrap the Claude CLI, an HTTP API, or any other provider.
 */
interface AiCompletionClient
{
    /**
     * Sends a single-turn completion request and returns the raw text output.
     *
     * @param string $prompt User prompt
     * @param string $systemPromptFile Absolute path to a system prompt file
     * @param array{model?: string, timeout?: int} $options Provider-specific options
     * @return array{success: bool, output?: string, error?: string}
     */
    public function complete(string $prompt, string $systemPromptFile, array $options = []): array;
}
