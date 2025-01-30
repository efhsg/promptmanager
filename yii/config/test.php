<?php

use yii\helpers\ArrayHelper;

$main = require __DIR__ . '/main.php';

$config = [
    'id' => 'basic-tests',
    'components' => [
        'db' => require __DIR__ . '/test_db.php',

        'request' => [
            'cookieValidationKey' => 'test',
            'enableCsrfValidation' => false,
        ],
    ],
];

return ArrayHelper::merge($main, $config);