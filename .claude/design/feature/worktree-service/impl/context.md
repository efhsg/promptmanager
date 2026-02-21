# Implementation Context: Worktree Management

## Goal

Build a generic `WorktreeService` for managing multiple git worktrees per project from PromptManager. Supports parallel work on features, agent workspaces, and community skills — each in an isolated worktree.

## Scope

### In scope
- Database table `project_worktree` with migration
- `ProjectWorktree` ActiveRecord model with query class
- `WorktreePurpose` enum (community-skills, feature, agent-workspace)
- `WorktreeService` with git operations (create, sync, status, remove, cleanup, recreate)
- `WorktreeController` with 6 AJAX endpoints + RBAC
- View partial `_worktrees.php` on project view page
- JavaScript `worktree-manager.js` for AJAX interactions
- Worktree link button on prompt-instance view
- Unit tests for model and service

### Out of scope
- Community skills specific logic (uses this as foundation)
- Integration/E2E tests with real git repos
- WebSocket/real-time updates

## Key File References

| Reference | Path | Why |
|-----------|------|-----|
| Spec | `.claude/design/feature/worktree-service/spec.md` | Source of truth |
| Plan | `.claude/design/feature/worktree-service/plan.md` | Phase structure |
| PathService | `yii/services/PathService.php` | Host→container path translation |
| LogCategory enum | `yii/common/enums/LogCategory.php` | Add WORKTREE case |
| TimestampTrait | `yii/models/traits/TimestampTrait.php` | created_at/updated_at |
| AiChatController | `yii/controllers/AiChatController.php` | Controller RBAC pattern |
| EntityPermissionService | `yii/services/EntityPermissionService.php` | Permission checking |
| Project model | `yii/models/Project.php` | ActiveRecord pattern |
| ProjectQuery | `yii/models/query/ProjectQuery.php` | Query class pattern |
| Project view | `yii/views/project/view.php` | Partial include target |
| PI view | `yii/views/prompt-instance/view.php` | Action button target |

## Definition of Done

- [ ] Migration runs on both `yii` and `yii_test` schemas
- [ ] Model validates correctly (branch, suffix, purpose, path traversal prevention)
- [ ] Service handles create/sync/status/remove/cleanup/recreate
- [ ] Controller endpoints have proper RBAC via EntityPermissionService
- [ ] View partial renders worktrees section with all UI states
- [ ] JS handles AJAX interactions, clipboard copy, modal forms
- [ ] Prompt instance view shows Worktree link button
- [ ] Unit tests pass for model and service
- [ ] Linter passes with 0 issues
