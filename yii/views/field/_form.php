<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Field $model */
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

<div class="field-form">
    <?php $form = ActiveForm::begin(['id' => 'field-form']); ?>

    <?= $form->field($model, 'project_id')->dropDownList(
        $projects,
        ['prompt' => '(No project)']
    )->label('Project') ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true])->label('Field Name') ?>

    <?= $form->field($model, 'type')->dropDownList(
        [
            'text' => 'Text',
            'number' => 'Number',
            'date' => 'Date',
            'checkbox' => 'Checkbox',
        ],
        ['prompt' => 'Select Type']
    )->label('Field Type') ?>

    <?= $form->field($model, 'selected_by_default')->dropDownList(
        [1 => 'Yes', 0 => 'No'],
        ['prompt' => 'Select']
    )->label('Selected By Default') ?>

    <?= $form->field($model, 'label')->textInput(['maxlength' => true])->label('Label (Optional)') ?>

    <div class="form-group mt-4 text-end">
        <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary me-2']) ?>
        <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
