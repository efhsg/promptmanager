<?php /** @noinspection PhpUnused */

namespace app\modules\identity\commands;

use app\modules\identity\exceptions\UserCreationException;
use app\modules\identity\models\User;
use app\modules\identity\services\UserService;
use Throwable;
use Yii;
use yii\console\{Controller, ExitCode};

class UserController extends Controller
{

    protected UserService $userService;

    public function __construct($id, $module, UserService $userService, $config = [])
    {
        $this->userService = $userService;
        parent::__construct($id, $module, $config);
    }

    public function actionCreate(string $username, string $email, string $password): int
    {
        try {
            $this->userService->create($username, $email, $password);
            $this->stdout("User '$username' has been created successfully.\n");
            return ExitCode::OK;
        } catch (UserCreationException $e) {
            $this->stdout("Failed to create user '$username': {$e->getMessage()}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        } catch (Throwable $e) {
            Yii::error("An unexpected error occurred: " . $e->getMessage(), __METHOD__);
            $this->stdout("An unexpected error occurred: {$e->getMessage()}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    public function actionDelete(string $username): int
    {
        $user = User::findOne(['username' => $username]);

        if (!$user) {
            $this->stdout("User '$username' not found.\n");
            return ExitCode::DATAERR;
        }

        $response = $this->prompt(
            "Do you want to soft delete or hard delete the user '$username'? (soft/hard): ",
            ['required' => true, 'default' => 'soft']
        );

        try {
            if ($response === 'soft') {
                if ($this->userService->softDelete($user)) {
                    $this->stdout("User '$username' has been soft deleted successfully.\n");
                    return ExitCode::OK;
                } else {
                    $this->stdout("Failed to soft delete user '$username'.\n");
                    return ExitCode::UNSPECIFIED_ERROR;
                }
            } elseif ($response === 'hard') {
                if ($this->userService->hardDelete($user)) {
                    $this->stdout("User '$username' has been permanently deleted.\n");
                    return ExitCode::OK;
                } else {
                    $this->stdout("Failed to hard delete user '$username'.\n");
                    return ExitCode::UNSPECIFIED_ERROR;
                }
            } else {
                $this->stdout("Invalid option. Please choose 'soft' or 'hard'.\n");
                return ExitCode::DATAERR;
            }
        } catch (Throwable $e) {
            Yii::error("An error occurred while deleting user '$username': " . $e->getMessage(), __METHOD__);
            $this->stdout("An unexpected error occurred: {$e->getMessage()}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
