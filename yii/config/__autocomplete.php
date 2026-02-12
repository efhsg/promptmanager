<?php

use app\modules\identity\models\User;
use yii\rbac\DbManager;
use yii\web\Application;

/** @noinspection PhpUndefinedNamespaceInspection */
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
 * @method static log(string $getMessage, string $string)
 */
class Yii
{
    /**
     * @var Application|yii\console\Application|__Application
     */
    public static \yii\console\Application|__Application|Application $app;
}

/**
 * @property DbManager $authManager
 * @property yii\web\User|__WebUser $user
 * @property mixed|object|null $projectContext
 * @property mixed|object|null $userPreference
 * @property mixed|object|null $modelService
 * @property mixed|object|null $fieldService
 * @property mixed|object|null $projectService
 * @property mixed|object|null $promptTemplateService
 * @property mixed|object|null $permissionService
 *
 */
class __Application {}

/**
 * @property User $identity
 */
class __WebUser {}
