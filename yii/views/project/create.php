<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Project $model */

$this->title = 'Create project';
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);

?>
<div class="project-create container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="border rounded p-4 shadow bg-white">
                <h3 class="mb-4"><?= Html::encode($this->title) ?></h3>
                <?= $this->render('_form', [
                    'model' => $model,
                ]) ?>
            </div>
        </div>
    </div>
</div>
