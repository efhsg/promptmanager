<?php
/** @var yii\web\View $this */
/** @var app\models\PromptInstance $model */
/** @var array $templates        // List of prompt templates (id => name) */
/** @var array $generalFieldsMap */
/** @var array $projectFieldsMap */

$this->title = 'Create Prompt Instance';
echo $this->render('_breadcrumbs', [
    'model'       => null,
    'actionLabel' => $this->title,
]);
?>
<div class="prompt-instance-create container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-11 col-lg-11">
            <div class="border rounded p-4 shadow bg-white mt-4">
                <?= $this->render('_form', [
                    'model'             => $model,
                    'templates'         => $templates,
                    'generalFieldsMap'  => $generalFieldsMap,
                    'projectFieldsMap'  => $projectFieldsMap,
                ]) ?>
            </div>
        </div>
    </div>
</div>
