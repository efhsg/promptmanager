<?php

use yii\helpers\ArrayHelper;

$main = require __DIR__ . '/main.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'components' => [
        'db' => $db,
        'request' => [
            'cookieValidationKey' => 'IwE5i3d_0AhHc5a7gnVMSk38YDzgqBYi',
        ],
        $config['components']['errorHandler'] = [
            'class' => 'yii\web\ErrorHandler',
            'errorAction' => 'site/error',
        ],
    ],
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        'allowedIPs' => ['127.0.0.1', '::1', '172.*.*.*'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        'allowedIPs' => ['127.0.0.1', '::1', '172.*.*.*'],
    ];
}

return ArrayHelper::merge($main, $config);
