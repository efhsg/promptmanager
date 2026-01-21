<?php

namespace app\services\sync;

/**
 * Resolves conflicts between source and destination records using last-write-wins.
 */
class ConflictResolver
{
    /**
     * Determines which record should be used based on updated_at timestamp.
     *
     * @return 'source'|'destination'|'equal'
     */
    public function resolve(?string $sourceUpdatedAt, ?string $destUpdatedAt): string
    {
        if ($sourceUpdatedAt === null && $destUpdatedAt === null) {
            return 'equal';
        }

        if ($sourceUpdatedAt === null) {
            return 'destination';
        }

        if ($destUpdatedAt === null) {
            return 'source';
        }

        $sourceTime = strtotime($sourceUpdatedAt);
        $destTime = strtotime($destUpdatedAt);

        if ($sourceTime > $destTime) {
            return 'source';
        }

        if ($destTime > $sourceTime) {
            return 'destination';
        }

        // Equal timestamps: source wins (arbitrary but consistent)
        return 'source';
    }

    /**
     * Checks if source record is newer than destination.
     */
    public function isSourceNewer(?string $sourceUpdatedAt, ?string $destUpdatedAt): bool
    {
        $result = $this->resolve($sourceUpdatedAt, $destUpdatedAt);
        return $result === 'source' || $result === 'equal';
    }
}
