<?php

use app\models\PromptInstance;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\ActiveForm;


/**
 * @var View $this
 * @var PromptInstance $model
 */

$this->title = 'Update Prompt Instance: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Prompt Instances', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="prompt-instance-update container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8">
            <div class="card">
                <div class="card-body">
                    <?php $form = ActiveForm::begin([
                        'id' => 'prompt-instance-update-form',
                        'enableClientValidation' => true,
                    ]); ?>
                    <?= $form->field($model, 'final_prompt')->textarea() ?>
                    <div class="form-group mt-4 text-end">
                        <?= Html::submitButton('Update', ['class' => 'btn btn-primary']) ?>
                    </div>
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
