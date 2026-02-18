<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Project $model */
/** @var array $availableProjects */
/** @var array $providers */

$this->title = 'Create project';
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);

?>
<div class="project-create container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-10">
            <div class="border rounded p-4 shadow bg-white">
                <h3 class="mb-4"><?= Html::encode($this->title) ?></h3>
                <?= $this->render('_form', [
                    'model' => $model,
                    'availableProjects' => $availableProjects,
                    'providers' => $providers,
                ]) ?>
            </div>
        </div>
    </div>
</div>
