<?php

namespace app\services\ai;

use app\models\Project;

/**
 * Optional interface for providers that manage workspace directories.
 *
 * Workspace directories contain provider-specific configuration files
 * (e.g. CLAUDE.md, .claude/settings.local.json) that are synced from
 * project settings.
 */
interface AiWorkspaceProviderInterface
{
    /**
     * Ensures a workspace directory exists for the project.
     *
     * @return string The absolute path to the workspace directory
     */
    public function ensureWorkspace(Project $project): string;

    /**
     * Syncs the project's configuration to its managed workspace.
     */
    public function syncConfig(Project $project): void;

    /**
     * Deletes the managed workspace for a project.
     */
    public function deleteWorkspace(Project $project): void;

    /**
     * Gets the workspace directory path for a project.
     */
    public function getWorkspacePath(Project $project): string;

    /**
     * Gets the default workspace path for contexts without a project.
     */
    public function getDefaultWorkspacePath(): string;
}
