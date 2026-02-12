<?php

namespace app\components;

use Yii;
use yii\web\UrlManager;

/**
 * Custom UrlManager that adds project parameter to project-scoped routes.
 *
 * This enables multi-tab support by including the project ID in URLs,
 * allowing different browser tabs to work with different projects.
 */
class ProjectUrlManager extends UrlManager
{
    private const PROJECT_SCOPED_PREFIXES = [
        'context',
        'field',
        'prompt-template',
        'prompt-instance',
        'note',
    ];

    /**
     * @inheritdoc
     */
    public function createUrl($params): string
    {
        $url = parent::createUrl($params);

        if (Yii::$app->request->isConsoleRequest) {
            return $url;
        }

        if (!$this->shouldAddProjectParam($params)) {
            return $url;
        }

        $projectId = Yii::$app->projectContext->getEffectiveProjectId();

        if ($projectId === null || $projectId === 0) {
            return $url;
        }

        if (str_contains($url, ProjectContext::URL_PARAM . '=')) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . ProjectContext::URL_PARAM . '=' . $projectId;
    }

    private function shouldAddProjectParam(array $params): bool
    {
        $route = $params[0] ?? '';

        foreach (self::PROJECT_SCOPED_PREFIXES as $prefix) {
            if (str_starts_with(ltrim($route, '/'), $prefix)) {
                return true;
            }
        }

        return false;
    }
}
