<?php

use app\components\TailscaleAwareRequest;
use yii\helpers\ArrayHelper;

$main = require __DIR__ . '/main.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'components' => [
        'db' => $db,
        'request' => [
            'class' => TailscaleAwareRequest::class,
            'cookieValidationKey' => 'IwE5i3d_0AhHc5a7gnVMSk38YDzgqBYi',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
            // Trust proxy headers from Tailscale (100.x.x.x) and Docker networks (172.x.x.x)
            // This enables correct HTTPS detection when behind Tailscale Serve
            'trustedHosts' => [
                '100\.\d+\.\d+\.\d+' => ['X-Forwarded-Proto', 'X-Forwarded-Port', 'X-Forwarded-For'],
                '172\.\d+\.\d+\.\d+' => ['X-Forwarded-Proto', 'X-Forwarded-Port', 'X-Forwarded-For'],
                '127\.0\.0\.1' => ['X-Forwarded-Proto', 'X-Forwarded-Port', 'X-Forwarded-For'],
            ],
        ],
        'errorHandler' => [
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
