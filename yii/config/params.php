<?php

use yii\helpers\ArrayHelper;

$rbac = require __DIR__ . '/rbac.php';

$params = [
    'adminEmail'  => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName'  => 'Example.com mailer',
];

return ArrayHelper::merge($params, ['rbac' => $rbac]);
