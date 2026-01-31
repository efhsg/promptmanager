<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => sprintf(
        'mysql:host=%s;port=%s;dbname=%s',
        getenv('DB_HOST'),
        getenv('DB_APP_PORT') ?: 3306,
        getenv('DB_DATABASE_TEST')
    ),
    'username' => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'enableSchemaCache' => false,
];
