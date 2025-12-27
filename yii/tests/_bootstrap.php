<?php

use Codeception\Configuration;

const YII_ENV = 'test';
defined('YII_DEBUG') or define('YII_DEBUG', true);

require_once __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../vendor/autoload.php';

Yii::setAlias('@tests', __DIR__);

if (!empty($_ENV['INCLUDED_TEST_MODULES'])) {
    $modulesRaw = $_ENV['INCLUDED_TEST_MODULES'];

    $modules = json_decode($modulesRaw, true);
    if (!is_array($modules)) {
        $modules = array_map('trim', explode(',', $modulesRaw));
    }

    $validModules = [];
    foreach ($modules as $module) {
        if (!is_dir($module)) {
            fwrite(STDERR, "Warning: Test module directory '$module' does not exist. Skipping it.\n");
        } else {
            $validModules[] = $module;
        }
    }

    Configuration::append(['include' => $validModules]);
}
