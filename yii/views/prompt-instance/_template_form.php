<?php

use app\assets\QuillAsset;
use app\services\CopyFormatConverter;
use app\services\PromptFieldRenderer;
use common\enums\CopyType;

/* @var yii\web\View $this */
/* @var string $templateBody */
/* @var array $fields */

QuillAsset::register($this);
$this->registerCss(<<<'CSS'
    .generated-prompt-form h2 { font-size: 1.25rem; line-height: 1.3; margin-bottom: 0.5rem; }
    .generated-prompt-form ol { list-style-type: decimal; padding-left: 2em; }
    .generated-prompt-form ol ol { list-style-type: lower-alpha; }
    .generated-prompt-form ol ol ol { list-style-type: lower-roman; }
    .generated-prompt-form ul { list-style-type: disc; padding-left: 2em; }
    .generated-prompt-form ul ul { list-style-type: circle; }
    .generated-prompt-form ul ul ul { list-style-type: square; }
    .generated-prompt-form li { list-style-type: inherit; }
    CSS);

$delta = json_decode($templateBody, true);
if (!is_array($delta) || !isset($delta['ops'])) {
    throw new \InvalidArgumentException('Template is not in valid Delta format.');
}

$copyFormatConverter = new CopyFormatConverter();
$templateHtml = $copyFormatConverter->convertFromQuillDelta($templateBody, CopyType::HTML);

$renderer = new PromptFieldRenderer($this);


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
