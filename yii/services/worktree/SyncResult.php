<?php

namespace app\services\worktree;

/**
 * Result of a worktree sync (merge) operation.
 */
class SyncResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $commitsMerged,
        public readonly ?string $errorMessage = null,
    ) {}
}
