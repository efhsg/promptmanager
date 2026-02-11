<?php

use app\assets\HighlightAsset;
use app\assets\QuillAsset;
use app\models\Project;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

/** @var View $this */
/** @var Project $project */
/** @var array $projectList */
/** @var array $claudeCommands */
/** @var string|null $gitBranch */
/** @var string|null $breadcrumbs */

QuillAsset::register($this);
HighlightAsset::register($this);
$this->registerJsFile('@web/js/marked.min.js', ['position' => View::POS_HEAD]);
$this->registerJsFile('@web/js/purify.min.js', ['position' => View::POS_HEAD]);
$this->registerCssFile('@web/css/claude-chat.css');

$pParam = ['p' => $project->id];
$streamClaudeUrl = Url::to(array_merge(['/claude/stream'], $pParam));
$cancelClaudeUrl = Url::to(array_merge(['/claude/cancel'], $pParam));
$summarizeUrl = Url::to(array_merge(['/claude/summarize-session'], $pParam));
$summarizePromptUrl = Url::to(array_merge(['/claude/summarize-prompt'], $pParam));
$summarizeResponseUrl = Url::to(array_merge(['/claude/summarize-response'], $pParam));
$saveUrl = Url::to(['/claude/save']);
$suggestNameUrl = Url::to(['/claude/suggest-name']);
$importTextUrl = Url::to(['/claude/import-text']);
$importMarkdownUrl = Url::to(['/claude/import-markdown']);
$viewUrlTemplate = Url::to(['/scratch-pad/view', 'id' => '__ID__']);
$checkConfigUrl = Url::to(array_merge(['/claude/check-config'], $pParam));
$usageUrl = Url::to(array_merge(['/claude/usage'], $pParam));
$projectDefaults = $project->getClaudeOptions();
$projectDefaultsJson = Json::encode($projectDefaults, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$checkConfigUrlJson = Json::encode($checkConfigUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$usageUrlJson = Json::encode($usageUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$gitBranchJson = Json::encode($gitBranch, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$projectNameJson = Json::encode($project->name, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

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
if ($breadcrumbs !== null) {
    foreach (json_decode($breadcrumbs, true) ?? [] as $crumb) {
        if (isset($crumb['label'])) {
            $crumb['label'] = Html::encode($crumb['label']);
        }
        $this->params['breadcrumbs'][] = $crumb;
    }
} else {
    $this->params['breadcrumbs'][] = ['label' => 'Projects', 'url' => ['/project/index']];
    $this->params['breadcrumbs'][] = ['label' => Html::encode($project->name), 'url' => ['/project/view', 'id' => $project->id]];
}
$this->params['breadcrumbs'][] = 'Claude CLI';
?>

<div class="claude-chat-page container">
    <!-- Page Header -->
    <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-terminal-fill me-2"></i>Claude CLI</h1>
        </div>
    </div>

    <!-- Combined bar (settings badges | usage summary) — always on top -->
    <div id="claude-combined-bar" class="claude-combined-bar claude-combined-bar--loading mb-3" role="button">
        <div id="claude-combined-settings" class="claude-combined-bar__settings"></div>
        <div class="claude-combined-bar__divider"></div>
        <div id="claude-combined-usage" class="claude-combined-bar__usage">
            <span class="claude-usage-summary__placeholder">Loading usage...</span>
        </div>
    </div>

    <!-- Subscription Usage (expanded) -->
    <div id="claude-subscription-usage" class="claude-subscription-usage mb-3 d-none">
        <div id="claudeUsageCard">
            <div class="claude-usage-section" role="button" id="claude-usage-expanded">
                <div id="claude-subscription-bars"></div>
            </div>
        </div>
    </div>

    <!-- Section 1: CLI Settings (expanded) -->
    <div class="card mb-4 d-none" id="claudeSettingsCardWrapper">
        <div id="claudeSettingsCard">
            <div class="card-body claude-settings-section">
                <div class="claude-settings-badges-bar" id="claude-settings-badges" role="button" title="Collapse settings">
                    <span class="badge bg-secondary" title="Project"><i class="bi bi-folder2-open"></i> <?= Html::encode($project->name) ?></span>
                    <?php if ($gitBranch): ?>
                    <span class="badge bg-secondary" title="Git branch"><i class="bi bi-signpost-split"></i> <?= Html::encode($gitBranch) ?></span>
                    <?php endif; ?>
                    <span id="claude-config-badge" class="badge d-none"></span>
                    <i class="bi bi-chevron-up claude-settings-badges-bar__chevron"></i>
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

            </div>
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

    <!-- Streaming preview (lives above the prompt editor while Claude is working) -->
    <div id="claude-stream-container" class="d-none mb-4"></div>

    <!-- Active response (rendered here after stream ends, hidden when empty, moved into accordion on next send) -->
    <div id="claude-active-response-container" class="d-none mb-4"></div>

    <!-- Prompt Editor (collapsible) -->
    <div class="card mb-4 claude-prompt-card-sticky">
        <div class="collapse show" id="claudePromptCard">
            <div class="card-body claude-prompt-section">
                <div class="claude-prompt-collapse-bar" id="claude-prompt-collapse-btn" role="button" title="Collapse editor">
                    <i class="bi bi-pencil-square claude-prompt-collapse-bar__icon"></i>
                    <span class="claude-prompt-collapse-bar__label">Prompt editor</span>
                    <i class="bi bi-chevron-up claude-prompt-collapse-bar__chevron"></i>
                </div>
                <!-- Quill editor (initial mode) -->
                <div id="claude-quill-wrapper" class="resizable-editor-container">
                    <div id="claude-quill-toolbar">
                        <span class="ql-formats">
                            <button class="ql-bold"></button>
                            <button class="ql-italic"></button>
                            <button class="ql-underline"></button>
                            <button class="ql-strike"></button>
                            <button class="ql-code"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-blockquote"></button>
                            <button class="ql-code-block"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-list" value="ordered"></button>
                            <button class="ql-list" value="bullet"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-indent" value="-1"></button>
                            <button class="ql-indent" value="+1"></button>
                        </span>
                        <span class="ql-formats">
                            <select class="ql-header">
                                <option value="1"></option>
                                <option value="2"></option>
                                <option value="3"></option>
                                <option value="4"></option>
                                <option value="5"></option>
                                <option value="6"></option>
                                <option selected></option>
                            </select>
                        </span>
                        <span class="ql-formats">
                            <select class="ql-align"></select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-clean"></button>
                        </span>
                        <span class="ql-formats" id="claude-command-slot">
                        </span>
                        <span class="ql-formats claude-toolbar-utils">
                            <button type="button" class="ql-clearEditor" title="Clear editor content">
                                <svg viewBox="0 0 18 18" width="18" height="18"><path d="M3 5h12M7 5V3h4v2M5 5v9a1 1 0 001 1h6a1 1 0 001-1V5" fill="none" stroke="currentColor" stroke-width="1.2"/><line x1="8" y1="8" x2="8" y2="12" stroke="currentColor" stroke-width="1"/><line x1="10" y1="8" x2="10" y2="12" stroke="currentColor" stroke-width="1"/></svg>
                            </button>
                            <button type="button" class="ql-smartPaste" title="Smart Paste (auto-detects markdown)">
                                <svg viewBox="0 0 18 18" width="18" height="18"><rect x="3" y="2" width="12" height="14" rx="1" fill="none" stroke="currentColor" stroke-width="1"/><rect x="6" y="0" width="6" height="3" rx="0.5" fill="none" stroke="currentColor" stroke-width="1"/><text x="9" y="13" text-anchor="middle" font-size="9" font-weight="bold" font-family="sans-serif" fill="currentColor">P</text></svg>
                            </button>
                            <button type="button" class="ql-loadMd" title="Load markdown file">
                                <svg viewBox="0 0 18 18" width="18" height="18"><path d="M4 2h7l4 4v10a1 1 0 01-1 1H4a1 1 0 01-1-1V3a1 1 0 011-1z" fill="none" stroke="currentColor" stroke-width="1"/><path d="M9 7v6M7 11l2 2 2-2" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            </button>
                            <button type="button" id="claude-focus-toggle" class="claude-focus-toggle" title="Focus mode (Alt+F)">
                                <i class="bi bi-arrows-fullscreen"></i>
                                <i class="bi bi-fullscreen-exit"></i>
                            </button>
                        </span>
                    </div>
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
                        <button type="button" id="claude-reuse-btn" class="btn btn-outline-secondary d-none">
                            <i class="bi bi-arrow-counterclockwise"></i> Last prompt
                        </button>
                        <button type="button" id="claude-send-btn" class="btn btn-primary" title="Send (Ctrl+Enter / Alt+S)">
                            <i class="bi bi-send-fill"></i> Send
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div id="claude-prompt-summary" class="claude-collapsible-summary d-none"
             data-bs-toggle="collapse" data-bs-target="#claudePromptCard" role="button">
            <i class="bi bi-pencil-square me-1"></i> Prompt editor
            <button type="button" id="claude-summary-reply-btn"
                    class="claude-collapsible-summary__reply d-none"
                    title="Reply (Alt+R)">
                <i class="bi bi-reply-fill"></i> Reply
            </button>
            <i class="bi bi-chevron-down claude-collapsible-summary__chevron"></i>
        </div>
    </div>

    <!-- Exchange History Accordion (exchanges go here immediately on send) -->
    <div id="claude-history-wrapper" class="d-none mb-4">
        <div class="d-flex align-items-center justify-content-end mb-2">
            <div id="claude-summarize-group" class="btn-group d-none me-2">
                <button type="button" id="claude-summarize-auto-btn" class="btn btn-outline-secondary btn-sm"
                        title="Summarize conversation for review before starting a new session">
                    <i class="bi bi-pencil-square"></i> Summarize
                </button>
                <button type="button" id="claude-summarize-split-toggle"
                        class="btn btn-outline-secondary btn-sm dropdown-toggle dropdown-toggle-split"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="#" id="claude-summarize-btn">
                            <i class="bi bi-arrow-repeat me-1"></i> Summarize &amp; New Session
                            <small class="d-block text-muted">Summarize and start new session automatically</small>
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
                        <div class="input-group">
                            <input type="text" class="form-control" id="save-dialog-name" placeholder="Enter a name...">
                            <button type="button" class="btn btn-outline-secondary" id="suggest-name-btn" title="Suggest name based on content">
                                <i class="bi bi-stars"></i> Suggest
                            </button>
                        </div>
                        <div class="invalid-feedback d-block d-none" id="save-dialog-name-error"></div>
                    </div>
                    <div class="mb-3">
                        <label for="save-dialog-project" class="form-label">Project</label>
                        <?= Html::dropDownList('project_id', $project->id, $projectList, [
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
$claudeCommandsJson = Json::encode($claudeCommands, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$js = <<<JS
    (function() {
        var quill = new Quill('#claude-quill-editor', {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: '#claude-quill-toolbar',
                    handlers: {
                        clearEditor: function() {},
                        smartPaste: function() {},
                        loadMd: function() {}
                    }
                }
            },
            placeholder: 'Enter your prompt...'
        });

        var urlConfig = {
            importTextUrl: '$importTextUrl',
            importMarkdownUrl: '$importMarkdownUrl'
        };
        if (window.QuillToolbar) {
            window.QuillToolbar.setupClearEditor(quill, null);
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
        document.getElementById('claude-command-slot').appendChild(commandDropdown);
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

        var storedContent = sessionStorage.getItem('claudePromptContent');
        var initialDelta = storedContent ? JSON.parse(storedContent) : {"ops":[]};
        sessionStorage.removeItem('claudePromptContent');
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
            streamToken: null,
            messages: [],
            lastSentDelta: null,
            inputMode: 'quill',
            historyCounter: 0,
            projectDefaults: $projectDefaultsJson,
            checkConfigUrl: $checkConfigUrlJson,
            settingsState: 'collapsed',
            usageState: 'collapsed',

            generateUUID: function() {
                if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function')
                    return crypto.randomUUID();
                return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, function(c) {
                    return (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16);
                });
            },

            copyText: function(text) {
                if (navigator.clipboard && navigator.clipboard.writeText)
                    return navigator.clipboard.writeText(text);
                return new Promise(function(resolve, reject) {
                    var textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand('copy') ? resolve() : reject(new Error('execCommand failed'));
                    } catch (err) {
                        reject(err);
                    }
                    document.body.removeChild(textarea);
                });
            },
            usageUrl: $usageUrlJson,
            projectName: $projectNameJson,
            gitBranch: $gitBranchJson,
            maxContext: 200000,
            warningDismissed: false,
            summarizing: false,

            init: function() {
                this.prefillFromDefaults();
                this.checkConfigStatus();
                this.fetchSubscriptionUsage();
                this.updateSettingsSummary();
                this.setupEventListeners();
                this.startUsageAutoRefresh();
                if (window.matchMedia('(max-width: 767.98px)').matches && this.inputMode === 'quill')
                    this.switchToTextareaNoConfirm();
            },

            prefillFromDefaults: function() {
                var d = this.projectDefaults;
                document.getElementById('claude-model').value = d.model || '';
                document.getElementById('claude-permission-mode').value = d.permissionMode || '';
            },

            checkConfigStatus: function() {
                var self = this;
                var badge = document.getElementById('claude-config-badge');

                if (!this.checkConfigUrl) return;

                fetch(this.checkConfigUrl, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return self.parseJsonResponse(r); })
                .then(function(data) {
                    if (!data.success) return;

                    var ps = data.pathStatus;
                    var icon, label, bg, title;

                    if (ps === 'not_mapped') {
                        icon = 'bi-x-circle'; bg = 'bg-danger'; label = 'Not mapped';
                        title = 'Project directory not mapped. Check PATH_MAPPINGS in .env and PROJECTS_ROOT volume mount.';
                    } else if (ps === 'not_accessible') {
                        icon = 'bi-x-circle'; bg = 'bg-danger'; label = 'Not accessible';
                        title = 'Project directory not accessible in container. Check that PROJECTS_ROOT volume is mounted correctly.';
                    } else if (ps === 'has_config') {
                        icon = 'bi-check-circle'; bg = 'badge-config';
                        var parts = [];
                        if (data.hasCLAUDE_MD) parts.push('CLAUDE.md');
                        if (data.hasClaudeDir) parts.push('.claude/');
                        label = parts.join(' + ');
                        title = 'Using project\'s own config: ' + label;
                    } else if (ps === 'no_config' && data.hasPromptManagerContext) {
                        icon = 'bi-info-circle'; bg = 'bg-info'; label = 'PM context';
                        title = 'No project config found. Using managed workspace with PromptManager context.';
                    } else {
                        icon = 'bi-exclamation-triangle'; bg = 'bg-warning'; label = 'No config';
                        title = 'No config found. Claude will use defaults.';
                    }

                    badge.className = 'badge ' + bg;
                    badge.textContent = '';
                    var badgeIcon = document.createElement('i');
                    badgeIcon.className = 'bi ' + icon + ' me-1';
                    badge.appendChild(badgeIcon);
                    badge.appendChild(document.createTextNode(label));
                    badge.title = title;
                    badge.classList.remove('d-none');
                    self.configBadgeLabel = label;
                    self.configBadgeTitle = title;
                    self.configBadgeBg = bg;
                    self.configBadgeIcon = icon;
                    self.updateSettingsSummary();
                })
                .catch(function(err) { console.warn('Config status check failed:', err); });
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
                document.getElementById('suggest-name-btn').addEventListener('click', function() { self.suggestName(); });
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

                var handleEditorKeydown = function(e) {
                    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        e.preventDefault();
                        self.send();
                    }
                    if (e.altKey && e.key.toLowerCase() === 's') {
                        e.preventDefault();
                        self.send();
                    }
                    if (e.altKey && e.key.toLowerCase() === 'g') {
                        var visibleGo = document.querySelector('.claude-message__go:not(.d-none)');
                        if (visibleGo) {
                            e.preventDefault();
                            document.getElementById('claude-summary-reply-btn').classList.add('d-none');
                            self.clearChoiceButtonsFromSummary();
                            self.sendFixedText('Proceed');
                        }
                    }
                    if (e.altKey && e.key.toLowerCase() === 'f') {
                        e.preventDefault();
                        self.toggleFocusMode();
                    }
                    if (e.key === 'Escape' && document.querySelector('.claude-chat-page').classList.contains('claude-focus-mode')) {
                        e.preventDefault();
                        self.toggleFocusMode();
                    }
                };
                document.getElementById('claude-followup-textarea').addEventListener('keydown', handleEditorKeydown);
                quill.root.addEventListener('keydown', handleEditorKeydown);

                // Alt+R must work even when the editor is collapsed and has no focus
                document.addEventListener('keydown', function(e) {
                    if (e.altKey && e.key.toLowerCase() === 'r') {
                        var replyBtn = document.getElementById('claude-summary-reply-btn');
                        if (replyBtn && !replyBtn.classList.contains('d-none')) {
                            e.preventDefault();
                            self.replyExpand();
                        }
                    }
                });

                document.querySelector('.claude-chat-page').addEventListener('click', function(e) {
                    var copyBtn = e.target.closest('.claude-message__copy');
                    if (copyBtn) self.handleCopyClick(copyBtn);

                    var header = e.target.closest('.claude-message--claude .claude-message__header');
                    if (header) {
                        var msg = header.closest('.claude-message--claude');
                        msg.classList.toggle('claude-message--collapsed');
                    }
                });

                document.getElementById('claude-settings-badges').addEventListener('click', function() {
                    self.collapseSettings();
                });
                document.getElementById('claude-combined-settings').addEventListener('click', function(e) {
                    e.stopPropagation();
                    self.toggleSettingsExpanded();
                });
                document.getElementById('claude-combined-usage').addEventListener('click', function(e) {
                    e.stopPropagation();
                    self.toggleUsageExpanded();
                });
                document.getElementById('claude-usage-expanded').addEventListener('click', function() {
                    self.toggleUsageExpanded();
                });

                var promptCard = document.getElementById('claudePromptCard');
                promptCard.addEventListener('hidden.bs.collapse', function() {
                    document.getElementById('claude-prompt-summary').classList.remove('d-none');
                    self.setStreamPreviewTall(true);
                });
                promptCard.addEventListener('shown.bs.collapse', function() {
                    var summary = document.getElementById('claude-prompt-summary');
                    summary.classList.add('d-none');
                    self.setStreamPreviewTall(false);
                    if (self._replyExpand) {
                        self._replyExpand = false;
                        self.swapEditorAboveResponse();
                        self.expandActiveResponse();
                        if (window.scrollY > 0)
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                    self.focusEditor();
                });
                document.getElementById('claude-prompt-collapse-btn').addEventListener('click', function() {
                    self.collapsePromptEditor();
                });
                document.getElementById('claude-summary-reply-btn').addEventListener('click', function(e) {
                    e.stopPropagation();
                    self.replyExpand();
                });
                document.getElementById('claude-focus-toggle').addEventListener('click', function() {
                    self.toggleFocusMode();
                });

                document.getElementById('claude-context-warning-close').addEventListener('click', function() {
                    document.getElementById('claude-context-warning').classList.add('d-none');
                    self.warningDismissed = true;
                });

                document.getElementById('claude-summarize-btn').addEventListener('click', function(e) {
                    e.preventDefault();
                    self.summarizeAndContinue(true);
                });
                document.getElementById('claude-summarize-auto-btn').addEventListener('click', function() {
                    self.summarizeAndContinue(false);
                });
                document.getElementById('claude-summarize-warning-btn').addEventListener('click', function() {
                    self.summarizeAndContinue(false);
                });
            },

            getOptions: function() {
                return {
                    model: document.getElementById('claude-model').value,
                    permissionMode: document.getElementById('claude-permission-mode').value
                };
            },

            send: function() {
                var self = this;
                var options = this.getOptions();
                var sendBtn = document.getElementById('claude-send-btn');

                this.streamToken = this.generateUUID();
                options.streamToken = this.streamToken;

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

                // Clean up smart choice buttons from previous response
                this.clearChoiceButtonsFromSummary();

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
                this.swapResponseAboveEditor();
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

            isEditorEmpty: function() {
                if (this.inputMode === 'quill')
                    return !quill.getText().replace(/\\n$/, '').trim();
                return !document.getElementById('claude-followup-textarea').value.trim();
            },

            needsApproval: function(text) {
                if (!text) return false;
                return /\?\s*$/.test(text.trimEnd());
            },

            /**
             * Parse the last non-empty line of a response for slash-separated
             * choice options like "Post / Bewerk / Skip?" or "Yes / No?".
             * Returns an array of {label, action} objects, or null if no pattern found.
             */
            parseChoiceOptions: function(text) {
                if (!text) return null;
                var lines = text.trimEnd().split('\\n');
                var lastLine = '';
                for (var i = lines.length - 1; i >= 0; i--) {
                    var trimmed = lines[i].trim();
                    if (trimmed) { lastLine = trimmed; break; }
                }
                if (!lastLine) return null;

                // Must contain at least one " / " separator
                if (lastLine.indexOf(' / ') === -1) return null;

                // Strip trailing question mark and whitespace
                var cleaned = lastLine.replace(/\??\s*$/, '');
                var parts = cleaned.split(' / ');

                // Need 2-4 options, each reasonable length (max 30 chars, non-empty)
                if (parts.length < 2 || parts.length > 4) return null;
                for (var j = 0; j < parts.length; j++) {
                    if (!parts[j] || parts[j].length > 30) return null;
                }

                var editWords = ['bewerk', 'edit', 'aanpassen', 'modify', 'adjust'];
                var options = [];
                for (var k = 0; k < parts.length; k++) {
                    var label = parts[k];
                    var action = editWords.indexOf(label.toLowerCase()) !== -1 ? 'edit' : 'send';
                    options.push({ label: label, action: action });
                }
                return options;
            },

            /**
             * Render choice buttons into the message actions area.
             */
            renderChoiceButtons: function(messageDiv, options, claudeContent) {
                var actions = messageDiv.querySelector('.claude-message__actions');
                if (!actions) return;

                var self = this;
                var strip = document.createElement('div');
                strip.className = 'claude-choice-buttons';

                for (var i = 0; i < options.length; i++) {
                    (function(opt) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'claude-choice-btn';
                        btn.textContent = opt.label;

                        if (opt.action === 'edit') {
                            btn.classList.add('claude-choice-btn--edit');
                            btn.title = 'Open editor with response';
                            btn.addEventListener('click', function() {
                                self.choiceEdit(claudeContent);
                            });
                        } else {
                            btn.addEventListener('click', function() {
                                self.choiceSend(opt.label);
                            });
                        }
                        strip.appendChild(btn);
                    })(options[i]);
                }

                // Insert before existing action buttons
                actions.insertBefore(strip, actions.firstChild);
            },

            /**
             * Choice action: send the label as a fixed reply.
             */
            choiceSend: function(label) {
                document.getElementById('claude-summary-reply-btn').classList.add('d-none');
                this.clearChoiceButtonsFromSummary();
                this.sendFixedText(label);
            },

            /**
             * Choice action: load Claude's response (minus the choice line) into the editor.
             */
            choiceEdit: function(claudeContent) {
                // Strip the last line (the choice question) from the response
                var lines = claudeContent.trimEnd().split('\\n');
                for (var i = lines.length - 1; i >= 0; i--) {
                    if (lines[i].trim()) { lines.splice(i, 1); break; }
                }
                var content = lines.join('\\n').trimEnd();

                // Load into editor
                if (this.inputMode === 'quill') {
                    var delta = quill.clipboard.convert({ html: this.renderMarkdown(content) });
                    quill.setContents(delta);
                } else {
                    document.getElementById('claude-followup-textarea').value = content;
                }

                this.clearChoiceButtonsFromSummary();
                this.replyExpand();
            },

            /**
             * Remove choice buttons from summary bar.
             */
            clearChoiceButtonsFromSummary: function() {
                var strip = document.getElementById('claude-summary-choices');
                if (strip) strip.remove();
                this._activeChoiceOptions = null;
            },

            /**
             * Render choice buttons in the collapsed summary bar.
             */
            renderSummaryChoiceButtons: function(options, claudeContent) {
                this.clearChoiceButtonsFromSummary();

                var summary = document.getElementById('claude-prompt-summary');
                var chevron = summary.querySelector('.claude-collapsible-summary__chevron');
                var self = this;

                var strip = document.createElement('span');
                strip.id = 'claude-summary-choices';
                strip.className = 'claude-summary-choices';

                for (var i = 0; i < options.length; i++) {
                    (function(opt) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'claude-collapsible-summary__reply';
                        btn.textContent = opt.label;

                        if (opt.action === 'edit') {
                            btn.classList.add('claude-choice-btn--edit');
                            btn.title = 'Open editor with response';
                        }

                        btn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            if (opt.action === 'edit')
                                self.choiceEdit(claudeContent);
                            else
                                self.choiceSend(opt.label);
                        });
                        strip.appendChild(btn);
                    })(options[i]);
                }

                summary.insertBefore(strip, chevron);
            },

            sendFixedText: function(text) {
                if (this.inputMode === 'quill')
                    quill.setText(text);
                else
                    document.getElementById('claude-followup-textarea').value = text;
                this.send();
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
                if (this._activeChoiceOptions) {
                    document.getElementById('claude-summary-reply-btn').classList.add('d-none');
                    this.renderSummaryChoiceButtons(this._activeChoiceOptions, claudeContent);
                } else {
                    document.getElementById('claude-summary-reply-btn').classList.remove('d-none');
                }
                this.scrollToTopUnlessFocused();
                this.fetchSubscriptionUsage();
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

                document.getElementById('claude-summary-reply-btn').classList.remove('d-none');
                this.expandPromptEditor();
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
                    },
                    body: JSON.stringify({ streamToken: this.streamToken })
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
                document.getElementById('claude-summary-reply-btn').classList.remove('d-none');

                this.expandPromptEditor();
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

                // Auto-scroll after Quill repaint
                var previewBody = document.getElementById('claude-stream-body');
                if (previewBody)
                    requestAnimationFrame(function() {
                        previewBody.scrollTop = previewBody.scrollHeight;
                    });
                var modalBody = document.querySelector('#claudeStreamModal .modal-body');
                if (modalBody)
                    requestAnimationFrame(function() {
                        modalBody.scrollTop = modalBody.scrollHeight;
                    });
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

            /**
             * Attach a hidden process block to a response container and wire its gear toggle.
             */
            attachProcessBlock: function(container, msgDiv, thinkingContent, processContent) {
                if (!thinkingContent && !processContent) return;
                var details = this.createProcessBlock(thinkingContent, processContent);
                details.classList.add('d-none');
                container.appendChild(details);
                msgDiv._processBlock = details;
                var vpBtn = msgDiv.querySelector('.claude-message__view-process');
                if (vpBtn) vpBtn.classList.remove('d-none');
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

                // Build header text from prompt (CSS text-overflow handles visual clipping)
                var headerText = (promptText || '').replace(/[#*_`>\[\]]/g, '').trim();
                if (headerText.length > 200) headerText = headerText.substring(0, 200) + '\u2026';

                var item = document.createElement('div');
                item.className = 'accordion-item';
                item.id = 'item-' + itemId;
                item.innerHTML =
                    '<h2 class="accordion-header" id="heading-' + itemId + '">' +
                        '<button class="accordion-button collapsed claude-history-item__header" type="button" ' +
                            'data-bs-toggle="collapse" data-bs-target="#collapse-' + itemId + '" ' +
                            'aria-expanded="false" aria-controls="collapse-' + itemId + '">' +
                            '<span class="claude-history-item__title">' + this.escapeHtml(headerText) + '</span>' +
                            '<span class="claude-history-item__meta"></span>' +
                        '</button>' +
                    '</h2>' +
                    '<div id="collapse-' + itemId + '" class="accordion-collapse collapse" ' +
                        'aria-labelledby="heading-' + itemId + '">' +
                        '<div class="accordion-body p-0">' +
                            '<div class="claude-active-prompt"></div>' +
                            '<div class="claude-active-response"></div>' +
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

                // Fire-and-forget: summarize prompt into a short title
                this.summarizePromptTitle(itemId, promptText);
            },

            /**
             * Request a short AI-generated title for the accordion item.
             */
            summarizePromptTitle: function(itemId, promptText) {
                fetch('$summarizePromptUrl', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': yii.getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ prompt: promptText })
                })
                .then(function(r) {
                    if (!r.ok) {
                        console.warn('summarizePromptTitle HTTP ' + r.status);
                        return null;
                    }
                    return r.json();
                })
                .then(function(data) {
                    if (!data) return;
                    if (!data.success || !data.title) {
                        console.warn('summarizePromptTitle response:', data);
                        return;
                    }
                    var title = data.title;
                    var titleEl = document.querySelector(
                        '#item-' + itemId + ' .claude-history-item__title'
                    );
                    if (titleEl) {
                        titleEl.textContent = title;
                        titleEl.classList.add('claude-history-item__title--summarized');
                    }
                })
                .catch(function(err) { console.warn('summarizePromptTitle failed:', err); });
            },

            /**
             * Request a short AI-generated summary for the collapsed response bar.
             */
            summarizeResponseTitle: function(messageDiv, responseText) {
                fetch('$summarizeResponseUrl', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': yii.getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ response: responseText })
                })
                .then(function(r) {
                    if (!r.ok) {
                        console.warn('summarizeResponseTitle HTTP ' + r.status);
                        return null;
                    }
                    return r.json();
                })
                .then(function(data) {
                    if (!data) return;
                    if (!data.success || !data.summary) {
                        console.warn('summarizeResponseTitle response:', data);
                        return;
                    }
                    var summaryEl = messageDiv.querySelector('.claude-message__header-summary');
                    if (summaryEl) {
                        summaryEl.textContent = '\u2014 ' + data.summary;
                        summaryEl.classList.add('claude-message__header-summary--summarized');
                    }
                })
                .catch(function(err) { console.warn('summarizeResponseTitle failed:', err); });
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
                    this.checkExpandOverflowAll(zones.response);
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

                // Brief border glow to signal inference completed
                msg.div.classList.add('claude-message--flash');
                msg.div.addEventListener('animationend', function() {
                    msg.div.classList.remove('claude-message--flash');
                }, {once: true});

                // Collapsible process section (thinking + intermediate reasoning)
                this.attachProcessBlock(container, msg.div, this.streamThinkingBuffer, processContent);

                // Initialize Quill after element is in the DOM
                this.renderToQuillViewer(msg.body, claudeContent);
                this.checkExpandOverflow(msg.div);

                // Show choice buttons or Go! button depending on response pattern
                var choiceOptions = this.parseChoiceOptions(claudeContent);
                if (choiceOptions) {
                    this._activeChoiceOptions = choiceOptions;
                    this.renderChoiceButtons(msg.div, choiceOptions, claudeContent);
                } else {
                    this._activeChoiceOptions = null;
                    var goBtn = msg.div.querySelector('.claude-message__go');
                    if (goBtn && this.needsApproval(claudeContent))
                        goBtn.classList.remove('d-none');
                }

                // Fire-and-forget: summarize response into collapsed bar
                this.summarizeResponseTitle(msg.div, claudeContent);

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
                if (meta.context_used != null) {
                    var maxContext = this.maxContext || 200000;
                    var pctUsed = Math.min(100, Math.round((meta.context_used || 0) / maxContext * 100));
                    parts.push(pctUsed + '% context used');
                }
                if (meta.model) parts.push(meta.model);
                if (meta.duration_ms)
                    parts.push((meta.duration_ms / 1000).toFixed(1) + 's');
                return parts.join(' \u00b7 ');
            },

            updateContextMeter: function(pctUsed, totalUsed) {
                var bars = document.getElementById('claude-subscription-bars');
                var colorClass = pctUsed < 60 ? 'green' : pctUsed < 80 ? 'orange' : 'red';

                // Build tooltip with token details
                var fmt = function(n) { return n >= 1000 ? (n / 1000).toFixed(1) + 'k' : String(n || 0); };
                var tooltip = fmt(totalUsed) + ' / ' + fmt(this.maxContext) + ' tokens';
                if (this.lastNumTurns)
                    tooltip += ' \u00b7 ' + this.lastNumTurns + (this.lastNumTurns === 1 ? ' turn' : ' turns');

                // Find or create the context row
                var row = document.getElementById('claude-context-row');
                if (!row) {
                    row = document.createElement('div');
                    row.id = 'claude-context-row';
                    row.className = 'claude-subscription-row';

                    var label = document.createElement('span');
                    label.className = 'claude-subscription-row__label';
                    label.textContent = 'Context used';

                    var barOuter = document.createElement('div');
                    barOuter.className = 'claude-subscription-row__bar';

                    var barFill = document.createElement('div');
                    barFill.className = 'claude-subscription-row__fill';
                    barOuter.appendChild(barFill);

                    var pctLabel = document.createElement('span');
                    pctLabel.className = 'claude-subscription-row__pct';

                    row.appendChild(label);
                    row.appendChild(barOuter);
                    row.appendChild(pctLabel);
                    bars.appendChild(row);
                }

                // Update values
                var fill = row.querySelector('.claude-subscription-row__fill');
                var pctLabel = row.querySelector('.claude-subscription-row__pct');
                fill.style.width = pctUsed + '%';
                fill.className = 'claude-subscription-row__fill claude-subscription-row__fill--' + colorClass;
                pctLabel.textContent = pctUsed + '%';
                pctLabel.title = tooltip;

                this.updateUsageSummary();
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

            startUsageAutoRefresh: function() {
                var self = this;
                setInterval(function() { self.fetchSubscriptionUsage(); }, 300000);
            },

            fetchSubscriptionUsage: function() {
                if (!this.usageUrl) return;
                var self = this;
                fetch(this.usageUrl)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success && data.data)
                            self.renderSubscriptionUsage(data.data);
                    })
                    .catch(function(err) { console.warn('Usage fetch failed:', err); });
            },

            renderSubscriptionUsage: function(data) {
                var wrapper = document.getElementById('claude-subscription-usage');
                var bars = document.getElementById('claude-subscription-bars');
                if (!data.windows || !data.windows.length) return;

                // Preserve context row before clearing
                var contextRow = document.getElementById('claude-context-row');
                if (contextRow) contextRow.remove();

                bars.innerHTML = '';
                var hasWarning = false;

                data.windows.forEach(function(w) {
                    var pct = Math.min(100, Math.round(w.utilization || 0));
                    var colorClass = pct < 60 ? 'green' : pct < 80 ? 'orange' : 'red';
                    if (pct >= 80) hasWarning = true;

                    var resetTooltip = '';
                    if (w.resets_at) {
                        var d = new Date(w.resets_at);
                        var now = new Date();
                        var diffMs = d - now;
                        var timeStr = d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
                        var dateStr = d.toLocaleDateString([], {day: 'numeric', month: 'short'});
                        if (diffMs > 0) {
                            var days = Math.floor(diffMs / 86400000);
                            var hrs = Math.floor((diffMs % 86400000) / 3600000);
                            var mins = Math.floor((diffMs % 3600000) / 60000);
                            var relLabel = days > 0 ? days + 'd ' + hrs + 'h ' + mins + 'm' : hrs > 0 ? hrs + 'h ' + mins + 'm' : mins + 'm';
                            resetTooltip = 'Resets at ' + dateStr + ' ' + timeStr + ' (in ' + relLabel + ')';
                        } else {
                            resetTooltip = 'Reset at ' + dateStr + ' ' + timeStr;
                        }
                    }

                    var row = document.createElement('div');
                    row.className = 'claude-subscription-row';

                    var label = document.createElement('span');
                    label.className = 'claude-subscription-row__label';
                    label.textContent = w.label;
                    if (resetTooltip)
                        label.title = resetTooltip;

                    var barOuter = document.createElement('div');
                    barOuter.className = 'claude-subscription-row__bar';

                    var barFill = document.createElement('div');
                    barFill.className = 'claude-subscription-row__fill claude-subscription-row__fill--' + colorClass;
                    barFill.style.width = pct + '%';
                    barOuter.appendChild(barFill);

                    var pctLabel = document.createElement('span');
                    pctLabel.className = 'claude-subscription-row__pct';
                    pctLabel.textContent = pct + '%';
                    if (resetTooltip)
                        pctLabel.title = resetTooltip;

                    row.appendChild(label);
                    row.appendChild(barOuter);
                    row.appendChild(pctLabel);
                    bars.appendChild(row);
                });

                // Re-insert context row at the bottom if it existed
                if (contextRow)
                    bars.appendChild(contextRow);

                if (hasWarning) wrapper.classList.add('claude-subscription-usage--warning');
                else wrapper.classList.remove('claude-subscription-usage--warning');

                // Populate the combined bar usage summary
                this.updateUsageSummary();
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

                var headerIcon = document.createElement('i');
                headerIcon.className = 'bi bi-terminal-fill';
                claudeHeader.appendChild(headerIcon);
                claudeHeader.appendChild(document.createTextNode(' Claude'));

                var headerSummary = document.createElement('span');
                headerSummary.className = 'claude-message__header-summary';
                claudeHeader.appendChild(headerSummary);

                var headerMeta = document.createElement('span');
                headerMeta.className = 'claude-message__header-meta';
                claudeHeader.appendChild(headerMeta);

                var headerChevron = document.createElement('i');
                headerChevron.className = 'bi bi-chevron-up claude-message__header-chevron';
                claudeHeader.appendChild(headerChevron);

                claudeDiv.appendChild(claudeHeader);

                var claudeBody = document.createElement('div');
                claudeBody.className = 'claude-message__body';
                claudeBody.setAttribute('data-quill-markdown', markdownContent);
                claudeDiv.appendChild(claudeBody);

                var actions = document.createElement('div');
                actions.className = 'claude-message__actions';

                var goBtn = document.createElement('button');
                goBtn.type = 'button';
                goBtn.className = 'claude-message__go d-none';
                goBtn.title = 'Approve and execute (Alt+G)';
                goBtn.innerHTML = '<i class="bi bi-check-lg"></i> Go!';
                var self = this;
                goBtn.addEventListener('click', function() {
                    document.getElementById('claude-summary-reply-btn').classList.add('d-none');
                    self.clearChoiceButtonsFromSummary();
                    self.sendFixedText('Proceed');
                });
                actions.appendChild(goBtn);

                var expandBtn = document.createElement('button');
                expandBtn.type = 'button';
                expandBtn.className = 'claude-message__expand d-none';
                expandBtn.title = 'Expand / collapse response';
                expandBtn.innerHTML = '<i class="bi bi-arrows-angle-expand"></i>';
                expandBtn.addEventListener('click', function() {
                    var expanded = claudeBody.classList.toggle('claude-message__body--expanded');
                    expandBtn.innerHTML = expanded
                        ? '<i class="bi bi-arrows-angle-contract"></i>'
                        : '<i class="bi bi-arrows-angle-expand"></i>';
                });
                actions.appendChild(expandBtn);

                var viewProcessBtn = document.createElement('button');
                viewProcessBtn.type = 'button';
                viewProcessBtn.className = 'claude-message__view-process d-none';
                viewProcessBtn.title = 'View process';
                viewProcessBtn.innerHTML = '<i class="bi bi-gear-fill"></i>';
                viewProcessBtn.addEventListener('click', function() {
                    var block = claudeDiv._processBlock;
                    if (block) {
                        var hidden = block.classList.toggle('d-none');
                        viewProcessBtn.classList.toggle('active');
                        if (!hidden) block.open = true;
                    }
                });
                actions.appendChild(viewProcessBtn);

                var copyBtn = this.createCopyButton(markdownContent);
                actions.appendChild(copyBtn);

                claudeDiv.appendChild(actions);

                // Meta always visible in header bar
                if (meta) {
                    var metaText = this.formatMeta(meta);
                    headerMeta.textContent = metaText;
                    if (meta.tool_uses && meta.tool_uses.length)
                        headerMeta.title = meta.tool_uses.join('\\n');
                }

                // Collapsed summary: content preview only
                var preview = (markdownContent || '').replace(/[#*`_~>\[\]()!]/g, '').trim();
                if (preview.length > 120) preview = preview.substring(0, 120) + '\u2026';
                headerSummary.textContent = preview ? '\u2014 ' + preview : '';

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
                this.checkExpandOverflow(msg.div);

                this.attachProcessBlock(container, msg.div, this.streamThinkingBuffer, '');
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

            checkExpandOverflow: function(msgDiv) {
                requestAnimationFrame(function() {
                    var body = msgDiv.querySelector('.claude-message__body');
                    var btn = msgDiv.querySelector('.claude-message__expand');
                    if (!body || !btn) return;
                    if (body.scrollHeight > body.clientHeight + 2)
                        btn.classList.remove('d-none');
                    else
                        btn.classList.add('d-none');
                });
            },

            checkExpandOverflowAll: function(container) {
                var self = this;
                var msgs = container.querySelectorAll('.claude-message--claude');
                msgs.forEach(function(msgDiv) { self.checkExpandOverflow(msgDiv); });
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
                this.switchToTextareaNoConfirm();
            },

            switchToTextareaNoConfirm: function() {
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
                this.streamToken = null;
                this.messages = [];
                this.lastSentDelta = null;
                this.historyCounter = 0;
                this.streamResultText = null;
                this.maxContext = 200000;
                this.warningDismissed = false;
                this._replyExpand = false;
                if (this.renderTimer) {
                    clearTimeout(this.renderTimer);
                    this.renderTimer = null;
                }
                if (this._toggleBtnTimer) {
                    clearTimeout(this._toggleBtnTimer);
                    this._toggleBtnTimer = null;
                }
                var contextRow = document.getElementById('claude-context-row');
                if (contextRow) contextRow.remove();
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
                document.getElementById('claude-summary-reply-btn').classList.add('d-none');
                this.clearChoiceButtonsFromSummary();
                this.updateSummarizeButtonColor(0);
                this.summarizing = false;

                this.expandSettings();
                this.usageState = 'collapsed';
                document.getElementById('claude-subscription-usage').classList.add('d-none');
                this.syncCombinedBar();
                this.expandPromptEditor();
                this.expandEditor();
                if (window.matchMedia('(max-width: 767.98px)').matches) {
                    quill.setContents(initialDelta);
                    this.switchToTextareaNoConfirm();
                } else {
                    this.switchToQuill(initialDelta);
                }
            },

            collapseSettings: function() {
                if (this.settingsState === 'expanded') {
                    this.settingsState = 'collapsed';
                    document.getElementById('claudeSettingsCardWrapper').classList.add('d-none');
                    this.updateSettingsSummary();
                    this.syncCombinedBar();
                }
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

            replyExpand: function() {
                var container = document.getElementById('claude-active-response-container');
                var goBtn = container ? container.querySelector('.claude-message__go') : null;
                if (goBtn) goBtn.classList.add('d-none');
                this._replyExpand = true;
                this.expandPromptEditor();
            },

            expandActiveResponse: function() {
                var container = document.getElementById('claude-active-response-container');
                if (!container) return;
                var body = container.querySelector('.claude-message__body');
                if (!body || body.classList.contains('claude-message__body--expanded')) return;
                body.classList.add('claude-message__body--expanded');
                var btn = container.querySelector('.claude-message__expand');
                if (btn) btn.innerHTML = '<i class="bi bi-arrows-angle-contract"></i>';
            },

            compactEditor: function() {
                document.getElementById('claude-quill-wrapper').classList.add('claude-editor-compact');
                document.getElementById('claude-textarea-wrapper').classList.add('claude-editor-compact');
            },

            expandEditor: function() {
                document.getElementById('claude-quill-wrapper').classList.remove('claude-editor-compact');
                document.getElementById('claude-textarea-wrapper').classList.remove('claude-editor-compact');
            },

            toggleFocusMode: function() {
                var page = document.querySelector('.claude-chat-page');
                var entering = !page.classList.contains('claude-focus-mode');
                page.classList.toggle('claude-focus-mode');
                if (entering) {
                    this.expandPromptEditor();
                    this.focusEditor();
                }
            },

            expandSettings: function() {
                if (this.settingsState !== 'expanded') {
                    this.settingsState = 'expanded';
                    document.getElementById('claudeSettingsCardWrapper').classList.remove('d-none');
                    this.syncCombinedBar();
                }
            },

            updateSettingsSummary: function() {
                var summary = document.getElementById('claude-combined-settings');
                var modelEl = document.getElementById('claude-model');
                var permEl = document.getElementById('claude-permission-mode');

                summary.innerHTML = '';

                var addBadge = function(icon, text, title, bg) {
                    var span = document.createElement('span');
                    span.className = 'badge ' + (bg || 'bg-secondary');
                    var i = document.createElement('i');
                    i.className = 'bi ' + icon + ' me-1';
                    span.appendChild(i);
                    span.appendChild(document.createTextNode(text));
                    if (title) span.title = title;
                    summary.appendChild(span);
                };

                if (this.projectName)
                    addBadge('bi-folder2-open', this.projectName, 'Project');

                if (this.gitBranch)
                    addBadge('bi-signpost-split', this.gitBranch, 'Git branch');

                if (this.configBadgeLabel)
                    addBadge(this.configBadgeIcon, this.configBadgeLabel, this.configBadgeTitle, this.configBadgeBg);

                var modelText = modelEl.options[modelEl.selectedIndex]?.text || '';
                if (modelText && modelText !== '(Use default)')
                    addBadge('bi-cpu', modelText, 'Model', 'badge-setting');
                else
                    addBadge('bi-cpu', 'Default model', 'Model', 'badge-setting');

                var permText = permEl.options[permEl.selectedIndex]?.text || '';
                if (permText && permText !== '(Use default)')
                    addBadge('bi-shield-check', permText.split(' (')[0], 'Permission mode', 'badge-setting');
                else
                    addBadge('bi-shield-check', 'Default permissions', 'Permission mode', 'badge-setting');
            },

            syncCombinedBar: function() {
                var bar = document.getElementById('claude-combined-bar');
                var settingsPart = document.getElementById('claude-combined-settings');
                var usagePart = document.getElementById('claude-combined-usage');
                var divider = bar.querySelector('.claude-combined-bar__divider');
                var settingsExpanded = this.settingsState === 'expanded';
                var usageExpanded = this.usageState === 'expanded';

                if (settingsExpanded && usageExpanded) {
                    bar.classList.add('d-none');
                    return;
                }

                bar.classList.remove('d-none');

                settingsPart.classList.toggle('d-none', settingsExpanded);
                usagePart.classList.toggle('d-none', usageExpanded);
                divider.classList.toggle('d-none', settingsExpanded || usageExpanded);
            },

            updateUsageSummary: function() {
                var summary = document.getElementById('claude-combined-usage');
                var combinedBar = document.getElementById('claude-combined-bar');
                var rows = document.querySelectorAll('#claude-subscription-bars .claude-subscription-row');

                // Remove loading state
                combinedBar.classList.remove('claude-combined-bar--loading');

                if (!rows.length) {
                    summary.innerHTML = '';
                    return;
                }

                var maxPct = 0;

                summary.innerHTML = '';
                var itemCount = 0;
                rows.forEach(function(row) {
                    var label = row.querySelector('.claude-subscription-row__label');
                    var fill = row.querySelector('.claude-subscription-row__fill');
                    if (!label || !fill) return;

                    if (itemCount > 0) {
                        var sep = document.createElement('span');
                        sep.className = 'claude-usage-summary__sep';
                        sep.textContent = '\u00B7';
                        summary.appendChild(sep);
                    }

                    var pct = parseInt(fill.style.width, 10) || 0;
                    if (pct > maxPct) maxPct = pct;
                    var colorClass = pct < 60 ? 'green' : pct < 80 ? 'orange' : 'red';

                    var item = document.createElement('span');
                    item.className = 'claude-usage-summary__item';
                    if (label.title)
                        item.title = label.title;

                    var labelSpan = document.createElement('span');
                    labelSpan.className = 'claude-usage-summary__label';
                    labelSpan.textContent = label.textContent;

                    var bar = document.createElement('span');
                    bar.className = 'claude-usage-summary__bar';
                    var barFill = document.createElement('span');
                    barFill.className = 'claude-usage-summary__bar-fill claude-usage-summary__bar-fill--' + colorClass;
                    barFill.style.width = pct + '%';
                    bar.appendChild(barFill);

                    var pctSpan = document.createElement('span');
                    pctSpan.className = 'claude-usage-summary__pct claude-usage-summary__pct--' + colorClass;
                    pctSpan.textContent = pct + '%';

                    item.appendChild(labelSpan);
                    item.appendChild(bar);
                    item.appendChild(pctSpan);
                    summary.appendChild(item);
                    itemCount++;
                });

                // Propagate warning state to combined bar
                if (maxPct >= 80)
                    combinedBar.classList.add('claude-combined-bar--warning');
                else
                    combinedBar.classList.remove('claude-combined-bar--warning');

                this._maxUsagePct = maxPct;
            },

            toggleUsageExpanded: function() {
                var wrapper = document.getElementById('claude-subscription-usage');

                if (this.usageState === 'collapsed') {
                    this.usageState = 'expanded';
                    wrapper.classList.remove('d-none');
                } else {
                    this.usageState = 'collapsed';
                    wrapper.classList.add('d-none');
                    this.updateUsageSummary();
                }
                this.syncCombinedBar();
            },

            toggleSettingsExpanded: function() {
                var wrapper = document.getElementById('claudeSettingsCardWrapper');

                if (this.settingsState === 'collapsed') {
                    this.settingsState = 'expanded';
                    wrapper.classList.remove('d-none');
                } else {
                    this.settingsState = 'collapsed';
                    wrapper.classList.add('d-none');
                    this.updateSettingsSummary();
                }
                this.syncCombinedBar();
            },

            focusEditor: function() {
                if (this.inputMode === 'quill')
                    quill.focus();
                else
                    document.getElementById('claude-followup-textarea').focus();
            },

            scrollToTopUnlessFocused: function() {
                var editorHasFocus = quill.hasFocus()
                    || document.activeElement === document.getElementById('claude-followup-textarea');
                if (!editorHasFocus && window.scrollY > 0)
                    window.scrollTo({ top: 0, behavior: 'smooth' });
            },

            _animateSwap: function(el) {
                el.classList.remove('claude-swap-animate');
                void el.offsetWidth;
                el.classList.add('claude-swap-animate');
                el.addEventListener('animationend', function() {
                    el.classList.remove('claude-swap-animate');
                }, {once: true});
            },

            swapEditorAboveResponse: function() {
                var response = document.getElementById('claude-active-response-container');
                var promptCard = document.getElementById('claudePromptCard');
                if (!response || !promptCard || response.classList.contains('d-none')) return;
                var editor = promptCard.parentElement;
                var alreadyAbove = editor.compareDocumentPosition(response) & Node.DOCUMENT_POSITION_FOLLOWING;
                response.parentElement.insertBefore(editor, response);
                if (!alreadyAbove)
                    this._animateSwap(editor);
                document.getElementById('claude-summary-reply-btn').classList.add('d-none');
                this.clearChoiceButtonsFromSummary();
            },

            swapResponseAboveEditor: function() {
                var response = document.getElementById('claude-active-response-container');
                var promptCard = document.getElementById('claudePromptCard');
                if (!response || !promptCard) return;
                var editor = promptCard.parentElement;
                var alreadyAbove = response.compareDocumentPosition(editor) & Node.DOCUMENT_POSITION_FOLLOWING;
                response.parentElement.insertBefore(response, editor);
                if (!alreadyAbove)
                    this._animateSwap(response);
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
                this.copyText(text).then(function() {
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

                var self = this;
                var btn = document.getElementById('claude-copy-all-btn');
                self.copyText(text).then(function() {
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
                summarizeAutoBtn.innerHTML = '<i class="bi bi-pencil-square"></i> Summarize';
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

                // Reset save form
                document.getElementById('save-dialog-name').value = '';
                document.getElementById('save-dialog-name').classList.remove('is-invalid');
                document.getElementById('save-dialog-name-error').textContent = '';
                document.getElementById('save-dialog-name-error').classList.add('d-none');
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
                    nameError.classList.remove('d-none');
                    return;
                }
                nameInput.classList.remove('is-invalid');
                nameError.textContent = '';
                nameError.classList.add('d-none');

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
                                nameError.classList.remove('d-none');
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

            suggestName: function() {
                var self = this;
                var btn = document.getElementById('suggest-name-btn');
                var nameInput = document.getElementById('save-dialog-name');
                var nameError = document.getElementById('save-dialog-name-error');

                nameError.classList.add('d-none');

                // Collect selected user messages from the save dialog checkboxes
                var checkboxes = document.querySelectorAll('#save-dialog-message-list .save-dialog-msg-cb:checked');
                var userParts = [];
                for (var i = 0; i < checkboxes.length; i++) {
                    var idx = parseInt(checkboxes[i].getAttribute('data-msg-index'), 10);
                    var msg = self.messages[idx];
                    if (msg && msg.role === 'user' && typeof msg.content === 'string')
                        userParts.push(msg.content);
                }
                var content = userParts.join('\\n\\n').replace(/[#*_`>\\[\\]]/g, '').replace(/\\s+/g, ' ').trim();

                if (!content) {
                    nameError.textContent = 'Select at least one user message.';
                    nameError.classList.remove('d-none');
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                fetch('$suggestNameUrl', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': yii.getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ content: content })
                })
                .then(function(r) { return self.parseJsonResponse(r); })
                .then(function(data) {
                    if (data.success && data.name) {
                        nameInput.value = data.name;
                        nameInput.classList.remove('is-invalid');
                        nameError.classList.add('d-none');
                    } else {
                        nameError.textContent = data.error || 'Could not generate name.';
                        nameError.classList.remove('d-none');
                    }
                })
                .catch(function() {
                    nameError.textContent = 'Request failed.';
                    nameError.classList.remove('d-none');
                })
                .finally(function() {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-stars"></i> Suggest';
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
