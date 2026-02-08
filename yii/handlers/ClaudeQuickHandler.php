<?php

namespace app\handlers;

use app\services\AiCompletionClient;
use InvalidArgumentException;
use RuntimeException;
use Yii;

/**
 * Handles lightweight AI completion calls for predefined use cases.
 *
 * Each use case maps to a workdir under `.claude/workdirs/{name}/` containing a
 * CLAUDE.md with the system prompt. The actual AI provider is abstracted behind
 * the AiCompletionClient interface.
 */
class ClaudeQuickHandler
{
    private const WORKDIR_BASE = '@app/../.claude/workdirs';

    private const USE_CASES = [
        'prompt-title' => [
            'model' => 'haiku',
            'timeout' => 60,
            'workdir' => 'prompt-title',
            'minChars' => 120,
            'maxChars' => 3000,
        ],
        'scratch-pad-name' => [
            'model' => 'haiku',
            'timeout' => 60,
            'workdir' => 'scratch-pad-name',
            'minChars' => 20,
            'maxChars' => 3000,
        ],
    ];

    public function __construct(
        private readonly AiCompletionClient $completionClient,
    ) {}

    /**
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function run(string $useCase, string $prompt): array
    {
        if (!isset(self::USE_CASES[$useCase])) {
            throw new InvalidArgumentException("Unknown use case: {$useCase}");
        }

        $config = self::USE_CASES[$useCase];
        $length = mb_strlen($prompt);

        if (isset($config['minChars']) && $length < $config['minChars']) {
            return ['success' => false, 'error' => 'Prompt too short for summarization.'];
        }

        if (isset($config['maxChars']) && $length > $config['maxChars']) {
            $prompt = mb_substr($prompt, 0, $config['maxChars']);
        }

        $systemPromptFile = $this->resolveSystemPromptFile($config['workdir']);

        // Wrap in document tags so the model treats it as content to summarize,
        // not as instructions to follow (prevents prompt injection).
        $prompt = '<document>' . $prompt . '</document>';

        $result = $this->completionClient->complete($prompt, $systemPromptFile, [
            'model' => $config['model'],
            'timeout' => $config['timeout'],
        ]);

        if (!$result['success']) {
            Yii::warning("ClaudeQuick [{$useCase}] failed: " . ($result['error'] ?? 'empty output'), __METHOD__);
            return [
                'success' => false,
                'error' => $result['error'] ?? 'AI returned empty output.',
            ];
        }

        return [
            'success' => true,
            'output' => trim($result['output']),
        ];
    }

    /**
     * @throws RuntimeException
     */
    private function resolveSystemPromptFile(string $workdir): string
    {
        $path = Yii::getAlias(self::WORKDIR_BASE) . '/' . $workdir . '/CLAUDE.md';

        if (!is_file($path)) {
            throw new RuntimeException("System prompt file not found: {$path}");
        }

        return $path;
    }
}
