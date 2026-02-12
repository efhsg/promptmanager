<?php
/**
 * Mobile bottom navigation bar
 * Displayed on screens < 768px, except on Claude chat page
 *
 * @var yii\web\View $this
 */

use yii\helpers\Url;

$controllerId = Yii::$app->controller->id ?? '';
$actionId = Yii::$app->controller->action->id ?? '';

$isActive = static fn(string $controller, ?string $action = null): string =>
    ($controllerId === $controller && ($action === null || $actionId === $action)) ? 'active' : '';
?>
<nav class="mobile-bottom-nav" id="mobile-bottom-nav">
    <ul class="mobile-bottom-nav__list">
        <li class="mobile-bottom-nav__item">
            <a href="<?= Url::to(['/prompt-instance/create']) ?>"
               class="mobile-bottom-nav__link <?= $isActive('prompt-instance', 'create') ?>">
                <i class="bi bi-plus-circle mobile-bottom-nav__icon"></i>
                <span class="mobile-bottom-nav__label">Prompts</span>
            </a>
        </li>
        <li class="mobile-bottom-nav__item">
            <a href="<?= Url::to(['/note/index']) ?>"
               class="mobile-bottom-nav__link <?= $isActive('note') ?>">
                <i class="bi bi-journal-text mobile-bottom-nav__icon"></i>
                <span class="mobile-bottom-nav__label">Notes</span>
            </a>
        </li>
        <li class="mobile-bottom-nav__item">
            <a href="<?= Url::to(['/claude/index']) ?>"
               class="mobile-bottom-nav__link <?= $isActive('claude') ?>">
                <i class="bi bi-chat-dots mobile-bottom-nav__icon"></i>
                <span class="mobile-bottom-nav__label">Claude</span>
            </a>
        </li>
        <li class="mobile-bottom-nav__item">
            <button type="button"
                    class="mobile-bottom-nav__link"
                    id="mobile-search-trigger"
                    aria-label="Search">
                <i class="bi bi-search mobile-bottom-nav__icon"></i>
                <span class="mobile-bottom-nav__label">Search</span>
            </button>
        </li>
    </ul>
</nav>
