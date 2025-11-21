# Commit Message Instructions

Generate **short**, **consistent**, and **imperative** commit messages.

## Format
<TYPE>: <description in lowercase>

## Types
- **ADD**: New feature, file, or functionality.
- **DEL**: Remove code, files, or functionality.
- **CHG**: Modify existing logic or behavior (use ADD if unsure).
- **FIX**: Fix a bug without introducing new behavior.
- **DOC**: Documentation only.
- **TXT**: Text/copy changes (labels, titles, wording).
- **REFACTOR**: Structural code changes without behavior change.
- **BRANCH**: Branch creation or maintenance.
- **MERGE**: Merge commits.
- **REVERT**: Revert a previous commit.
- **TAG**: Release tagging.

## Rules
- Use **imperative voice** ("Add", "Fix", "Change").
- Lowercase description after colon.
- Keep descriptions **≤ 72 chars**.
- **No emojis, no trailing punctuation**.

## Examples
ADD: create order form
CHG: support multiplication in calculator
FIX: retain user ID on language switch
TXT: update submit button label
REFACTOR: move order editing to separate class
