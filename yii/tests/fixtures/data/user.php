<?php

/** @noinspection PhpExpressionResultUnusedInspection */

try {
    return [
        'user1' => [
            'id' => 100,
            'username' => 'admin',
            'auth_key' => 'test100key',
            'password_hash' => Yii::$app->security->generatePasswordHash('admin'),
            'access_token' => '100_access_token',
            'email' => 'admin@example.com',
            'created_at' => time(),
            'updated_at' => time(),
        ],
        'user2' => [
            'id' => 1,
            'username' => 'userWithField',
            'auth_key' => 'userWithFieldkey',
            'password_hash' => Yii::$app->security->generatePasswordHash('testpassword'),
            'access_token' => '1_access_token',
            'email' => 'userWithField@example.com',
            'created_at' => time(),
            'updated_at' => time(),
        ],
    ];
} catch (\yii\base\Exception $e) {
    Yii::log($e->getMessage(), 'error');
}
