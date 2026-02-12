<?php
/**
 * Mobile fullscreen search overlay
 *
 * @var yii\web\View $this
 */

use yii\bootstrap5\Html;
?>
<div class="mobile-search-overlay" id="mobile-search-overlay">
    <div class="mobile-search-overlay__header">
        <?= Html::textInput('mobile_search', '', [
            'id' => 'mobile-search-input',
            'class' => 'form-control mobile-search-overlay__input',
            'placeholder' => 'Search projects, templates, notes...',
            'autocomplete' => 'off',
        ]) ?>
        <button type="button" class="mobile-search-overlay__close" id="mobile-search-close" aria-label="Close search">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="mobile-search-overlay__results" id="mobile-search-results"></div>
</div>
