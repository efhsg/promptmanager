<?php

use yii\helpers\ArrayHelper;

$main = require __DIR__ . '/main.php';
$params = require __DIR__ . '/params.php';

$config = [
    'id' => 'basic-tests',
    'components' => [
        'db' => require __DIR__ . '/test_db.php',

        'request' => [
            'cookieValidationKey' => 'test',
            'enableCsrfValidation' => false,
        ],
    ],

    'params' => $params,
];

return ArrayHelper::merge($main, $config);