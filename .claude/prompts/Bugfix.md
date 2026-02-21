## Instructions

You are fixing a bug in an existing PHP 8.2 Yii 2 application. Your primary goal is to eliminate the described bug while preserving all other observable behavior, unless the task explicitly authorizes a specific behavior change.

# Bug

GEN:{{Description}}

## Hard Requirements

- Respect existing Yii 2 patterns (ActiveRecord models, controllers, forms, views, DI/config, console commands, behaviors, components, etc.). Fix the bug within the current architecture; do not replace it with other frameworks or custom architectures.
- Make the smallest, clearest change that fully resolves the bug. Avoid speculative abstractions, broad refactors, or optimizations that are not strictly required to fix the issue.
- Preserve backward compatibility of existing public APIs, data formats, and behaviors, except where directly implicated by the bug and explicitly authorized to change. Do **not** silently change unrelated behavior.
- Apply SOLID and DRY principles only as far as needed to remove duplication, tight coupling, or obvious design smells directly causing or aggravating the bug. Stop once the bug is fixed and the goal is met.
- Keep responsibilities focused: introduce new methods and classes only when they are clearly justified by the bug fix and follow existing project naming conventions.
- When dealing with external I/O (DB, HTTP, filesystem, email, queues), follow the project’s existing patterns (components, services, repositories, etc.). Do **not** introduce new third-party libraries unless explicitly requested.
- Ensure the fixed code is testable. Where appropriate, add or adjust tests so that the bug is covered and regressions are less likely.

## Bugfix Phases

### Phase 1 – Diagnosis

- Analyze the bug described in Bug description and Bug reproduction steps within the boundaries defined in Bug scope.
- Identify the most likely root cause in the provided code and configuration, including which files and methods are involved.
- Define the minimal, concrete steps required to fix the bug and prevent regression, grouped by file (including tests that should be updated or added).
- If any required file, configuration, or dependency referenced by the code or by the bug description is missing, **stop** after this phase and explicitly request those artifacts instead of guessing their contents.

Present diagnosis:

```
## Diagnose

{root cause, betrokken bestanden, fix-plan}

Start fix / Plan aanpassen?
```

**Wacht op gebruikersinput. Ga NIET door totdat de gebruiker reageert.**

### Phase 2 – Fix

- Once all necessary files, configurations, and dependencies are available, implement the bug fix according to the plan from Phase 1.
- Keep changes cohesive and minimal: only modify code that directly contributes to resolving the bug within Bug scope.
- Add or adjust tests so the bug is covered and the expected behavior is verified.
- Do not introduce new behavior except what is required to correct the bug or is explicitly authorized in the task.

## Output Rules

- Unless the task says otherwise, assume that only a specific subset of files may be changed. Do not modify or create files outside those explicitly mentioned or implied in Allowed files.
- For any file you are asked to change, output the complete, final contents of that file, fully formatted, with all necessary namespace and use statements included.
- Do **not** output diffs, inline explanations, or commentary—only the final code for each file in the exact format the task specifies.
- Keep your changes cohesive: only include code that is directly related to the bug fix and its minimal wiring.
- Never add declare(strict_types=1); to any file.
- If the task does not specify an output format, return each modified file as a complete code block containing exactly the file contents and nothing else.

## Afsluiting

Na Phase 2, toon samenvatting:

{gewijzigde bestanden, testresultaat, wat de fix doet}

Commit wijzigingen / Review wijzigingen / Aanpassen?

**Wacht op gebruikersinput. Ga NIET door totdat de gebruiker reageert.**