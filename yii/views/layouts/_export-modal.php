<?php

use common\enums\CopyType;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$formatOptions = CopyType::labels();
$convertFormatUrl = Url::to(['/note/convert-format']);
$exportToFileUrl = Url::to(['/export/to-file']);
$pathListUrl = Url::to(['/field/path-list']);
$suggestNameUrl = Url::to(['/claude/suggest-name']);
?>

<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Content</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="export-error-alert"></div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Destination</label>
                    <div class="btn-group w-100" role="group" aria-label="Export destination">
                        <input type="radio" class="btn-check" name="export-destination" id="export-dest-clipboard" value="clipboard" checked>
                        <label class="btn btn-outline-primary" for="export-dest-clipboard">
                            <i class="bi bi-clipboard"></i> Clipboard
                        </label>
                        <input type="radio" class="btn-check" name="export-destination" id="export-dest-download" value="download">
                        <label class="btn btn-outline-primary" for="export-dest-download">
                            <i class="bi bi-download"></i> Download
                        </label>
                        <input type="radio" class="btn-check" name="export-destination" id="export-dest-file" value="file">
                        <label class="btn btn-outline-primary" for="export-dest-file" id="export-dest-file-label">
                            <i class="bi bi-hdd"></i> Server
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="export-format" class="form-label fw-bold">Format</label>
                    <?= Html::dropDownList('export-format', CopyType::MD->value, $formatOptions, [
                        'id' => 'export-format',
                        'class' => 'form-select',
                    ]) ?>
                </div>

                <div id="export-filename-options" class="d-none">
                    <hr>

                    <div class="mb-3">
                        <label for="export-filename" class="form-label fw-bold">Filename</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="export-filename" placeholder="Enter filename...">
                            <span class="input-group-text" id="export-extension">.md</span>
                            <button type="button" class="btn btn-outline-secondary" id="export-suggest-name-btn" title="Suggest name based on content">
                                <i class="bi bi-stars"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback d-block d-none" id="export-filename-error"></div>
                    </div>
                </div>

                <div id="export-directory-options" class="d-none">
                    <div class="mb-3">
                        <label for="export-directory" class="form-label fw-bold">Directory</label>
                        <small class="text-muted d-block mb-1" id="export-root-display-wrapper">
                            Project root: <code id="export-root-display"></code>
                        </small>
                        <div class="position-relative">
                            <input type="text" class="form-control" id="export-directory" value="" placeholder="" autocomplete="off">
                            <div id="export-directory-dropdown" class="dropdown-menu w-100" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted">
                            Preview: <code id="export-preview-path">/filename.md</code>
                        </small>
                    </div>

                    <div class="alert alert-warning d-none" id="export-overwrite-warning">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="export-overwrite-confirm">
                            <label class="form-check-label" for="export-overwrite-confirm">
                                File already exists. Check to overwrite.
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="export-submit-btn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.ExportModal = (function() {
    const FORMAT_MAP = {
        'md': { ext: '.md', mime: 'text/markdown' },
        'text': { ext: '.txt', mime: 'text/plain' },
        'html': { ext: '.html', mime: 'text/html' },
        'quilldelta': { ext: '.json', mime: 'application/json' },
        'llm-xml': { ext: '.xml', mime: 'application/xml' }
    };

    const URLS = {
        convertFormat: '<?= $convertFormatUrl ?>',
        exportToFile: '<?= $exportToFileUrl ?>',
        pathList: '<?= $pathListUrl ?>',
        suggestName: '<?= $suggestNameUrl ?>'
    };

    let currentConfig = {
        projectId: null,
        entityName: '',
        hasRoot: false,
        rootDirectory: null,
        getContent: null
    };

    let directorySelector = null;

    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.content || meta.getAttribute('content')) : '';
    };

    const showToast = (message, type) => {
        if (window.QuillToolbar && window.QuillToolbar.showToast) {
            window.QuillToolbar.showToast(message, type);
        }
    };

    const getElements = () => ({
        modal: document.getElementById('exportModal'),
        errorAlert: document.getElementById('export-error-alert'),
        destClipboard: document.getElementById('export-dest-clipboard'),
        destDownload: document.getElementById('export-dest-download'),
        destFile: document.getElementById('export-dest-file'),
        destFileLabel: document.getElementById('export-dest-file-label'),
        formatSelect: document.getElementById('export-format'),
        filenameOptions: document.getElementById('export-filename-options'),
        directoryOptions: document.getElementById('export-directory-options'),
        filenameInput: document.getElementById('export-filename'),
        filenameError: document.getElementById('export-filename-error'),
        extensionSpan: document.getElementById('export-extension'),
        directoryInput: document.getElementById('export-directory'),
        directoryDropdown: document.getElementById('export-directory-dropdown'),
        rootDisplay: document.getElementById('export-root-display'),
        rootDisplayWrapper: document.getElementById('export-root-display-wrapper'),
        previewPath: document.getElementById('export-preview-path'),
        overwriteWarning: document.getElementById('export-overwrite-warning'),
        overwriteCheckbox: document.getElementById('export-overwrite-confirm'),
        suggestBtn: document.getElementById('export-suggest-name-btn'),
        submitBtn: document.getElementById('export-submit-btn')
    });

    const getSelectedDestination = () => {
        const checked = document.querySelector('input[name="export-destination"]:checked');
        return checked ? checked.value : 'clipboard';
    };

    const sanitizeFilename = (name) => {
        let sanitized = name.replace(/[\/\\:*?"<>|]/g, '-');
        sanitized = sanitized.replace(/^[\s.]+|[\s.]+$/g, '');
        return sanitized.substring(0, 200);
    };

    const updateExtension = () => {
        const el = getElements();
        const format = el.formatSelect.value;
        const formatInfo = FORMAT_MAP[format] || { ext: '.txt' };
        el.extensionSpan.textContent = formatInfo.ext;
        updatePreviewPath();
    };

    const updatePreviewPath = () => {
        const el = getElements();
        const filename = el.filenameInput.value.trim() || 'filename';
        const ext = el.extensionSpan.textContent;
        const relativeDir = directorySelector ? directorySelector.getValue() : (el.directoryInput.value.trim() || '/');
        const normalizedRelativeDir = relativeDir.endsWith('/') ? relativeDir : relativeDir + '/';

        const rootDir = currentConfig.rootDirectory || '';
        const normalizedRoot = rootDir.endsWith('/') ? rootDir.slice(0, -1) : rootDir;
        const absolutePath = normalizedRoot + normalizedRelativeDir + sanitizeFilename(filename) + ext;
        el.previewPath.textContent = absolutePath;
    };

    const toggleDestinationOptions = () => {
        const el = getElements();
        const destination = getSelectedDestination();

        const showFilename = destination === 'download' || destination === 'file';
        const showDirectory = destination === 'file';

        el.filenameOptions.classList.toggle('d-none', !showFilename);
        el.directoryOptions.classList.toggle('d-none', !showDirectory);
        el.overwriteWarning.classList.add('d-none');
        el.overwriteCheckbox.checked = false;
    };

    const initDirectorySelector = () => {
        const el = getElements();
        if (!window.DirectorySelector) {
            console.warn('DirectorySelector not loaded, using fallback');
            return;
        }

        directorySelector = new window.DirectorySelector({
            inputElement: el.directoryInput,
            dropdownElement: el.directoryDropdown,
            pathListUrl: URLS.pathList,
            onSelect: () => updatePreviewPath(),
            onChange: () => updatePreviewPath(),
            emptyText: 'No directories match'
        });
    };

    const loadDirectories = async (projectId) => {
        if (directorySelector) {
            await directorySelector.load(projectId, 'directory');
        }
    };

    const suggestName = async () => {
        const el = getElements();
        if (!currentConfig.getContent) return;

        const content = currentConfig.getContent();
        if (!content || content.length <= 1) {
            el.filenameError.textContent = 'Write some content first.';
            el.filenameError.classList.remove('d-none');
            return;
        }

        const originalHtml = el.suggestBtn.innerHTML;
        el.suggestBtn.disabled = true;
        el.suggestBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        el.filenameError.classList.add('d-none');

        try {
            const response = await fetch(URLS.suggestName, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ content: content })
            });

            const data = await response.json();
            if (data.success && data.name) {
                el.filenameInput.value = sanitizeFilename(data.name);
                updatePreviewPath();
            } else {
                el.filenameError.textContent = data.error || 'Could not generate name.';
                el.filenameError.classList.remove('d-none');
            }
        } catch (err) {
            el.filenameError.textContent = 'Request failed.';
            el.filenameError.classList.remove('d-none');
        } finally {
            el.suggestBtn.disabled = false;
            el.suggestBtn.innerHTML = originalHtml;
        }
    };

    const copyToClipboard = async (text) => {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
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

    const exportToClipboard = async (deltaContent, format) => {
        const el = getElements();
        const originalHtml = el.submitBtn.innerHTML;
        el.submitBtn.disabled = true;
        el.submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Exporting...';

        try {
            const response = await fetch(URLS.convertFormat, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ content: deltaContent, format: format })
            });

            const data = await response.json();
            if (data.success && data.content !== undefined) {
                await copyToClipboard(data.content);
                bootstrap.Modal.getInstance(el.modal).hide();
                showToast('Content copied to clipboard', 'success');
            } else {
                el.errorAlert.textContent = data.message || 'Failed to convert format.';
                el.errorAlert.classList.remove('d-none');
            }
        } catch (err) {
            el.errorAlert.textContent = 'Export failed.';
            el.errorAlert.classList.remove('d-none');
        } finally {
            el.submitBtn.disabled = false;
            el.submitBtn.innerHTML = originalHtml;
        }
    };

    const exportToDownload = async (deltaContent, format) => {
        const el = getElements();
        const filename = el.filenameInput.value.trim();

        if (!filename) {
            el.filenameError.textContent = 'Filename is required.';
            el.filenameError.classList.remove('d-none');
            el.filenameInput.focus();
            return;
        }

        el.filenameError.classList.add('d-none');

        const originalHtml = el.submitBtn.innerHTML;
        el.submitBtn.disabled = true;
        el.submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Exporting...';

        try {
            const response = await fetch(URLS.convertFormat, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ content: deltaContent, format: format })
            });

            const data = await response.json();
            if (data.success && data.content !== undefined) {
                const formatInfo = FORMAT_MAP[format] || { ext: '.txt', mime: 'text/plain' };
                const blob = new Blob([data.content], { type: formatInfo.mime });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = sanitizeFilename(filename) + formatInfo.ext;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                bootstrap.Modal.getInstance(el.modal).hide();
                showToast('Downloaded ' + sanitizeFilename(filename) + formatInfo.ext, 'success');
            } else {
                el.errorAlert.textContent = data.message || 'Failed to convert format.';
                el.errorAlert.classList.remove('d-none');
            }
        } catch (err) {
            el.errorAlert.textContent = 'Export failed.';
            el.errorAlert.classList.remove('d-none');
        } finally {
            el.submitBtn.disabled = false;
            el.submitBtn.innerHTML = originalHtml;
        }
    };

    const exportToFile = async (deltaContent, format) => {
        const el = getElements();
        const filename = el.filenameInput.value.trim();
        const directory = directorySelector ? directorySelector.getValue() : (el.directoryInput.value.trim() || '/');
        const overwrite = el.overwriteCheckbox.checked;

        if (!filename) {
            el.filenameError.textContent = 'Filename is required.';
            el.filenameError.classList.remove('d-none');
            el.filenameInput.focus();
            return;
        }

        el.filenameError.classList.add('d-none');

        const originalHtml = el.submitBtn.innerHTML;
        el.submitBtn.disabled = true;
        el.submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Exporting...';

        try {
            const response = await fetch(URLS.exportToFile, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    content: deltaContent,
                    format: format,
                    filename: sanitizeFilename(filename),
                    directory: directory,
                    project_id: currentConfig.projectId,
                    overwrite: overwrite
                })
            });

            const data = await response.json();
            if (data.success) {
                bootstrap.Modal.getInstance(el.modal).hide();
                showToast('Saved to ' + data.path, 'success');
            } else if (data.exists) {
                el.overwriteWarning.classList.remove('d-none');
            } else {
                el.errorAlert.textContent = data.message || 'Export failed.';
                el.errorAlert.classList.remove('d-none');
            }
        } catch (err) {
            el.errorAlert.textContent = 'Export failed.';
            el.errorAlert.classList.remove('d-none');
        } finally {
            el.submitBtn.disabled = false;
            el.submitBtn.innerHTML = originalHtml;
        }
    };

    const handleExport = async () => {
        const el = getElements();
        el.errorAlert.classList.add('d-none');

        if (!currentConfig.getContent) {
            el.errorAlert.textContent = 'No content provider configured.';
            el.errorAlert.classList.remove('d-none');
            return;
        }

        const deltaContent = currentConfig.getContent();
        const format = el.formatSelect.value;

        switch (getSelectedDestination()) {
            case 'clipboard':
                await exportToClipboard(deltaContent, format);
                break;
            case 'download':
                await exportToDownload(deltaContent, format);
                break;
            case 'file':
                await exportToFile(deltaContent, format);
                break;
        }
    };

    const open = (config) => {
        const el = getElements();

        currentConfig = {
            projectId: config.projectId || null,
            entityName: config.entityName || '',
            hasRoot: config.hasRoot || false,
            rootDirectory: config.rootDirectory || null,
            getContent: config.getContent || null
        };

        // Reset state
        el.errorAlert.classList.add('d-none');
        el.filenameError.classList.add('d-none');
        el.overwriteWarning.classList.add('d-none');
        el.overwriteCheckbox.checked = false;
        el.destClipboard.checked = true;
        el.formatSelect.value = 'md';
        el.filenameInput.value = sanitizeFilename(currentConfig.entityName);

        // Reset directory selector
        if (directorySelector) {
            directorySelector.reset();
        } else {
            el.directoryInput.value = '';
        }

        // Update root directory display
        if (currentConfig.rootDirectory) {
            el.rootDisplay.textContent = currentConfig.rootDirectory;
            el.rootDisplayWrapper.classList.remove('d-none');
        } else {
            el.rootDisplay.textContent = '';
            el.rootDisplayWrapper.classList.add('d-none');
        }

        // Toggle file option visibility
        if (currentConfig.hasRoot && currentConfig.projectId) {
            el.destFile.disabled = false;
            el.destFileLabel.classList.remove('disabled');
            el.destFileLabel.title = '';
            loadDirectories(currentConfig.projectId);
        } else {
            el.destFile.disabled = true;
            el.destFileLabel.classList.add('disabled');
            el.destFileLabel.title = 'Project has no root directory configured';
        }

        toggleDestinationOptions();
        updateExtension();

        const modal = new bootstrap.Modal(el.modal);
        modal.show();
    };

    const init = () => {
        const el = getElements();

        el.destClipboard.addEventListener('change', toggleDestinationOptions);
        el.destDownload.addEventListener('change', toggleDestinationOptions);
        el.destFile.addEventListener('change', toggleDestinationOptions);
        el.formatSelect.addEventListener('change', updateExtension);
        el.filenameInput.addEventListener('input', updatePreviewPath);
        el.suggestBtn.addEventListener('click', suggestName);
        el.submitBtn.addEventListener('click', handleExport);

        initDirectorySelector();

        // Handle Enter key in filename input
        el.filenameInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleExport();
            }
        });
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return { open };
})();
</script>
