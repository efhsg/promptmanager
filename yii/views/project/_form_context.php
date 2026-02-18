<?php
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\Project $model */
/** @var yii\widgets\ActiveForm $form */
/** @var array $projectConfigStatus */

$hasAnyProviderConfig = false;
$configSummaryParts = [];
foreach ($projectConfigStatus as $providerId => $status) {
    if (!empty($status['pathStatus']) && $status['pathStatus'] === 'has_config') {
        $hasAnyProviderConfig = true;
        $providerName = Html::encode($status['providerName'] ?? $providerId);
        $parts = array_filter([
            !empty($status['hasConfigFile']) ? '<code>' . $providerName . ' config file</code>' : null,
            !empty($status['hasConfigDir']) ? '<code>' . $providerName . ' config dir</code>' : null,
        ]);
        if ($parts !== []) {
            $configSummaryParts[] = implode(' + ', $parts);
        }
    }
}
?>

<p class="text-muted small mb-3">
    Define project-specific context for AI providers when this project's directory lacks its own configuration.
    This serves as a "database context" that gets injected at runtime.
</p>

<?php
// Show per-provider config status alerts
foreach ($projectConfigStatus as $providerId => $status):
    $ps = $status['pathStatus'] ?? null;
    $providerName = Html::encode($status['providerName'] ?? $providerId);

    if ($ps === 'not_mapped'): ?>
        <div class="alert alert-danger small mb-3">
            <i class="bi bi-x-circle me-1"></i>
            <strong><?= $providerName ?>:</strong>
            Project directory not mapped into container. Check <code>PATH_MAPPINGS</code> in <code>.env</code>
            and ensure <code>PROJECTS_ROOT</code> volume is configured in <code>docker-compose.yml</code>.
        </div>
    <?php elseif ($ps === 'not_accessible'): ?>
        <div class="alert alert-danger small mb-3">
            <i class="bi bi-x-circle me-1"></i>
            <strong><?= $providerName ?>:</strong>
            Project directory not accessible in container (mapped to <code><?= Html::encode($status['effectivePath'] ?? '') ?></code>).
            Check that <code>PROJECTS_ROOT</code> volume is mounted correctly.
        </div>
    <?php elseif ($ps === 'has_config'): ?>
        <div class="alert alert-info small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            <strong><?= $providerName ?>:</strong>
            This project's directory already has its own config
            (<?= implode(' + ', array_filter([
                !empty($status['hasConfigFile']) ? '<code>config file</code>' : null,
                !empty($status['hasConfigDir']) ? '<code>config dir</code>' : null,
            ])) ?>).
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php if ($hasAnyProviderConfig): ?>
    <div class="alert alert-secondary small mb-3">
        <i class="bi bi-info-circle me-1"></i>
        The context below is only used when the project directory lacks native provider config.
    </div>
<?php endif; ?>

<?= $form->field($model, 'ai_context')
    ->hiddenInput(['id' => 'context-hidden'])
    ->label(false) ?>

<div class="resizable-editor-container" style="min-height: 150px; max-height: 600px;">
    <div id="context-editor" class="resizable-editor" style="min-height: 150px;"></div>
</div>
<div class="hint-block">Rich-text context describing the project, coding standards, and any specific instructions for AI providers.</div>

<div class="mt-2">
    <button type="button" id="generate-context-btn" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-magic"></i> Generate from Description
    </button>
</div>
