<?php /** @noinspection JSUnresolvedReference */

/** @noinspection PhpUnhandledExceptionInspection */

use app\helpers\TooltipHelper;
use app\models\PromptInstanceForm;
use conquer\select2\Select2Widget;
use yii\helpers\Html;
use yii\web\JsExpression;
use yii\web\View;
use yii\widgets\ActiveForm;

/* @var View $this */
/* @var PromptInstanceForm $model */
/* @var array $templates */
/* @var array $templatesDescription */
/* @var array $contexts */
/* @var array $contextsContent */

$maxContentLength = 1000;
$contextTooltipTexts = TooltipHelper::prepareTexts($contextsContent, $maxContentLength);
$templateTooltipTexts = TooltipHelper::prepareTexts($templatesDescription, $maxContentLength);

$this->registerJsVar('contextTooltipTexts', $contextTooltipTexts);
$this->registerJsVar('templateTooltipTexts', $templateTooltipTexts);
?>

<div class="prompt-instance-form focus-on-first-field">
    <?php $form = ActiveForm::begin([
        'id' => 'prompt-instance-form',
        'enableClientValidation' => true,
    ]); ?>
    <div class="accordion" id="promptInstanceAccordion">

        <div class="accordion-item">
            <h2 class="accordion-header" id="headingSelection">
                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseSelection" aria-expanded="true" aria-controls="collapseSelection">
                    1. Select Context(s) & Template
                </button>
            </h2>
            <div id="collapseSelection" class="accordion-collapse collapse show" aria-labelledby="headingSelection"
                 data-bs-parent="#promptInstanceAccordion">
                <div class="accordion-body">
                    <?php
                    $contextSelect2Settings = [
                        'minimumResultsForSearch' => new JsExpression("Infinity"),
                        'templateResult' => new JsExpression("
                        function(state) {
                            if (!state.id) return state.text;
                            var tooltip = contextTooltipTexts[state.id] || '';
                            var \$el = $('<span></span>').text(state.text).attr('title', tooltip);
                            return \$el;
                        }
                    "),
                        'templateSelection' => new JsExpression("
                        function(state) {
                            if (!state.id) return state.text;
                            var tooltip = contextTooltipTexts[state.id] || '';
                            var \$el = $('<span></span>').text(state.text).attr('title', tooltip);
                            return \$el;
                        }
                    "),
                    ];
                    $templateSelect2Settings = [
                        'minimumResultsForSearch' => 0,
                        'templateResult' => new JsExpression("
                        function(state) {
                            if (!state.id) return state.text;
                            var tooltip = templateTooltipTexts[state.id] || '';
                            var \$el = $('<span></span>').text(state.text).attr('title', tooltip);
                            return \$el;
                        }
                    "),
                        'templateSelection' => new JsExpression("
                        function(state) {
                            if (!state.id) return state.text;
                            var tooltip = templateTooltipTexts[state.id] || '';
                            var \$el = $('<span></span>').text(state.text).attr('title', tooltip);
                            return \$el;
                        }
                    "),
                    ];
                    echo $form->field($model, 'context_ids')
                        ->label('Context(s)')
                        ->widget(Select2Widget::class, [
                            'items' => $contexts,
                            'options' => ['placeholder' => 'Select one or more contexts...', 'multiple' => true],
                            'settings' => $contextSelect2Settings,
                        ]);
                    echo $form->field($model, 'template_id')
                        ->label('Template')
                        ->widget(Select2Widget::class, [
                            'items' => ['' => 'Select a Prompt Template...'] + $templates,
                            'options' => ['placeholder' => 'Select a Prompt Template...'],
                            'settings' => $templateSelect2Settings,
                        ]);
                    ?>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header" id="headingGeneration">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseGeneration" aria-expanded="false" aria-controls="collapseGeneration">
                    2. Complete Template
                </button>
            </h2>
            <div id="collapseGeneration" class="accordion-collapse collapse" aria-labelledby="headingGeneration"
                 data-bs-parent="#promptInstanceAccordion">
                <div class="accordion-body">
                    <div id="template-instance-container"></div>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header" id="headingFinalPrompt">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseFinalPrompt" aria-expanded="false" aria-controls="collapseFinalPrompt">
                    3. Generated Prompt
                </button>
            </h2>
            <div id="collapseFinalPrompt" class="accordion-collapse collapse" aria-labelledby="headingFinalPrompt"
                 data-bs-parent="#promptInstanceAccordion">
                <div class="accordion-body">
                    <div id="final-prompt-container">
                        <?= app\widgets\ContentViewerWidget::widget([
                            'content' => '',
                            'copyButtonOptions' => [
                                'class' => 'btn btn-sm position-absolute',
                                'style' => 'bottom: 10px; right: 20px;',
                                'title' => 'Copy to clipboard',
                                'aria-label' => 'Copy content to clipboard',
                            ],
                        ]) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="form-group mt-4 text-end">
        <?= Html::button('Previous', [
            'class' => 'btn btn-secondary me-2 d-none',
            'id' => 'previous-button'
        ]) ?>
        <?= Html::submitButton('Next', [
            'class' => 'btn btn-primary',
            'id' => 'form-submit-button'
        ]) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<?php
$script = <<<'JS'
var step2Loaded = false;
var currentStep = 1;
var $nextButton = $('#form-submit-button');
var $prevButton = $('#previous-button');
var $finalPromptContainer = $('#final-prompt-container');

function updateButtonState(step) {
    currentStep = step;
    if (step === 1) {
        $nextButton.text('Next').attr('data-action', 'next');
        $prevButton.addClass('d-none');
    } else if (step === 2) {
        $nextButton.text('Next').attr('data-action', 'next');
        $prevButton.removeClass('d-none');
    } else if (step === 3) {
        $nextButton.text('Save').attr('data-action', 'save');
        $prevButton.removeClass('d-none');
    }
}

function goToPreviousStep() {
    if (currentStep === 2) {
        $('#collapseSelection').collapse('show');
        step2Loaded = false;
        $('#template-instance-container').empty();
        updateButtonState(1);
    } else if (currentStep === 3) {
        $('#collapseGeneration').collapse('show');
        $finalPromptContainer.find('.content-viewer').empty();
        $finalPromptContainer.find('textarea').text('');
        updateButtonState(2);
    }
}

$prevButton.on('click', function() {
    goToPreviousStep();
});
$('#prompt-instance-form').on('beforeSubmit', function(e) {
    e.preventDefault();
    var form = $(this);
    var container = $('#template-instance-container');
    var button = $('#form-submit-button');
    var action = button.attr('data-action');
    var templateId = form.find('#promptinstanceform-template_id').val();
    if (action === 'save') {
        var finalPrompt = $('#final-prompt-container').find('textarea').val();
        $.ajax({
            url: '/prompt-instance/save-final-prompt',
            type: 'POST',
            data: { 
                prompt: finalPrompt,
                template_id: templateId,
                _csrf: yii.getCsrfToken()
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.redirectUrl;
                } else {
                    alert('Error saving the prompt. Please try again.');
                }
            },
            error: function() {
                alert('Error saving the prompt. Please try again.');
            }
        });
        return false;
    }
    if (!step2Loaded) {
        console.log(templateId);
        if (!templateId) {
            alert('Please select a valid prompt template.');
            return false;
        }
        $.ajax({
            url: '/prompt-instance/generate-prompt-form',
            type: 'POST',
            data: { 
                template_id: templateId,
                _csrf: yii.getCsrfToken()
            },
            success: function(response) {
                container.html(response);
                $('#collapseGeneration').collapse('show');
                container.find('select[multiple]').each(function() {
                    $(this).select2({
                        minimumResultsForSearch: 0,
                        templateResult: function(state) {
                            if (!state.id) return state.text;
                            return $('<span></span>').text(state.text);
                        },
                        templateSelection: function(state) {
                            if (!state.id) return state.text;
                            return $('<span></span>').text(state.text);
                        }
                    });
                });
                var firstField = container.find('input.form-control, select.form-control, textarea.form-control').first();
                if (firstField.length) {
                    firstField.focus();
                }
                step2Loaded = true;
                updateButtonState(2);
            },
            error: function() {
                alert('Error generating the prompt form. Please try again.');
            }
        });
    } else {
        console.log(form.find('#promptinstanceform-template_id').val());
        var data = (form.find('select[name^="PromptInstanceForm[context_ids]"]').val() || [])
            .map(id => ({ name: 'context_ids[]', value: id }))
            .concat({
                name: 'template_id',
                value: form.find('#promptinstanceform-template_id').val()
            })
            .concat(container.find(':input').serializeArray());
        data.push({ name: '_csrf', value: yii.getCsrfToken() });

        $.ajax({
            url: '/prompt-instance/generate-final-prompt',
            type: 'POST',
            data: data,
            success: function(response){
                var container = $('#final-prompt-container');
                container.find('.content-viewer').html(response);
                container.find('textarea').text(response);
                $('#collapseFinalPrompt').collapse('show');
                updateButtonState(3);
            },
            error: function() {
                alert('Error generating the final prompt. Please try again.');
            }
        });
    }
    return false;
});
$('.accordion-button').prop('disabled', true);
$('#promptinstanceform-template_id').on('change', function() {
    if (!step2Loaded) {
        $('#prompt-instance-form').submit();
    }
});
JS;
$this->registerJs($script);
?>
