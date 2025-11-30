<?php

use app\assets\QuillAsset;
use app\services\CopyFormatConverter;
use app\services\PromptFieldRenderer;
use common\enums\CopyType;

/* @var yii\web\View $this */
/* @var string $templateBody */
/* @var array $fields */

QuillAsset::register($this);
$this->registerCss('.generated-prompt-form h2{font-size:1.25rem;line-height:1.3;margin-bottom:0.5rem;}');

// Validate template format
$delta = json_decode($templateBody, true);
if (!is_array($delta) || !isset($delta['ops'])) {
    throw new \InvalidArgumentException('Template is not in valid Delta format.');
}

// Convert template to HTML for rendering
$copyFormatConverter = new CopyFormatConverter();
$templateHtml = $copyFormatConverter->convertFromQuillDelta($templateBody, CopyType::HTML);

// Create renderer service
$renderer = new PromptFieldRenderer($this);

// Replace placeholders with rendered fields
$templateRendered = preg_replace_callback(
    PromptFieldRenderer::PLACEHOLDER_PATTERN,
    static function (array $matches) use ($fields, $renderer) {
        $placeholder = $matches[1];
        if (!isset($fields[$placeholder])) {
            return $matches[0];
        }

        return $renderer->renderField($fields[$placeholder], $placeholder);
    },
    $templateHtml
);
?>

<div class="generated-prompt-form">
    <?= $templateRendered ?>
</div>
