<?php

use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use app\models\ScratchPad;
use yii\web\View;

/** @var View $this */
/** @var ScratchPad $model */

$runClaudeUrl = Url::to(['/scratch-pad/run-claude', 'id' => $model->id]);
$checkConfigUrl = $model->project ? Url::to(['/project/check-claude-config', 'id' => $model->project->id]) : null;
$projectDefaults = $model->project ? $model->project->getClaudeOptions() : [];
$projectDefaultsJson = Json::htmlEncode($projectDefaults);
$checkConfigUrlJson = Json::htmlEncode($checkConfigUrl);

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
?>

<div class="modal fade" id="claudeCliModal" tabindex="-1" aria-labelledby="claudeCliModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="claudeCliModalLabel">
                    <i class="bi bi-terminal-fill me-2"></i>Claude CLI
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Config Status Indicator -->
                <div id="claude-config-status" class="alert alert-secondary mb-3 d-none">
                    <small><i class="bi bi-info-circle me-1"></i><span id="claude-config-status-text">Checking config...</span></small>
                </div>

                <!-- Step 1: Configuration Form -->
                <div id="claude-cli-config-step">
                    <p class="text-muted mb-3">Configure options for this execution. Values from project defaults are pre-filled.</p>

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

                <!-- Step 2: Loading/Output -->
                <div id="claude-cli-output-step" class="d-none">
                    <div id="claude-cli-loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Running Claude CLI...</p>
                        <p class="small text-muted">This may take a few minutes</p>
                    </div>
                    <div id="claude-cli-error" class="alert alert-danger d-none"></div>
                    <div id="claude-cli-output-container" class="d-none">
                        <pre id="claude-cli-output" class="p-3 rounded" style="background-color: #1e1e1e; color: #d4d4d4; max-height: 60vh; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word; font-size: 0.875rem;"></pre>
                        <small id="claude-cli-meta" class="text-muted d-none mt-2 d-block"></small>
                    </div>
                    <!-- Continue Conversation -->
                    <div id="claude-continue-section" class="d-none mt-3">
                        <div class="input-group">
                            <input type="text" id="claude-continue-input" class="form-control"
                                   placeholder="Ask a follow-up question...">
                            <button type="button" id="claude-continue-btn" class="btn btn-primary">
                                <i class="bi bi-send-fill"></i> Send
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="claude-cli-back-btn" class="btn btn-outline-secondary d-none">
                    <i class="bi bi-arrow-left"></i> Back
                </button>
                <button type="button" id="claude-cli-new-btn" class="btn btn-outline-secondary d-none">
                    <i class="bi bi-plus-circle"></i> New Conversation
                </button>
                <button type="button" id="claude-cli-copy-btn" class="btn btn-outline-secondary d-none">
                    <i class="bi bi-clipboard"></i> Copy Output
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="claude-cli-run-btn" class="btn btn-primary">
                    <i class="bi bi-play-fill"></i> Run
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$csrfToken = Yii::$app->request->csrfToken;
$js = <<<JS
    window.ClaudeCliModal = {
        modal: null,
        projectDefaults: $projectDefaultsJson,
        checkConfigUrl: $checkConfigUrlJson,
        sessionId: null,
        conversationOutput: '',

        show: function() {
            const modalEl = document.getElementById('claudeCliModal');
            this.modal = new bootstrap.Modal(modalEl);

            // Reset session state
            this.sessionId = null;
            this.conversationOutput = '';

            // Reset to config step
            this.showConfigStep();
            this.prefillFromDefaults();
            this.checkConfigStatus();

            this.modal.show();
        },

        checkConfigStatus: function() {
            const statusEl = document.getElementById('claude-config-status');
            const statusTextEl = document.getElementById('claude-config-status-text');

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
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    statusEl.classList.add('d-none');
                    return;
                }

                statusEl.classList.remove('alert-secondary', 'alert-success', 'alert-info', 'alert-warning');

                if (data.hasAnyConfig) {
                    statusEl.classList.add('alert-success');
                    const parts = [];
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
            .catch(() => {
                statusEl.classList.add('d-none');
            });
        },

        prefillFromDefaults: function() {
            const defaults = this.projectDefaults;
            document.getElementById('claude-model').value = defaults.model || '';
            document.getElementById('claude-permission-mode').value = defaults.permissionMode || '';
            document.getElementById('claude-system-prompt').value = defaults.appendSystemPrompt || '';
            document.getElementById('claude-allowed-tools').value = defaults.allowedTools || '';
            document.getElementById('claude-disallowed-tools').value = defaults.disallowedTools || '';
        },

        showConfigStep: function() {
            document.getElementById('claude-cli-config-step').classList.remove('d-none');
            document.getElementById('claude-cli-output-step').classList.add('d-none');
            document.getElementById('claude-cli-run-btn').classList.remove('d-none');
            document.getElementById('claude-cli-back-btn').classList.add('d-none');
            document.getElementById('claude-cli-copy-btn').classList.add('d-none');
            document.getElementById('claude-cli-new-btn').classList.add('d-none');
            document.getElementById('claude-continue-section').classList.add('d-none');
        },

        showOutputStep: function(keepOutput) {
            document.getElementById('claude-cli-config-step').classList.add('d-none');
            document.getElementById('claude-cli-output-step').classList.remove('d-none');
            document.getElementById('claude-cli-run-btn').classList.add('d-none');
            document.getElementById('claude-cli-back-btn').classList.remove('d-none');
            document.getElementById('claude-continue-section').classList.add('d-none');

            // Reset output state (preserve output for follow-ups)
            document.getElementById('claude-cli-loading').classList.remove('d-none');
            document.getElementById('claude-cli-error').classList.add('d-none');
            if (!keepOutput) {
                document.getElementById('claude-cli-output-container').classList.add('d-none');
                document.getElementById('claude-cli-copy-btn').classList.add('d-none');
                document.getElementById('claude-cli-output').textContent = '';
            }
            document.getElementById('claude-cli-meta').classList.add('d-none');
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

        run: function() {
            this.conversationOutput = '';
            this.showOutputStep();
            this.execute();
        },

        execute: function(followUpPrompt) {
            const options = this.getOptions();
            if (this.sessionId)
                options.sessionId = this.sessionId;
            if (followUpPrompt)
                options.prompt = followUpPrompt;

            var self = this;

            fetch('$runClaudeUrl', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '$csrfToken',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(options)
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('claude-cli-loading').classList.add('d-none');

                if (data.success) {
                    const output = data.output || '(No output)';

                    // Store session ID for follow-ups
                    if (data.sessionId)
                        self.sessionId = data.sessionId;

                    // Accumulate conversation output
                    if (followUpPrompt) {
                        self.conversationOutput += '\\n\\n--- Follow-up ---\\n\\n> ' + followUpPrompt + '\\n\\n' + output;
                    } else {
                        self.conversationOutput = output;
                    }
                    document.getElementById('claude-cli-output').textContent = self.conversationOutput;
                    document.getElementById('claude-cli-output-container').classList.remove('d-none');
                    document.getElementById('claude-cli-copy-btn').classList.remove('d-none');

                    // Show metadata if available
                    const metaEl = document.getElementById('claude-cli-meta');
                    if (metaEl && (data.cost_usd || data.duration_ms || data.configSource)) {
                        const parts = [];
                        if (data.duration_ms) {
                            const seconds = (data.duration_ms / 1000).toFixed(1);
                            parts.push('Duration: ' + seconds + 's');
                        }
                        if (data.cost_usd) {
                            parts.push('Cost: \$' + data.cost_usd.toFixed(4));
                        }
                        if (data.configSource) {
                            let sourceLabel = data.configSource;
                            if (data.configSource.startsWith('project_own:')) {
                                sourceLabel = 'Config: Project\\'s own ' + data.configSource.replace('project_own:', '');
                            } else if (data.configSource === 'managed_workspace') {
                                sourceLabel = 'Config: Managed workspace';
                            } else if (data.configSource === 'default_workspace') {
                                sourceLabel = 'Config: Default workspace';
                            }
                            parts.push(sourceLabel);
                        }
                        metaEl.textContent = parts.join(' | ');
                        metaEl.classList.remove('d-none');
                    }

                    // Show continue UI if we have a session
                    if (self.sessionId)
                        self.showContinueUI();
                } else {
                    const errorMsg = data.error || data.output || 'An unknown error occurred';
                    document.getElementById('claude-cli-error').textContent = errorMsg;
                    document.getElementById('claude-cli-error').classList.remove('d-none');

                    if (data.output) {
                        document.getElementById('claude-cli-output').textContent = data.output;
                        document.getElementById('claude-cli-output-container').classList.remove('d-none');
                        document.getElementById('claude-cli-copy-btn').classList.remove('d-none');
                    }
                }
            })
            .catch(error => {
                document.getElementById('claude-cli-loading').classList.add('d-none');
                document.getElementById('claude-cli-error').textContent = 'Failed to execute Claude CLI: ' + error.message;
                document.getElementById('claude-cli-error').classList.remove('d-none');
            });
        },

        showContinueUI: function() {
            document.getElementById('claude-continue-section').classList.remove('d-none');
            document.getElementById('claude-cli-new-btn').classList.remove('d-none');
            document.getElementById('claude-continue-input').value = '';
            document.getElementById('claude-continue-input').focus();
            // Scroll output to bottom
            const outputEl = document.getElementById('claude-cli-output');
            outputEl.scrollTop = outputEl.scrollHeight;
        },

        continueConversation: function() {
            const input = document.getElementById('claude-continue-input');
            const prompt = input.value.trim();
            if (!prompt) return;

            input.value = '';
            this.showOutputStep(true);
            this.execute(prompt);
        },

        newConversation: function() {
            this.sessionId = null;
            this.conversationOutput = '';
            this.showConfigStep();
        }
    };

    document.getElementById('claude-cli-run-btn').addEventListener('click', function() {
        window.ClaudeCliModal.run();
    });

    document.getElementById('claude-cli-back-btn').addEventListener('click', function() {
        window.ClaudeCliModal.showConfigStep();
    });

    document.getElementById('claude-cli-copy-btn').addEventListener('click', function() {
        const output = document.getElementById('claude-cli-output').textContent;
        navigator.clipboard.writeText(output).then(function() {
            const btn = document.getElementById('claude-cli-copy-btn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-success');
            setTimeout(function() {
                btn.innerHTML = originalHtml;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-secondary');
            }, 2000);
        });
    });

    document.getElementById('claude-continue-btn').addEventListener('click', function() {
        window.ClaudeCliModal.continueConversation();
    });

    document.getElementById('claude-continue-input').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            window.ClaudeCliModal.continueConversation();
        }
    });

    document.getElementById('claude-cli-new-btn').addEventListener('click', function() {
        window.ClaudeCliModal.newConversation();
    });

    document.getElementById('claudeCliModal').addEventListener('hidden.bs.modal', function() {
        window.ClaudeCliModal.sessionId = null;
        window.ClaudeCliModal.conversationOutput = '';
        window.ClaudeCliModal.showConfigStep();
    });
    JS;
$this->registerJs($js);
?>
