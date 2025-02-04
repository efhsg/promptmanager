<?php /** @noinspection PhpUnusedPrivateMethodInspection */

namespace app\commands;

use Yii;
use yii\base\Exception;
use yii\console\Controller;
use yii\base\InvalidConfigException;
use yii\db\Query;
use yii\rbac\ManagerInterface;

class RbacController extends Controller
{
    private array $rules = [];

    /**
     * @throws InvalidConfigException|Exception
     * @throws \Exception
     */
    public function actionInit(): void
    {
        $auth = Yii::$app->authManager;
        $rbacConfig = Yii::$app->params['rbac'] ?? null;
        if (!$rbacConfig) {
            throw new InvalidConfigException('RBAC configuration not found in params.php');
        }
        $auth->removeAll();
        $this->initializePermissions($auth, $rbacConfig['entities']);
        $this->initializeRoles($auth, $rbacConfig['roles']);
        $this->assignUserRoleToAllUsers($auth);
        echo "RBAC initialization complete.\n";
    }

    /**
     * @throws \Exception
     */
    private function initializePermissions(ManagerInterface $auth, array $entities): void
    {
        foreach ($entities as $entityConfig) {
            $permissions = $entityConfig['permissions'] ?? [];
            foreach ($permissions as $permissionName => $permissionData) {
                $permission = $auth->createPermission($permissionName);
                $permission->description = $permissionData['description'] ?? $permissionName;
                if (!empty($permissionData['rule'])) {
                    $permission->ruleName = $this->getRuleName($permissionData['rule']);
                }
                $auth->add($permission);
            }
        }
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    private function initializeRoles(ManagerInterface $auth, array $roles): void
    {
        foreach ($roles as $roleName => $config) {
            $role = $auth->createRole($roleName);
            $auth->add($role);
        }
        foreach ($roles as $roleName => $config) {
            $role = $auth->getRole($roleName);
            foreach ($config['permissions'] as $permName) {
                $perm = $auth->getPermission($permName);
                if ($perm) {
                    $auth->addChild($role, $perm);
                }
            }
            foreach ($config['children'] as $childRole) {
                $auth->addChild($role, $auth->getRole($childRole));
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function getRuleName(string $ruleClass): string
    {
        if (!isset($this->rules[$ruleClass])) {
            $rule = new $ruleClass();
            Yii::$app->authManager->add($rule);
            $this->rules[$ruleClass] = $rule->name;
        }
        return $this->rules[$ruleClass];
    }

    /**
     * @throws \Exception
     */
    private function assignUserRoleToAllUsers(ManagerInterface $auth): void
    {
        $userRole = $auth->getRole('user');
        if (!$userRole) {
            return;
        }

        if (!$userRole) {
            echo "Warning: 'user' role not found. Skipping user role assignment.\n";
            return;
        }

        $userIds = (new Query())
            ->select('id')
            ->from('{{%user}}')
            ->column();

        foreach ($userIds as $userId) {
            // Check if the user doesn't already have the role
            $existingRoles = $auth->getRolesByUser($userId);
            if (!isset($existingRoles['user'])) {
                $auth->assign($userRole, $userId);
            }
        }

        echo "Assigned 'user' role to " . count($userIds) . " users.\n";
    }
}
