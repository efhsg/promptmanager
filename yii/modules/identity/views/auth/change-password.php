<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var app\modules\identity\models\ChangePasswordForm $model */

use yii\bootstrap5\{ActiveForm, Html};

$this->title = 'Change password';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="site-change-password">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">

            <div class="border rounded p-4 shadow bg-white mt-4">
                <h1 class="mb-4 text-center"><?= Html::encode($this->title) ?></h1>

                <p class="text-center">Please enter your current password and choose a new one.</p>

                <?php $form = ActiveForm::begin([
                    'id' => 'change-password-form',
                ]); ?>

                <?= $form->field($model, 'currentPassword')->passwordInput([
                    'autofocus' => true,
                    'placeholder' => 'Enter your current password',
                ]) ?>

                <?= $form->field($model, 'newPassword')->passwordInput([
                    'placeholder' => 'Enter your new password',
                ]) ?>

                <?= $form->field($model, 'confirmPassword')->passwordInput([
                    'placeholder' => 'Confirm your new password',
                ]) ?>

                <div class="form-group mt-4">
                    <?= Html::submitButton('Change password', ['class' => 'btn btn-primary w-100', 'name' => 'change-password-button']) ?>
                    <?= Html::a('Cancel', Yii::$app->request->referrer ?: ['/'], ['class' => 'btn btn-outline-secondary w-100 mt-2']) ?>
                </div>

                <?php ActiveForm::end(); ?>
            </div>

        </div>
    </div>
</div>
