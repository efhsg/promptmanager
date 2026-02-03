# Claude Command Dropdown — Design Spec

## Feature

Add a dropdown to the Quill editor toolbar in the Claude CLI dialog (`claude.php`) that lists all available slash commands. Selecting a command inserts `/<command> ` (with `/` prefix and trailing space) as plain text at the cursor position.

The dropdown is **only** available in the Claude chat editor — no other Quill instances are affected.

## User Story

As a user chatting with Claude via the browser interface, I want quick access to all available slash commands so I can insert them without memorizing or typing them manually.

## Behavior

1. A `<select>` dropdown labeled **"Command"** appears in the Quill toolbar (after `clean`, before `smartPaste`).
2. The dropdown lists all slash commands alphabetically (flat list, no grouping).
3. Selecting a command:
   - Inserts `/<command-name> ` at the current cursor position (plain text).
   - Moves the cursor to after the inserted text.
   - Resets the dropdown to its placeholder label (`this.selectedIndex = 0`).
4. If no cursor position exists, the text is inserted at position 0.
5. The dropdown is only visible when the Quill editor is active (not in textarea mode).

## Command Source

Commands are discovered **dynamically** from the project's `.claude/commands/` directory. Each command is a `.md` file with YAML frontmatter containing a `description` field:

```
.claude/commands/
├── analyze-codebase.md    # description: Generate comprehensive analysis...
├── audit-config.md        # description: Audit config completeness...
├── cp.md                  # description: Commit staged changes and push...
├── new-model.md           # description: Create an ActiveRecord model...
└── ...
```

**Why dynamic?** Commands change over time. A static list would require code changes every time a command is added, renamed, or removed. Scanning the directory ensures the dropdown always reflects the current state.

### Parsing Rules

- **Directory**: `{project.root_directory}/.claude/commands/`
- **Command name**: Filename without `.md` extension (e.g., `new-model.md` → `new-model`)
- **Description**: Extracted from YAML frontmatter `description:` field
- **Ordering**: Alphabetical by command name
- **No grouping**: Flat list for simplicity

## Technical Plan

### Approach: Dynamic filesystem scan, passed as JSON

Scan the `.claude/commands/` directory in `ScratchPadController::actionClaude()`, extract command names and descriptions from the file frontmatter, sort alphabetically, and pass to the view as JSON.

### Data Flow

```
ScratchPadController::actionClaude()
  → Resolve project root_directory
  → Scan {root_directory}/.claude/commands/*.md
  → Parse YAML frontmatter for description
  → Sort alphabetically by command name
  → Pass to view as $claudeCommands
  → Json::encode() in view
  → JavaScript builds <select> dropdown
  → On change: insert "/<command> " at cursor, reset to placeholder
```

### Implementation Steps

#### 1. Controller — `ScratchPadController::actionClaude()`

Scan the commands directory and build the list dynamically:

```php
public function actionClaude(int $id): string
{
    $model = $this->findModel($id);

    if ($model->project_id === null) {
        throw new NotFoundHttpException('Claude CLI requires a project.');
    }

    $claudeCommands = $this->loadClaudeCommands($model->project->root_directory);

    return $this->render('claude', [
        'model' => $model,
        'projectList' => Yii::$app->projectService->fetchProjectsList(Yii::$app->user->id),
        'claudeCommands' => $claudeCommands,
    ]);
}

/**
 * Loads available Claude slash commands from the project's .claude/commands/ directory.
 *
 * @return array<string, string> command name => description, sorted alphabetically
 */
private function loadClaudeCommands(?string $rootDirectory): array
{
    if ($rootDirectory === null) {
        return [];
    }

    $commandsDir = rtrim($rootDirectory, '/') . '/.claude/commands';
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

/**
 * Extracts the description from a command file's YAML frontmatter.
 */
private function parseCommandDescription(string $filePath): string
{
    $content = file_get_contents($filePath);
    if ($content === false) {
        return '';
    }

    // Match YAML frontmatter between --- delimiters
    if (preg_match('/^---\s*\n(.*?)\n---/s', $content, $matches)) {
        // Extract description field
        if (preg_match('/^description:\s*(.+)$/m', $matches[1], $descMatch)) {
            return trim($descMatch[1]);
        }
    }

    return '';
}
```

#### 2. View — `claude.php`

**2a. Toolbar config**

Add the custom dropdown placeholder to the toolbar array:

```javascript
toolbar: [
    ['bold', 'italic', 'underline', 'strike', 'code'],
    ['blockquote', 'code-block'],
    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
    [{ 'indent': '-1' }, { 'indent': '+1' }],
    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
    [{ 'align': [] }],
    ['clean'],
    [{ 'insertClaudeCommand': [] }],   // <-- NEW
    [{ 'smartPaste': [] }],
    [{ 'loadMd': [] }]
]
```

**2b. Dropdown construction**

After Quill init, build the `<select>` element following the same pattern as the field dropdowns in `prompt-template/_form.php`:

```javascript
var commandsJson = <?= Json::encode($claudeCommands) ?>;
var toolbar = quill.getModule('toolbar');
var toolbarContainer = toolbar.container;

var commandDropdown = document.createElement('select');
commandDropdown.classList.add('ql-insertClaudeCommand', 'ql-picker', 'ql-font');
commandDropdown.innerHTML = '<option value="" selected disabled>Command</option>';

Object.keys(commandsJson).forEach(function(key) {
    var option = document.createElement('option');
    option.value = '/' + key + ' ';
    option.textContent = '/' + key;
    option.title = commandsJson[key]; // tooltip with description
    commandDropdown.appendChild(option);
});

toolbarContainer.querySelector('.ql-insertClaudeCommand').replaceWith(commandDropdown);

commandDropdown.addEventListener('change', function() {
    var value = this.value;
    if (value) {
        var range = quill.getSelection();
        var position = range ? range.index : 0;
        quill.insertText(position, value);
        quill.setSelection(position + value.length);
        this.selectedIndex = 0;
    }
});
```

#### 3. CSS — `site.css`

Add `.ql-insertClaudeCommand` to the existing custom picker selector:

```css
.ql-toolbar .ql-picker.ql-insertGeneralField,
.ql-toolbar .ql-picker.ql-insertProjectField,
.ql-toolbar .ql-picker.ql-insertExternalField,
.ql-toolbar .ql-picker.ql-insertClaudeCommand {
    width: auto;
    padding: 2px 2px;
    margin: 8px 0 4px 8px;
    border: 1px solid #ccc;
    border-radius: 3px;
    background-color: #fff;
    color: #444;
    font: smaller sans-serif;
    cursor: pointer;
}
```

### Files Changed

| File | Change |
|------|--------|
| `yii/controllers/ScratchPadController.php` | Add `loadClaudeCommands()` and `parseCommandDescription()` private methods; pass `$claudeCommands` from `actionClaude()` |
| `yii/views/scratch-pad/claude.php` | Add `insertClaudeCommand` to toolbar + dropdown construction JS |
| `yii/web/css/site.css` | Add `.ql-insertClaudeCommand` to custom picker CSS selector |

### No Changes Needed

- `editor-init.js` — dropdown is view-specific, not global
- `editor-init.min.js` — no rebuild needed
- No migrations
- No new models or services

### Testing

- **Unit test**: `loadClaudeCommands()` and `parseCommandDescription()` — test with fixture directory containing sample `.md` files with frontmatter
- **Manual verification**: open Claude chat, verify dropdown appears with alphabetically sorted commands, select command, verify insertion with `/` prefix and trailing space, verify dropdown resets to "Command" placeholder

## Edge Cases

| Case | Handling |
|------|----------|
| **No cursor** | Insert at position 0 |
| **Text selected** | Quill's `insertText` inserts at selection start (standard behavior) |
| **No commands directory** | `loadClaudeCommands()` returns `[]`; dropdown shows only placeholder |
| **No `root_directory` on project** | `loadClaudeCommands(null)` returns `[]`; dropdown shows only placeholder |
| **Command file without frontmatter** | `parseCommandDescription()` returns `''`; command still listed, no tooltip |
| **Textarea mode active** | Quill toolbar is hidden; dropdown is not accessible — correct behavior |
| **Dropdown reset** | Uses `selectedIndex = 0` instead of `value = ''` to reliably show placeholder across browsers |

## Security

- File path is constructed from `project.root_directory` which is validated at project level
- Only `.md` files are read; only the `description` frontmatter field is extracted
- No user input influences the glob pattern

## Out of Scope

- Command autocompletion while typing
- Filtering/search within the dropdown
- Command descriptions visible in the dropdown (only as tooltips)
- Dynamic loading of commands via AJAX
- Grouping by category (can be added later if needed)
