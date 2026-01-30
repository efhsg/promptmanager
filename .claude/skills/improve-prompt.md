---
name: improve-prompt
description: Analyze and improve agent prompt files for clarity, completeness, and reliability
area: validation
provides:
  - prompt_improvement
depends_on: []
---

# Improve Prompt

Analyze an agent prompt file and apply structured improvements while preserving the author's intent, variable conventions, and structure.

## Persona

Prompt engineer who specializes in improving agent prompts for clarity, completeness, and reliability. Understands LLM behavior and knows which instructions agents tend to skip or misinterpret.

## When to Use

- User wants to improve an existing prompt in `.claude/prompts/`
- User has written a new prompt and wants it reviewed
- User runs `/improve-prompt`

## Inputs

- `file`: Path to the prompt file (required). If not provided, list `.claude/prompts/*.md` and ask.

## Outputs

- Analysis report with pass/issue status per checklist item
- Proposed changes with explanations
- Edited file (after user approval)

## Variable Conventions

This project uses template variables in prompts. **Always preserve these exactly as-is.**

| Prefix | Meaning | Example |
|--------|---------|---------|
| `GEN:{{name}}` | Generic parameter (not project-specific) | `GEN:{{Language}}`, `GEN:{{Jira ticket}}` |
| `PRJ:{{name}}` | Project-specific parameter | `PRJ:{{PR_NUMBER}}` |
| `{{name}}` | Simple placeholder (legacy, no prefix) | `{{JIRA_TICKET}}`, `{{PR_NUMBER}}` |

**Rules:**
- Never rename, remove, or reformat variable placeholders
- Never replace variables with hardcoded values
- If adding new parameters, follow the `GEN:` / `PRJ:` convention
- `JIRA_TICKET` used in paths (e.g., `.claude/design/JIRA_TICKET/...`) is a runtime substitution, not a template variable — leave as-is

## Algorithm

### 1. Read the prompt file

Read the file specified by the user. If no file is given, list `.claude/prompts/*.md` and ask.

### 2. Analyze against the improvement checklist

Evaluate the prompt against each item. Score: pass / issue found / not applicable.

### 3. Present findings

Show the user a summary of issues found, grouped by category. For each issue, explain what's wrong and propose a fix.

### 4. Ask for approval

Present the list of proposed changes. The user can approve all, select specific ones, or modify.

### 5. Apply changes

Edit the file with approved improvements.

## Improvement Checklist

### A. Structure & Clarity

| # | Check | Common Issue |
|---|-------|--------------|
| A1 | **Single responsibility per phase** | Prompt tries to do multiple distinct tasks without clear separation. Split into named phases or workflows. |
| A2 | **Mandatory steps are marked** | Steps the agent tends to skip (reading code, IDE navigation) lack emphasis. Mark with **mandatory** / **CRITICAL**. |
| A3 | **Stop points are explicit** | Agent should wait for user input but no STOP instruction is given. Add "Wait for user input. Do not proceed until answered." |
| A4 | **Action options are concrete** | Vague options like "Review reply / Mark resolved". Replace with letter-coded options: `[A] Approve`, `[F] Follow-up`, etc. |
| A5 | **Priority order is defined** | Multiple items to process but no defined order. Add explicit ordering (e.g., by severity, by dependency). |
| A6 | **Termination condition is clear** | No defined end state. Add explicit conditions for when the workflow stops. |

### B. Robustness

| # | Check | Common Issue |
|---|-------|--------------|
| B1 | **No duplicate content** | Same query, template, or instruction appears multiple times. Consolidate to one location, reference from others. |
| B2 | **File references are resilient** | Prompt references files that may not exist (e.g., from a previous phase). Add "read if they exist" or check before reading. |
| B3 | **Memory management for long sessions** | No instructions for handling context compaction. Add: update files before compaction, re-read on resume. |
| B4 | **Resume capability** | No way to resume after interruption. Agent memory directory and context file should capture current state. |

### C. Agent Behavior

| # | Check | Common Issue |
|---|-------|--------------|
| C1 | **Code is read before assessment** | Agent is asked to evaluate code changes without being told to read the actual source files. Add explicit "Read the file at the relevant location" step. |
| C2 | **IDE integration** | Working with code but no instruction to open files in IDE. Add `code --goto {file}:{line}` step where relevant. |
| C3 | **Output format is specified** | Agent produces unstructured output. Add a template with placeholders. |
| C4 | **Analysis has criteria** | Agent is asked to evaluate but no rubric is given. Add verdict options with definitions (e.g., Correctly Fixed / Partially Fixed / Not Fixed). |

### D. Consistency

| # | Check | Common Issue |
|---|-------|--------------|
| D1 | **Variable syntax is consistent** | Mix of `{{VAR}}`, `GEN:{{VAR}}`, `PRJ:{{VAR}}`, and literal values. Standardize to the project convention. |
| D2 | **Section naming follows convention** | Sections use different patterns across prompts. Align with existing project prompts. |
| D3 | **Language instruction is present** | Prompt should specify output language if not English. Use `GEN:{{Language}}`. |
| D4 | **Persona is defined** | No clear persona or role. Add a persona section at the top. |

## Output Format

```
## Prompt Analysis: {filename}

### Summary
{1-2 sentences on overall quality}

### Findings

#### {Category A/B/C/D}: {Category Name}

| # | Status | Finding | Proposal |
|---|--------|---------|----------|
| {id} | issue | {issue} | {proposed fix} |
| {id} | pass | {passes} | — |

### Proposed Changes

1. {Concrete change 1}
2. {Concrete change 2}
...
```

## Definition of Done

- Prompt file has been read and analyzed against the full checklist
- Each applicable check has a status (pass / issue)
- Proposed changes are presented to the user before applying
- User has approved changes before any edits are made
- Variable conventions are preserved exactly
- Improved file is written only after approval
