<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\PromptTemplate $model */
/** @var array $projects */

$this->title = 'Update Prompt Template: ' . $model->name;
echo $this->render('_breadcrumbs', [
    'model' => $model,
    'actionLabel' => 'Update Prompt Template',
]);
?>
<div class="prompt-template-update container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-10">
            <div class="border rounded p-4 shadow bg-white mt-4">
                <h3 class="mb-4 text-center"><?= Html::encode($this->title) ?></h3>
                <?= $this->render('_form', [
                    'model' => $model,
                    'projects' => $projects,
                ]) ?>
            </div>
        </div>
    </div>
</div>
