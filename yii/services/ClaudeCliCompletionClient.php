<?php

namespace app\services;

use app\services\ai\AiProviderInterface;

/**
 * AiCompletionClient backed by the Claude CLI.
 *
 * Runs an isolated, single-turn, tool-less CLI invocation from a bare
 * working directory so no project context leaks into the call.
 */
class ClaudeCliCompletionClient implements AiCompletionClient
{
    private AiProviderInterface $claudeCliService;

    public function __construct(AiProviderInterface $claudeCliService)
    {
        $this->claudeCliService = $claudeCliService;
    }

    public function complete(string $prompt, string $systemPromptFile, array $options = []): array
    {
        $workdir = $this->ensureBareWorkdir();

        $result = $this->claudeCliService->execute(
            $prompt,
            $workdir,
            $options['timeout'] ?? 60,
            [
                'model' => $options['model'] ?? 'haiku',
                'systemPromptFile' => $systemPromptFile,
                'maxTurns' => 1,
                'outputFormat' => 'json',
                'verbose' => false,
                'tools' => '',
                'noSessionPersistence' => true,
                'rawWorkingDirectory' => true,
            ],
            null,
            null
        );

        $output = $result['output'] ?? '';
        if (!$result['success'] || !is_string($output) || trim($output) === '') {
            return [
                'success' => false,
                'error' => $result['error'] ?: 'AI returned empty output.',
            ];
        }

        return [
            'success' => true,
            'output' => trim($output),
        ];
    }

    /**
     * Returns an empty directory outside the project tree so the CLI
     * does not discover any CLAUDE.md or .claude/ via parent traversal.
     * Removes any stale config files to keep isolation intact.
     */
    private function ensureBareWorkdir(): string
    {
        $path = sys_get_temp_dir() . '/claude-quick';

        if (!is_dir($path)) {
            mkdir($path, 0o755, true);
            return $path;
        }

        // Remove any files that could affect CLI behavior.
        // Check symlinks first to avoid following them into unrelated directories.
        foreach (['CLAUDE.md', '.claude'] as $artifact) {
            $target = $path . '/' . $artifact;
            if (is_link($target)) {
                unlink($target);
            } elseif (is_file($target)) {
                unlink($target);
            } elseif (is_dir($target)) {
                $this->removeDirectory($target);
            }
        }

        return $path;
    }

    private function removeDirectory(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $dir . '/' . $item;
            if (is_link($itemPath)) {
                unlink($itemPath);
            } elseif (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($dir);
    }
}
