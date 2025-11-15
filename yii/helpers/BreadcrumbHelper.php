<?php

namespace app\helpers;

use InvalidArgumentException;
use yii\db\BaseActiveRecord;

class BreadcrumbHelper
{
    /**
     * Generate breadcrumbs for a model-based view.
     *
     * @param array<int, array{label: string, url?: array|string|null}> $breadcrumbParts
     * @param object|null $model The model instance (optional).
     * @param string|null $actionLabel The label for the current action (e.g., "View").
     * @return array<int, array{label: string, url: array|string|null}>
     */
    public static function generateModelBreadcrumbs(array $breadcrumbParts, ?object $model, ?string $actionLabel): array
    {
        $breadcrumbs = [];

        foreach ($breadcrumbParts as $index => $part) {
            if (!is_array($part)) {
                throw new InvalidArgumentException("Breadcrumb part at index $index must be an array.");
            }
            $breadcrumbs[] = self::normalizePart($part, $index);
        }

        if ($model !== null) {
            $label = self::resolveModelLabel($model);
            $id = self::resolveModelId($model);

            $breadcrumbs[] = [
                'label' => $label,
                'url' => $id !== null ? ['view', 'id' => $id] : null,
            ];
        }

        if ($actionLabel !== null && $actionLabel !== '') {
            $breadcrumbs[] = [
                'label' => $actionLabel,
                'url' => null,
            ];
        }

        return $breadcrumbs;
    }

    /**
     * @param array $part
     * @param int $index
     * @return array{label: string, url: array|string|null}
     */
    private static function normalizePart(array $part, int $index): array
    {
        if (!array_key_exists('label', $part) || !is_string($part['label']) || $part['label'] === '') {
            throw new InvalidArgumentException("Breadcrumb part at index $index must have a non-empty 'label' string.");
        }

        $url = $part['url'] ?? null;
        if (!is_null($url) && !is_string($url) && !is_array($url)) {
            throw new InvalidArgumentException("Breadcrumb part 'url' at index $index must be array|string|null.");
        }

        return [
            'label' => $part['label'],
            'url' => $url,
        ];
    }

    private static function resolveModelLabel(object $model): string
    {
        // Prefer explicit attributes for Yii AR
        if ($model instanceof BaseActiveRecord) {
            $name = $model->getAttribute('name');
            if (is_string($name) && $name !== '') {
                return $name;
            }
            $title = $model->getAttribute('title');
            if (is_string($title) && $title !== '') {
                return $title;
            }
        }

        // Generic object properties
        /** @var mixed $candidate */
        $candidate = null;
        if (isset($model->name) && is_string($model->name) && $model->name !== '') {
            $candidate = $model->name;
        } elseif (isset($model->title) && is_string($model->title) && $model->title !== '') {
            $candidate = $model->title;
        }

        if (is_string($candidate) && $candidate !== '') {
            return $candidate;
        }

        // __toString fallback
        if (method_exists($model, '__toString')) {
            $asString = (string) $model;
            if ($asString !== '') {
                return $asString;
            }
        }

        // Final fallback: class short name with optional id
        $short = substr(strrchr('\\' . get_class($model), '\\') ?: '', 1) ?: 'Model';
        $id = self::resolveModelId($model);
        return $id !== null ? $short . ' #' . $id : $short;
    }

    /**
     * @return int|string|null
     */
    private static function resolveModelId(object $model): int|string|null
    {
        if ($model instanceof BaseActiveRecord) {
            /** @var mixed $pk */
            $pk = $model->getPrimaryKey();
            return is_scalar($pk) ? $pk : null;
        }

        if (isset($model->id) && (is_int($model->id) || is_string($model->id))) {
            return $model->id;
        }

        return null;
    }
}
