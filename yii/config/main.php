<?php

/**
 * This is the "base" config shared across web, console, and test.
 * We do NOT load the DB here (it is loaded in each environment config).
 */

use app\components\ProjectContext;
use app\services\ai\AiProviderInterface;
use app\services\ai\providers\ClaudeCliProvider;
use app\services\EntityPermissionService;
use app\services\FieldService;
use app\services\ModelService;
use app\services\ProjectService;
use app\services\PromptTemplateService;
use app\services\UserPreferenceService;
use yii\symfonymailer\Mailer;
use yii\console\Application;
use app\components\ProjectUrlManager;

$params = require __DIR__ . '/params.php';

$config = [
    'name' => 'Promptmanager',

    // Base application path
    'basePath' => dirname(__DIR__),

    // Common time zone
    'timeZone' => 'Europe/Amsterdam',

    // Bootstrap log component and queue in all environments that merge this config
    'bootstrap' => ['log', 'queue'],

    // Common aliases
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],

    'modules' => [
        'identity' => [
            'class' => 'app\modules\identity\Module',
        ],
    ],

    // Common components (no DB here!)
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\DbTarget',
                    'enabled' => YII_ENV !== 'test',
                    'levels' => ['error'],
                    'categories' => ['application', 'database'],
                    'logTable' => '{{%log}}',
                ],
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => ['application', 'database'],
                    'logFile' => '@runtime/logs/db.log',
                ],
            ],
        ],
        'formatter' => [
            'class' => 'yii\i18n\Formatter',
            'defaultTimeZone' => 'Europe/Amsterdam',
        ],
        'urlManager' => [
            'class' => ProjectUrlManager::class,
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'POST api/note' => 'api/note/create',
                'claude/<action:\w[\w-]*>' => 'ai-chat/<action>',
            ],
        ],
        'assetManager' => [
            'appendTimestamp' => true,
            'basePath' => __DIR__ . '/../web/assets',
            'bundles' => [
                'yii\bootstrap\BootstrapAsset' => [
                    'class' => 'yii\bootstrap5\BootstrapAsset',
                ],
                'yii\bootstrap\BootstrapPluginAsset' => [
                    'class' => 'yii\bootstrap5\BootstrapPluginAsset',
                ],
            ],
        ],
        'user' => [
            'class' => 'yii\web\User',
            'identityClass' => 'app\modules\identity\models\User',
            'enableAutoLogin' => true,
            'authTimeout' => 3600 * 24 * 30,
            'loginUrl' => ['/identity/auth/login'],
            'enableSession' => !Yii::$app instanceof Application,
        ],
        'session' => [
            'class' => 'yii\web\Session',
            'timeout' => 3600 * 4, // 4 hours - allows long AI CLI sessions
        ],
        'mailer' => [
            'class' => Mailer::class,
            'viewPath' => '@app/mail',
            'useFileTransport' => true,
        ],
        'authManager' => [
            'class' => 'yii\rbac\DbManager',
        ],
        'validator' => [
            'class' => 'yii\validators\Validator',
        ],
        'permissionService' => [
            'class' => EntityPermissionService::class,
        ],
        'modelService' => [
            'class' => ModelService::class,
        ],
        'fieldService' => [
            'class' => FieldService::class,
        ],
        'projectService' => [
            'class' => ProjectService::class,
        ],
        'promptTemplateService' => [
            'class' => PromptTemplateService::class,
        ],
        'userPreference' => [
            'class' => UserPreferenceService::class,
        ],
        'projectContext' => function () {
            $app = Yii::$app;
            return new ProjectContext(
                $app->session,
                $app->userPreference,
                $app->user
            );
        },
        'queue' => [
            'class' => \yii\queue\db\Queue::class,
            'db' => 'db',
            'tableName' => '{{%queue}}',
            'channel' => 'ai',
            'mutex' => \yii\mutex\MysqlMutex::class,
            'ttr' => 3900,     // 65 min (> max AI run timeout 3600s)
            'attempts' => 1,   // no retry (inference not idempotent)
        ],

    ],

    'params' => $params,
];

$config['container'] = [
    'definitions' => [
        AiProviderInterface::class => ClaudeCliProvider::class,
        'app\services\AiCompletionClient' => [
            'class' => 'app\services\ClaudeCliCompletionClient',
        ],
        'app\modules\identity\services\UserDataSeederInterface' => [
            'class' => 'app\services\UserDataSeeder',
        ],
        'yii\widgets\ActiveForm' => [
            'errorCssClass' => 'has-error',
            'successCssClass' => 'has-success',
            'options' => ['class' => 'form-vertical'],
            'fieldConfig' => [
                'template' => "{label}\n{input}\n{error}",
                'errorOptions' => [
                    'class' => 'help-block error-message',
                ],
            ],
        ],
    ],
];

return $config;
