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
        <label for="field-path-input" class="form-label">Path</label>
        <input
            type="text"
            class="form-control"
            id="field-path-input"
            placeholder="Start typing to search paths"
            disabled
            autocomplete="off"
        >
        <div class="position-relative mt-1">
            <div
                id="field-path-suggestions"
                class="list-group shadow-sm d-none position-absolute w-100"
                style="max-height: 240px; overflow-y: auto; z-index: 1050; top: 0; left: 0;"
            ></div>
        </div>
        <div class="form-text">
            <span>Root:</span>
            <span id="field-path-root"><?= Html::encode($modelField->project?->root_directory ?? '') ?></span>
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
    const pathInput = document.getElementById('field-path-input');
    const pathSuggestions = document.getElementById('field-path-suggestions');
    const pathStatus = document.getElementById('field-path-status');
    const pathRootLabel = document.getElementById('field-path-root');
    const projectSelector = document.getElementById('field-project_id');
    const hiddenContentInput = document.querySelector('#field-content');
    const pathListUrl = <?= json_encode(Url::to(['field/path-list'])) ?>;
    let currentFieldType = '<?= $modelField->type ?>';
    let previousFieldType = currentFieldType;
    let initialPathValue = <?= in_array($modelField->type, FieldConstants::PATH_FIELD_TYPES, true)
        ? json_encode($modelField->content)
        : 'null' ?>;
    let availablePaths = [];
    window.fieldFormContentTypes = contentTypes;
    window.fieldFormCurrentType = currentFieldType;

    function syncHiddenContentFromPath() {
        if (hiddenContentInput && pathInput) {
            hiddenContentInput.value = pathInput.value || '';
        }
    }

    function resetPathWidget(placeholder = 'Select a path', clearHiddenContent = true) {
        availablePaths = [];
        if (pathInput) {
            pathInput.value = '';
            pathInput.placeholder = placeholder;
            pathInput.disabled = true;
        }
        if (pathSuggestions) {
            pathSuggestions.innerHTML = '';
            pathSuggestions.classList.add('d-none');
        }
        if (hiddenContentInput && clearHiddenContent) {
            hiddenContentInput.value = '';
        }
    }

    function handlePathError(message) {
        if (pathStatus) {
            pathStatus.textContent = message;
        }
        resetPathWidget('Unable to load paths');
    }

    function renderPathOptions(forceValue = null) {
        if (!pathInput || !pathSuggestions) {
            return;
        }

        if (forceValue !== null) {
            pathInput.value = forceValue;
        }

        const filterTerm = pathInput.value.trim().toLowerCase();
        const filteredPaths = filterTerm === ''
            ? availablePaths
            : availablePaths.filter((path) => path.toLowerCase().includes(filterTerm));

        const currentValue = pathInput.value;
        pathSuggestions.innerHTML = '';
        if (filteredPaths.length === 1 && currentValue === filteredPaths[0]) {
            pathSuggestions.classList.add('d-none');
            pathSuggestions.innerHTML = '';
        } else {
            filteredPaths.forEach((path) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'list-group-item list-group-item-action';
                option.textContent = path;
                option.dataset.value = path;
                if (currentValue === path) {
                    option.classList.add('active');
                }
                option.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    pathInput.value = path;
                    renderPathOptions();
                });
                pathSuggestions.appendChild(option);
            });

            if (filteredPaths.length === 0) {
                pathSuggestions.classList.add('d-none');
            } else {
                pathSuggestions.classList.remove('d-none');
            }
        }

        if (pathStatus) {
            if (availablePaths.length === 0) {
                pathStatus.textContent = 'No paths available.';
            } else if (filteredPaths.length === 0 && filterTerm !== '') {
                pathStatus.textContent = 'No paths match current input.';
            } else if (currentValue && !availablePaths.includes(currentValue)) {
                pathStatus.textContent = 'Path not found in project.';
            } else {
                pathStatus.textContent = '';
            }
        }

        syncHiddenContentFromPath();
    }

    async function loadPathOptions(fieldType) {
        if (!pathInput) {
            return;
        }

        const projectId = projectSelector ? projectSelector.value : '';
        if (!projectId) {
            if (pathStatus) {
                pathStatus.textContent = 'Select a project to browse paths.';
            }
            if (pathRootLabel) {
                pathRootLabel.textContent = '';
            }
            resetPathWidget('Select a path');
            return;
        }

        if (pathStatus) {
            pathStatus.textContent = 'Loading...';
        }
        if (pathInput) {
            pathInput.disabled = true;
            pathInput.placeholder = 'Loading paths...';
        }
        if (pathSuggestions) {
            pathSuggestions.innerHTML = '';
            pathSuggestions.classList.add('d-none');
        }

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

            availablePaths = Array.isArray(data.paths) ? data.paths : [];
            if (pathRootLabel) {
                pathRootLabel.textContent = data.root || '';
            }

            if (pathInput) {
                pathInput.disabled = false;
                pathInput.placeholder = 'Start typing to search paths';
            }

            const targetValue = (hiddenContentInput ? hiddenContentInput.value : '') || initialPathValue || '';
            renderPathOptions(targetValue);
            initialPathValue = null;
        } catch (error) {
            handlePathError(error.message);
        }
    }

    if (pathInput) {
        pathInput.addEventListener('input', () => {
            renderPathOptions();
        });
        pathInput.addEventListener('focus', () => {
            if (pathSuggestions && pathSuggestions.children.length > 0) {
                pathSuggestions.classList.remove('d-none');
            }
        });
        pathInput.addEventListener('blur', () => {
            if (pathSuggestions) {
                setTimeout(() => pathSuggestions.classList.add('d-none'), 100);
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
            if (pathRootLabel) {
                pathRootLabel.textContent = '';
            }
            if (pathStatus) {
                pathStatus.textContent = '';
            }
            const shouldClearHiddenContent = wasPathType || !isContentType;
            resetPathWidget('Select a path', shouldClearHiddenContent);
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

        previousFieldType = value;
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
