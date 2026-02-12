<?php

namespace app\widgets;

use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\data\DataProviderInterface;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * Renders a mobile-friendly card layout for data providers.
 *
 * Usage:
 * ```php
 * echo MobileCardView::widget([
 *     'dataProvider' => $dataProvider,
 *     'titleAttribute' => 'name',
 *     'metaAttributes' => ['project.name', 'type', 'updated_at'],
 *     'viewAction' => 'view',
 *     'actions' => ['update', 'delete'],
 * ]);
 * ```
 */
class MobileCardView extends Widget
{
    public DataProviderInterface $dataProvider;

    /**
     * Attribute name for the card title, or a callable.
     * Callable signature: function($model): string
     * @var string|callable
     */
    public $titleAttribute = 'name';

    /**
     * Attributes to show as meta info.
     * Can be attribute names or callables.
     * Callable signature: function($model): string
     * @var array
     */
    public array $metaAttributes = [];

    /**
     * Labels for meta attributes.
     * @var array
     */
    public array $metaLabels = [];

    /**
     * Action name for clicking the card (e.g., 'view').
     * Set to null to disable click navigation.
     */
    public ?string $viewAction = 'view';

    /**
     * Actions to show as buttons at the bottom of the card.
     * @var array
     */
    public array $actions = ['update', 'delete'];

    /**
     * Additional HTML options for the container.
     * @var array
     */
    public array $options = [];

    /**
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();
        if (!isset($this->dataProvider)) {
            throw new InvalidConfigException('The "dataProvider" property must be set.');
        }
    }

    public function run(): string
    {
        $models = $this->dataProvider->getModels();
        $options = array_merge(['class' => 'mobile-card-view'], $this->options);

        if (empty($models)) {
            $emptyContent = Html::tag('div', 'No items found.', ['class' => 'mobile-card-view__empty p-3 text-muted']);
            return Html::tag('div', $emptyContent, $options);
        }

        $cards = [];
        foreach ($models as $model) {
            $cards[] = $this->renderCard($model);
        }

        return Html::tag('div', implode("\n", $cards), $options);
    }

    protected function renderCard($model): string
    {
        $title = $this->getValue($model, $this->titleAttribute);
        $meta = $this->renderMeta($model);
        $actions = $this->renderActions($model);

        $cardOptions = ['class' => 'mobile-card-item'];

        if ($this->viewAction !== null) {
            $id = $this->getModelId($model);
            $url = Url::to([$this->viewAction, 'id' => $id]);
            $cardOptions['onclick'] = "window.location.href='" . $url . "';";
        }

        $content = Html::tag('div', Html::encode($title), ['class' => 'mobile-card-item__title']);
        $content .= Html::tag('div', $meta, ['class' => 'mobile-card-item__meta']);

        if (!empty($actions)) {
            $content .= Html::tag('div', $actions, ['class' => 'mobile-card-item__actions']);
        }

        return Html::tag('div', $content, $cardOptions);
    }

    protected function renderMeta($model): string
    {
        $parts = [];
        foreach ($this->metaAttributes as $key => $attribute) {
            $value = $this->getValue($model, $attribute);
            if ($value === null || $value === '') {
                continue;
            }

            $label = $this->metaLabels[$key] ?? (is_string($attribute) ? ucfirst(str_replace('_', ' ', $attribute)) : '');
            if ($label !== '') {
                $parts[] = Html::tag('span', Html::encode($label) . ': ' . Html::encode($value));
            } else {
                $parts[] = Html::tag('span', Html::encode($value));
            }
        }

        return implode('', $parts);
    }

    protected function renderActions($model): string
    {
        $id = $this->getModelId($model);
        $buttons = [];

        foreach ($this->actions as $action) {
            $url = Url::to([$action, 'id' => $id]);

            switch ($action) {
                case 'update':
                    $buttons[] = Html::a(
                        '<i class="bi bi-pencil"></i>',
                        $url,
                        [
                            'class' => 'btn btn-sm btn-outline-primary',
                            'title' => 'Update',
                            'onclick' => 'event.stopPropagation();',
                        ]
                    );
                    break;
                case 'delete':
                    $buttons[] = Html::a(
                        '<i class="bi bi-trash"></i>',
                        $url,
                        [
                            'class' => 'btn btn-sm btn-outline-danger',
                            'title' => 'Delete',
                            'onclick' => 'event.stopPropagation();',
                            'data' => [
                                'confirm' => 'Are you sure you want to delete this item?',
                                'method' => 'post',
                            ],
                        ]
                    );
                    break;
                case 'view':
                    $buttons[] = Html::a(
                        '<i class="bi bi-eye"></i>',
                        $url,
                        [
                            'class' => 'btn btn-sm btn-outline-secondary',
                            'title' => 'View',
                            'onclick' => 'event.stopPropagation();',
                        ]
                    );
                    break;
            }
        }

        return implode(' ', $buttons);
    }

    /**
     * @param mixed $model
     * @param string|callable $attribute
     * @return mixed
     */
    protected function getValue($model, $attribute): mixed
    {
        if (is_callable($attribute)) {
            return $attribute($model);
        }

        // Handle nested attributes like 'project.name'
        if (str_contains($attribute, '.')) {
            $parts = explode('.', $attribute);
            $value = $model;
            foreach ($parts as $part) {
                if (is_object($value) && isset($value->$part)) {
                    $value = $value->$part;
                } elseif (is_array($value) && isset($value[$part])) {
                    $value = $value[$part];
                } else {
                    return null;
                }
            }
            return $value;
        }

        if (is_object($model)) {
            return $model->$attribute ?? null;
        }

        return $model[$attribute] ?? null;
    }

    /**
     * @param mixed $model
     */
    protected function getModelId($model): mixed
    {
        if (is_object($model)) {
            return $model->id ?? $model->getPrimaryKey();
        }
        return $model['id'] ?? null;
    }
}
