<?php

namespace app\services;

use Yii;
use yii\base\Component;
use yii\caching\TagDependency;
use yii\db\ActiveRecord;

class EntityPermissionService extends Component
{
    private const CACHE_DURATION = 3600;
    private const CACHE_TAG = 'entity_permissions';

    public function getActionPermissionMap(string $entityName): array
    {
        $cacheKey = "action_permission_map_{$entityName}";
        return Yii::$app->cache->getOrSet(
            $cacheKey,
            fn() => $this->buildActionPermissionMap($entityName),
            self::CACHE_DURATION,
            new TagDependency(['tags' => [self::CACHE_TAG]])
        );
    }

    public function checkPermission(string $permissionName, ?ActiveRecord $model = null): bool
    {
        return $model
            ? Yii::$app->user->can($permissionName, ['model' => $model])
            : Yii::$app->user->can($permissionName);
    }

    private function buildActionPermissionMap(string $entityName): array
    {
        $prefix = ucfirst($entityName);
        $map = [];
        $actions = ['create', 'view', 'update', 'delete'];
        foreach ($actions as $action) {
            $permissionName = $action . $prefix;
            if ($this->permissionExists($permissionName)) {
                $map[$action] = $permissionName;
            }
        }
        return $map;
    }

    private function permissionExists(string $permissionName): bool
    {
        return Yii::$app->authManager->getPermission($permissionName) !== null;
    }

    public function invalidateCache(): void
    {
        TagDependency::invalidate(Yii::$app->cache, [self::CACHE_TAG]);
    }
}
