# User Story Template (Full)

Full version with all sections. For a quick start, see `user-story-template-minimal.md`.

Use this template to write a user story or use case that feeds into the two-phase analysis process (see `analyst.md`).

Fill in each section. Delete sections marked **(if applicable)** when they don't apply. Replace all `{placeholders}` with actual content.

---

## Story

**As a** {role — e.g., authenticated user, project owner, admin},
**I want to** {action — what the user does or triggers},
**so that** {benefit — why this matters to the user}.

## Context

{1-3 sentences explaining the broader context. Why is this needed now? What existing workflow does it improve or extend? Reference the relevant entity if applicable: Project, Context, Field, PromptTemplate, ScratchPad, PromptInstance.}

## Current Behavior (if applicable)

{Describe what happens today. Be specific: which page, which button, what the user sees. If this is a net-new feature with no current equivalent, delete this section.}

## Desired Behavior

{Describe the expected outcome step by step. Use numbered steps for a user flow:}

1. User navigates to / clicks / triggers ...
2. System shows / responds with ...
3. User confirms / submits / selects ...
4. System persists / updates / notifies ...

## Acceptance Criteria

{Concrete, testable conditions that must be true when this story is done. Use checkboxes:}

- [ ] {Criterion 1 — observable behavior, not implementation detail}
- [ ] {Criterion 2}
- [ ] {Criterion 3}

## Error States

{What should happen when things go wrong? If you're unsure, say so — the analyst will ask.}

| Scenario | Expected Behavior |
|----------|-------------------|
| {e.g., User submits empty form} | {e.g., Inline validation error on required fields} |
| {e.g., Server returns 500} | {e.g., Flash error message, form remains filled} |

## Constraints (if applicable)

{Any non-functional requirements or boundaries:}

- **Performance:** {e.g., response within 2 seconds}
- **Security:** {e.g., only project owner can access}
- **Compatibility:** {e.g., must work with existing Quill Delta format}
- **Data:** {e.g., max 255 characters, stored as JSON}

## UX Notes (if applicable)

{Wireframe sketch, description of visual placement, or reference to an existing page/component to follow. ASCII wireframes are welcome:}

```
{ASCII wireframe or "Follow the same layout as /scratch-pad/view"}
```

## Scope Boundaries

**In scope:**
- {What this story explicitly covers}

**Out of scope:**
- {What this story deliberately excludes and why}

## Open Questions (if any)

{List anything you're unsure about. The analyst will investigate before specifying.}

- {e.g., Should the button appear for all users or only project owners?}
- {e.g., Do we need to support bulk operations or just single items?}

---

## Usage

1. Copy this template
2. Fill in the sections — be as specific as you can, but don't force answers you're unsure about
3. Hand it to the analyst phase (see `analyst.md`) with:
   ```
   Run Phase 1 on this user story. Design dir: .claude/design/{feature-name}/
   ```
4. The analyst will ask clarifying questions before producing `spec.md`
5. After spec approval, Phase 2 produces `plan.md`

**Tip:** The more concrete your acceptance criteria and error states, the fewer round-trips the analyst needs. But it's better to leave a section blank and mark it as an open question than to guess — the analyst is designed to probe for missing information.
