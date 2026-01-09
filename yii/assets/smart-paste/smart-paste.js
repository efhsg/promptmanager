/**
 * SmartPaste - Clipboard-to-Quill paste with markdown detection
 *
 * Reads clipboard text, sends to backend for markdown parsing,
 * and inserts the resulting Quill Delta at cursor position.
 */
(function() {
    'use strict';

    const IMPORT_URL = '/scratch-pad/import-text';

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function showToast(message, type) {
        const toastContainer = document.getElementById('smart-paste-toast-container');
        if (!toastContainer || !window.bootstrap) return;

        const toastEl = toastContainer.querySelector('.toast');
        if (!toastEl) return;

        toastEl.querySelector('.toast-body').textContent = message;
        toastEl.className = 'toast align-items-center border-0 text-bg-' + (type || 'secondary');

        const toast = new bootstrap.Toast(toastEl, { delay: 2000 });
        toast.show();
    }

    function ensureToastContainer() {
        if (document.getElementById('smart-paste-toast-container')) return;

        const html = '<div id="smart-paste-toast-container" class="toast-container position-fixed bottom-0 end-0 p-3">' +
            '<div class="toast align-items-center text-bg-secondary border-0" role="alert" aria-live="assertive" aria-atomic="true">' +
            '<div class="d-flex">' +
            '<div class="toast-body"></div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
            '</div></div></div>';

        document.body.insertAdjacentHTML('beforeend', html);
    }

    async function handlePaste(button, hiddenInputId) {
        const quill = window.QuillEditors && window.QuillEditors[hiddenInputId];
        if (!quill) {
            console.error('SmartPaste: Quill instance not found for', hiddenInputId);
            return;
        }

        const originalHtml = button.innerHTML;

        try {
            const text = await navigator.clipboard.readText();
            if (!text.trim()) {
                showToast('Clipboard is empty', 'warning');
                return;
            }

            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
            button.disabled = true;

            const response = await fetch(IMPORT_URL, {
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

                const hidden = document.getElementById(hiddenInputId);
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
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
    }

    function init(config) {
        ensureToastContainer();

        const button = document.getElementById(config.buttonId);
        if (!button) return;

        button.addEventListener('click', function() {
            handlePaste(this, config.hiddenInputId);
        });
    }

    window.SmartPaste = {
        init: init,
        showToast: showToast
    };
})();
