<?php /** @noinspection JSUnresolvedReference */

/** @noinspection PhpUnhandledExceptionInspection */

use app\assets\QuillAsset;
use app\helpers\TooltipHelper;
use app\models\PromptInstanceForm;
use conquer\select2\Select2Widget;
use common\enums\CopyType;
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
/* @var array $defaultContextIds */


QuillAsset::register($this);

$maxContentLength = 1000;
$contextTooltipTexts = TooltipHelper::prepareTexts($contextsContent, $maxContentLength);
$templateTooltipTexts = TooltipHelper::prepareTexts($templatesDescription, $maxContentLength);

$this->registerJsVar('contextTooltipTexts', $contextTooltipTexts);
$this->registerJsVar('templateTooltipTexts', $templateTooltipTexts);

$projectCopyFormat = (\Yii::$app->projectContext)->getCurrentProject()?->getPromptInstanceCopyFormatEnum()->value
    ?? CopyType::MD->value;
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
                            'options' => [
                                'placeholder' => 'Select one or more contexts...',
                                'multiple' => true,
                                'value' => $defaultContextIds
                            ],
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
                        <div id="final-prompt-container-view">
                            <?= app\widgets\ContentViewerWidget::widget([
                                'content' => '',
                                'copyButtonOptions' => [
                                    'class' => 'btn btn-sm position-absolute',
                                    'style' => 'bottom: 10px; right: 20px;',
                                    'title' => 'Copy to clipboard',
                                    'aria-label' => 'Copy content to clipboard',
                                    'copyFormat' => $projectCopyFormat,
                                ],
                            ]) ?>
                        </div>
                        <div id="final-prompt-container-edit" class="d-none">
                            <div id="quill-editor"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="label-input-container" class="form-group mt-3 d-none">
        <?= Html::label('Label', 'prompt-instance-label', ['class' => 'form-label']) ?>
        <?= Html::textInput('label', '', [
            'id' => 'prompt-instance-label',
            'class' => 'form-control',
            'maxlength' => 255,
            'placeholder' => 'Enter a label',
        ]) ?>
    </div>
    <div class="form-group mt-4 text-end">
        <?= Html::button('Previous', [
            'class' => 'btn btn-secondary me-2 d-none',
            'id' => 'previous-button'
        ]) ?>
        <?= Html::button('Edit', [
            'class' => 'btn btn-secondary me-2 d-none',
            'id' => 'edit-button'
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
var $editButton = $('#edit-button');
var $finalPromptContainer = $('#final-prompt-container');
var $labelContainer = $('#label-input-container');
var $labelInput = $('#prompt-instance-label');

function updateButtonState(step) {
    currentStep = step;
    if (step === 1) {
        $nextButton.text('Next').attr('data-action', 'next');
        $prevButton.addClass('d-none');
        $editButton.addClass('d-none');
    } else if (step === 2) {
        $nextButton.text('Next').attr('data-action', 'next');
        $prevButton.removeClass('d-none');
        $editButton.addClass('d-none');
    } else if (step === 3) {
        $nextButton.text('Save').attr('data-action', 'save');
        $prevButton.removeClass('d-none');
        $editButton.removeClass('d-none');
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
        if ($labelContainer.hasClass('d-none')) {
            $labelContainer.removeClass('d-none');
            setTimeout(function() {
                $labelInput.trigger('focus');
            }, 0);
            return false;
        }
        const deltaObj   = (typeof quillEditor !== 'undefined' && quillEditor)
        ? quillEditor.getContents()                         
        : $finalPromptContainer.data('deltaObj') || {}; 
        const finalPrompt = JSON.stringify(deltaObj); 
        $.ajax({
            url: '/prompt-instance/save-final-prompt',
            type: 'POST',
            data: { 
                prompt: finalPrompt,
                label: $labelInput.val(),
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
            success: function(response) {
    var container = $finalPromptContainer;
    var deltaString = response.displayDelta || '';
    
    // Parse the delta object from the string
    var deltaObj;
    try {
        deltaObj = JSON.parse(deltaString);
    } catch (error) {
        console.error("Error parsing delta", error);
        deltaObj = { ops: [{ insert: "Error rendering content" }] };
    }
    
    // Create a temporary container with Quill classes
    var tempContainer = document.createElement('div');
    tempContainer.className = 'ql-container ql-snow';
    
    var tempEditor = document.createElement('div');
    tempEditor.className = 'ql-editor';
    tempContainer.appendChild(tempEditor);
    
    var tempQuill = new Quill(tempEditor, {
        readOnly: true,
        theme: 'snow',
        modules: { toolbar: false }
    });
    
    tempQuill.setContents(deltaObj);
    
    // Get the entire container to preserve Quill styling
    var renderedHtml = tempContainer.outerHTML;
    var viewer = container.find('.content-viewer');
    viewer.html(renderedHtml);
    viewer.attr('data-delta-content', deltaString);
    container.attr('data-delta-content', deltaString);
    
    var plainText = tempQuill.getText();
    var copyContent = response.copyContent || plainText;
    container.find('textarea').text(copyContent);

    var copyButton = container.find('.copy-button-container button');
    if (copyButton.length) {
        copyButton.attr('data-copy-content', copyContent);
    }
    
    // Store the delta object for the edit functionality
    container.data('deltaObj', deltaObj);
    
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
let quillEditor = null;

function initQuillEditor() {
    if (!quillEditor) {
        quillEditor = new Quill('#quill-editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['clean']
                ]
            }
        });
    }
}

$editButton.on('click', function() {
    const isEditing = $(this).text() === 'Edit';
    const $viewContainer = $('#final-prompt-container-view');
    const $editContainer = $('#final-prompt-container-edit');
    
    if (isEditing) {
        initQuillEditor();
        
        // Use the stored delta object
        const delta = $finalPromptContainer.data('deltaObj');
        if (delta && delta.ops) {
            quillEditor.setContents(delta);
        } else {
            // Fallback to HTML
            quillEditor.clipboard.dangerouslyPasteHTML($viewContainer.find('.content-viewer').html());
        }
        
        $viewContainer.addClass('d-none');
        $editContainer.removeClass('d-none');
        $('.ql-toolbar').show();
        $(this).text('View');
    } else {
        const delta      = quillEditor.getContents();
        const deltaString = JSON.stringify(delta);
        const innerHtml  = quillEditor.root.innerHTML;
        const $rendered  = $('<div>', { class: 'ql-container ql-snow' })
            .append($('<div>', { class: 'ql-editor', html: innerHtml }));
        const $viewer     = $viewContainer.find('.content-viewer');
        $viewer.html($rendered);
        $viewer.attr('data-delta-content', deltaString);
        $finalPromptContainer.data('deltaObj', delta);
        $finalPromptContainer.attr('data-delta-content', deltaString);
        const plainText  = quillEditor.getText();
        $viewContainer.find('textarea').text(plainText);
        const copyBtn = $viewContainer.find('.copy-button-container button');
        if (copyBtn.length) {
            copyBtn.attr('data-copy-content', plainText);
        }
        $editContainer.addClass('d-none');
        $('.ql-toolbar').hide();
        $viewContainer.removeClass('d-none');
            $(this).text('Edit');
    }
});
JS;
$this->registerJs($script);
?>
