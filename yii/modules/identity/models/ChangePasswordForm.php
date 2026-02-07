<?php

namespace app\modules\identity\models;

use app\modules\identity\services\UserService;
use Yii;
use yii\base\Model;

/**
 * ChangePasswordForm handles password changes for authenticated users.
 */
class ChangePasswordForm extends Model
{
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $confirmPassword = '';

    private UserService $userService;

    public function __construct(UserService $userService, $config = [])
    {
        $this->userService = $userService;
        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['currentPassword', 'newPassword', 'confirmPassword'], 'required'],
            [['newPassword'], 'string', 'min' => 3, 'max' => 255],
            ['confirmPassword', 'compare', 'compareAttribute' => 'newPassword', 'message' => 'Passwords do not match.'],
            ['currentPassword', 'validateCurrentPassword'],
            ['newPassword', 'validateNewPasswordDiffers'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'currentPassword' => 'Current password',
            'newPassword' => 'New password',
            'confirmPassword' => 'Confirm new password',
        ];
    }

    public function validateCurrentPassword(string $attribute): void
    {
        if (!$this->hasErrors()) {
            $user = Yii::$app->user->identity;
            if (!$user || !$user->validatePassword($this->currentPassword)) {
                $this->addError($attribute, 'Current password is incorrect.');
            }
        }
    }

    public function validateNewPasswordDiffers(string $attribute): void
    {
        if (!$this->hasErrors() && $this->newPassword === $this->currentPassword) {
            $this->addError($attribute, 'New password must differ from your current password.');
        }
    }

    public function changePassword(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        return $this->userService->changePassword(
            Yii::$app->user->identity,
            $this->newPassword
        );
    }
}
