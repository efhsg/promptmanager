# Problem: Provider Selection UX Is Complex, Hidden, and Incomplete

## Context

The AI Chat view (`yii/views/ai-chat/index.php`) lets users choose a **provider** (e.g. Claude, Codex), a **model**, a **permission mode**, and **provider-specific settings** (e.g. Codex reasoning level) before starting a conversation.

We recently added **provider locking** — once the user sends the first message, all settings dropdowns are disabled and only unlock again on "New Session". This solved the problem of switching providers mid-dialog (which is pointless because context doesn't carry over between providers).

## The Problem

The current settings panel has several UX issues that make provider selection confusing and error-prone:

### 1. Settings are hidden by default

On page load, the full settings card (`#claudeSettingsCardWrapper`) is hidden (`d-none`). The user only sees a compact **combined bar** showing badge summaries (project, git branch, provider, model, permission mode). To actually *change* any setting, they must first click the combined bar to expand the full settings card.

**Issue:** A new user — or a user who wants to switch provider — may not realize they need to click the summary bar to access the dropdowns. The summary bar looks informational, not interactive.

### 2. Settings collapse again on first send

When the first message is sent, `collapseSettings()` is called automatically, hiding the card. Combined with the new provider locking, this means the settings are both hidden *and* disabled. But visually, the summary bar still shows clickable badges — clicking them expands a card full of disabled dropdowns, which is confusing.

**Issue:** After the first send, expanding the settings card reveals disabled controls with no explanation of *why* they're disabled or how to unlock them (answer: "New Session").

### 3. No visual feedback that settings are locked

The `lockProvider()` method sets `disabled = true` on the dropdowns, but there's no visible indicator on the **combined bar** that the session is active and settings are frozen. The summary badges look the same whether or not settings are locked.

**Issue:** Users can't tell from the combined bar alone whether they're in an active session (locked settings) or a fresh state (editable settings).

### 4. The combined bar is overloaded

The combined bar serves multiple purposes:
- Shows project context (project name, git branch) — always relevant
- Shows current settings (provider, model, permission mode) — only relevant before first send
- Shows config status badge — diagnostic info
- Acts as a toggle button for the settings panel

These are different concerns crammed into one element. The project context is static, the settings are pre-session choices, and the config badge is a health indicator.

### 5. `newSession()` expands settings but user may not expect it

When the user clicks "New Session", settings are unlocked and the card is expanded. This is correct behavior but may feel jarring — the UI suddenly shifts to show a full settings card they may not want to interact with.

## Current Flow

```
Page load → Settings collapsed (hidden), combined bar visible with badges
         → User clicks combined bar → Settings card expands, combined bar settings section hides
         → User picks provider/model/settings
         → User clicks chevron inside card → Settings collapse, combined bar updates

First send → Settings auto-collapse + lock (all dropdowns disabled)
          → Combined bar still looks clickable
          → Clicking combined bar shows disabled dropdowns (no explanation)

New Session → Settings unlock + expand
           → User can reconfigure and start fresh
```

## Questions to Analyse

1. **Should the settings card be expanded by default** on fresh page load (no session history), so the user immediately sees the provider choice?
2. **Should the combined bar indicate lock state** — e.g. a lock icon, muted badges, or different background when the session is active?
3. **Should expanding a locked settings panel show an explanatory message** like "Settings are locked during an active session. Start a New Session to change them"?
4. **Should the combined bar still be clickable** when settings are locked, or should the toggle be disabled too?
5. **Should settings and project context be visually separated** in the combined bar?
6. **Is the current `newSession()` behavior** (auto-expand settings) the right UX, or should it just unlock without expanding?

## Desired Outcome

A clear, intuitive flow where:
- The user can easily find and change provider/model/settings **before** starting a conversation
- Once a conversation starts, it's visually obvious that settings are locked to this session
- The path to unlocking (New Session) is discoverable
- The UI doesn't show interactive-looking elements that are actually disabled
