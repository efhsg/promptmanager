window.WorktreeManager = (function() {
  let config = {};

  const getCsrfToken = () => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? (meta.content || meta.getAttribute('content')) : '';
  };

  const fetchJson = (url, data) => {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(data)
    }).then(r => r.json());
  };

  const showToast = (message, type) => {
    if (window.QuillToolbar && window.QuillToolbar.showToast) {
      window.QuillToolbar.showToast(message, type || 'success');
    }
  };

  const escapeHtml = (str) => {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  };

  const setButtonLoading = (btn, loading) => {
    if (loading) {
      btn.dataset.originalHtml = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';
      btn.disabled = true;
      btn.setAttribute('aria-busy', 'true');
    } else {
      btn.innerHTML = btn.dataset.originalHtml || btn.innerHTML;
      btn.disabled = false;
      btn.removeAttribute('aria-busy');
    }
  };

  const showContainerError = (message) => {
    const container = document.querySelector(config.container);
    if (!container) return;
    const existing = container.querySelector('.alert-danger');
    if (existing) existing.remove();
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger';
    alert.setAttribute('role', 'alert');
    alert.textContent = message;
    container.prepend(alert);
  };

  const clearContainerErrors = () => {
    const container = document.querySelector(config.container);
    if (!container) return;
    container.querySelectorAll('.alert-danger').forEach(el => el.remove());
  };

  const showItemError = (worktreeId, message) => {
    const item = document.querySelector(`[data-worktree-id="${worktreeId}"]`);
    if (!item) return;
    const existing = item.querySelector('.alert-danger');
    if (existing) existing.remove();
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger mt-2 mb-0 py-1 px-2 small';
    alert.setAttribute('role', 'alert');
    alert.textContent = message;
    item.appendChild(alert);
  };

  const purposeDefaults = {
    'feature': { branch: 'feature/', suffix: 'feature' },
    'bugfix': { branch: 'bugfix/', suffix: 'bugfix' },
    'refactor': { branch: 'refactor/', suffix: 'refactor' },
    'spike': { branch: 'spike/', suffix: 'spike' }
  };

  const renderStatusBadge = (status) => {
    if (!status.directoryExists) {
      return '<span class="badge bg-danger" aria-live="polite">Missing</span>';
    }
    if (status.behindSourceCount === 0) {
      return '<span class="badge bg-success" aria-live="polite">In sync</span>';
    }
    return `<span class="badge bg-warning text-dark" aria-live="polite">${status.behindSourceCount} behind</span>`;
  };

  const renderActions = (status) => {
    if (!status.directoryExists) {
      return `<button class="btn btn-sm btn-outline-primary me-1" onclick="WorktreeManager.handleRecreate(${status.id}, event)" title="Re-create the git worktree from the saved configuration" aria-label="Re-create worktree">Re-create</button>` +
        `<button class="btn btn-sm btn-outline-danger" onclick="WorktreeManager.handleCleanup(${status.id}, event)" title="Remove database record (worktree directory no longer exists)" aria-label="Clean up worktree record">Cleanup</button>`;
    }
    return `<button class="btn btn-sm btn-outline-primary me-1" onclick="WorktreeManager.handleSync(${status.id}, event)" aria-label="Sync worktree with ${escapeHtml(status.sourceBranch)}">Sync</button>` +
      `<button class="btn btn-sm btn-outline-danger" onclick="WorktreeManager.openRemoveModal(${status.id}, '${escapeHtml(status.hostPath).replace(/'/g, "\\'")}')" aria-label="Remove worktree">Remove</button>`;
  };

  const renderCard = (status) => {
    const purposeLabel = escapeHtml(status.purposeLabel);
    const badgeClass = status.purposeBadgeClass;
    const branch = escapeHtml(status.branch);
    const sourceBranch = escapeHtml(status.sourceBranch);
    const hostPath = escapeHtml(status.hostPath);

    return `<div class="list-group-item" data-worktree-id="${status.id}">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div class="flex-grow-1 me-md-3 mb-2 mb-md-0">
          <div class="d-flex align-items-center mb-1">
            <span class="badge ${badgeClass} me-2">${purposeLabel}</span>
            ${renderStatusBadge(status)}
          </div>
          <div class="small text-muted">
            <span>Path: <code>${hostPath}</code></span>
            <button class="btn btn-link btn-sm p-0 ms-1 copy-path-btn" onclick="WorktreeManager.copyPath('${hostPath.replace(/'/g, "\\'")}', this)" title="Copy path to clipboard" aria-label="Copy worktree path">
              <i class="bi bi-clipboard"></i>
            </button>
          </div>
          <div class="small text-muted">Branch: <code>${branch}</code> &rarr; <code>${sourceBranch}</code></div>
        </div>
        <div class="d-flex align-items-center flex-shrink-0">
          ${renderActions(status)}
        </div>
      </div>
    </div>`;
  };

  const renderList = (data) => {
    const container = document.querySelector(config.container);
    if (!container) return;

    if (!data.isGitRepo) {
      container.innerHTML = '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Root directory is not a git repository.</div>';
      const newBtn = document.getElementById('btn-new-worktree');
      if (newBtn) newBtn.disabled = true;
      return;
    }

    if (!data.worktrees || data.worktrees.length === 0) {
      container.innerHTML = '<p class="text-muted mb-0">No worktrees configured. Click "New Worktree" to create one.</p>';
      return;
    }

    let html = '<div class="list-group">';
    data.worktrees.forEach(s => { html += renderCard(s); });
    html += '</div>';
    html += '<div class="mt-2 small text-muted">Usage: <code>cd &lt;path&gt; &amp;&amp; claude</code></div>';
    container.innerHTML = html;
  };

  const loadStatus = async () => {
    const container = document.querySelector(config.container);
    if (!container) return;

    container.innerHTML = '<div class="d-flex justify-content-center py-3"><div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    clearContainerErrors();

    try {
      const response = await fetch(
        config.urls.status + '?projectId=' + config.projectId,
        { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
      );
      const result = await response.json();

      if (result.success) {
        renderList(result.data);
      } else {
        showContainerError(result.message || 'Failed to load worktree status.');
      }
    } catch (e) {
      showContainerError('Failed to load worktree status.');
    }
  };

  const openCreateModal = () => {
    const modal = document.getElementById('createWorktreeModal');
    if (!modal) return;

    // Reset form
    const errorAlert = modal.querySelector('.alert-danger');
    if (errorAlert) errorAlert.classList.add('d-none');
    modal.querySelector('#wt-branch').value = '';
    modal.querySelector('#wt-suffix').value = '';
    modal.querySelector('#wt-source-branch').value = 'main';
    const radios = modal.querySelectorAll('input[name="wt-purpose"]');
    radios.forEach(r => { r.checked = false; });

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  };

  const handlePurposeChange = (value) => {
    const defaults = purposeDefaults[value];
    const branchInput = document.getElementById('wt-branch');
    const suffixInput = document.getElementById('wt-suffix');
    const preview = document.getElementById('wt-suffix-preview');

    if (!defaults) {
      // Custom: clear fields that still hold a template value
      if (Object.values(purposeDefaults).some(d => d.branch === branchInput.value))
        branchInput.value = '';
      if (Object.values(purposeDefaults).some(d => d.suffix === suffixInput.value)) {
        suffixInput.value = '';
        if (preview) preview.textContent = '...';
      }
      branchInput.focus();
      return;
    }

    if (!branchInput.value || Object.values(purposeDefaults).some(d => d.branch === branchInput.value)) {
      branchInput.value = defaults.branch;
      if (defaults.branch.endsWith('/')) {
        branchInput.focus();
        branchInput.setSelectionRange(branchInput.value.length, branchInput.value.length);
      }
    }
    if (!suffixInput.value || Object.values(purposeDefaults).some(d => d.suffix === suffixInput.value)) {
      suffixInput.value = defaults.suffix;
      if (preview) preview.textContent = defaults.suffix;
    }
  };

  const handleCreate = async () => {
    const modal = document.getElementById('createWorktreeModal');
    if (!modal) return;

    const errorAlert = modal.querySelector('.alert-danger');
    if (errorAlert) errorAlert.classList.add('d-none');

    const purpose = modal.querySelector('input[name="wt-purpose"]:checked');
    const branch = modal.querySelector('#wt-branch').value.trim();
    const suffix = modal.querySelector('#wt-suffix').value.trim();
    const sourceBranch = modal.querySelector('#wt-source-branch').value.trim() || 'main';
    const submitBtn = modal.querySelector('#wt-create-btn');

    if (!purpose || !branch || !suffix) {
      if (errorAlert) {
        errorAlert.textContent = 'Please fill in all required fields.';
        errorAlert.classList.remove('d-none');
      }
      return;
    }

    setButtonLoading(submitBtn, true);

    try {
      const result = await fetchJson(config.urls.create, {
        projectId: config.projectId,
        branch: branch,
        suffix: suffix,
        purpose: purpose.value,
        sourceBranch: sourceBranch
      });

      if (result.success) {
        bootstrap.Modal.getInstance(modal).hide();
        showToast('Worktree created', 'success');
        loadStatus();
      } else {
        if (errorAlert) {
          errorAlert.textContent = result.message || 'Failed to create worktree.';
          errorAlert.classList.remove('d-none');
        }
      }
    } catch (e) {
      if (errorAlert) {
        errorAlert.textContent = 'Failed to create worktree.';
        errorAlert.classList.remove('d-none');
      }
    } finally {
      setButtonLoading(submitBtn, false);
    }
  };

  const handleSync = async (id, evt) => {
    const btn = evt.currentTarget;
    setButtonLoading(btn, true);

    try {
      const result = await fetchJson(config.urls.sync, { worktreeId: id });
      if (result.success) {
        showToast(result.message, 'success');
        loadStatus();
      } else {
        showItemError(id, result.message || 'Sync failed.');
      }
    } catch (e) {
      showItemError(id, 'Sync failed.');
    } finally {
      setButtonLoading(btn, false);
    }
  };

  const openRemoveModal = (id, path) => {
    const modal = document.getElementById('confirmRemoveModal');
    if (!modal) return;

    modal.querySelector('#remove-worktree-path').textContent = path;
    modal.querySelector('#remove-confirm-btn').onclick = () => handleRemove(id);

    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  };

  const handleRemove = async (id) => {
    const modal = document.getElementById('confirmRemoveModal');
    const btn = modal ? modal.querySelector('#remove-confirm-btn') : null;
    if (btn) setButtonLoading(btn, true);

    try {
      const result = await fetchJson(config.urls.remove, { worktreeId: id });
      if (result.success) {
        if (modal) bootstrap.Modal.getInstance(modal).hide();
        showToast('Worktree removed.', 'success');
        loadStatus();
      } else {
        showItemError(id, result.message || 'Remove failed.');
        if (modal) bootstrap.Modal.getInstance(modal).hide();
      }
    } catch (e) {
      showItemError(id, 'Remove failed.');
      if (modal) bootstrap.Modal.getInstance(modal).hide();
    } finally {
      if (btn) setButtonLoading(btn, false);
    }
  };

  const handleRecreate = async (id, evt) => {
    const btn = evt.currentTarget;
    setButtonLoading(btn, true);

    try {
      const result = await fetchJson(config.urls.recreate, { worktreeId: id });
      if (result.success) {
        showToast('Worktree re-created.', 'success');
        loadStatus();
      } else {
        showItemError(id, result.message || 'Re-create failed.');
      }
    } catch (e) {
      showItemError(id, 'Re-create failed.');
    } finally {
      setButtonLoading(btn, false);
    }
  };

  const handleCleanup = async (id, evt) => {
    const btn = evt.currentTarget;
    setButtonLoading(btn, true);

    try {
      const result = await fetchJson(config.urls.cleanup, { worktreeId: id });
      if (result.success) {
        showToast('Record cleaned up.', 'success');
        loadStatus();
      } else {
        showItemError(id, result.message || 'Cleanup failed.');
      }
    } catch (e) {
      showItemError(id, 'Cleanup failed.');
    } finally {
      setButtonLoading(btn, false);
    }
  };

  const copyPath = async (path, btn) => {
    const originalHtml = btn.innerHTML;
    try {
      if (window.QuillToolbar && window.QuillToolbar.copyToClipboard) {
        await window.QuillToolbar.copyToClipboard(path);
      } else {
        await navigator.clipboard.writeText(path);
      }
      btn.innerHTML = '<i class="bi bi-check"></i>';
      setTimeout(() => { btn.innerHTML = originalHtml; }, 1000);
    } catch (e) {
      showContainerError('Failed to copy path.');
    }
  };

  const init = (cfg) => {
    config = cfg;
    loadStatus();
  };

  return {
    init,
    loadStatus,
    handleSync,
    handleRecreate,
    handleCleanup,
    openRemoveModal,
    copyPath,
    openCreateModal,
    handleCreate,
    handlePurposeChange
  };
})();
