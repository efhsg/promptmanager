<?php

use app\services\AdvancedSearchService;
use common\enums\SearchMode;
use yii\bootstrap5\Html;

?>

<div class="modal fade" id="advancedSearchModal" tabindex="-1" aria-labelledby="advancedSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="advancedSearchModalLabel">Advanced Search</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col">
                        <div class="d-flex gap-2 flex-nowrap">
                            <?= Html::textInput('advanced_q', '', [
                                'id' => 'advanced-search-input',
                                'class' => 'form-control',
                                'placeholder' => 'Enter search term...',
                                'autocomplete' => 'off',
                            ]) ?>
                            <button type="button" class="btn btn-primary flex-shrink-0" id="advanced-search-btn">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Search in:</label>
                        <div>
                            <?php foreach (AdvancedSearchService::typeLabels() as $value => $label): ?>
                                <div class="form-check">
                                    <?= Html::checkbox('advanced_types[]', true, [
                                        'id' => 'advanced-type-' . $value,
                                        'class' => 'form-check-input advanced-search-type',
                                        'value' => $value,
                                    ]) ?>
                                    <label class="form-check-label" for="advanced-type-<?= $value ?>">
                                        <?= Html::encode($label) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Search mode:</label>
                        <div>
                            <?php foreach (SearchMode::cases() as $mode): ?>
                                <div class="form-check">
                                    <?= Html::radio('advanced_mode', $mode === SearchMode::PHRASE, [
                                        'id' => 'advanced-mode-' . $mode->value,
                                        'class' => 'form-check-input advanced-search-mode',
                                        'value' => $mode->value,
                                    ]) ?>
                                    <label class="form-check-label" for="advanced-mode-<?= $mode->value ?>">
                                        <?= Html::encode($mode->label()) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <hr>

                <div id="advanced-search-results" class="advanced-search-results-container"></div>
            </div>
        </div>
    </div>
</div>
