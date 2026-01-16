<?php

namespace app\commands;

use app\modules\identity\models\User;
use app\modules\identity\services\UserService;
use Exception;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use RuntimeException;

class UserController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly UserService $userService = new UserService(),
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Assigns a role to a user.
     *
     * Usage: php yii user/assign-role <user_id> <role_name>
     *
     * @param integer $userId
     * @param string $roleName
     * @throws Exception
     */
    public function actionAssignRole(int $userId, string $roleName): void
    {
        $auth = Yii::$app->authManager;
        $role = $auth->getRole($roleName);
        if (!$role) {
            echo "Role '$roleName' does not exist.\n";
            return;
        }

        $auth->assign($role, $userId);
        echo "Role '$roleName' has been assigned to user with ID $userId.\n";
    }

    /**
     * Generates an API access token for a user.
     *
     * Usage: yii user/generate-token <user_id> [expiry_days]
     */
    public function actionGenerateToken(int $userId, int $expiryDays = 90): int
    {
        $user = User::findOne($userId);
        if (!$user) {
            $this->stderr("User with ID $userId not found.\n");
            return ExitCode::DATAERR;
        }

        $token = $this->userService->generateAccessToken($user, $expiryDays);
        $expiresAt = $user->access_token_expires_at;

        $this->stdout("Access token for user '{$user->username}':\n");
        $this->stdout("$token\n\n");
        $this->stdout("Expires: $expiresAt\n");
        $this->stdout("IMPORTANT: Store this token securely. It cannot be retrieved again.\n");
        return ExitCode::OK;
    }

    /**
     * Rotates the API access token for a user (invalidates old, creates new).
     *
     * Usage: yii user/rotate-token <user_id> [expiry_days]
     */
    public function actionRotateToken(int $userId, int $expiryDays = 90): int
    {
        $user = User::findOne($userId);
        if (!$user) {
            $this->stderr("User with ID $userId not found.\n");
            return ExitCode::DATAERR;
        }

        $token = $this->userService->rotateAccessToken($user, $expiryDays);
        $expiresAt = $user->access_token_expires_at;

        $this->stdout("New access token for user '{$user->username}':\n");
        $this->stdout("$token\n\n");
        $this->stdout("Expires: $expiresAt\n");
        $this->stdout("Previous token has been invalidated.\n");
        return ExitCode::OK;
    }

    /**
     * Revokes the API access token for a user.
     *
     * Usage: yii user/revoke-token <user_id>
     */
    public function actionRevokeToken(int $userId): int
    {
        $user = User::findOne($userId);
        if (!$user) {
            $this->stderr("User with ID $userId not found.\n");
            return ExitCode::DATAERR;
        }

        try {
            $this->userService->revokeAccessToken($user);
            $this->stdout("Access token revoked for user '{$user->username}'.\n");
            return ExitCode::OK;
        } catch (RuntimeException $e) {
            $this->stderr("Failed to revoke access token: {$e->getMessage()}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
