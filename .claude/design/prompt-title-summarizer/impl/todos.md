# Prompt Title Summarizer — Implementation Checklist

## Pre-work (already done)
- [x] RBAC: `summarizePrompt` mapped in `yii/config/rbac.php` for both project and scratchPad
- [x] Permission: `summarize-prompt` added to `MODEL_BASED_ACTIONS` in `EntityPermissionService`
- [x] URL variable `$summarizePromptUrl` added to both claude.php views

## Backend
- [ ] `ProjectController::actionSummarizePrompt()` — new action, follows `actionSummarizeSession` pattern but uses haiku, 30s timeout, `prompt` body field, returns `title`
- [ ] `ProjectController::buildTitleSummarizerSystemPrompt()` — private method, returns concise system prompt
- [ ] `ScratchPadController::actionSummarizePrompt()` — mirror of ProjectController version
- [ ] `ScratchPadController::buildTitleSummarizerSystemPrompt()` — mirror of ProjectController version

## Frontend
- [ ] `summarizePromptTitle(itemId, promptText)` JS method in `project/claude.php` — fire-and-forget fetch, updates `.claude-history-item__title`
- [ ] Call `summarizePromptTitle` from `createActiveAccordionItem` in `project/claude.php`
- [ ] `summarizePromptTitle(itemId, promptText)` JS method in `scratch-pad/claude.php` — same implementation
- [ ] Call `summarizePromptTitle` from `createActiveAccordionItem` in `scratch-pad/claude.php`

## Validation
- [ ] Manual test: send prompt in project Claude CLI, verify title updates after a few seconds
- [ ] Manual test: same in scratch-pad Claude CLI
- [ ] Verify fallback: if summarize fails, truncated title remains
