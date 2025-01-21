<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\PromptTemplate $model */
/** @var yii\widgets\ActiveForm $form */
/** @var array $projects */

?>

<?php if ($model->hasErrors()): ?>
    <div class="alert alert-danger">
        <strong>Validation Errors:</strong>
        <ul>
            <?php foreach ($model->getErrors() as $attribute => $errors): ?>
                <?php foreach ($errors as $error): ?>
                    <li><?= Html::encode($attribute . ': ' . $error) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="prompt-template-form">

    <?php $form = ActiveForm::begin(['id' => 'prompt-template-form']); ?>

    <?= $form->field($model, 'project_id')->dropDownList(
        $projects,
        ['prompt' => 'Select a Project']
    )->label('Project') ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true])->label('Template Name') ?>

    <?= $form->field($model, 'description')->textarea(['rows' => 6])->label('Description') ?>

    <?= $form->field($model, 'template_body')->textarea(['rows' => 10])->label('Template Body') ?>

    <div class="form-group mt-4 text-end">
        <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary me-2']) ?>
        <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
