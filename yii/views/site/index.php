<?php

/** @var yii\web\View $this */
/** @var string $dbConnectionStatus */

use yii\helpers\Html;

$this->title = 'Welcome to Promptmanager';
?>

<div class="site-index">
    <div class="jumbotron text-center bg-light py-5">
        <img src="<?= Yii::getAlias('@web/images/prompt-manager-logo.png') ?>" alt="Promptmanager Logo" width="200"
            class="mb-4">
        <h1 class="display-4">Promptmanager</h1>
        <p class="lead">Automate your AI prompts</p>
        <p><?= Html::a('Get Started', ['/site/configuration'], ['class' => 'btn btn-primary btn-lg']) ?></p>
    </div>

</div>