<?php

namespace app\commands;

use app\models\Project;
use app\services\ai\AiConfigProviderInterface;
use app\services\ai\AiProviderInterface;
use app\services\ai\AiWorkspaceProviderInterface;
use RuntimeException;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use Throwable;

/**
 * Manages AI workspace directories and diagnostics.
 *
 * Usage:
 *   ./yii ai/sync-workspaces    Sync all project workspaces
 *   ./yii ai/diagnose           Check AI CLI setup and path mappings
 */
class AiController extends Controller
{
    private AiProviderInterface $aiProvider;

    public function __construct(
        $id,
        $module,
        ?AiProviderInterface $aiProvider = null,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->aiProvider = $aiProvider ?? Yii::$container->get(AiProviderInterface::class);
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
                if (!$this->aiProvider instanceof AiWorkspaceProviderInterface) {
                    throw new RuntimeException('Provider does not support workspace management');
                }
                $this->aiProvider->syncConfig($project);
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

    /**
     * Checks Claude CLI setup: binary availability, PATH_MAPPINGS, and project directory accessibility.
     */
    public function actionDiagnose(): int
    {
        $this->stdout("Claude CLI Diagnostics\n", Console::FG_CYAN);
        $this->stdout(str_repeat('=', 40) . "\n\n");
        $hasErrors = false;

        // 1. Claude CLI binary
        $this->stdout("1. Claude CLI binary\n", Console::BOLD);
        $claudePath = trim((string) shell_exec('which claude 2>/dev/null'));
        if ($claudePath !== '') {
            $this->stdout("  [OK] ", Console::FG_GREEN);
            $this->stdout("Found at {$claudePath}\n");
        } else {
            $this->stderr("  [ERR] ", Console::FG_RED);
            $this->stderr("Claude CLI not found in PATH\n");
            $hasErrors = true;
        }

        // 2. PATH_MAPPINGS
        $this->stdout("\n2. PATH_MAPPINGS configuration\n", Console::BOLD);
        $mappings = Yii::$app->params['pathMappings'] ?? [];
        if (empty($mappings)) {
            $this->stderr("  [ERR] ", Console::FG_RED);
            $this->stderr("PATH_MAPPINGS is empty — host paths won't be translated\n");
            $hasErrors = true;
        } else {
            foreach ($mappings as $hostPrefix => $containerPrefix) {
                $accessible = is_dir($containerPrefix);
                if ($accessible) {
                    $this->stdout("  [OK] ", Console::FG_GREEN);
                    $this->stdout("{$hostPrefix} → {$containerPrefix}\n");
                } else {
                    $this->stderr("  [ERR] ", Console::FG_RED);
                    $this->stderr("{$hostPrefix} → {$containerPrefix} (container path not accessible)\n");
                    $hasErrors = true;
                }
            }
        }

        // 3. Per-project directory status
        $this->stdout("\n3. Project directory status\n", Console::BOLD);
        $projects = Project::find()
            ->andWhere(['not', ['root_directory' => null]])
            ->andWhere(['!=', 'root_directory', ''])
            ->limit(10)
            ->all();

        if (empty($projects)) {
            $this->stdout("  No projects with root_directory configured.\n");
        }

        if (!empty($projects) && !$this->aiProvider instanceof AiConfigProviderInterface) {
            $this->stderr("  [ERR] Provider does not support config checking\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($projects as $project) {
            $status = $this->aiProvider->checkConfig($project->root_directory);
            $label = match ($status['pathStatus']) {
                'has_config' => ['  [OK] ', Console::FG_GREEN],
                'no_config' => ['  [--] ', Console::FG_YELLOW],
                'not_mapped' => ['  [ERR] ', Console::FG_RED],
                'not_accessible' => ['  [ERR] ', Console::FG_RED],
                default => ['  [??] ', Console::FG_YELLOW],
            };
            $this->stdout($label[0], $label[1]);
            $this->stdout("Project {$project->id} ({$project->name}): {$status['pathStatus']}");
            if ($status['pathMapped']) {
                $this->stdout(" [{$status['requestedPath']} → {$status['effectivePath']}]");
            }
            $this->stdout("\n");

            if (in_array($status['pathStatus'], ['not_mapped', 'not_accessible'], true)) {
                $hasErrors = true;
            }
        }

        $this->stdout("\n");

        if ($hasErrors) {
            $this->stdout("Diagnostics complete — issues found.\n", Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Diagnostics complete — all checks passed.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }
}
