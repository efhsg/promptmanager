<?php

namespace app\services;

use Yii;
use yii\base\Component;
use yii\caching\TagDependency;
use yii\db\ActiveRecord;
use yii\rbac\ManagerInterface;

class EntityPermissionService extends Component
{
    private const CACHE_DURATION = 3600;
    private const CACHE_TAG = 'user_permissions';
    private const RBAC_VERSION_KEY = 'rbac_version';

    /**
     * Returns the permission mapping for the given entity.
     * The map is cached with a tag dependency.
     *
     * @param string $entityName
     * @return array
     */
    public function getActionPermissionMap(string $entityName): array
    {
        $cacheKey = "action_permission_map_$entityName";
        return Yii::$app->cache->getOrSet(
            $cacheKey,
            fn() => $this->buildActionPermissionMap($entityName),
            self::CACHE_DURATION,
            new TagDependency(['tags' => [self::CACHE_TAG]])
        );
    }

    /**
     * Checks the permission for the current user.
     * If a model is provided, it uses its primary key (or object hash as fallback)
     * to generate a unique cache key. The RBAC version is also included in the key
     * so that any RBAC change invalidates all cached permission checks.
     *
     * @param string $permissionName The permission name to check.
     * @param ActiveRecord|null $model Optional model context.
     * @return bool
     */
    public function checkPermission(string $permissionName, ?ActiveRecord $model = null): bool
    {
        $userId = Yii::$app->user->id;
        $modelKey = '';

        if ($model !== null) {
            $primaryKey = $model->getPrimaryKey();
            if ($primaryKey !== null) {
                $modelKey = is_array($primaryKey) ? '_model_' . implode('_', $primaryKey) : '_model_' . $primaryKey;
            } else {
                // Fall back when model is not persisted yet.
                $modelKey = '_model_' . spl_object_hash($model);
            }
        }

        // Include the current RBAC version in the cache key.
        $rbacVersion = $this->getRbacVersion();
        $cacheKey = "permission_check_{$userId}_$permissionName{$modelKey}_$rbacVersion";
        $cache = Yii::$app->cache;

        if (($cached = $cache->get($cacheKey)) !== false) {
            return $cached;
        }

        $result = $model
            ? Yii::$app->user->can($permissionName, ['model' => $model])
            : Yii::$app->user->can($permissionName);

        $cache->set($cacheKey, $result, self::CACHE_DURATION, new TagDependency(['tags' => [self::CACHE_TAG]]));
        return $result;
    }

    /**
     * Helper method to build a permission map for an entity.
     *
     * @param string $entityName
     * @return array
     */
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

    /**
     * Checks if a given permission exists in the RBAC system.
     *
     * @param string $permissionName
     * @return bool
     */
    private function permissionExists(string $permissionName): bool
    {
        return Yii::$app->authManager->getPermission($permissionName) !== null;
    }

    /**
     * Invalidates all cached permission checks by invalidating the tag dependency
     * and updating the RBAC version.
     */
    public static function invalidatePermissionCache(): void
    {
        TagDependency::invalidate(Yii::$app->cache, self::CACHE_TAG);
        Yii::$app->cache->set(self::RBAC_VERSION_KEY, time());
    }

    /**
     * Retrieves the current RBAC version. If not set, initializes it.
     *
     * @return int
     */
    private function getRbacVersion(): int
    {
        $version = Yii::$app->cache->get(self::RBAC_VERSION_KEY);
        if ($version === false) {
            $version = time();
            Yii::$app->cache->set(self::RBAC_VERSION_KEY, $version);
        }
        return $version;
    }

    /**
     * Revokes all RBAC assignments for the given user ID.
     * This method wraps the native authManager call and ensures that
     * the permission cache is invalidated immediately after.
     *
     * @param int $userId
     * @return void
     */
    public function revokeAllUserPermissions(int $userId): void
    {
        $auth = Yii::$app->authManager;
        if ($auth instanceof ManagerInterface) {
            $auth->revokeAll($userId);
            self::invalidatePermissionCache();
        }
    }
}
