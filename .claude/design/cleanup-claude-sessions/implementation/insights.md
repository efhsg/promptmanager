# Insights

## Decisions
- Service is plain class (no `extends Component`), consistent with `ClaudeStreamRelayService`
- No DI config change needed — Yii2 auto-wires constructor type hints
- Ownership enforced via `forUser()` query scope, consistent with existing run endpoints
- Stream files deleted before DB transaction (idempotent, orphaned files acceptable)

## Findings
- ClaudeController constructor already has 4 DI services — adding 5th follows same pattern
- `ClaudeRunQuery` already has all needed scopes: `forUser()`, `terminal()`, `forSession()`
- `ClaudeRun::isTerminal()` checks against `ClaudeRunStatus::terminalValues()`
- Existing test pattern uses Codeception `Unit` with `verify()` assertions
- VerbFilter only needed for POST-only endpoints; `cleanup` supports GET+POST

## Pitfalls
- `ClaudeControllerTest` has two factory methods that construct `ClaudeController` manually — both needed the new `ClaudeRunCleanupService` parameter added (caused 28 test errors until fixed)

## Result
- Linter: 0 issues
- Tests: 1067 pass, 0 errors, 0 failures, 21 skipped (pre-existing)
- All 13 new ClaudeRunCleanupServiceTest tests pass
