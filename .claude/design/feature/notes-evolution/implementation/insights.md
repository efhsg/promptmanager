# Insights — Notes Evolution

## Decisions
- NoteService follows FieldService pattern: standalone class, no Component extension
- saveNote() accepts assoc array (not DTO), consistent with current actionSave() pattern
- Migrations use string literals, no enum/model dependencies
- No ScratchPadQueryTest exists — will create NoteQueryTest fresh

## Findings
- ScratchPad model uses TimestampTrait for beforeSave timestamps
- created_at/updated_at are INT (unix timestamps) stored as string type in rules
- No existing ScratchPadService — all logic is in controller
- EntityLoader references `scratch_pad` in countLocalEntities()
- ClaudeQuickHandler has `scratch-pad-name` use case key
- `response-summary` use case is independent, no rename needed
