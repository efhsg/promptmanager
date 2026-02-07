<?php

/** @noinspection PhpUnused */

namespace app\modules\identity\controllers;

use app\modules\identity\models\ChangePasswordForm;
use app\modules\identity\models\LoginForm;
use app\modules\identity\models\SignupForm;
use app\modules\identity\services\UserService;
use Yii;
use yii\web\{Controller, Response};

class AuthController extends Controller
{
    protected UserService $userService;

    public function __construct($id, $module, UserService $userService, $config = [])
    {
        $this->userService = $userService;
        parent::__construct($id, $module, $config);
    }

    public function actions(): array
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionLogin(): Response|string
    {
        $loginForm = new LoginForm();

        if ($loginForm->load(Yii::$app->request->post()) && $loginForm->login()) {
            return $this->goBack();
        }

        return $this->render('login', [
            'model' => $loginForm,
        ]);
    }

    public function actionLogout(): Response
    {
        Yii::$app->user->logout();
        return $this->goHome();
    }

    public function actionChangePassword(): Response|string
    {
        if (Yii::$app->user->isGuest) {
            return $this->redirect(['/identity/auth/login']);
        }

        $model = new ChangePasswordForm($this->userService);

        if ($model->load(Yii::$app->request->post()) && $model->changePassword()) {
            Yii::$app->session->setFlash('success', 'Password changed successfully.');
            return $this->goHome();
        }

        return $this->render('change-password', ['model' => $model]);
    }

    public function actionSignup(): Response|string
    {
        $model = new SignupForm($this->userService);

        if (Yii::$app->request->isPost) {
            $model->load(Yii::$app->request->post());

            if ($model->signup()) {
                Yii::$app->session->setFlash('success', 'Registration successful! You can now log in.');
                return $this->redirect(['/identity/auth/login']);
            }
        }

        return $this->render('signup', ['model' => $model]);
    }

}
