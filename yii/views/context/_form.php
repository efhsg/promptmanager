<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Context $model */
/** @var yii\widgets\ActiveForm $form */
/** @var array $projects */

?>

<div class="context-form">

    <?php $form = ActiveForm::begin(['id' => 'context-form']); ?>

    <?= $form->field($model, 'project_id')->dropDownList(
        $projects,
        ['prompt' => 'Select a Project'])->label('Project')
    ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true])->label('Context Name') ?>

    <?= $form->field($model, 'content')->textarea(['rows' => 10])->label('Content') ?>

    <div class="form-group mt-4 text-end">
        <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary me-2']) ?>
        <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
