<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @var yii\web\View $this */

/** @var string $content */

use app\widgets\Alert;
use yii\bootstrap5\{Breadcrumbs, Html, Nav, NavBar};

$this->beginContent('@app/views/layouts/_base.php'); ?>

    <header id="header">
        <?php
        NavBar::begin([
            'brandLabel' => Html::img('@web/images/prompt-manager-logo-nav.png', ['alt' => Yii::$app->name, 'height' => 40]) . '&nbsp;&nbsp;&nbsp;' . Yii::$app->name,
            'brandUrl' => Yii::$app->homeUrl,
            'options' => ['class' => 'navbar-expand-md navbar-dark bg-dark fixed-top']
        ]);

        echo Nav::widget([
            'options' => ['class' => 'navbar-nav me-auto'],
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
                            'url' => ['/manage/contexts'],
                        ],
                        [
                            'label' => 'Templates',
                            'url' => ['/manage/templates']
                        ],
                    ],
                ],
                [
                    'label' => 'Generate',
                    'url' => ['/generate/generate'],
                ],
            ],
        ]);

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
                . '</li>'
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