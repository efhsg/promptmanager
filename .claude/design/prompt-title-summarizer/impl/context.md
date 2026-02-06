# Prompt Title Summarizer — Implementation Context

## Goal

Add a background AI-powered title summarization for prompt accordion items in the Claude CLI chat views. When a prompt is sent, the truncated text appears immediately as a fallback title, then a background request replaces it with a short AI-generated summary.

## Scope

6 files to modify (see spec.md for full details):

| File | Change |
|------|--------|
| `yii/controllers/ProjectController.php` | `+actionSummarizePrompt()`, `+buildTitleSummarizerSystemPrompt()` |
| `yii/controllers/ScratchPadController.php` | `+actionSummarizePrompt()`, `+buildTitleSummarizerSystemPrompt()` |
| `yii/views/project/claude.php` | `+summarizePromptTitle()` JS method, call in `createActiveAccordionItem` |
| `yii/views/scratch-pad/claude.php` | `+summarizePromptTitle()` JS method, call in `createActiveAccordionItem` |
| `yii/config/rbac.php` | `+summarizePrompt` action mapping (already done) |
| `yii/services/EntityPermissionService.php` | `+summarize-prompt` in `MODEL_BASED_ACTIONS` (already done) |

## Key References

| What | Where |
|------|-------|
| Spec | `.claude/design/prompt-title-summarizer/spec.md` |
| Existing pattern | `actionSummarizeSession` in both controllers |
| ClaudeCliService | `yii/services/ClaudeCliService.php` — `execute()` method |
| Accordion creation | `createActiveAccordionItem()` in both claude.php views |
| RBAC config | `yii/config/rbac.php` — `actionPermissionMap` per entity |
| Permission service | `yii/services/EntityPermissionService.php` — `MODEL_BASED_ACTIONS` |

## Key Differences from `actionSummarizeSession`

| Aspect | `summarizeSession` | `summarizePrompt` (new) |
|--------|--------------------|-----------------------|
| Input | Full conversation | Single prompt text |
| Body field | `conversation` | `prompt` |
| Model | `sonnet` | `haiku` |
| Timeout | 120s | 30s |
| Output | Structured markdown | Single line of plain text |
| Response field | `summary` | `title` |
| System prompt | Multi-section summarizer | "Summarize in max 10 words" |
