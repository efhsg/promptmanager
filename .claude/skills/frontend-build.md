---
name: frontend-build
description: Build and minify frontend JavaScript assets
area: creation
provides:
  - quill_build
  - js_minification
---

# Frontend Build

Build and minify Quill editor JavaScript customizations.

## When to Use

- When modifying Quill editor JavaScript
- After changing `npm/src/js/editor-init.js`
- When asked to rebuild frontend assets

## Source and Build Artifacts

| Type | Path |
|------|------|
| Source | `npm/src/js/editor-init.js` |
| Output | `yii/web/quill/1.3.7/editor-init.min.js` |

**Never edit minified files directly.** They are build artifacts that get overwritten.

## Build Commands

Frontend builds require the npm container (not available from yii container). Ask the user to run manually:

```bash
# Via Docker (standard)
docker compose run --entrypoint bash pma_npm -c "npm run build-and-minify"

# Local development (direct uglify-js)
cd npm && node ./node_modules/uglify-js/bin/uglifyjs src/js/editor-init.js -o ../yii/web/quill/1.3.7/editor-init.min.js

# Watch mode (local, auto-rebuilds on save)
cd npm && npm run watch
```

## Build Scripts Reference

From `npm/package.json`:

| Script | Purpose |
|--------|---------|
| `build-init` | Minify editor-init.js |
| `build-and-minify` | Full Quill build + minification |
| `watch` | Auto-rebuild editor-init.js on changes |

## Definition of Done

- Source file edited (never minified)
- User informed to run build command
- Changes tested in browser after build
