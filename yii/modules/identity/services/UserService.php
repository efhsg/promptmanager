<?php /** @noinspection PhpUnused */

namespace app\modules\identity\services;

use app\modules\identity\exceptions\UserCreationException;
use app\modules\identity\models\User;
use app\modules\identity\traits\ValidationErrorFormatterTrait;
use Exception;
use Throwable;
use Yii;

class UserService
{

    use ValidationErrorFormatterTrait;

    private ?UserDataSeederInterface $userDataSeeder;

    public function __construct(?UserDataSeederInterface $userDataSeeder = null)
    {
        $this->userDataSeeder = $userDataSeeder;
    }

    /**
     * @throws UserCreationException
     */
    public function create(string $username, string $email, string $password): User
    {
        $transaction = Yii::$app->db->beginTransaction();

        try {
            $user = $this->createUserInstance($username, $email, $password);

            if (!$user->save()) {
                throw new UserCreationException("User creation failed: {$this->formatValidationErrors($user)}");
            }

            $this->userDataSeeder?->seed($user->id);

            $transaction->commit();
            return $user;
        } catch (UserCreationException $e) {
            $transaction->rollBack();
            Yii::error("Validation error creating user '$username': " . $e->getMessage(), __METHOD__);
            throw $e;
        } catch (Throwable $e) {
            $transaction->rollBack();
            Yii::error("Unexpected error creating user '$username': " . $e->getMessage(), __METHOD__);
            $errorMessage = "An unexpected error occurred while creating the user: " . $e->getMessage();
            throw new UserCreationException($errorMessage, 0, $e);
        }
    }

    /**
     * @throws \yii\base\Exception
     */
    private function createUserInstance(string $username, string $email, string $password): User
    {
        $user = new User();
        $user->username = $username;
        $user->email = $email;
        $user->setPassword($password);
        $user->generateAuthKey();
        $user->status = User::STATUS_ACTIVE;

        return $user;
    }

    /**
     * @throws \yii\base\Exception
     */
    public function generatePasswordResetToken(User $user): bool
    {
        return $this->updateUserAttribute($user, 'password_reset_token', Yii::$app->security->generateRandomString() . '_' . time());
    }

    private function updateUserAttribute(User $user, string $attribute, $value): bool
    {
        $user->$attribute = $value;
        try {
            return $user->save(false, [$attribute]);
        } catch (Exception $e) {
            Yii::error("Error updating $attribute for user ID $user->id: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    public function removePasswordResetToken(User $user): bool
    {
        return $this->updateUserAttribute($user, 'password_reset_token', null);
    }

    public function softDelete(User $user): bool
    {
        if ($user->deleted_at !== null) {
            Yii::warning("Attempted to soft delete an already deleted record.");
            return false;
        }

        return $this->updateUserAttribute($user, 'deleted_at', time());
    }

    public function restoreSoftDelete(User $user): bool
    {
        if ($user->deleted_at === null) {
            Yii::warning("Attempted to restore a record that is not deleted.");
            return false;
        }

        return $this->updateUserAttribute($user, 'deleted_at', null);
    }

    public function hardDelete(User $user): bool
    {
        try {
            return (bool)$user->delete();
        } catch (Throwable $e) {
            Yii::error("Error permanently deleting user ID $user->id: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

}
