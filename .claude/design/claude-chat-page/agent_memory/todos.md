# Implementation Units

- [x] U1: Download JS assets — `marked.min.js` (v17.0.1) and `purify.min.js` (v3.3.1) to `yii/web/js/`
- [x] U2: Create `yii/web/css/claude-chat.css` — scoped styles for chat page
- [x] U3: Add RBAC entry `'claude' => 'viewScratchPad'` in `config/rbac.php` + add `'claude'` to `EntityPermissionService::MODEL_BASED_ACTIONS`
- [x] U4: Add `actionClaude` to `ScratchPadController` + update `actionRunClaude` to accept `contentDelta` and return `promptMarkdown`
- [x] U5: Create `views/scratch-pad/claude.php` — PHP/HTML structure (settings card, prompt section, conversation panel, asset registration)
- [x] U6: Add JavaScript to `claude.php` — `ClaudeChat` object (init, config, settings, send, conversation rendering, input switching, markdown rendering, utilities)
- [x] U7: Update `views/scratch-pad/view.php` — change 3 Claude buttons to links, remove modal render call, delete `_claude-cli-modal.php`
- [x] U8: Add unit tests to `ScratchPadControllerTest.php` — `actionClaude` + `actionRunClaude` contentDelta/promptMarkdown tests
- [x] U9: Run linter + full test suite — verify all pass (703 tests, 1730 assertions, 0 failures)
