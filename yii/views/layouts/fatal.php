<?php

/** @var yii\web\View $this */
/** @var string $content */

$this->beginContent('@app/views/layouts/_base.php'); ?>

<main id="main" class="flex-shrink-0" role="main">
    <div class="container mt-5 pt-5">
        <?= $content ?>
    </div>
</main>

<?php $this->endContent(); ?>