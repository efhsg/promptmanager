# Extract shared Quill toolbar into a partial

## Context

`yii/views/scratch-pad/claude.php` and `yii/views/project/claude.php` contain a **byte-for-byte identical** `<div id="claude-quill-toolbar">` block (~55 lines of HTML). This toolbar is the container-based Quill toolbar used by both Claude CLI chat views.

## Task

Extract the shared toolbar HTML into a Yii2 partial and render it from both views.

## Steps

1. Create `yii/views/partials/_claude-quill-toolbar.php` containing the entire `<div id="claude-quill-toolbar">...</div>` block (the one with all the `<span class="ql-formats">` groups including clean, clearEditor, command-slot, smartPaste, loadMd).

2. In `yii/views/scratch-pad/claude.php`, replace the toolbar `<div>` block with:
   ```php
   <?= $this->render('/partials/_claude-quill-toolbar') ?>
   ```

3. In `yii/views/project/claude.php`, replace the toolbar `<div>` block with the same render call.

4. Verify no parameters are needed — the toolbar HTML is fully static. The JS that references `#claude-quill-toolbar` and `#claude-command-slot` remains in each view's inline script and needs no changes.

## Constraints

- The partial must live in `yii/views/partials/` (create the directory if it doesn't exist).
- Use the `/partials/` path prefix in the render call so it resolves from the views root, not the current controller's view directory.
- Do not move or change any JavaScript — only the HTML block moves to the partial.
- Do not change any element IDs or class names.
