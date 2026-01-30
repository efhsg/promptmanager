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
| `watch` | Auto-rebuild editor-init.js on changes (uses chokidar) |

### Docker vs Local

Build scripts use Docker paths (`/yii/...`). Use accordingly:

```bash
# Via Docker (standard)
docker compose run --entrypoint bash pma_npm -c "npm run build-and-minify"

# Local development (direct uglify-js)
cd npm && node ./node_modules/uglify-js/bin/uglifyjs src/js/editor-init.js -o ../yii/web/quill/1.3.7/editor-init.min.js

# Watch mode (local, auto-rebuilds on save)
cd npm && npm run watch
```
