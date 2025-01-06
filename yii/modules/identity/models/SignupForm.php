<?php

namespace app\modules\identity\models;

use app\modules\identity\exceptions\UserCreationException;
use app\modules\identity\services\UserService;
use yii\base\Model;

/**
 * SignupForm handles user registration.
 */
class SignupForm extends Model
{
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public ?string $captcha = Null;

    private UserService $userService;

    public function __construct(UserService $userService, $config = [])
    {
        $this->userService = $userService;
        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['username', 'email', 'password'], 'required'],
            [['password'], 'string', 'min' => 3, 'max' => 255],
            ['email', 'email'],
            ['captcha', 'captcha', 'captchaAction' => '/identity/auth/captcha'],
        ];
    }

    public function signup(): ?User
    {
        if ($this->validate()) {
            $user = new User([
                'username' => $this->username,
                'email' => $this->email,
                'password' => $this->password,
            ]);

            if ($user->validate()) {
                try {
                    return $this->userService->create($this->username, $this->email, $this->password);
                } catch (UserCreationException $e) {
                    $this->addError('username', $e->getMessage());
                }
            } else {
                $this->addErrors($user->getErrors());
            }
        }

        return null;
    }
}
