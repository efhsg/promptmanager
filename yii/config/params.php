<?php

use yii\helpers\ArrayHelper;

$rbac = require __DIR__ . '/rbac.php';

$params = [
    'adminEmail'  => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName'  => 'Example.com mailer',
    'ytx' => [
        'pythonPath' => getenv('YTX_PYTHON_PATH') ?: '/usr/bin/python3',
        'scriptPath' => getenv('YTX_SCRIPT_PATH') ?: '/opt/ytx/ytx.py',
    ],
    'sync' => [
        'remoteHost' => getenv('SYNC_REMOTE_HOST') ?: null,
        'remoteUser' => getenv('SYNC_REMOTE_USER') ?: 'esg',
        'remoteDbPassword' => getenv('SYNC_REMOTE_DB_PASSWORD') ?: null,
        'remoteDbName' => getenv('SYNC_REMOTE_DB_NAME') ?: 'yii',
        'sshKeyPath' => getenv('SYNC_SSH_KEY_PATH') ?: null,
    ],
];

return ArrayHelper::merge($params, ['rbac' => $rbac]);
