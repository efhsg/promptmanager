# Directory Selector Widget — Analyse en Verbeterplan

## 1. Probleemanalyse

### Gerapporteerde problemen

1. **Root niet zichtbaar**: De gebruiker ziet `/` in plaats van de project root directory. Dit geeft geen context over waar men zich bevindt.
2. **Autocomplete werkt niet**: De dropdown toont nooit matches.

### Root Cause Analyse

#### Probleem 1: Root niet zichtbaar

**Huidige situatie:**
- De backend (`PathService::collectPaths()`) retourneert relatieve paden vanaf de project root
- Paden beginnen met `/` wat de project root vertegenwoordigt (zie regel 26, 139)
- De UI toont alleen `/` als default waarde, niet de absolute project root

**Waarom dit verwarrend is:**
- Gebruiker ziet `/` maar weet niet dat dit `/var/projects/my-project` vertegenwoordigt
- Er is geen visuele indicatie van de project root in de modal

**Oplossing:** ✅ GEÏMPLEMENTEERD
- Project root wordt nu getoond boven de directory input
- Preview path toont nu het volledige absolute pad

#### Probleem 2: Autocomplete werkt niet

Na analyse van de code is de flow:

1. `ExportModal.open()` wordt aangeroepen met config
2. `loadDirectories(projectId)` wordt aangeroepen (regel 413)
3. `DirectorySelector.load()` fetcht van `/field/path-list?projectId={id}&type=directory`
4. Response bevat `paths: ["/", "/docs", "/src", ...]`
5. Bij input worden paths gefilterd met `includes(query)` (regel 122-123)

**Mogelijke oorzaken:**

1. **Project ID niet doorgegeven**: Als `currentConfig.projectId` `null` of `0` is, laadt de selector geen paden
2. **Async timing**: `loadDirectories()` is async maar er wordt niet gewacht op completion
3. **Cache leeg**: Als de fetch faalt, blijft `cache` leeg
4. **Root ontbreekt in response**: De endpoint kan falen als project geen `root_directory` heeft

**Verificatie nodig:**
- Check of de fetch request daadwerkelijk wordt gedaan (Network tab)
- Check of response `success: true` en `paths` array bevat

## 2. Geïmplementeerde Wijzigingen

### Fase 1: Root Weergave (✅ Voltooid)

De volgende wijzigingen zijn doorgevoerd om de project root zichtbaar te maken:

#### 2.1 Form Views — Data Uitbreiding

**Bestanden gewijzigd:**
- `yii/views/note/_form.php`
- `yii/views/context/_form.php`
- `yii/views/prompt-template/_form.php`

**Wijziging:** `projectDataForExport` bevat nu ook `rootDirectory`:

```php
$projectDataForExport[$projectId] = [
    'hasRoot' => $project && !empty($project->root_directory),
    'rootDirectory' => $project->root_directory ?? null,  // NIEUW
];
```

**Wijziging:** Nieuwe callback `getRootDirectory` toegevoegd aan `setupExportContent`:

```javascript
window.QuillToolbar.setupExportContent(quill, hidden, {
    getProjectId: () => ...,
    getEntityName: () => ...,
    getHasRoot: () => ...,
    getRootDirectory: () => {  // NIEUW
        var selectedProjectId = projectSelect ? projectSelect.value : null;
        var projectInfo = selectedProjectId ? (projectData[selectedProjectId] || {}) : {};
        return projectInfo.rootDirectory || null;
    }
});
```

#### 2.2 Editor Init — Config Doorgifte

**Bestand gewijzigd:** `npm/src/js/editor-init.js`

**Wijziging:** `rootDirectory` wordt nu doorgegeven aan `ExportModal.open()`:

```javascript
window.ExportModal.open({
    projectId: config.getProjectId ? config.getProjectId() : null,
    entityName: config.getEntityName ? config.getEntityName() : 'export',
    hasRoot: config.getHasRoot ? config.getHasRoot() : false,
    rootDirectory: config.getRootDirectory ? config.getRootDirectory() : null,  // NIEUW
    getContent: () => JSON.stringify(quill.getContents())
});
```

#### 2.3 Export Modal — UI Weergave

**Bestand gewijzigd:** `yii/views/layouts/_export-modal.php`

**HTML wijziging:** Root display toegevoegd onder Directory label:

```html
<div class="mb-3">
    <label for="export-directory" class="form-label fw-bold">Directory</label>
    <small class="text-muted d-block mb-1" id="export-root-display-wrapper">
        Project root: <code id="export-root-display"></code>
    </small>
    <!-- bestaande input -->
</div>
```

**JavaScript wijzigingen:**

1. `currentConfig` bevat nu `rootDirectory`
2. `getElements()` bevat `rootDisplay` en `rootDisplayWrapper`
3. `open()` vult de root display:
   ```javascript
   if (currentConfig.rootDirectory) {
       el.rootDisplay.textContent = currentConfig.rootDirectory;
       el.rootDisplayWrapper.classList.remove('d-none');
   } else {
       el.rootDisplay.textContent = '';
       el.rootDisplayWrapper.classList.add('d-none');
   }
   ```
4. `updatePreviewPath()` toont nu het absolute pad:
   ```javascript
   const rootDir = currentConfig.rootDirectory || '';
   const normalizedRoot = rootDir.endsWith('/') ? rootDir.slice(0, -1) : rootDir;
   const absolutePath = normalizedRoot + normalizedRelativeDir + sanitizeFilename(filename) + ext;
   el.previewPath.textContent = absolutePath;
   ```

### 2.4 Build Stap Vereist

**⚠️ ACTIE NODIG:** Na deze wijzigingen moet het minified JS bestand opnieuw gegenereerd worden:

```bash
# Via Docker (standaard)
docker compose run --entrypoint bash pma_npm -c "npm run build-init"

# Of lokaal met Node.js
cd npm && node ./node_modules/uglify-js/bin/uglifyjs src/js/editor-init.js -o ../yii/web/quill/1.3.7/editor-init.min.js
```

## 3. Nog Te Onderzoeken: Autocomplete Issue

Het autocomplete probleem vereist verdere debugging. Mogelijke stappen:

1. **Browser debugging:**
   - Open Network tab
   - Open export modal
   - Controleer of `/field/path-list` request wordt gedaan
   - Controleer response payload

2. **Mogelijke fixes:**
   - Verifieer dat project ID correct is (niet `null` of `0`)
   - Controleer of `DirectorySelector.load()` succesvol compleet
   - Voeg console logging toe voor debugging

## 4. Toekomstige Verbeteringen

### Fase 2: UX Verbetering (Later)

1. **Hiërarchische dropdown** met:
   - Paden gesorteerd per niveau
   - Visuele inspringen voor diepte
   - Folder icons

2. **Keyboard navigatie** verbeteren:
   - Tab/arrow navigatie in dropdown
   - Enter om te selecteren

### Library Opties

| Library | Pros | Cons |
|---------|------|------|
| **jstree** | Mature, feature-rich, jQuery compatible | jQuery dependency |
| **Fancytree** | Lazy loading, keyboard nav | jQuery dependency |
| **Bootstrap 5 native** | Geen extra dependencies | Zelf bouwen nodig |

**Aanbeveling:** Bestaande autocomplete verbeteren met hiërarchische weergave is minste werk en voldoende voor de use case.

## 5. Conclusie

**Probleem 1 (Root niet zichtbaar):** ✅ Opgelost
- Project root wordt nu getoond in de modal
- Preview path toont volledige absolute pad

**Probleem 2 (Autocomplete werkt niet):** 🔍 Vereist debugging
- Mogelijke oorzaken geïdentificeerd
- Verificatie nodig via browser Network tab
