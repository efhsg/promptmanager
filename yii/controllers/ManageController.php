<?php /** @noinspection PhpUnused */

namespace app\controllers;

use Exception;
use Yii;
use yii\filters\{AccessControl, VerbFilter};
use yii\web\{Controller, Response};

class ManageController extends Controller
{

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function actionContexts(): string
    {
        return $this->render('context');
    }

    public function actionTemplates(): string
    {
        return $this->render('context');
    }


}
