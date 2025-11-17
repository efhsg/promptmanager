<?php /** @noinspection BadExpressionStatementJS */

/** @noinspection JSUnresolvedReference */

use common\constants\FieldConstants;
use app\assets\QuillAsset;
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

<?php if ($modelField->hasErrors()): ?>
    <div class="alert alert-danger">
        <strong>Validation Errors:</strong>
        <ul>
            <?php foreach ($modelField->getErrors() as $attribute => $errors): ?>
                <?php foreach ($errors as $error): ?>
                    <li><?= Html::encode($attribute . ': ' . $error) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="field-form focus-on-first-field">
    <?php $form = ActiveForm::begin(['id' => 'field-form']); ?>

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
    <div id="field-path-wrapper" class="mb-3" style="display: none;">
        <label for="field-path-select" class="form-label">Path</label>
        <div class="input-group">
            <select id="field-path-select" class="form-select" disabled>
                <option value="">Select a path</option>
            </select>
            <button type="button" class="btn btn-outline-secondary" id="field-path-refresh">Refresh</button>
        </div>
        <div class="form-text">
            <span>Root:</span>
            <span id="field-path-root"><?= Html::encode($modelField->project->root_directory ?? '') ?></span>
            <span id="field-path-status" class="ms-2 text-danger"></span>
        </div>
    </div>
    <div id="field-options-wrapper" style="display: none;">
        <?= $this->render('_fieldOptionsForm', [
            'form'              => $form,
            'modelField'        => $modelField,
            'modelsFieldOption' => $modelsFieldOption,
        ]) ?>
    </div>

    <div class="form-group mt-4 text-end">
        <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary me-2']) ?>
        <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<script>
    const contentTypes = <?= json_encode(FieldConstants::CONTENT_FIELD_TYPES) ?>;
    const optionTypes = <?= json_encode(FieldConstants::OPTION_FIELD_TYPES) ?>;
    const pathTypes = <?= json_encode(FieldConstants::PATH_FIELD_TYPES) ?>;
    const optionsWrapper = document.getElementById('field-options-wrapper');
    const nonOptionFieldElements = optionsWrapper.querySelectorAll(<?= json_encode(FieldConstants::NO_OPTION_FIELD_TYPES) ?>);
    const contentWrapper = document.getElementById('field-content-wrapper');
    const pathWrapper = document.getElementById('field-path-wrapper');
    const pathSelect = document.getElementById('field-path-select');
    const pathStatus = document.getElementById('field-path-status');
    const pathRootLabel = document.getElementById('field-path-root');
    const pathRefreshButton = document.getElementById('field-path-refresh');
    const projectSelector = document.getElementById('field-project_id');
    const hiddenContentInput = document.querySelector('#field-content');
    const pathListUrl = <?= json_encode(Url::to(['field/path-list'])) ?>;
    let currentFieldType = '<?= $modelField->type ?>';
    let initialPathValue = <?= in_array($modelField->type, FieldConstants::PATH_FIELD_TYPES, true)
        ? json_encode($modelField->content)
        : 'null' ?>;
    window.fieldFormContentTypes = contentTypes;
    window.fieldFormCurrentType = currentFieldType;

    function syncHiddenContentFromPath() {
        if (hiddenContentInput && pathSelect) {
            hiddenContentInput.value = pathSelect.value || '';
        }
    }

    function handlePathError(message) {
        pathStatus.textContent = message;
        setPathPlaceholder('Unable to load paths');
        pathSelect.disabled = true;
        if (hiddenContentInput) {
            hiddenContentInput.value = '';
        }
    }

    function setPathPlaceholder(message) {
        if (!pathSelect) {
            return;
        }

        pathSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = message;
        pathSelect.appendChild(placeholder);
        pathSelect.value = '';
    }

    async function loadPathOptions(fieldType) {
        if (!pathSelect) {
            return;
        }

        const projectId = projectSelector ? projectSelector.value : '';
        if (!projectId) {
            pathStatus.textContent = 'Select a project to browse paths.';
            pathRootLabel.textContent = '';
            setPathPlaceholder('Select a path');
            pathSelect.disabled = true;
            if (hiddenContentInput) {
                hiddenContentInput.value = '';
            }
            return;
        }

        pathStatus.textContent = 'Loading...';
        pathSelect.disabled = true;

        try {
            const response = await fetch(`${pathListUrl}?projectId=${projectId}&type=${fieldType}`, {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            });

            if (!response.ok) {
                handlePathError('Unable to load paths.');
                return;
            }

            const data = await response.json();
            if (!data.success) {
                handlePathError(data.message || 'Unable to load paths.');
                return;
            }

            const paths = Array.isArray(data.paths) ? data.paths : [];
            pathRootLabel.textContent = data.root || '';

            pathSelect.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select a path';
            pathSelect.appendChild(placeholder);

            paths.forEach((path) => {
                const option = document.createElement('option');
                option.value = path;
                option.textContent = path;
                pathSelect.appendChild(option);
            });

            let targetValue = hiddenContentInput ? hiddenContentInput.value : '';
            if (!targetValue) {
                targetValue = initialPathValue || '';
            }

            if (targetValue) {
                let exists = false;
                Array.from(pathSelect.options).forEach((option) => {
                    if (option.value === targetValue) {
                        exists = true;
                    }
                });
                if (!exists) {
                    const customOption = document.createElement('option');
                    customOption.value = targetValue;
                    customOption.textContent = `${targetValue} (missing)`;
                    pathSelect.appendChild(customOption);
                }
                pathSelect.value = targetValue;
            } else {
                pathSelect.value = '';
            }

            syncHiddenContentFromPath();
            initialPathValue = null;
            pathSelect.disabled = false;
            pathStatus.textContent = '';
        } catch (error) {
            handlePathError(error.message);
        }
    }

    if (pathSelect) {
        pathSelect.addEventListener('change', syncHiddenContentFromPath);
    }

    if (pathRefreshButton) {
        pathRefreshButton.addEventListener('click', () => {
            if (pathTypes.includes(currentFieldType)) {
                loadPathOptions(currentFieldType);
            }
        });
    }

    if (projectSelector) {
        projectSelector.addEventListener('change', () => {
            if (pathTypes.includes(currentFieldType)) {
                loadPathOptions(currentFieldType);
            }
        });
    }

    function toggleFieldOptions(value) {
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

        if (isPathType) {
            loadPathOptions(value);
        }

        if (optionTypes.includes(value)) {
            optionsWrapper.style.display = 'block';
            nonOptionFieldElements.forEach((el) => el.disabled = false);
        } else {
            optionsWrapper.style.display = 'none';
            nonOptionFieldElements.forEach((el) => el.disabled = true);
        }
    }

    toggleFieldOptions('<?= $modelField->type ?>');
</script>

<?php
$templateContent = json_encode($modelField->content);
$contentTypesJson = json_encode(FieldConstants::CONTENT_FIELD_TYPES);
$currentType = json_encode($modelField->type);
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
            [{ 'direction': 'rtl' }],
            [{ 'size': ['small', false, 'large', 'huge'] }],
            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'font': [] }],
            [{ 'align': [] }],
            ['link', 'image'],
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
JS;
$this->registerJs($script);
?>
