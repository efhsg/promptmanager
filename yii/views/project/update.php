<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Project $model */

$this->title = 'Update ' . $model->name;
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title ,
]);
?>

<div class="project-update container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-10">
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
