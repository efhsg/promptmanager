# Plan: Add "Suggest Name" to Scratch Pad Create Page

## Problem

The `/scratch-pad/create` page has a save modal where the user must manually type a name. The update page (`_form.php`) already has a "Suggest" button in its Save As modal that calls `/scratch-pad/suggest-name` using the Quill editor content. The create page lacks this feature.

The goal is to add the same AI name suggestion to:
1. **Save modal** on the create page (`create.php`)
2. Ensure it also works on the **Save As modal** on the update page (already done in `_form.php`)

## Current State

### Already implemented (no changes needed):
- **Backend:** `ScratchPadController::actionSuggestName()` — POST endpoint, no model-id required (line 776-804)
- **Backend:** `ClaudeQuickHandler` — `scratch-pad-name` use case configured (minChars: 20, maxChars: 3000, Haiku model)
- **Backend:** `.claude/workdirs/scratch-pad-name/CLAUDE.md` — system prompt
- **Frontend:** `_form.php` Save As modal — already has suggest button + JS handler (lines 118-120, 304-349)
- **Access control:** `suggest-name` in VerbFilter and AccessControl rules

### Missing:
- **`create.php` Save modal** — no suggest button, no JS handler

## Changes

### File: `yii/views/scratch-pad/create.php`

#### 1. HTML — Add suggest button to save modal name field

**Current** (lines 104-108):
```html
<div class="mb-3">
    <label for="scratch-pad-name" class="form-label">Name <span class="text-danger">*</span></label>
    <input type="text" class="form-control" id="scratch-pad-name" placeholder="Enter a name...">
    <div class="invalid-feedback" id="scratch-pad-name-error"></div>
</div>
```

**New:**
```html
<div class="mb-3">
    <label for="scratch-pad-name" class="form-label">Name <span class="text-danger">*</span></label>
    <div class="input-group">
        <input type="text" class="form-control" id="scratch-pad-name" placeholder="Enter a name...">
        <button type="button" class="btn btn-outline-secondary" id="suggest-name-btn" title="Suggest name based on content">
            <i class="bi bi-stars"></i> Suggest
        </button>
    </div>
    <div class="invalid-feedback d-block d-none" id="scratch-pad-name-error"></div>
</div>
```

**Why `d-block d-none`:** Bootstrap 5's `.invalid-feedback` relies on a `.is-invalid` sibling. Inside an `input-group`, this cascade breaks. The `d-block d-none` pattern gives us direct JS toggle control. This is the same pattern used in `_form.php` (line 122) and `claude.php`.

#### 2. PHP — Add URL variable

After `$importMarkdownUrl` (line 141), add:
```php
$suggestNameUrl = Url::to(['/scratch-pad/suggest-name']);
```

#### 3. JavaScript — Patch save-confirm handler for `d-block d-none` error div

The existing save-confirm handler (line 229-232) uses `.is-invalid` + `.invalid-feedback` which won't work with the `input-group` wrapper. Add `d-none` toggle:

```javascript
// In save-confirm-btn click handler:
if (!name) {
    nameInput.classList.add('is-invalid');
    document.getElementById('scratch-pad-name-error').textContent = 'Name is required.';
    document.getElementById('scratch-pad-name-error').classList.remove('d-none');  // NEW
    return;
}
// Reset at top of handler:
document.getElementById('scratch-pad-name-error').classList.add('d-none');  // NEW
```

#### 4. JavaScript — Patch modal-open handler to reset error div

In the save-content-btn click handler (line 213-219), add:
```javascript
document.getElementById('scratch-pad-name-error').classList.add('d-none');
```

#### 5. JavaScript — Add suggest-name click handler

After the save-confirm-btn handler, add a new event listener. **Content source: `window.quill.getText()`** (the main content editor, same as `_form.php` uses `quill.getText()`).

Follow `create.php` JS conventions: `const` variables, arrow functions, `document.querySelector('meta[name="csrf-token"]').getAttribute('content')` for CSRF.

```javascript
document.getElementById('suggest-name-btn').addEventListener('click', function() {
    const btn = this;
    const nameInput = document.getElementById('scratch-pad-name');
    const errorDiv = document.getElementById('scratch-pad-name-error');
    const content = window.quill.getText().trim();

    errorDiv.classList.add('d-none');

    if (!content) {
        errorDiv.textContent = 'Write some content first.';
        errorDiv.classList.remove('d-none');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('$suggestNameUrl', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ content: content })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.name) {
            nameInput.value = data.name;
            nameInput.classList.remove('is-invalid');
            errorDiv.classList.add('d-none');
        } else {
            errorDiv.textContent = data.error || 'Could not generate name.';
            errorDiv.classList.remove('d-none');
        }
    })
    .catch(() => {
        errorDiv.textContent = 'Request failed.';
        errorDiv.classList.remove('d-none');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-stars"></i> Suggest';
    });
});
```

### No changes needed

| Component | Reason |
|-----------|--------|
| `ScratchPadController` | `actionSuggestName()` already exists and works without model-id |
| `ClaudeQuickHandler` | `scratch-pad-name` use case already configured |
| `.claude/workdirs/scratch-pad-name/CLAUDE.md` | System prompt already exists |
| `_form.php` Save As modal | Already has suggest button (lines 118-120, 304-349) |
| `claude.php` Save dialog | Already has suggest button (uses selected user messages, different input source) |
| Access control / VerbFilter | `suggest-name` already listed in both rules |
| Tests | Backend is already tested; new change is frontend-only |

## Summary

Single file change: `yii/views/scratch-pad/create.php`
- Wrap name input in `input-group` with suggest button
- Switch error div to `d-block d-none` pattern
- Add `$suggestNameUrl` PHP variable
- Add suggest-name JS click handler using `window.quill.getText()`
- Patch existing save handler for error div toggle

## Content Source

| Page | Content source | Why |
|------|---------------|-----|
| `create.php` Save modal | `window.quill.getText()` | Main content editor; scratch pad hasn't been saved yet |
| `_form.php` Save As modal | `quill.getText()` (local var) | Same — editor content of existing scratch pad |
| `claude.php` Save dialog | Selected user messages from `self.messages[]` | Chat context; only user intent matters |

All three use plain text — AI doesn't need formatting for name generation.
