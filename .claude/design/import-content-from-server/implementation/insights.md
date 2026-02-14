# Insights: import-content-from-server

## Decisions
- Follow exact same IIFE + getElements pattern as ExportModal
- Import extensions hardcoded to .md, .markdown, .txt (spec says subset of project allowed_extensions)
- MarkdownDetector used to auto-detect .txt files that contain markdown
- PathService injected via constructor DI (same as FieldController pattern)
- No separate service class (spec says ~30 lines inline, matches actionImportMarkdown pattern)
- Extracted `applyImportDelta` helper in editor-init.js for reuse between setupLoadMd callback and future callers
- Views with project context (note/_form, context/_form, prompt-template/_form) pass projectConfig to setupLoadMd
- Views without project context keep simple urlConfig → server tab auto-disabled in modal

## Pitfalls
- Node/Docker not available in dev environment; editor-init.min.js not regenerated. Run manually: `cd npm && node ./node_modules/uglify-js/bin/uglifyjs src/js/editor-init.js -o ../yii/web/quill/1.3.7/editor-init.min.js`
- vfsStream test setup: use `vfsStream::setup('name', null, $structure)` array syntax instead of manually creating+adding files (slashes in filenames break the hierarchy)

## Findings
- NoteController constructor already uses DI: EntityPermissionService, YouTubeTranscriptService, NoteService
- ExportModal uses `window.DirectorySelector` with type='directory'; ImportModal will use type='file'
- `actionPathList` in FieldController already supports `type=file` filtering
- Test pattern: mockJsonRequest sets _rawBody via reflection + $_SERVER['REQUEST_METHOD']
- Project::find()->findUserProject() returns null if not owned

## Final Result
- Linter: 0 issues
- Tests: 985 total, 0 failures, 0 errors, 21 skipped (pre-existing)
- New tests: 11 (all passing)
