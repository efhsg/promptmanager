<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Project $model */

$this->title = 'Update Project: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Projects', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="project-update container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="border rounded p-4 shadow bg-white">
                <h3 class="mb-4"><?= Html::encode($this->title) ?></h3>
                <p>Please update the following fields:</p>

                <?= $this->render('_form', [
                    'model' => $model,
                ]) ?>
            </div>
        </div>
    </div>
</div>
