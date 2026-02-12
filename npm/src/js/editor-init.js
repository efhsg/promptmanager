window.QuillEditors = window.QuillEditors || {};

// Shared Quill toolbar utilities
window.QuillToolbar = (function() {
    const SPINNER_SVG = '<svg viewBox="0 0 18 18" width="18" height="18" class="ql-spin"><circle cx="9" cy="9" r="6" fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="20 10"/></svg>';

    const SMART_PASTE_SVG = '<svg viewBox="0 0 18 18" width="18" height="18">' +
        '<rect x="3" y="2" width="12" height="14" rx="1" fill="none" stroke="currentColor" stroke-width="1"/>' +
        '<rect x="6" y="0" width="6" height="3" rx="0.5" fill="none" stroke="currentColor" stroke-width="1"/>' +
        '<text x="9" y="13" text-anchor="middle" font-size="9" font-weight="bold" font-family="sans-serif" fill="currentColor">P</text>' +
        '</svg>';

    const CLEAR_EDITOR_SVG = '<svg viewBox="0 0 18 18" width="18" height="18">' +
        '<path d="M3 5h12M7 5V3h4v2M5 5v9a1 1 0 001 1h6a1 1 0 001-1V5" fill="none" stroke="currentColor" stroke-width="1.2"/>' +
        '<line x1="8" y1="8" x2="8" y2="12" stroke="currentColor" stroke-width="1"/>' +
        '<line x1="10" y1="8" x2="10" y2="12" stroke="currentColor" stroke-width="1"/>' +
        '</svg>';

    const UNDO_SVG = '<svg viewBox="0 0 18 18" width="18" height="18">' +
        '<path d="M4 7h8a3 3 0 010 6H9" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>' +
        '<path d="M7 4L4 7l3 3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>' +
        '</svg>';

    const LOAD_MD_SVG = '<svg viewBox="0 0 18 18" width="18" height="18">' +
        '<path d="M4 2h7l4 4v10a1 1 0 01-1 1H4a1 1 0 01-1-1V3a1 1 0 011-1z" fill="none" stroke="currentColor" stroke-width="1"/>' +
        '<path d="M9 7v6M7 11l2 2 2-2" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>' +
        '</svg>';

    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.content || meta.getAttribute('content')) : '';
    };

    const showToast = (message, type) => {
        let container = document.getElementById('quill-toolbar-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'quill-toolbar-toast-container';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.innerHTML = '<div class="toast align-items-center border-0" role="alert">' +
                '<div class="d-flex"><div class="toast-body"></div>' +
                '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
                '</div></div>';
            document.body.appendChild(container);
        }
        const toastEl = container.querySelector('.toast');
        toastEl.querySelector('.toast-body').textContent = message;
        toastEl.className = 'toast align-items-center border-0 text-bg-' + (type || 'secondary');
        if (window.bootstrap && window.bootstrap.Toast) {
            new bootstrap.Toast(toastEl, { delay: 2000 }).show();
        }
    };

    const ensureSpinnerCss = () => {
        if (!document.getElementById('ql-spin-style')) {
            const style = document.createElement('style');
            style.id = 'ql-spin-style';
            style.textContent = '.ql-spin { animation: ql-spin 1s linear infinite; } @keyframes ql-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
            document.head.appendChild(style);
        }
    };

    const DEFAULT_IMPORT_TEXT_URL = '/note/import-text';
    const DEFAULT_IMPORT_MARKDOWN_URL = '/note/import-markdown';

    const setupClearEditor = (quill, hidden) => {
        const toolbar = quill.getModule('toolbar');
        if (!toolbar || !toolbar.container) return;

        const el = toolbar.container.querySelector('.ql-clearEditor');
        if (!el) return;

        let btn;
        if (el.tagName === 'BUTTON') {
            btn = el;
        } else {
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ql-clearEditor';
            btn.title = 'Clear editor content';
            btn.innerHTML = CLEAR_EDITOR_SVG;
            el.replaceWith(btn);
        }

        const syncHidden = () => {
            if (hidden) hidden.value = JSON.stringify(quill.getContents());
        };

        let cleared = false;

        const setTrashMode = () => {
            cleared = false;
            btn.innerHTML = CLEAR_EDITOR_SVG;
            btn.title = 'Clear editor content';
        };

        const setUndoMode = () => {
            cleared = true;
            btn.innerHTML = UNDO_SVG;
            btn.title = 'Undo clear';
        };

        btn.addEventListener('click', () => {
            if (cleared) {
                quill.history.undo();
                syncHidden();
                setTrashMode();
                return;
            }

            const len = quill.getLength();
            if (len <= 1) return;

            quill.deleteText(0, len - 1);
            syncHidden();
            setUndoMode();
        });

        quill.on('text-change', (delta, oldDelta, source) => {
            if (source === 'user' && cleared) setTrashMode();
        });
    };

    const setupSmartPaste = (quill, hidden, config) => {
        const toolbar = quill.getModule('toolbar');
        if (!toolbar || !toolbar.container) return;

        const el = toolbar.container.querySelector('.ql-smartPaste');
        if (!el) return;

        const importUrl = (config && config.importTextUrl) || DEFAULT_IMPORT_TEXT_URL;

        ensureSpinnerCss();

        let btn;
        if (el.tagName === 'BUTTON') {
            btn = el;
        } else {
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ql-smartPaste';
            btn.title = 'Smart Paste (auto-detects markdown)';
            btn.innerHTML = SMART_PASTE_SVG;
            el.replaceWith(btn);
        }

        btn.addEventListener('click', async () => {
            const originalHtml = btn.innerHTML;
            try {
                const text = await navigator.clipboard.readText();
                if (!text.trim()) {
                    showToast('Clipboard is empty', 'warning');
                    return;
                }

                btn.innerHTML = SPINNER_SVG;
                btn.disabled = true;

                const response = await fetch(importUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ text: text })
                });

                const data = await response.json();
                if (data.success && data.importData && data.importData.content) {
                    const delta = typeof data.importData.content === 'string'
                        ? JSON.parse(data.importData.content)
                        : data.importData.content;

                    const Delta = Quill.import('delta');
                    const length = quill.getLength();

                    if (length <= 1) {
                        quill.setContents(delta);
                    } else {
                        const range = quill.getSelection(true);
                        quill.updateContents(new Delta().retain(range.index).concat(delta));
                    }

                    if (hidden) {
                        hidden.value = JSON.stringify(quill.getContents());
                    }
                    showToast(data.format === 'md' ? 'Pasted as markdown' : 'Pasted as text', 'success');
                } else {
                    showToast(data.message || 'Failed to paste content', 'danger');
                }
            } catch (err) {
                console.error('SmartPaste error:', err);
                showToast('Unable to read clipboard', 'danger');
            } finally {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        });
    };

    const setupLoadMd = (quill, hidden, config) => {
        const toolbar = quill.getModule('toolbar');
        if (!toolbar || !toolbar.container) return;

        const el = toolbar.container.querySelector('.ql-loadMd');
        if (!el) return;

        const importUrl = (config && config.importMarkdownUrl) || DEFAULT_IMPORT_MARKDOWN_URL;

        ensureSpinnerCss();

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = '.md,.markdown,.txt';
        fileInput.style.display = 'none';
        document.body.appendChild(fileInput);

        let btn;
        if (el.tagName === 'BUTTON') {
            btn = el;
        } else {
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ql-loadMd';
            btn.title = 'Load markdown file';
            btn.innerHTML = LOAD_MD_SVG;
            el.replaceWith(btn);
        }

        btn.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', async function() {
            const file = this.files[0];
            if (!file) return;

            const originalHtml = btn.innerHTML;
            btn.innerHTML = SPINNER_SVG;
            btn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('mdFile', file);

                const response = await fetch(importUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-Token': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                if (data.success && data.importData && data.importData.content) {
                    const delta = typeof data.importData.content === 'string'
                        ? JSON.parse(data.importData.content)
                        : data.importData.content;

                    const Delta = Quill.import('delta');
                    const length = quill.getLength();

                    if (length <= 1) {
                        quill.setContents(delta);
                    } else {
                        const range = quill.getSelection(true);
                        quill.updateContents(new Delta().retain(range.index).concat(delta));
                    }

                    if (hidden) {
                        hidden.value = JSON.stringify(quill.getContents());
                    }
                    showToast('Loaded ' + file.name, 'success');
                } else {
                    showToast(data.errors?.mdFile?.[0] || data.message || 'Failed to load file', 'danger');
                }
            } catch (err) {
                console.error('Load MD error:', err);
                showToast('Failed to load file', 'danger');
            } finally {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                fileInput.value = '';
            }
        });
    };

    const copyToClipboard = (text) => {
        if (navigator.clipboard && navigator.clipboard.writeText)
            return navigator.clipboard.writeText(text);
        return new Promise((resolve, reject) => {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy') ? resolve() : reject(new Error('execCommand failed'));
            } catch (err) {
                reject(err);
            }
            document.body.removeChild(textarea);
        });
    };

    const DEFAULT_CONVERT_FORMAT_URL = '/note/convert-format';

    /**
     * Copy content with format conversion
     * @param {string} deltaContent - The Quill delta JSON string
     * @param {string} format - The target format (e.g., 'md', 'text')
     * @param {HTMLElement} button - The button element to show feedback on
     * @param {string} convertUrl - Optional custom convert URL
     */
    const copyWithFormat = async (deltaContent, format, button, convertUrl) => {
        const url = convertUrl || DEFAULT_CONVERT_FORMAT_URL;
        const originalHtml = button.innerHTML;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    content: deltaContent,
                    format: format
                })
            });

            const data = await response.json();
            if (data.success && data.content !== undefined) {
                await copyToClipboard(data.content);
                button.innerHTML = '<i class="bi bi-check"></i> Copied';
                setTimeout(() => {
                    button.innerHTML = originalHtml;
                }, 1000);
            } else {
                console.error('Failed to convert format:', data.message);
                showToast('Failed to copy', 'danger');
            }
        } catch (err) {
            console.error('Failed to copy:', err);
            showToast('Failed to copy', 'danger');
        }
    };

    /**
     * Setup a copy button with format selector
     * @param {string} buttonId - The copy button element ID
     * @param {string} formatSelectId - The format dropdown element ID
     * @param {Function|string} contentProvider - Function returning delta JSON, or static delta JSON string
     * @param {string} convertUrl - Optional custom convert URL
     */
    const setupCopyButton = (buttonId, formatSelectId, contentProvider, convertUrl) => {
        const button = document.getElementById(buttonId);
        const formatSelect = document.getElementById(formatSelectId);

        if (!button || !formatSelect) return;

        button.addEventListener('click', () => {
            const format = formatSelect.value;
            const deltaContent = typeof contentProvider === 'function'
                ? contentProvider()
                : contentProvider;
            copyWithFormat(deltaContent, format, button, convertUrl);
        });
    };

    return {
        setupClearEditor: setupClearEditor,
        setupSmartPaste: setupSmartPaste,
        setupLoadMd: setupLoadMd,
        showToast: showToast,
        copyWithFormat: copyWithFormat,
        setupCopyButton: setupCopyButton
    };
})();

// ── Fixed toolbar on page scroll ──
// Defined outside DOMContentLoaded so inline Quill inits (registerJs) can call
// it without a race condition between DOMContentLoaded and jQuery ready.
const NAVBAR_HEIGHT = 56;

const setupFixedToolbar = container => {
    if (container.closest('.claude-prompt-card-sticky')) return;

    const toolbar = container.querySelector('.ql-toolbar');
    if (!toolbar) return;

    let spacer = null;
    let fixed = false;

    const apply = () => {
        const cRect = container.getBoundingClientRect();
        const shouldFix = cRect.top < NAVBAR_HEIGHT && cRect.bottom > NAVBAR_HEIGHT + toolbar.offsetHeight;

        if (shouldFix && !fixed) {
            fixed = true;
            spacer = document.createElement('div');
            spacer.style.height = toolbar.offsetHeight + 'px';
            toolbar.parentNode.insertBefore(spacer, toolbar);
            toolbar.classList.add('ql-toolbar-fixed');
            toolbar.style.left = cRect.left + 'px';
            toolbar.style.width = cRect.width + 'px';
        } else if (shouldFix && fixed) {
            toolbar.style.left = cRect.left + 'px';
            toolbar.style.width = cRect.width + 'px';
        } else if (!shouldFix && fixed) {
            fixed = false;
            toolbar.classList.remove('ql-toolbar-fixed');
            toolbar.style.left = '';
            toolbar.style.width = '';
            if (spacer) {
                spacer.remove();
                spacer = null;
            }
        }
    };

    window.addEventListener('scroll', apply, { passive: true });
    window.addEventListener('resize', apply, { passive: true });
};

// Expose immediately for inline-initialized editors (prompt-template, note, etc.)
window.QuillToolbar.setupFixedToolbar = setupFixedToolbar;

document.addEventListener('DOMContentLoaded', () => {
    const init = node => {
        if (node.dataset.inited) return;          // idempotent
        node.dataset.inited = 1;

        const hidden = document.getElementById(node.dataset.target);
        const cfg    = JSON.parse(node.dataset.config);

        // Register no-op handlers for custom toolbar buttons so Quill
        // doesn't warn about nonexistent formats during init.
        const noopHandlers = { clearEditor: function() {}, smartPaste: function() {}, loadMd: function() {} };
        if (cfg.modules && cfg.modules.toolbar) {
            if (Array.isArray(cfg.modules.toolbar)) {
                cfg.modules.toolbar = { container: cfg.modules.toolbar, handlers: noopHandlers };
            } else if (typeof cfg.modules.toolbar === 'object' && cfg.modules.toolbar !== null) {
                cfg.modules.toolbar.handlers = Object.assign(noopHandlers, cfg.modules.toolbar.handlers || {});
            }
        }

        const quill  = new Quill(node, cfg);      // Quill global is present

        // Register in global registry for external access
        if (hidden && hidden.id) {
            window.QuillEditors[hidden.id] = quill;
        }

        // Setup toolbar buttons if configured (read URLs from data attributes)
        const urlConfig = {
            importTextUrl: node.dataset.importTextUrl,
            importMarkdownUrl: node.dataset.importMarkdownUrl
        };
        window.QuillToolbar.setupClearEditor(quill, hidden);
        window.QuillToolbar.setupSmartPaste(quill, hidden, urlConfig);
        window.QuillToolbar.setupLoadMd(quill, hidden, urlConfig);

        if (hidden.value) {
            try { quill.setContents(JSON.parse(hidden.value)); }
            catch (e) { console.warn('Delta parse', e); }
        }

        quill.on('text-change', () =>
            hidden.value = JSON.stringify(quill.getContents())
        );
    };

    // 1️⃣ initial pass
    document.querySelectorAll('[data-editor="quill"]').forEach(init);

    // 2️⃣ setup fixed toolbars for all containers
    document.querySelectorAll('.resizable-editor-container').forEach(setupFixedToolbar);

    // 3️⃣ future nodes (AJAX, modal, cloneRow...)
    new MutationObserver(muts =>
        muts.forEach(m =>
            m.addedNodes.forEach(n => {
                n.querySelectorAll?.('[data-editor="quill"]').forEach(init);
                n.querySelectorAll?.('.resizable-editor-container').forEach(setupFixedToolbar);
            })
        )
    ).observe(document.body, {childList:true, subtree:true});
});
