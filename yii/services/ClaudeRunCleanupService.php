<?php

namespace app\services;

use app\models\ClaudeRun;
use Yii;

/**
 * Handles deletion of ClaudeRun records and their associated stream files.
 */
class ClaudeRunCleanupService
{
    /**
     * Deletes all terminal runs belonging to the same session as the representative run.
     * For standalone runs (no session_id), deletes only that single run if terminal.
     *
     * @return int number of deleted records
     */
    public function deleteSession(ClaudeRun $representativeRun): int
    {
        if ($representativeRun->session_id !== null) {
            $runs = ClaudeRun::find()
                ->forUser($representativeRun->user_id)
                ->forSession($representativeRun->session_id)
                ->terminal()
                ->all();
        } else {
            $runs = $representativeRun->isTerminal() ? [$representativeRun] : [];
        }

        return $this->deleteRunsWithCleanup($runs);
    }

    /**
     * Deletes all terminal runs for the given user.
     *
     * @return int number of deleted records
     */
    public function bulkCleanup(int $userId): int
    {
        $runs = ClaudeRun::find()
            ->forUser($userId)
            ->terminal()
            ->all();

        return $this->deleteRunsWithCleanup($runs);
    }

    /**
     * Counts distinct deletable sessions (unique session_ids + standalone terminal runs).
     */
    public function countTerminalSessions(int $userId): int
    {
        $query = ClaudeRun::find()
            ->forUser($userId)
            ->terminal();

        $sessionCount = (int) (clone $query)
            ->andWhere(['IS NOT', 'session_id', null])
            ->select('session_id')
            ->distinct()
            ->count();

        $standaloneCount = (int) (clone $query)
            ->andWhere(['session_id' => null])
            ->count();

        return $sessionCount + $standaloneCount;
    }

    /**
     * Counts the total number of deletable runs.
     */
    public function countTerminalRuns(int $userId): int
    {
        return (int) ClaudeRun::find()
            ->forUser($userId)
            ->terminal()
            ->count();
    }

    /**
     * Deletes stream files and DB records in a transaction.
     *
     * Stream files are deleted first (idempotent). DB deletes are wrapped in a transaction.
     *
     * @param ClaudeRun[] $runs
     * @return int number of deleted records
     */
    private function deleteRunsWithCleanup(array $runs): int
    {
        if ($runs === []) {
            return 0;
        }

        // Delete stream files first (idempotent — orphans are acceptable)
        foreach ($runs as $run) {
            $path = $run->getStreamFilePath();
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        // Delete DB records in transaction
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $deleted = 0;
            foreach ($runs as $run) {
                $run->delete();
                $deleted++;
            }
            $transaction->commit();

            return $deleted;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error('Failed to delete ClaudeRun records: ' . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }
}
