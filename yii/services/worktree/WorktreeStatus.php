<?php

namespace app\services\worktree;

use common\enums\WorktreePurpose;

/**
 * Live status of a worktree (filesystem + git state).
 */
class WorktreeStatus
{
    public function __construct(
        public readonly int $worktreeId,
        public readonly bool $directoryExists,
        public readonly string $containerPath,
        public readonly string $hostPath,
        public readonly string $branch,
        public readonly string $sourceBranch,
        public readonly WorktreePurpose $purpose,
        public readonly int $behindSourceCount,
    ) {}
}
