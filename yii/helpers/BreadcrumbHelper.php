<?php

namespace app\helpers;

class BreadcrumbHelper
{
    /**
     * Generate breadcrumbs for a model-based view.
     *
     * @param array $breadcrumbParts An array of ["label" => "URL|null"] representing breadcrumb parts.
     * @param object|null $model The model instance (optional).
     * @param string|null $actionLabel The label for the current action (e.g., "View").
     * @return array The generated breadcrumbs array.
     */
    public static function generateModelBreadcrumbs(array $breadcrumbParts, ?object $model, ?string $actionLabel): array
    {
        $breadcrumbs = [];


        foreach ($breadcrumbParts as $part) {
            $breadcrumbs[] = [
                'label' => $part['label'],
                'url' => $part['url'], // URL or null for static breadcrumb
            ];
        }

        if ($model !== null) {
            $breadcrumbs[] = [
                'label' => $model->name,
                'url' => ['view', 'id' => $model->id],
            ];
        }

        if ($actionLabel) {
            $breadcrumbs[] = [
                'label' => $actionLabel,
                'url' => null,
            ];
        }

        return $breadcrumbs;
    }
}
