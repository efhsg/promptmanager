<?php

use yii\helpers\Url;

$importMarkdownUrl = Url::to(['/note/import-markdown']);
$importServerFileUrl = Url::to(['/note/import-server-file']);
$pathListUrl = Url::to(['/field/path-list']);
?>

<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import Content</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="import-error-alert" role="alert"></div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Source</label>
                    <div class="btn-group w-100" role="group" aria-label="Import source">
                        <input type="radio" class="btn-check" name="import-source" id="import-source-client" value="client" checked>
                        <label class="btn btn-outline-primary" for="import-source-client">
                            <i class="bi bi-upload"></i> Client
                        </label>
                        <input type="radio" class="btn-check" name="import-source" id="import-source-server" value="server">
                        <label class="btn btn-outline-primary" for="import-source-server" id="import-source-server-label">
                            <i class="bi bi-hdd"></i> Server
                        </label>
                    </div>
                </div>

                <div id="import-client-options">
                    <div class="mb-3">
                        <label for="import-file-input" class="form-label fw-bold">File</label>
                        <input type="file" class="form-control" id="import-file-input" accept=".md,.markdown,.txt"
                               aria-describedby="import-file-hint">
                        <small class="text-muted" id="import-file-hint">Accepted: .md, .markdown, .txt (max 1MB)</small>
                    </div>
                </div>

                <div id="import-server-options" class="d-none">
                    <div class="mb-2">
                        <small class="text-muted" id="import-root-display-wrapper">
                            Project root: <code id="import-root-display"></code>
                        </small>
                    </div>
                    <div class="mb-3">
                        <label for="import-server-file-input" class="form-label fw-bold">File</label>
                        <div class="position-relative">
                            <input type="text" class="form-control" id="import-server-file-input"
                                   placeholder="Start typing to search files..." autocomplete="off">
                            <div id="import-server-file-dropdown" class="dropdown-menu w-100" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="import-submit-btn" disabled>
                    <i class="bi bi-box-arrow-in-down"></i> Import
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.ImportModal = (function() {
    const URLS = {
        importMarkdown: '<?= $importMarkdownUrl ?>',
        importServerFile: '<?= $importServerFileUrl ?>',
        pathList: '<?= $pathListUrl ?>'
    };

    let currentConfig = {
        projectId: null,
        hasRoot: false,
        rootDirectory: null,
        onImport: null
    };

    let fileSelector = null;
    let selectedServerPath = null;

    const getCsrfToken = () => window.QuillToolbar.getCsrfToken();

    const showToast = (message, type) => {
        if (window.QuillToolbar && window.QuillToolbar.showToast) {
            window.QuillToolbar.showToast(message, type);
        }
    };

    const getElements = () => ({
        modal: document.getElementById('importModal'),
        errorAlert: document.getElementById('import-error-alert'),
        sourceClient: document.getElementById('import-source-client'),
        sourceServer: document.getElementById('import-source-server'),
        sourceServerLabel: document.getElementById('import-source-server-label'),
        clientOptions: document.getElementById('import-client-options'),
        serverOptions: document.getElementById('import-server-options'),
        fileInput: document.getElementById('import-file-input'),
        serverFileInput: document.getElementById('import-server-file-input'),
        serverFileDropdown: document.getElementById('import-server-file-dropdown'),
        rootDisplay: document.getElementById('import-root-display'),
        rootDisplayWrapper: document.getElementById('import-root-display-wrapper'),
        submitBtn: document.getElementById('import-submit-btn')
    });

    const showError = (message) => {
        const el = getElements();
        el.errorAlert.textContent = message;
        el.errorAlert.classList.remove('d-none');
    };

    const hideError = () => {
        const el = getElements();
        el.errorAlert.classList.add('d-none');
    };

    const updateSubmitState = () => {
        const el = getElements();
        const isClient = el.sourceClient.checked;

        if (isClient) {
            el.submitBtn.disabled = !el.fileInput.files.length;
        } else {
            el.submitBtn.disabled = !selectedServerPath;
        }
    };

    const toggleSource = () => {
        const el = getElements();
        const isServer = el.sourceServer.checked;

        el.clientOptions.classList.toggle('d-none', isServer);
        el.serverOptions.classList.toggle('d-none', !isServer);
        hideError();
        updateSubmitState();
    };

    const initFileSelector = () => {
        const el = getElements();
        if (!window.DirectorySelector) {
            console.warn('DirectorySelector not loaded, server file selection unavailable');
            return;
        }

        fileSelector = new window.DirectorySelector({
            inputElement: el.serverFileInput,
            dropdownElement: el.serverFileDropdown,
            pathListUrl: URLS.pathList,
            onSelect: (path) => {
                selectedServerPath = path;
                updateSubmitState();
            },
            onChange: (value) => {
                if (value !== selectedServerPath) {
                    selectedServerPath = null;
                    updateSubmitState();
                }
            },
            emptyText: 'No matching files'
        });
    };

    const loadFiles = async (projectId) => {
        if (fileSelector) {
            await fileSelector.load(projectId, 'file');
        }
    };

    const importFromClient = async () => {
        const el = getElements();
        const file = el.fileInput.files[0];
        if (!file) return;

        const originalHtml = el.submitBtn.innerHTML;
        el.submitBtn.disabled = true;
        el.submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';

        try {
            const formData = new FormData();
            formData.append('mdFile', file);

            const response = await fetch(URLS.importMarkdown, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-Token': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();
            if (data.success && data.importData && data.importData.content) {
                bootstrap.Modal.getInstance(el.modal).hide();
                if (currentConfig.onImport) {
                    currentConfig.onImport(data.importData.content);
                }
                showToast('Loaded ' + file.name, 'success');
            } else {
                showError(data.errors?.mdFile?.[0] || data.message || 'Failed to import file.');
            }
        } catch (err) {
            console.error('Import error:', err);
            showError('Import failed.');
        } finally {
            el.submitBtn.disabled = false;
            el.submitBtn.innerHTML = originalHtml;
            updateSubmitState();
        }
    };

    const importFromServer = async () => {
        const el = getElements();
        if (!selectedServerPath) return;

        const originalHtml = el.submitBtn.innerHTML;
        el.submitBtn.disabled = true;
        el.submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';

        try {
            const response = await fetch(URLS.importServerFile, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    project_id: currentConfig.projectId,
                    path: selectedServerPath
                })
            });

            const data = await response.json();
            if (data.success && data.importData && data.importData.content) {
                bootstrap.Modal.getInstance(el.modal).hide();
                if (currentConfig.onImport) {
                    currentConfig.onImport(data.importData.content);
                }
                showToast('Loaded ' + (data.filename || 'file'), 'success');
            } else {
                showError(data.message || 'Failed to import file.');
            }
        } catch (err) {
            console.error('Import error:', err);
            showError('Import failed.');
        } finally {
            el.submitBtn.disabled = false;
            el.submitBtn.innerHTML = originalHtml;
            updateSubmitState();
        }
    };

    const handleImport = async () => {
        hideError();
        const el = getElements();

        if (el.sourceClient.checked) {
            await importFromClient();
        } else {
            await importFromServer();
        }
    };

    const open = (config) => {
        const el = getElements();

        currentConfig = {
            projectId: config.projectId || null,
            hasRoot: config.hasRoot || false,
            rootDirectory: config.rootDirectory || null,
            onImport: config.onImport || null
        };

        // Reset state
        hideError();
        el.sourceClient.checked = true;
        el.fileInput.value = '';
        selectedServerPath = null;

        if (fileSelector) {
            fileSelector.reset();
        } else {
            el.serverFileInput.value = '';
        }

        // Update root directory display
        if (currentConfig.rootDirectory) {
            el.rootDisplay.textContent = currentConfig.rootDirectory;
            el.rootDisplayWrapper.classList.remove('d-none');
        } else {
            el.rootDisplay.textContent = '';
            el.rootDisplayWrapper.classList.add('d-none');
        }

        // Toggle server option availability
        if (currentConfig.hasRoot && currentConfig.projectId) {
            el.sourceServer.disabled = false;
            el.sourceServerLabel.classList.remove('disabled');
            el.sourceServerLabel.title = '';
            loadFiles(currentConfig.projectId);
        } else {
            el.sourceServer.disabled = true;
            el.sourceServerLabel.classList.add('disabled');
            el.sourceServerLabel.title = 'Project has no root directory configured';
        }

        toggleSource();

        const modal = new bootstrap.Modal(el.modal);
        modal.show();
    };

    const init = () => {
        const el = getElements();

        el.sourceClient.addEventListener('change', toggleSource);
        el.sourceServer.addEventListener('change', toggleSource);
        el.fileInput.addEventListener('change', updateSubmitState);
        el.submitBtn.addEventListener('click', handleImport);

        initFileSelector();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return { open };
})();
</script>
