# Reviews

## Review: Architect — 2026-02-14

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties
- [x] Componenten hebben file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet (n.v.t. — geen backend wijzigingen)
- [x] Wireframe-component alignment
- [x] Test scenarios compleet
- [x] Herbruikbare componenten geïdentificeerd

### Goed
- Minimale oplossing: één bestand wijzigen, geen nieuwe services/controllers
- Hergebruik van bestaand convert-format endpoint — geen duplicatie
- Past bij bestaand patroon (Import modal heeft ook Client/Server toggle)
- Architectuurbeslissing "puur frontend" is correct — geen server-side file write nodig

### Verbeterd
- EXTENSION_MAP en MIME_TYPE_MAP samengevoegd tot één FORMAT_MAP — voorkomt twee losse mappings die uit sync kunnen raken
- `URL.revokeObjectURL()` cleanup expliciet vermeld — voorkomt memory leak bij herhaald downloaden

### Nog open
- Geen

## Review: Security — 2026-02-14

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties
- [x] Componenten hebben file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet
- [x] Wireframe-component alignment
- [x] Test scenarios compleet
- [x] Herbruikbare componenten geïdentificeerd

### Goed
- Geen nieuwe backend endpoints — geen nieuw aanvalsoppervlak
- "Local" download is puur client-side — geen server-side file path risico
- Hergebruik van bestaand `/note/convert-format` endpoint dat al beveiligd is (`@` access + CSRF)
- Filename sanitization via bestaande `sanitizeFilename()` — geen XSS via download filename
- Content flow (Delta → server conversie → Blob) introduceert geen injection vectors

### Verbeterd
- Geen spec wijzigingen nodig

### Nog open
- Geen

## Review: UX/UI Designer — 2026-02-14

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties
- [x] Componenten hebben file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet
- [x] Wireframe-component alignment
- [x] Test scenarios compleet
- [x] Herbruikbare componenten geïdentificeerd

### Goed
- Drie destination opties passen in bestaand btn-group patroon
- Wireframe is helder met duidelijke sectie-splits (filename vs directory)
- UI states voor alle drie destinations beschreven
- Accessibility: ARIA labels, keyboard navigatie, label-for attributen

### Verbeterd
- Label "Local" → "Download" — beschrijft de actie, niet de techniek; gebruiksvriendelijker
- Icon `bi-download` toegevoegd aan Download knop — consistent met Clipboard (`bi-clipboard`) en File (`bi-file-earmark`)
- Success state per destination uitgesplitst — duidelijker welke feedback bij welke actie hoort

### Nog open
- Geen

## Review: Front-end Developer — 2026-02-14

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties
- [x] Componenten hebben file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet
- [x] Wireframe-component alignment
- [x] Test scenarios compleet
- [x] Herbruikbare componenten geïdentificeerd

### Goed
- Blob + URL.createObjectURL is het juiste patroon voor browser downloads
- FORMAT_MAP combineert ext + mime — clean, geen dubbele lookups
- Hergebruik van bestaande sanitizeFilename() en convert-format endpoint
- ES2019 is projectstandaard — geen polyfill nodig voor Blob

### Verbeterd
- HTML structuur gespecificeerd: `export-file-options` opsplitsen in `export-filename-options` en `export-directory-options`
- Specifieke element ID voor nieuwe radio button: `export-dest-download` met value `download`
- Preview path en overwrite warning expliciet beperkt tot File destination
- `toggleDestinationOptions()` drie-staps logica verduidelijkt

### Nog open
- Geen

## Review: Developer — 2026-02-14

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties
- [x] Componenten hebben file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet
- [x] Wireframe-component alignment
- [x] Test scenarios compleet
- [x] Herbruikbare componenten geïdentificeerd

### Goed
- Geen backend wijzigingen — minimale implementatie
- Puur frontend wijziging in één bestand — laag risico
- Bestaande functies (`exportToClipboard`, `exportToFile`) worden niet gebroken, alleen aangevuld
- FORMAT_MAP consolidatie is logisch, geen premature abstractie

### Verbeterd
- `handleExport()` refactoring expliciet beschreven: boolean → switch op destination value
- `getElements()` uitbreiding gespecificeerd met nieuwe element referenties
- Nieuwe functie `exportToDownload()` benoemd als parallel aan bestaande export functies

### Nog open
- Geen

## Review: Tester — 2026-02-14

### Score: 9/10

### 8+ Checklist
- [x] Geen interne contradicties
- [x] Componenten hebben file locaties
- [x] UI states gespecificeerd
- [x] Security validaties expliciet
- [x] Wireframe-component alignment
- [x] Test scenarios compleet
- [x] Herbruikbare componenten geïdentificeerd

### Goed
- Handmatige test scenarios dekken happy path en variaties
- Edge cases met verwacht gedrag gedefinieerd
- Regressie scenarios voor Clipboard en File export aanwezig
- Correct geïdentificeerd dat geen PHP unit tests nodig zijn (puur frontend wijziging)
- Acceptatiecriteria zijn meetbaar en verifieerbaar

### Verbeterd
- Edge case "dubbele klik op Export" toegevoegd — knop disabled tijdens verwerking
- Edge case "netwerk fout tijdens conversie" toegevoegd — error handling in catch block
- Corresponderende test scenarios toegevoegd in edge case tests tabel

### Nog open
- Geen
