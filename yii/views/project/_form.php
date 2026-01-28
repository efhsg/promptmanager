<?php /** @noinspection JSUnresolvedReference */
use app\assets\QuillAsset;
use conquer\select2\Select2Widget;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Project $model */
/** @var yii\widgets\ActiveForm $form */
/** @var array $availableProjects */

QuillAsset::register($this);

$claudeOptions = $model->getClaudeOptions();

$permissionModes = [
    '' => '(Use CLI default)',
    'plan' => 'Plan (restricted to planning)',
    'dontAsk' => 'Don\'t Ask (fail on permission needed)',
    'bypassPermissions' => 'Bypass Permissions (auto-approve all)',
    'acceptEdits' => 'Accept Edits (auto-accept edits only)',
    'default' => 'Default (interactive, may hang)',
];

$modelOptions = [
    '' => '(Use CLI default)',
    'sonnet' => 'Sonnet',
    'opus' => 'Opus',
    'haiku' => 'Haiku',
];
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

    <div class="card mb-3">
        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#claudeOptionsCollapse" aria-expanded="false" aria-controls="claudeOptionsCollapse" style="cursor: pointer;">
            <i class="bi bi-terminal-fill me-2"></i>Claude CLI Defaults
            <i class="bi bi-chevron-down float-end"></i>
        </div>
        <div class="collapse" id="claudeOptionsCollapse">
            <div class="card-body">
                <p class="text-muted small mb-3">Default options for Claude CLI executions from this project's scratch pads. Leave blank to use CLI defaults.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="claude-options-model" class="form-label">Model</label>
                        <?= Html::dropDownList('claude_options[model]', $claudeOptions['model'] ?? '', $modelOptions, [
                            'id' => 'claude-options-model',
                            'class' => 'form-select',
                        ]) ?>
                    </div>
                    <div class="col-md-6">
                        <label for="claude-options-permission-mode" class="form-label">Permission Mode</label>
                        <?= Html::dropDownList('claude_options[permissionMode]', $claudeOptions['permissionMode'] ?? '', $permissionModes, [
                            'id' => 'claude-options-permission-mode',
                            'class' => 'form-select',
                        ]) ?>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label for="claude-options-allowed-tools" class="form-label">Allowed Tools</label>
                        <?= Html::textInput('claude_options[allowedTools]', $claudeOptions['allowedTools'] ?? '', [
                            'id' => 'claude-options-allowed-tools',
                            'class' => 'form-control',
                            'placeholder' => 'e.g. Read,Glob,Grep',
                        ]) ?>
                        <div class="form-text">Comma-separated tool names</div>
                    </div>
                    <div class="col-md-6">
                        <label for="claude-options-disallowed-tools" class="form-label">Disallowed Tools</label>
                        <?= Html::textInput('claude_options[disallowedTools]', $claudeOptions['disallowedTools'] ?? '', [
                            'id' => 'claude-options-disallowed-tools',
                            'class' => 'form-control',
                            'placeholder' => 'e.g. Bash,Write',
                        ]) ?>
                        <div class="form-text">Comma-separated tool names</div>
                    </div>
                </div>

                <div class="mt-3">
                    <label for="claude-options-system-prompt" class="form-label">Append to System Prompt</label>
                    <?= Html::textarea('claude_options[appendSystemPrompt]', $claudeOptions['appendSystemPrompt'] ?? '', [
                        'id' => 'claude-options-system-prompt',
                        'class' => 'form-control',
                        'rows' => 2,
                        'placeholder' => 'Additional instructions appended to Claude\'s system prompt',
                    ]) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#claudeContextCollapse" aria-expanded="false" aria-controls="claudeContextCollapse" style="cursor: pointer;">
            <i class="bi bi-file-earmark-code me-2"></i>Claude Project Context
            <i class="bi bi-chevron-down float-end"></i>
        </div>
        <div class="collapse" id="claudeContextCollapse">
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Define project-specific context that Claude will use when this project's directory lacks its own <code>.claude/</code> configuration.
                    This serves as a "database CLAUDE.md" that gets injected at runtime.
                </p>

                <?= $form->field($model, 'claude_context')
                    ->textarea([
                        'id' => 'claude-context-textarea',
                        'rows' => 8,
                        'class' => 'form-control font-monospace',
                        'placeholder' => "## Role\n\nYou are working on a Laravel application...\n\n## Guidelines\n\n- Follow PSR-12 coding standards\n- Use dependency injection",
                    ])
                    ->hint('Markdown-formatted context describing the project, coding standards, and any specific instructions for Claude.')
                    ->label(false) ?>

                <div class="mt-2">
                    <button type="button" id="generate-context-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-magic"></i> Generate from Description
                    </button>
                </div>

            </div>
        </div>
    </div>

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
                [{ 'align': [] }],
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

    // Generate context from project description
    document.getElementById('generate-context-btn')?.addEventListener('click', function() {
        const descText = quill.getText().trim();
        const projectName = document.querySelector('input[name="Project[name]"]')?.value || 'this project';
        const extensions = document.querySelector('input[name="Project[allowed_file_extensions]"]')?.value || '';
        const blacklist = document.querySelector('input[name="Project[blacklisted_directories]"]')?.value || '';

        let context = '## Role\\n\\nYou are working on **' + projectName + '**.\\n\\n';

        if (descText) {
            context += '## About\\n\\n' + descText + '\\n\\n';
        }

        context += '## Guidelines\\n\\n';

        if (extensions) {
            context += '- Focus on files with extensions: ' + extensions + '\\n';
        }

        if (blacklist) {
            context += '- Avoid directories: ' + blacklist + '\\n';
        }

        context += '- Follow existing code patterns and conventions\\n';
        context += '- Write clean, tested code\\n';

        document.getElementById('claude-context-textarea').value = context.replace(/\\\\n/g, '\\n');
    });
    JS;

$this->registerJs($script);
?>
