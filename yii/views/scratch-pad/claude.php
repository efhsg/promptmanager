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
                    <small><i class="bi bi-info-circle me-1"></i><span id="claude-config-status-text">Checking config...</span></small>
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

    <!-- Section 3: Conversation Panel -->
    <div class="card">
        <div class="card-body p-0">
            <div id="claude-conversation" class="claude-conversation">
                <div id="claude-conversation-empty" class="claude-conversation__empty">
                    <i class="bi bi-terminal"></i>
                    <span class="text-muted">Send a prompt to start a conversation</span>
                </div>
            </div>
            <div id="claude-copy-all-wrapper" class="d-none p-2 border-top text-end">
                <button type="button" id="claude-copy-all-btn" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-clipboard"></i> Copy All
                </button>
            </div>
        </div>
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
            isUserScrolledUp: false,
            projectDefaults: $projectDefaultsJson,
            checkConfigUrl: $checkConfigUrlJson,

            init: function() {
                this.prefillFromDefaults();
                this.checkConfigStatus();
                this.setupEventListeners();
                this.setupScrollTracking();
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
                    statusEl.classList.remove('alert-secondary', 'alert-success', 'alert-info', 'alert-warning');
                    if (data.hasAnyConfig) {
                        statusEl.classList.add('alert-success');
                        var parts = [];
                        if (data.hasCLAUDE_MD) parts.push('CLAUDE.md');
                        if (data.hasClaudeDir) parts.push('.claude/');
                        statusTextEl.innerHTML = '<i class="bi bi-check-circle me-1"></i>Using project\\'s own config: ' + parts.join(' + ');
                    } else if (data.hasPromptManagerContext) {
                        statusEl.classList.add('alert-info');
                        statusTextEl.innerHTML = '<i class="bi bi-cloud me-1"></i>Using PromptManager context (injected at runtime)';
                    } else {
                        statusEl.classList.add('alert-warning');
                        statusTextEl.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>No config - Claude will use defaults. Consider adding context in Project settings.';
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

                var settingsCard = document.getElementById('claudeSettingsCard');
                settingsCard.addEventListener('hidden.bs.collapse', function() { self.updateSettingsSummary(); });
                settingsCard.addEventListener('shown.bs.collapse', function() {
                    document.getElementById('claude-settings-summary').classList.add('d-none');
                });
            },

            setupScrollTracking: function() {
                var self = this;
                var panel = document.getElementById('claude-conversation');
                panel.addEventListener('scroll', function() {
                    var threshold = 50;
                    self.isUserScrolledUp = (panel.scrollTop + panel.clientHeight) < (panel.scrollHeight - threshold);
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

                if (this.inputMode === 'quill') {
                    var delta = quill.getContents();
                    this.lastSentDelta = delta;
                    options.contentDelta = JSON.stringify(delta);
                    quill.setText('');
                } else {
                    var textarea = document.getElementById('claude-followup-textarea');
                    var text = textarea.value.trim();
                    if (!text) return;
                    options.prompt = text;
                    textarea.value = '';
                }

                sendBtn.disabled = true;
                this.hideEmptyState();
                var placeholder = this.showLoadingPlaceholder();

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
                    self.removeLoadingPlaceholder(placeholder);

                    if (data.success) {
                        self.addMessage('user', data.promptMarkdown || options.prompt || '(prompt)');
                        self.addMessage('claude', data.output || '(No output)', {
                            duration_ms: data.duration_ms,
                            model: data.model,
                            input_tokens: data.input_tokens,
                            output_tokens: data.output_tokens,
                            configSource: data.configSource
                        });

                        if (data.sessionId)
                            self.sessionId = data.sessionId;

                        self.messages.push(
                            { role: 'user', content: data.promptMarkdown || options.prompt || '' },
                            { role: 'claude', content: data.output || '' }
                        );

                        // First successful run: collapse settings, show follow-up buttons
                        if (self.messages.length === 2) {
                            self.collapseSettings();
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
                    self.removeLoadingPlaceholder(placeholder);
                    self.addErrorMessage('Failed to execute Claude CLI: ' + error.message);
                    self.focusEditor();
                });
            },

            addMessage: function(role, content, meta) {
                var panel = document.getElementById('claude-conversation');
                var div = document.createElement('div');
                div.className = 'claude-message claude-message--' + role;

                var header = document.createElement('div');
                header.className = 'claude-message__header';
                if (role === 'user') {
                    header.innerHTML = '<i class="bi bi-person-fill"></i> You';
                } else {
                    header.innerHTML = '<i class="bi bi-terminal-fill"></i> Claude';
                }
                div.appendChild(header);

                var body = document.createElement('div');
                body.className = 'claude-message__body';
                body.innerHTML = this.renderMarkdown(content);
                div.appendChild(body);

                if (meta) {
                    var metaDiv = document.createElement('div');
                    metaDiv.className = 'claude-message__meta';
                    var parts = [];
                    if (meta.duration_ms) {
                        parts.push((meta.duration_ms / 1000).toFixed(1) + 's');
                    }
                    if (meta.input_tokens != null || meta.output_tokens != null) {
                        var fmt = function(n) { return n >= 1000 ? (n / 1000).toFixed(1) + 'k' : String(n || 0); };
                        parts.push(fmt(meta.input_tokens) + '/' + fmt(meta.output_tokens));
                    }
                    if (meta.model) parts.push(meta.model);
                    if (meta.configSource) {
                        var src = meta.configSource;
                        if (src.startsWith('project_own:'))
                            src = 'project config';
                        else if (src === 'managed_workspace')
                            src = 'managed workspace';
                        else if (src === 'default_workspace')
                            src = 'default workspace';
                        parts.push(src);
                    }
                    metaDiv.textContent = parts.join(' \u00b7 ');
                    div.appendChild(metaDiv);
                }

                panel.appendChild(div);
                this.scrollToBottom();
            },

            addErrorMessage: function(errorText) {
                var panel = document.getElementById('claude-conversation');
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

                panel.appendChild(div);
                this.scrollToBottom();
            },

            showLoadingPlaceholder: function() {
                var panel = document.getElementById('claude-conversation');
                var div = document.createElement('div');
                div.className = 'claude-message claude-message--loading';
                div.id = 'claude-loading-placeholder';

                var header = document.createElement('div');
                header.className = 'claude-message__header';
                header.innerHTML = '<i class="bi bi-terminal-fill"></i> Claude';
                div.appendChild(header);

                var body = document.createElement('div');
                body.className = 'claude-message__body';
                body.innerHTML = '<div class="claude-thinking-dots"><span></span><span></span><span></span></div>' +
                    '<div class="text-muted small">Running Claude CLI...</div>';
                div.appendChild(body);

                panel.appendChild(div);
                this.scrollToBottom();
                return div;
            },

            removeLoadingPlaceholder: function(el) {
                if (el && el.parentNode)
                    el.parentNode.removeChild(el);
            },

            renderMarkdown: function(text) {
                if (!text) return '';
                var html = marked.parse(String(text));
                return DOMPurify.sanitize(html, { ADD_ATTR: ['class', 'target', 'rel'] });
            },

            hideEmptyState: function() {
                var empty = document.getElementById('claude-conversation-empty');
                if (empty) empty.classList.add('d-none');
            },

            scrollToBottom: function() {
                if (this.isUserScrolledUp) return;
                var panel = document.getElementById('claude-conversation');
                panel.scrollTop = panel.scrollHeight;
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

                // Clear conversation panel
                var panel = document.getElementById('claude-conversation');
                panel.innerHTML = '';
                var empty = document.createElement('div');
                empty.id = 'claude-conversation-empty';
                empty.className = 'claude-conversation__empty';
                empty.innerHTML = '<i class="bi bi-terminal"></i><span class="text-muted">Send a prompt to start a conversation</span>';
                panel.appendChild(empty);

                document.getElementById('claude-copy-all-wrapper').classList.add('d-none');
                document.getElementById('claude-reuse-btn').classList.add('d-none');
                document.getElementById('claude-new-session-btn').classList.add('d-none');

                this.expandSettings();
                this.switchToQuill(initialDelta);
            },

            collapseSettings: function() {
                var card = document.getElementById('claudeSettingsCard');
                var bsCollapse = bootstrap.Collapse.getInstance(card);
                if (bsCollapse) bsCollapse.hide();
                else new bootstrap.Collapse(card, { toggle: true });
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
                    if (statusEl.classList.contains('alert-success')) parts.push('Project config');
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
            }
        };

        window.ClaudeChat.init();
        quill.focus();
        quill.setSelection(quill.getLength(), 0);
    })();
    JS;
$this->registerJs($js);
?>
