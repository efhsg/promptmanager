<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\ScratchPad $model */

$this->title = 'Update: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Saved Scratch Pads', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>

<div class="scratch-pad-update container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-10">
            <div class="border rounded p-4 shadow bg-white">
                <h3 class="mb-4"><?= Html::encode($this->title) ?></h3>

                <?= $this->render('_form', [
                    'model' => $model,
                ]) ?>
            </div>
        </div>
    </div>
</div>
