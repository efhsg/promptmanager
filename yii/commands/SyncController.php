<?php

namespace app\commands;

use app\services\sync\SyncService;
use Exception;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Database sync between local and remote (zenbook via Tailscale).
 *
 * Usage:
 *   ./yii sync/status           Preview what would sync
 *   ./yii sync/pull             Pull from remote (zenbook) to local
 *   ./yii sync/push             Push from local to remote (zenbook)
 *   ./yii sync/run              Bidirectional sync (pull then push)
 *
 * Options:
 *   --dry-run                   Preview changes without applying
 *   --user-id=N                 Specify user ID (default: 1)
 */
class SyncController extends Controller
{
    public bool $dryRun = false;
    public int $userId = 1;

    private SyncService $syncService;

    public function __construct($id, $module, ?SyncService $syncService = null, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->syncService = $syncService ?? new SyncService(Yii::$app->db);
    }

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['dryRun', 'userId']);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            'd' => 'dryRun',
            'u' => 'userId',
        ]);
    }

    public function actionStatus(): int
    {
        $this->stdout("Checking sync status for user {$this->userId}...\n\n", Console::FG_CYAN);

        try {
            $status = $this->syncService->status($this->userId);

            $this->stdout("=== PULL (remote -> local) ===\n", Console::BOLD);
            $this->printReport($status['pull']);

            $this->stdout("\n=== PUSH (local -> remote) ===\n", Console::BOLD);
            $this->printReport($status['push']);

            return ExitCode::OK;
        } catch (Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    public function actionPull(): int
    {
        $mode = $this->dryRun ? '[DRY-RUN] ' : '';
        $this->stdout("{$mode}Pulling from remote to local for user {$this->userId}...\n\n", Console::FG_CYAN);

        try {
            $report = $this->syncService->pull($this->userId, $this->dryRun);
            $this->printReport($report->toArray());

            if ($report->hasErrors()) {
                return ExitCode::UNSPECIFIED_ERROR;
            }

            if (!$this->dryRun) {
                $this->stdout("\nPull completed successfully.\n", Console::FG_GREEN);
            }

            return ExitCode::OK;
        } catch (Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    public function actionPush(): int
    {
        $mode = $this->dryRun ? '[DRY-RUN] ' : '';
        $this->stdout("{$mode}Pushing from local to remote for user {$this->userId}...\n\n", Console::FG_CYAN);

        try {
            $report = $this->syncService->push($this->userId, $this->dryRun);
            $this->printReport($report->toArray());

            if ($report->hasErrors()) {
                return ExitCode::UNSPECIFIED_ERROR;
            }

            if (!$this->dryRun) {
                $this->stdout("\nPush completed successfully.\n", Console::FG_GREEN);
            }

            return ExitCode::OK;
        } catch (Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    public function actionRun(): int
    {
        $mode = $this->dryRun ? '[DRY-RUN] ' : '';
        $this->stdout("{$mode}Running bidirectional sync for user {$this->userId}...\n\n", Console::FG_CYAN);

        try {
            $results = $this->syncService->run($this->userId, $this->dryRun);

            $this->stdout("=== PULL (remote -> local) ===\n", Console::BOLD);
            $this->printReport($results['pull']->toArray());

            $this->stdout("\n=== PUSH (local -> remote) ===\n", Console::BOLD);
            $this->printReport($results['push']->toArray());

            $hasErrors = $results['pull']->hasErrors() || $results['push']->hasErrors();
            if ($hasErrors) {
                return ExitCode::UNSPECIFIED_ERROR;
            }

            if (!$this->dryRun) {
                $this->stdout("\nBidirectional sync completed successfully.\n", Console::FG_GREEN);
            }

            return ExitCode::OK;
        } catch (Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    private function printReport(array $report): void
    {
        // Print per-entity stats
        $allEntities = array_unique(array_merge(
            array_keys($report['inserted']),
            array_keys($report['updated']),
            array_keys($report['skipped']),
            array_keys($report['errors'])
        ));
        sort($allEntities);

        if (empty($allEntities)) {
            $this->stdout("No changes.\n", Console::FG_YELLOW);
            return;
        }

        foreach ($allEntities as $entity) {
            $inserted = $report['inserted'][$entity] ?? 0;
            $updated = $report['updated'][$entity] ?? 0;
            $skipped = $report['skipped'][$entity] ?? 0;
            $errors = $report['errors'][$entity] ?? [];

            $parts = [];
            if ($inserted > 0) {
                $parts[] = Console::ansiFormat("+{$inserted}", [Console::FG_GREEN]);
            }
            if ($updated > 0) {
                $parts[] = Console::ansiFormat("~{$updated}", [Console::FG_YELLOW]);
            }
            if ($skipped > 0) {
                $parts[] = Console::ansiFormat("={$skipped}", [Console::FG_GREY]);
            }
            if (count($errors) > 0) {
                $parts[] = Console::ansiFormat("!!" . count($errors), [Console::FG_RED]);
            }

            if (!empty($parts)) {
                $this->stdout(sprintf("  %-25s %s\n", $entity, implode(' ', $parts)));
            }

            // Print errors
            foreach ($errors as $error) {
                $this->stderr("    Error: {$error}\n", Console::FG_RED);
            }
        }

        // Print totals
        $totals = $report['totals'];
        $this->stdout("\n");
        $this->stdout(sprintf(
            "Total: %s inserted, %s updated, %s skipped, %s errors\n",
            Console::ansiFormat($totals['inserted'], [Console::FG_GREEN]),
            Console::ansiFormat($totals['updated'], [Console::FG_YELLOW]),
            $totals['skipped'],
            $totals['errors'] > 0
                ? Console::ansiFormat($totals['errors'], [Console::FG_RED])
                : '0'
        ));
    }
}
