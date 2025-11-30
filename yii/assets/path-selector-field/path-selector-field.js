/**
 * PathSelectorField - Handles file path selection with preview functionality
 *
 * Manages the interaction between:
 * - Path selector widget for browsing and selecting file paths
 * - Preview widget for displaying file contents with syntax highlighting
 * - Modal dialogs for both selection and preview
 */
(function() {
    'use strict';

    /**
     * Language mapping for syntax highlighting
     * Maps file extensions to [highlightjs-language, display-label] pairs
     */
    const LANGUAGE_MAP = {
        'php': ['php', 'PHP'],
        'js': ['javascript', 'JavaScript'],
        'ts': ['typescript', 'TypeScript'],
        'json': ['json', 'JSON'],
        'css': ['css', 'CSS'],
        'html': ['xml', 'HTML'],
        'htm': ['xml', 'HTML'],
        'xml': ['xml', 'XML'],
        'md': ['markdown', 'Markdown'],
        'yaml': ['yaml', 'YAML'],
        'yml': ['yaml', 'YAML'],
        'sh': ['bash', 'Shell'],
        'bash': ['bash', 'Shell'],
        'zsh': ['bash', 'Shell'],
        'py': ['python', 'Python']
    };

    /**
     * Prune a file path to just the first directory component
     * Example: "foo/bar/baz.txt" => "foo/"
     */
    function pruneToFirstDirectory(path) {
        if (!path) return '';
        const parts = path.split('/');
        return parts.length > 0 ? parts[0] + '/' : '';
    }

    /**
     * Resolve language information from file path
     * Returns [language, languageLabel] array
     */
    function resolveLanguage(path) {
        const extension = path.split('.').pop().toLowerCase();
        return LANGUAGE_MAP[extension] || ['plaintext', 'Plain Text'];
    }

    /**
     * Escape HTML special characters for safe insertion into DOM
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Get path selector widget instance by ID
     */
    function getPathSelector(pathSelectorId) {
        return window.pathSelectorWidgets && window.pathSelectorWidgets[pathSelectorId];
    }

    /**
     * Ensure the shared preview modal exists in the DOM
     * Creates it if it doesn't exist yet
     */
    function ensurePreviewModalExists() {
        const modalId = 'path-preview-modal';
        if (document.getElementById(modalId)) {
            return true;
        }

        const modalHtml = '<div class="modal fade" id="' + modalId + '" tabindex="-1" aria-hidden="true">' +
            '<div class="modal-dialog modal-xl modal-dialog-scrollable">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title mb-0 flex-grow-1 text-truncate">' +
            '<span id="path-preview-title">File Preview</span>' +
            '<span id="path-preview-language" class="badge text-bg-secondary ms-2 d-none"></span>' +
            '</h5>' +
            '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<pre class="mb-0 border-0"><code id="path-preview-content" class="font-monospace"></code></pre>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        return true;
    }

    /**
     * Apply syntax highlighting tweaks for PHP inspection suppressions
     * Highlights "PhpUnused" and similar inspection names within PHPDoc comments
     */
    function tweakPhpSuppressions(root) {
        if (!root) return;

        root.querySelectorAll('.hljs-comment').forEach(function(commentEl) {
            if (!commentEl.querySelector('.hljs-doctag')) return;

            var walker = document.createTreeWalker(commentEl, NodeFilter.SHOW_TEXT, null);
            var textNodes = [];
            while (walker.nextNode()) {
                textNodes.push(walker.currentNode);
            }

            textNodes.forEach(function(node) {
                var text = node.nodeValue;
                var target = 'PhpUnused';
                var index = text.indexOf(target);

                if (index === -1) return;

                var before = text.slice(0, index);
                var after = text.slice(index + target.length);

                var span = document.createElement('span');
                span.className = 'hljs-inspection-name';
                span.textContent = target;

                var parent = node.parentNode;
                if (!parent) return;

                if (before) {
                    parent.insertBefore(document.createTextNode(before), node);
                }
                parent.insertBefore(span, node);
                if (after) {
                    parent.insertBefore(document.createTextNode(after), node);
                }

                parent.removeChild(node);
            });
        });
    }

    /**
     * Attach click handler to preview button
     * Fetches and displays file contents in modal with syntax highlighting
     */
    function attachPreviewHandler(button) {
        const modalId = 'path-preview-modal';
        const titleId = 'path-preview-title';
        const languageId = 'path-preview-language';
        const contentId = 'path-preview-content';

        if (!ensurePreviewModalExists() || !window.bootstrap) return;

        const modalElement = document.getElementById(modalId);
        if (!modalElement) return;

        const modalBody = document.getElementById(contentId);
        const modalTitle = document.getElementById(titleId);
        const languageBadge = document.getElementById(languageId);
        const previewModal = new bootstrap.Modal(modalElement);

        button.addEventListener('click', function() {
            const language = button.getAttribute('data-language') || 'plaintext';
            const languageLabel = button.getAttribute('data-language-label') || language.toUpperCase();
            const previewUrl = button.getAttribute('data-url');

            if (modalTitle) {
                modalTitle.textContent = button.getAttribute('data-path-label') || 'File Preview';
            }

            if (languageBadge) {
                languageBadge.textContent = '';
                languageBadge.classList.add('d-none');
            }

            if (modalBody) {
                modalBody.className = 'font-monospace hljs text-light language-' + language;
                modalBody.textContent = 'Loading...';
            }

            previewModal.show();

            if (!previewUrl) return;

            fetch(previewUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (!data.success) {
                        if (modalBody) modalBody.textContent = 'Preview error: ' + (data.message || 'Unable to load preview.');
                        return;
                    }
                    if (languageBadge && languageLabel) {
                        languageBadge.textContent = languageLabel;
                        languageBadge.classList.remove('d-none');
                    }
                    if (modalBody) {
                        modalBody.textContent = data.preview;
                        if (window.hljs) {
                            window.hljs.highlightElement(modalBody);
                            tweakPhpSuppressions(modalBody);
                        }
                    }
                })
                .catch(function(error) {
                    console.error('Path preview failed:', error);
                    if (modalBody) modalBody.textContent = 'Preview error: Unable to load preview.';
                });
        });
    }

    /**
     * Update the path preview display with new path
     * Creates clickable link with preview functionality
     */
    function updatePathPreview(path, pathPreviewWrapper, basePreviewUrl, fieldId) {
        if (!pathPreviewWrapper) return;

        if (!path) {
            pathPreviewWrapper.innerHTML = '<div class="path-preview-widget"><span class="font-monospace text-break"></span></div>';
            return;
        }

        const previewUrl = basePreviewUrl + '&path=' + encodeURIComponent(path);
        const pathLabel = escapeHtml(path);
        const [language, languageLabel] = resolveLanguage(path);

        const buttonId = 'path-preview-button-' + fieldId + '-' + Date.now();
        const buttonHtml = '<button id="' + buttonId + '" type="button" class="btn btn-link p-0 text-decoration-underline path-preview font-monospace" ' +
            'data-url="' + escapeHtml(previewUrl) + '" ' +
            'data-language="' + escapeHtml(language) + '" ' +
            'data-language-label="' + escapeHtml(languageLabel) + '" ' +
            'data-path-label="' + pathLabel + '">' +
            pathLabel +
            '</button>';

        pathPreviewWrapper.innerHTML = '<div class="path-preview-widget">' + buttonHtml + '</div>';

        const button = document.getElementById(buttonId);
        if (button) {
            attachPreviewHandler(button);
        }
    }

    /**
     * Initialize a path selector field instance
     * Sets up modal interactions and path selection handlers
     *
     * @param {Object} config - Configuration object with:
     *   - modalId: ID of the selection modal
     *   - pathSelectorId: ID of the path selector widget
     *   - saveButtonId: ID of the save button
     *   - hiddenInputId: ID of the hidden input storing the path
     *   - pathPreviewWrapperId: ID of the preview display wrapper
     *   - projectId: Current project ID
     *   - fieldType: Type of field ('file')
     *   - fieldId: Field ID
     *   - basePreviewUrl: Base URL for preview endpoint
     */
    function init(config) {
        const modalElement = document.getElementById(config.modalId);
        const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
        const saveBtn = document.getElementById(config.saveButtonId);
        const hiddenInput = document.getElementById(config.hiddenInputId);
        const pathPreviewWrapper = document.getElementById(config.pathPreviewWrapperId);

        // Set up modal show event to load path selector
        if (modalElement) {
            modalElement.addEventListener('show.bs.modal', function() {
                const pathSelector = getPathSelector(config.pathSelectorId);
                if (pathSelector && config.projectId) {
                    const currentPath = hiddenInput ? hiddenInput.value : '';
                    const prunedPath = pruneToFirstDirectory(currentPath);

                    pathSelector.load(config.fieldType, config.projectId);

                    // Wait for path selector to finish loading before rendering
                    // TODO: Replace timeout with proper callback/event from pathSelector.load()
                    setTimeout(function() {
                        if (prunedPath) {
                            pathSelector.render(prunedPath);
                        }
                    }, 100);
                }
            });
        }

        // Set up save button to sync path and update preview
        if (saveBtn && hiddenInput && pathPreviewWrapper) {
            saveBtn.addEventListener('click', function() {
                const pathSelector = getPathSelector(config.pathSelectorId);
                if (pathSelector) {
                    pathSelector.sync();
                }
                const newPath = hiddenInput.value;

                updatePathPreview(newPath, pathPreviewWrapper, config.basePreviewUrl, config.fieldId);

                if (modal) {
                    modal.hide();
                }
            });
        }
    }

    // Export to global scope
    window.PathSelectorField = {
        init: init,
        updatePathPreview: updatePathPreview,
        resolveLanguage: resolveLanguage,
    };
})();
