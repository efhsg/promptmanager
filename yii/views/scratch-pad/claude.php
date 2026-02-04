<?php

use app\assets\HighlightAsset;
use app\assets\QuillAsset;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;
use app\models\ScratchPad;

/** @var View $this */
/** @var ScratchPad $model */
/** @var array $projectList */
/** @var array $claudeCommands */

QuillAsset::register($this);
HighlightAsset::register($this);
$this->registerJsFile('@web/js/marked.min.js', ['position' => View::POS_HEAD]);
$this->registerJsFile('@web/js/purify.min.js', ['position' => View::POS_HEAD]);
$this->registerCssFile('@web/css/claude-chat.css');

$runClaudeUrl = Url::to(['/scratch-pad/run-claude', 'id' => $model->id]);
$streamClaudeUrl = Url::to(['/scratch-pad/stream-claude', 'id' => $model->id]);
$cancelClaudeUrl = Url::to(['/scratch-pad/cancel-claude', 'id' => $model->id]);
$summarizeUrl = Url::to(['/scratch-pad/summarize-session', 'id' => $model->id]);
$saveUrl = Url::to(['/scratch-pad/save']);
$importTextUrl = Url::to(['/scratch-pad/import-text']);
$importMarkdownUrl = Url::to(['/scratch-pad/import-markdown']);
$viewUrlTemplate = Url::to(['/scratch-pad/view', 'id' => '__ID__']);
$checkConfigUrl = $model->project ? Url::to(['/project/check-claude-config', 'id' => $model->project->id]) : null;
$projectDefaults = $model->project ? $model->project->getClaudeOptions() : [];
$projectDefaultsJson = Json::encode($projectDefaults, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$checkConfigUrlJson = Json::encode($checkConfigUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

$permissionModes = [
    '' => '(Use default)',
    'plan' => 'Plan (restricted to planning)',
    'dontAsk' => 'Don\'t Ask (fail on permission needed)',
    'bypassPermissions' => 'Bypass Permissions (auto-approve all)',
    'acceptEdits' => 'Accept Edits (auto-accept edits only)',
    'default' => 'Default (interactive, may hang)',
];

$models = [
    '' => '(Use default)',
    'sonnet' => 'Sonnet',
    'opus' => 'Opus',
    'haiku' => 'Haiku',
];

$this->title = 'Claude CLI';
$this->params['breadcrumbs'][] = ['label' => 'Saved Scratch Pads', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => Html::encode($model->name), 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Claude CLI';
?>

<div class="claude-chat-page container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <?= Html::a('<i class="bi bi-arrow-left"></i> Back to "' . Html::encode($model->name) . '"',
                ['view', 'id' => $model->id],
                ['class' => 'text-decoration-none']) ?>
            <h1 class="h3 mt-2 mb-0"><i class="bi bi-terminal-fill me-2"></i>Claude CLI</h1>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="claude-settings-toggle"
                data-bs-toggle="collapse" data-bs-target="#claudeSettingsCard" aria-expanded="true">
            <i class="bi bi-gear"></i> Settings
        </button>
    </div>

    <!-- Section 1: CLI Settings (collapsible) -->
    <div class="card mb-4">
        <div class="collapse show" id="claudeSettingsCard">
            <div class="card-body">
                <div id="claude-config-status" class="alert alert-secondary mb-3 d-none">
                    <small><span id="claude-config-status-text">Checking config...</span></small>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="claude-model" class="form-label">Model</label>
                        <?= Html::dropDownList('claude-model', '', $models, [
                            'id' => 'claude-model',
                            'class' => 'form-select',
                        ]) ?>
                    </div>
                    <div class="col-md-6">
                        <label for="claude-permission-mode" class="form-label">Permission Mode</label>
                        <?= Html::dropDownList('claude-permission-mode', '', $permissionModes, [
                            'id' => 'claude-permission-mode',
                            'class' => 'form-select',
                        ]) ?>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label for="claude-allowed-tools" class="form-label">Allowed Tools</label>
                        <?= Html::textInput('claude-allowed-tools', '', [
                            'id' => 'claude-allowed-tools',
                            'class' => 'form-control',
                            'placeholder' => 'e.g. Read,Glob,Grep',
                        ]) ?>
                        <div class="form-text">Comma-separated tool names</div>
                    </div>
                    <div class="col-md-6">
                        <label for="claude-disallowed-tools" class="form-label">Disallowed Tools</label>
                        <?= Html::textInput('claude-disallowed-tools', '', [
                            'id' => 'claude-disallowed-tools',
                            'class' => 'form-control',
                            'placeholder' => 'e.g. Bash,Write',
                        ]) ?>
                        <div class="form-text">Comma-separated tool names</div>
                    </div>
                </div>

                <div class="mt-3">
                    <label for="claude-system-prompt" class="form-label">Append to System Prompt</label>
                    <?= Html::textarea('claude-system-prompt', '', [
                        'id' => 'claude-system-prompt',
                        'class' => 'form-control',
                        'rows' => 2,
                        'placeholder' => 'Additional instructions appended to Claude\'s system prompt',
                    ]) ?>
                </div>
            </div>
        </div>
        <div id="claude-settings-summary" class="claude-collapsible-summary d-none"
             data-bs-toggle="collapse" data-bs-target="#claudeSettingsCard" role="button">
        </div>
    </div>

    <!-- Section 2: Prompt Editor (collapsible) -->
    <div class="card mb-4">
        <div class="collapse show" id="claudePromptCard">
            <div class="card-body claude-prompt-section">
                <button type="button" id="claude-prompt-collapse-btn" class="claude-prompt-collapse-btn"
                        title="Collapse editor">
                    <i class="bi bi-x-lg"></i>
                </button>
                <!-- Quill editor (initial mode) -->
                <div id="claude-quill-wrapper" class="resizable-editor-container">
                    <div id="claude-quill-editor" class="resizable-editor"></div>
                </div>

                <!-- Textarea (follow-up mode, hidden initially) -->
                <div id="claude-textarea-wrapper" class="d-none">
                    <textarea id="claude-followup-textarea" class="form-control claude-followup-textarea"
                              rows="3" placeholder="Ask a follow-up question..."></textarea>
                </div>

                <!-- Action buttons + editor toggle -->
                <div class="mt-3 d-flex justify-content-between align-items-center">
                    <div class="claude-editor-toggle">
                        <a href="#" id="claude-editor-toggle" class="small text-muted">
                            Switch to plain text
                        </a>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <div id="claude-summarize-group" class="btn-group d-none">
                            <button type="button" id="claude-summarize-auto-btn" class="btn btn-outline-secondary"
                                    title="Summarize conversation and start a new session with the summary">
                                <i class="bi bi-arrow-repeat"></i> Summarize &amp; New Session
                            </button>
                            <button type="button" id="claude-summarize-split-toggle"
                                    class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="visually-hidden">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#" id="claude-summarize-btn">
                                        <i class="bi bi-pencil-square me-1"></i> Summarize
                                        <small class="d-block text-muted">Review summary before sending</small>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="#" id="claude-new-session-btn">
                                        <i class="bi bi-x-circle me-1"></i> New Session
                                        <small class="d-block text-muted">Discard context and start fresh</small>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <button type="button" id="claude-reuse-btn" class="btn btn-outline-secondary d-none">
                            <i class="bi bi-arrow-counterclockwise"></i> Last prompt
                        </button>
                        <button type="button" id="claude-send-btn" class="btn btn-primary" title="Send (Ctrl+Enter)">
                            <i class="bi bi-send-fill"></i> Send
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div id="claude-prompt-summary" class="claude-collapsible-summary d-none"
             data-bs-toggle="collapse" data-bs-target="#claudePromptCard" role="button">
            <i class="bi bi-pencil-square me-1"></i> Prompt editor
        </div>
    </div>

    <!-- Context Usage Meter -->
    <div id="claude-context-meter-wrapper" class="claude-context-meter d-none mb-3">
        <div class="claude-context-meter__bar-container">
            <div id="claude-context-meter-fill" class="claude-context-meter__fill" style="width: 0%"></div>
        </div>
        <div class="claude-context-meter__label">
            <span id="claude-context-meter-text">0% context used</span>
        </div>
    </div>

    <!-- Context Warning -->
    <div id="claude-context-warning" class="alert alert-warning alert-dismissible d-none mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        <span id="claude-context-warning-text"></span>
        <small class="ms-1">Consider starting a new session to avoid degraded performance.</small>
        <button type="button" id="claude-summarize-warning-btn" class="btn btn-warning btn-sm ms-2">
            <i class="bi bi-arrow-repeat"></i> Summarize &amp; Continue
        </button>
        <button type="button" class="btn-close" id="claude-context-warning-close" aria-label="Close"></button>
    </div>

    <!-- Streaming preview (lives above the accordion while Claude is working) -->
    <div id="claude-stream-container" class="d-none mb-4"></div>

    <!-- Active response (rendered here after stream ends, moved into accordion on next send) -->
    <div id="claude-active-response-container" class="d-none mb-4"></div>

    <!-- Exchange History Accordion (exchanges go here immediately on send) -->
    <div id="claude-history-wrapper" class="d-none mb-4">
        <div class="d-flex justify-content-end mb-2">
            <button type="button" id="claude-toggle-history-btn" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrows-collapse"></i> Collapse All
            </button>
        </div>
        <div class="accordion" id="claude-history-accordion"></div>
    </div>

    <!-- Copy All + Save Dialog (below both) -->
    <div id="claude-copy-all-wrapper" class="d-none text-end mb-4">
        <button type="button" id="claude-save-dialog-btn" class="btn btn-outline-primary btn-sm me-1">
            <i class="bi bi-save"></i> Save Dialog
        </button>
        <button type="button" id="claude-copy-all-btn" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-clipboard"></i> Copy All
        </button>
    </div>

    <!-- Save Dialog: Step 1 — Select messages -->
    <div class="modal fade" id="saveDialogSelectModal" tabindex="-1" aria-labelledby="saveDialogSelectLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="saveDialogSelectLabel">Save Dialog — Select Messages</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="save-dialog-toggle-all" checked>
                        <label class="form-check-label fw-semibold" for="save-dialog-toggle-all">Select all</label>
                    </div>
                    <hr class="mt-0">
                    <div id="save-dialog-message-list"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-dialog-continue-btn">
                        Continue <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Dialog: Step 2 — Name and save -->
    <div class="modal fade" id="saveDialogSaveModal" tabindex="-1" aria-labelledby="saveDialogSaveLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="saveDialogSaveLabel">Save Scratch Pad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="save-dialog-error-alert"></div>
                    <div class="mb-3">
                        <label for="save-dialog-name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="save-dialog-name" placeholder="Enter a name...">
                        <div class="invalid-feedback" id="save-dialog-name-error"></div>
                    </div>
                    <div class="mb-3">
                        <label for="save-dialog-project" class="form-label">Project</label>
                        <?= Html::dropDownList('project_id', $model->project_id, $projectList, [
                            'id' => 'save-dialog-project',
                            'class' => 'form-select',
                            'prompt' => 'No project',
                        ]) ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="save-dialog-back-btn">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary" id="save-dialog-save-btn">
                        <i class="bi bi-save"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Streaming Process Modal -->
    <div class="modal fade" id="claudeStreamModal" tabindex="-1" aria-labelledby="claudeStreamModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="claudeStreamModalLabel">
                        <i class="bi bi-terminal-fill me-1"></i> Claude Process
                        <span id="claude-modal-dots" class="claude-thinking-dots">
                            <span></span><span></span><span></span>
                        </span>
                    </h5>
                    <button type="button" id="claude-modal-cancel-btn" class="claude-cancel-btn claude-cancel-btn--modal d-none" title="Cancel inference">
                        <i class="bi bi-stop-fill"></i> Stop
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <details id="claude-modal-thinking" class="claude-thinking-block d-none" open>
                        <summary>Thinking</summary>
                        <div id="claude-modal-thinking-body" class="claude-thinking-block__content"></div>
                    </details>
                    <div id="claude-modal-body" class="claude-message__body"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$contentJson = Json::encode($model->content ?? '{"ops":[]}', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$claudeCommandsJson = Json::encode($claudeCommands, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$js = <<<JS
    (function() {
        var quill = new Quill('#claude-quill-editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike', 'code'],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'indent': '-1' }, { 'indent': '+1' }],
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    [{ 'align': [] }],
                    ['clean'],
                    [{ 'insertClaudeCommand': [] }],
                    [{ 'smartPaste': [] }],
                    [{ 'loadMd': [] }]
                ]
            },
            placeholder: 'Enter your prompt...'
        });

        var urlConfig = {
            importTextUrl: '$importTextUrl',
            importMarkdownUrl: '$importMarkdownUrl'
        };
        if (window.QuillToolbar) {
            window.QuillToolbar.setupSmartPaste(quill, null, urlConfig);
            window.QuillToolbar.setupLoadMd(quill, null, urlConfig);
        }

        // Build Claude command dropdown
        var claudeCommands = $claudeCommandsJson;
        var commandDropdown = document.createElement('select');
        commandDropdown.classList.add('ql-insertClaudeCommand', 'ql-picker');
        commandDropdown.innerHTML = '<option value="" selected disabled>Command</option>';
        var firstValue = Object.values(claudeCommands)[0];
        var isGrouped = firstValue !== null && firstValue !== undefined && typeof firstValue === 'object';

        if (isGrouped) {
            Object.keys(claudeCommands).forEach(function(group) {
                var optgroup = document.createElement('optgroup');
                optgroup.label = group;
                Object.keys(claudeCommands[group]).forEach(function(key) {
                    var option = document.createElement('option');
                    option.value = '/' + key + ' ';
                    option.textContent = key;
                    option.title = claudeCommands[group][key];
                    optgroup.appendChild(option);
                });
                commandDropdown.appendChild(optgroup);
            });
        } else {
            Object.keys(claudeCommands).forEach(function(key) {
                var option = document.createElement('option');
                option.value = '/' + key + ' ';
                option.textContent = key;
                option.title = claudeCommands[key];
                commandDropdown.appendChild(option);
            });
        }
        var toolbarContainer = quill.getModule('toolbar').container;
        var placeholder = toolbarContainer.querySelector('.ql-insertClaudeCommand');
        if (placeholder) {
            placeholder.replaceWith(commandDropdown);
        }
        commandDropdown.addEventListener('change', function() {
            var value = this.value;
            if (value) {
                var range = quill.getSelection();
                var position = range ? range.index : 0;
                quill.insertText(position, value);
                quill.setSelection(position + value.length);
                this.selectedIndex = 0;
            }
        });

        var initialDelta = JSON.parse($contentJson);
        quill.setContents(initialDelta);

        // Configure marked with custom renderer for code highlighting
        var renderer = {
            code: function(token) {
                var code = token.text;
                var lang = token.lang || '';
                var highlighted;
                try {
                    if (lang && typeof hljs !== 'undefined' && hljs.getLanguage(lang))
                        highlighted = hljs.highlight(code, { language: lang }).value;
                    else if (typeof hljs !== 'undefined')
                        highlighted = hljs.highlightAuto(code).value;
                    else
                        highlighted = code.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                } catch (e) {
                    highlighted = code.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                }
                var langClass = lang ? ' language-' + lang : '';
                return '<pre><code class="hljs' + langClass + '">' + highlighted + '</code></pre>';
            },
            link: function(token) {
                var href = token.href || '';
                var title = token.title ? ' title="' + token.title + '"' : '';
                var text = token.tokens ? this.parser.parseInline(token.tokens) : token.text;
                return '<a href="' + href + '"' + title + ' target="_blank" rel="noopener noreferrer">' + text + '</a>';
            }
        };
        marked.use({ renderer: renderer, breaks: true, gfm: true });

        window.ClaudeChat = {
            sessionId: null,
            messages: [],
            lastSentDelta: null,
            inputMode: 'quill',
            historyCounter: 0,
            projectDefaults: $projectDefaultsJson,
            checkConfigUrl: $checkConfigUrlJson,
            maxContext: 200000,
            warningDismissed: false,
            summarizing: false,

            init: function() {
                this.prefillFromDefaults();
                this.checkConfigStatus();
                this.setupEventListeners();
            },

            prefillFromDefaults: function() {
                var d = this.projectDefaults;
                document.getElementById('claude-model').value = d.model || '';
                document.getElementById('claude-permission-mode').value = d.permissionMode || '';
                document.getElementById('claude-system-prompt').value = d.appendSystemPrompt || '';
                document.getElementById('claude-allowed-tools').value = d.allowedTools || '';
                document.getElementById('claude-disallowed-tools').value = d.disallowedTools || '';
            },

            checkConfigStatus: function() {
                var self = this;
                var statusEl = document.getElementById('claude-config-status');
                var statusTextEl = document.getElementById('claude-config-status-text');

                if (!this.checkConfigUrl) {
                    statusEl.classList.add('d-none');
                    return;
                }

                statusEl.classList.remove('d-none');
                statusTextEl.textContent = 'Checking config...';

                fetch(this.checkConfigUrl, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return self.parseJsonResponse(r); })
                .then(function(data) {
                    if (!data.success) {
                        statusEl.classList.add('d-none');
                        return;
                    }
                    statusEl.classList.remove('alert-secondary', 'alert-success', 'alert-info', 'alert-warning', 'alert-danger');
                    var ps = data.pathStatus;
                    if (ps === 'not_mapped') {
                        statusEl.classList.add('alert-danger');
                        var msg = '<i class="bi bi-x-circle me-1"></i>Project directory not mapped. Check PATH_MAPPINGS in .env and PROJECTS_ROOT volume mount.';
                        if (data.hasPromptManagerContext)
                            msg += '<br><small class="text-muted">Falling back to managed workspace with PromptManager context.</small>';
                        statusTextEl.innerHTML = msg;
                    } else if (ps === 'not_accessible') {
                        statusEl.classList.add('alert-danger');
                        var msg2 = '<i class="bi bi-x-circle me-1"></i>Project directory not accessible in container. Check that PROJECTS_ROOT volume is mounted correctly.';
                        if (data.hasPromptManagerContext)
                            msg2 += '<br><small class="text-muted">Falling back to managed workspace with PromptManager context.</small>';
                        statusTextEl.innerHTML = msg2;
                    } else if (ps === 'has_config') {
                        statusEl.classList.add('alert-success');
                        var parts = [];
                        if (data.hasCLAUDE_MD) parts.push('CLAUDE.md');
                        if (data.hasClaudeDir) parts.push('.claude/');
                        statusTextEl.innerHTML = '<i class="bi bi-check-circle me-1"></i>Using project\\'s own config: ' + parts.join(' + ');
                    } else if (ps === 'no_config' && data.hasPromptManagerContext) {
                        statusEl.classList.add('alert-info');
                        statusTextEl.innerHTML = '<i class="bi bi-info-circle me-1"></i>No project config found. Using managed workspace with PromptManager context.';
                        if (data.claudeContext) {
                            statusEl.title = data.claudeContext;
                            statusEl.style.cursor = 'help';
                        }
                    } else {
                        statusEl.classList.add('alert-warning');
                        statusTextEl.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>No config found. Claude will use defaults. Consider adding context in Project settings.';
                    }
                })
                .catch(function() { statusEl.classList.add('d-none'); });
            },

            setupEventListeners: function() {
                var self = this;
                document.getElementById('claude-send-btn').addEventListener('click', function() { self.send(); });
                document.getElementById('claude-reuse-btn').addEventListener('click', function() { self.reuseLastPrompt(); });
                document.getElementById('claude-new-session-btn').addEventListener('click', function(e) { e.preventDefault(); self.newSession(); });
                document.getElementById('claude-copy-all-btn').addEventListener('click', function() { self.copyConversation(); });
                document.getElementById('claude-save-dialog-btn').addEventListener('click', function() { self.openSaveDialogSelect(); });
                document.getElementById('save-dialog-toggle-all').addEventListener('change', function() { self.toggleAllMessages(this.checked); });
                document.getElementById('save-dialog-continue-btn').addEventListener('click', function() { self.saveDialogContinue(); });
                document.getElementById('save-dialog-back-btn').addEventListener('click', function() { self.saveDialogBack(); });
                document.getElementById('save-dialog-save-btn').addEventListener('click', function() { self.saveDialogSave(); });
                document.getElementById('claude-toggle-history-btn').addEventListener('click', function() { self.toggleHistory(); });
                var historyAccordion = document.getElementById('claude-history-accordion');
                historyAccordion.addEventListener('shown.bs.collapse', function() { self.updateToggleHistoryBtn(); });
                historyAccordion.addEventListener('hidden.bs.collapse', function() { self.updateToggleHistoryBtn(); });
                document.getElementById('claude-modal-cancel-btn').addEventListener('click', function() { self.cancel(); });
                document.getElementById('claude-editor-toggle').addEventListener('click', function(e) {
                    e.preventDefault();
                    if (self.inputMode === 'quill')
                        self.switchToTextarea();
                    else
                        self.switchToQuill(null);
                });

                document.getElementById('claude-followup-textarea').addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        e.preventDefault();
                        self.send();
                    }
                });

                quill.root.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        e.preventDefault();
                        self.send();
                    }
                });

                document.querySelector('.claude-chat-page').addEventListener('click', function(e) {
                    var copyBtn = e.target.closest('.claude-message__copy');
                    if (copyBtn) self.handleCopyClick(copyBtn);
                });

                var settingsCard = document.getElementById('claudeSettingsCard');
                settingsCard.addEventListener('hidden.bs.collapse', function() { self.updateSettingsSummary(); });
                settingsCard.addEventListener('shown.bs.collapse', function() {
                    document.getElementById('claude-settings-summary').classList.add('d-none');
                });

                var promptCard = document.getElementById('claudePromptCard');
                promptCard.addEventListener('hidden.bs.collapse', function() {
                    document.getElementById('claude-prompt-summary').classList.remove('d-none');
                    self.setStreamPreviewTall(true);
                });
                promptCard.addEventListener('shown.bs.collapse', function() {
                    document.getElementById('claude-prompt-summary').classList.add('d-none');
                    self.setStreamPreviewTall(false);
                });
                document.getElementById('claude-prompt-collapse-btn').addEventListener('click', function() {
                    self.collapsePromptEditor();
                });

                document.getElementById('claude-context-warning-close').addEventListener('click', function() {
                    document.getElementById('claude-context-warning').classList.add('d-none');
                    self.warningDismissed = true;
                });

                document.getElementById('claude-summarize-btn').addEventListener('click', function(e) {
                    e.preventDefault();
                    self.summarizeAndContinue(false);
                });
                document.getElementById('claude-summarize-auto-btn').addEventListener('click', function() {
                    self.summarizeAndContinue(true);
                });
                document.getElementById('claude-summarize-warning-btn').addEventListener('click', function() {
                    self.summarizeAndContinue(false);
                });
            },

            getOptions: function() {
                return {
                    model: document.getElementById('claude-model').value,
                    permissionMode: document.getElementById('claude-permission-mode').value,
                    appendSystemPrompt: document.getElementById('claude-system-prompt').value,
                    allowedTools: document.getElementById('claude-allowed-tools').value,
                    disallowedTools: document.getElementById('claude-disallowed-tools').value
                };
            },

            send: function() {
                var self = this;
                var options = this.getOptions();
                var sendBtn = document.getElementById('claude-send-btn');

                if (this.sessionId)
                    options.sessionId = this.sessionId;

                var pendingPrompt = null;
                var pendingDelta = null;
                if (this.inputMode === 'quill') {
                    var delta = quill.getContents();
                    this.lastSentDelta = delta;
                    pendingPrompt = quill.getText().replace(/\\n$/, '');
                    if (!pendingPrompt.trim()) return;
                    pendingDelta = delta;
                    options.contentDelta = JSON.stringify(delta);
                    quill.setText('');
                } else {
                    var textarea = document.getElementById('claude-followup-textarea');
                    var text = textarea.value.trim();
                    if (!text) return;
                    pendingPrompt = text;
                    options.prompt = text;
                    textarea.value = '';
                }

                // First send: collapse settings and compact editor
                var isFirstSend = this.messages.length === 0;
                if (isFirstSend) {
                    this.collapseSettings();
                    this.compactEditor();
                }

                sendBtn.disabled = true;

                // Move the active response into its accordion item before starting a new exchange
                this.moveActiveResponseToAccordion();

                // Collapse previous accordion item (if any)
                this.collapseActiveItem();

                // Reset stream state
                this.streamBuffer = '';
                this.streamThinkingBuffer = '';
                this.streamResultText = null;
                this.streamCurrentBlockType = null;
                this.streamMeta = {};
                this.streamPromptMarkdown = null;
                this.streamReceivedText = false;
                this.activeReader = null;
                this.streamPreviewQuill = null;
                this.streamModalQuill = null;
                this.streamModalThinkingQuill = null;

                // Create new accordion item with user prompt + streaming placeholder
                this.createActiveAccordionItem(pendingPrompt, pendingDelta);
                this.showCancelButton(true);
                this.collapsePromptEditor();

                fetch('$streamClaudeUrl', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': yii.getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(options)
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    var contentType = response.headers.get('Content-Type') || '';
                    if (contentType.indexOf('text/event-stream') === -1)
                        throw new Error('Session may have expired (received ' + contentType.split(';')[0] + ' instead of SSE). Please reload the page.');
                    var reader = response.body.getReader();
                    self.activeReader = reader;
                    var decoder = new TextDecoder();
                    var buffer = '';

                    function processStream() {
                        return reader.read().then(function(result) {
                            if (result.done) {
                                self.onStreamEnd();
                                return;
                            }
                            buffer += decoder.decode(result.value, { stream: true });
                            var lines = buffer.split('\\n');
                            buffer = lines.pop();

                            lines.forEach(function(line) {
                                if (line.startsWith('data: ')) {
                                    var payload = line.substring(6);
                                    if (payload === '[DONE]') {
                                        self.onStreamEnd();
                                        return;
                                    }
                                    try {
                                        self.onStreamEvent(JSON.parse(payload));
                                    } catch (e) {
                                        // skip unparseable lines
                                    }
                                }
                            });

                            return processStream();
                        });
                    }

                    return processStream();
                })
                .catch(function(error) {
                    self.onStreamError('Failed to execute Claude CLI: ' + error.message);
                });
            },

            // --- Stream event handlers ---

            onStreamEvent: function(data) {
                var type = data.type;

                if (type === 'prompt_markdown')
                    this.streamPromptMarkdown = data.markdown;
                else if (type === 'system' && data.subtype === 'init')
                    this.onStreamInit(data);
                else if (type === 'stream_event')
                    this.onStreamDelta(data.event);
                else if (type === 'assistant' && !data.isSidechain)
                    this.onStreamAssistant(data);
                else if (type === 'result')
                    this.onStreamResult(data);
                else if (type === 'server_error')
                    this.onStreamError(data.error || 'Unknown server error');
            },

            onStreamInit: function(data) {
                if (data.session_id)
                    this.sessionId = data.session_id;
                if (data.model)
                    this.streamMeta.model = this.formatModelShort(data.model);
            },

            onStreamDelta: function(event) {
                if (!event) return;
                var eventType = event.type;

                if (eventType === 'content_block_start') {
                    var block = event.content_block || event.contentBlock || {};
                    this.streamCurrentBlockType = block.type || 'text';
                } else if (eventType === 'content_block_stop') {
                    // Ensure the active buffer ends with a newline so consecutive
                    // blocks don't glue together (e.g. "text### Heading").
                    if (this.streamCurrentBlockType === 'thinking') {
                        if (this.streamThinkingBuffer && !this.streamThinkingBuffer.endsWith('\\n'))
                            this.streamThinkingBuffer += '\\n';
                    } else {
                        if (this.streamBuffer && !this.streamBuffer.endsWith('\\n'))
                            this.streamBuffer += '\\n';
                    }
                    this.streamCurrentBlockType = null;
                } else if (eventType === 'content_block_delta') {
                    var delta = event.delta;
                    if (delta && delta.type === 'thinking_delta' && delta.thinking) {
                        this.streamThinkingBuffer += delta.thinking;
                        this.streamReceivedText = true;
                        this.scheduleStreamRender();
                    } else if (delta && delta.type === 'text_delta' && delta.text) {
                        if (this.streamCurrentBlockType === 'thinking')
                            this.streamThinkingBuffer += delta.text;
                        else
                            this.streamBuffer += delta.text;
                        this.streamReceivedText = true;
                        this.scheduleStreamRender();
                    }
                }
            },

            onStreamAssistant: function(data) {
                var msg = data.message;
                if (!msg) return;
                // Capture usage from the last non-sidechain assistant message
                if (msg.usage) {
                    this.streamMeta.input_tokens = msg.usage.input_tokens || 0;
                    this.streamMeta.cache_tokens = (msg.usage.cache_read_input_tokens || 0)
                        + (msg.usage.cache_creation_input_tokens || 0);
                }
                // Extract tool uses
                var content = msg.content || [];
                var uses = [];
                content.forEach(function(block) {
                    if (block.type === 'tool_use') {
                        var name = block.name || 'unknown';
                        var input = block.input || {};
                        var target = null;
                        if (name === 'Read' || name === 'Edit' || name === 'Write') target = input.file_path;
                        else if (name === 'Glob' || name === 'Grep') target = input.pattern;
                        else if (name === 'Bash' && input.command) target = input.command.substring(0, 80);
                        else if (name === 'Task') target = input.description;
                        uses.push(target ? name + ': ' + target : name);
                    }
                });
                if (uses.length)
                    this.streamMeta.tool_uses = (this.streamMeta.tool_uses || []).concat(uses);
            },

            onStreamResult: function(data) {
                if (data.session_id)
                    this.sessionId = data.session_id;
                if (data.result != null)
                    this.streamResultText = data.result;
                if (data.duration_ms)
                    this.streamMeta.duration_ms = data.duration_ms;
                if (data.num_turns)
                    this.streamMeta.num_turns = data.num_turns;
                // Output tokens from result usage (cumulative)
                if (data.usage && data.usage.output_tokens != null)
                    this.streamMeta.output_tokens = data.usage.output_tokens;
                // Model & context window from modelUsage
                if (data.modelUsage) {
                    var modelId = Object.keys(data.modelUsage)[0];
                    if (modelId) {
                        this.streamMeta.model = this.formatModelShort(modelId);
                        var info = data.modelUsage[modelId];
                        if (info && info.contextWindow)
                            this.maxContext = info.contextWindow;
                    }
                }
            },

            /**
             * Shared teardown for all stream-ending paths (success, error, cancel).
             * Disables streaming UI, re-enables the send button, clears the render timer.
             */
            cleanupStreamUI: function() {
                document.getElementById('claude-send-btn').disabled = false;
                this.removeStreamDots();
                this.showCancelButton(false);
                this.closeStreamModal();
                this.hideStreamContainer();
                var modalDots = document.getElementById('claude-modal-dots');
                if (modalDots) modalDots.classList.add('d-none');
                if (this.renderTimer) {
                    clearTimeout(this.renderTimer);
                    this.renderTimer = null;
                }
            },

            onStreamEnd: function() {
                if (this.streamEnded) return;
                this.streamEnded = true;
                this.cleanupStreamUI();

                var userContent = this.streamPromptMarkdown || '(prompt)';

                // Separate process (intermediate) text from final result.
                // The result event carries the canonical final answer; the full
                // streamBuffer contains ALL text_delta output (intermediate + final)
                // and is shown in the collapsible process block.
                var claudeContent, processContent;
                if (this.streamResultText != null) {
                    claudeContent = this.streamResultText || '';
                    processContent = this.streamBuffer || '';
                    // Single-turn: process buffer is identical to result — no intermediate content
                    if (processContent === claudeContent)
                        processContent = '';
                } else {
                    claudeContent = this.streamBuffer || (this.streamThinkingBuffer ? '' : '(No output)');
                    processContent = '';
                }

                // Build meta for display
                var contextUsed = (this.streamMeta.input_tokens || 0) + (this.streamMeta.cache_tokens || 0);
                var meta = {
                    duration_ms: this.streamMeta.duration_ms,
                    model: this.streamMeta.model,
                    context_used: contextUsed,
                    output_tokens: this.streamMeta.output_tokens,
                    num_turns: this.streamMeta.num_turns,
                    tool_uses: this.streamMeta.tool_uses || [],
                    configSource: this.streamMeta.configSource
                };

                this.renderCurrentExchange(userContent, claudeContent, processContent, meta);

                this.messages.push(
                    { role: 'user', content: userContent },
                    { role: 'claude', content: claudeContent, processContent: processContent || '' }
                );

                var pctUsed = Math.min(100, Math.round(contextUsed / this.maxContext * 100));
                this.lastNumTurns = meta.num_turns || null;
                this.lastToolUses = meta.tool_uses;
                this.updateContextMeter(pctUsed, contextUsed);
                if (pctUsed >= 80 && !this.warningDismissed)
                    this.showContextWarning(pctUsed);

                if (this.messages.length === 2) {
                    document.getElementById('claude-reuse-btn').classList.remove('d-none');
                    document.getElementById('claude-summarize-group').classList.remove('d-none');
                }
                document.getElementById('claude-copy-all-wrapper').classList.remove('d-none');
                this.expandPromptEditor();
                this.focusEditor();
            },

            onStreamError: function(msg) {
                this.streamEnded = true;

                // Abort active reader to prevent further events
                if (this.activeReader) {
                    try { this.activeReader.cancel(); } catch (e) {}
                    this.activeReader = null;
                }

                this.cleanupStreamUI();

                // If we have partial streamed text, show it with error appended
                if (this.streamReceivedText) {
                    var claudeBody = this.renderPartialResponse(this.streamBuffer);
                    var alert = document.createElement('div');
                    alert.className = 'alert alert-danger mt-2 mb-0';
                    alert.textContent = msg;
                    claudeBody.appendChild(alert);
                } else {
                    this.addErrorMessage(msg);
                }
                this.expandPromptEditor();
                this.focusEditor();
            },

            cancel: function() {
                // 1. Abort the ReadableStream reader
                if (this.activeReader) {
                    try { this.activeReader.cancel(); } catch (e) {}
                    this.activeReader = null;
                }

                // 2. Tell the server to kill the process
                fetch('$cancelClaudeUrl', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': yii.getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).catch(function(e) { console.error('Cancel request failed:', e); });

                // 3. Finalize UI with what we have so far
                this.streamEnded = true;
                this.cleanupStreamUI();

                // Render partial content into the standalone active response container
                var partialContent = this.streamBuffer || '';
                if (partialContent || this.streamThinkingBuffer) {
                    var claudeBody = this.renderPartialResponse(partialContent);
                    var notice = document.createElement('div');
                    notice.className = 'claude-cancelled-notice';
                    notice.innerHTML = '<i class="bi bi-stop-circle"></i> Generation cancelled';
                    claudeBody.appendChild(notice);
                }

                // Store partial content as a message
                var claudeContent = this.streamBuffer || '(Cancelled)';
                var userContent = this.streamPromptMarkdown || '(prompt)';
                this.messages.push(
                    { role: 'user', content: userContent },
                    { role: 'claude', content: claudeContent }
                );

                if (this.messages.length === 2) {
                    document.getElementById('claude-reuse-btn').classList.remove('d-none');
                    document.getElementById('claude-summarize-group').classList.remove('d-none');
                }
                document.getElementById('claude-copy-all-wrapper').classList.remove('d-none');
                this.expandPromptEditor();
                this.focusEditor();
            },

            showCancelButton: function(visible) {
                var btn = document.getElementById('claude-cancel-btn');
                var modalBtn = document.getElementById('claude-modal-cancel-btn');
                if (visible) {
                    if (btn) btn.classList.remove('d-none');
                    if (modalBtn) modalBtn.classList.remove('d-none');
                } else {
                    if (btn) btn.classList.add('d-none');
                    if (modalBtn) modalBtn.classList.add('d-none');
                }
            },

            scheduleStreamRender: function() {
                if (this.renderTimer) return;
                var self = this;
                this.renderTimer = setTimeout(function() {
                    self.renderTimer = null;
                    self.renderStreamContent();
                }, 100);
            },

            renderStreamContent: function() {
                var modalThinking = document.getElementById('claude-modal-thinking');

                // Render thinking into modal
                if (modalThinking && this.streamThinkingBuffer) {
                    modalThinking.classList.remove('d-none');
                    this.updateStreamQuill(this.streamModalThinkingQuill, this.streamThinkingBuffer);
                }

                // Render text into modal
                this.updateStreamQuill(this.streamModalQuill, this.streamBuffer);

                // Render combined preview (thinking + text) into compact box
                var previewContent = this.streamThinkingBuffer || this.streamBuffer;
                this.updateStreamQuill(this.streamPreviewQuill, previewContent);
                var previewBody = document.getElementById('claude-stream-body');
                if (previewBody)
                    previewBody.scrollTop = previewBody.scrollHeight;
            },

            renderStreamingPlaceholderInto: function(responseEl) {
                var self = this;

                responseEl.innerHTML = '';

                // Compact 5-line preview box (not part of final response)
                var preview = document.createElement('div');
                preview.id = 'claude-stream-preview';
                preview.className = 'claude-stream-preview';
                preview.title = 'Click to view full process';
                preview.innerHTML =
                    '<div class="claude-stream-preview__header">' +
                        '<i class="bi bi-terminal-fill"></i> Claude ' +
                        '<span id="claude-stream-dots" class="claude-thinking-dots">' +
                        '<span></span><span></span><span></span></span>' +
                        '<button type="button" id="claude-cancel-btn" class="claude-cancel-btn d-none" title="Cancel inference">' +
                            '<i class="bi bi-stop-fill"></i> Stop' +
                        '</button>' +
                        '<i class="bi bi-arrows-fullscreen claude-stream-preview__expand"></i>' +
                    '</div>' +
                    '<div id="claude-stream-body" class="claude-stream-preview__body"></div>';
                preview.addEventListener('click', function(e) {
                    if (e.target.closest('.claude-cancel-btn')) return;
                    self.openStreamModal();
                });
                preview.querySelector('.claude-cancel-btn').addEventListener('click', function() {
                    self.cancel();
                });
                responseEl.appendChild(preview);

                // Initialize persistent Quill viewer for preview
                this.streamPreviewQuill = this.createStreamQuill('claude-stream-body');

                // Reset modal content and initialize Quill viewers
                var modalThinking = document.getElementById('claude-modal-thinking');
                var modalThinkingBody = document.getElementById('claude-modal-thinking-body');
                var modalBody = document.getElementById('claude-modal-body');
                var modalDots = document.getElementById('claude-modal-dots');
                modalThinking.classList.add('d-none');
                modalThinkingBody.innerHTML = '';
                modalBody.innerHTML = '';
                modalDots.classList.remove('d-none');
                this.streamModalThinkingQuill = this.createStreamQuill('claude-modal-thinking-body');
                this.streamModalQuill = this.createStreamQuill('claude-modal-body');
            },

            openStreamModal: function() {
                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('claudeStreamModal'));
                modal.show();
            },

            closeStreamModal: function() {
                var modalEl = document.getElementById('claudeStreamModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            },

            createProcessBlock: function(thinkingContent, processContent) {
                var details = document.createElement('details');
                details.className = 'claude-process-block';
                var summary = document.createElement('summary');
                summary.innerHTML = '<i class="bi bi-gear-fill"></i> View process';
                details.appendChild(summary);
                var body = document.createElement('div');
                body.className = 'claude-process-block__content';

                if (thinkingContent) {
                    var thinkingSection = document.createElement('div');
                    thinkingSection.className = 'claude-process-block__thinking';
                    thinkingSection.innerHTML = '<div class="claude-process-block__label">Thinking</div>' +
                        this.renderMarkdown(thinkingContent);
                    body.appendChild(thinkingSection);
                }

                if (processContent) {
                    var reasoningSection = document.createElement('div');
                    reasoningSection.className = 'claude-process-block__reasoning';
                    var reasoningHtml = '';
                    if (thinkingContent)
                        reasoningHtml += '<div class="claude-process-block__label">Intermediate output</div>';
                    reasoningHtml += this.renderMarkdown(processContent);
                    reasoningSection.innerHTML = reasoningHtml;
                    body.appendChild(reasoningSection);
                }

                details.appendChild(body);
                return details;
            },

            removeStreamDots: function() {
                var dots = document.getElementById('claude-stream-dots');
                if (dots) dots.remove();
            },

            hideStreamContainer: function() {
                var el = document.getElementById('claude-stream-container');
                el.innerHTML = '';
                el.classList.add('d-none');
                this.streamPreviewQuill = null;
                this.streamModalQuill = null;
                this.streamModalThinkingQuill = null;
            },

            setStreamPreviewTall: function(tall) {
                var body = document.getElementById('claude-stream-body');
                if (!body) return;
                body.classList.toggle('claude-stream-preview__body--tall', tall);
            },

            hideEmptyState: function() {
                var empty = document.getElementById('claude-empty-state');
                if (empty) empty.classList.add('d-none');
            },

            formatModelShort: function(modelId) {
                var m = modelId.match(/claude-(\w+)-(\d+)-(\d+)/);
                return m ? m[1] + '-' + m[2] + '.' + m[3] : modelId;
            },

            parseJsonResponse: function(response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                var ct = response.headers.get('Content-Type') || '';
                if (ct.indexOf('json') === -1)
                    throw new Error('Session expired. Please reload the page.');
                return response.json();
            },

            /**
             * Create a new accordion item with user prompt and streaming placeholder.
             * The item starts expanded. The response zone inside it will be filled
             * by renderCurrentExchange() or onStreamError()/cancel() when the stream ends.
             */
            createActiveAccordionItem: function(promptText, promptDelta) {
                this.hideEmptyState();
                this.streamEnded = false;

                var accordion = document.getElementById('claude-history-accordion');
                var idx = this.historyCounter++;
                var itemId = 'claude-history-item-' + idx;
                this.activeItemId = itemId;

                // Build header text from prompt
                var headerText = (promptText || '').replace(/[#*_`>\[\]]/g, '').trim();
                if (headerText.length > 80) headerText = headerText.substring(0, 80) + '\u2026';

                var item = document.createElement('div');
                item.className = 'accordion-item';
                item.id = 'item-' + itemId;
                item.innerHTML =
                    '<h2 class="accordion-header" id="heading-' + itemId + '">' +
                        '<button class="accordion-button claude-history-item__header" type="button" ' +
                            'data-bs-toggle="collapse" data-bs-target="#collapse-' + itemId + '" ' +
                            'aria-expanded="true" aria-controls="collapse-' + itemId + '">' +
                            '<span class="claude-history-item__title">' + this.escapeHtml(headerText) + '</span>' +
                            '<span class="claude-history-item__meta"></span>' +
                        '</button>' +
                    '</h2>' +
                    '<div id="collapse-' + itemId + '" class="accordion-collapse collapse show" ' +
                        'aria-labelledby="heading-' + itemId + '">' +
                        '<div class="accordion-body p-0">' +
                            '<div class="claude-active-response"></div>' +
                            '<div class="claude-active-prompt"></div>' +
                        '</div>' +
                    '</div>';

                // Prepend (newest first)
                accordion.insertBefore(item, accordion.firstChild);
                document.getElementById('claude-history-wrapper').classList.remove('d-none');

                // Render user prompt inside the accordion body using Quill Delta
                var promptZone = item.querySelector('.claude-active-prompt');
                this.renderUserPromptInto(promptZone, promptText, promptDelta);

                // Render streaming placeholder outside the accordion
                var streamContainer = document.getElementById('claude-stream-container');
                streamContainer.classList.remove('d-none');
                this.renderStreamingPlaceholderInto(streamContainer);

                // If the prompt editor is already collapsed, use the tall preview
                if (!document.getElementById('claudePromptCard').classList.contains('show'))
                    this.setStreamPreviewTall(true);
            },

            /**
             * Collapse the currently active accordion item (before creating a new one).
             */
            collapseActiveItem: function() {
                if (!this.activeItemId) return;
                var collapseEl = document.getElementById('collapse-' + this.activeItemId);
                if (collapseEl) {
                    var bsCollapse = bootstrap.Collapse.getInstance(collapseEl);
                    if (bsCollapse)
                        bsCollapse.hide();
                    else
                        new bootstrap.Collapse(collapseEl, { toggle: false }).hide();
                }
            },

            /**
             * Move the standalone active response container contents into the
             * current accordion item's response zone. Called on the next send()
             * so the previous response settles into the accordion.
             */
            moveActiveResponseToAccordion: function() {
                var container = document.getElementById('claude-active-response-container');
                if (container.classList.contains('d-none') || !container.hasChildNodes())
                    return;

                var zones = this.getActiveZones();
                if (zones && zones.response) {
                    zones.response.innerHTML = '';
                    while (container.firstChild)
                        zones.response.appendChild(container.firstChild);
                    this.reinitQuillViewers(zones.response);
                }

                container.innerHTML = '';
                container.classList.add('d-none');
            },

            /**
             * Get the active accordion item's response/prompt zones.
             */
            getActiveZones: function() {
                var item = this.activeItemId ? document.getElementById('item-' + this.activeItemId) : null;
                if (!item) return null;
                return {
                    item: item,
                    response: item.querySelector('.claude-active-response'),
                    prompt: item.querySelector('.claude-active-prompt'),
                    metaSpan: item.querySelector('.claude-history-item__meta')
                };
            },

            renderCurrentExchange: function(userContent, claudeContent, processContent, meta) {
                // Render into the standalone container above the accordion.
                // On the next send(), this content is moved into the accordion item.
                var container = document.getElementById('claude-active-response-container');
                container.innerHTML = '';
                container.classList.remove('d-none');

                var msg = this.createClaudeMessageDiv(claudeContent, meta);
                container.appendChild(msg.div);

                // Collapsible process section (thinking + intermediate reasoning)
                if (this.streamThinkingBuffer || processContent) {
                    var details = this.createProcessBlock(this.streamThinkingBuffer, processContent);
                    container.appendChild(details);
                }

                // Initialize Quill after element is in the DOM
                this.renderToQuillViewer(msg.body, claudeContent);

                // Update accordion header meta
                var zones = this.getActiveZones();
                if (zones && meta && zones.metaSpan) {
                    var metaSummary = this.formatMeta(meta);
                    zones.metaSpan.textContent = metaSummary;
                    if (meta.tool_uses && meta.tool_uses.length)
                        zones.metaSpan.title = meta.tool_uses.join('\\n');
                }
            },

            formatMeta: function(meta) {
                var parts = [];
                if (meta.duration_ms)
                    parts.push((meta.duration_ms / 1000).toFixed(1) + 's');
                if (meta.context_used != null || meta.output_tokens != null) {
                    var fmt = function(n) { return n >= 1000 ? (n / 1000).toFixed(1) + 'k' : String(n || 0); };
                    parts.push(fmt(meta.context_used) + '/' + fmt(meta.output_tokens));
                    var maxContext = this.maxContext || 200000;
                    var pctUsed = Math.min(100, Math.round((meta.context_used || 0) / maxContext * 100));
                    parts.push(pctUsed + '% context used');
                }
                if (meta.num_turns)
                    parts.push(meta.num_turns + (meta.num_turns === 1 ? ' turn' : ' turns'));
                if (meta.model) parts.push(meta.model);
                return parts.join(' \u00b7 ');
            },

            updateContextMeter: function(pctUsed, totalUsed) {
                var wrapper = document.getElementById('claude-context-meter-wrapper');
                var fill = document.getElementById('claude-context-meter-fill');
                var textEl = document.getElementById('claude-context-meter-text');

                wrapper.classList.remove('d-none');
                fill.style.width = pctUsed + '%';

                fill.classList.remove(
                    'claude-context-meter__fill--green',
                    'claude-context-meter__fill--orange',
                    'claude-context-meter__fill--red'
                );
                if (pctUsed < 60)
                    fill.classList.add('claude-context-meter__fill--green');
                else if (pctUsed < 80)
                    fill.classList.add('claude-context-meter__fill--orange');
                else
                    fill.classList.add('claude-context-meter__fill--red');

                var fmt = function(n) { return n >= 1000 ? (n / 1000).toFixed(1) + 'k' : String(n || 0); };
                var label = pctUsed + '% context used (' + fmt(totalUsed) + ' / ' + fmt(this.maxContext) + ' tokens)';
                if (this.lastNumTurns)
                    label += ' \u00b7 ' + this.lastNumTurns + (this.lastNumTurns === 1 ? ' turn' : ' turns');
                textEl.textContent = label;
                textEl.title = this.lastToolUses.length ? this.lastToolUses.join('\\n') : '';

                this.updateSummarizeButtonColor(pctUsed);
            },

            updateSummarizeButtonColor: function(pctUsed) {
                var btns = [
                    document.getElementById('claude-summarize-auto-btn'),
                    document.getElementById('claude-summarize-split-toggle')
                ];
                var remove = ['btn-outline-secondary', 'btn-outline-warning', 'btn-outline-danger'];
                var add = pctUsed < 60 ? 'btn-outline-secondary'
                        : pctUsed < 80 ? 'btn-outline-warning'
                        : 'btn-outline-danger';
                btns.forEach(function(btn) {
                    remove.forEach(function(cls) { btn.classList.remove(cls); });
                    btn.classList.add(add);
                });
            },

            showContextWarning: function(pctUsed) {
                var warning = document.getElementById('claude-context-warning');
                var warningText = document.getElementById('claude-context-warning-text');
                warningText.textContent = 'Context usage is at ' + pctUsed + '%.';
                warning.classList.remove('d-none');
            },

            escapeHtml: function(text) {
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(text));
                return div.innerHTML;
            },

            /**
             * Build a claude-message div with header, body, and optional meta/copy button.
             * Returns { div, body } so callers can append notices or extra elements.
             */
            createClaudeMessageDiv: function(markdownContent, meta) {
                var claudeDiv = document.createElement('div');
                claudeDiv.className = 'claude-message claude-message--claude';

                var claudeHeader = document.createElement('div');
                claudeHeader.className = 'claude-message__header';
                claudeHeader.innerHTML = '<i class="bi bi-terminal-fill"></i> Claude';
                claudeDiv.appendChild(claudeHeader);

                var claudeBody = document.createElement('div');
                claudeBody.className = 'claude-message__body';
                claudeBody.setAttribute('data-quill-markdown', markdownContent);
                claudeDiv.appendChild(claudeBody);

                if (meta) {
                    var metaDiv = document.createElement('div');
                    metaDiv.className = 'claude-message__meta';
                    metaDiv.textContent = this.formatMeta(meta);
                    if (meta.tool_uses && meta.tool_uses.length)
                        metaDiv.title = meta.tool_uses.join('\\n');
                    claudeDiv.appendChild(metaDiv);
                }

                var copyBtn = this.createCopyButton(markdownContent);
                claudeDiv.appendChild(copyBtn);

                return { div: claudeDiv, body: claudeBody };
            },

            /**
             * Render partial streamed content into the active response container.
             * Used by onStreamError (with an error alert) and cancel (with a cancelled notice).
             * Returns the .claude-message__body element so callers can append notices.
             */
            renderPartialResponse: function(markdownContent) {
                var container = document.getElementById('claude-active-response-container');
                container.innerHTML = '';
                container.classList.remove('d-none');

                var msg = this.createClaudeMessageDiv(markdownContent, null);
                container.appendChild(msg.div);
                this.renderToQuillViewer(msg.body, markdownContent);

                if (this.streamThinkingBuffer) {
                    var details = this.createProcessBlock(this.streamThinkingBuffer, '');
                    container.appendChild(details);
                }
                return msg.body;
            },

            addErrorMessage: function(errorText) {
                var container = document.getElementById('claude-active-response-container');
                container.innerHTML = '';
                container.classList.remove('d-none');

                var div = document.createElement('div');
                div.className = 'claude-message claude-message--error';

                var header = document.createElement('div');
                header.className = 'claude-message__header';
                header.innerHTML = '<i class="bi bi-terminal-fill"></i> Claude';
                div.appendChild(header);

                var body = document.createElement('div');
                body.className = 'claude-message__body';
                var alert = document.createElement('div');
                alert.className = 'alert alert-danger mb-0';
                alert.textContent = errorText;
                body.appendChild(alert);
                div.appendChild(body);

                container.appendChild(div);
            },

            /**
             * Render user prompt into the given container as a bare Quill read-only viewer,
             * matching the QuillViewerWidget style used on the view page.
             *
             * @param {HTMLElement} promptEl  Container element
             * @param {string}     plainText Plain text (markdown fallback when no delta)
             * @param {object|null} delta    Quill Delta object (preferred) or null
             */
            renderUserPromptInto: function(promptEl, plainText, delta) {
                promptEl.innerHTML = '';

                if (delta) {
                    this.renderDeltaToQuillViewer(promptEl, delta);
                } else if (plainText) {
                    promptEl.setAttribute('data-quill-markdown', plainText);
                    this.renderToQuillViewer(promptEl, plainText);
                } else {
                    promptEl.innerHTML = '<span class="text-muted fst-italic">Sending prompt\u2026</span>';
                }

                if (plainText) {
                    var copyBtn = this.createCopyButton(plainText);
                    promptEl.appendChild(copyBtn);
                }
            },

            renderMarkdown: function(text) {
                if (!text) return '';
                text = this.normalizeMarkdown(String(text));
                var html = marked.parse(text);
                return DOMPurify.sanitize(html, { ADD_ATTR: ['class', 'target', 'rel'] });
            },

            normalizeMarkdown: function(text) {
                // Ensure blank line before ATX headings so marked.js parses them.
                // 1. Heading on its own line but missing the blank line above it
                // 2. Heading glued to preceding text with no newline at all (##+ only to avoid C# false positives)
                return text
                    .replace(/([^\\n])\\n(#{1,6} )/g, '$1\\n\\n$2')
                    .replace(/(\\S)(#{2,6} )/g, '$1\\n\\n$2');
            },

            markdownToDelta: function(text) {
                var html = this.renderMarkdown(text);
                var delta = quill.clipboard.convert({ html: html });
                return JSON.stringify(delta);
            },

            /**
             * Create a read-only Quill instance inside the given container element.
             * Clears existing content and returns the new Quill instance.
             */
            createReadOnlyQuill: function(container) {
                container.innerHTML = '';
                var viewerDiv = document.createElement('div');
                container.appendChild(viewerDiv);
                return new Quill(viewerDiv, {
                    readOnly: true,
                    theme: 'snow',
                    modules: { toolbar: false }
                });
            },

            /**
             * Render markdown text into a read-only Quill viewer inside the given container.
             * Returns the Quill instance so callers can reference it if needed.
             */
            renderToQuillViewer: function(container, markdownText) {
                var viewer = this.createReadOnlyQuill(container);
                var html = this.renderMarkdown(markdownText);
                var delta = quill.clipboard.convert({ html: html });
                viewer.setContents(delta);
                return viewer;
            },

            /**
             * Render a Quill Delta directly into a read-only Quill viewer.
             * Used for user prompts where we already have the original Delta.
             */
            renderDeltaToQuillViewer: function(container, delta) {
                var viewer = this.createReadOnlyQuill(container);
                viewer.setContents(delta);
                return viewer;
            },

            /**
             * Create a read-only Quill instance inside the element with the given ID.
             * Used for streaming content that updates incrementally.
             */
            createStreamQuill: function(containerId) {
                var container = document.getElementById(containerId);
                if (!container) return null;
                return this.createReadOnlyQuill(container);
            },

            /**
             * Update a persistent streaming Quill instance with new markdown content.
             */
            updateStreamQuill: function(viewer, markdownText) {
                if (!viewer) return;
                var html = this.renderMarkdown(markdownText || '');
                var delta = quill.clipboard.convert({ html: html });
                viewer.setContents(delta);
            },

            /**
             * Re-initialize all Quill viewers inside a container (e.g. after DOM move).
             * Finds elements with data-quill-markdown and creates fresh Quill instances.
             */
            reinitQuillViewers: function(container) {
                var self = this;
                var targets = container.querySelectorAll('[data-quill-markdown]');
                targets.forEach(function(el) {
                    var md = el.getAttribute('data-quill-markdown');
                    if (md)
                        self.renderToQuillViewer(el, md);
                });
            },

            hasFormatting: function() {
                var ops = quill.getContents().ops || [];
                for (var i = 0; i < ops.length; i++) {
                    var op = ops[i];
                    if (op.attributes && Object.keys(op.attributes).length > 0)
                        return true;
                    if (typeof op.insert !== 'string')
                        return true;
                }
                return false;
            },

            switchToTextarea: function() {
                if (this.hasFormatting()) {
                    if (!confirm('Switching to plain text will discard all formatting (bold, headers, lists, etc.). Continue?'))
                        return;
                }
                var text = quill.getText().replace(/\\n$/, '');
                document.getElementById('claude-quill-wrapper').classList.add('d-none');
                document.getElementById('claude-textarea-wrapper').classList.remove('d-none');
                document.getElementById('claude-editor-toggle').textContent = 'Switch to rich editor';
                this.inputMode = 'textarea';
                var textarea = document.getElementById('claude-followup-textarea');
                textarea.value = text;
                textarea.focus();
            },

            switchToQuill: function(delta) {
                document.getElementById('claude-textarea-wrapper').classList.add('d-none');
                document.getElementById('claude-quill-wrapper').classList.remove('d-none');
                document.getElementById('claude-editor-toggle').textContent = 'Switch to plain text';
                if (delta)
                    quill.setContents(delta);
                else
                    quill.setText(document.getElementById('claude-followup-textarea').value || '');
                this.inputMode = 'quill';
                quill.focus();
            },

            reuseLastPrompt: function() {
                if (this.lastSentDelta)
                    this.switchToQuill(this.lastSentDelta);
            },

            newSession: function() {
                this.sessionId = null;
                this.messages = [];
                this.lastSentDelta = null;
                this.historyCounter = 0;
                this.streamResultText = null;
                this.maxContext = 200000;
                this.warningDismissed = false;
                if (this.renderTimer) {
                    clearTimeout(this.renderTimer);
                    this.renderTimer = null;
                }
                if (this._toggleBtnTimer) {
                    clearTimeout(this._toggleBtnTimer);
                    this._toggleBtnTimer = null;
                }
                document.getElementById('claude-context-meter-wrapper').classList.add('d-none');
                document.getElementById('claude-context-meter-fill').style.width = '0%';
                document.getElementById('claude-context-warning').classList.add('d-none');

                this.activeItemId = null;

                // Clear history, streaming container, and active response
                document.getElementById('claude-history-accordion').innerHTML = '';
                document.getElementById('claude-history-wrapper').classList.add('d-none');
                this.hideStreamContainer();
                var activeResponseContainer = document.getElementById('claude-active-response-container');
                activeResponseContainer.innerHTML = '';
                activeResponseContainer.classList.add('d-none');

                document.getElementById('claude-copy-all-wrapper').classList.add('d-none');
                document.getElementById('claude-reuse-btn').classList.add('d-none');
                document.getElementById('claude-summarize-group').classList.add('d-none');
                this.updateSummarizeButtonColor(0);
                this.summarizing = false;

                this.expandSettings();
                this.expandPromptEditor();
                this.expandEditor();
                this.switchToQuill(initialDelta);
            },

            collapseSettings: function() {
                var card = document.getElementById('claudeSettingsCard');
                var bsCollapse = bootstrap.Collapse.getInstance(card);
                if (bsCollapse) bsCollapse.hide();
                else new bootstrap.Collapse(card, { toggle: false }).hide();
            },

            collapsePromptEditor: function() {
                var card = document.getElementById('claudePromptCard');
                var bsCollapse = bootstrap.Collapse.getInstance(card);
                if (bsCollapse) bsCollapse.hide();
                else new bootstrap.Collapse(card, { toggle: false }).hide();
            },

            expandPromptEditor: function() {
                var card = document.getElementById('claudePromptCard');
                var bsCollapse = bootstrap.Collapse.getInstance(card);
                if (bsCollapse) bsCollapse.show();
                else new bootstrap.Collapse(card, { toggle: false }).show();
            },

            compactEditor: function() {
                document.getElementById('claude-quill-wrapper').classList.add('claude-editor-compact');
                document.getElementById('claude-textarea-wrapper').classList.add('claude-editor-compact');
            },

            expandEditor: function() {
                document.getElementById('claude-quill-wrapper').classList.remove('claude-editor-compact');
                document.getElementById('claude-textarea-wrapper').classList.remove('claude-editor-compact');
            },

            expandSettings: function() {
                var card = document.getElementById('claudeSettingsCard');
                var bsCollapse = bootstrap.Collapse.getInstance(card);
                if (bsCollapse) bsCollapse.show();
                else new bootstrap.Collapse(card, { toggle: false }).show();
            },

            updateSettingsSummary: function() {
                var summary = document.getElementById('claude-settings-summary');
                var modelEl = document.getElementById('claude-model');
                var permEl = document.getElementById('claude-permission-mode');

                var parts = [];
                var modelText = modelEl.options[modelEl.selectedIndex]?.text || '';
                if (modelText && modelText !== '(Use default)') parts.push(modelText);
                else parts.push('Default model');

                var permText = permEl.options[permEl.selectedIndex]?.text || '';
                if (permText && permText !== '(Use default)') {
                    // Just the first word before the parenthetical
                    parts.push(permText.split(' (')[0]);
                } else {
                    parts.push('Default permissions');
                }

                var statusEl = document.getElementById('claude-config-status');
                if (!statusEl.classList.contains('d-none')) {
                    if (statusEl.classList.contains('alert-danger')) parts.push('Setup issue');
                    else if (statusEl.classList.contains('alert-success')) parts.push('Project config');
                    else if (statusEl.classList.contains('alert-info')) parts.push('PM context');
                    else parts.push('No config');
                }

                summary.textContent = parts.join(' \u00b7 ');
                summary.classList.remove('d-none');
            },

            focusEditor: function() {
                if (this.inputMode === 'quill')
                    quill.focus();
                else
                    document.getElementById('claude-followup-textarea').focus();
            },

            createCopyButton: function(markdownText) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'claude-message__copy';
                btn.title = 'Copy to clipboard';
                btn.setAttribute('aria-label', 'Copy to clipboard');
                btn.setAttribute('data-copy-markdown', markdownText);
                btn.innerHTML = '<i class="bi bi-clipboard"></i>';
                return btn;
            },

            handleCopyClick: function(btn) {
                var text = btn.getAttribute('data-copy-markdown');
                if (!text) return;
                navigator.clipboard.writeText(text).then(function() {
                    var orig = btn.innerHTML;
                    btn.innerHTML = '<i class="bi bi-check"></i>';
                    btn.style.color = '#0d6efd';
                    btn.style.opacity = '1';
                    setTimeout(function() {
                        btn.innerHTML = orig;
                        btn.style.color = '';
                        btn.style.opacity = '';
                    }, 1500);
                }).catch(function() {});
            },

            copyConversation: function() {
                var text = this.messages.map(function(m) {
                    var prefix = m.role === 'user' ? '## You' : '## Claude';
                    return prefix + '\\n\\n' + m.content;
                }).join('\\n\\n---\\n\\n');

                var btn = document.getElementById('claude-copy-all-btn');
                navigator.clipboard.writeText(text).then(function() {
                    var orig = btn.innerHTML;
                    btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-success');
                    setTimeout(function() {
                        btn.innerHTML = orig;
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-outline-secondary');
                    }, 2000);
                });
            },

            summarizeAndContinue: function(autoSend) {
                if (this.summarizing || this.messages.length < 2) return;

                var confirmMsg = autoSend
                    ? 'This will summarize the conversation and immediately start a new session with the summary. Continue?'
                    : 'This will summarize the conversation and place it in the editor for review before starting a new session. Continue?';
                if (!confirm(confirmMsg)) return;

                this.summarizing = true;
                var self = this;

                // Build conversation markdown (same format as copyConversation)
                var conversationText = this.messages.map(function(m) {
                    var prefix = m.role === 'user' ? '## You' : '## Claude';
                    return prefix + '\\n\\n' + m.content;
                }).join('\\n\\n---\\n\\n');

                // Disable buttons and show spinner on primary button
                var summarizeAutoBtn = document.getElementById('claude-summarize-auto-btn');
                var summarizeWarningBtn = document.getElementById('claude-summarize-warning-btn');
                var sendBtn = document.getElementById('claude-send-btn');
                summarizeAutoBtn.disabled = true;
                summarizeWarningBtn.disabled = true;
                sendBtn.disabled = true;
                summarizeAutoBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Summarizing…';

                fetch('$summarizeUrl', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': yii.getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ conversation: conversationText })
                })
                .then(function(r) { return self.parseJsonResponse(r); })
                .then(function(data) {
                    if (data.success && data.summary) {
                        var summary = data.summary;
                        self.restoreSummarizeButtons();
                        self.newSession();

                        if (autoSend) {
                            if (self.inputMode === 'quill') {
                                quill.setText(summary);
                            } else {
                                document.getElementById('claude-followup-textarea').value = summary;
                            }
                            self.send();
                        } else {
                            var prefixed = 'Here is the summary of our previous session. Please read it carefully and continue from where we left off.\\n\\n' + summary;
                            if (self.inputMode === 'quill') {
                                quill.setText(prefixed);
                                quill.focus();
                                quill.setSelection(0, 0);
                            } else {
                                var textarea = document.getElementById('claude-followup-textarea');
                                textarea.value = prefixed;
                                textarea.focus();
                                textarea.setSelectionRange(0, 0);
                            }
                        }
                    } else {
                        alert('Summarization failed: ' + (data.error || 'Unknown error'));
                        self.restoreSummarizeButtons();
                    }
                })
                .catch(function(error) {
                    alert('Summarization request failed: ' + error.message);
                    self.restoreSummarizeButtons();
                });
            },

            restoreSummarizeButtons: function() {
                this.summarizing = false;
                var summarizeAutoBtn = document.getElementById('claude-summarize-auto-btn');
                var summarizeWarningBtn = document.getElementById('claude-summarize-warning-btn');
                var sendBtn = document.getElementById('claude-send-btn');
                summarizeAutoBtn.disabled = false;
                summarizeWarningBtn.disabled = false;
                sendBtn.disabled = false;
                summarizeAutoBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Summarize &amp; New Session';
            },

            openSaveDialogSelect: function() {
                var self = this;
                var list = document.getElementById('save-dialog-message-list');
                list.innerHTML = '';

                // Group messages into exchanges (pairs of user + claude)
                for (var i = 0; i < this.messages.length; i += 2) {
                    var userMsg = this.messages[i];
                    var claudeMsg = this.messages[i + 1];
                    var idx = i / 2;

                    var wrapper = document.createElement('div');
                    wrapper.className = 'save-dialog-item mb-2';

                    // Exchange-level (parent) checkbox
                    var exchangeRow = document.createElement('div');
                    exchangeRow.className = 'form-check save-dialog-item__exchange';

                    var exchangeInput = document.createElement('input');
                    exchangeInput.className = 'form-check-input save-dialog-exchange-cb';
                    exchangeInput.type = 'checkbox';
                    exchangeInput.checked = true;
                    exchangeInput.id = 'save-dialog-exchange-' + idx;
                    exchangeInput.setAttribute('data-exchange-index', String(idx));

                    var exchangeLabel = document.createElement('label');
                    exchangeLabel.className = 'form-check-label fw-semibold save-dialog-item__exchange-label';
                    exchangeLabel.setAttribute('for', 'save-dialog-exchange-' + idx);
                    exchangeLabel.textContent = 'Exchange ' + (idx + 1);

                    exchangeRow.appendChild(exchangeInput);
                    exchangeRow.appendChild(exchangeLabel);
                    wrapper.appendChild(exchangeRow);

                    // Individual message checkboxes
                    var messagesDiv = document.createElement('div');
                    messagesDiv.className = 'save-dialog-item__messages';

                    // User message checkbox
                    if (userMsg) {
                        var userPreview = userMsg.content.replace(/[#*_`>\[\]]/g, '').trim();
                        if (userPreview.length > 100) userPreview = userPreview.substring(0, 100) + '\u2026';

                        var userRow = document.createElement('div');
                        userRow.className = 'form-check save-dialog-item__msg-row';

                        var userInput = document.createElement('input');
                        userInput.className = 'form-check-input save-dialog-msg-cb';
                        userInput.type = 'checkbox';
                        userInput.checked = true;
                        userInput.id = 'save-dialog-msg-' + i;
                        userInput.setAttribute('data-msg-index', String(i));
                        userInput.setAttribute('data-exchange-index', String(idx));

                        var userLabel = document.createElement('label');
                        userLabel.className = 'form-check-label save-dialog-item__label';
                        userLabel.setAttribute('for', 'save-dialog-msg-' + i);
                        userLabel.innerHTML =
                            '<span class="save-dialog-item__role save-dialog-item__role--user"><i class="bi bi-person-fill"></i> You</span>' +
                            '<span class="save-dialog-item__preview">' + this.escapeHtml(userPreview) + '</span>';

                        userRow.appendChild(userInput);
                        userRow.appendChild(userLabel);
                        messagesDiv.appendChild(userRow);
                    }

                    // Claude message checkbox
                    if (claudeMsg) {
                        var claudePreview = claudeMsg.content.replace(/[#*_`>\[\]]/g, '').trim();
                        if (claudePreview.length > 120) claudePreview = claudePreview.substring(0, 120) + '\u2026';

                        var claudeRow = document.createElement('div');
                        claudeRow.className = 'form-check save-dialog-item__msg-row';

                        var claudeInput = document.createElement('input');
                        claudeInput.className = 'form-check-input save-dialog-msg-cb';
                        claudeInput.type = 'checkbox';
                        claudeInput.checked = true;
                        claudeInput.id = 'save-dialog-msg-' + (i + 1);
                        claudeInput.setAttribute('data-msg-index', String(i + 1));
                        claudeInput.setAttribute('data-exchange-index', String(idx));

                        var claudeLabel = document.createElement('label');
                        claudeLabel.className = 'form-check-label save-dialog-item__label';
                        claudeLabel.setAttribute('for', 'save-dialog-msg-' + (i + 1));
                        claudeLabel.innerHTML =
                            '<span class="save-dialog-item__role save-dialog-item__role--claude"><i class="bi bi-terminal-fill"></i> Claude</span>' +
                            '<span class="save-dialog-item__preview">' + this.escapeHtml(claudePreview) + '</span>';

                        claudeRow.appendChild(claudeInput);
                        claudeRow.appendChild(claudeLabel);
                        messagesDiv.appendChild(claudeRow);
                    }

                    wrapper.appendChild(messagesDiv);
                    list.appendChild(wrapper);

                    // Exchange checkbox toggles its children
                    (function(exchangeCb, wrapperEl) {
                        exchangeCb.addEventListener('change', function() {
                            var children = wrapperEl.querySelectorAll('.save-dialog-msg-cb');
                            children.forEach(function(cb) { cb.checked = exchangeCb.checked; });
                            self.syncToggleAll();
                        });
                    })(exchangeInput, wrapper);
                }

                // Individual message checkboxes sync their parent exchange checkbox
                list.querySelectorAll('.save-dialog-msg-cb').forEach(function(cb) {
                    cb.addEventListener('change', function() {
                        var exchangeIdx = cb.getAttribute('data-exchange-index');
                        self.syncExchangeCheckbox(exchangeIdx);
                        self.syncToggleAll();
                    });
                });

                // Reset toggle-all
                document.getElementById('save-dialog-toggle-all').checked = true;

                var modal = new bootstrap.Modal(document.getElementById('saveDialogSelectModal'));
                modal.show();
            },

            toggleAllMessages: function(checked) {
                var checkboxes = document.querySelectorAll('#save-dialog-message-list input[type="checkbox"]');
                checkboxes.forEach(function(cb) {
                    cb.checked = checked;
                    cb.indeterminate = false;
                });
            },

            syncExchangeCheckbox: function(exchangeIdx) {
                var children = document.querySelectorAll('.save-dialog-msg-cb[data-exchange-index="' + exchangeIdx + '"]');
                var exchangeCb = document.getElementById('save-dialog-exchange-' + exchangeIdx);
                var allChecked = true;
                children.forEach(function(cb) {
                    if (!cb.checked) allChecked = false;
                });
                exchangeCb.checked = allChecked;
                exchangeCb.indeterminate = !allChecked && Array.from(children).some(function(cb) { return cb.checked; });
            },

            syncToggleAll: function() {
                var allCbs = document.querySelectorAll('#save-dialog-message-list .save-dialog-msg-cb');
                var toggleAll = document.getElementById('save-dialog-toggle-all');
                var allChecked = true;
                var someChecked = false;
                allCbs.forEach(function(cb) {
                    if (!cb.checked) allChecked = false;
                    if (cb.checked) someChecked = true;
                });
                toggleAll.checked = allChecked;
                toggleAll.indeterminate = !allChecked && someChecked;
            },

            saveDialogContinue: function() {
                // Check at least one individual message is selected
                var checkboxes = document.querySelectorAll('#save-dialog-message-list .save-dialog-msg-cb:checked');
                if (checkboxes.length === 0) {
                    alert('Please select at least one message.');
                    return;
                }

                // Hide select modal, show save modal
                bootstrap.Modal.getInstance(document.getElementById('saveDialogSelectModal')).hide();

                // Suggest a name from the first user message
                var suggestedName = '';
                if (this.messages.length > 0 && this.messages[0].role === 'user') {
                    suggestedName = this.messages[0].content
                        .replace(/[#*_`>\[\]]/g, '')
                        .replace(/\s+/g, ' ')
                        .trim();
                    if (suggestedName.length > 80) {
                        suggestedName = suggestedName.substring(0, 80).replace(/\s\S*$/, '') + '\u2026';
                    }
                }

                // Reset save form
                document.getElementById('save-dialog-name').value = suggestedName;
                document.getElementById('save-dialog-name').classList.remove('is-invalid');
                document.getElementById('save-dialog-name-error').textContent = '';
                document.getElementById('save-dialog-error-alert').classList.add('d-none');

                var saveModal = new bootstrap.Modal(document.getElementById('saveDialogSaveModal'));
                saveModal.show();
                setTimeout(function() { document.getElementById('save-dialog-name').focus(); }, 300);
            },

            saveDialogBack: function() {
                bootstrap.Modal.getInstance(document.getElementById('saveDialogSaveModal')).hide();
                var selectModal = new bootstrap.Modal(document.getElementById('saveDialogSelectModal'));
                selectModal.show();
            },

            saveDialogSave: function() {
                var self = this;
                var nameInput = document.getElementById('save-dialog-name');
                var name = nameInput.value.trim();
                var errorAlert = document.getElementById('save-dialog-error-alert');
                var nameError = document.getElementById('save-dialog-name-error');

                // Validate name
                if (!name) {
                    nameInput.classList.add('is-invalid');
                    nameError.textContent = 'Name is required.';
                    return;
                }
                nameInput.classList.remove('is-invalid');
                nameError.textContent = '';

                var projectId = document.getElementById('save-dialog-project').value || null;

                // Build markdown from individually selected messages
                var checkboxes = document.querySelectorAll('#save-dialog-message-list .save-dialog-msg-cb:checked');
                var selectedParts = [];
                checkboxes.forEach(function(cb) {
                    var idx = parseInt(cb.getAttribute('data-msg-index'), 10);
                    var msg = self.messages[idx];
                    if (!msg) return;
                    var role = (idx % 2 === 0) ? 'You' : 'Claude';
                    selectedParts.push('## ' + role + '\\n\\n' + msg.content);
                });
                var markdown = selectedParts.join('\\n\\n---\\n\\n');

                // Convert markdown to Quill Delta client-side
                var deltaJson = self.markdownToDelta(markdown);

                // Disable save button
                var saveBtn = document.getElementById('save-dialog-save-btn');
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving\u2026';

                // Save as new scratch pad
                fetch('$saveUrl', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': yii.getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        name: name,
                        content: deltaJson,
                        response: '',
                        project_id: projectId
                    })
                })
                .then(function(r) { return self.parseJsonResponse(r); })
                .then(function(saveData) {
                    if (!saveData.success) {
                        var msg = saveData.message || '';
                        if (saveData.errors) {
                            var errs = saveData.errors;
                            if (errs.name) {
                                nameInput.classList.add('is-invalid');
                                nameError.textContent = errs.name[0];
                            }
                            if (!errs.name) msg = Object.values(errs).flat().join(' ');
                        }
                        if (msg) {
                            errorAlert.textContent = msg;
                            errorAlert.classList.remove('d-none');
                        }
                        self.restoreSaveDialogButton();
                        return;
                    }

                    // Success — redirect to view
                    var viewUrl = '$viewUrlTemplate'.replace('__ID__', saveData.id);
                    window.location.href = viewUrl;
                })
                .catch(function(error) {
                    errorAlert.textContent = error.message || 'An unexpected error occurred.';
                    errorAlert.classList.remove('d-none');
                    self.restoreSaveDialogButton();
                });
            },

            restoreSaveDialogButton: function() {
                var saveBtn = document.getElementById('save-dialog-save-btn');
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-save"></i> Save';
            },

            toggleHistory: function() {
                var accordion = document.getElementById('claude-history-accordion');
                var panels = accordion.querySelectorAll('.accordion-collapse');
                var allCollapsed = Array.prototype.every.call(panels, function(p) {
                    return !p.classList.contains('show');
                });

                panels.forEach(function(panel) {
                    var instance = bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false });
                    if (allCollapsed)
                        instance.show();
                    else
                        instance.hide();
                });
            },

            updateToggleHistoryBtn: function() {
                if (this._toggleBtnTimer) return;
                var self = this;
                this._toggleBtnTimer = setTimeout(function() {
                    self._toggleBtnTimer = null;
                    var accordion = document.getElementById('claude-history-accordion');
                    var panels = accordion.querySelectorAll('.accordion-collapse');
                    var btn = document.getElementById('claude-toggle-history-btn');
                    if (!panels.length) return;

                    var allCollapsed = Array.prototype.every.call(panels, function(p) {
                        return !p.classList.contains('show');
                    });

                    if (allCollapsed)
                        btn.innerHTML = '<i class="bi bi-arrows-expand"></i> Expand All';
                    else
                        btn.innerHTML = '<i class="bi bi-arrows-collapse"></i> Collapse All';
                }, 150);
            }
        };

        window.ClaudeChat.init();
        quill.focus();
        quill.setSelection(quill.getLength(), 0);
    })();
    JS;
$this->registerJs($js);
?>
