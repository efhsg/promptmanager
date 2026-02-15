<?php

namespace app\commands;

use app\models\ClaudeRun;
use common\enums\ClaudeRunStatus;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Manages Claude run maintenance tasks.
 *
 * Usage:
 *   ./yii claude-run/cleanup-stale    Mark stale runs as failed
 *   ./yii claude-run/cleanup-files    Remove old stream files
 */
class ClaudeRunController extends Controller
{
    /**
     * Marks stale running runs as failed.
     *
     * A run is considered stale when its updated_at is older than
     * the threshold and its status is still 'running'.
     *
     * @param int $thresholdMinutes Minutes after which a running run is considered stale
     */
    public function actionCleanupStale(int $thresholdMinutes = 5): int
    {
        $this->stdout("Checking for stale Claude runs (threshold: {$thresholdMinutes} min)...\n", Console::FG_CYAN);

        $staleRuns = ClaudeRun::find()->stale($thresholdMinutes)->all();

        if ($staleRuns === []) {
            $this->stdout("No stale runs found.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $count = 0;
        foreach ($staleRuns as $run) {
            $run->markFailed('Worker heartbeat timeout — run marked as failed by cleanup.');
            $this->stdout("  Marked run {$run->id} as failed (last heartbeat: {$run->updated_at})\n", Console::FG_YELLOW);
            $count++;
        }

        $this->stdout("Done. Marked {$count} stale run(s) as failed.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Removes stream files older than the given threshold.
     *
     * @param int $maxAgeHours Maximum age of stream files in hours
     */
    public function actionCleanupFiles(int $maxAgeHours = 24): int
    {
        $this->stdout("Cleaning up stream files older than {$maxAgeHours}h...\n", Console::FG_CYAN);

        $directory = Yii::getAlias('@app/storage/claude-runs');
        if (!is_dir($directory)) {
            $this->stdout("Stream directory does not exist, nothing to clean.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $threshold = time() - ($maxAgeHours * 3600);
        $files = glob($directory . '/*.ndjson');
        if ($files === false) {
            return ExitCode::OK;
        }

        $deleted = 0;
        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                if (unlink($file)) {
                    $deleted++;
                } else {
                    $this->stderr("  Failed to delete: {$file}\n", Console::FG_RED);
                }
            }
        }

        $this->stdout("Done. Deleted {$deleted} old stream file(s).\n", Console::FG_GREEN);
        return ExitCode::OK;
    }
}
