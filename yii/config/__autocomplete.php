<?php /** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpUndefinedClassInspection */

/**
 * This class only exists here for IDE (PHPStorm/Netbeans/...) autocompletion.
 * This file is never included anywhere.
 * Adjust this file to match classes configured in your application config, to enable IDE autocompletion for custom components.
 * Example: A property phpdoc can be added in `__Application` class as `@property \vendor\package\Rollbar|__Rollbar $rollbar` and adding a class in this file
 * ```php
 * // @property of \vendor\package\Rollbar goes here
 * class __Rollbar {
 * }
 * ```
 */
class Yii
{
    /**
     * @var \yii\web\Application|\yii\console\Application|__Application
     */
    public static \yii\console\Application|__Application|\yii\web\Application $app;
}

/**
 * @property yii\rbac\DbManager $authManager
 * @property \yii\web\User|__WebUser $user
 *
 */
class __Application
{
}

/**
 * @property app\modules\identity\models\User $identity
 */
class __WebUser
{
}
