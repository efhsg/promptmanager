<?php

/** @noinspection PhpUnused */

namespace app\controllers;

use common\enums\LogCategory;
use Exception;
use Yii;
use yii\filters\{AccessControl, VerbFilter};
use yii\web\{Controller};

class SiteController extends Controller
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

    public function actions(): array
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    public function actionIndex(): string
    {
        try {
            Yii::$app->db->createCommand('SELECT 1')->execute();
            return $this->render('index');
        } catch (Exception $e) {
            Yii::error('Database connection failed: ' . $e->getMessage(), LogCategory::APPLICATION->value);
            $this->layout = 'fatal';
            return $this->render('error', [
                'name' => 'Database Connection Error',
                'message' => 'Unable to connect to the database. Please try again later.',
            ]);
        }
    }

}
