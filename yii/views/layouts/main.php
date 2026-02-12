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
            'brandLabel' => Html::img('@web/images/prompt-manager-logo-nav.png', ['alt' => Yii::$app->name, 'height' => 40])
                . '<span class="d-none d-xxl-inline">&nbsp;&nbsp;&nbsp;' . Yii::$app->name . '</span>',
            'brandUrl' => Yii::$app->homeUrl,
            'options' => ['class' => 'navbar-expand-xl navbar-dark bg-primary fixed-top'],
        ]);

echo Nav::widget([
    'options' => ['class' => 'navbar-nav me-auto ms-1'],
    'activateParents' => true,
    'activateItems' => true,
    'items' => [
        [
            'label' => 'Prompts',
            'items' => [
                [
                    'label' => 'Generate',
                    'url' => ['/prompt-instance/create'],
                ],
                '<hr class="dropdown-divider">',
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
            'label' => 'Notes',
            'url' => ['/note/index'],
            'options' => ['id' => 'nav-notes'],
        ],
    ],
]);

if (!Yii::$app->user->isGuest) {
    echo '<div class="quick-search-container ms-auto me-3">';
    echo '<button type="button" class="btn btn-sm advanced-search-btn" data-bs-toggle="modal" data-bs-target="#advancedSearchModal" title="Advanced Search"><i class="bi bi-search"></i></button>';
    echo Html::textInput('q', '', [
        'id' => 'quick-search-input',
        'class' => 'form-control form-control-sm',
        'placeholder' => 'Quick Search',
        'autocomplete' => 'off',
    ]);
    echo '<div id="quick-search-results"></div>';
    echo '</div>';
}

echo Nav::widget([
    'options' => ['class' => 'navbar-nav'],
    'encodeLabels' => false,
    'items' => Yii::$app->user->isGuest ? [
        ['label' => 'Signup', 'url' => ['/identity/auth/signup']],
        ['label' => 'Login', 'url' => ['/identity/auth/login']],
    ] : [
        [
            'label' => '<i class="bi bi-person-circle"></i> '
                . Html::encode(Yii::$app->user->identity->username),
            'dropdownOptions' => ['class' => 'dropdown-menu dropdown-menu-end'],
            'items' => [
                [
                    'label' => '<i class="bi bi-key"></i> ' . Yii::t('app', 'Change password'),
                    'url' => ['/identity/auth/change-password'],
                    'encode' => false,
                ],
                '<hr class="dropdown-divider">',
                [
                    'label' => '<i class="bi bi-box-arrow-right"></i> ' . Yii::t('app', 'Logout'),
                    'url' => ['/identity/auth/logout'],
                    'linkOptions' => ['data-method' => 'post'],
                    'encode' => false,
                ],
            ],
        ],
    ],
]);

NavBar::end();

if (!Yii::$app->user->isGuest) {
    $projectList = Yii::$app->projectService->fetchProjectsList(Yii::$app->user->id);
    $projectContext = Yii::$app->projectContext;
    $currentProject = $projectContext->getCurrentProject();
    $currentProjectId = $projectContext->isAllProjectsContext()
        ? ProjectContext::ALL_PROJECTS_ID
        : $currentProject?->id;

    $projectListWithAll = [ProjectContext::ALL_PROJECTS_ID => 'All Projects'] + $projectList;

    echo '<div class="project-context-wrapper">';
    echo Html::dropDownList('project_id', $currentProjectId, $projectListWithAll, [
        'class' => 'form-select',
        'prompt' => 'No Project',
        'id' => 'project-context-selector',
        'onchange' => 'updateProjectInUrl(this.value)',
    ]);
    echo '</div>';
}
?>
    </header>

    <main id="main" class="flex-shrink-0" role="main">
        <div class="container mt-5 pt-5">
            <?php if (!empty($this->params['breadcrumbs'])): ?>
                <div class="d-none d-md-block">
                    <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
                </div>
            <?php endif ?>
            <?= Alert::widget() ?>
            <?= $content ?>
        </div>
    </main>

<?php if (!Yii::$app->user->isGuest): ?>
    <?= $this->render('_advanced-search-modal') ?>
<?php endif; ?>

<script>
(function() {
    window.updateProjectInUrl = function(projectId) {
        const url = new URL(window.location.href);
        if (projectId && parseInt(projectId) > 0) {
            url.searchParams.set('<?= ProjectContext::URL_PARAM ?>', projectId);
        } else if (projectId === '<?= ProjectContext::ALL_PROJECTS_ID ?>') {
            url.searchParams.set('<?= ProjectContext::URL_PARAM ?>', projectId);
        } else {
            url.searchParams.delete('<?= ProjectContext::URL_PARAM ?>');
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        fetch('<?= \yii\helpers\Url::to(['/project/set-current']) ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'project_id=' + encodeURIComponent(projectId)
        })
        .catch(function(err) { console.error('Failed to save project preference:', err); })
        .finally(function() { window.location.href = url.toString(); });
    };

    function positionProjectDropdown() {
        const wrapper = document.querySelector('.project-context-wrapper');
        if (!wrapper) return;

        const isDesktop = window.innerWidth >= 1200; // Bootstrap xl breakpoint
        if (isDesktop) {
            const notesLink = document.querySelector('#nav-notes a');
            if (notesLink) {
                const linkRect = notesLink.getBoundingClientRect();
                const wrapperHeight = wrapper.offsetHeight;
                const linkCenterY = linkRect.top + (linkRect.height / 2);
                wrapper.style.left = (linkRect.right + 20) + 'px';
                wrapper.style.top = (linkCenterY - (wrapperHeight / 2)) + 'px';
                wrapper.style.transform = 'none';
            }
        } else {
            wrapper.style.left = '';
            wrapper.style.top = '';
            wrapper.style.transform = '';
        }
    }

    document.addEventListener('DOMContentLoaded', positionProjectDropdown);
    window.addEventListener('load', positionProjectDropdown);
    window.addEventListener('resize', positionProjectDropdown);
})();
</script>

<?php $this->endContent(); ?>
