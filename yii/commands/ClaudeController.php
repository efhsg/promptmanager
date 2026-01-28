<?php

namespace app\commands;

use app\models\Project;
use app\services\ClaudeWorkspaceService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use Throwable;

/**
 * Manages Claude workspace directories for projects.
 *
 * Usage:
 *   ./yii claude/sync-workspaces    Sync all project workspaces
 */
class ClaudeController extends Controller
{
    private ClaudeWorkspaceService $workspaceService;

    public function __construct($id, $module, ?ClaudeWorkspaceService $workspaceService = null, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->workspaceService = $workspaceService ?? new ClaudeWorkspaceService();
    }

    /**
     * Syncs Claude workspace directories for all projects.
     *
     * Creates or updates managed workspace directories containing CLAUDE.md
     * and .claude/settings.local.json for each project.
     */
    public function actionSyncWorkspaces(): int
    {
        $this->stdout("Syncing Claude workspaces for all projects...\n\n", Console::FG_CYAN);

        $projects = Project::find()->all();
        $count = 0;
        $errors = 0;

        foreach ($projects as $project) {
            try {
                $this->workspaceService->syncConfig($project);
                $this->stdout("  [OK] ", Console::FG_GREEN);
                $this->stdout("Project {$project->id}: {$project->name}\n");
                $count++;
            } catch (Throwable $e) {
                $this->stderr("  [ERR] ", Console::FG_RED);
                $this->stderr("Project {$project->id}: {$e->getMessage()}\n");
                $errors++;
            }
        }

        $this->stdout("\n");

        if ($errors > 0) {
            $this->stdout("Done. Synced {$count} workspaces with {$errors} errors.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Done. Synced {$count} workspaces.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }
}
