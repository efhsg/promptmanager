<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use yii\bootstrap5\Html;

AppAsset::register($this);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/images/prompt-manager.ico')]);
$this->registerCssFile('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css');
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">

<head>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>

<?php
$projectSchemeValue = '';
if (!Yii::$app->user->isGuest) {
    $project = Yii::$app->projectContext->getCurrentProject();
    if ($project !== null && $project->color_scheme !== null) {
        $projectSchemeValue = $project->color_scheme;
    }
}
?>
<body class="d-flex flex-column h-100" data-project-scheme="<?= Html::encode($projectSchemeValue) ?>">
    <script>
    (function() {
        var tab = sessionStorage.getItem('pm_color_scheme');
        var project = document.body.getAttribute('data-project-scheme');
        var scheme = tab || project || '';
        if (scheme && scheme !== 'default')
            document.body.classList.add('color-scheme-' + scheme);
    })();
    </script>
    <?php $this->beginBody() ?>
    <?= $content ?>
    <?php $this->endBody() ?>
</body>

</html>
<?php $this->endPage() ?>