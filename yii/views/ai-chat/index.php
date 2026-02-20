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
/** @var array $aiCommands */
/** @var string|null $gitBranch */
/** @var string|null $breadcrumbs */
/** @var string|null $resumeSessionId */
/** @var int|null $replayRunId */
/** @var string|null $replayRunSummary */
/** @var array $sessionHistory */
/** @var array $providerData */
/** @var string $defaultProvider */

QuillAsset::register($this);
HighlightAsset::register($this);
$this->registerJsFile('@web/js/marked.min.js', ['position' => View::POS_HEAD]);
$this->registerJsFile('@web/js/purify.min.js', ['position' => View::POS_HEAD]);
$this->registerCssFile('@web/css/ai-chat.css');

$pParam = ['p' => $project->id];
$streamUrl = Url::to(array_merge(['/ai-chat/stream'], $pParam));
$cancelUrl = Url::to(array_merge(['/ai-chat/cancel'], $pParam));
$startRunUrl = Url::to(array_merge(['/ai-chat/start-run'], $pParam));
$streamRunUrl = Url::to(['/ai-chat/stream-run']);
$cancelRunUrl = Url::to(['/ai-chat/cancel-run']);
$runStatusUrl = Url::to(['/ai-chat/run-status']);
$summarizeUrl = Url::to(array_merge(['/ai-chat/summarize-session'], $pParam));
$summarizePromptUrl = Url::to(array_merge(['/ai-chat/summarize-prompt'], $pParam));
$summarizeResponseUrl = Url::to(array_merge(['/ai-chat/summarize-response'], $pParam));
$saveUrl = Url::to(['/ai-chat/save']);
$suggestNameUrl = Url::to(['/ai-chat/suggest-name']);
$importTextUrl = Url::to(['/ai-chat/import-text']);
$importMarkdownUrl = Url::to(['/ai-chat/import-markdown']);
$viewUrlTemplate = Url::to(['/note/view', 'id' => '__ID__']);
$checkConfigUrl = Url::to(array_merge(['/ai-chat/check-config'], $pParam));
$usageUrl = Url::to(array_merge(['/ai-chat/usage'], $pParam));
$projectDefaultsPerProvider = [];
foreach ($providerData as $pid => $pd) {
    $projectDefaultsPerProvider[$pid] = $project->getAiOptionsForProvider($pid);
}
$projectDefaultsJson = Json::encode($projectDefaultsPerProvider, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$checkConfigUrlJson = Json::encode($checkConfigUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$usageUrlJson = Json::encode($usageUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$gitBranchJson = Json::encode($gitBranch, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$projectNameJson = Json::encode($project->name, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$resumeSessionIdJson = Json::encode($resumeSessionId, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$replayRunIdJson = Json::encode($replayRunId, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$replayRunSummaryJson = Json::encode($replayRunSummary, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$sessionHistoryJson = Json::encode($sessionHistory ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$providerDataJson = Json::encode($providerData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$defaultProviderJson = Json::encode($defaultProvider, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

$defaultProviderData = $providerData[$defaultProvider] ?? [];
$defaultProviderName = $defaultProviderData['name'] ?? 'AI';
$models = $defaultProviderData['models'] ?? ['' => '(Use default)'];
$permissionModes = $defaultProviderData['permissionModes'] ?? [];
$showProviderSelector = count($providerData) > 1;
$providerOptions = [];
foreach ($providerData as $id => $pd) {
    $providerOptions[$id] = Html::encode($pd['name']);
}

$this->title = Html::encode($defaultProviderName) . ' CLI';
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
$this->params['breadcrumbs'][] = Html::encode($defaultProviderName) . ' CLI';
?>

<div class="ai-chat-page container">
    <!-- Page Header -->
    <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-terminal-fill me-2"></i><?= Html::encode($defaultProviderName) ?> CLI</h1>
        </div>
    </div>
    <div id="ai-provider-status" class="visually-hidden" aria-live="polite"></div>

    <!-- Combined bar (settings badges | usage summary) — always on top -->
    <div id="ai-combined-bar" class="ai-combined-bar ai-combined-bar--loading mb-3" role="button">
        <div id="ai-combined-settings" class="ai-combined-bar__settings"></div>
        <div class="ai-combined-bar__divider"></div>
        <div id="ai-combined-usage" class="ai-combined-bar__usage">
            <span class="ai-usage-summary__placeholder">Loading usage...</span>
        </div>
    </div>

    <!-- Subscription Usage (expanded) -->
    <div id="ai-subscription-usage" class="ai-subscription-usage mb-3 d-none">
        <div id="aiUsageCard">
            <div class="ai-usage-section" role="button" id="ai-usage-expanded">
                <div id="ai-subscription-bars"></div>
            </div>
        </div>
    </div>

    <!-- Section 1: CLI Settings (expanded) -->
    <div class="card mb-4 d-none" id="aiSettingsCardWrapper">
        <div id="aiSettingsCard">
            <div class="card-body ai-settings-section">
                <div class="ai-settings-badges-bar" id="ai-settings-badges" role="button" title="Collapse settings">
                    <span class="badge bg-secondary" title="Project"><i class="bi bi-folder2-open"></i> <?= Html::encode($project->name) ?></span>
                    <?php if ($gitBranch): ?>
                    <span class="badge bg-secondary" title="Git branch"><i class="bi bi-signpost-split"></i> <?= Html::encode($gitBranch) ?></span>
                    <?php endif; ?>
                    <span id="ai-config-badge" class="badge d-none"></span>
                    <i class="bi bi-chevron-up ai-settings-badges-bar__chevron"></i>
                </div>

                <?php if ($showProviderSelector): ?>
                <div id="ai-provider-row" class="ai-provider-row">
                    <div id="ai-settings-locked-alert" class="alert alert-info ai-settings-locked-alert d-none" role="alert" aria-live="assertive">
                        <i class="bi bi-lock-fill me-1"></i>
                        Provider is locked for this session.
                        <a href="#" id="ai-settings-locked-new-session" class="alert-link">Start a New Session</a> to change provider.
                    </div>
                    <div class="row g-3">
                        <div class="col">
                            <label for="ai-provider" class="form-label">Provider</label>
                            <?= Html::dropDownList('ai-provider', $defaultProvider, $providerOptions, [
                                'id' => 'ai-provider',
                                'class' => 'form-select',
                                'aria-label' => 'Select AI provider',
                            ]) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row g-3" id="ai-core-settings-row">
                    <div class="col-md-6">
                        <label for="ai-model" class="form-label">Model</label>
                        <?= Html::dropDownList('ai-model', '', $models, [
                            'id' => 'ai-model',
                            'class' => 'form-select',
                            'aria-label' => 'Select AI model',
                        ]) ?>
                    </div>
                    <div class="col-md-6" id="ai-permission-mode-col">
                        <label for="ai-permission-mode" class="form-label">Permission Mode</label>
                        <?= Html::dropDownList('ai-permission-mode', '', $permissionModes, [
                            'id' => 'ai-permission-mode',
                            'class' => 'form-select',
                            'aria-label' => 'Select permission mode',
                        ]) ?>
                    </div>
                </div>

                <div class="row g-3 mt-1" id="provider-custom-fields"></div>

            </div>
        </div>
    </div>

    <!-- Streaming preview (lives above the prompt editor while AI is working) -->
    <div id="ai-stream-container" class="d-none mb-4"></div>

    <!-- Active response (rendered here after stream ends, hidden when empty, moved into accordion on next send) -->
    <div id="ai-active-response-container" class="d-none mb-4"></div>

    <!-- Prompt Editor (collapsible) -->
    <div class="card mb-4 ai-prompt-card-sticky">
        <div class="collapse show" id="aiPromptCard">
            <div class="card-body ai-prompt-section">
                <div class="ai-prompt-collapse-bar" id="ai-prompt-collapse-btn" role="button" title="Collapse editor">
                    <i class="bi bi-pencil-square ai-prompt-collapse-bar__icon"></i>
                    <span class="ai-prompt-collapse-bar__label">Prompt editor</span>
                    <i class="bi bi-chevron-up ai-prompt-collapse-bar__chevron"></i>
                </div>
                <!-- Quill editor (initial mode) -->
                <div id="ai-quill-wrapper" class="resizable-editor-container">
                    <div id="ai-quill-toolbar">
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
                        <span class="ql-formats" id="ai-command-slot">
                        </span>
                        <span class="ql-formats ai-toolbar-utils">
                            <button type="button" class="ql-clearEditor" title="Clear editor content">
                                <svg viewBox="0 0 18 18" width="18" height="18"><path d="M3 5h12M7 5V3h4v2M5 5v9a1 1 0 001 1h6a1 1 0 001-1V5" fill="none" stroke="currentColor" stroke-width="1.2"/><line x1="8" y1="8" x2="8" y2="12" stroke="currentColor" stroke-width="1"/><line x1="10" y1="8" x2="10" y2="12" stroke="currentColor" stroke-width="1"/></svg>
                            </button>
                            <button type="button" class="ql-smartPaste" title="Smart Paste (auto-detects markdown)">
                                <svg viewBox="0 0 18 18" width="18" height="18"><rect x="3" y="2" width="12" height="14" rx="1" fill="none" stroke="currentColor" stroke-width="1"/><rect x="6" y="0" width="6" height="3" rx="0.5" fill="none" stroke="currentColor" stroke-width="1"/><text x="9" y="13" text-anchor="middle" font-size="9" font-weight="bold" font-family="sans-serif" fill="currentColor">P</text></svg>
                            </button>
                            <button type="button" class="ql-loadMd" title="Load markdown file">
                                <svg viewBox="0 0 18 18" width="18" height="18"><path d="M4 2h7l4 4v10a1 1 0 01-1 1H4a1 1 0 01-1-1V3a1 1 0 011-1z" fill="none" stroke="currentColor" stroke-width="1"/><path d="M9 7v6M7 11l2 2 2-2" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            </button>
                            <button type="button" id="ai-focus-toggle" class="ai-focus-toggle" title="Focus mode (Alt+F)">
                                <i class="bi bi-arrows-fullscreen"></i>
                                <i class="bi bi-fullscreen-exit"></i>
                            </button>
                        </span>
                    </div>
                    <div id="ai-quill-editor" class="resizable-editor"></div>
                </div>

                <!-- Textarea (follow-up mode, hidden initially) -->
                <div id="ai-textarea-wrapper" class="d-none">
                    <textarea id="ai-followup-textarea" class="form-control ai-followup-textarea"
                              rows="3" placeholder="Ask a follow-up question..."></textarea>
                </div>

                <!-- Action buttons + editor toggle -->
                <div class="mt-3 d-flex justify-content-between align-items-center">
                    <div class="ai-editor-toggle">
                        <a href="#" id="ai-editor-toggle" class="small text-muted">
                            Switch to plain text
                        </a>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <button type="button" id="ai-reuse-btn" class="btn btn-outline-secondary d-none">
                            <i class="bi bi-arrow-counterclockwise"></i> Last prompt
                        </button>
                        <button type="button" id="ai-send-btn" class="btn btn-primary" title="Send (Ctrl+Enter / Alt+S)">
                            <i class="bi bi-send-fill"></i> Send
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div id="ai-prompt-summary" class="ai-collapsible-summary d-none"
             data-bs-toggle="collapse" data-bs-target="#aiPromptCard" role="button">
            <i class="bi bi-pencil-square me-1"></i> Prompt editor
            <button type="button" id="ai-summary-reply-btn"
                    class="ai-collapsible-summary__reply d-none"
                    title="Reply (Alt+R)">
                <i class="bi bi-reply-fill"></i> Reply
            </button>
            <i class="bi bi-chevron-down ai-collapsible-summary__chevron"></i>
        </div>
    </div>

    <!-- Exchange History Accordion (exchanges go here immediately on send) -->
    <div id="ai-history-wrapper" class="d-none mb-4">
        <div class="d-flex align-items-center justify-content-end mb-2">
            <div id="ai-summarize-group" class="btn-group d-none me-2">
                <button type="button" id="ai-summarize-auto-btn" class="btn btn-outline-secondary btn-sm"
                        title="Summarize conversation for review before starting a new session">
                    <i class="bi bi-pencil-square"></i> Summarize
                </button>
                <button type="button" id="ai-summarize-split-toggle"
                        class="btn btn-outline-secondary btn-sm dropdown-toggle dropdown-toggle-split"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="#" id="ai-summarize-btn">
                            <i class="bi bi-arrow-repeat me-1"></i> Summarize &amp; New Session
                            <small class="d-block text-muted">Summarize and start new session automatically</small>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="#" id="ai-new-session-btn">
                            <i class="bi bi-x-circle me-1"></i> New Session
                            <small class="d-block text-muted">Discard context and start fresh</small>
                        </a>
                    </li>
                </ul>
            </div>
            <button type="button" id="ai-toggle-history-btn" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrows-collapse"></i> Collapse All
            </button>
        </div>
        <div class="accordion" id="ai-history-accordion"></div>
    </div>

    <!-- Copy All + Save Dialog (below both) -->
    <div id="ai-copy-all-wrapper" class="d-none text-end mb-4">
        <button type="button" id="ai-save-dialog-btn" class="btn btn-outline-primary btn-sm me-1">
            <i class="bi bi-save"></i> Save Dialog
        </button>
        <button type="button" id="ai-copy-all-btn" class="btn btn-outline-secondary btn-sm">
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
                    <h5 class="modal-title" id="saveDialogSaveLabel">Save Note</h5>
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
    <div class="modal fade" id="aiStreamModal" tabindex="-1" aria-labelledby="aiStreamModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="aiStreamModalLabel">
                        <i class="bi bi-terminal-fill me-1"></i>
                        <span id="ai-modal-status-label" class="ai-stream-status">AI thinking
                            <span id="ai-modal-dots" class="ai-thinking-dots"><span></span><span></span><span></span></span>
                        </span>
                    </h5>
                    <span id="ai-modal-timer" class="ai-stream-timer me-2"></span>
                    <button type="button" id="ai-modal-cancel-btn" class="ai-cancel-btn ai-cancel-btn--modal d-none" title="Cancel inference">
                        <i class="bi bi-stop-fill"></i> Stop
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <details id="ai-modal-thinking" class="ai-thinking-block d-none" open>
                        <summary>Thinking</summary>
                        <div id="ai-modal-thinking-body" class="ai-thinking-block__content"></div>
                    </details>
                    <div id="ai-modal-body" class="ai-message__body"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$aiCommandsJson = Json::encode($aiCommands, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$js = <<<JS
    (function() {
        var quill = new Quill('#ai-quill-editor', {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: '#ai-quill-toolbar',
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

        // Build AI command dropdown
        var aiCommands = $aiCommandsJson;
        var commandDropdown = document.createElement('select');
        commandDropdown.classList.add('ql-insertAiCommand', 'ql-picker');
        commandDropdown.innerHTML = '<option value="" selected disabled>Command</option>';
        var firstValue = Object.values(aiCommands)[0];
        var isGrouped = firstValue !== null && firstValue !== undefined && typeof firstValue === 'object';

        if (isGrouped) {
            Object.keys(aiCommands).forEach(function(group) {
                var optgroup = document.createElement('optgroup');
                optgroup.label = group;
                Object.keys(aiCommands[group]).forEach(function(key) {
                    var option = document.createElement('option');
                    option.value = '/' + key + ' ';
                    option.textContent = key;
                    option.title = aiCommands[group][key];
                    optgroup.appendChild(option);
                });
                commandDropdown.appendChild(optgroup);
            });
        } else {
            Object.keys(aiCommands).forEach(function(key) {
                var option = document.createElement('option');
                option.value = '/' + key + ' ';
                option.textContent = key;
                option.title = aiCommands[key];
                commandDropdown.appendChild(option);
            });
        }
        document.getElementById('ai-command-slot').appendChild(commandDropdown);
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

        var storedContent = sessionStorage.getItem('aiPromptContent');
        var initialDelta = storedContent ? JSON.parse(storedContent) : {"ops":[]};
        sessionStorage.removeItem('aiPromptContent');
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

        window.AiChat = {
            sessionId: $resumeSessionIdJson,
            streamToken: null,
            currentRunId: null,
            messages: [],
            lastSentDelta: null,
            inputMode: 'quill',
            historyCounter: 0,
            projectDefaults: $projectDefaultsJson,
            checkConfigUrl: $checkConfigUrlJson,
            settingsState: 'collapsed',
            usageState: 'collapsed',
            providerLocked: false,
            _editHintActive: false,
            _pulseTimer: null,
            _editHintTimer: null,

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
            providerData: $providerDataJson,
            defaultProvider: $defaultProviderJson,
            maxContext: 200000,
            summarizing: false,
            replayRunId: $replayRunIdJson,
            replayRunSummary: $replayRunSummaryJson,
            sessionHistory: $sessionHistoryJson,
            _appendNextAccordionItem: false,

            init: function() {
                this.prefillFromDefaults();
                this.checkConfigStatus();
                var provider = this.getSelectedProviderId();
                var providerMeta = this.providerData[provider];
                if (providerMeta)
                    this.updateCapabilityBadges(providerMeta);
                else
                    this.fetchSubscriptionUsage();
                this.updateSettingsSummary();
                this.setupEventListeners();
                this.startUsageAutoRefresh();
                this.setupUsageCompactResize();
                if (this.sessionHistory && this.sessionHistory.length > 0) {
                    this.collapseSettings();
                    this.compactEditor();
                    var self = this;
                    this.sessionHistory.forEach(function(run) {
                        self.renderHistoricalExchange(run);
                    });
                    document.getElementById('ai-reuse-btn').classList.remove('d-none');
                    document.getElementById('ai-summarize-group').classList.remove('d-none');
                    document.getElementById('ai-copy-all-wrapper').classList.remove('d-none');
                    document.getElementById('ai-summary-reply-btn').classList.remove('d-none');
                }
                if (this.sessionId) {
                    this.lockProvider();
                }
                if (this.replayRunId) {
                    this._appendNextAccordionItem = this.sessionHistory && this.sessionHistory.length > 0;
                    this.connectToStream(this.replayRunId, 0, this.replayRunSummary);
                }
                if (window.matchMedia('(max-width: 767.98px)').matches && this.inputMode === 'quill')
                    this.switchToTextareaNoConfirm();
            },

            connectToStream: function(runId, offset, promptSummary) {
                var self = this;
                this.currentRunId = runId;

                // Reset stream state
                this.streamBuffer = '';
                this.streamThinkingBuffer = '';
                this.streamResultText = null;
                this.streamCurrentBlockType = null;
                this.streamMeta = {};
                this.streamPromptMarkdown = promptSummary || '(Reconnected)';
                this.streamReceivedText = false;
                this.streamLabelSwitched = false;
                this.activeReader = null;

                // Create UI for reconnected stream
                this.createActiveAccordionItem(promptSummary || '(Reconnected)', null);
                this.showCancelButton(true);
                this.swapResponseAboveEditor();

                var url = '$streamRunUrl' + '?runId=' + runId + '&offset=' + (offset || 0);
                fetch(url, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    var reader = response.body.getReader();
                    self.activeReader = reader;
                    var decoder = new TextDecoder();
                    var buffer = '';

                    self.resetInactivityTimer();

                    function processStream() {
                        return reader.read().then(function(result) {
                            if (result.done) {
                                self.onStreamEnd();
                                return;
                            }

                            self.resetInactivityTimer();
                            buffer += decoder.decode(result.value, { stream: true });
                            var lines = buffer.split('\\n');
                            buffer = lines.pop();

                            var streamDone = false;
                            lines.forEach(function(line) {
                                if (streamDone) return;
                                if (line.startsWith('data: ')) {
                                    var payload = line.substring(6);
                                    if (payload === '[DONE]') {
                                        self.onStreamEnd();
                                        streamDone = true;
                                        return;
                                    }
                                    try {
                                        self.onStreamEvent(JSON.parse(payload));
                                    } catch (e) {}
                                }
                            });

                            if (streamDone) {
                                self.cancelActiveReader();
                                return;
                            }
                            return processStream();
                        });
                    }

                    return processStream();
                })
                .catch(function(error) {
                    if (!self.streamEnded)
                        self.onStreamError('Connection lost: ' + error.message);
                });
            },

            prefillFromDefaults: function() {
                var providerEl = document.getElementById('ai-provider');
                var provider = providerEl ? providerEl.value : this.defaultProvider;
                var d = this.projectDefaults[provider] || {};
                var modelEl = document.getElementById('ai-model');
                var permEl = document.getElementById('ai-permission-mode');
                if (modelEl) modelEl.value = d.model || '';
                if (permEl) permEl.value = d.permissionMode || '';
                this.applyProviderFieldVisibility(provider);
                this.renderProviderCustomFields(provider);
                this.prefillCustomFields(provider);
            },

            checkConfigStatus: function() {
                var self = this;
                var badge = document.getElementById('ai-config-badge');

                if (!this.checkConfigUrl) return;

                var providerEl = document.getElementById('ai-provider');
                var provider = providerEl ? providerEl.value : this.defaultProvider;
                var sep = this.checkConfigUrl.indexOf('?') > -1 ? '&' : '?';
                var url = this.checkConfigUrl + sep + 'provider=' + encodeURIComponent(provider);

                fetch(url, {
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
                        if (data.hasConfigFile) parts.push(data.configFileName || 'config file');
                        if (data.hasConfigDir) parts.push(data.configDirName || 'config dir');
                        label = 'Configured';
                        title = 'Project config: ' + parts.join(' + ');
                    } else if (ps === 'no_config' && data.hasPromptManagerContext) {
                        icon = 'bi-info-circle'; bg = 'bg-info'; label = 'PM context';
                        title = 'No project config found. Using managed workspace with PromptManager context.';
                    } else {
                        icon = 'bi-exclamation-triangle'; bg = 'bg-warning'; label = 'No config';
                        title = 'No config found. AI will use defaults.';
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
                document.getElementById('ai-send-btn').addEventListener('click', function() { self.send(); });
                document.getElementById('ai-reuse-btn').addEventListener('click', function() { self.reuseLastPrompt(); });
                document.getElementById('ai-new-session-btn').addEventListener('click', function(e) { e.preventDefault(); self.newSession(); });
                var lockedNewSession = document.getElementById('ai-settings-locked-new-session');
                if (lockedNewSession) lockedNewSession.addEventListener('click', function(e) { e.preventDefault(); self.newSession(); });
                document.getElementById('ai-copy-all-btn').addEventListener('click', function() { self.copyConversation(); });
                document.getElementById('ai-save-dialog-btn').addEventListener('click', function() { self.openSaveDialogSelect(); });
                document.getElementById('save-dialog-toggle-all').addEventListener('change', function() { self.toggleAllMessages(this.checked); });
                document.getElementById('save-dialog-continue-btn').addEventListener('click', function() { self.saveDialogContinue(); });
                document.getElementById('save-dialog-back-btn').addEventListener('click', function() { self.saveDialogBack(); });
                document.getElementById('save-dialog-save-btn').addEventListener('click', function() { self.saveDialogSave(); });
                document.getElementById('suggest-name-btn').addEventListener('click', function() { self.suggestName(); });
                document.getElementById('ai-toggle-history-btn').addEventListener('click', function() { self.toggleHistory(); });
                var historyAccordion = document.getElementById('ai-history-accordion');
                historyAccordion.addEventListener('shown.bs.collapse', function() { self.updateToggleHistoryBtn(); });
                historyAccordion.addEventListener('hidden.bs.collapse', function() { self.updateToggleHistoryBtn(); });
                document.getElementById('ai-modal-cancel-btn').addEventListener('click', function() { self.cancel(); });
                document.getElementById('ai-editor-toggle').addEventListener('click', function(e) {
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
                        var visibleGo = document.querySelector('.ai-message__go:not(.d-none)');
                        if (visibleGo) {
                            e.preventDefault();
                            document.getElementById('ai-summary-reply-btn').classList.add('d-none');
                            self.sendFixedText('Proceed');
                        }
                    }
                    if (e.altKey && e.key.toLowerCase() === 'f') {
                        e.preventDefault();
                        self.toggleFocusMode();
                    }
                    if (e.key === 'Escape' && document.querySelector('.ai-chat-page').classList.contains('ai-focus-mode')) {
                        e.preventDefault();
                        self.toggleFocusMode();
                    }
                };
                document.getElementById('ai-followup-textarea').addEventListener('keydown', handleEditorKeydown);
                quill.root.addEventListener('keydown', handleEditorKeydown);

                // Alt+R must work even when the editor is collapsed and has no focus
                document.addEventListener('keydown', function(e) {
                    if (e.altKey && e.key.toLowerCase() === 'r') {
                        var replyBtn = document.getElementById('ai-summary-reply-btn');
                        if (replyBtn && !replyBtn.classList.contains('d-none')) {
                            e.preventDefault();
                            self.replyExpand();
                        }
                    }
                });

                document.querySelector('.ai-chat-page').addEventListener('click', function(e) {
                    var copyBtn = e.target.closest('.ai-message__copy');
                    if (copyBtn) self.handleCopyClick(copyBtn);

                    var header = e.target.closest('.ai-message--response .ai-message__header');
                    if (header) {
                        var msg = header.closest('.ai-message--response');
                        msg.classList.toggle('ai-message--collapsed');
                    }
                });

                document.getElementById('ai-settings-badges').addEventListener('click', function() {
                    self.collapseSettings();
                });
                document.getElementById('ai-combined-settings').addEventListener('click', function(e) {
                    e.stopPropagation();
                    self.toggleSettingsExpanded();
                });
                document.getElementById('ai-combined-usage').addEventListener('click', function(e) {
                    e.stopPropagation();
                    self.toggleUsageExpanded();
                });
                document.getElementById('ai-usage-expanded').addEventListener('click', function() {
                    self.toggleUsageExpanded();
                });

                var promptCard = document.getElementById('aiPromptCard');
                promptCard.addEventListener('hidden.bs.collapse', function() {
                    document.getElementById('ai-prompt-summary').classList.remove('d-none');
                    self.setStreamPreviewTall(true);
                });
                promptCard.addEventListener('shown.bs.collapse', function() {
                    var summary = document.getElementById('ai-prompt-summary');
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
                document.getElementById('ai-prompt-collapse-btn').addEventListener('click', function() {
                    self.collapsePromptEditor();
                });
                document.getElementById('ai-summary-reply-btn').addEventListener('click', function(e) {
                    e.stopPropagation();
                    self.replyExpand();
                });
                document.getElementById('ai-focus-toggle').addEventListener('click', function() {
                    self.toggleFocusMode();
                });

                document.getElementById('ai-summarize-btn').addEventListener('click', function(e) {
                    e.preventDefault();
                    self.summarizeAndContinue(true);
                });
                document.getElementById('ai-summarize-auto-btn').addEventListener('click', function() {
                    self.summarizeAndContinue(false);
                });

                var providerEl = document.getElementById('ai-provider');
                if (providerEl) {
                    providerEl.addEventListener('change', function() {
                        var id = this.value;
                        if (!Object.hasOwn(self.providerData, id)) return;
                        var data = self.providerData[id];
                        self.repopulateSelect('ai-model', data.models);
                        self.repopulateSelect('ai-permission-mode', data.permissionModes);
                        var d = self.projectDefaults[id] || {};
                        var modelEl = document.getElementById('ai-model');
                        var permEl = document.getElementById('ai-permission-mode');
                        if (modelEl) modelEl.value = d.model || '';
                        if (permEl) permEl.value = d.permissionMode || '';
                        self.applyProviderFieldVisibility(id);
                        self.renderProviderCustomFields(id);
                        self.prefillCustomFields(id);
                        self.checkConfigStatus();
                        var appSuffix = document.title.indexOf(' - ') > -1
                            ? ' - ' + document.title.split(' - ').slice(1).join(' - ')
                            : '';
                        document.title = data.name + ' CLI' + appSuffix;
                        var h1 = document.querySelector('.ai-chat-page h1');
                        if (h1) h1.textContent = data.name + ' CLI';
                        self.updateSettingsSummary();
                        self.updateCapabilityBadges(data);
                        var statusEl = document.getElementById('ai-provider-status');
                        if (statusEl) statusEl.textContent = 'Model and provider options updated for ' + data.name;
                    });
                }
            },

            getOptions: function() {
                var providerEl = document.getElementById('ai-provider');
                var options = {
                    provider: providerEl ? providerEl.value : this.defaultProvider,
                    model: document.getElementById('ai-model').value,
                    permissionMode: document.getElementById('ai-permission-mode').value
                };
                var customFields = document.querySelectorAll('#aiSettingsCard [data-option-key]');
                customFields.forEach(function(el) {
                    var key = el.dataset.optionKey;
                    options[key] = el.type === 'checkbox' ? el.checked : el.value;
                });
                return options;
            },

            repopulateSelect: function(id, options) {
                var el = document.getElementById(id);
                if (!el) return;
                el.innerHTML = '';
                Object.keys(options).forEach(function(key) {
                    var opt = document.createElement('option');
                    opt.value = key;
                    opt.textContent = options[key];
                    el.appendChild(opt);
                });
            },

            getProviderFieldConfig: function(providerId) {
                if (providerId === 'claude') {
                    return { showPermissionMode: true, customFieldKeys: [] };
                }
                if (providerId === 'codex') {
                    return { showPermissionMode: false, customFieldKeys: ['reasoning'] };
                }
                return { showPermissionMode: false, customFieldKeys: [] };
            },

            applyProviderFieldVisibility: function(providerId) {
                var config = this.getProviderFieldConfig(providerId);
                var permissionCol = document.getElementById('ai-permission-mode-col');
                var permissionEl = document.getElementById('ai-permission-mode');
                if (permissionCol) {
                    permissionCol.classList.toggle('d-none', !config.showPermissionMode);
                }
                if (permissionEl) {
                    permissionEl.disabled = !config.showPermissionMode;
                    if (!config.showPermissionMode) {
                        permissionEl.value = '';
                    }
                }
            },

            getFilteredConfigSchema: function(providerId) {
                var data = this.providerData[providerId];
                if (!data || !data.configSchema) return {};

                var config = this.getProviderFieldConfig(providerId);
                if (!config.customFieldKeys || config.customFieldKeys.length === 0) {
                    return {};
                }

                var schema = {};
                config.customFieldKeys.forEach(function(key) {
                    if (Object.hasOwn(data.configSchema, key)) {
                        schema[key] = data.configSchema[key];
                    }
                });

                return schema;
            },

            renderProviderCustomFields: function(providerId) {
                var additionalRow = document.getElementById('provider-custom-fields');
                var coreRow = document.getElementById('ai-core-settings-row');
                if (!additionalRow || !coreRow) return;
                additionalRow.innerHTML = '';
                coreRow.querySelectorAll('.ai-provider-custom-field').forEach(function(el) {
                    el.remove();
                });

                var schema = this.getFilteredConfigSchema(providerId);
                var keys = Object.keys(schema);
                if (keys.length === 0) return;
                var appendToCoreRow = providerId === 'codex';
                var container = appendToCoreRow ? coreRow : additionalRow;

                keys.forEach(function(key) {
                    var field = schema[key];
                    var type = field.type || 'text';
                    var fieldId = 'ai-custom-' + key;
                    var col = document.createElement('div');
                    col.className = type === 'textarea' ? 'col-12' : 'col-md-6';
                    col.classList.add('ai-provider-custom-field');

                    var label = document.createElement('label');
                    label.className = 'form-label';
                    label.setAttribute('for', fieldId);
                    label.textContent = field.label || key;
                    col.appendChild(label);

                    var input;
                    if (type === 'select') {
                        input = document.createElement('select');
                        input.className = 'form-select';
                        var emptyOpt = document.createElement('option');
                        emptyOpt.value = '';
                        emptyOpt.textContent = '(Use default)';
                        input.appendChild(emptyOpt);
                        if (field.options) {
                            Object.keys(field.options).forEach(function(optKey) {
                                var opt = document.createElement('option');
                                opt.value = optKey;
                                opt.textContent = field.options[optKey];
                                input.appendChild(opt);
                            });
                        }
                    } else if (type === 'textarea') {
                        input = document.createElement('textarea');
                        input.className = 'form-control';
                        input.rows = 2;
                        if (field.placeholder) input.placeholder = field.placeholder;
                    } else if (type === 'checkbox') {
                        var wrapper = document.createElement('div');
                        wrapper.className = 'form-check mt-2';
                        input = document.createElement('input');
                        input.type = 'checkbox';
                        input.className = 'form-check-input';
                        input.id = fieldId;
                        input.dataset.optionKey = key;
                        var checkLabel = document.createElement('label');
                        checkLabel.className = 'form-check-label';
                        checkLabel.setAttribute('for', fieldId);
                        checkLabel.textContent = field.label || key;
                        wrapper.appendChild(input);
                        wrapper.appendChild(checkLabel);
                        col.innerHTML = '';
                        col.appendChild(wrapper);
                        if (field.hint) {
                            var hint = document.createElement('div');
                            hint.className = 'form-text';
                            hint.textContent = field.hint;
                            col.appendChild(hint);
                        }
                        container.appendChild(col);
                        return;
                    } else {
                        input = document.createElement('input');
                        input.type = 'text';
                        input.className = 'form-control';
                        if (field.placeholder) input.placeholder = field.placeholder;
                    }

                    input.id = fieldId;
                    input.dataset.optionKey = key;
                    col.appendChild(input);

                    if (field.hint) {
                        var hint = document.createElement('div');
                        hint.className = 'form-text';
                        hint.textContent = field.hint;
                        col.appendChild(hint);
                    }

                    container.appendChild(col);
                });
            },

            prefillCustomFields: function(providerId) {
                var defaults = this.projectDefaults[providerId] || {};
                var customFields = document.querySelectorAll('#aiSettingsCard [data-option-key]');
                customFields.forEach(function(el) {
                    var key = el.dataset.optionKey;
                    var val = defaults[key];
                    if (el.type === 'checkbox')
                        el.checked = !!val;
                    else
                        el.value = val || '';
                });
            },

            lockProvider: function() {
                this.providerLocked = true;
                this._editHintActive = false;
                if (this._pulseTimer) { clearTimeout(this._pulseTimer); this._pulseTimer = null; }
                if (this._editHintTimer) { clearTimeout(this._editHintTimer); this._editHintTimer = null; }

                var el = document.getElementById('ai-provider');
                if (el) el.disabled = true;

                var providerRow = document.getElementById('ai-provider-row');
                if (providerRow) providerRow.classList.add('ai-provider-row--locked');

                var bar = document.getElementById('ai-combined-bar');
                bar.classList.remove('ai-combined-bar--pulse');
                this.updateSettingsSummary();

                var alert = document.getElementById('ai-settings-locked-alert');
                if (alert) alert.classList.remove('d-none');

                var statusEl = document.getElementById('ai-provider-status');
                if (statusEl) statusEl.textContent = 'Provider locked for this session';
            },

            unlockProvider: function() {
                this.providerLocked = false;

                var el = document.getElementById('ai-provider');
                if (el) el.disabled = false;

                var providerRow = document.getElementById('ai-provider-row');
                if (providerRow) providerRow.classList.remove('ai-provider-row--locked');

                this.updateSettingsSummary();

                var alert = document.getElementById('ai-settings-locked-alert');
                if (alert) alert.classList.add('d-none');

                var statusEl = document.getElementById('ai-provider-status');
                if (statusEl) statusEl.textContent = 'Provider unlocked';
            },

            updateCapabilityBadges: function(data) {
                var badge = document.getElementById('ai-config-badge');
                if (!data.supportsConfig) {
                    if (badge) {
                        badge.className = 'badge bg-secondary';
                        badge.textContent = 'Config N/A';
                        badge.title = 'This provider does not support config checking';
                        badge.classList.remove('d-none');
                    }
                } else {
                    this.checkConfigStatus();
                }

                if (!data.supportsUsage) {
                    this.renderUsageUnavailable(data.name);
                } else {
                    this.fetchSubscriptionUsage();
                }
            },

            getSelectedProviderId: function() {
                var providerEl = document.getElementById('ai-provider');
                return providerEl ? providerEl.value : this.defaultProvider;
            },

            send: function() {
                var self = this;
                var options = this.getOptions();
                var sendBtn = document.getElementById('ai-send-btn');

                this.lockProvider();

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
                    var textarea = document.getElementById('ai-followup-textarea');
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
                this.streamLabelSwitched = false;
                this.activeReader = null;
                this.streamPreviewQuill = null;
                this.streamModalQuill = null;
                this.streamModalThinkingQuill = null;

                // Create new accordion item with user prompt + streaming placeholder
                this.createActiveAccordionItem(pendingPrompt, pendingDelta);
                this.showCancelButton(true);
                this.swapResponseAboveEditor();
                this.collapsePromptEditor();

                fetch('$streamUrl', {
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

                    self.resetInactivityTimer();

                    function processStream() {
                        return reader.read().then(function(result) {
                            if (result.done) {
                                self.onStreamEnd();
                                return;
                            }

                            self.resetInactivityTimer();
                            buffer += decoder.decode(result.value, { stream: true });
                            var lines = buffer.split('\\n');
                            buffer = lines.pop();

                            var streamDone = false;
                            lines.forEach(function(line) {
                                if (streamDone) return;
                                if (line.startsWith('data: ')) {
                                    var payload = line.substring(6);
                                    if (payload === '[DONE]') {
                                        self.onStreamEnd();
                                        streamDone = true;
                                        return;
                                    }
                                    try {
                                        self.onStreamEvent(JSON.parse(payload));
                                    } catch (e) {
                                        // skip unparseable lines
                                    }
                                }
                            });

                            if (streamDone) {
                                self.cancelActiveReader();
                                return;
                            }
                            return processStream();
                        });
                    }

                    return processStream();
                })
                .catch(function(error) {
                    if (!self.streamEnded)
                        self.onStreamError('Connection lost: ' + error.message);
                });
            },

            isEditorEmpty: function() {
                if (this.inputMode === 'quill')
                    return !quill.getText().replace(/\\n$/, '').trim();
                return !document.getElementById('ai-followup-textarea').value.trim();
            },

            needsApproval: function(text) {
                if (!text) return false;
                return /\?\s*$/.test(text.trimEnd());
            },

            /**
             * Parse the last lines of a response for choice options.
             *
             * Supported formats (preferred first):
             *   1. Slash-separated: "Post / Bewerk / Skip?"
             *   2. Parenthesized:   "Approve? (Yes / No)"
             *   3. Bracket-letter:  "[I] Implementatie\n[R] Review\n[E] Bewerk"
             *   4. Inline bracket:  "[I] Implementatie [R] Review [E] Bewerk"
             *
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

                var editWords = ['bewerk', 'edit', 'aanpassen', 'modify', 'adjust'];
                var stripMd = function(s) {
                    return s.replace(/[*_`~]/g, '').trim();
                };

                // --- Format 1 & 2: slash-separated ---
                if (lastLine.indexOf(' / ') !== -1) {
                    var choicePart = lastLine;
                    var parenMatch = lastLine.match(/\(([^)]*\/[^)]*)\)/);
                    if (parenMatch)
                        choicePart = parenMatch[1].trim();

                    var cleaned = choicePart.replace(/\??\s*$/, '').trim();
                    var parts = cleaned.split(' / ');

                    // Strip context prefix: "Geen verbeterpunten — door naar Architect / Aanpassen"
                    if (parts.length >= 2 && parts[0].match(/[\u2014\u2013]\s/))
                        parts[0] = parts[0].replace(/^.*[\u2014\u2013]\s+/, '');

                    if (parts.length >= 2 && parts.length <= 4) {
                        var options = [];
                        for (var j = 0; j < parts.length; j++) {
                            var label = stripMd(parts[j]);
                            if (!label || [...label].length > 80) return null;
                            var action = editWords.indexOf(label.toLowerCase()) !== -1 ? 'edit' : 'send';
                            options.push({ label: label, action: action });
                        }
                        return options;
                    }
                }

                // --- Format 3: bracket-letter lines [X] Description ---
                var bracketPattern = /^\[([A-Z])\]\s+(.{1,40})$/u;
                var bracketOptions = [];
                for (var b = lines.length - 1; b >= 0; b--) {
                    var line = lines[b].trim();
                    if (!line) continue;
                    var m = line.match(bracketPattern);
                    if (m)
                        bracketOptions.unshift({ label: stripMd(m[2]), action: editWords.indexOf(stripMd(m[2]).toLowerCase()) !== -1 ? 'edit' : 'send' });
                    else
                        break;
                }
                if (bracketOptions.length >= 2 && bracketOptions.length <= 5)
                    return bracketOptions;

                // --- Format 4: inline bracket-letter on single line ---
                var inlinePattern = /\[([A-Z])\]\s+(.+?)(?=\s*\[[A-Z]\]|$)/g;
                var inlineOptions = [];
                var im;
                while ((im = inlinePattern.exec(lastLine)) !== null) {
                    var iLabel = stripMd(im[2]);
                    if (!iLabel || [...iLabel].length > 40) { inlineOptions = []; break; }
                    inlineOptions.push({ label: iLabel, action: editWords.indexOf(iLabel.toLowerCase()) !== -1 ? 'edit' : 'send' });
                }
                if (inlineOptions.length >= 2 && inlineOptions.length <= 5)
                    return inlineOptions;

                return null;
            },

            /**
             * Render choice buttons into the message actions area.
             */
            renderChoiceButtons: function(messageDiv, options) {
                var actions = messageDiv.querySelector('.ai-message__actions');
                if (!actions) return;

                var self = this;
                var strip = document.createElement('div');
                strip.className = 'ai-choice-buttons';

                for (var i = 0; i < options.length; i++) {
                    (function(opt) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'ai-choice-btn';
                        btn.textContent = opt.label;

                        if (opt.action === 'edit') {
                            btn.classList.add('ai-choice-btn--edit');
                            btn.title = 'Open editor';
                            btn.addEventListener('click', function() {
                                self.choiceEdit();
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
                document.getElementById('ai-summary-reply-btn').classList.add('d-none');
                this.sendFixedText(label);
            },

            /**
             * Choice action: open the prompt editor (empty) for the user to type.
             */
            choiceEdit: function() {
                document.getElementById('ai-summary-reply-btn').classList.add('d-none');
                this.replyExpand();
            },

            sendFixedText: function(text) {
                if (this.inputMode === 'quill')
                    quill.setText(text);
                else
                    document.getElementById('ai-followup-textarea').value = text;
                this.send();
            },

            // --- Stream event handlers ---

            onStreamEvent: function(data) {
                var type = data.type;

                // Meta events (provider-agnostic)
                if (type === 'waiting' || type === 'keepalive')
                    return;
                if (type === 'prompt_markdown') {
                    this.streamPromptMarkdown = data.markdown;
                    if (data.runId)
                        this.currentRunId = data.runId;
                    if (this._pendingSummarize) {
                        this.summarizePromptTitle(this._pendingSummarize.itemId, this._pendingSummarize.promptText);
                        this._pendingSummarize = null;
                    }
                    return;
                }
                if (type === 'run_status') { this.onRunStatus(data); return; }
                if (type === 'server_error') { this.onStreamError(data.error || 'Unknown server error'); return; }
                if (type === 'sync_result') { this.onStreamResult(data); return; }

                // Provider-specific event dispatch
                var providerEl = document.getElementById('ai-provider');
                var activeProvider = providerEl ? providerEl.value : this.defaultProvider;
                var handler = this._eventHandlers[activeProvider] || this._eventHandlers['claude'];
                if (handler) handler.call(this, data);
            },

            _eventHandlers: {
                claude: function(data) {
                    var type = data.type;
                    if (type === 'system' && data.subtype === 'init')
                        this.onStreamInit(data);
                    else if (type === 'stream_event')
                        this.onStreamDelta(data.event);
                    else if (type === 'assistant' && !data.isSidechain)
                        this.onStreamAssistant(data);
                    else if (type === 'result')
                        this.onStreamResult(data);
                },
                codex: function(data) {
                    var type = data.type;
                    if (type === 'thread.started')
                        this.onCodexThreadStarted(data);
                    else if (type === 'item.completed')
                        this.onCodexItemCompleted(data);
                    else if (type === 'turn.completed')
                        this.onCodexTurnCompleted(data);
                    else if (type === 'turn.failed')
                        this.onStreamError((data.error && data.error.message) || 'Codex turn failed');
                    else if (type === 'error')
                        this.onStreamError(data.message || 'Codex error');
                }
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
                        if (this.streamCurrentBlockType === 'thinking') {
                            this.streamThinkingBuffer += delta.text;
                        } else {
                            this.streamBuffer += delta.text;
                            if (!this.streamLabelSwitched) {
                                this.streamLabelSwitched = true;
                                this.updateStreamStatusLabel('responding');
                            }
                        }
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

            // --- Codex-specific stream event handlers ---

            onCodexThreadStarted: function(data) {
                if (data.thread_id)
                    this.sessionId = data.thread_id;
            },

            onCodexItemCompleted: function(data) {
                var item = data.item;
                if (!item) return;
                if (item.type === 'agent_message' || item.type === 'message') {
                    // Flat text field (codex-cli >= 0.104)
                    if (typeof item.text === 'string' && item.text) {
                        this.streamBuffer += item.text;
                        this.streamReceivedText = true;
                        if (!this.streamLabelSwitched) {
                            this.streamLabelSwitched = true;
                            this.updateStreamStatusLabel('responding');
                        }
                    }
                    // Content blocks format (earlier versions)
                    var content = item.content || [];
                    for (var i = 0; i < content.length; i++) {
                        if (content[i].type === 'output_text' || content[i].type === 'text') {
                            this.streamBuffer += content[i].text || '';
                            this.streamReceivedText = true;
                            if (!this.streamLabelSwitched) {
                                this.streamLabelSwitched = true;
                                this.updateStreamStatusLabel('responding');
                            }
                        }
                    }
                    this.scheduleStreamRender();
                } else if (item.type === 'error') {
                    this.onStreamError(item.message || 'Codex error');
                } else if (item.type === 'tool_call' || item.type === 'function_call') {
                    var toolName = item.name || item.function?.name || 'tool';
                    var uses = [toolName];
                    this.streamMeta.tool_uses = (this.streamMeta.tool_uses || []).concat(uses);
                }
            },

            onCodexTurnCompleted: function(data) {
                if (data.usage) {
                    this.streamMeta.input_tokens = data.usage.input_tokens || 0;
                    this.streamMeta.output_tokens = data.usage.output_tokens || 0;
                }
            },

            onRunStatus: function(data) {
                if (data.sessionId)
                    this.sessionId = data.sessionId;

                // Store error so onStreamEnd (triggered by [DONE]) can display it.
                // Do NOT call onStreamError here — that triggers a recovery poll
                // which finds "failed" again, causing duplicate error UI.
                if (data.status === 'failed') {
                    this._runStatusError = data.error || 'Run failed';
                    return;
                }

                // If relay missed the result event or result was empty, use the fallback result text
                if (data.status === 'completed' && data.resultText && !this.streamResultText)
                    this.streamResultText = data.resultText;
            },

            /**
             * Polls run-status and either reconnects to an active stream or
             * recovers a completed result when the SSE connection was lost.
             */
            recoverFromRunStatus: function(runId) {
                var self = this;
                if (!runId || this.streamEnded) return;

                fetch('$runStatusUrl' + '?runId=' + runId, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success || self.streamEnded) return;

                    if (data.status === 'completed') {
                        if (data.resultText != null)
                            self.streamResultText = data.resultText;
                        if (data.sessionId)
                            self.sessionId = data.sessionId;
                        self.onStreamEnd();
                    } else if (data.status === 'failed' || data.status === 'cancelled') {
                        self.onStreamError(data.errorMessage || 'Run ' + data.status);
                    }
                    // If still active (pending/running), do nothing — error message stays visible
                })
                .catch(function() {
                    // Network error — no retry, error message already visible
                });
            },

            /**
             * Cancels the active ReadableStream reader if present.
             */
            cancelActiveReader: function() {
                if (this.activeReader) {
                    try { this.activeReader.cancel(); } catch (e) {}
                    this.activeReader = null;
                }
            },

            /**
             * Resets the inactivity timer. If no data arrives within 120 seconds,
             * the stream is assumed dead and recovery is attempted via run-status.
             */
            resetInactivityTimer: function() {
                var self = this;
                if (this.streamInactivityTimer)
                    clearTimeout(this.streamInactivityTimer);

                this.streamInactivityTimer = setTimeout(function() {
                    if (!self.streamEnded) {
                        self.cancelActiveReader();
                        self.onStreamError('Connection lost — no data received for 120 seconds.');
                    }
                }, 120000);
            },

            /**
             * Shared teardown for all stream-ending paths (success, error, cancel).
             * Disables streaming UI, re-enables the send button, clears the render timer.
             */
            cleanupStreamUI: function() {
                document.getElementById('ai-send-btn').disabled = false;
                this.removeStreamDots();
                this.showCancelButton(false);
                this.closeStreamModal();
                this.hideStreamContainer();
                // Stop inactivity timer
                if (this.streamInactivityTimer) {
                    clearTimeout(this.streamInactivityTimer);
                    this.streamInactivityTimer = null;
                }
                var modalDots = document.getElementById('ai-modal-dots');
                if (modalDots) modalDots.classList.add('d-none');
                if (this.renderTimer) {
                    clearTimeout(this.renderTimer);
                    this.renderTimer = null;
                }
                // Stop elapsed timer
                if (this.streamTimerInterval) {
                    clearInterval(this.streamTimerInterval);
                    this.streamTimerInterval = null;
                }
                // Remove pulse-ring
                var preview = document.getElementById('ai-stream-preview');
                if (preview) preview.classList.remove('ai-stream-preview--active');
                // Reset modal status label and timer for next stream
                var modalLabel = document.getElementById('ai-modal-status-label');
                if (modalLabel) modalLabel.firstChild.textContent = 'AI thinking';
                var modalTimer = document.getElementById('ai-modal-timer');
                if (modalTimer) modalTimer.textContent = '';
            },

            onStreamEnd: function() {
                if (this.streamEnded) return;
                this.streamEnded = true;
                this.currentRunId = null;

                // If run_status indicated failure, delegate to error handler
                if (this._runStatusError) {
                    var errorMsg = this._runStatusError;
                    this._runStatusError = null;
                    this.onStreamError(errorMsg);
                    return;
                }

                this.cleanupStreamUI();

                var userContent = this.streamPromptMarkdown || '(prompt)';

                // Separate process (intermediate) text from final result.
                // The result event carries the canonical final answer; the full
                // streamBuffer contains ALL text_delta output (intermediate + final)
                // and is shown in the collapsible process block.
                // Multi-turn runs may have an empty result field — fall back to streamBuffer.
                var aiContent, processContent;
                if (this.streamResultText) {
                    aiContent = this.streamResultText;
                    processContent = this.streamBuffer || '';
                    // Single-turn: process buffer is identical to result — no intermediate content
                    if (processContent === aiContent)
                        processContent = '';
                } else {
                    aiContent = this.streamBuffer || (this.streamThinkingBuffer ? '' : '(No output)');
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

                this.renderCurrentExchange(userContent, aiContent, processContent, meta);

                this.messages.push(
                    { role: 'user', content: userContent },
                    { role: 'ai', content: aiContent, processContent: processContent || '' }
                );

                var pctUsed = Math.min(100, Math.round(contextUsed / this.maxContext * 100));
                this.lastNumTurns = meta.num_turns || null;
                this.lastToolUses = meta.tool_uses;
                this.updateContextMeter(pctUsed, contextUsed);

                if (this.messages.length === 2) {
                    document.getElementById('ai-reuse-btn').classList.remove('d-none');
                    document.getElementById('ai-summarize-group').classList.remove('d-none');
                }
                document.getElementById('ai-copy-all-wrapper').classList.remove('d-none');
                document.getElementById('ai-summary-reply-btn').classList.remove('d-none');
                this.scrollToTopUnlessFocused();
                this.fetchSubscriptionUsage();
            },

            onStreamError: function(msg) {
                var recoveryRunId = this.currentRunId;
                this.streamEnded = true;
                this.currentRunId = null;

                this.cancelActiveReader();
                this.cleanupStreamUI();

                // If we have partial streamed text, show it with error appended
                if (this.streamReceivedText) {
                    var aiBody = this.renderPartialResponse(this.streamBuffer);
                    var alert = document.createElement('div');
                    alert.className = 'alert alert-danger mt-2 mb-0';
                    alert.textContent = msg;
                    aiBody.appendChild(alert);
                } else {
                    this.addErrorMessage(msg);
                }

                document.getElementById('ai-summary-reply-btn').classList.remove('d-none');
                this.expandPromptEditor();

                // Attempt recovery: if the run completed on the server despite
                // the SSE connection dropping, replace the error with the result
                if (recoveryRunId) {
                    this.streamEnded = false;
                    this.recoverFromRunStatus(recoveryRunId);
                }
            },

            cancel: function() {
                // 1. Abort the ReadableStream reader
                this.cancelActiveReader();

                // 2. Tell the server to kill the process (async run or legacy)
                if (this.currentRunId) {
                    fetch('$cancelRunUrl' + '?runId=' + this.currentRunId, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': yii.getCsrfToken(),
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).catch(function(e) { console.error('Cancel run request failed:', e); });
                } else {
                    fetch('$cancelUrl', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': yii.getCsrfToken(),
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ streamToken: this.streamToken })
                    }).catch(function(e) { console.error('Cancel request failed:', e); });
                }

                // 3. Finalize UI with what we have so far
                this.streamEnded = true;
                this.currentRunId = null;

                this.cleanupStreamUI();

                // Render partial content into the standalone active response container
                var partialContent = this.streamBuffer || '';
                if (partialContent || this.streamThinkingBuffer) {
                    var aiBody = this.renderPartialResponse(partialContent);
                    var notice = document.createElement('div');
                    notice.className = 'ai-cancelled-notice';
                    notice.innerHTML = '<i class="bi bi-stop-circle"></i> Generation cancelled';
                    aiBody.appendChild(notice);
                }

                // Store partial content as a message
                var aiContent = this.streamBuffer || '(Cancelled)';
                var userContent = this.streamPromptMarkdown || '(prompt)';
                this.messages.push(
                    { role: 'user', content: userContent },
                    { role: 'ai', content: aiContent }
                );

                if (this.messages.length === 2) {
                    document.getElementById('ai-reuse-btn').classList.remove('d-none');
                    document.getElementById('ai-summarize-group').classList.remove('d-none');
                }
                document.getElementById('ai-copy-all-wrapper').classList.remove('d-none');
                document.getElementById('ai-summary-reply-btn').classList.remove('d-none');

                this.expandPromptEditor();
            },

            showCancelButton: function(visible) {
                var btn = document.getElementById('ai-cancel-btn');
                var modalBtn = document.getElementById('ai-modal-cancel-btn');
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
                var modalThinking = document.getElementById('ai-modal-thinking');

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
                var previewBody = document.getElementById('ai-stream-body');
                if (previewBody)
                    requestAnimationFrame(function() {
                        previewBody.scrollTop = previewBody.scrollHeight;
                    });
                var modalBody = document.querySelector('#aiStreamModal .modal-body');
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
                preview.id = 'ai-stream-preview';
                preview.className = 'ai-stream-preview';
                preview.title = 'Click to view full process';
                preview.innerHTML =
                    '<div class="ai-stream-preview__header">' +
                        '<i class="bi bi-terminal-fill"></i>' +
                        '<span id="ai-stream-status-label" class="ai-stream-status">AI thinking' +
                            '<span id="ai-stream-dots" class="ai-thinking-dots">' +
                                '<span></span><span></span><span></span>' +
                            '</span>' +
                        '</span>' +
                        '<span id="ai-stream-timer" class="ai-stream-timer"></span>' +
                        '<button type="button" id="ai-cancel-btn" class="ai-cancel-btn d-none" title="Cancel inference">' +
                            '<i class="bi bi-stop-fill"></i> Stop' +
                        '</button>' +
                        '<i class="bi bi-arrows-fullscreen ai-stream-preview__expand"></i>' +
                    '</div>' +
                    '<div id="ai-stream-body" class="ai-stream-preview__body"></div>';
                preview.classList.add('ai-stream-preview--active');
                preview.addEventListener('click', function(e) {
                    if (e.target.closest('.ai-cancel-btn')) return;
                    self.openStreamModal();
                });
                preview.querySelector('.ai-cancel-btn').addEventListener('click', function() {
                    self.cancel();
                });
                responseEl.appendChild(preview);

                // Initialize persistent Quill viewer for preview
                this.streamPreviewQuill = this.createStreamQuill('ai-stream-body');

                // Start elapsed timer (updates both preview and modal)
                if (this.streamTimerInterval) clearInterval(this.streamTimerInterval);
                this.streamTimerStart = Date.now();
                var timerEl = document.getElementById('ai-stream-timer');
                var modalTimerEl = document.getElementById('ai-modal-timer');
                if (timerEl) timerEl.textContent = '0:00';
                if (modalTimerEl) modalTimerEl.textContent = '0:00';
                this.streamTimerInterval = setInterval(function() {
                    var elapsed = Math.floor((Date.now() - self.streamTimerStart) / 1000);
                    var mins = Math.floor(elapsed / 60);
                    var secs = elapsed % 60;
                    var formatted = mins + ':' + (secs < 10 ? '0' : '') + secs;
                    if (timerEl) timerEl.textContent = formatted;
                    if (modalTimerEl) modalTimerEl.textContent = formatted;
                }, 1000);

                // Reset modal content and initialize Quill viewers
                var modalThinking = document.getElementById('ai-modal-thinking');
                var modalThinkingBody = document.getElementById('ai-modal-thinking-body');
                var modalBody = document.getElementById('ai-modal-body');
                var modalDots = document.getElementById('ai-modal-dots');
                modalThinking.classList.add('d-none');
                modalThinkingBody.innerHTML = '';
                modalBody.innerHTML = '';
                modalDots.classList.remove('d-none');
                this.streamModalThinkingQuill = this.createStreamQuill('ai-modal-thinking-body');
                this.streamModalQuill = this.createStreamQuill('ai-modal-body');
            },

            openStreamModal: function() {
                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('aiStreamModal'));
                modal.show();
            },

            closeStreamModal: function() {
                var modalEl = document.getElementById('aiStreamModal');
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            },

            createProcessBlock: function(thinkingContent, processContent) {
                var details = document.createElement('details');
                details.className = 'ai-process-block';
                var summary = document.createElement('summary');
                summary.innerHTML = '<i class="bi bi-gear-fill"></i> View process';
                details.appendChild(summary);
                var body = document.createElement('div');
                body.className = 'ai-process-block__content';

                if (thinkingContent) {
                    var thinkingSection = document.createElement('div');
                    thinkingSection.className = 'ai-process-block__thinking';
                    thinkingSection.innerHTML = '<div class="ai-process-block__label">Thinking</div>' +
                        this.renderMarkdown(thinkingContent);
                    body.appendChild(thinkingSection);
                }

                if (processContent) {
                    var reasoningSection = document.createElement('div');
                    reasoningSection.className = 'ai-process-block__reasoning';
                    var reasoningHtml = '';
                    if (thinkingContent)
                        reasoningHtml += '<div class="ai-process-block__label">Intermediate output</div>';
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
                var vpBtn = msgDiv.querySelector('.ai-message__view-process');
                if (vpBtn) vpBtn.classList.remove('d-none');
            },

            updateStreamStatusLabel: function(status) {
                var text = 'AI ' + status;
                var previewLabel = document.getElementById('ai-stream-status-label');
                var modalLabel = document.getElementById('ai-modal-status-label');
                if (previewLabel) previewLabel.firstChild.textContent = text;
                if (modalLabel) modalLabel.firstChild.textContent = text;
            },

            removeStreamDots: function() {
                var dots = document.getElementById('ai-stream-dots');
                if (dots) dots.remove();
            },

            hideStreamContainer: function() {
                var el = document.getElementById('ai-stream-container');
                el.innerHTML = '';
                el.classList.add('d-none');
                this.streamPreviewQuill = null;
                this.streamModalQuill = null;
                this.streamModalThinkingQuill = null;
            },

            setStreamPreviewTall: function(tall) {
                var body = document.getElementById('ai-stream-body');
                if (!body) return;
                body.classList.toggle('ai-stream-preview__body--tall', tall);
            },

            hideEmptyState: function() {
                var empty = document.getElementById('ai-empty-state');
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

                var accordion = document.getElementById('ai-history-accordion');
                var idx = this.historyCounter++;
                var itemId = 'ai-history-item-' + idx;
                this.activeItemId = itemId;

                // Build header text from prompt (CSS text-overflow handles visual clipping)
                var headerText = (promptText || '').replace(/[#*_`>\[\]]/g, '').trim();
                if (headerText.length > 200) headerText = headerText.substring(0, 200) + '\u2026';

                var item = document.createElement('div');
                item.className = 'accordion-item';
                item.id = 'item-' + itemId;
                item.innerHTML =
                    '<h2 class="accordion-header" id="heading-' + itemId + '">' +
                        '<button class="accordion-button collapsed ai-history-item__header" type="button" ' +
                            'data-bs-toggle="collapse" data-bs-target="#collapse-' + itemId + '" ' +
                            'aria-expanded="false" aria-controls="collapse-' + itemId + '">' +
                            '<span class="ai-history-item__title">' + this.escapeHtml(headerText) + '</span>' +
                            '<span class="ai-history-item__meta"></span>' +
                        '</button>' +
                    '</h2>' +
                    '<div id="collapse-' + itemId + '" class="accordion-collapse collapse" ' +
                        'aria-labelledby="heading-' + itemId + '">' +
                        '<div class="accordion-body p-0">' +
                            '<div class="ai-active-prompt"></div>' +
                            '<div class="ai-active-response"></div>' +
                        '</div>' +
                    '</div>';

                // Append chronologically when replaying session history, otherwise prepend (newest first)
                if (this._appendNextAccordionItem) {
                    accordion.appendChild(item);
                    this._appendNextAccordionItem = false;
                } else {
                    accordion.insertBefore(item, accordion.firstChild);
                }
                document.getElementById('ai-history-wrapper').classList.remove('d-none');

                // Render user prompt inside the accordion body using Quill Delta
                var promptZone = item.querySelector('.ai-active-prompt');
                this.renderUserPromptInto(promptZone, promptText, promptDelta);

                // Render streaming placeholder outside the accordion
                var streamContainer = document.getElementById('ai-stream-container');
                streamContainer.classList.remove('d-none');
                this.renderStreamingPlaceholderInto(streamContainer);

                // If the prompt editor is already collapsed, use the tall preview
                if (!document.getElementById('aiPromptCard').classList.contains('show'))
                    this.setStreamPreviewTall(true);

                // Fire-and-forget: summarize prompt into a short title.
                // When currentRunId is already known (reconnect), include it so
                // the server can persist the title to prompt_summary right away.
                // For new runs the runId arrives via the prompt_markdown SSE event;
                // onStreamEvent will trigger the call at that point instead.
                if (this.currentRunId)
                    this.summarizePromptTitle(itemId, promptText);
                else
                    this._pendingSummarize = { itemId: itemId, promptText: promptText };
            },

            /**
             * Request a short AI-generated title for the accordion item.
             */
            summarizePromptTitle: function(itemId, promptText) {
                var self = this;
                fetch('$summarizePromptUrl', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': yii.getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ prompt: promptText, runId: self.currentRunId || null })
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
                        '#item-' + itemId + ' .ai-history-item__title'
                    );
                    if (titleEl) {
                        titleEl.textContent = title;
                        titleEl.classList.add('ai-history-item__title--summarized');
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
                    var summaryEl = messageDiv.querySelector('.ai-message__header-summary');
                    if (summaryEl) {
                        summaryEl.textContent = '\u2014 ' + data.summary;
                        summaryEl.classList.add('ai-message__header-summary--summarized');
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
                var container = document.getElementById('ai-active-response-container');
                if (container.classList.contains('d-none') || !container.hasChildNodes())
                    return;

                // Remove expanded state so max-height clamp + fade bar apply in accordion
                container.querySelectorAll('.ai-message__body--expanded').forEach(function(el) {
                    el.classList.remove('ai-message__body--expanded');
                });
                // Reset expand-bar label/icon for collapsed state
                container.querySelectorAll('.ai-message__expand-bar').forEach(function(bar) {
                    var label = bar.querySelector('span');
                    var icon = bar.querySelector('i');
                    if (label) label.textContent = 'Show full response';
                    if (icon) icon.className = 'bi bi-chevron-down';
                });

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
                    response: item.querySelector('.ai-active-response'),
                    prompt: item.querySelector('.ai-active-prompt'),
                    metaSpan: item.querySelector('.ai-history-item__meta')
                };
            },

            renderCurrentExchange: function(userContent, aiContent, processContent, meta) {
                // Render into the standalone container above the accordion.
                // On the next send(), this content is moved into the accordion item.
                var container = document.getElementById('ai-active-response-container');
                container.innerHTML = '';
                container.classList.remove('d-none');

                var msg = this.createAiMessageDiv(aiContent, meta);
                container.appendChild(msg.div);

                // Brief border glow to signal inference completed
                msg.div.classList.add('ai-message--flash');
                msg.div.addEventListener('animationend', function() {
                    msg.div.classList.remove('ai-message--flash');
                }, {once: true});

                // Collapsible process section (thinking + intermediate reasoning)
                this.attachProcessBlock(container, msg.div, this.streamThinkingBuffer, processContent);

                // Initialize Quill after element is in the DOM
                this.renderToQuillViewer(msg.body, aiContent);

                // Detect overflow while still clamped (max-height: 23em),
                // then expand. The bar stays so users can collapse back.
                requestAnimationFrame(function() {
                    if (msg.body.scrollHeight > msg.body.clientHeight + 2)
                        msg.div.classList.add('ai-message--overflowing');
                    msg.body.classList.add('ai-message__body--expanded');
                });

                // Show choice buttons or Go! button depending on response pattern
                var choiceOptions = this.parseChoiceOptions(aiContent);
                if (choiceOptions) {
                    this.renderChoiceButtons(msg.div, choiceOptions);
                } else {
                    var goBtn = msg.div.querySelector('.ai-message__go');
                    if (goBtn && this.needsApproval(aiContent))
                        goBtn.classList.remove('d-none');
                }

                // Fire-and-forget: summarize response into collapsed bar
                this.summarizeResponseTitle(msg.div, aiContent);

                // Update accordion header meta
                var zones = this.getActiveZones();
                if (zones && meta && zones.metaSpan) {
                    var metaSummary = this.formatMeta(meta);
                    zones.metaSpan.textContent = metaSummary;
                    if (meta.tool_uses && meta.tool_uses.length)
                        zones.metaSpan.title = meta.tool_uses.join('\\n');
                }
            },

            renderHistoricalExchange: function(run) {
                this.hideEmptyState();

                var accordion = document.getElementById('ai-history-accordion');
                var idx = this.historyCounter++;
                var itemId = 'ai-history-item-' + idx;

                var headerText = (run.promptSummary || run.promptMarkdown || '').replace(/[#*_`>\[\]]/g, '').trim();
                if (headerText.length > 200) headerText = headerText.substring(0, 200) + '\u2026';

                var meta = run.metadata || {};
                var contextUsed = (meta.input_tokens || 0) + (meta.cache_tokens || 0);
                var displayMeta = {
                    duration_ms: meta.duration_ms,
                    model: meta.model,
                    context_used: contextUsed,
                    output_tokens: meta.output_tokens,
                    num_turns: meta.num_turns,
                    tool_uses: []
                };
                var metaSummary = this.formatMeta(displayMeta);

                var statusIndicator = '';
                if (run.status === 'failed')
                    statusIndicator = '<span class="badge bg-danger ms-2">Failed</span>';
                else if (run.status === 'cancelled')
                    statusIndicator = '<span class="badge bg-secondary ms-2">Cancelled</span>';

                var item = document.createElement('div');
                item.className = 'accordion-item';
                item.id = 'item-' + itemId;
                item.innerHTML =
                    '<h2 class="accordion-header" id="heading-' + itemId + '">' +
                        '<button class="accordion-button collapsed ai-history-item__header" type="button" ' +
                            'data-bs-toggle="collapse" data-bs-target="#collapse-' + itemId + '" ' +
                            'aria-expanded="false" aria-controls="collapse-' + itemId + '">' +
                            '<span class="ai-history-item__title">' + this.escapeHtml(headerText) + '</span>' +
                            statusIndicator +
                            '<span class="ai-history-item__meta">' + this.escapeHtml(metaSummary) + '</span>' +
                        '</button>' +
                    '</h2>' +
                    '<div id="collapse-' + itemId + '" class="accordion-collapse collapse" ' +
                        'aria-labelledby="heading-' + itemId + '">' +
                        '<div class="accordion-body p-0">' +
                            '<div class="ai-active-prompt"></div>' +
                            '<div class="ai-active-response"></div>' +
                        '</div>' +
                    '</div>';

                accordion.appendChild(item);
                document.getElementById('ai-history-wrapper').classList.remove('d-none');

                var promptZone = item.querySelector('.ai-active-prompt');
                this.renderUserPromptInto(promptZone, run.promptMarkdown, null);

                var responseZone = item.querySelector('.ai-active-response');
                if (run.status === 'completed' && run.resultText) {
                    var msg = this.createAiMessageDiv(run.resultText, displayMeta);
                    responseZone.appendChild(msg.div);
                    this.renderToQuillViewer(msg.body, run.resultText);
                    this.checkExpandOverflow(msg.div);
                } else if (run.status === 'failed') {
                    var errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger m-3';
                    errorDiv.textContent = run.errorMessage || 'Inference failed.';
                    responseZone.appendChild(errorDiv);
                } else if (run.status === 'cancelled') {
                    var cancelDiv = document.createElement('div');
                    cancelDiv.className = 'alert alert-secondary m-3';
                    cancelDiv.textContent = 'Inference was cancelled.';
                    responseZone.appendChild(cancelDiv);
                }

                this.messages.push(
                    { role: 'user', content: run.promptMarkdown },
                    { role: 'ai', content: run.resultText || run.errorMessage || '(No output)', processContent: '' }
                );
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
                var bars = document.getElementById('ai-subscription-bars');
                var colorClass = pctUsed < 60 ? 'green' : pctUsed < 80 ? 'orange' : 'red';

                // Build tooltip with token details
                var fmt = function(n) { return n >= 1000 ? (n / 1000).toFixed(1) + 'k' : String(n || 0); };
                var tooltip = fmt(totalUsed) + ' / ' + fmt(this.maxContext) + ' tokens';
                if (this.lastNumTurns)
                    tooltip += ' \u00b7 ' + this.lastNumTurns + (this.lastNumTurns === 1 ? ' turn' : ' turns');

                // Find or create the context row
                var row = document.getElementById('ai-context-row');
                if (!row) {
                    row = document.createElement('div');
                    row.id = 'ai-context-row';
                    row.className = 'ai-subscription-row';

                    var label = document.createElement('span');
                    label.className = 'ai-subscription-row__label';
                    label.textContent = 'Context used';

                    var barOuter = document.createElement('div');
                    barOuter.className = 'ai-subscription-row__bar';

                    var barFill = document.createElement('div');
                    barFill.className = 'ai-subscription-row__fill';
                    barOuter.appendChild(barFill);

                    var pctLabel = document.createElement('span');
                    pctLabel.className = 'ai-subscription-row__pct';

                    row.appendChild(label);
                    row.appendChild(barOuter);
                    row.appendChild(pctLabel);
                    bars.appendChild(row);
                }

                // Update values
                var fill = row.querySelector('.ai-subscription-row__fill');
                var pctLabel = row.querySelector('.ai-subscription-row__pct');
                fill.style.width = pctUsed + '%';
                fill.className = 'ai-subscription-row__fill ai-subscription-row__fill--' + colorClass;
                pctLabel.textContent = pctUsed + '%';
                pctLabel.title = tooltip;

                this.updateUsageSummary();
                this.updateSummarizeButtonColor(pctUsed);
            },

            updateSummarizeButtonColor: function(pctUsed) {
                var btns = [
                    document.getElementById('ai-summarize-auto-btn'),
                    document.getElementById('ai-summarize-split-toggle')
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

            startUsageAutoRefresh: function() {
                var self = this;
                setInterval(function() { self.fetchSubscriptionUsage(); }, 300000);
            },

            setupUsageCompactResize: function() {
                var self = this;
                var timer;
                window.addEventListener('resize', function() {
                    clearTimeout(timer);
                    timer = setTimeout(function() { self.recheckUsageCompact(); }, 150);
                });
            },

            recheckUsageCompact: function() {
                var summary = document.getElementById('ai-combined-usage');
                if (!summary || summary.classList.contains('d-none')) return;

                // Reset to full mode (with bars)
                summary.classList.remove('ai-combined-bar__usage--compact');

                // Temporarily force nowrap to detect horizontal overflow
                summary.style.flexWrap = 'nowrap';
                summary.style.overflow = 'hidden';
                var overflows = summary.scrollWidth > summary.clientWidth;
                summary.style.flexWrap = '';
                summary.style.overflow = '';

                if (overflows)
                    summary.classList.add('ai-combined-bar__usage--compact');
            },

            fetchSubscriptionUsage: function() {
                if (!this.usageUrl) return;
                var self = this;
                var provider = this.getSelectedProviderId();
                var providerMeta = this.providerData[provider] || null;
                if (providerMeta && !providerMeta.supportsUsage) {
                    this.renderUsageUnavailable(providerMeta.name);
                    return;
                }
                this._usageRequestId = (this._usageRequestId || 0) + 1;
                var requestId = this._usageRequestId;
                var sep = this.usageUrl.indexOf('?') > -1 ? '&' : '?';
                var url = this.usageUrl + sep + 'provider=' + encodeURIComponent(provider);
                fetch(url)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (requestId !== self._usageRequestId) return;
                        if (self.getSelectedProviderId() !== provider) return;
                        if (data.success && data.data) {
                            self.renderSubscriptionUsage(data.data);
                            return;
                        }
                        if (providerMeta)
                            self.renderUsageUnavailable(providerMeta.name);
                    })
                    .catch(function(err) {
                        if (requestId !== self._usageRequestId) return;
                        if (self.getSelectedProviderId() !== provider) return;
                        if (providerMeta)
                            self.renderUsageUnavailable(providerMeta.name);
                        console.warn('Usage fetch failed:', err);
                    });
            },

            renderUsageUnavailable: function(providerName) {
                var combinedBar = document.getElementById('ai-combined-bar');
                var summary = document.getElementById('ai-combined-usage');
                var wrapper = document.getElementById('ai-subscription-usage');
                var bars = document.getElementById('ai-subscription-bars');

                if (combinedBar) {
                    combinedBar.classList.remove('ai-combined-bar--loading');
                    combinedBar.classList.remove('ai-combined-bar--warning');
                }
                if (summary)
                    summary.innerHTML = '<span class="text-muted small">Usage not available for ' + this.escapeHtml(providerName) + '</span>';
                if (bars)
                    bars.innerHTML = '';
                if (wrapper) {
                    wrapper.classList.add('d-none');
                    wrapper.classList.remove('ai-subscription-usage--warning');
                }

                this.usageState = 'collapsed';
                this.syncCombinedBar();
            },

            renderSubscriptionUsage: function(data) {
                var wrapper = document.getElementById('ai-subscription-usage');
                var bars = document.getElementById('ai-subscription-bars');
                if (!data.windows || !data.windows.length) return;

                // Preserve context row before clearing
                var contextRow = document.getElementById('ai-context-row');
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
                    row.className = 'ai-subscription-row';

                    var label = document.createElement('span');
                    label.className = 'ai-subscription-row__label';
                    label.textContent = w.label;
                    if (resetTooltip)
                        label.title = resetTooltip;

                    var barOuter = document.createElement('div');
                    barOuter.className = 'ai-subscription-row__bar';

                    var barFill = document.createElement('div');
                    barFill.className = 'ai-subscription-row__fill ai-subscription-row__fill--' + colorClass;
                    barFill.style.width = pct + '%';
                    barOuter.appendChild(barFill);

                    var pctLabel = document.createElement('span');
                    pctLabel.className = 'ai-subscription-row__pct';
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

                if (hasWarning) wrapper.classList.add('ai-subscription-usage--warning');
                else wrapper.classList.remove('ai-subscription-usage--warning');

                // Populate the combined bar usage summary
                this.updateUsageSummary();
            },

            escapeHtml: function(text) {
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(text));
                return div.innerHTML;
            },

            /**
             * Build a ai-message div with header, body, and optional meta/copy button.
             * Returns { div, body } so callers can append notices or extra elements.
             */
            createAiMessageDiv: function(markdownContent, meta) {
                var aiDiv = document.createElement('div');
                aiDiv.className = 'ai-message ai-message--response';

                var aiHeader = document.createElement('div');
                aiHeader.className = 'ai-message__header';

                var headerIcon = document.createElement('i');
                headerIcon.className = 'bi bi-terminal-fill';
                aiHeader.appendChild(headerIcon);
                aiHeader.appendChild(document.createTextNode(' AI'));

                var headerSummary = document.createElement('span');
                headerSummary.className = 'ai-message__header-summary';
                aiHeader.appendChild(headerSummary);

                var headerMeta = document.createElement('span');
                headerMeta.className = 'ai-message__header-meta';
                aiHeader.appendChild(headerMeta);

                var headerChevron = document.createElement('i');
                headerChevron.className = 'bi bi-chevron-up ai-message__header-chevron';
                aiHeader.appendChild(headerChevron);

                aiDiv.appendChild(aiHeader);

                var aiBody = document.createElement('div');
                aiBody.className = 'ai-message__body';
                aiBody.setAttribute('data-quill-markdown', markdownContent);
                aiDiv.appendChild(aiBody);

                var actions = document.createElement('div');
                actions.className = 'ai-message__actions';

                var goBtn = document.createElement('button');
                goBtn.type = 'button';
                goBtn.className = 'ai-message__go d-none';
                goBtn.title = 'Approve and execute (Alt+G)';
                goBtn.innerHTML = '<i class="bi bi-check-lg"></i> Go!';
                var self = this;
                goBtn.addEventListener('click', function() {
                    document.getElementById('ai-summary-reply-btn').classList.add('d-none');
                    self.sendFixedText('Proceed');
                });
                actions.appendChild(goBtn);

                // Full-width expand bar (replaces small icon button)
                var expandBar = document.createElement('button');
                expandBar.type = 'button';
                expandBar.className = 'ai-message__expand-bar';
                var expandBarLabel = document.createElement('span');
                expandBarLabel.textContent = 'Show full response';
                expandBar.appendChild(expandBarLabel);
                var expandBarIcon = document.createElement('i');
                expandBarIcon.className = 'bi bi-chevron-down';
                expandBar.appendChild(expandBarIcon);
                expandBar.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var isExpanded = aiBody.classList.toggle('ai-message__body--expanded');
                    expandBarLabel.textContent = isExpanded ? 'Collapse response' : 'Show full response';
                    expandBarIcon.className = isExpanded ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
                });
                aiDiv.appendChild(expandBar);

                var viewProcessBtn = document.createElement('button');
                viewProcessBtn.type = 'button';
                viewProcessBtn.className = 'ai-message__view-process d-none';
                viewProcessBtn.title = 'View process';
                viewProcessBtn.innerHTML = '<i class="bi bi-gear-fill"></i>';
                viewProcessBtn.addEventListener('click', function() {
                    var block = aiDiv._processBlock;
                    if (block) {
                        var hidden = block.classList.toggle('d-none');
                        viewProcessBtn.classList.toggle('active');
                        if (!hidden) block.open = true;
                    }
                });
                actions.appendChild(viewProcessBtn);

                var copyBtn = this.createCopyButton(markdownContent);
                actions.appendChild(copyBtn);

                aiDiv.appendChild(actions);

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

                return { div: aiDiv, body: aiBody };
            },

            /**
             * Render partial streamed content into the active response container.
             * Used by onStreamError (with an error alert) and cancel (with a cancelled notice).
             * Returns the .ai-message__body element so callers can append notices.
             */
            renderPartialResponse: function(markdownContent) {
                var container = document.getElementById('ai-active-response-container');
                container.innerHTML = '';
                container.classList.remove('d-none');

                var msg = this.createAiMessageDiv(markdownContent, null);
                container.appendChild(msg.div);
                this.renderToQuillViewer(msg.body, markdownContent);
                this.checkExpandOverflow(msg.div);

                this.attachProcessBlock(container, msg.div, this.streamThinkingBuffer, '');
                return msg.body;
            },

            addErrorMessage: function(errorText) {
                var container = document.getElementById('ai-active-response-container');
                container.innerHTML = '';
                container.classList.remove('d-none');

                var div = document.createElement('div');
                div.className = 'ai-message ai-message--error';

                var header = document.createElement('div');
                header.className = 'ai-message__header';
                header.innerHTML = '<i class="bi bi-terminal-fill"></i> AI';
                div.appendChild(header);

                var body = document.createElement('div');
                body.className = 'ai-message__body';
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
                    var body = msgDiv.querySelector('.ai-message__body');
                    if (!body) return;
                    if (body.scrollHeight > body.clientHeight + 2)
                        msgDiv.classList.add('ai-message--overflowing');
                    else
                        msgDiv.classList.remove('ai-message--overflowing');
                });
            },

            checkExpandOverflowAll: function(container) {
                var self = this;
                var msgs = container.querySelectorAll('.ai-message--response');
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
                document.getElementById('ai-quill-wrapper').classList.add('d-none');
                document.getElementById('ai-textarea-wrapper').classList.remove('d-none');
                document.getElementById('ai-editor-toggle').textContent = 'Switch to rich editor';
                this.inputMode = 'textarea';
                var textarea = document.getElementById('ai-followup-textarea');
                textarea.value = text;
                textarea.focus();
            },

            switchToQuill: function(delta) {
                document.getElementById('ai-textarea-wrapper').classList.add('d-none');
                document.getElementById('ai-quill-wrapper').classList.remove('d-none');
                document.getElementById('ai-editor-toggle').textContent = 'Switch to plain text';
                if (delta)
                    quill.setContents(delta);
                else
                    quill.setText(document.getElementById('ai-followup-textarea').value || '');
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
                this._replyExpand = false;
                if (this.renderTimer) {
                    clearTimeout(this.renderTimer);
                    this.renderTimer = null;
                }
                if (this._toggleBtnTimer) {
                    clearTimeout(this._toggleBtnTimer);
                    this._toggleBtnTimer = null;
                }
                var contextRow = document.getElementById('ai-context-row');
                if (contextRow) contextRow.remove();

                this.activeItemId = null;

                // Clear history, streaming container, and active response
                document.getElementById('ai-history-accordion').innerHTML = '';
                document.getElementById('ai-history-wrapper').classList.add('d-none');
                this.hideStreamContainer();
                var activeResponseContainer = document.getElementById('ai-active-response-container');
                activeResponseContainer.innerHTML = '';
                activeResponseContainer.classList.add('d-none');

                document.getElementById('ai-copy-all-wrapper').classList.add('d-none');
                document.getElementById('ai-reuse-btn').classList.add('d-none');
                document.getElementById('ai-summarize-group').classList.add('d-none');
                document.getElementById('ai-summary-reply-btn').classList.add('d-none');
                this.updateSummarizeButtonColor(0);
                this.summarizing = false;

                // Clean existing timers (prevent orphaned timers on rapid calls)
                if (this._pulseTimer) { clearTimeout(this._pulseTimer); this._pulseTimer = null; }
                if (this._editHintTimer) { clearTimeout(this._editHintTimer); this._editHintTimer = null; }

                // Activate edit-hint BEFORE unlockProvider() calls updateSettingsSummary()
                this._editHintActive = true;
                this.unlockProvider();
                // NOT: this.expandSettings() — FR-6: no auto-expand

                this.usageState = 'collapsed';
                document.getElementById('ai-subscription-usage').classList.add('d-none');
                this.syncCombinedBar();

                // Pulse animation on combined bar
                var bar = document.getElementById('ai-combined-bar');
                bar.classList.add('ai-combined-bar--pulse');
                var self = this;
                this._pulseTimer = setTimeout(function() {
                    bar.classList.remove('ai-combined-bar--pulse');
                    self._pulseTimer = null;
                }, 1200);

                // Edit icon fades out after 3s
                this._editHintTimer = setTimeout(function() {
                    self._editHintActive = false;
                    self._editHintTimer = null;
                    self.updateSettingsSummary();
                }, 3000);

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
                    document.getElementById('aiSettingsCardWrapper').classList.add('d-none');
                    this.updateSettingsSummary();
                    this.syncCombinedBar();
                }
            },

            collapsePromptEditor: function() {
                var card = document.getElementById('aiPromptCard');
                var bsCollapse = bootstrap.Collapse.getInstance(card);
                if (bsCollapse) bsCollapse.hide();
                else new bootstrap.Collapse(card, { toggle: false }).hide();
            },

            expandPromptEditor: function() {
                var card = document.getElementById('aiPromptCard');
                var bsCollapse = bootstrap.Collapse.getInstance(card);
                if (bsCollapse) bsCollapse.show();
                else new bootstrap.Collapse(card, { toggle: false }).show();
            },

            replyExpand: function() {
                var container = document.getElementById('ai-active-response-container');
                var goBtn = container ? container.querySelector('.ai-message__go') : null;
                if (goBtn) goBtn.classList.add('d-none');
                this._replyExpand = true;
                this.expandPromptEditor();
            },

            expandActiveResponse: function() {
                var container = document.getElementById('ai-active-response-container');
                if (!container) return;
                var body = container.querySelector('.ai-message__body');
                if (!body || body.classList.contains('ai-message__body--expanded')) return;
                body.classList.add('ai-message__body--expanded');
                var bar = container.querySelector('.ai-message__expand-bar');
                if (bar) {
                    var label = bar.querySelector('span');
                    var icon = bar.querySelector('i');
                    if (label) label.textContent = 'Collapse response';
                    if (icon) icon.className = 'bi bi-chevron-up';
                }
            },

            compactEditor: function() {
                document.getElementById('ai-quill-wrapper').classList.add('ai-editor-compact');
                document.getElementById('ai-textarea-wrapper').classList.add('ai-editor-compact');
            },

            expandEditor: function() {
                document.getElementById('ai-quill-wrapper').classList.remove('ai-editor-compact');
                document.getElementById('ai-textarea-wrapper').classList.remove('ai-editor-compact');
            },

            toggleFocusMode: function() {
                var page = document.querySelector('.ai-chat-page');
                var entering = !page.classList.contains('ai-focus-mode');
                page.classList.toggle('ai-focus-mode');
                if (entering) {
                    this.expandPromptEditor();
                    this.focusEditor();
                }
            },

            expandSettings: function() {
                if (this.settingsState !== 'expanded') {
                    this.settingsState = 'expanded';
                    document.getElementById('aiSettingsCardWrapper').classList.remove('d-none');
                    this.syncCombinedBar();
                }
            },

            updateSettingsSummary: function() {
                var summary = document.getElementById('ai-combined-settings');
                var modelEl = document.getElementById('ai-model');
                var permEl = document.getElementById('ai-permission-mode');
                var locked = this.providerLocked;
                var providerEl = document.getElementById('ai-provider');
                var providerId = providerEl ? providerEl.value : this.defaultProvider;
                var providerConfig = this.getProviderFieldConfig(providerId);

                summary.innerHTML = '';

                var self = this;
                var addBadge = function(icon, text, title, bg, extraClass) {
                    var span = document.createElement('span');
                    span.className = 'badge ' + (bg || 'bg-secondary') + (extraClass ? ' ' + extraClass : '');
                    var i = document.createElement('i');
                    i.className = 'bi ' + icon + ' me-1';
                    span.appendChild(i);
                    span.appendChild(document.createTextNode(text));
                    if (title) span.title = title;
                    summary.appendChild(span);
                };

                // Context group: project, git branch, config
                if (this.projectName)
                    addBadge('bi-folder2-open', this.projectName, 'Project');

                if (this.gitBranch)
                    addBadge('bi-signpost-split', this.gitBranch, 'Git branch');

                if (this.configBadgeLabel)
                    addBadge(this.configBadgeIcon, this.configBadgeLabel, this.configBadgeTitle, this.configBadgeBg);

                // Divider between context and settings groups
                var divider = document.createElement('span');
                divider.className = 'ai-combined-bar__settings-divider';
                summary.appendChild(divider);

                // Edit-hint icon
                if (this._editHintActive) {
                    var editBadge = document.createElement('span');
                    editBadge.className = 'badge badge-lock';
                    editBadge.setAttribute('aria-label', 'Click to edit settings');
                    var editIcon = document.createElement('i');
                    editIcon.className = 'bi bi-pencil';
                    editBadge.appendChild(editIcon);
                    summary.appendChild(editBadge);
                }

                // Settings group: provider, model, permission
                var providerBg = locked ? 'badge-setting badge-setting--locked' : 'badge-setting';

                if (providerEl) {
                    var providerName = providerEl.options[providerEl.selectedIndex]?.text || '';
                    if (providerName)
                        addBadge('bi-robot', providerName, 'Provider', providerBg);
                }

                var modelText = modelEl.options[modelEl.selectedIndex]?.text || '';
                if (modelText && modelText !== '(Use default)')
                    addBadge('bi-cpu', modelText, 'Model', 'badge-setting');
                else
                    addBadge('bi-cpu', 'Default model', 'Model', 'badge-setting');

                if (providerConfig.showPermissionMode) {
                    var permText = permEl.options[permEl.selectedIndex]?.text || '';
                    if (permText && permText !== '(Use default)')
                        addBadge('bi-shield-check', permText.split(' (')[0], 'Permission mode', 'badge-setting');
                    else
                        addBadge('bi-shield-check', 'Default permissions', 'Permission mode', 'badge-setting');
                } else {
                    var reasoningEl = document.getElementById('ai-custom-reasoning');
                    var reasoningText = reasoningEl ? reasoningEl.options[reasoningEl.selectedIndex]?.text || '' : '';
                    if (reasoningText && reasoningText !== '(Use default)')
                        addBadge('bi-lightning-charge', reasoningText.split(' —')[0], 'Reasoning level', 'badge-setting');
                    else
                        addBadge('bi-lightning-charge', 'Default reasoning', 'Reasoning level', 'badge-setting');
                }

                // Hide divider when settings card is expanded (settings badges not visible)
                if (this.settingsState === 'expanded')
                    divider.classList.add('d-none');
            },

            syncCombinedBar: function() {
                var bar = document.getElementById('ai-combined-bar');
                var settingsPart = document.getElementById('ai-combined-settings');
                var usagePart = document.getElementById('ai-combined-usage');
                var divider = bar.querySelector('.ai-combined-bar__divider');
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
                var summary = document.getElementById('ai-combined-usage');
                var combinedBar = document.getElementById('ai-combined-bar');
                var rows = document.querySelectorAll('#ai-subscription-bars .ai-subscription-row');

                // Remove loading state
                combinedBar.classList.remove('ai-combined-bar--loading');

                if (!rows.length) {
                    summary.innerHTML = '';
                    return;
                }

                var maxPct = 0;

                summary.innerHTML = '';
                var itemCount = 0;
                rows.forEach(function(row) {
                    var label = row.querySelector('.ai-subscription-row__label');
                    var fill = row.querySelector('.ai-subscription-row__fill');
                    if (!label || !fill) return;

                    if (itemCount > 0) {
                        var sep = document.createElement('span');
                        sep.className = 'ai-usage-summary__sep';
                        sep.textContent = '\u00B7';
                        summary.appendChild(sep);
                    }

                    var pct = parseInt(fill.style.width, 10) || 0;
                    if (pct > maxPct) maxPct = pct;
                    var colorClass = pct < 60 ? 'green' : pct < 80 ? 'orange' : 'red';

                    var item = document.createElement('span');
                    item.className = 'ai-usage-summary__item';
                    if (label.title)
                        item.title = label.title;

                    var labelSpan = document.createElement('span');
                    labelSpan.className = 'ai-usage-summary__label';
                    labelSpan.textContent = label.textContent;

                    var bar = document.createElement('span');
                    bar.className = 'ai-usage-summary__bar';
                    var barFill = document.createElement('span');
                    barFill.className = 'ai-usage-summary__bar-fill ai-usage-summary__bar-fill--' + colorClass;
                    barFill.style.width = pct + '%';
                    bar.appendChild(barFill);

                    var pctSpan = document.createElement('span');
                    pctSpan.className = 'ai-usage-summary__pct ai-usage-summary__pct--' + colorClass;
                    pctSpan.textContent = pct + '%';

                    item.appendChild(labelSpan);
                    item.appendChild(bar);
                    item.appendChild(pctSpan);
                    summary.appendChild(item);
                    itemCount++;
                });

                // Hide mini bars only when they would cause a line-wrap
                this.recheckUsageCompact();

                // Propagate warning state to combined bar
                if (maxPct >= 80)
                    combinedBar.classList.add('ai-combined-bar--warning');
                else
                    combinedBar.classList.remove('ai-combined-bar--warning');

                this._maxUsagePct = maxPct;
            },

            toggleUsageExpanded: function() {
                var wrapper = document.getElementById('ai-subscription-usage');

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
                var wrapper = document.getElementById('aiSettingsCardWrapper');

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
                    document.getElementById('ai-followup-textarea').focus();
            },

            scrollToTopUnlessFocused: function() {
                var editorHasFocus = quill.hasFocus()
                    || document.activeElement === document.getElementById('ai-followup-textarea');
                if (!editorHasFocus && window.scrollY > 0)
                    window.scrollTo({ top: 0, behavior: 'smooth' });
            },

            _animateSwap: function(el) {
                el.classList.remove('ai-swap-animate');
                void el.offsetWidth;
                el.classList.add('ai-swap-animate');
                el.addEventListener('animationend', function() {
                    el.classList.remove('ai-swap-animate');
                }, {once: true});
            },

            swapEditorAboveResponse: function() {
                var response = document.getElementById('ai-active-response-container');
                var promptCard = document.getElementById('aiPromptCard');
                if (!response || !promptCard || response.classList.contains('d-none')) return;
                var editor = promptCard.parentElement;
                var alreadyAbove = editor.compareDocumentPosition(response) & Node.DOCUMENT_POSITION_FOLLOWING;
                response.parentElement.insertBefore(editor, response);
                if (!alreadyAbove)
                    this._animateSwap(editor);
                document.getElementById('ai-summary-reply-btn').classList.add('d-none');
            },

            swapResponseAboveEditor: function() {
                var response = document.getElementById('ai-active-response-container');
                var promptCard = document.getElementById('aiPromptCard');
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
                btn.className = 'ai-message__copy';
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
                    var prefix = m.role === 'user' ? '## You' : '## AI';
                    return prefix + '\\n\\n' + m.content;
                }).join('\\n\\n---\\n\\n');

                var self = this;
                var btn = document.getElementById('ai-copy-all-btn');
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
                    var prefix = m.role === 'user' ? '## You' : '## AI';
                    return prefix + '\\n\\n' + m.content;
                }).join('\\n\\n---\\n\\n');

                // Disable buttons and show spinner on primary button
                var summarizeAutoBtn = document.getElementById('ai-summarize-auto-btn');
                var sendBtn = document.getElementById('ai-send-btn');
                summarizeAutoBtn.disabled = true;
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
                                document.getElementById('ai-followup-textarea').value = summary;
                            }
                            self.send();
                        } else {
                            var prefixed = 'Here is the summary of our previous session. Please read it carefully and continue from where we left off.\\n\\n' + summary;
                            if (self.inputMode === 'quill') {
                                quill.setText(prefixed);
                                quill.focus();
                                quill.setSelection(0, 0);
                            } else {
                                var textarea = document.getElementById('ai-followup-textarea');
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
                var summarizeAutoBtn = document.getElementById('ai-summarize-auto-btn');
                var sendBtn = document.getElementById('ai-send-btn');
                summarizeAutoBtn.disabled = false;
                sendBtn.disabled = false;
                summarizeAutoBtn.innerHTML = '<i class="bi bi-pencil-square"></i> Summarize';
            },

            openSaveDialogSelect: function() {
                var self = this;
                var list = document.getElementById('save-dialog-message-list');
                list.innerHTML = '';

                // Group messages into exchanges (pairs of user + ai)
                for (var i = 0; i < this.messages.length; i += 2) {
                    var userMsg = this.messages[i];
                    var aiMsg = this.messages[i + 1];
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

                    // AI message checkbox
                    if (aiMsg) {
                        var aiPreview = aiMsg.content.replace(/[#*_`>\[\]]/g, '').trim();
                        if (aiPreview.length > 120) aiPreview = aiPreview.substring(0, 120) + '\u2026';

                        var aiRow = document.createElement('div');
                        aiRow.className = 'form-check save-dialog-item__msg-row';

                        var aiInput = document.createElement('input');
                        aiInput.className = 'form-check-input save-dialog-msg-cb';
                        aiInput.type = 'checkbox';
                        aiInput.checked = true;
                        aiInput.id = 'save-dialog-msg-' + (i + 1);
                        aiInput.setAttribute('data-msg-index', String(i + 1));
                        aiInput.setAttribute('data-exchange-index', String(idx));

                        var aiLabel = document.createElement('label');
                        aiLabel.className = 'form-check-label save-dialog-item__label';
                        aiLabel.setAttribute('for', 'save-dialog-msg-' + (i + 1));
                        aiLabel.innerHTML =
                            '<span class="save-dialog-item__role save-dialog-item__role--ai"><i class="bi bi-terminal-fill"></i> AI</span>' +
                            '<span class="save-dialog-item__preview">' + this.escapeHtml(aiPreview) + '</span>';

                        aiRow.appendChild(aiInput);
                        aiRow.appendChild(aiLabel);
                        messagesDiv.appendChild(aiRow);
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

                // Separate user and response messages from selected checkboxes
                var checkboxes = document.querySelectorAll('#save-dialog-message-list .save-dialog-msg-cb:checked');
                var userParts = [];
                var responseParts = [];
                checkboxes.forEach(function(cb) {
                    var idx = parseInt(cb.getAttribute('data-msg-index'), 10);
                    var msg = self.messages[idx];
                    if (!msg) return;
                    var isUser = (idx % 2 === 0);
                    var role = isUser ? 'You' : 'AI';
                    var part = '## ' + role + '\\n\\n' + msg.content;
                    if (isUser)
                        userParts.push(part);
                    else
                        responseParts.push(part);
                });

                var userMarkdown = userParts.join('\\n\\n---\\n\\n');
                var userDelta = self.markdownToDelta(userMarkdown);

                // Disable save button
                var saveBtn = document.getElementById('save-dialog-save-btn');
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving\u2026';

                var saveHeaders = {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': yii.getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                };

                // Step 1: Save parent note with user content
                fetch('$saveUrl', {
                    method: 'POST',
                    headers: saveHeaders,
                    body: JSON.stringify({
                        name: name,
                        content: userDelta,
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

                    var parentId = saveData.id;

                    // Step 2: If response content exists, save child note with type='summation'
                    if (responseParts.length > 0) {
                        var responseMarkdown = responseParts.join('\\n\\n---\\n\\n');
                        var responseDelta = self.markdownToDelta(responseMarkdown);

                        return fetch('$saveUrl', {
                            method: 'POST',
                            headers: saveHeaders,
                            body: JSON.stringify({
                                name: name,
                                content: responseDelta,
                                project_id: projectId,
                                type: 'summation',
                                parent_id: parentId
                            })
                        })
                        .then(function(r) { return self.parseJsonResponse(r); })
                        .then(function(childData) {
                            if (!childData.success) {
                                console.warn('Child note save failed:', childData.message || childData.errors);
                            }
                            var viewUrl = '$viewUrlTemplate'.replace('__ID__', parentId);
                            window.location.href = viewUrl;
                        });
                    }

                    // No response content — redirect to parent note
                    var viewUrl = '$viewUrlTemplate'.replace('__ID__', parentId);
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
                var accordion = document.getElementById('ai-history-accordion');
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
                    var accordion = document.getElementById('ai-history-accordion');
                    var panels = accordion.querySelectorAll('.accordion-collapse');
                    var btn = document.getElementById('ai-toggle-history-btn');
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

        window.AiChat.init();
        quill.focus();
        quill.setSelection(quill.getLength(), 0);
    })();
    JS;
$this->registerJs($js);
?>
