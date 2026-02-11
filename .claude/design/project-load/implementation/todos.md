# Todos: Project Load Implementation

## Steps

- [x] 1. Create `EntityConfig` — entity definitions, FK mappings, insert order, excludes
- [x] 2. Create `LoadReport` — DTO for tracking loaded/skipped/warnings/errors
- [x] 3. Create `SchemaInspector` — dynamic column detection via INFORMATION_SCHEMA
- [x] 4. Create `DumpImporter` — dump validation, temp schema management, import via proc_open
- [x] 5. Create `PlaceholderRemapper` — PRJ/GEN/EXT placeholder remapping in template_body
- [x] 6. Create `EntityLoader` — entity loading with FK remapping and raw inserts (prev session, reviewed OK)
- [x] 7. Create `ProjectLoadService` — main orchestration (list, load, cleanup, dry-run) (prev session)
- [x] 8. Create `ProjectLoadController` — CLI controller with list/load/cleanup actions (prev session)
- [x] 9. Write integrity tests (§10.1) — EntityConfigIntegrityTest (prev session)
- [x] 10. Write functional tests (§10.2) — LoadReportTest, PlaceholderRemapperTest, DumpImporterTest, EntityLoaderTest, ProjectLoadServiceTest
- [x] 11. Run linter + tests, fix issues — all 877 tests pass, 0 errors, linter clean
