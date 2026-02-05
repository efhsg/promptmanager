# Quill Toolbar Testing Recommendations

## Context

The `npm/src/js/editor-init.js` file contains custom Quill toolbar button implementations:
- `setupClearEditor` — Clear editor with undo functionality
- `setupSmartPaste` — Smart paste from clipboard with markdown detection
- `setupLoadMd` — Load markdown from file

## Current State

The project has no JavaScript testing infrastructure. All testing is PHP-based using Codeception.

## Recommendation: Add JavaScript Testing

### Suggested Framework

**Jest** — Modern, zero-config JavaScript testing framework with good browser API mocking

```json
{
  "devDependencies": {
    "jest": "^29.0.0",
    "jest-environment-jsdom": "^29.0.0"
  },
  "scripts": {
    "test": "jest",
    "test:watch": "jest --watch"
  }
}
```

### Test Coverage Priorities

#### 1. setupClearEditor (HIGH)

**Test cases:**
- ✓ Should render trash icon initially
- ✓ Should clear editor content when clicked
- ✓ Should switch to undo icon after clear
- ✓ Should restore content when undo icon clicked
- ✓ Should sync hidden field after clear
- ✓ Should sync hidden field after undo
- ✓ Should reset to trash icon after user types (following clear)
- ✓ Should handle empty editor (no-op when length <= 1)

**Example test structure:**
```javascript
describe('setupClearEditor', () => {
  let quill, hidden, container;

  beforeEach(() => {
    // Mock Quill instance
    container = document.createElement('div');
    quill = {
      getModule: jest.fn(() => ({ container })),
      getLength: jest.fn(() => 10),
      deleteText: jest.fn(),
      getContents: jest.fn(() => ({ ops: [] })),
      history: { undo: jest.fn() },
      on: jest.fn()
    };
    hidden = document.createElement('input');
  });

  it('should clear editor and show undo icon', () => {
    setupClearEditor(quill, hidden);
    const btn = container.querySelector('.ql-clearEditor');

    btn.click();

    expect(quill.deleteText).toHaveBeenCalledWith(0, 9);
    expect(btn.innerHTML).toContain('UNDO_SVG');
  });
});
```

#### 2. setupSmartPaste (MEDIUM)

**Test cases:**
- ✓ Should read from clipboard API
- ✓ Should show spinner during fetch
- ✓ Should insert delta at cursor position
- ✓ Should replace entire content if editor empty
- ✓ Should show appropriate toast messages
- ✓ Should handle fetch errors gracefully
- ✓ Should disable button during operation

#### 3. setupLoadMd (MEDIUM)

**Test cases:**
- ✓ Should open file dialog
- ✓ Should read markdown file
- ✓ Should convert to Quill delta
- ✓ Should handle file read errors
- ✓ Should show appropriate toast messages

### Browser API Mocking

These functions use browser APIs that need mocking:
- `navigator.clipboard.readText()` — Clipboard API
- `fetch()` — Network requests
- `bootstrap.Toast` — Bootstrap toast UI
- `FileReader` — File reading

### Integration vs Unit Testing

**Unit tests** (recommended for current need):
- Test each `setup*` function in isolation
- Mock Quill, DOM, and browser APIs
- Fast, reliable, no external dependencies

**Integration tests** (future consideration):
- Test actual Quill editor with custom buttons
- Requires headless browser (Playwright, Cypress)
- Slower but catches real-world issues

## Alternative: Manual Testing Checklist

Until automated tests are added, use this checklist for manual testing:

### Clear Editor Button
- [ ] Click trash icon — editor clears completely
- [ ] Verify icon changes to undo (curved arrow)
- [ ] Click undo icon — content restored
- [ ] Verify icon changes back to trash
- [ ] Type after clear — icon resets to trash
- [ ] Click trash when editor empty — no error

### Smart Paste Button
- [ ] Copy plain text — pastes at cursor
- [ ] Copy markdown — converts to formatted content
- [ ] Paste into empty editor — replaces all
- [ ] Paste into populated editor — inserts at cursor
- [ ] Empty clipboard — shows warning toast

### Load Markdown Button
- [ ] Select .md file — loads and formats
- [ ] Select non-markdown file — shows error
- [ ] Cancel file dialog — no error
- [ ] Large file — handles without freezing

## Implementation Priority

1. **Immediate:** Add manual testing checklist to QA process
2. **Short-term:** Set up Jest for `editor-init.js` unit tests
3. **Long-term:** Consider Playwright for full integration tests

## Related Files

- `npm/src/js/editor-init.js` — Implementation
- `npm/package.json` — Dependency management
- `.claude/rules/testing.md` — Testing standards (PHP-focused)
