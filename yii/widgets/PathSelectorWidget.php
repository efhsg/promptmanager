<?php
/** @noinspection JSPrimitiveTypeWrapperUsage */

namespace app\widgets;

use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;

/**
 * Path selector widget with autocomplete functionality.
 * Fetches paths from a project via AJAX and syncs with a hidden input.
 */
class PathSelectorWidget extends Widget
{
    public ?string $initialValue = null;
    public string $pathListUrl = '';
    public ?string $projectRootDirectory = null;
    public string $hiddenContentInputId = 'field-content';
    public array $wrapperOptions = [];
    public string $labelText = 'Path';
    public string $placeholderText = 'Start typing to search paths';
    public string $loadingText = 'Loading paths...';
    public string $noProjectText = 'Select a project to browse paths.';
    public string $noPathsText = 'No paths available.';
    public string $noMatchText = 'No paths match current input.';
    public string $notFoundText = 'Path not found in project.';
    public string $errorText = 'Unable to load paths.';

    /**
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();
        if (empty($this->pathListUrl)) {
            throw new InvalidConfigException('The "pathListUrl" property must be set.');
        }
    }

    public function run(): string
    {
        $baseId = $this->getId();
        $wrapperId = $baseId . '-wrapper';
        $inputId = $baseId . '-input';
        $suggestionsId = $baseId . '-suggestions';
        $statusId = $baseId . '-status';
        $rootLabelId = $baseId . '-root';

        $wrapperOptions = array_merge([
            'id' => $wrapperId,
            'class' => 'mb-3',
            'style' => 'display: none;',
        ], $this->wrapperOptions);

        $html = Html::beginTag('div', $wrapperOptions);
        $html .= Html::label($this->labelText, $inputId, ['class' => 'form-label']);
        $html .= Html::input('text', null, '', [
            'class' => 'form-control',
            'id' => $inputId,
            'placeholder' => $this->placeholderText,
            'disabled' => true,
            'autocomplete' => 'off',
        ]);
        $html .= Html::beginTag('div', ['class' => 'position-relative mt-1']);
        $html .= Html::tag('div', '', [
            'id' => $suggestionsId,
            'class' => 'list-group shadow-sm d-none position-absolute w-100',
        ]);
        $html .= Html::endTag('div');
        $html .= Html::beginTag('div', ['class' => 'form-text']);
        $html .= Html::tag('span', 'Root:');
        $html .= ' ';
        $html .= Html::tag('span', Html::encode($this->projectRootDirectory ?? ''), ['id' => $rootLabelId]);
        $html .= Html::tag('span', '', ['id' => $statusId, 'class' => 'ms-2 text-danger']);
        $html .= Html::endTag('div');
        $html .= Html::endTag('div');

        $this->registerScript($baseId, $inputId, $suggestionsId, $statusId, $rootLabelId, $wrapperId);

        return $html;
    }

    private function registerScript(
        string $baseId,
        string $inputId,
        string $suggestionsId,
        string $statusId,
        string $rootLabelId,
        string $wrapperId
    ): void {
        $baseIdJson = Json::encode($baseId);
        $inputIdJson = Json::encode($inputId);
        $suggestionsIdJson = Json::encode($suggestionsId);
        $statusIdJson = Json::encode($statusId);
        $rootLabelIdJson = Json::encode($rootLabelId);
        $wrapperIdJson = Json::encode($wrapperId);
        $pathListUrl = Json::encode($this->pathListUrl);
        $hiddenContentInputId = Json::encode($this->hiddenContentInputId);
        $initialValue = Json::encode($this->initialValue);
        $placeholderText = Json::encode($this->placeholderText);
        $loadingText = Json::encode($this->loadingText);
        $noProjectText = Json::encode($this->noProjectText);
        $noPathsText = Json::encode($this->noPathsText);
        $noMatchText = Json::encode($this->noMatchText);
        $notFoundText = Json::encode($this->notFoundText);
        $errorText = Json::encode($this->errorText);

        $script = <<<JS
/* eslint-disable */
// noinspection JSAnnotator

(function() {
    const pathInput = document.getElementById({$inputIdJson});
    const pathSuggestions = document.getElementById({$suggestionsIdJson});
    const pathStatus = document.getElementById({$statusIdJson});
    const pathRootLabel = document.getElementById({$rootLabelIdJson});
    const hiddenContentInput = document.getElementById({$hiddenContentInputId});
    const pathListUrl = {$pathListUrl};
    let availablePaths = [];
    let initialPathValue = {$initialValue};

    const messages = {
        placeholder: {$placeholderText},
        loading: {$loadingText},
        noProject: {$noProjectText},
        noPaths: {$noPathsText},
        noMatch: {$noMatchText},
        notFound: {$notFoundText},
        error: {$errorText}
    };

    function syncHiddenContentFromPath() {
        if (hiddenContentInput && pathInput) {
            hiddenContentInput.value = pathInput.value || '';
        }
    }

    function resetPathWidget(placeholder = messages.placeholder, clearHiddenContent = true) {
        availablePaths = [];
        if (pathInput) {
            pathInput.value = '';
            pathInput.placeholder = placeholder;
            pathInput.disabled = true;
        }
        if (pathSuggestions) {
            pathSuggestions.innerHTML = '';
            pathSuggestions.classList.add('d-none');
        }
        if (hiddenContentInput && clearHiddenContent) {
            hiddenContentInput.value = '';
        }
    }

    function handlePathError(message) {
        if (pathStatus) {
            pathStatus.textContent = message;
        }
        resetPathWidget(messages.error, false);
    }

    function renderPathOptions(forceValue = null) {
        if (!pathInput || !pathSuggestions) {
            return;
        }

        if (forceValue !== null) {
            pathInput.value = forceValue;
        }

        const filterTerm = pathInput.value.trim().toLowerCase();
        const filteredPaths = filterTerm === ''
            ? availablePaths
            : availablePaths.filter((path) => path.toLowerCase().includes(filterTerm));

        const currentValue = pathInput.value;
        pathSuggestions.innerHTML = '';
        if (filteredPaths.length === 1 && currentValue === filteredPaths[0]) {
            pathSuggestions.classList.add('d-none');
            pathSuggestions.innerHTML = '';
        } else {
            filteredPaths.forEach((path) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'list-group-item list-group-item-action';
                option.textContent = path;
                option.dataset.value = path;
                if (currentValue === path) {
                    option.classList.add('active');
                }
                option.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    pathInput.value = path;
                    renderPathOptions();
                });
                pathSuggestions.appendChild(option);
            });

            if (filteredPaths.length === 0) {
                pathSuggestions.classList.add('d-none');
            } else {
                pathSuggestions.classList.remove('d-none');
            }
        }

        if (pathStatus) {
            if (availablePaths.length === 0) {
                pathStatus.textContent = messages.noPaths;
            } else if (filteredPaths.length === 0 && filterTerm !== '') {
                pathStatus.textContent = messages.noMatch;
            } else if (currentValue && !availablePaths.includes(currentValue)) {
                pathStatus.textContent = messages.notFound;
            } else {
                pathStatus.textContent = '';
            }
        }

        syncHiddenContentFromPath();
    }

    async function loadPathOptions(fieldType, projectId) {
        if (!pathInput) {
            return;
        }

        if (!projectId) {
            if (pathStatus) {
                pathStatus.textContent = messages.noProject;
            }
            if (pathRootLabel) {
                pathRootLabel.textContent = '';
            }
            resetPathWidget(messages.placeholder);
            return;
        }

        if (pathStatus) {
            pathStatus.textContent = 'Loading...';
        }
        if (pathInput) {
            pathInput.disabled = true;
            pathInput.placeholder = messages.loading;
        }
        if (pathSuggestions) {
            pathSuggestions.innerHTML = '';
            pathSuggestions.classList.add('d-none');
        }

        try {
            const response = await fetch(`\${pathListUrl}?projectId=\${projectId}&type=\${fieldType}`, {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });

            if (!response.ok) {
                handlePathError(messages.error);
                return;
            }

            const data = await response.json();
            if (!data.success) {
                handlePathError(data.message || messages.error);
                return;
            }

            availablePaths = Array.isArray(data.paths) ? data.paths : [];
            if (pathRootLabel) {
                pathRootLabel.textContent = data.root || '';
            }

            if (pathInput) {
                pathInput.disabled = false;
                pathInput.placeholder = messages.placeholder;
            }

            const targetValue = (hiddenContentInput ? hiddenContentInput.value : '') || initialPathValue || '';
            renderPathOptions(targetValue);
            initialPathValue = null;
        } catch (error) {
            handlePathError(error.message);
        }
    }

    if (pathInput) {
        pathInput.addEventListener('input', () => {
            renderPathOptions();
        });
        pathInput.addEventListener('focus', () => {
            if (pathSuggestions && pathSuggestions.children.length > 0) {
                pathSuggestions.classList.remove('d-none');
            }
        });
        pathInput.addEventListener('blur', () => {
            if (pathSuggestions) {
                setTimeout(() => pathSuggestions.classList.add('d-none'), 100);
            }
        });
    }

    window.pathSelectorWidgets = window.pathSelectorWidgets || {};
    window.pathSelectorWidgets[{$baseIdJson}] = {
        load: loadPathOptions,
        reset: resetPathWidget,
        render: renderPathOptions,
        sync: syncHiddenContentFromPath
    };

    if (!window.pathSelectorWidget) {
        window.pathSelectorWidget = window.pathSelectorWidgets[{$baseIdJson}];
    }
})();
/* eslint-enable */
JS;

        $this->view->registerJs($script);
    }
}
