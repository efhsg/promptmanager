---
allowed-tools: Bash(find:*), Bash(ls:*), Bash(grep:*), Bash(cat:*), Bash(diff:*), Read, Grep, Glob
description: Audit all Claude configuration files for completeness, correctness, and consistency
---
# Configuration Audit

Audit all Claude Code configuration files against the codebase understanding.

## Files to Audit

### Main Configuration
@CLAUDE.md

### Project Configuration
@.claude/config/project.md

### Rules
@.claude/rules/coding-standards.md
@.claude/rules/architecture.md
@.claude/rules/security.md
@.claude/rules/testing.md
@.claude/rules/commits.md
@.claude/rules/workflow.md
@.claude/rules/skill-routing.md

### Skills Index
@.claude/skills/index.md

### Other AI Configurations
@AGENTS.md
@GEMINI.md

## Commands List
!`ls -1 .claude/commands/*.md | xargs -I {} basename {}`

## Skills List
!`ls -1 .claude/skills/*.md | xargs -I {} basename {}`

## Codebase Context

### Current Controllers
!`ls -1 yii/controllers/*.php 2>/dev/null | xargs -I {} basename {} .php`

### Current Models
!`ls -1 yii/models/*.php 2>/dev/null | xargs -I {} basename {} .php`

### Current Services
!`ls -1 yii/services/*.php 2>/dev/null | xargs -I {} basename {} .php`

### Current Enums
!`ls -1 yii/common/enums/*.php 2>/dev/null | xargs -I {} basename {} .php`

### RBAC Rules
!`ls -1 yii/rbac/*.php 2>/dev/null | xargs -I {} basename {} .php`

---

## Audit Instructions

Perform a comprehensive audit checking:

### 1. Completeness
- Are all domain entities documented?
- Are all services mentioned in architecture/codebase_analysis?
- Are all RBAC rules listed in security.md?
- Are all commands documented in skills/index.md?
- Are all skills documented in skills/index.md?
- Missing patterns or conventions that exist in code but not in rules?

### 2. Correctness
- Do documented paths match actual file locations?
- Are command examples correct and working?
- Do entity relationships match the actual models?
- Are deprecated patterns still documented?
- Do test commands work as documented?

### 3. Duplicates & Conflicts
- Same rule defined in multiple places with different wording?
- Contradictory instructions between CLAUDE.md and rules files?
- Overlapping responsibilities between skills?
- Commands in CLAUDE.md that don't match skills/index.md?

### 4. AGENTS.md & GEMINI.md Alignment
- Both must reference CLAUDE.md as single source of truth
- Both must have identical structure (only tool name differs)
- Quick reference commands must match CLAUDE.md
- No additional rules that contradict CLAUDE.md

## Output Format

Provide a structured report:

```markdown
# Configuration Audit Report

## Summary
- Total issues found: X
- Critical: X | Warning: X | Info: X

## Completeness Issues
- [ ] Issue description — file:location

## Correctness Issues
- [ ] Issue description — file:location

## Duplicates & Conflicts
- [ ] Issue description — files involved

## AGENTS.md / GEMINI.md Issues
- [ ] Issue description

## Recommendations
1. Priority fix: ...
2. ...
```

After the report, ask the user which issues to fix.
