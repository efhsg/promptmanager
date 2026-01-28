<?php

namespace app\commands;

use app\services\EntityPermissionService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Permission cache management commands.
 */
class PermissionCacheController extends Controller
{
    /**
     * Warms the permission cache for all entities.
     *
     * This prevents intermittent 403 errors caused by incomplete permission maps
     * being built during high-load scenarios. Run after deployments or RBAC changes.
     *
     * Usage: yii permission-cache/warm
     *
     * @return int Exit code
     */
    public function actionWarm(): int
    {
        $this->stdout("Warming permission cache...\n");

        $entities = Yii::$app->params['rbac']['entities'] ?? [];
        if (empty($entities)) {
            $this->stderr("No RBAC entities found in configuration.\n");
            return ExitCode::CONFIG;
        }

        /** @var EntityPermissionService $service */
        $service = Yii::$app->get('permissionService');

        $total = 0;
        foreach (array_keys($entities) as $entityName) {
            $map = $service->getActionPermissionMap($entityName);
            $count = count($map);
            $total += $count;
            $this->stdout("  $entityName: $count actions cached\n");
        }

        $this->stdout("Done. Cached $total total action permissions.\n");

        return ExitCode::OK;
    }

    /**
     * Validates that all configured RBAC permissions exist in the database.
     *
     * Use this to diagnose permission issues without modifying the cache.
     *
     * Usage: yii permission-cache/validate
     *
     * @return int Exit code
     */
    public function actionValidate(): int
    {
        $this->stdout("Validating RBAC permissions...\n");

        $entities = Yii::$app->params['rbac']['entities'] ?? [];
        if (empty($entities)) {
            $this->stderr("No RBAC entities found in configuration.\n");
            return ExitCode::CONFIG;
        }

        $auth = Yii::$app->authManager;
        $missingCount = 0;

        foreach ($entities as $entityName => $entityConfig) {
            $permissions = $entityConfig['permissions'] ?? [];
            $missing = [];

            foreach (array_keys($permissions) as $permissionName) {
                if ($auth->getPermission($permissionName) === null) {
                    $missing[] = $permissionName;
                    $missingCount++;
                }
            }

            if (empty($missing)) {
                $this->stdout("  $entityName: OK (" . count($permissions) . " permissions)\n");
            } else {
                $this->stderr("  $entityName: MISSING " . implode(', ', $missing) . "\n");
            }
        }

        if ($missingCount > 0) {
            $this->stderr("\nValidation failed: $missingCount permissions missing.\n");
            $this->stderr("Run 'yii rbac/init' to initialize RBAC.\n");
            return ExitCode::DATAERR;
        }

        $this->stdout("\nAll permissions validated successfully.\n");
        return ExitCode::OK;
    }
}
