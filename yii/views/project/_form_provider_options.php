<?php
use common\enums\AiPermissionMode;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var app\models\Project $model */
/** @var string $providerId */
/** @var array $providerData */
/** @var array $projectConfigStatus */

$providerOptions = $model->getAiOptionsForProvider($providerId);
$models = $providerData['models'] ?? [];
$permissionModes = $providerData['permissionModes'] ?? [];
$configSchema = $providerData['configSchema'] ?? [];
$prefix = "ai_options[{$providerId}]";
$idPrefix = "provider-{$providerId}";
?>

<p class="text-muted small mb-3">Default options for <?= Html::encode($providerData['name']) ?> CLI executions from this project's notes. Leave blank to use CLI defaults.</p>

<div class="row g-3">
    <?php if ($models !== []): ?>
        <div class="col-md-6">
            <label for="<?= Html::encode($idPrefix) ?>-model" class="form-label">Model</label>
            <?= Html::dropDownList(
                "{$prefix}[model]",
                $providerOptions['model'] ?? '',
                array_merge(['' => '(Use CLI default)'], $models),
                ['id' => "{$idPrefix}-model", 'class' => 'form-select']
            ) ?>
        </div>
    <?php endif; ?>

    <?php if ($permissionModes !== []): ?>
        <div class="col-md-6">
            <label for="<?= Html::encode($idPrefix) ?>-permission-mode" class="form-label">Permission Mode</label>
            <?php
            $modeLabels = ['' => '(Use CLI default)'];
        foreach ($permissionModes as $modeValue) {
            $enum = AiPermissionMode::tryFrom($modeValue);
            $modeLabels[$modeValue] = $enum ? $enum->label() : $modeValue;
        }
        ?>
            <?= Html::dropDownList(
                "{$prefix}[permissionMode]",
                $providerOptions['permissionMode'] ?? '',
                $modeLabels,
                ['id' => "{$idPrefix}-permission-mode", 'class' => 'form-select']
            ) ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($configSchema !== []): ?>
<div class="row g-3 mt-1">
<?php foreach ($configSchema as $fieldKey => $fieldDef):
    $fieldType = $fieldDef['type'] ?? 'text';
    $fieldLabel = $fieldDef['label'] ?? $fieldKey;
    $fieldHint = $fieldDef['hint'] ?? '';
    $fieldPlaceholder = $fieldDef['placeholder'] ?? '';
    $fieldOptions = $fieldDef['options'] ?? [];
    $fieldDefault = $fieldDef['default'] ?? '';
    $fieldValue = $providerOptions[$fieldKey] ?? $fieldDefault;
    $fieldId = "{$idPrefix}-{$fieldKey}";
    $fieldName = "{$prefix}[{$fieldKey}]";
    ?>

    <?php if ($fieldType === 'select'): ?>
        <div class="col-md-6">
            <label for="<?= Html::encode($fieldId) ?>" class="form-label"><?= Html::encode($fieldLabel) ?></label>
            <?= Html::dropDownList(
                $fieldName,
                $fieldValue,
                array_merge(['' => '(Use CLI default)'], $fieldOptions),
                ['id' => $fieldId, 'class' => 'form-select']
            ) ?>
            <?php if ($fieldHint !== ''): ?>
                <div class="form-text"><?= Html::encode($fieldHint) ?></div>
            <?php endif; ?>
        </div>
    <?php elseif ($fieldType === 'textarea'): ?>
        <div class="col-12">
            <label for="<?= Html::encode($fieldId) ?>" class="form-label"><?= Html::encode($fieldLabel) ?></label>
            <?= Html::textarea($fieldName, $fieldValue, [
                'id' => $fieldId,
                'class' => 'form-control',
                'rows' => 2,
                'placeholder' => $fieldPlaceholder,
            ]) ?>
            <?php if ($fieldHint !== ''): ?>
                <div class="form-text"><?= Html::encode($fieldHint) ?></div>
            <?php endif; ?>
        </div>
    <?php elseif ($fieldType === 'checkbox'): ?>
        <div class="col-md-6">
            <div class="form-check mt-4">
                <?= Html::checkbox($fieldName, (bool) $fieldValue, [
                    'id' => $fieldId,
                    'class' => 'form-check-input',
                ]) ?>
                <label for="<?= Html::encode($fieldId) ?>" class="form-check-label"><?= Html::encode($fieldLabel) ?></label>
            </div>
            <?php if ($fieldHint !== ''): ?>
                <div class="form-text"><?= Html::encode($fieldHint) ?></div>
            <?php endif; ?>
        </div>
    <?php else: /* text */ ?>
        <div class="col-md-6">
            <label for="<?= Html::encode($fieldId) ?>" class="form-label"><?= Html::encode($fieldLabel) ?></label>
            <?= Html::textInput($fieldName, $fieldValue, [
                'id' => $fieldId,
                'class' => 'form-control',
                'placeholder' => $fieldPlaceholder,
            ]) ?>
            <?php if ($fieldHint !== ''): ?>
                <div class="form-text"><?= Html::encode($fieldHint) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php
// Command dropdown section — only for saved projects with a root directory
if ($model->id && !empty($model->root_directory)):
    $commandBlacklistJson = Json::encode($model->getAiCommandBlacklist($providerId));
    $commandGroupsJson = Json::encode($model->getAiCommandGroups($providerId) ?: new \stdClass());
    $commandsUrl = Url::to(['/project/ai-commands', 'id' => $model->id, 'provider' => $providerId]);
    $cmdPrefix = "{$prefix}";
    $cmdIdPrefix = "cmd-{$providerId}";
    ?>
    <hr class="my-3">
    <h6 class="text-muted mb-3"><i class="bi bi-list-ul me-1"></i>Command Dropdown</h6>
    <p class="text-muted small mb-3">
        Configure which slash commands appear in the chat dropdown and how they are grouped.
    </p>

    <div id="<?= $cmdIdPrefix ?>-loading" class="text-muted small mb-3" style="display:none;">
        <i class="bi bi-hourglass-split me-1"></i>Loading commands...
    </div>
    <div id="<?= $cmdIdPrefix ?>-empty" class="alert alert-info small" style="display:none;">
        <i class="bi bi-info-circle me-1"></i>
        No commands found for this provider.
    </div>

    <div id="<?= $cmdIdPrefix ?>-content" style="display:none;">
        <div class="mb-3">
            <label class="form-label">Hidden Commands</label>
            <select id="<?= $cmdIdPrefix ?>-blacklist-select" class="form-select" multiple></select>
            <div class="form-text">Commands hidden from the chat dropdown.</div>
        </div>

        <div class="mb-3">
            <label class="form-label">Command Groups</label>
            <div id="<?= $cmdIdPrefix ?>-groups-container"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-2 cmd-add-group-btn" data-provider="<?= Html::encode($providerId) ?>">
                <i class="bi bi-plus-circle"></i> Add Group
            </button>
            <div class="form-text">Leave empty for a flat alphabetical list. Ungrouped commands appear under "Other".</div>
        </div>
    </div>

    <input type="hidden" id="<?= $cmdIdPrefix ?>-blacklist-hidden" name="<?= $cmdPrefix ?>[commandBlacklist]" value="<?= Html::encode($commandBlacklistJson) ?>">
    <input type="hidden" id="<?= $cmdIdPrefix ?>-groups-hidden" name="<?= $cmdPrefix ?>[commandGroups]" value="<?= Html::encode($commandGroupsJson) ?>">

    <button type="button" class="btn btn-sm btn-outline-secondary cmd-load-btn" data-provider="<?= Html::encode($providerId) ?>" data-url="<?= Html::encode($commandsUrl) ?>">
        <i class="bi bi-arrow-clockwise me-1"></i>Load Commands
    </button>

<?php
        $commandDropdownJs = <<<JS
                (function() {
                    var providerId = '{$providerId}';
                    var prefix = '{$cmdIdPrefix}';
                    var availableCommands = {};
                    var blacklistSelect = null;
                    var groupCounter = 0;

                    function escapeHtml(str) {
                        var div = document.createElement('div');
                        div.appendChild(document.createTextNode(str));
                        return div.innerHTML;
                    }

                    function fetchCommands() {
                        document.getElementById(prefix + '-loading').style.display = '';
                        document.getElementById(prefix + '-empty').style.display = 'none';
                        document.getElementById(prefix + '-content').style.display = 'none';
                        fetch('{$commandsUrl}')
                            .then(function(r) {
                                if (!r.ok) return r.text().then(function(t) { throw new Error('HTTP ' + r.status + ': ' + t.substring(0, 200)); });
                                return r.json();
                            })
                            .then(function(data) {
                                document.getElementById(prefix + '-loading').style.display = 'none';
                                if (data.success && Object.keys(data.commands).length > 0) {
                                    availableCommands = data.commands;
                                    document.getElementById(prefix + '-content').style.display = '';
                                    initializeUI();
                                } else {
                                    document.getElementById(prefix + '-empty').style.display = '';
                                }
                            })
                            .catch(function(err) {
                                console.error(providerId + '-commands fetch error:', err);
                                document.getElementById(prefix + '-loading').style.display = 'none';
                                document.getElementById(prefix + '-empty').style.display = '';
                            });
                    }

                    function initializeUI() {
                        initBlacklistSelect();
                        initGroupsFromData();
                        syncHiddenFields();
                    }

                    function initBlacklistSelect() {
                        blacklistSelect = jQuery('#' + prefix + '-blacklist-select');
                        blacklistSelect.empty();
                        Object.keys(availableCommands).forEach(function(cmd) {
                            blacklistSelect.append(new Option(cmd, cmd, false, false));
                        });

                        var currentBlacklist = {$commandBlacklistJson};
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
                        var groups = {$commandGroupsJson};
                        Object.keys(groups).forEach(function(label) {
                            addGroupRow(label, groups[label]);
                        });
                    }

                    function addGroupRow(label, selectedCommands) {
                        label = label || '';
                        selectedCommands = selectedCommands || [];

                        var groupId = prefix + '-group-' + (++groupCounter);
                        var container = document.getElementById(prefix + '-groups-container');

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
                        row._orderedCommands = [];

                        populateGroupSelect(select, row);

                        jQuery(select).select2({
                            placeholder: 'Add command...',
                            width: '100%'
                        });

                        jQuery(select).on('select2:select', function(e) {
                            var cmd = e.params.data.id;
                            row._orderedCommands.push(cmd);
                            renderOrderList(row);
                            rebuildAllGroupSelects();
                            syncHiddenFields();
                            jQuery(select).val(null).trigger('change.select2');
                        });

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
                        Object.keys(availableCommands).forEach(function(cmd) {
                            if (blacklisted.indexOf(cmd) === -1 && allAssigned.indexOf(cmd) === -1) {
                                selectElement.add(new Option(cmd, cmd, false, false));
                            }
                        });
                        jQuery(selectElement).val(null).trigger('change.select2');
                    }

                    function getAllAssignedCommands() {
                        var assigned = [];
                        document.querySelectorAll('#' + prefix + '-groups-container .card').forEach(function(row) {
                            if (row._orderedCommands)
                                assigned = assigned.concat(row._orderedCommands);
                        });
                        return assigned;
                    }

                    function removeBlacklistedFromGroups() {
                        var blacklisted = blacklistSelect ? (blacklistSelect.val() || []) : [];
                        if (blacklisted.length === 0) return;
                        document.querySelectorAll('#' + prefix + '-groups-container .card').forEach(function(row) {
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
                        document.querySelectorAll('#' + prefix + '-groups-container .card').forEach(function(row) {
                            var sel = row.querySelector('.group-commands-select');
                            if (sel) populateGroupSelect(sel, row);
                        });
                    }

                    function syncHiddenFields() {
                        var blacklist = blacklistSelect ? (blacklistSelect.val() || []) : [];
                        document.getElementById(prefix + '-blacklist-hidden').value = JSON.stringify(blacklist);

                        var groups = {};
                        document.querySelectorAll('#' + prefix + '-groups-container .card').forEach(function(row) {
                            var label = row.querySelector('.group-label-input').value.trim();
                            var commands = row._orderedCommands || [];
                            if (label !== '' && commands.length > 0) {
                                groups[label] = commands;
                            }
                        });
                        document.getElementById(prefix + '-groups-hidden').value = JSON.stringify(groups);
                    }

                    // Add group button
                    document.querySelector('.cmd-add-group-btn[data-provider="' + providerId + '"]')
                        ?.addEventListener('click', function() { addGroupRow('', []); });

                    // Load commands button
                    document.querySelector('.cmd-load-btn[data-provider="' + providerId + '"]')
                        ?.addEventListener('click', function() { fetchCommands(); });
                })();
            JS;

    $this->registerJs($commandDropdownJs);
endif;
?>
