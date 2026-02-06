# Prompt Title Summarizer — Implementation Insights

## Pre-implementation findings

### RBAC & permissions already wired
The staged changes (visible in `git status`) show that `rbac.php` and `EntityPermissionService.php` already have the `summarizePrompt` / `summarize-prompt` entries. Both views also already have the `$summarizePromptUrl` variable. This means only the controller actions and the JS methods remain.

### Controller pattern is near-identical
Both controllers have identical `actionSummarizeSession` / `buildSummarizerSystemPrompt` methods. The new action follows the same structure — only the body field name, model, timeout, system prompt, and response field change.

### ScratchPad working directory resolution
`ProjectController` uses `$model->root_directory` directly. `ScratchPadController` resolves it via `$model->project->root_directory` (the ScratchPad belongs to a Project). This difference must be preserved in the new action.

### Accordion DOM structure
The title element is at `#item-{itemId} .claude-history-item__title`. The `itemId` is available as `this.activeItemId` right after `createActiveAccordionItem` sets it, but it's cleaner to pass it explicitly to the summarize method.

### No client-side truncation needed yet
The spec mentions truncating long Haiku responses at 80 chars. The system prompt already constrains output to ~10 words, so this is a safety net. Can be added as a simple `substring(0, 80)` in the JS callback.
