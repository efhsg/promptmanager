<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @var yii\web\View $this */

/** @var string $content */

use app\components\ProjectContext;
use app\widgets\Alert;
use yii\bootstrap5\{Breadcrumbs, Html, Nav, NavBar};

$this->beginContent('@app/views/layouts/_base.php'); ?>

    <header id="header">
        <?php
        NavBar::begin([
            'brandLabel' => Html::img('@web/images/prompt-manager-logo-nav.png', ['alt' => Yii::$app->name, 'height' => 40]) . '&nbsp;&nbsp;&nbsp;' . Yii::$app->name,
            'brandUrl' => Yii::$app->homeUrl,
            'options' => ['class' => 'navbar-expand-md navbar-dark bg-primary fixed-top'],
        ]);

echo '<div class="d-flex align-items-center">';

echo Nav::widget([
    'options' => ['class' => 'navbar-nav me-auto ms-1'],
    'activateParents' => true,
    'activateItems' => true,
    'items' => [
        [
            'label' => 'Manage',
            'items' => [
                [
                    'label' => 'Projects',
                    'url' => ['/project/index'],
                    'active' => (Yii::$app->controller->id === 'project'),
                ],
                [
                    'label' => 'Contexts',
                    'url' => ['/context/index'],
                ],
                [
                    'label' => 'Fields',
                    'url' => ['/field/index'],
                ],
                [
                    'label' => 'Templates',
                    'url' => ['/prompt-template/index'],
                ],
                [
                    'label' => 'Generated',
                    'url' => ['/prompt-instance/index'],
                ],
            ],
        ],
        [
            'label' => 'Generate',
            'url' => ['/prompt-instance/create'],
        ],
        [
            'label' => 'Scratch Pad',
            'items' => [
                [
                    'label' => 'Create',
                    'url' => ['/scratch-pad/create'],
                ],
                [
                    'label' => 'Saved',
                    'url' => ['/scratch-pad/index'],
                ],
            ],
        ],
    ],
]);

if (!Yii::$app->user->isGuest) {
    $projectList = Yii::$app->projectService->fetchProjectsList(Yii::$app->user->id);
    $projectContext = Yii::$app->projectContext;
    $currentProject = $projectContext->getCurrentProject();
    $currentProjectId = $projectContext->isAllProjectsContext()
        ? ProjectContext::ALL_PROJECTS_ID
        : $currentProject?->id;

    $projectListWithAll = [ProjectContext::ALL_PROJECTS_ID => 'All Projects'] + $projectList;

    echo Html::beginForm(['/project/set-current'], 'post', [
        'class' => 'd-flex align-items-center ms-4 me-3',
        'id' => 'set-context-project-form',
    ]);
    echo Html::dropDownList('project_id', $currentProjectId, $projectListWithAll, [
        'class' => 'form-select me-2',
        'prompt' => 'No Project',
        'onchange' => 'this.form.submit()',
    ]);
    echo Html::endForm();
}

echo '</div>';

echo Nav::widget([
    'options' => ['class' => 'navbar-nav ms-auto'],
    'items' => Yii::$app->user->isGuest ? [
        ['label' => 'Signup', 'url' => ['/identity/auth/signup']],
        ['label' => 'Login', 'url' => ['/identity/auth/login']],
    ] : [
        '<li class="nav-item">'
        . Html::beginForm(['/identity/auth/logout'])
        . Html::submitButton(
            'Logout (' . Html::encode(Yii::$app->user->identity->username) . ')',
            ['class' => 'nav-link btn btn-link logout']
        )
        . Html::endForm()
        . '</li>',
    ],
]);

NavBar::end();
?>
    </header>

    <main id="main" class="flex-shrink-0" role="main">
        <div class="container mt-5 pt-5">
            <?php if (!empty($this->params['breadcrumbs'])): ?>
                <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
            <?php endif ?>
            <?= Alert::widget() ?>
            <?= $content ?>
        </div>
    </main>

<?php $this->endContent(); ?>
