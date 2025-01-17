<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Field $modelField */
/** @var app\models\FieldOption[] $modelsFieldOption */
/** @var array $projects */

$this->title = 'Create Field';
?>
<div class="field-create container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8">
            <div class="border rounded p-4 shadow bg-white mt-4">
                <h3 class="mb-4 text-center"><?= Html::encode($this->title) ?></h3>

                <?= $this->render('_form', [
                    'modelField'        => $modelField,
                    'modelsFieldOption' => $modelsFieldOption,
                    'projects'     => $projects,
                ]) ?>
            </div>
        </div>
    </div>
</div>
