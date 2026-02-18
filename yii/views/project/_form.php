<?php /** @noinspection JSUnresolvedReference */
use app\assets\QuillAsset;
use common\enums\ColorScheme;
use conquer\select2\Select2Widget;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Project $model */
/** @var yii\widgets\ActiveForm $form */
/** @var array $availableProjects */
/** @var array $projectConfigStatus */
/** @var array $providers */
$projectConfigStatus ??= [];
$providers ??= [];

QuillAsset::register($this);

$multipleProviders = count($providers) > 1;
$defaultProvider = $model->getDefaultProvider();
?>

<div class="project-form focus-on-first-field">
    <?php $form = ActiveForm::begin([
        'id' => 'project-form',
        'enableClientValidation' => true,
    ]); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'label')
        ->textInput(['maxlength' => true, 'placeholder' => 'Short identifier'])
        ->hint('Optional short code or label for quick identification.') ?>

    <?= $form->field($model, 'color_scheme')
        ->dropDownList(ColorScheme::labels(), ['prompt' => 'No color scheme'])
        ->hint('Sets the navbar color for this project. Can be overridden per browser tab.') ?>

    <?= $form->field($model, 'root_directory')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'blacklisted_directories')
        ->textInput(['placeholder' => 'vendor,runtime,web,npm,docker'])
        ->hint('Comma-separated directories under the root to exclude (e.g. vendor,runtime,web,npm,docker).') ?>

    <?= $form->field($model, 'allowed_file_extensions')
        ->textInput(['maxlength' => true, 'placeholder' => 'php,scss,html'])
        ->hint('Comma-separated extensions; leave blank to allow all.') ?>

    <?= $form->field($model, 'prompt_instance_copy_format')
        ->dropDownList($model::getPromptInstanceCopyFormatOptions())
        ->hint('Format used by prompt instance copy buttons (e.g. Markdown).') ?>

    <?php if ($multipleProviders): ?>
        <!-- Multi-provider: Bootstrap 5 tabs -->
        <ul class="nav nav-tabs mb-0" id="providerTabs" role="tablist">
            <?php foreach ($providers as $providerId => $providerData): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?= $providerId === $defaultProvider ? ' active' : '' ?>"
                            id="tab-<?= Html::encode($providerId) ?>"
                            data-bs-toggle="tab"
                            data-bs-target="#tabpanel-<?= Html::encode($providerId) ?>"
                            type="button" role="tab"
                            aria-controls="tabpanel-<?= Html::encode($providerId) ?>"
                            aria-selected="<?= $providerId === $defaultProvider ? 'true' : 'false' ?>">
                        <i class="bi bi-terminal-fill me-1"></i><?= Html::encode($providerData['name']) ?>
                    </button>
                </li>
            <?php endforeach; ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-context"
                        data-bs-toggle="tab" data-bs-target="#tabpanel-context"
                        type="button" role="tab"
                        aria-controls="tabpanel-context" aria-selected="false">
                    <i class="bi bi-file-earmark-code me-1"></i>Context
                </button>
            </li>
        </ul>

        <div class="tab-content border border-top-0 rounded-bottom p-3 mb-3" id="providerTabContent">
            <?php foreach ($providers as $providerId => $providerData): ?>
                <div class="tab-pane fade<?= $providerId === $defaultProvider ? ' show active' : '' ?>"
                     id="tabpanel-<?= Html::encode($providerId) ?>" role="tabpanel"
                     aria-labelledby="tab-<?= Html::encode($providerId) ?>">
                    <?= $this->render('_form_provider_options', [
                        'model' => $model,
                        'providerId' => $providerId,
                        'providerData' => $providerData,
                        'projectConfigStatus' => $projectConfigStatus[$providerId] ?? [],
                    ]) ?>
                </div>
            <?php endforeach; ?>

            <div class="tab-pane fade" id="tabpanel-context" role="tabpanel" aria-labelledby="tab-context">
                <?= $this->render('_form_context', [
                    'model' => $model,
                    'form' => $form,
                    'projectConfigStatus' => $projectConfigStatus,
                ]) ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Single provider: collapsible cards (current UX) -->
        <?php foreach ($providers as $providerId => $providerData): ?>
            <div class="card mb-3">
                <div class="card-header" data-bs-toggle="collapse"
                     data-bs-target="#providerOptionsCollapse-<?= Html::encode($providerId) ?>"
                     aria-expanded="false"
                     aria-controls="providerOptionsCollapse-<?= Html::encode($providerId) ?>"
                     style="cursor: pointer;">
                    <i class="bi bi-terminal-fill me-2"></i><?= Html::encode($providerData['name']) ?> CLI Defaults
                    <i class="bi bi-chevron-down float-end"></i>
                </div>
                <div class="collapse" id="providerOptionsCollapse-<?= Html::encode($providerId) ?>">
                    <div class="card-body">
                        <?= $this->render('_form_provider_options', [
                            'model' => $model,
                            'providerId' => $providerId,
                            'providerData' => $providerData,
                            'projectConfigStatus' => $projectConfigStatus[$providerId] ?? [],
                        ]) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Single provider: context as collapsible card -->
        <div class="card mb-3">
            <div class="card-header" data-bs-toggle="collapse" data-bs-target="#contextCollapse" aria-expanded="false" aria-controls="contextCollapse" style="cursor: pointer;">
                <i class="bi bi-file-earmark-code me-2"></i>Project Context
                <i class="bi bi-chevron-down float-end"></i>
            </div>
            <div class="collapse" id="contextCollapse">
                <div class="card-body">
                    <?= $this->render('_form_context', [
                        'model' => $model,
                        'form' => $form,
                        'projectConfigStatus' => $projectConfigStatus,
                    ]) ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php
    echo $form->field($model, 'linkedProjectIds')
        ->widget(Select2Widget::class, [
            'items' => $availableProjects,
            'options' => [
                'placeholder' => 'Select projects to link...',
                'multiple' => true,
            ],
            'settings' => [
                'minimumResultsForSearch' => 0,
            ],
        ])
        ->hint('Select other projects whose fields can be used as external (EXT) fields in prompt instances.');
?>

    <?= $form->field($model, 'description')
    ->hiddenInput(['id' => 'project-description'])
    ->label('Description') ?>

    <div class="resizable-editor-container mb-3">
        <div id="project-editor" class="resizable-editor"></div>
    </div>

    <div class="form-group mt-4 text-end">
        <?= Html::a('Cancel', Yii::$app->request->referrer ?: ['index'], [
            'class' => 'btn btn-secondary me-2',
        ]) ?>
        <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<?php
$templateBody = json_encode($model->description);
$claudeContextBody = json_encode($model->ai_context);
$importTextUrl = Url::to(['/note/import-text']);
$importMarkdownUrl = Url::to(['/note/import-markdown']);
$script = <<<JS
    var quill = new Quill('#project-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike', 'code'],
                ['blockquote', 'code-block'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                [{ 'indent': '-1' }, { 'indent': '+1' }],
                [{ 'size': ['small', false, 'large', 'huge'] }],
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                [{ 'color': [] }, { 'background': [] }],
                ['clean']
            ]
        }
    });

    try {
        quill.setContents(JSON.parse($templateBody))
    } catch (error) {
        console.error('Error injecting template body:', error);
    }

    quill.on('text-change', function() {
        document.querySelector('#project-description').value = JSON.stringify(quill.getContents());
    });

    // Context Quill editor (same toolbar as note)
    var contextQuill = new Quill('#context-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike', 'code'],
                ['blockquote', 'code-block'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                [{ 'indent': '-1' }, { 'indent': '+1' }],
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                ['clean'],
                [{ 'clearEditor': [] }],
                [{ 'smartPaste': [] }],
                [{ 'loadMd': [] }]
            ]
        }
    });

    var contextUrlConfig = {
        importTextUrl: '$importTextUrl',
        importMarkdownUrl: '$importMarkdownUrl'
    };
    if (window.QuillToolbar) {
        window.QuillToolbar.setupClearEditor(contextQuill, null);
        window.QuillToolbar.setupSmartPaste(contextQuill, null, contextUrlConfig);
        window.QuillToolbar.setupLoadMd(contextQuill, null, contextUrlConfig);
    }

    try {
        var contextData = JSON.parse($claudeContextBody);
        if (contextData && contextData.ops) {
            contextQuill.setContents(contextData);
        } else if ($claudeContextBody && $claudeContextBody.trim() !== '' && $claudeContextBody !== 'null') {
            // Backward compat: plain text that isn't Delta JSON
            contextQuill.setText($claudeContextBody);
        }
    } catch (error) {
        // Backward compat: existing plain-text content that isn't valid JSON
        var plainText = $claudeContextBody;
        if (plainText && plainText !== 'null') {
            contextQuill.setText(plainText);
        }
    }

    contextQuill.on('text-change', function() {
        document.querySelector('#context-hidden').value = JSON.stringify(contextQuill.getContents());
    });

    // Sync initial Delta to hidden field (in case loaded from backward-compat plain text)
    if (contextQuill.getLength() > 1) {
        document.querySelector('#context-hidden').value = JSON.stringify(contextQuill.getContents());
    }

    // Generate context from project description
    document.getElementById('generate-context-btn')?.addEventListener('click', function() {
        var descText = quill.getText().trim();
        var projectName = document.querySelector('input[name="Project[name]"]')?.value || 'this project';
        var extensions = document.querySelector('input[name="Project[allowed_file_extensions]"]')?.value || '';
        var blacklist = document.querySelector('input[name="Project[blacklisted_directories]"]')?.value || '';

        var delta = { ops: [] };

        delta.ops.push({ insert: 'Role', attributes: { header: 2 } });
        delta.ops.push({ insert: '\\n' });
        delta.ops.push({ insert: 'You are working on ' });
        delta.ops.push({ insert: projectName, attributes: { bold: true } });
        delta.ops.push({ insert: '.\\n\\n' });

        if (descText) {
            delta.ops.push({ insert: 'About', attributes: { header: 2 } });
            delta.ops.push({ insert: '\\n' });
            delta.ops.push({ insert: descText + '\\n\\n' });
        }

        delta.ops.push({ insert: 'Guidelines', attributes: { header: 2 } });
        delta.ops.push({ insert: '\\n' });

        if (extensions) {
            delta.ops.push({ insert: 'Focus on files with extensions: ' + extensions });
            delta.ops.push({ insert: '\\n', attributes: { list: 'bullet' } });
        }

        if (blacklist) {
            delta.ops.push({ insert: 'Avoid directories: ' + blacklist });
            delta.ops.push({ insert: '\\n', attributes: { list: 'bullet' } });
        }

        delta.ops.push({ insert: 'Follow existing code patterns and conventions' });
        delta.ops.push({ insert: '\\n', attributes: { list: 'bullet' } });
        delta.ops.push({ insert: 'Write clean, tested code' });
        delta.ops.push({ insert: '\\n', attributes: { list: 'bullet' } });

        contextQuill.setContents(delta);
    });
    JS;

$this->registerJs($script);
?>
