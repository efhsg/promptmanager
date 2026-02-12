<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Note $model */
/** @var array $projectList */
/** @var app\models\Note[] $children */

$this->title = 'Update: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Notes', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>

<div class="note-update container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-10">
            <div class="border rounded p-4 shadow bg-white">
                <h3 class="mb-4"><?= Html::encode($this->title) ?></h3>

                <?= $this->render('_form', [
                    'model' => $model,
                    'projectList' => $projectList,
                    'children' => $children,
                ]) ?>
            </div>
        </div>
    </div>
</div>
