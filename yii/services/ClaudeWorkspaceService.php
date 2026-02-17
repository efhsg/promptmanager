<?php

namespace app\services;

use app\models\Project;
use Yii;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Manages persistent Claude workspace directories for projects.
 *
 * Each project gets its own managed workspace directory containing:
 * - CLAUDE.md: Generated from project's claude_context
 * - .claude/settings.local.json: Generated from project's claude_options
 */
class ClaudeWorkspaceService
{
    private const WORKSPACE_BASE = '@app/storage/projects';

    /**
     * Gets the workspace directory path for a project.
     */
    public function getWorkspacePath(Project $project): string
    {
        return Yii::getAlias(self::WORKSPACE_BASE) . '/' . $project->id;
    }

    /**
     * Gets the default workspace path for notes without a project.
     */
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

    /**
     * Ensures the workspace directory exists for a project.
     */
    public function ensureWorkspace(Project $project): string
    {
        $path = $this->getWorkspacePath($project);

        if (!is_dir($path)) {
            $this->createDirectory($path);
            $this->createDirectory($path . '/.claude');
        }

        return $path;
    }

    /**
     * Syncs the project's Claude configuration to its managed workspace.
     */
    public function syncConfig(Project $project): void
    {
        $path = $this->ensureWorkspace($project);

        // Generate and write CLAUDE.md
        $claudeMd = $this->generateClaudeMd($project);
        file_put_contents($path . '/CLAUDE.md', $claudeMd);

        // Generate and write settings.local.json
        $settings = $this->generateSettingsJson($project);
        file_put_contents(
            $path . '/.claude/settings.local.json',
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Deletes the managed workspace for a project.
     */
    public function deleteWorkspace(Project $project): void
    {
        $path = $this->getWorkspacePath($project);

        if (is_dir($path)) {
            $this->removeDirectory($path);
        }
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

        // Include custom context if set (stored as Delta JSON, converted to markdown)
        if ($project->hasAiContext()) {
            $lines[] = '## Project Context';
            $lines[] = '';
            $lines[] = $project->getAiContextAsMarkdown();
            $lines[] = '';
        }

        // File patterns from allowed extensions
        $extensions = $project->getAllowedFileExtensions();
        if ($extensions !== []) {
            $lines[] = '## File Patterns';
            $lines[] = '';
            $lines[] = 'Focus on files with these extensions: ' . implode(', ', array_map(fn($e) => "`.{$e}`", $extensions));
            $lines[] = '';
        }

        // Exclusions from blacklisted directories
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
     * Generates settings.local.json content from project's claude_options.
     *
     * @return array Settings array suitable for JSON encoding
     */
    public function generateSettingsJson(Project $project): array
    {
        $options = $project->getAiOptions();
        $settings = [];

        // Map permission mode to settings
        if (!empty($options['permissionMode'])) {
            $settings['permissions'] = [
                'defaultMode' => $options['permissionMode'],
            ];
        }

        // Map model preference
        if (!empty($options['model'])) {
            $settings['model'] = $options['model'];
        }

        // Map allowed/disallowed tools
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

    /**
     * Generates default CLAUDE.md for notes without a project.
     */
    private function generateDefaultClaudeMd(): string
    {
        return "# Default Workspace\n\nGeneral purpose workspace for notes without a project assignment.\n";
    }

    /**
     * Creates a directory with proper permissions.
     */
    private function createDirectory(string $path): void
    {
        if (!mkdir($path, 0o755, true) && !is_dir($path)) {
            throw new RuntimeException("Failed to create directory: {$path}");
        }
    }

    /**
     * Recursively removes a directory and its contents.
     */
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
