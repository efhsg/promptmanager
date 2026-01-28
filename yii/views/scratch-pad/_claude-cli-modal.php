<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var app\models\ScratchPad $model */

$runClaudeUrl = Url::to(['/scratch-pad/run-claude', 'id' => $model->id]);
$checkConfigUrl = $model->project ? Url::to(['/project/check-claude-config', 'id' => $model->project->id]) : null;
$projectDefaults = $model->project ? $model->project->getClaudeOptions() : [];
$projectDefaultsJson = json_encode($projectDefaults);
$checkConfigUrlJson = json_encode($checkConfigUrl);

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
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="claude-cli-back-btn" class="btn btn-outline-secondary d-none">
                    <i class="bi bi-arrow-left"></i> Back
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

        show: function() {
            const modalEl = document.getElementById('claudeCliModal');
            this.modal = new bootstrap.Modal(modalEl);

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
        },

        showOutputStep: function() {
            document.getElementById('claude-cli-config-step').classList.add('d-none');
            document.getElementById('claude-cli-output-step').classList.remove('d-none');
            document.getElementById('claude-cli-run-btn').classList.add('d-none');
            document.getElementById('claude-cli-back-btn').classList.remove('d-none');

            // Reset output state
            document.getElementById('claude-cli-loading').classList.remove('d-none');
            document.getElementById('claude-cli-error').classList.add('d-none');
            document.getElementById('claude-cli-output-container').classList.add('d-none');
            document.getElementById('claude-cli-copy-btn').classList.add('d-none');
            document.getElementById('claude-cli-output').textContent = '';
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
            this.showOutputStep();
            this.execute();
        },

        execute: function() {
            const options = this.getOptions();

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
                    document.getElementById('claude-cli-output').textContent = output;
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
                                sourceLabel = 'Config: Project\'s own ' + data.configSource.replace('project_own:', '');
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

    document.getElementById('claudeCliModal').addEventListener('hidden.bs.modal', function() {
        window.ClaudeCliModal.showConfigStep();
    });
    JS;
$this->registerJs($js);
?>
