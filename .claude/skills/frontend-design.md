# Frontend Design

Create production-grade frontend interfaces following PromptManager's Bootstrap 5 foundation. Use this skill when building or modifying UI components, views, or styles.

## Overview

PromptManager uses **Bootstrap 5 with the Spacelab theme** as its design foundation. All UI work should extend Bootstrap patterns rather than fighting against them. The aesthetic is clean, functional, and developer-tool oriented.

**Tech stack:**
- **CSS Framework:** Bootstrap 5 + Spacelab theme (`spacelab.min.css`)
- **Custom styles:** `yii/web/css/site.css`
- **Rich text:** Quill editor with Delta JSON format
- **JavaScript:** Vanilla JS (no frameworks)
- **Views:** Yii2 PHP templates

## Bootstrap Integration

### Use Bootstrap Utilities First

Before writing custom CSS, check if Bootstrap utilities can solve the problem:

```html
<!-- CORRECT - Bootstrap utilities -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="mb-0">Page Title</h1>
    <a href="#" class="btn btn-primary">Action</a>
</div>

<!-- AVOID - Custom CSS for basic layout -->
<div class="custom-header">
    <h1>Page Title</h1>
    <a href="#">Action</a>
</div>
```

### Common Bootstrap Patterns

**Buttons:**
```html
<a class="btn btn-primary">Primary Action</a>
<button class="btn btn-secondary">Secondary</button>
<button class="btn btn-danger">Delete</button>
<button class="btn btn-sm btn-outline-secondary">Small</button>
```

**Alerts:**
```html
<div class="alert alert-success">Success message</div>
<div class="alert alert-danger">Error message</div>
<div class="alert alert-warning">Warning message</div>
```

**Cards:**
```html
<div class="card">
    <div class="card-header">Title</div>
    <div class="card-body">Content</div>
</div>
```

**Forms:**
```html
<div class="mb-3">
    <label class="form-label" for="name">Name</label>
    <input class="form-control" id="name" type="text">
    <div class="form-text">Help text</div>
</div>
```

**Tables:**
```html
<table class="table table-striped table-hover">
    <thead>
        <tr><th>Column</th></tr>
    </thead>
    <tbody>
        <tr><td>Data</td></tr>
    </tbody>
</table>
```

## Custom Styles (site.css)

When Bootstrap isn't sufficient, add custom styles to `yii/web/css/site.css`.

### Existing Custom Patterns

| Class | Purpose |
|-------|---------|
| `.form-group` | Form field wrapper with margin |
| `.has-error .form-control` | Error state for inputs |
| `.help-block.error-message` | Validation error display |
| `.resizable-editor-container` | Quill editor wrapper |
| `.error-summary` | Form error summary block |
| `.not-set` | Italic red for missing values |
| `.hint-block` | Muted helper text |

### Adding Custom Styles

When adding new styles:

1. **Check Bootstrap first** — Can a utility class solve this?
2. **Scope narrowly** — Use specific selectors, not global overrides
3. **Document purpose** — Add a comment explaining what it's for
4. **Avoid `!important`** — Only use for overriding third-party styles

```css
/* GOOD - Scoped, documented */
/* Sticky toolbar for scrollable Quill editors */
.resizable-editor-container .ql-toolbar {
    position: sticky;
    top: -10px;
    z-index: 1;
    background: #fff;
}

/* AVOID - Too broad */
.toolbar {
    position: sticky !important;
}
```

## Color Palette

Use Bootstrap's semantic colors. These are defined by the Spacelab theme:

| Bootstrap Class | Color | Use For |
|-----------------|-------|---------|
| `btn-primary`, `text-primary` | Blue | Primary actions, links |
| `btn-danger`, `text-danger` | Red | Destructive actions, errors |
| `btn-success`, `text-success` | Green | Confirmations, valid states |
| `btn-warning`, `text-warning` | Yellow | Warnings, cautions |
| `btn-secondary` | Gray | Secondary actions |
| `bg-light` | Light gray | Backgrounds, footers |

### Custom Colors in site.css

Existing custom colors (hardcoded):

| Color | Usage |
|-------|-------|
| `#2b2b2b` | Dark modal background (path preview) |
| `#dcdcdc` | Light text on dark background |
| `#ced4da` | Border color (Bootstrap default) |
| `#e6f0ff` | Select2 selected option background |
| `#3b6dbc` | Select2 accent border |

## Quill Editor Styling

The Quill rich text editor has specific styling requirements.

### Editor Container

```html
<div class="resizable-editor-container">
    <div id="editor" class="resizable-editor"></div>
</div>
```

### Key CSS Classes

| Class | Purpose |
|-------|---------|
| `.resizable-editor-container` | Outer wrapper with resize, border, padding |
| `.resizable-editor` | Inner editor with min-height |
| `.ql-toolbar` | Sticky toolbar (custom styling) |
| `.ql-editor` | Content area (Quill default) |

### Custom Toolbar Pickers

Field insertion pickers have custom styling:

```css
.ql-toolbar .ql-picker.ql-insertGeneralField,
.ql-toolbar .ql-picker.ql-insertProjectField,
.ql-toolbar .ql-picker.ql-insertExternalField {
    width: auto;
    padding: 2px 2px;
    margin: 8px 0 4px 8px;
    border: 1px solid #ccc;
    border-radius: 3px;
}
```

### Quill Theme

PromptManager uses the **Snow theme** (`ql-snow`). Don't mix themes or override core Quill styles unnecessarily.

## Dark Theme (Modal Only)

The path preview modal uses a dark theme for code display:

```css
#path-preview-modal .modal-content {
    background-color: #2b2b2b;
    color: #dcdcdc;
}
```

This dark theme is **scoped to `#path-preview-modal`** only. Do not apply dark styles globally.

### IDE-like Syntax Highlighting

The modal uses highlight.js with custom colors matching JetBrains IDEs:

| Token | Color | Examples |
|-------|-------|----------|
| Keywords | `#ce6d3c` | `public`, `function`, `namespace` |
| Strings | `#6a8759` | `'text'`, `"string"` |
| Variables | `#6897bb` | `$variable`, numbers |
| Comments | `#6a8759` | `// comment` |

## Select2 Styling

Select2 dropdowns use Bootstrap-styled theming:

```css
.select2-container--bootstrap .select2-results__option--selected {
    background-color: #e6f0ff;
    border-left-color: #3b6dbc;
    color: #123870;
}
```

Maintain these accent colors for consistency across all Select2 instances.

## Yii2 View Conventions

### Layout Structure

```
views/layouts/_base.php    → HTML skeleton
views/layouts/main.php     → Main layout (navbar + footer)
views/<controller>/*.php   → Controller views
```

### Common Patterns

**Page header with action:**
```php
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= Html::encode($this->title) ?></h1>
    <?= Html::a('Create', ['create'], ['class' => 'btn btn-primary']) ?>
</div>
```

**Form with validation:**
```php
<?php $form = ActiveForm::begin(); ?>
    <?= $form->field($model, 'name')->textInput() ?>
    <?= $form->field($model, 'description')->textarea(['rows' => 3]) ?>
    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    </div>
<?php ActiveForm::end(); ?>
```

**Detail view:**
```php
<?= DetailView::widget([
    'model' => $model,
    'attributes' => ['id', 'name', 'created_at:datetime'],
]) ?>
```

### HTML Encoding

Always use `Html::encode()` for user-provided values:

```php
<!-- CORRECT -->
<td><?= Html::encode($model->name) ?></td>

<!-- WRONG - XSS vulnerability -->
<td><?= $model->name ?></td>
```

## Accessibility

- **Labels:** Every form input needs a `<label>` with `for` attribute
- **Buttons:** Icon-only buttons need `aria-label`
- **Color contrast:** Don't rely solely on color to convey meaning
- **Focus states:** Ensure keyboard navigation works

```html
<!-- Icon button with accessibility -->
<button class="btn btn-sm btn-outline-secondary" aria-label="Edit">
    <i class="bi bi-pencil"></i>
</button>
```

## Responsive Design

Bootstrap handles responsive breakpoints. Use Bootstrap's grid and responsive utilities:

```html
<!-- Responsive columns -->
<div class="row">
    <div class="col-12 col-md-6 col-lg-4">Column</div>
</div>

<!-- Hide on mobile -->
<span class="d-none d-md-inline">Desktop only</span>

<!-- Stack on mobile, row on desktop -->
<div class="d-flex flex-column flex-md-row">...</div>
```

## Anti-Patterns to Avoid

| Anti-Pattern | Correct Approach |
|--------------|------------------|
| Inline styles | Add to `site.css` with scoped selector |
| Global CSS overrides | Scope to specific component/page |
| Custom layout utilities | Use Bootstrap's flex/grid utilities |
| New color values | Use Bootstrap semantic colors |
| Fighting Bootstrap | Work with the framework, not against it |
| `!important` everywhere | Fix specificity properly |

## Verification Checklist

Before completing frontend work:

- [ ] Uses Bootstrap utilities where possible
- [ ] Custom CSS is scoped and documented
- [ ] Quill editor styling preserved
- [ ] Select2 accent colors consistent
- [ ] Dark theme stays scoped to modal
- [ ] Forms have proper labels
- [ ] User content is HTML-encoded
- [ ] Responsive behavior tested
