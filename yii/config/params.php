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
    'pathMappings' => getenv('PATH_MAPPINGS') ? json_decode(getenv('PATH_MAPPINGS'), true) : [],
    'codex' => [
        'models' => [
            'gpt-5.3-codex' => 'GPT 5.3 Codex',
            'gpt-5.2-codex' => 'GPT 5.2 Codex',
            'gpt-5.1-codex-max' => 'GPT 5.1 Codex Max',
            'gpt-5.2' => 'GPT 5.2',
            'gpt-5.1-codex-mini' => 'GPT 5.1 Codex Mini',
        ],
    ],
];

return ArrayHelper::merge($params, ['rbac' => $rbac]);
