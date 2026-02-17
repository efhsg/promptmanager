<?php /** @noinspection JSUnresolvedReference */
use app\assets\QuillAsset;
use common\enums\ColorScheme;
use conquer\select2\Select2Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\Project $model */
/** @var yii\widgets\ActiveForm $form */
/** @var array $availableProjects */
/** @var array $projectConfigStatus */
$projectConfigStatus ??= [];

QuillAsset::register($this);

$claudeOptions = $model->getAiOptions();

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

    <div class="card mb-3">
        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#claudeOptionsCollapse" aria-expanded="false" aria-controls="claudeOptionsCollapse" style="cursor: pointer;">
            <i class="bi bi-terminal-fill me-2"></i>Claude CLI Defaults
            <i class="bi bi-chevron-down float-end"></i>
        </div>
        <div class="collapse" id="claudeOptionsCollapse">
            <div class="card-body">
                <p class="text-muted small mb-3">Default options for Claude CLI executions from this project's notes. Leave blank to use CLI defaults.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="claude-options-model" class="form-label">Model</label>
                        <?= Html::dropDownList('ai_options[model]', $claudeOptions['model'] ?? '', $modelOptions, [
                            'id' => 'claude-options-model',
                            'class' => 'form-select',
                        ]) ?>
                    </div>
                    <div class="col-md-6">
                        <label for="claude-options-permission-mode" class="form-label">Permission Mode</label>
                        <?= Html::dropDownList('ai_options[permissionMode]', $claudeOptions['permissionMode'] ?? '', $permissionModes, [
                            'id' => 'claude-options-permission-mode',
                            'class' => 'form-select',
                        ]) ?>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label for="claude-options-allowed-tools" class="form-label">Allowed Tools</label>
                        <?= Html::textInput('ai_options[allowedTools]', $claudeOptions['allowedTools'] ?? '', [
                            'id' => 'claude-options-allowed-tools',
                            'class' => 'form-control',
                            'placeholder' => 'e.g. Read,Glob,Grep',
                        ]) ?>
                        <div class="form-text">Comma-separated tool names</div>
                    </div>
                    <div class="col-md-6">
                        <label for="claude-options-disallowed-tools" class="form-label">Disallowed Tools</label>
                        <?= Html::textInput('ai_options[disallowedTools]', $claudeOptions['disallowedTools'] ?? '', [
                            'id' => 'claude-options-disallowed-tools',
                            'class' => 'form-control',
                            'placeholder' => 'e.g. Bash,Write',
                        ]) ?>
                        <div class="form-text">Comma-separated tool names</div>
                    </div>
                </div>

                <div class="mt-3">
                    <label for="claude-options-system-prompt" class="form-label">Append to System Prompt</label>
                    <?= Html::textarea('ai_options[appendSystemPrompt]', $claudeOptions['appendSystemPrompt'] ?? '', [
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
        <div class="card-header" data-bs-toggle="collapse" data-bs-target="#claudeCommandsCollapse" aria-expanded="false" aria-controls="claudeCommandsCollapse" style="cursor: pointer;">
            <i class="bi bi-list-ul me-2"></i>Claude Command Dropdown
            <i class="bi bi-chevron-down float-end"></i>
        </div>
        <div class="collapse" id="claudeCommandsCollapse">
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Configure which slash commands appear in the Claude chat dropdown and how they are grouped.
                </p>

                <?php if (!$model->id): ?>
                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle me-1"></i>
                        Save the project first to configure command dropdown settings.
                    </div>
                <?php elseif (empty($model->root_directory)): ?>
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Set a Root Directory to load available commands.
                    </div>
                <?php else: ?>
                    <div id="claude-commands-loading" class="text-muted small mb-3" style="display:none;">
                        <i class="bi bi-hourglass-split me-1"></i>Loading commands...
                    </div>
                    <div id="claude-commands-empty" class="alert alert-info small" style="display:none;">
                        <i class="bi bi-info-circle me-1"></i>
                        No commands found in <code>.claude/commands/</code>.
                    </div>

                    <div id="claude-commands-content" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label">Hidden Commands</label>
                            <select id="command-blacklist-select" class="form-select" multiple></select>
                            <div class="form-text">Commands hidden from the Claude chat dropdown.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Command Groups</label>
                            <div id="command-groups-container"></div>
                            <button type="button" id="add-group-btn" class="btn btn-sm btn-outline-secondary mt-2">
                                <i class="bi bi-plus-circle"></i> Add Group
                            </button>
                            <div class="form-text">Leave empty for a flat alphabetical list. Ungrouped commands appear under "Other".</div>
                        </div>
                    </div>

                    <input type="hidden" id="claude-command-blacklist-hidden" name="ai_options[commandBlacklist]" value="<?= Html::encode(Json::encode($model->getAiCommandBlacklist())) ?>">
                    <input type="hidden" id="claude-command-groups-hidden" name="ai_options[commandGroups]" value="<?= Html::encode(Json::encode($model->getAiCommandGroups() ?: new \stdClass())) ?>">
                <?php endif; ?>
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

                <?php if (!empty($projectConfigStatus['pathStatus'])): ?>
                    <?php $ps = $projectConfigStatus['pathStatus']; ?>
                    <?php if ($ps === 'not_mapped'): ?>
                        <div class="alert alert-danger small mb-3">
                            <i class="bi bi-x-circle me-1"></i>
                            Project directory not mapped into container. Check <code>PATH_MAPPINGS</code> in <code>.env</code>
                            and ensure <code>PROJECTS_ROOT</code> volume is configured in <code>docker-compose.yml</code>.
                            Claude will use a managed workspace as fallback.
                        </div>
                    <?php elseif ($ps === 'not_accessible'): ?>
                        <div class="alert alert-danger small mb-3">
                            <i class="bi bi-x-circle me-1"></i>
                            Project directory not accessible in container (mapped to <code><?= Html::encode($projectConfigStatus['effectivePath'] ?? '') ?></code>).
                            Check that <code>PROJECTS_ROOT</code> volume is mounted correctly.
                        </div>
                    <?php elseif ($ps === 'has_config'): ?>
                        <div class="alert alert-info small mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            This project's directory already has its own Claude config
                            (<?= implode(' + ', array_filter([
                                !empty($projectConfigStatus['hasCLAUDE_MD']) ? '<code>CLAUDE.md</code>' : null,
                                !empty($projectConfigStatus['hasClaudeDir']) ? '<code>.claude/</code>' : null,
                            ])) ?>).
                            The context below is only used when the project directory lacks native config.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?= $form->field($model, 'ai_context')
                    ->hiddenInput(['id' => 'claude-context-hidden'])
                    ->label(false) ?>

                <div class="resizable-editor-container" style="min-height: 150px; max-height: 600px;">
                    <div id="claude-context-editor" class="resizable-editor" style="min-height: 150px;"></div>
                </div>
                <div class="hint-block">Rich-text context describing the project, coding standards, and any specific instructions for Claude.</div>

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

    // Claude context Quill editor (same toolbar as note)
    var contextQuill = new Quill('#claude-context-editor', {
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
        document.querySelector('#claude-context-hidden').value = JSON.stringify(contextQuill.getContents());
    });

    // Sync initial Delta to hidden field (in case loaded from backward-compat plain text)
    if (contextQuill.getLength() > 1) {
        document.querySelector('#claude-context-hidden').value = JSON.stringify(contextQuill.getContents());
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

if ($model->id && !empty($model->root_directory)):
    $commandBlacklistJson = Json::encode($model->getAiCommandBlacklist());
    $commandGroupsJson = Json::encode($model->getAiCommandGroups() ?: new \stdClass());
    $claudeCommandsUrl = Url::to(['/project/ai-commands', 'id' => $model->id]);

    $commandDropdownJs = <<<JS
            (function() {
                var availableCommands = {};
                var blacklistSelect = null;
                var groupCounter = 0;

                function escapeHtml(str) {
                    var div = document.createElement('div');
                    div.appendChild(document.createTextNode(str));
                    return div.innerHTML;
                }

                function fetchCommands() {
                    document.getElementById('claude-commands-loading').style.display = '';
                    fetch('$claudeCommandsUrl')
                        .then(function(r) {
                            if (!r.ok) return r.text().then(function(t) { throw new Error('HTTP ' + r.status + ': ' + t.substring(0, 200)); });
                            return r.json();
                        })
                        .then(function(data) {
                            document.getElementById('claude-commands-loading').style.display = 'none';
                            if (data.success && Object.keys(data.commands).length > 0) {
                                availableCommands = data.commands;
                                document.getElementById('claude-commands-content').style.display = '';
                                initializeUI();
                            } else if (data.success) {
                                document.getElementById('claude-commands-empty').style.display = '';
                            } else {
                                document.getElementById('claude-commands-empty').style.display = '';
                            }
                        })
                        .catch(function(err) {
                            console.error('claude-commands fetch error:', err);
                            document.getElementById('claude-commands-loading').style.display = 'none';
                            document.getElementById('claude-commands-empty').style.display = '';
                        });
                }

                function initializeUI() {
                    initBlacklistSelect();
                    initGroupsFromData();
                    syncHiddenFields();
                }

                function initBlacklistSelect() {
                    blacklistSelect = jQuery('#command-blacklist-select');
                    blacklistSelect.empty();
                    Object.keys(availableCommands).forEach(function(cmd) {
                        blacklistSelect.append(new Option(cmd, cmd, false, false));
                    });

                    var currentBlacklist = $commandBlacklistJson;
                    // Preserve blacklisted commands that are no longer on disk
                    currentBlacklist.forEach(function(cmd) {
                        if (availableCommands[cmd] === undefined) {
                            var opt = new Option(cmd + ' (not found)', cmd, false, false);
                            blacklistSelect.append(opt);
                        }
                    });
                    blacklistSelect.val(currentBlacklist).trigger('change');

                    blacklistSelect.select2({
                        placeholder: 'Select commands to hide...',
                        allowClear: true,
                        width: '100%'
                    });

                    blacklistSelect.on('change', function() {
                        removeBlacklistedFromGroups();
                        rebuildAllGroupSelects();
                        syncHiddenFields();
                    });
                }

                function initGroupsFromData() {
                    var groups = $commandGroupsJson;
                    Object.keys(groups).forEach(function(label) {
                        addGroupRow(label, groups[label]);
                    });
                }

                function addGroupRow(label, selectedCommands) {
                    label = label || '';
                    selectedCommands = selectedCommands || [];

                    var groupId = 'group-' + (++groupCounter);
                    var container = document.getElementById('command-groups-container');

                    var row = document.createElement('div');
                    row.className = 'card mb-2';
                    row.id = groupId;
                    row.innerHTML =
                        '<div class="card-body py-2 px-3">' +
                        '  <div class="d-flex gap-2 align-items-start mb-2">' +
                        '    <input type="text" class="form-control form-control-sm group-label-input" placeholder="Group name" value="' + escapeHtml(label) + '" style="max-width:200px;">' +
                        '    <select class="form-select form-select-sm group-commands-select" style="flex:1;"></select>' +
                        '    <button type="button" class="btn btn-sm btn-outline-danger remove-group-btn" title="Remove group">' +
                        '      <i class="bi bi-x-circle"></i>' +
                        '    </button>' +
                        '  </div>' +
                        '  <ul class="group-order-list list-group list-group-flush"></ul>' +
                        '</div>';

                    container.appendChild(row);

                    var select = row.querySelector('.group-commands-select');
                    var orderList = row.querySelector('.group-order-list');

                    // Store ordered commands as data on the row
                    row._orderedCommands = [];

                    populateGroupSelect(select, row);

                    jQuery(select).select2({
                        placeholder: 'Add command...',
                        width: '100%'
                    });

                    // When a command is selected in the dropdown, add it to the ordered list
                    jQuery(select).on('select2:select', function(e) {
                        var cmd = e.params.data.id;
                        row._orderedCommands.push(cmd);
                        renderOrderList(row);
                        rebuildAllGroupSelects();
                        syncHiddenFields();
                        // Clear the dropdown selection (it's just for adding)
                        jQuery(select).val(null).trigger('change.select2');
                    });

                    // Seed the ordered list from saved data (keep missing commands)
                    selectedCommands.forEach(function(cmd) {
                        row._orderedCommands.push(cmd);
                    });
                    renderOrderList(row);

                    row.querySelector('.group-label-input').addEventListener('input', syncHiddenFields);
                    row.querySelector('.remove-group-btn').addEventListener('click', function() {
                        jQuery(select).select2('destroy');
                        row.remove();
                        rebuildAllGroupSelects();
                        syncHiddenFields();
                    });
                }

                function renderOrderList(row) {
                    var list = row.querySelector('.group-order-list');
                    list.innerHTML = '';
                    var commands = row._orderedCommands;
                    commands.forEach(function(cmd, idx) {
                        var isMissing = availableCommands[cmd] === undefined;
                        var li = document.createElement('li');
                        li.className = 'list-group-item d-flex align-items-center py-1 px-2 group-order-item';
                        li.innerHTML =
                            '<span class="me-auto small' + (isMissing ? ' text-muted' : '') + '">' + escapeHtml(cmd) + (isMissing ? ' <em>(not found)</em>' : '') + '</span>' +
                            '<button type="button" class="btn btn-link btn-sm p-0 me-1 group-order-up" title="Move up"' + (idx === 0 ? ' disabled' : '') + '>' +
                            '  <i class="bi bi-arrow-up"></i>' +
                            '</button>' +
                            '<button type="button" class="btn btn-link btn-sm p-0 me-1 group-order-down" title="Move down"' + (idx === commands.length - 1 ? ' disabled' : '') + '>' +
                            '  <i class="bi bi-arrow-down"></i>' +
                            '</button>' +
                            '<button type="button" class="btn btn-link btn-sm p-0 text-danger group-order-remove" title="Remove">' +
                            '  <i class="bi bi-x"></i>' +
                            '</button>';

                        li.querySelector('.group-order-up').addEventListener('click', function() {
                            if (idx > 0) {
                                commands.splice(idx, 1);
                                commands.splice(idx - 1, 0, cmd);
                                renderOrderList(row);
                                syncHiddenFields();
                            }
                        });
                        li.querySelector('.group-order-down').addEventListener('click', function() {
                            if (idx < commands.length - 1) {
                                commands.splice(idx, 1);
                                commands.splice(idx + 1, 0, cmd);
                                renderOrderList(row);
                                syncHiddenFields();
                            }
                        });
                        li.querySelector('.group-order-remove').addEventListener('click', function() {
                            commands.splice(idx, 1);
                            renderOrderList(row);
                            rebuildAllGroupSelects();
                            syncHiddenFields();
                        });

                        list.appendChild(li);
                    });
                }

                function populateGroupSelect(selectElement, row) {
                    var blacklisted = blacklistSelect ? (blacklistSelect.val() || []) : [];
                    var allAssigned = getAllAssignedCommands();

                    jQuery(selectElement).empty();
                    // Only show unassigned, non-blacklisted commands
                    Object.keys(availableCommands).forEach(function(cmd) {
                        if (blacklisted.indexOf(cmd) === -1 && allAssigned.indexOf(cmd) === -1) {
                            selectElement.add(new Option(cmd, cmd, false, false));
                        }
                    });
                    jQuery(selectElement).val(null).trigger('change.select2');
                }

                function getAllAssignedCommands() {
                    var assigned = [];
                    document.querySelectorAll('#command-groups-container .card').forEach(function(row) {
                        if (row._orderedCommands)
                            assigned = assigned.concat(row._orderedCommands);
                    });
                    return assigned;
                }

                function removeBlacklistedFromGroups() {
                    var blacklisted = blacklistSelect ? (blacklistSelect.val() || []) : [];
                    if (blacklisted.length === 0) return;
                    document.querySelectorAll('#command-groups-container .card').forEach(function(row) {
                        if (!row._orderedCommands) return;
                        var before = row._orderedCommands.length;
                        row._orderedCommands = row._orderedCommands.filter(function(cmd) {
                            return blacklisted.indexOf(cmd) === -1;
                        });
                        if (row._orderedCommands.length !== before)
                            renderOrderList(row);
                    });
                }

                function rebuildAllGroupSelects() {
                    document.querySelectorAll('#command-groups-container .card').forEach(function(row) {
                        var sel = row.querySelector('.group-commands-select');
                        if (sel) populateGroupSelect(sel, row);
                    });
                }

                function syncHiddenFields() {
                    var blacklist = blacklistSelect ? (blacklistSelect.val() || []) : [];
                    document.getElementById('claude-command-blacklist-hidden').value = JSON.stringify(blacklist);

                    var groups = {};
                    document.querySelectorAll('#command-groups-container .card').forEach(function(row) {
                        var label = row.querySelector('.group-label-input').value.trim();
                        var commands = row._orderedCommands || [];
                        if (label !== '' && commands.length > 0) {
                            groups[label] = commands;
                        }
                    });
                    document.getElementById('claude-command-groups-hidden').value = JSON.stringify(groups);
                }

                document.getElementById('add-group-btn').addEventListener('click', function() {
                    addGroupRow('', []);
                });

                var collapse = document.getElementById('claudeCommandsCollapse');
                collapse.addEventListener('show.bs.collapse', function() {
                    if (Object.keys(availableCommands).length === 0) {
                        fetchCommands();
                    }
                }, { once: true });
            })();
        JS;

    $this->registerJs($commandDropdownJs);
endif;
?>
