<?php /** @noinspection PhpUnhandledExceptionInspection */

use yii\bootstrap5\{ActiveForm, Html};
use yii\captcha\Captcha;

/** @var yii\web\View $this */
/** @var app\modules\identity\models\SignupForm $model */

$this->title = 'Signup';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="site-signup">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="border rounded p-4 shadow bg-white mt-4">
                <h1 class="mb-4 text-center"><?= Html::encode($this->title) ?></h1>

                <p class="text-center">Please fill out the following fields to signup:</p>

                <?php $form = ActiveForm::begin([
                    'id' => 'signup-form',
                ]); ?>

                <?= $form->field($model, 'username')->textInput([
                    'autofocus' => true,
                    'placeholder' => 'Enter your username',
                ]) ?>

                <?= $form->field($model, 'email')->textInput([
                    'placeholder' => 'Enter your email',
                ]) ?>

                <?= $form->field($model, 'password')->passwordInput([
                    'placeholder' => 'Enter your password',
                ]) ?>

                <?php if (getenv('IDENTITY_DISABLE_CAPTCHA') !== 'TRUE'): ?>
                    <?= $form->field($model, 'captcha', ['labelOptions' => ['style' => 'display:none']])->widget(Captcha::class, [
                        'captchaAction' => '/identity/auth/captcha',
                        'options' => ['placeholder' => 'Enter the verification code'],
                        'template' => '<div class="d-flex">{image}{input}</div>',
                    ]) ?>
                <?php endif; ?>

                <div class="form-group mt-4">
                    <?= Html::submitButton('Signup', ['class' => 'btn btn-primary w-100', 'name' => 'signup-button']) ?>
                </div>

                <?php ActiveForm::end(); ?>
            </div>

        </div>
    </div>
</div>