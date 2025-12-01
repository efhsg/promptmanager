<?php

/** @var yii\web\View $this */
/** @var app\models\PromptTemplate $model */
/** @var array $projects */
/** @var array $generalFieldsMap */
/** @var array $projectFieldsMap */
/** @var array $externalFieldsMap */

$this->title = 'Update - ' . $model->name;
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);
?>
<div class="prompt-template-update container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-11 col-lg-11">
            <div class="border rounded p-4 shadow bg-white mt-4">
                <?= $this->render('_form', [
                    'model' => $model,
                    'projects' => $projects,
                    'generalFieldsMap' => $generalFieldsMap,
                    'projectFieldsMap' => $projectFieldsMap,
                    'externalFieldsMap' => $externalFieldsMap,
                ]) ?>
            </div>
        </div>
    </div>
</div>
