<?php

/** @var yii\web\View $this */
/** @var app\models\Project|null $model */
/** @var string $actionLabel */

use app\helpers\BreadcrumbHelper;

$projectsUrl = $actionLabel === null ? null : ['index'];

$breadcrumbParts = [
    ['label' => 'Manage', 'url' => null],
    ['label' => 'Projects', 'url' => $projectsUrl],
];

$this->params['breadcrumbs'] = BreadcrumbHelper::generateModelBreadcrumbs(
    $breadcrumbParts,
    $model,
    $actionLabel
);
