<?php /** @noinspection PhpUnhandledExceptionInspection */

use app\helpers\TooltipHelper;
use app\models\PromptInstanceForm;
use conquer\select2\Select2Widget;
use yii\helpers\Html;
use yii\web\JsExpression;
use yii\web\View;
use yii\widgets\ActiveForm;

/* @var View $this */
/* @var PromptInstanceForm $model */
/* @var array $templates             List of prompt templates */
/* @var array $templatesDescription  Tooltip descriptions for each template */
/* @var array $contexts              List of contexts */
/* @var array $contextsContent       Content for each context tooltip */

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
        <!-- Step 1: Select Context(s) & Template -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingSelection">
                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseSelection" aria-expanded="true" aria-controls="collapseSelection">
                    1. Select Context(s) &amp; Template
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
        <!-- Step 2: Complete Template -->
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
                    <!-- This container will be populated with the prompt form via AJAX.
                         It must include a hidden input with id "original-template" that contains the template with placeholders. -->
                    <div id="template-instance-container"></div>
                </div>
            </div>
        </div>
        <!-- Step 3: Final Prompt -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="headingFinalPrompt">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseFinalPrompt" aria-expanded="false" aria-controls="collapseFinalPrompt">
                    3. Final Prompt
                </button>
            </h2>
            <div id="collapseFinalPrompt" class="accordion-collapse collapse" aria-labelledby="headingFinalPrompt"
                 data-bs-parent="#promptInstanceAccordion">
                <div class="accordion-body">
                    <div id="final-prompt-container"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="form-group mt-4 text-end">
        <?= Html::submitButton('Next', ['class' => 'btn btn-primary']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<?php
$script = <<<JS
// A flag to determine whether the prompt form (step 2) has been loaded.
var step2Loaded = false;

$('#prompt-instance-form').on('beforeSubmit', function(e) {
    e.preventDefault();
    var form = $(this);
    var container = $('#template-instance-container');
    
    // If the prompt form (step 2) is not loaded, load it via AJAX.
    if (!step2Loaded) {
        var templateId = form.find('#promptinstanceform-template_id').val();
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
                // The response should include the prompt form and a hidden input with id "original-template".
                container.html(response);
                $('#collapseGeneration').collapse('show');

                // Reinitialize Select2 for any multi-select fields inside the container.
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

                // Set focus to the first rendered field.
                var firstField = container.find('input.form-control, select.form-control, textarea.form-control').first();
                if (firstField.length) {
                    firstField.focus();
                }
                // Mark step 2 as loaded.
                step2Loaded = true;
            },
            error: function() {
                alert('Error generating the prompt form. Please try again.');
            }
        });
} else {
        var data = (form.find('select[name^="PromptInstanceForm[context_ids]"]').val() || [])
            .map(id => ({ name: 'context_ids[]', value: id }))
            .concat({
                name: 'template_id',
                value: form.find('select[name="PromptInstanceForm[template_id]"]').val()
            })
            .concat(container.find(':input').serializeArray());
        data.push({ name: '_csrf', value: yii.getCsrfToken() });
    
        $.ajax({
            url: '/prompt-instance/generate-final-prompt',
            type: 'POST',
            data: data,
            success: function(response) {
                $('#final-prompt-container').html(response);
                $('#collapseFinalPrompt').collapse('show');
            },
            error: function() {
                alert('Error generating the final prompt. Please try again.');
            }
        });
    }
    return false;
});
JS;
$this->registerJs($script);
?>
