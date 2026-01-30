---
allowed-tools: Read, Grep, Glob, Bash(git diff:*), Bash(git status:*), Bash(git log:*)
description: Critically assess an external code review against the actual codebase
---

# Triage Review

Follow the skill contract in `skills/triage-review.md`.

## Context

- Current branch: !`git branch --show-current`
- Current git status: !`git status`
- Recent diff: !`git diff`
- Recent commits: !`git log --oneline -10`

## Task

Critically evaluate the following external code review against the actual codebase and project rules. Classify every comment as Dismissed or Valid with evidence.

$ARGUMENTS
