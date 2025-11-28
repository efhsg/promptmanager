<?php
/** @noinspection BadExpressionStatementJS */

/** @noinspection JSUnresolvedReference */

use app\assets\QuillAsset;
use app\widgets\PathSelectorWidget;
use common\constants\FieldConstants;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Field $modelField */
/** @var app\models\FieldOption[] $modelsFieldOption */
/** @var array $projects */
/** @var yii\widgets\ActiveForm $form */

QuillAsset::register($this);
?>

<?php
if ($modelField->hasErrors()): ?>
    <div class="alert alert-danger">
        <strong>Validation Errors:</strong>
        <ul>
            <?php
            foreach ($modelField->getErrors() as $attribute => $errors): ?>
                <?php
                foreach ($errors as $error): ?>
                    <li><?= Html::encode($attribute . ': ' . $error) ?></li>
                <?php
                endforeach; ?>
            <?php
            endforeach; ?>
        </ul>
    </div>
<?php
endif; ?>

<div class="field-form focus-on-first-field">
    <?php
    $form = ActiveForm::begin(['id' => 'field-form']); ?>

    <?= $form->field($modelField, 'project_id')->dropDownList(
            $projects,
            ['prompt' => '(not set)']
    )->label('Project') ?>

    <?= $form->field($modelField, 'name')->textInput(['maxlength' => true])->label('Field Name') ?>

    <?= $form->field($modelField, 'type')->dropDownList(
            array_combine(FieldConstants::TYPES, FieldConstants::TYPES),
            [
                    'onchange' => 'toggleFieldOptions(this.value)'
            ]
    )->label('Field Type') ?>

    <?= $form->field($modelField, 'selected_by_default')->dropDownList(
            [0 => 'No', 1 => 'Yes']
    )->label('Default on') ?>

    <?= $form->field($modelField, 'label')->textInput(['maxlength' => true])->label('Label (Optional)') ?>

    <div id="field-content-wrapper" style="display: none;">
        <?= $form->field($modelField, 'content')->hiddenInput(['id' => 'field-content'])->label(false) ?>
        <div class="resizable-editor-container mb-3">
            <div id="editor" class="resizable-editor"></div>
        </div>
    </div>

    <?= PathSelectorWidget::widget([
            'initialValue' => in_array(
                    $modelField->type,
                    FieldConstants::PATH_FIELD_TYPES,
                    true
            ) ? $modelField->content : null,
            'pathListUrl' => Url::to(['field/path-list']),
            'projectRootDirectory' => $modelField->project?->root_directory,
            'hiddenContentInputId' => 'field-content',
            'wrapperOptions' => ['id' => 'field-path-wrapper'],
    ]) ?>

    <div id="field-options-wrapper" style="display: none;">
        <?= $this->render('_fieldOptionsForm', [
                'form' => $form,
                'modelField' => $modelField,
                'modelsFieldOption' => $modelsFieldOption,
        ]) ?>
    </div>

    <div class="form-group mt-4 text-end">
        <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary me-2']) ?>
        <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php
    ActiveForm::end(); ?>
</div>

<script>
    const contentTypes = <?= json_encode(FieldConstants::CONTENT_FIELD_TYPES) ?>;
    const optionTypes = <?= json_encode(FieldConstants::OPTION_FIELD_TYPES) ?>;
    const pathTypes = <?= json_encode(FieldConstants::PATH_FIELD_TYPES) ?>;
    const optionsWrapper = document.getElementById('field-options-wrapper');
    const nonOptionFieldElements = optionsWrapper.querySelectorAll(<?= json_encode(
            FieldConstants::NO_OPTION_FIELD_TYPES
    ) ?>);
    const contentWrapper = document.getElementById('field-content-wrapper');
    const pathWrapper = document.getElementById('field-path-wrapper');
    const projectSelector = document.getElementById('field-project_id');
    const hiddenContentInput = document.querySelector('#field-content');
    let currentFieldType = '<?= $modelField->type ?>';
    let previousFieldType = currentFieldType;
    window.fieldFormContentTypes = contentTypes;
    window.fieldFormCurrentType = currentFieldType;

    if (projectSelector) {
        projectSelector.addEventListener('change', () => {
            if (pathTypes.includes(currentFieldType)) {
                const projectId = projectSelector.value;
                if (window.pathSelectorWidget) {
                    window.pathSelectorWidget.load(currentFieldType, projectId);
                }
            }
        });
    }

    function toggleFieldOptions(value) {
        const wasPathType = pathTypes.includes(previousFieldType);
        currentFieldType = value;
        window.fieldFormCurrentType = value;

        const isContentType = contentTypes.includes(value);
        const isPathType = pathTypes.includes(value);
        const shouldEnableContent = isContentType || isPathType;

        if (contentWrapper) {
            contentWrapper.style.display = isContentType ? 'block' : 'none';
        }

        if (pathWrapper) {
            pathWrapper.style.display = isPathType ? 'block' : 'none';
        }

        if (hiddenContentInput) {
            hiddenContentInput.disabled = !shouldEnableContent;
            if (!isContentType && !isPathType) {
                hiddenContentInput.value = '';
            }
        }

        if (!isPathType) {
            const shouldClearHiddenContent = wasPathType || !isContentType;
            if (window.pathSelectorWidget) {
                window.pathSelectorWidget.reset('Select a path', shouldClearHiddenContent);
            }
        }

        if (isPathType) {
            if (!wasPathType && hiddenContentInput) {
                hiddenContentInput.value = '';
            }
            const projectId = projectSelector ? projectSelector.value : '';
            if (window.pathSelectorWidget) {
                window.pathSelectorWidget.load(value, projectId);
            }
        }

        if (optionTypes.includes(value)) {
            optionsWrapper.style.display = 'block';
            nonOptionFieldElements.forEach((el) => el.disabled = false);
        } else {
            optionsWrapper.style.display = 'none';
            nonOptionFieldElements.forEach((el) => el.disabled = true);
        }

        previousFieldType = value;
    }
</script>

<?php
$templateContent = json_encode($modelField->content);
$contentTypesJson = json_encode(FieldConstants::CONTENT_FIELD_TYPES);
$currentType = json_encode($modelField->type);
$fieldType = json_encode($modelField->type);
$script = <<<JS
window.fieldFormContentTypes = window.fieldFormContentTypes || $contentTypesJson;
window.fieldFormCurrentType = window.fieldFormCurrentType || $currentType;
var quill = new Quill('#editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline', 'strike'],
            ['blockquote', 'code-block'],
            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            [{ 'indent': '-1' }, { 'indent': '+1' }],
            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'align': [] }],
            ['clean']
        ]
    }
});

function fieldFormUsesEditor() {
    if (!Array.isArray(window.fieldFormContentTypes)) {
        return false;
    }
    return window.fieldFormContentTypes.includes(window.fieldFormCurrentType || '');
}

if (fieldFormUsesEditor()) {
    try {
        quill.setContents(JSON.parse($templateContent))
    } catch (error) {
        console.error('Error injecting content:', error);
    }
}

quill.on('text-change', function() {
    if (!fieldFormUsesEditor()) {
        return;
    }
    var target = document.querySelector('#field-content');
    if (target) {
        target.value = JSON.stringify(quill.getContents());
    }
});

if (typeof toggleFieldOptions === 'function') {
    toggleFieldOptions($fieldType)
}
JS;
$this->registerJs($script);
?>
