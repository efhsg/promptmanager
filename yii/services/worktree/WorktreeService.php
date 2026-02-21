<?php

namespace app\services\worktree;

use app\models\Project;
use app\models\ProjectWorktree;
use app\services\PathService;
use common\enums\LogCategory;
use common\enums\WorktreePurpose;
use RuntimeException;
use Yii;

/**
 * Manages git worktree lifecycle: create, sync, status, remove, cleanup, recreate.
 */
class WorktreeService
{
    public function __construct(private readonly PathService $pathService) {}

    /**
     * Create a new worktree for a project.
     *
     * @throws RuntimeException
     */
    public function create(
        Project $project,
        string $branch,
        string $suffix,
        WorktreePurpose $purpose,
        string $sourceBranch = 'main'
    ): ProjectWorktree {
        $rootPath = $this->getTranslatedRootPath($project);
        if ($rootPath === null) {
            throw new RuntimeException('Project has no root directory configured.');
        }

        if (!$this->isGitRepo($project)) {
            throw new RuntimeException('Root directory is not a git repository.');
        }

        $worktreePath = $rootPath . '-' . $suffix;

        // 1. Validate model before executing git commands
        $model = new ProjectWorktree();
        $model->project_id = $project->id;
        $model->purpose = $purpose->value;
        $model->branch = $branch;
        $model->path_suffix = $suffix;
        $model->source_branch = $sourceBranch;

        if (!$model->validate()) {
            throw new RuntimeException(implode(' ', $model->getFirstErrors()));
        }

        // 2. git worktree add -b <branch> <path>
        $command = 'git -C ' . escapeshellarg($rootPath)
            . ' worktree add -b ' . escapeshellarg($branch)
            . ' ' . escapeshellarg($worktreePath);
        $this->execGit($command, 'Failed to create worktree. Branch or path may already exist.');

        // 3. git merge <source_branch> --no-edit (initial sync)
        $this->mergeSourceBranch($worktreePath, $sourceBranch, $suffix);

        // 4. Save DB record
        if (!$model->save(false)) {
            // Compensate: remove the git worktree
            $removeCommand = 'git -C ' . escapeshellarg($rootPath)
                . ' worktree remove ' . escapeshellarg($worktreePath) . ' --force';
            exec($removeCommand . ' 2>&1');

            Yii::error(
                'Failed to save worktree record, compensated by removing git worktree.',
                LogCategory::WORKTREE->value
            );
            throw new RuntimeException('Failed to save worktree record.');
        }

        return $model;
    }

    /**
     * Sync a worktree with its source branch.
     */
    public function sync(ProjectWorktree $worktree): SyncResult
    {
        $containerPath = $this->getContainerPath($worktree);

        if (!is_dir($containerPath)) {
            return new SyncResult(false, 0, 'Worktree directory does not exist.');
        }

        // Get current commit count before merge to calculate merged count
        $beforeCount = $this->getBehindCount($containerPath, $worktree->source_branch);

        $command = 'git -C ' . escapeshellarg($containerPath)
            . ' merge ' . escapeshellarg($worktree->source_branch) . ' --no-edit';
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $errorMessage = implode("\n", $output);
            Yii::warning("Sync failed for worktree {$worktree->path_suffix}: {$errorMessage}", LogCategory::WORKTREE->value);

            // Abort the failed merge to restore clean state
            exec('git -C ' . escapeshellarg($containerPath) . ' merge --abort 2>&1');

            return new SyncResult(
                false,
                0,
                'Merge conflict detected. Resolve manually: cd ' . $this->getHostPath($worktree) . ' && git merge --abort'
            );
        }

        return new SyncResult(true, max(0, $beforeCount));
    }

    /**
     * Get live status for a single worktree.
     */
    public function getStatus(ProjectWorktree $worktree): WorktreeStatus
    {
        $containerPath = $this->getContainerPath($worktree);
        $hostPath = $this->getHostPath($worktree);
        $directoryExists = is_dir($containerPath);
        $behindCount = 0;

        if ($directoryExists) {
            $behindCount = $this->getBehindCount($containerPath, $worktree->source_branch);
        }

        return new WorktreeStatus(
            $worktree->id,
            $directoryExists,
            $containerPath,
            $hostPath,
            $worktree->branch,
            $worktree->source_branch,
            WorktreePurpose::from($worktree->purpose),
            $behindCount
        );
    }

    /**
     * Get status for all worktrees belonging to a project.
     *
     * @return WorktreeStatus[]
     */
    public function getStatusForProject(Project $project): array
    {
        $worktrees = ProjectWorktree::find()->forProject($project->id)->all();
        $statuses = [];

        foreach ($worktrees as $worktree) {
            $statuses[] = $this->getStatus($worktree);
        }

        return $statuses;
    }

    /**
     * Remove a worktree (git + DB record).
     *
     * @throws RuntimeException
     */
    public function remove(ProjectWorktree $worktree): bool
    {
        $containerPath = $this->getContainerPath($worktree);
        $rootPath = $this->getTranslatedRootPath($worktree->project);

        if ($rootPath !== null && is_dir($containerPath)) {
            $command = 'git -C ' . escapeshellarg($rootPath)
                . ' worktree remove ' . escapeshellarg($containerPath) . ' --force';
            $this->execGit($command, 'Failed to remove worktree. Check if it is locked.');
        }

        $worktree->delete();

        return true;
    }

    /**
     * Clean up a DB record for a worktree whose directory no longer exists.
     *
     * @throws RuntimeException
     */
    public function cleanup(ProjectWorktree $worktree): bool
    {
        $containerPath = $this->getContainerPath($worktree);

        if (is_dir($containerPath)) {
            throw new RuntimeException(
                'Cannot clean up: worktree directory still exists. Use "Remove" to delete both the directory and the record.'
            );
        }

        // Also prune the git worktree reference if the root repo is accessible
        $rootPath = $this->getTranslatedRootPath($worktree->project);
        if ($rootPath !== null && is_dir($rootPath)) {
            exec('git -C ' . escapeshellarg($rootPath) . ' worktree prune 2>&1');
        }

        $worktree->delete();

        return true;
    }

    /**
     * Re-create a worktree from an existing DB record (directory was removed).
     *
     * @throws RuntimeException
     */
    public function recreate(ProjectWorktree $worktree): ProjectWorktree
    {
        $containerPath = $this->getContainerPath($worktree);

        if (is_dir($containerPath)) {
            throw new RuntimeException('Cannot re-create: worktree directory already exists.');
        }

        $rootPath = $this->getTranslatedRootPath($worktree->project);
        if ($rootPath === null) {
            throw new RuntimeException('Project has no root directory configured.');
        }

        // Prune stale worktree references first
        exec('git -C ' . escapeshellarg($rootPath) . ' worktree prune 2>&1');

        // git worktree add <path> <branch> (without -b, branch already exists)
        $command = 'git -C ' . escapeshellarg($rootPath)
            . ' worktree add ' . escapeshellarg($containerPath)
            . ' ' . escapeshellarg($worktree->branch);
        $this->execGit($command, 'Failed to re-create worktree. Branch may not exist or path conflict.');

        // Merge source branch
        $this->mergeSourceBranch($containerPath, $worktree->source_branch, $worktree->path_suffix);

        // Touch updated_at
        $worktree->updateAttributes(['updated_at' => date('Y-m-d H:i:s')]);

        return $worktree;
    }

    /**
     * Check whether a project's root directory is a git repository.
     */
    public function isGitRepo(Project $project): bool
    {
        $rootPath = $this->getTranslatedRootPath($project);
        if ($rootPath === null || !is_dir($rootPath)) {
            return false;
        }

        exec('git -C ' . escapeshellarg($rootPath) . ' rev-parse --git-dir 2>&1', $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Get the worktree filesystem path for a project + suffix, translated to container context.
     */
    public function getWorktreePath(Project $project, string $suffix): ?string
    {
        if (!$project->root_directory) {
            return null;
        }

        $translated = $this->pathService->translatePath($project->root_directory);
        if ($translated === $project->root_directory && !is_dir($translated)) {
            return null;
        }

        return rtrim($translated, '/') . '-' . $suffix;
    }

    /**
     * Get the container path for a worktree (translated via PathService).
     */
    public function getContainerPath(ProjectWorktree $worktree): string
    {
        $fullPath = $worktree->getFullPath();
        if ($fullPath === null) {
            return '';
        }

        return $this->pathService->translatePath($fullPath);
    }

    /**
     * Get the host path (original, untranslated) for display purposes.
     */
    private function getHostPath(ProjectWorktree $worktree): string
    {
        return $worktree->getFullPath() ?? '';
    }

    /**
     * Get the translated root path for a project.
     */
    private function getTranslatedRootPath(Project $project): ?string
    {
        if (!$project->root_directory) {
            return null;
        }

        return $this->pathService->translatePath($project->root_directory);
    }

    /**
     * Attempt a non-fatal merge of the source branch into a worktree directory.
     */
    private function mergeSourceBranch(string $worktreePath, string $sourceBranch, string $context): void
    {
        $command = 'git -C ' . escapeshellarg($worktreePath)
            . ' merge ' . escapeshellarg($sourceBranch) . ' --no-edit';
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            Yii::warning(
                "Merge failed for worktree {$context}: " . implode("\n", $output),
                LogCategory::WORKTREE->value
            );
        }
    }

    /**
     * Get the number of commits a worktree is behind its source branch.
     */
    private function getBehindCount(string $containerPath, string $sourceBranch): int
    {
        exec(
            'git -C ' . escapeshellarg($containerPath)
            . ' rev-list --count HEAD..' . escapeshellarg($sourceBranch) . ' 2>&1',
            $output,
            $returnCode
        );

        if ($returnCode !== 0 || !isset($output[0])) {
            return 0;
        }

        return (int) $output[0];
    }

    /**
     * Execute a git command, throwing on failure.
     *
     * @throws RuntimeException
     */
    private function execGit(string $command, string $failureMessage = 'Git command failed.'): array
    {
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $errorMessage = implode("\n", $output);
            Yii::warning("Git command failed: {$command} — {$errorMessage}", LogCategory::WORKTREE->value);
            throw new RuntimeException($failureMessage);
        }

        return $output;
    }
}
