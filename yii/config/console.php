<?php

use yii\helpers\ArrayHelper;

$main = require __DIR__ . '/main.php';

$config = [
    'id' => 'basic-console',
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@tests' => '@app/tests',
    ],
    'controllerMap' => [
        'migrate' => [
            'class' => 'yii\console\controllers\MigrateController',
            'migrationPath' => null,
        ],
    ],
    'components' => [
        'db' => require __DIR__ . '/db.php',
    ],
    'container' => [
        'definitions' => [
            app\modules\identity\services\UserService::class => app\modules\identity\services\UserService::class
        ],
    ],
];

// Dev environment adjustments
if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];

    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        'allowedIPs' => ['127.0.0.1', '::1', '172.*.*.*'],
    ];
}

return ArrayHelper::merge($main, $config);
