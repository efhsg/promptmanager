# Frontend Build Rules

## Quill Editor Customizations

When modifying Quill editor JavaScript:

1. **Edit source only**: `npm/src/js/editor-init.js`
2. **Never edit minified**: `yii/web/quill/1.3.7/editor-init.min.js`
3. **Regenerate after changes**:
   ```bash
   cd npm && node ./node_modules/uglify-js/bin/uglifyjs src/js/editor-init.js -o ../yii/web/quill/1.3.7/editor-init.min.js
   ```

## Why

- Minified files are build artifacts, not source code
- Manual edits to `.min.js` get overwritten on next build
- Source file is readable and maintainable

## Build Scripts Reference

From `npm/package.json`:

| Script | Purpose |
|--------|---------|
| `build-init` | Minify editor-init.js |
| `build-and-minify` | Full Quill build + minification |

Note: Build scripts use Docker paths (`/yii/...`). For local development, use the direct uglify-js command above.
