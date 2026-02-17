<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var int $sessionCount */
/** @var int $runCount */

$this->title = 'Cleanup Sessions';
$this->params['breadcrumbs'][] = ['label' => 'AI Sessions', 'url' => ['runs']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="ai-cleanup-confirm container py-4">

    <div class="card">
        <div class="card-header">
            <strong>Are you sure you want to delete all completed sessions?</strong>
        </div>
        <div class="card-body">
            <p class="mb-3">
                This will permanently delete <strong><?= $sessionCount ?></strong> session(s)
                containing <strong><?= $runCount ?></strong> run(s) in total.
                Active runs (pending/running) will not be affected.
            </p>
        </div>

        <div class="card-footer d-flex justify-content-end">
            <?= Html::a('Cancel', ['runs'], ['class' => 'btn btn-secondary me-2']) ?>

            <?php if ($sessionCount > 0): ?>
                <?= Html::beginForm(['cleanup'], 'post', ['class' => 'd-inline']) ?>
                <?= Html::hiddenInput('confirm', 1) ?>
                <?= Html::submitButton('Yes, Delete All', ['class' => 'btn btn-danger']) ?>
                <?= Html::endForm() ?>
            <?php else: ?>
                <?= Html::button('Yes, Delete All', ['class' => 'btn btn-danger', 'disabled' => true]) ?>
            <?php endif; ?>
        </div>
    </div>
</div>
