<?php

namespace app\commands;

use app\services\projectload\DumpImporter;
use app\services\projectload\EntityConfig;
use app\services\projectload\LoadReport;
use app\services\projectload\ProjectLoadService;
use app\services\projectload\SchemaInspector;
use Exception;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Load projects from a MySQL dump file.
 *
 * Usage:
 *   ./yii project-load/list <dump-file>                          List projects in dump
 *   ./yii project-load/load <dump-file> --project-ids=5,8,12     Load specific projects
 *   ./yii project-load/load <dump-file> --project-ids=5 --dry-run   Preview without changes
 *   ./yii project-load/cleanup                                    Clean orphaned temp schemas
 *
 * Options:
 *   --project-ids=N,N,...           Project IDs to load from dump
 *   --local-project-ids=N,N,...     Explicit local project ID mapping
 *   --user-id=N                    Target user ID (default: 1)
 *   --dry-run                      Preview changes without applying
 *   --include-global-fields        Include referenced global fields
 */
class ProjectLoadController extends Controller
{
    public string $projectIds = '';
    public string $localProjectIds = '';
    public int $userId = 1;
    public bool $dryRun = false;
    public bool $includeGlobalFields = false;

    private ProjectLoadService $service;

    public function __construct(
        $id,
        $module,
        ?ProjectLoadService $service = null,
        $config = []
    ) {
        parent::__construct($id, $module, $config);

        if ($service === null) {
            $db = Yii::$app->db;
            $inspector = new SchemaInspector($db);
            $importer = new DumpImporter($db, $inspector);
            $service = new ProjectLoadService($db, $importer, $inspector);
        }

        $this->service = $service;
    }

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'load') {
            $options = array_merge($options, [
                'projectIds',
                'localProjectIds',
                'userId',
                'dryRun',
                'includeGlobalFields',
            ]);
        }

        return $options;
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            'p' => 'projectIds',
            'l' => 'localProjectIds',
            'u' => 'userId',
            'd' => 'dryRun',
            'g' => 'includeGlobalFields',
        ]);
    }

    /**
     * List projects available in a dump file.
     *
     * @param string $dumpFile Path to the MySQL dump file
     */
    public function actionList(string $dumpFile): int
    {
        try {
            $result = $this->service->listProjects($dumpFile);
            $this->printProjectList($result['projects'], $result['entityCounts']);
            return ExitCode::OK;
        } catch (Exception $e) {
            $this->stderr("Fout: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Load projects from a dump file.
     *
     * @param string $dumpFile Path to the MySQL dump file
     */
    public function actionLoad(string $dumpFile): int
    {
        if (empty($this->projectIds)) {
            $this->stderr("Fout: --project-ids is verplicht\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        $projectIds = $this->parseIntList($this->projectIds);
        if ($projectIds === null) {
            $this->stderr("Fout: --project-ids bevat ongeldige waarden\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        $localProjectIds = [];
        if (!empty($this->localProjectIds)) {
            $localProjectIds = $this->parseIntList($this->localProjectIds);
            if ($localProjectIds === null) {
                $this->stderr("Fout: --local-project-ids bevat ongeldige waarden\n", Console::FG_RED);
                return ExitCode::USAGE;
            }
        }

        $mode = $this->dryRun ? '[DRY-RUN] ' : '';
        $this->stdout(
            "{$mode}Laden van " . count($projectIds) . " project(en) voor user {$this->userId}...\n\n",
            Console::FG_CYAN
        );

        try {
            $report = $this->service->load(
                $dumpFile,
                $projectIds,
                $this->userId,
                $this->dryRun,
                $this->includeGlobalFields,
                $localProjectIds
            );

            $this->printLoadReport($report);

            return $report->hasErrors() ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
        } catch (Exception $e) {
            $this->stderr("Fout: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Clean up orphaned temporary schemas.
     */
    public function actionCleanup(): int
    {
        try {
            $cleaned = $this->service->cleanup();

            if (empty($cleaned)) {
                $this->stdout("Geen orphaned schemas gevonden.\n", Console::FG_GREEN);
                return ExitCode::OK;
            }

            $this->stdout("Orphaned schemas opgeruimd:\n", Console::BOLD);
            foreach ($cleaned as $schema => $age) {
                $this->stdout("  {$schema} — verwijderd ({$age})\n");
            }
            $this->stdout("\nTotaal: " . count($cleaned) . " schemas verwijderd\n", Console::FG_GREEN);

            return ExitCode::OK;
        } catch (Exception $e) {
            $this->stderr("Fout: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    private function printProjectList(array $projects, array $entityCounts): void
    {
        if (empty($projects)) {
            $this->stdout("Geen projecten gevonden in dump.\n", Console::FG_YELLOW);
            return;
        }

        $this->stdout("Projecten in dump:\n\n", Console::BOLD);

        $listColumns = EntityConfig::getListColumns();

        // Header
        $header = sprintf(
            "  %4s │ %-20s",
            'ID',
            'Naam'
        );
        foreach ($listColumns as $label) {
            $header .= sprintf(' │ %5s', $label);
        }
        $this->stdout($header . "\n");
        $this->stdout("  " . str_repeat('─', 4) . "┼" . str_repeat('─', 22));
        foreach ($listColumns as $label) {
            $this->stdout("┼" . str_repeat('─', 7));
        }
        $this->stdout("\n");

        // Rows
        foreach ($projects as $project) {
            $id = (int) $project['id'];
            $name = mb_substr($project['name'], 0, 20);
            $softDeleted = $project['deleted_at'] !== null;

            $row = sprintf("  %4d │ %-20s", $id, $name);
            foreach ($listColumns as $entity => $label) {
                $count = $entityCounts[$id][$entity] ?? '-';
                $row .= sprintf(' │ %5s', $count);
            }

            if ($softDeleted) {
                $this->stdout($row . "  (soft-deleted)\n", Console::FG_GREY);
            } else {
                $this->stdout($row . "\n");
            }
        }

        $this->stdout("\nTotaal: " . count($projects) . " projecten\n");
    }

    private function printLoadReport(LoadReport $report): void
    {
        foreach ($report->getGlobalWarnings() as $warning) {
            $this->stderr("⚠ {$warning}\n", Console::FG_YELLOW);
        }

        foreach ($report->getProjects() as $dumpId => $project) {
            $this->stdout("\n");
            $this->stdout(str_repeat('═', 55) . "\n");
            $this->stdout(
                "Project: \"{$project['name']}\" (dump ID: {$dumpId})\n",
                Console::BOLD
            );
            $this->stdout(str_repeat('─', 55) . "\n");

            if ($project['isReplacement'] && $project['localProjectId']) {
                $this->stdout("Lokale match: ID {$project['localProjectId']} → WORDT VERWIJDERD\n");
            } elseif (!$project['isReplacement'] && $project['status'] !== 'error' && $project['status'] !== 'skipped') {
                $this->stdout("Lokale match: GEEN → nieuw project\n");
            }

            // Status
            match ($project['status']) {
                'success' => $this->stdout("Status: " . Console::ansiFormat("SUCCES", [Console::FG_GREEN]) . "\n"),
                'error' => $this->stdout("Status: " . Console::ansiFormat("FOUT", [Console::FG_RED]) . "\n"),
                'skipped' => $this->stdout("Status: " . Console::ansiFormat("OVERGESLAGEN", [Console::FG_YELLOW]) . "\n"),
                'dry-run' => $this->stdout("Status: DRY-RUN\n"),
                default => null,
            };

            // Deleted entities
            if (!empty($project['deleted'])) {
                $this->stdout("\n  Wordt verwijderd (lokaal):\n");
                foreach ($project['deleted'] as $entity => $count) {
                    $this->stdout("    {$count} {$entity}\n");
                }
            }

            // Inserted entities
            if (!empty($project['inserted'])) {
                $label = $project['status'] === 'dry-run' ? 'Wordt geladen (uit dump)' : 'Geladen';
                $this->stdout("\n  {$label}:\n");
                foreach ($project['inserted'] as $entity => $count) {
                    $this->stdout("    {$count} {$entity}\n");
                }
            }

            // Error
            if ($project['error']) {
                $this->stderr("\n  Fout: {$project['error']}\n", Console::FG_RED);
            }

            // Warnings
            if (!empty($project['warnings'])) {
                $this->stdout("\n  Waarschuwingen:\n");
                foreach ($project['warnings'] as $warning) {
                    $this->stderr("    ⚠ {$warning}\n", Console::FG_YELLOW);
                }
            }
        }

        // Summary
        $this->stdout("\n" . str_repeat('═', 55) . "\n");
        $total = count($report->getProjects());
        $success = $report->getSuccessCount();
        $errors = $report->getErrorCount();
        $skipped = $report->getSkippedCount();
        $replacements = $report->getReplacementCount();
        $new = $report->getNewCount();

        $parts = ["{$total} projecten"];
        if ($replacements > 0) {
            $parts[] = "{$replacements} vervangen";
        }
        if ($new > 0) {
            $parts[] = "{$new} nieuw";
        }
        if ($skipped > 0) {
            $parts[] = "{$skipped} overgeslagen";
        }
        if ($errors > 0) {
            $parts[] = Console::ansiFormat("{$errors} fouten", [Console::FG_RED]);
        }

        $this->stdout("Samenvatting: " . implode(', ', $parts) . "\n");
    }

    /**
     * @return int[]|null
     */
    private function parseIntList(string $input): ?array
    {
        $parts = array_map('trim', explode(',', $input));
        $ids = [];

        foreach ($parts as $part) {
            if ($part === '' || !ctype_digit($part)) {
                return null;
            }
            $ids[] = (int) $part;
        }

        return $ids;
    }
}
