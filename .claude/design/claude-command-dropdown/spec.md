# Claude Command Dropdown — Blacklist & Grouping (User-Configurable)

## Problem

The dropdown lists all 22 commands as a flat alphabetical list. Hard to scan, and some commands are meta/utility that clutter the prompt editing view. We want blacklisting and grouping — **per project**, configured by the user via the Project form with multi-select widgets.

## Current State

- **Backend:** `ScratchPadController::loadClaudeCommands()` returns `['name' => 'description']` (flat, alphabetical)
- **Frontend:** `claude.php` renders a flat `<select>` with one `<option>` per command
- **Files:** `ScratchPadController.php` (lines 745-792), `claude.php` (lines 366-392)
- **Project model:** Has a `claude_options` JSON column for CLI config (model, permissionMode, tools, systemPrompt)
- **Existing AJAX pattern:** `ProjectController::actionCheckClaudeConfig()` — JSON endpoint with `viewProject` permission
- **Select2Widget:** Used across codebase with static `items` arrays; the widget supports AJAX via `data-ajax--url`

---

## 1. Storage — Extend `claude_options` JSON

No migration. Add two new keys to the existing `claude_options` JSON column:

```json
{
  "model": "sonnet",
  "permissionMode": "plan",
  "commandBlacklist": ["onboarding", "audit-config", "analyze-codebase"],
  "commandGroups": {
    "Scaffolding": ["new-branch", "new-controller-action", "new-enum", "new-form",
                     "new-migration", "new-model", "new-search", "new-service",
                     "new-test", "new-tests-staged"],
    "Review": ["check-standards", "refactor", "refactor-plan", "review-changes", "triage-review"],
    "Git": ["cp", "finalize-changes"]
  }
}
```

Key difference from previous design: `commandGroups` maps group labels to **explicit command name arrays** (not prefix patterns). Each command is assigned to exactly one group by name. This enables validated multi-select UI.

---

## 2. Blacklist

Array of command names to hide. Stored as `commandBlacklist` in `claude_options`.

- **Empty/null** → show all commands (no filtering)
- **Defaults** → empty; suggested values shown as placeholder text only

---

## 3. Grouping

Object mapping group label → array of command names. Stored as `commandGroups` in `claude_options`.

- **Empty/null** → flat alphabetical list (current behavior)
- Commands assigned to a group appear under that group's `<optgroup>`
- Commands not assigned to any group appear under **"Other"** at the end
- A command can only belong to one group (enforced by UI)
- Groups are rendered in the order they appear in the JSON object

---

## 4. AJAX Endpoint — `ProjectController::actionClaudeCommands`

New JSON endpoint to load available commands from a project's `.claude/commands/` directory.

```php
public function actionClaudeCommands(int $id): array
{
    Yii::$app->response->format = Response::FORMAT_JSON;

    $model = $this->findModel($id);

    if (empty($model->root_directory)) {
        return ['success' => false, 'commands' => []];
    }

    // Reuse ScratchPadController's scanning logic via a shared service or inline
    $commands = $this->loadClaudeCommandsFromDirectory($model->root_directory);

    return [
        'success' => true,
        'commands' => $commands, // ['name' => 'description', ...]
    ];
}
```

### RBAC

Add to `yii/config/rbac.php` under project entity:

```php
'claudeCommands' => 'viewProject',
```

Add `'claude-commands'` to `EntityPermissionService::MODEL_BASED_ACTIONS`.

### Command scanning

Extract `ScratchPadController::loadClaudeCommands()` scanning logic into a **shared method** accessible by both controllers. Options:

- **Option A:** Move scanning to `ProjectService` or a new `ClaudeCommandService` (clean separation)
- **Option B:** Move to `ClaudeCliService` which already has `translatePath()` (minimal new classes)
- **Option C:** Duplicate the ~20 lines in ProjectController (pragmatic but DRY violation)

**Recommended: Option B** — `ClaudeCliService` already handles path translation and Claude config. Add `loadCommands(string $rootDirectory): array` method there. Both controllers call it.

---

## 5. UI in Project Form

Add a new collapsible card in `_form.php`, positioned **after** "Claude CLI Defaults" and **before** "Claude Project Context".

### Form design

```
┌──────────────────────────────────────────────────────────────┐
│  Claude Command Dropdown                                 ▼   │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  Configure which commands appear in the Claude chat          │
│  dropdown and how they are grouped.                          │
│                                                              │
│  ⚠ Set a Root Directory to load available commands.          │  ← shown when no root_directory
│                                                              │
│  Hidden Commands                                             │
│  ┌──────────────────────────────────────────────────────┐    │
│  │ × onboarding  × audit-config  × analyze-codebase    │    │  ← Select2 multi-select
│  └──────────────────────────────────────────────────────┘    │
│  Commands hidden from the Claude chat dropdown.              │
│                                                              │
│  Command Groups                                              │
│                                                              │
│  ┌─ Scaffolding ──────────────────────── [× Remove] ───┐    │
│  │ × new-branch × new-model × new-service × ...        │    │  ← Select2 multi-select
│  └──────────────────────────────────────────────────────┘    │
│  ┌─ Review ───────────────────────────── [× Remove] ───┐    │
│  │ × check-standards × refactor × review-changes × ... │    │
│  └──────────────────────────────────────────────────────┘    │
│  ┌─ Git ──────────────────────────────── [× Remove] ───┐    │
│  │ × cp × finalize-changes                              │    │
│  └──────────────────────────────────────────────────────┘    │
│                                                              │
│  [+ Add Group]                                               │
│                                                              │
│  Leave empty for a flat alphabetical list.                   │
│  Ungrouped commands appear under "Other".                    │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### Interaction flow

1. **On card expand** (or page load if project has `root_directory`): AJAX call to `project/claude-commands?id={projectId}` fetches available commands
2. **Blacklist multi-select:** Populated with all available commands. Pre-selected = currently blacklisted commands.
3. **Group multi-selects:** Each group has:
   - Editable text input for group name
   - Select2 multi-select populated with available commands **minus blacklisted ones minus commands already assigned to other groups**
4. **"Add Group" button:** Appends a new empty group row (name input + multi-select)
5. **"Remove" button:** Removes the group row, commands become unassigned
6. **On save:** JavaScript collects the state into hidden fields as JSON, submitted with the form

### Form field names

Two hidden inputs hold the serialized JSON, submitted as part of `claude_options`:

```html
<input type="hidden" name="claude_options[commandBlacklist]" value='["onboarding","audit-config"]'>
<input type="hidden" name="claude_options[commandGroups]" value='{"Scaffolding":["new-branch",...],...}'>
```

JavaScript keeps these synced with the UI state.

### Available commands pool

Commands available for assignment = all commands from AJAX − blacklisted commands. When a command is assigned to one group, it's removed from other groups' select options (a command can only be in one group).

### Edge cases

| Case | Handling |
|------|----------|
| No `root_directory` on project | Show info alert, disable multi-selects |
| New project (no ID yet) | Disable section, show hint "Save project first to configure commands" |
| `.claude/commands/` not found | AJAX returns empty array, show info "No commands found" |
| Saved command no longer on disk | Keep in config (no auto-cleanup), tooltip "(not found on disk)" |
| Group with empty name | Ignore on save |
| Group with no commands | Ignore on save |

---

## 6. Backend Changes

### 6a. `ClaudeCliService` — extract command scanning

Move scanning logic from `ScratchPadController::loadClaudeCommands()`:

```php
/**
 * Loads available Claude slash commands from a project's .claude/commands/ directory.
 *
 * @return array<string, string> command name => description, sorted alphabetically
 */
public function loadCommandsFromDirectory(?string $rootDirectory): array
{
    if ($rootDirectory === null || trim($rootDirectory) === '') {
        return [];
    }

    $containerPath = $this->translatePath($rootDirectory);
    $commandsDir = rtrim($containerPath, '/') . '/.claude/commands';
    if (!is_dir($commandsDir)) {
        return [];
    }

    $files = glob($commandsDir . '/*.md');
    if ($files === false) {
        return [];
    }

    $commands = [];
    foreach ($files as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);
        $description = $this->parseCommandDescription($file);
        $commands[$name] = $description;
    }

    ksort($commands);
    return $commands;
}
```

Also move `parseCommandDescription()` to this service.

### 6b. `Project` model — convenience getters

```php
public function getClaudeCommandBlacklist(): array
{
    $raw = $this->getClaudeOption('commandBlacklist');
    if (is_array($raw)) {
        return array_values(array_filter($raw, static fn($v): bool => is_string($v) && $v !== ''));
    }
    return [];
}

public function getClaudeCommandGroups(): array
{
    $raw = $this->getClaudeOption('commandGroups');
    if (is_array($raw)) {
        return array_filter(
            $raw,
            static fn($v, $k): bool => is_string($k) && $k !== '' && is_array($v),
            ARRAY_FILTER_USE_BOTH
        );
    }
    return [];
}
```

### 6c. `ScratchPadController::loadClaudeCommands()` — use service + apply config

```php
private function loadClaudeCommands(?string $rootDirectory, Project $project): array
{
    $commands = $this->claudeCliService->loadCommandsFromDirectory($rootDirectory);
    if ($commands === []) {
        return [];
    }

    // Apply blacklist
    $blacklist = $project->getClaudeCommandBlacklist();
    if ($blacklist !== []) {
        $commands = array_diff_key($commands, array_flip($blacklist));
    }

    // Apply grouping
    $groups = $project->getClaudeCommandGroups();
    if ($groups === []) {
        return $commands; // flat list
    }

    $grouped = [];
    $assigned = [];

    foreach ($groups as $label => $commandNames) {
        $groupCommands = [];
        foreach ($commandNames as $name) {
            if (isset($commands[$name]) && !isset($assigned[$name])) {
                $groupCommands[$name] = $commands[$name];
                $assigned[$name] = true;
            }
        }
        if ($groupCommands !== []) {
            $grouped[$label] = $groupCommands;
        }
    }

    // "Other" group for unassigned commands
    $other = array_diff_key($commands, $assigned);
    if ($other !== []) {
        $grouped['Other'] = $other;
    }

    return $grouped;
}
```

### 6d. `ProjectController` — new AJAX action + RBAC

**Action:**

```php
public function actionClaudeCommands(int $id): array
{
    Yii::$app->response->format = Response::FORMAT_JSON;

    $model = $this->findModel($id);

    if (empty($model->root_directory)) {
        return ['success' => false, 'commands' => []];
    }

    return [
        'success' => true,
        'commands' => $this->claudeCliService->loadCommandsFromDirectory($model->root_directory),
    ];
}
```

**RBAC config** (`yii/config/rbac.php`):

```php
'actionPermissionMap' => [
    // ... existing ...
    'claudeCommands' => 'viewProject',
],
```

**Model-based action** (`EntityPermissionService`):

Add `'claude-commands'` to `MODEL_BASED_ACTIONS`.

---

## 7. Frontend Changes

### 7a. `claude.php` — dropdown rendering

Detect grouped vs flat and render `<optgroup>` accordingly:

```javascript
var claudeCommands = $claudeCommandsJson;
var firstValue = Object.values(claudeCommands)[0];
var isGrouped = firstValue !== undefined && typeof firstValue === 'object';

if (isGrouped) {
    Object.keys(claudeCommands).forEach(function(group) {
        var optgroup = document.createElement('optgroup');
        optgroup.label = group;
        Object.keys(claudeCommands[group]).forEach(function(key) {
            var option = document.createElement('option');
            option.value = key + ' ';
            option.textContent = key;
            option.title = claudeCommands[group][key];
            optgroup.appendChild(option);
        });
        commandDropdown.appendChild(optgroup);
    });
} else {
    Object.keys(claudeCommands).forEach(function(key) {
        var option = document.createElement('option');
        option.value = key + ' ';
        option.textContent = key;
        option.title = claudeCommands[key];
        commandDropdown.appendChild(option);
    });
}
```

### 7b. `_form.php` — command dropdown configuration card

JavaScript for the dynamic group management UI:

1. **Fetch commands** on card expand (lazy load):
   ```javascript
   fetch('/project/claude-commands?id=' + projectId)
       .then(r => r.json())
       .then(data => { availableCommands = data.commands; rebuildSelects(); });
   ```

2. **Blacklist Select2:** Standard multi-select with all commands as options.

3. **Group rows:** Each group is a `<div>` with:
   - Text input for group name
   - Select2 multi-select for commands (filtered: no blacklisted, no already-assigned)
   - Remove button

4. **"Add Group" button:** Clones a template row, initializes Select2.

5. **Sync to hidden fields** on any change:
   ```javascript
   function syncHiddenFields() {
       var blacklist = getBlacklistValues();
       var groups = getGroupValues(); // { "Label": ["cmd1", "cmd2"], ... }
       document.getElementById('claude-command-blacklist').value = JSON.stringify(blacklist);
       document.getElementById('claude-command-groups').value = JSON.stringify(groups);
   }
   ```

6. **Cross-select filtering:** When a command is selected in one group, remove it from other groups' available options. When blacklisted, remove from all group selects.

---

## 8. Visual Result (Claude chat dropdown)

```
┌──────────────────────────┐
│ Command              ▼   │
├──────────────────────────┤
│ ── Scaffolding ───────── │
│   new-branch             │
│   new-controller-action  │
│   new-enum               │
│   new-form               │
│   new-migration          │
│   new-model              │
│   new-search             │
│   new-service            │
│   new-test               │
│   new-tests-staged       │
│ ── Review ────────────── │
│   check-standards        │
│   refactor               │
│   refactor-plan          │
│   review-changes         │
│   triage-review          │
│ ── Git ──────────────── │
│   cp                     │
│   finalize-changes       │
│ ── Other ────────────── │
│   switch-db              │
└──────────────────────────┘
```

---

## 9. Files to Change

| File | Change |
|------|--------|
| `yii/services/ClaudeCliService.php` | Add `loadCommandsFromDirectory()` and `parseCommandDescription()` |
| `yii/models/Project.php` | Add `getClaudeCommandBlacklist()` and `getClaudeCommandGroups()` |
| `yii/controllers/ProjectController.php` | Add `actionClaudeCommands()` AJAX endpoint |
| `yii/controllers/ScratchPadController.php` | Refactor `loadClaudeCommands()` to use service + apply blacklist/grouping |
| `yii/views/project/_form.php` | Add "Claude Command Dropdown" collapsible card with dynamic group UI |
| `yii/views/scratch-pad/claude.php` | Update JS to handle grouped data with `<optgroup>` |
| `yii/config/rbac.php` | Add `claudeCommands` action mapping |
| `yii/services/EntityPermissionService.php` | Add `claude-commands` to `MODEL_BASED_ACTIONS` |

**No migration needed** — stored in existing `claude_options` JSON column.

---

## 10. Serialization Flow

```
Project Form (UI)
  │
  │  Select2 multi-selects ──sync──→ hidden inputs (JSON strings)
  │
  ▼
POST claude_options[commandBlacklist] = '["onboarding","audit-config"]'
POST claude_options[commandGroups]    = '{"Scaffolding":["new-branch",...]}'
  │
  ▼
ProjectController::loadClaudeOptions()
  → Yii::$app->request->post('claude_options') returns array
  → $model->setClaudeOptions(['commandBlacklist' => '["..."]', 'commandGroups' => '{"..."}', ...])
  → JSON-encoded into claude_options column
  │
  ▼
ScratchPadController::loadClaudeCommands()
  → $project->getClaudeCommandBlacklist()  → JSON-decoded array
  → $project->getClaudeCommandGroups()     → JSON-decoded object
  → Filter + group commands
  → Pass to claude.php view as JSON
```

**Note on nested JSON:** The form submits `commandBlacklist` and `commandGroups` as JSON strings inside the `claude_options` POST array. `setClaudeOptions()` receives them as strings, which then get JSON-encoded as part of the parent object. On read, `getClaudeOption()` returns the JSON-decoded value — which will be arrays/objects since `json_decode` handles nested JSON. The getter methods handle both cases (already-decoded array or raw string).

---

## 11. Decisions (Resolved)

| Question | Answer |
|----------|--------|
| Blacklist defaults | Empty — show all commands by default |
| Group names | Dynamic per project — user defines group labels |
| Validation | Multi-select UI prevents invalid input; backend validates arrays |
| "Other" group | Fixed label "Other" for unmatched commands |
