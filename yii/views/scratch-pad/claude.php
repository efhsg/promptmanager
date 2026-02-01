<?php

use app\assets\HighlightAsset;
use app\assets\QuillAsset;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

/** @var View $this */
/** @var app\models\ScratchPad $model */

QuillAsset::register($this);
HighlightAsset::register($this);
$this->registerJsFile('@web/js/marked.min.js', ['position' => View::POS_HEAD]);
$this->registerJsFile('@web/js/purify.min.js', ['position' => View::POS_HEAD]);
$this->registerCssFile('@web/css/claude-chat.css');

$runClaudeUrl = Url::to(['/scratch-pad/run-claude', 'id' => $model->id]);
$importTextUrl = Url::to(['/scratch-pad/import-text']);
$importMarkdownUrl = Url::to(['/scratch-pad/import-markdown']);
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
        <div id="claude-settings-summary" class="claude-settings-summary d-none"
             data-bs-toggle="collapse" data-bs-target="#claudeSettingsCard" role="button">
        </div>
    </div>

    <!-- Section 2: Prompt Editor -->
    <div class="card mb-4">
        <div class="card-body claude-prompt-section">
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
                    <button type="button" id="claude-new-session-btn" class="btn btn-outline-secondary d-none">
                        <i class="bi bi-arrow-repeat"></i> New Session
                    </button>
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
        <button type="button" class="btn-close" id="claude-context-warning-close" aria-label="Close"></button>
    </div>

    <!-- Section 3a: Current Exchange -->
    <div id="claude-current-exchange" class="mb-4">
        <div>
            <div id="claude-current-empty" class="claude-conversation__empty">
                <i class="bi bi-terminal"></i>
                <span class="text-muted">Send a prompt to start a conversation</span>
            </div>
            <div id="claude-current-response" class="d-none"></div>
            <div id="claude-current-prompt" class="d-none"></div>
        </div>
    </div>

    <!-- Section 3b: History Accordion -->
    <div id="claude-history-wrapper" class="d-none mb-4">
        <div class="d-flex justify-content-end mb-2">
            <button type="button" id="claude-toggle-history-btn" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrows-expand"></i> Expand All
            </button>
        </div>
        <div class="accordion" id="claude-history-accordion"></div>
    </div>

    <!-- Copy All (below both) -->
    <div id="claude-copy-all-wrapper" class="d-none text-end mb-4">
        <button type="button" id="claude-copy-all-btn" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-clipboard"></i> Copy All
        </button>
    </div>
</div>

<?php
$contentJson = Json::encode($model->content ?? '{"ops":[]}', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$csrfToken = Yii::$app->request->csrfToken;
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
                .then(function(r) { return r.json(); })
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
                document.getElementById('claude-new-session-btn').addEventListener('click', function() { self.newSession(); });
                document.getElementById('claude-copy-all-btn').addEventListener('click', function() { self.copyConversation(); });
                document.getElementById('claude-toggle-history-btn').addEventListener('click', function() { self.toggleHistory(); });
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
                    var btn = e.target.closest('.claude-message__copy');
                    if (btn) self.handleCopyClick(btn);
                });

                var settingsCard = document.getElementById('claudeSettingsCard');
                settingsCard.addEventListener('hidden.bs.collapse', function() { self.updateSettingsSummary(); });
                settingsCard.addEventListener('shown.bs.collapse', function() {
                    document.getElementById('claude-settings-summary').classList.add('d-none');
                });

                document.getElementById('claude-context-warning-close').addEventListener('click', function() {
                    document.getElementById('claude-context-warning').classList.add('d-none');
                    self.warningDismissed = true;
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
                if (this.inputMode === 'quill') {
                    var delta = quill.getContents();
                    this.lastSentDelta = delta;
                    pendingPrompt = quill.getText().replace(/\\n$/, '');
                    if (!pendingPrompt.trim()) return;
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

                // Archive current exchange before new content overwrites the DOM
                if (this.messages.length >= 2)
                    this.moveCurrentToHistory();

                // Show user prompt immediately (before fetch)
                this.showUserPrompt(pendingPrompt);
                this.showLoadingPlaceholder();

                fetch('$runClaudeUrl', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '$csrfToken',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(options)
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    sendBtn.disabled = false;
                    self.removeLoadingPlaceholder();

                    if (data.success) {
                        var userContent = data.promptMarkdown || options.prompt || '(prompt)';
                        var claudeContent = data.output || '(No output)';

                        // Context = input + cache tokens from this invocation.
                        // Output tokens have a separate limit and don't consume the context window.
                        if (data.context_window)
                            self.maxContext = data.context_window;
                        var contextUsed = (data.input_tokens || 0) + (data.cache_tokens || 0);

                        var meta = {
                            duration_ms: data.duration_ms,
                            model: data.model,
                            context_used: contextUsed,
                            output_tokens: data.output_tokens,
                            num_turns: data.num_turns,
                            tool_uses: data.tool_uses || [],
                            configSource: data.configSource
                        };

                        self.renderCurrentExchange(userContent, claudeContent, meta);

                        if (data.sessionId)
                            self.sessionId = data.sessionId;

                        self.messages.push(
                            { role: 'user', content: userContent },
                            { role: 'claude', content: claudeContent }
                        );

                        var pctUsed = Math.min(100, Math.round(contextUsed / self.maxContext * 100));
                        self.lastNumTurns = data.num_turns || null;
                        self.lastToolUses = data.tool_uses || [];
                        self.updateContextMeter(pctUsed, contextUsed);
                        if (pctUsed >= 80 && !self.warningDismissed)
                            self.showContextWarning(pctUsed);

                        // First successful run: show follow-up buttons
                        if (self.messages.length === 2) {
                            document.getElementById('claude-reuse-btn').classList.remove('d-none');
                            document.getElementById('claude-new-session-btn').classList.remove('d-none');
                        }

                        document.getElementById('claude-copy-all-wrapper').classList.remove('d-none');
                    } else {
                        self.addErrorMessage(data.error || data.output || 'An unknown error occurred');
                    }
                    self.focusEditor();
                })
                .catch(function(error) {
                    sendBtn.disabled = false;
                    self.removeLoadingPlaceholder();
                    self.addErrorMessage('Failed to execute Claude CLI: ' + error.message);
                    self.focusEditor();
                });
            },

            renderCurrentExchange: function(userContent, claudeContent, meta) {
                this.hideEmptyState();
                var responseEl = document.getElementById('claude-current-response');

                // Render Claude response (top)
                responseEl.innerHTML = '';
                var claudeDiv = document.createElement('div');
                claudeDiv.className = 'claude-message claude-message--claude';

                var claudeHeader = document.createElement('div');
                claudeHeader.className = 'claude-message__header';
                claudeHeader.innerHTML = '<i class="bi bi-terminal-fill"></i> Claude';
                claudeDiv.appendChild(claudeHeader);

                var claudeBody = document.createElement('div');
                claudeBody.className = 'claude-message__body';
                claudeBody.innerHTML = this.renderMarkdown(claudeContent);
                claudeDiv.appendChild(claudeBody);

                if (meta) {
                    var metaDiv = document.createElement('div');
                    metaDiv.className = 'claude-message__meta';
                    metaDiv.textContent = this.formatMeta(meta);
                    if (meta.tool_uses && meta.tool_uses.length)
                        metaDiv.title = meta.tool_uses.join('\\n');
                    claudeDiv.appendChild(metaDiv);
                }

                var copyBtn = this.createCopyButton(claudeContent);
                claudeDiv.appendChild(copyBtn);

                responseEl.appendChild(claudeDiv);
                responseEl.classList.remove('d-none');

                // Update user prompt with server-converted markdown
                this.showUserPrompt(userContent);

                // Store meta for when this exchange moves to history
                this.currentMeta = meta;
                this.currentPromptText = userContent;
            },

            moveCurrentToHistory: function() {
                if (!this.currentPromptText) return;

                var responseEl = document.getElementById('claude-current-response');
                var promptEl = document.getElementById('claude-current-prompt');

                var accordion = document.getElementById('claude-history-accordion');
                var idx = this.historyCounter++;
                var itemId = 'claude-history-item-' + idx;

                // Build header text: truncated prompt + meta
                var headerText = (this.currentPromptText || '').replace(/[#*_`>\\[\\]]/g, '').trim();
                if (headerText.length > 80) headerText = headerText.substring(0, 80) + '\u2026';
                var metaSummary = this.currentMeta ? this.formatMeta(this.currentMeta) : '';

                var item = document.createElement('div');
                item.className = 'accordion-item';
                item.innerHTML =
                    '<h2 class="accordion-header" id="heading-' + itemId + '">' +
                        '<button class="accordion-button collapsed claude-history-item__header" type="button" ' +
                            'data-bs-toggle="collapse" data-bs-target="#collapse-' + itemId + '" ' +
                            'aria-expanded="false" aria-controls="collapse-' + itemId + '">' +
                            '<span class="claude-history-item__title">' + this.escapeHtml(headerText) + '</span>' +
                            (metaSummary ? '<span class="claude-history-item__meta">' + this.escapeHtml(metaSummary) + '</span>' : '') +
                        '</button>' +
                    '</h2>' +
                    '<div id="collapse-' + itemId + '" class="accordion-collapse collapse" ' +
                        'aria-labelledby="heading-' + itemId + '">' +
                        '<div class="accordion-body p-0"></div>' +
                    '</div>';

                // Set tool_uses title via DOM to avoid attribute injection
                var metaSpan = item.querySelector('.claude-history-item__meta');
                if (metaSpan && this.currentMeta && this.currentMeta.tool_uses && this.currentMeta.tool_uses.length) {
                    metaSpan.title = this.currentMeta.tool_uses.join('\\n');
                }

                // Move current content into accordion body
                var body = item.querySelector('.accordion-body');
                body.innerHTML = responseEl.innerHTML + promptEl.innerHTML;

                // Prepend (newest first)
                accordion.insertBefore(item, accordion.firstChild);
                document.getElementById('claude-history-wrapper').classList.remove('d-none');

                // Clear current zones
                responseEl.innerHTML = '';
                responseEl.classList.add('d-none');
                promptEl.innerHTML = '';
                promptEl.classList.add('d-none');
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

            addErrorMessage: function(errorText) {
                this.hideEmptyState();
                var responseEl = document.getElementById('claude-current-response');
                responseEl.innerHTML = '';

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

                responseEl.appendChild(div);
                responseEl.classList.remove('d-none');
            },

            showLoadingPlaceholder: function() {
                this.hideEmptyState();
                var responseEl = document.getElementById('claude-current-response');
                responseEl.innerHTML = '';

                var div = document.createElement('div');
                div.className = 'claude-message claude-message--loading';

                var header = document.createElement('div');
                header.className = 'claude-message__header';
                header.innerHTML = '<i class="bi bi-terminal-fill"></i> Claude';
                div.appendChild(header);

                var body = document.createElement('div');
                body.className = 'claude-message__body';
                body.innerHTML = '<div class="claude-thinking-dots"><span></span><span></span><span></span></div>' +
                    '<div class="text-muted small">Running Claude CLI...</div>';
                div.appendChild(body);

                responseEl.appendChild(div);
                responseEl.classList.remove('d-none');
            },

            removeLoadingPlaceholder: function() {
                var responseEl = document.getElementById('claude-current-response');
                responseEl.innerHTML = '';
                responseEl.classList.add('d-none');
            },

            showUserPrompt: function(plainText) {
                this.hideEmptyState();
                var promptEl = document.getElementById('claude-current-prompt');
                promptEl.innerHTML = '';

                var userDiv = document.createElement('div');
                userDiv.className = 'claude-message claude-message--user';

                var userHeader = document.createElement('div');
                userHeader.className = 'claude-message__header';
                userHeader.innerHTML = '<i class="bi bi-person-fill"></i> You';
                userDiv.appendChild(userHeader);

                var userBody = document.createElement('div');
                userBody.className = 'claude-message__body';
                if (plainText)
                    userBody.innerHTML = this.renderMarkdown(plainText);
                else
                    userBody.innerHTML = '<span class="text-muted fst-italic">Sending prompt\u2026</span>';
                userDiv.appendChild(userBody);

                if (plainText) {
                    var copyBtn = this.createCopyButton(plainText);
                    userDiv.appendChild(copyBtn);
                }

                promptEl.appendChild(userDiv);
                promptEl.classList.remove('d-none');
            },

            renderMarkdown: function(text) {
                if (!text) return '';
                var html = marked.parse(String(text));
                return DOMPurify.sanitize(html, { ADD_ATTR: ['class', 'target', 'rel'] });
            },

            hideEmptyState: function() {
                var empty = document.getElementById('claude-current-empty');
                if (empty) empty.classList.add('d-none');
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
                this.currentMeta = null;
                this.currentPromptText = null;
                this.maxContext = 200000;
                this.warningDismissed = false;
                document.getElementById('claude-context-meter-wrapper').classList.add('d-none');
                document.getElementById('claude-context-meter-fill').style.width = '0%';
                document.getElementById('claude-context-warning').classList.add('d-none');

                // Clear current exchange
                var responseEl = document.getElementById('claude-current-response');
                var promptEl = document.getElementById('claude-current-prompt');
                responseEl.innerHTML = '';
                responseEl.classList.add('d-none');
                promptEl.innerHTML = '';
                promptEl.classList.add('d-none');

                // Show empty state
                document.getElementById('claude-current-empty').classList.remove('d-none');

                // Clear history
                document.getElementById('claude-history-accordion').innerHTML = '';
                document.getElementById('claude-history-wrapper').classList.add('d-none');

                document.getElementById('claude-copy-all-wrapper').classList.add('d-none');
                document.getElementById('claude-reuse-btn').classList.add('d-none');
                document.getElementById('claude-new-session-btn').classList.add('d-none');

                this.expandSettings();
                this.expandEditor();
                this.switchToQuill(initialDelta);
            },

            collapseSettings: function() {
                var card = document.getElementById('claudeSettingsCard');
                var bsCollapse = bootstrap.Collapse.getInstance(card);
                if (bsCollapse) bsCollapse.hide();
                else new bootstrap.Collapse(card, { toggle: true });
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

            toggleHistory: function() {
                var accordion = document.getElementById('claude-history-accordion');
                var panels = accordion.querySelectorAll('.accordion-collapse');
                var btn = document.getElementById('claude-toggle-history-btn');
                var allExpanded = Array.prototype.every.call(panels, function(p) {
                    return p.classList.contains('show');
                });

                panels.forEach(function(panel) {
                    var instance = bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false });
                    if (allExpanded)
                        instance.hide();
                    else
                        instance.show();
                });

                if (allExpanded) {
                    btn.innerHTML = '<i class="bi bi-arrows-expand"></i> Expand All';
                } else {
                    btn.innerHTML = '<i class="bi bi-arrows-collapse"></i> Collapse All';
                }
            }
        };

        window.ClaudeChat.init();
        quill.focus();
        quill.setSelection(quill.getLength(), 0);
    })();
    JS;
$this->registerJs($js);
?>
