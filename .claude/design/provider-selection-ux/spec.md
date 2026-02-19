# Feature: provider-selection-ux

## Samenvatting

Verbeter de Provider Selection UX in de AI Chat view zodat settings duidelijk vindbaar en bewerkbaar zijn vóór een conversatie, visueel gelocked verschijnen tijdens een actieve sessie, en het pad naar "New Session" ontdekbaar is — zonder interactief-uitziende disabled elementen.

## User story

Zie `.claude/design/provider-selection-ux/prompt.md` voor de volledige probleemomschrijving.

**Kernproblemen:**
1. Settings verborgen op page load — combined bar ziet er informatief uit, niet klikbaar
2. Na eerste send: settings collapsed + locked → uitklappen toont disabled dropdowns zonder uitleg
3. Geen visuele lock-indicator op combined bar
4. Combined bar is overloaded (project context + settings + config + toggle)
5. `newSession()` auto-expand kan jarring zijn

## Functionele requirements

### FR-1: Settings expanded op verse page load

- Beschrijving: Bij een verse page load (geen session history) toont de settings card standaard expanded, zodat de gebruiker direct provider/model/permissions kan kiezen. Bij page load mét session history (resume) blijven settings collapsed.
- Acceptatiecriteria:
  - [ ] Bij `sessionHistory.length === 0`: `#claudeSettingsCardWrapper` is zichtbaar (geen `d-none`) na `init()`
  - [ ] Bij `sessionHistory.length > 0`: settings blijven collapsed (bestaand gedrag)
  - [ ] `settingsState` wordt op `'expanded'` gezet bij verse page load (via bestaande `expandSettings()` die intern `this.settingsState = 'expanded'` zet — verifieer bij implementatie)
  - [ ] Combined bar verbergt settings-sectie wanneer card expanded is (bestaand `syncCombinedBar()` gedrag). **NB:** Bij expanded state verbergt `syncCombinedBar()` het hele `#claude-combined-settings` element inclusief context-badges (project, git branch). Dit is bestaand gedrag en wordt niet gewijzigd.

### FR-2: Visuele lock-indicator op combined bar

- Beschrijving: Wanneer settings gelocked zijn (actieve sessie), toont de combined bar een duidelijke visuele indicator dat settings bevroren zijn. De setting-badges krijgen een muted/dimmed stijl en een lock-icoon verschijnt.
- Acceptatiecriteria:
  - [ ] CSS modifier class `claude-combined-bar--locked` wordt toegevoegd aan `#claude-combined-bar` bij lock
  - [ ] Lock-icoon (`bi-lock-fill`) wordt zichtbaar in de settings-sectie van de combined bar
  - [ ] Setting-badges (provider, model, permission mode) krijgen verminderde opacity (0.6) en/of gedimde achtergrondkleur
  - [ ] Project/git-branch badges behouden hun normale stijl (niet beïnvloed door lock)
  - [ ] Bij unlock (new session) wordt de modifier class en het lock-icoon verwijderd

### FR-3: Combined bar niet-klikbaar tijdens lock

- Beschrijving: Wanneer settings gelocked zijn, is de settings-sectie van de combined bar niet meer klikbaar. Klikken op de usage-sectie blijft werken. Dit voorkomt het verwarrende scenario van een uitklapbaar paneel met disabled dropdowns.
- Acceptatiecriteria:
  - [ ] `toggleSettingsExpanded()` checkt `this.providerLocked` en doet niets als `true`
  - [ ] Cursor verandert van `pointer` naar `default` op `.claude-combined-bar--locked .claude-combined-bar__settings`
  - [ ] Usage-sectie (rechts van divider) blijft klikbaar ongeacht lock-state
  - [ ] Na unlock: settings-sectie is weer klikbaar

### FR-4: Locked state melding bij toch uitklappen

- Beschrijving: Als de combined bar wél klikbaar blijft (fallback) of de settings card op een andere manier geopend wordt terwijl locked, toon dan een inline melding bovenaan de settings card die uitlegt dat settings bevroren zijn en hoe te unlocken.
- Acceptatiecriteria:
  - [ ] Een alert-banner (`alert alert-info`) met tekst "Settings are locked for this session. Start a New Session to change them." verschijnt bovenaan de settings card wanneer expanded + locked
  - [ ] De alert bevat een link/button "New Session" die `newSession()` aanroept
  - [ ] De alert is verborgen wanneer settings unlocked zijn
  - [ ] De alert wordt dynamisch getoond/verborgen via JS (niet server-side)

### FR-5: Visuele scheiding project context vs. settings in combined bar

- Beschrijving: Splits de combined bar visueel in twee groepen: (1) project/git-branch/config badges (statische context + health) en (2) provider/model/permission badges (sessie-instellingen). De config badge is een health indicator, geen instelling, en hoort bij de context-groep. Dit maakt de bar minder overloaded.
- Acceptatiecriteria:
  - [ ] Een subtiele visuele scheiding (dunne divider `<span>` met 1px breedte en margin) tussen context-badges en settings-badges in `#claude-combined-settings`
  - [ ] Context-badges (project, git branch, config status) staan links
  - [ ] Settings-badges (provider, model, permission mode) staan rechts
  - [ ] Op mobiel: badges wrappen maar groepering blijft herkenbaar
  - [ ] Divider element is verborgen wanneer settings card expanded is (settings-badges niet zichtbaar in bar)

### FR-6: Verbeterde `newSession()` flow

- Beschrijving: Bij "New Session" worden settings unlocked maar de card wordt **niet** automatisch expanded. In plaats daarvan wordt de combined bar geüpdated met een visuele puls/highlight en een tijdelijke edit-hint om aan te geven dat settings nu bewerkbaar zijn. De gebruiker kan dan zelf klikken om de card te openen indien gewenst.
- Acceptatiecriteria:
  - [ ] `newSession()` roept `unlockProvider()` aan maar **niet** `expandSettings()`
  - [ ] Combined bar krijgt een korte CSS animatie (bijv. 1.2s highlight/pulse) na unlock
  - [ ] Settings-badges keren terug naar normale (niet-muted) stijl
  - [ ] Lock-icoon verdwijnt en wordt vervangen door een edit-icoon (`bi-pencil`) dat na 3 seconden uitfadet, om klikbaarheid te communiceren
  - [ ] Gebruiker kan daarna zelf settings expanderen door op de combined bar te klikken

### FR-7: Lock state tracking in JS

- Beschrijving: Introduceer een expliciete `this.providerLocked` boolean in het JS-object om lock state centraal te tracken, in plaats van alleen te leunen op DOM `disabled` attributen.
- Acceptatiecriteria:
  - [ ] `this.providerLocked` wordt geïnitialiseerd als `false` in `init()`
  - [ ] `lockProvider()` zet `this.providerLocked = true` en past visuele lock-indicator toe
  - [ ] `unlockProvider()` zet `this.providerLocked = false` en verwijdert visuele lock-indicator
  - [ ] Alle lock-afhankelijke logica (combined bar klik, badge styling) leest `this.providerLocked`

## Gebruikersflow

### Verse page load (geen sessie)
1. Pagina laadt → settings card is **expanded** (FR-1), combined bar verbergt settings-sectie
2. Gebruiker ziet direct de provider, model en permission mode dropdowns
3. Gebruiker past settings aan
4. Gebruiker klikt chevron in settings card of combined bar → settings card collapsed, combined bar toont badges

### Eerste bericht versturen
5. Gebruiker typt prompt en klikt "Send"
6. Settings auto-collapse (bestaand gedrag)
7. `lockProvider()` → dropdowns disabled, `this.providerLocked = true`
8. Combined bar toont lock-icoon + gedimde setting-badges (FR-2)
9. Combined bar settings-sectie is niet meer klikbaar (FR-3)

### Tijdens actieve sessie
10. Gebruiker ziet aan combined bar dat settings gelocked zijn (lock-icoon, muted badges)
11. Klikken op settings-sectie van combined bar doet niets (FR-3)
12. Usage-sectie blijft klikbaar

### New Session
13. Gebruiker klikt "New Session" in dropdown
14. `unlockProvider()` → `this.providerLocked = false`
15. Settings card blijft **collapsed** maar combined bar wordt gehighlight (FR-6)
16. Lock-icoon verdwijnt, badges keren terug naar normale stijl
17. Gebruiker klikt op combined bar → settings card expanded, kan instellingen wijzigen

### Resume sessie
18. Pagina laadt met session history → settings collapsed (bestaand gedrag), maar **niet** gelocked
19. Gebruiker kan settings nog wijzigen door combined bar te klikken (uitklappen)
20. Bij eerste send in resumed sessie → `lockProvider()` wordt aangeroepen → locked state actief
21. Combined bar toont lock-indicator

### Verse page load → direct send (zonder settings wijziging)
22. Pagina laadt → settings card expanded (FR-1)
23. Gebruiker negeert settings, typt prompt direct en klikt "Send"
24. Settings auto-collapse + lock (default provider/model/permissions worden gebruikt)
25. Combined bar toont lock-indicator met default waarden

## Edge cases

| Case | Gedrag |
|------|--------|
| Slechts één provider beschikbaar | Provider dropdown is verborgen (bestaand); combined bar toont geen provider badge als `$showProviderSelector === false`, of toont het als informatief label |
| Provider dropdown verborgen + locked | Lock indicator nog steeds zichtbaar op model/permission badges |
| Page load met resume session | Settings collapsed maar **niet** locked; lock pas bij eerste send in resumed sessie |
| Gebruiker klikt "New Session" maar wil niet wijzigen | Settings blijven collapsed, gebruiker kan direct nieuw bericht sturen |
| Browser terug-knop na New Session | Geen server-side state — JavaScript state bepaalt lock status |
| Provider switch gevolgd door send | Lock slaat de gekozen provider op; combined bar toont nieuwe provider in muted badge |
| Alle badges overflow op mobiel | Badges wrappen; lock-icoon blijft zichtbaar als eerste item in settings-groep |
| Config check AJAX faalt terwijl locked | Config badge toont fout-status maar settings blijven locked |

## Entiteiten en relaties

### Bestaande entiteiten
- **Project** (`yii/models/Project.php`) — `getAiOptionsForProvider()` levert default settings per provider
- **AiRun** (`yii/models/AiRun.php`) — `session_id` groepeert runs in een sessie
- **UserPreference** (`yii/models/UserPreference.php`) — kan in de toekomst lock-voorkeuren opslaan

### Nieuwe/gewijzigde componenten

| Component | Type | Locatie | Wijziging |
|-----------|------|---------|-----------|
| AI Chat view | View/JS | `yii/views/ai-chat/index.php` | Wijzigen: init flow, lock state tracking, combined bar rendering, newSession flow |
| AI Chat CSS | CSS | `yii/web/css/ai-chat.css` | Wijzigen: locked modifier class, muted badges, pulse animatie, divider in settings groep |

## Herbruikbare componenten

| Component | Locatie | Hoe hergebruikt |
|-----------|---------|-----------------|
| Combined bar | `yii/views/ai-chat/index.php` L97-104 | Bestaand — wijzigen: lock-icoon toevoegen, groepering aanpassen |
| `updateSettingsSummary()` | `yii/views/ai-chat/index.php` JS L3036-3081 | Bestaand — wijzigen: lock-icoon + divider in badges toevoegen |
| `syncCombinedBar()` | `yii/views/ai-chat/index.php` JS L3083-3101 | Bestaand — wijzigen: lock-state meenemen in visibility logica |
| `lockProvider()`/`unlockProvider()` | `yii/views/ai-chat/index.php` JS L1037-1057 | Bestaand — wijzigen: `this.providerLocked` + visuele indicator |
| `toggleSettingsExpanded()` | `yii/views/ai-chat/index.php` JS L3189-3201 | Bestaand — wijzigen: lock-check toevoegen |
| `newSession()` | `yii/views/ai-chat/index.php` JS L2915-2964 | Bestaand — wijzigen: niet meer auto-expandSettings, wel pulse animatie |
| Bootstrap Icons | CDN | Hergebruik: `bi-lock-fill` / `bi-unlock-fill` iconen |
| Bootstrap alerts | Framework | Hergebruik: `alert alert-info` voor locked-state melding |
| ARIA live region | `yii/views/ai-chat/index.php` L95 | Bestaand: `#ai-provider-status` — hergebruiken voor lock-state announcements |

## Architectuurbeslissingen

| Beslissing | Rationale |
|------------|-----------|
| Puur frontend wijzigingen (geen backend) | Lock state is client-side; server kent geen "locked" concept. De provider/model keuze wordt al bij elke `start-run` AJAX call meegestuurd. |
| `this.providerLocked` boolean i.p.v. DOM-check | Centraliseert lock-state; voorkomt fragiele `el.disabled` checks verspreid door de code. |
| Combined bar niet-klikbaar bij lock (FR-3) | Voorkomt het verwarrende patroon van klikken → disabled dropdowns zien. Eenvoudiger dan het tonen van een locked overlay. |
| Fallback alert in settings card (FR-4) | Defense-in-depth: als de card tóch geopend wordt (bijv. toekomstige code paths), ziet de gebruiker altijd uitleg. |
| `newSession()` niet auto-expand (FR-6) | Vermijdt jarring UI shift; gebruiker die alleen door wil chatten hoeft niet eerst door settings te scrollen. |
| Settings expanded op verse load (FR-1) | Eerste gebruik moet onboarding-vriendelijk zijn; returning users met een sessie zien collapsed state. |
| CSS modifier classes i.p.v. inline styles | Volgt bestaand patroon (`claude-combined-bar--loading`, `claude-combined-bar--warning`). Makkelijker te onderhouden. |

## Open vragen

Geen — alle designkeuzes zijn gemaakt op basis van de probleemanalyse en bestaande codebase patterns.

## UI/UX overwegingen

### Layout/Wireframe

**Verse page load (expanded):**
```
┌──────────────────────────────────────────────────────────────┐
│ Combined bar:  [Usage bars...]                               │
│                (settings sectie verborgen want card expanded) │
└──────────────────────────────────────────────────────────────┘
┌──────────────────────────────────────────────────────────────┐
│ Settings Card (expanded)                                     │
│ ┌──────────────────────────────────────────────────────────┐ │
│ │ [Project ▾]  [Git branch ▾]  [Config ✓]         [▲]     │ │
│ └──────────────────────────────────────────────────────────┘ │
│ ┌────────────┐ ┌────────────┐ ┌──────────────────┐          │
│ │ Provider ▾ │ │ Model    ▾ │ │ Permission Mode ▾│          │
│ └────────────┘ └────────────┘ └──────────────────┘          │
│ [Provider-specific custom fields...]                         │
└──────────────────────────────────────────────────────────────┘
```

**Collapsed + Unlocked (before first send):**
```
┌──────────────────────────────────────────────────────────────┐
│ [📁 Project] [🔀 Branch] [✓ OK] │ [🤖 Claude] [💻 Sonnet]  │
│                                   │ [🛡 Default]             │
│                                   ├── [Usage bars...]        │
└──────────────────────────────────────────────────────────────┘
```

**Collapsed + Locked (active session):**
```
┌──────────────────────────────────────────────────────────────┐
│ [📁 Project] [🔀 Branch] [✓ OK] │ 🔒 [🤖 Claude] [💻 Son]  │
│                                   │    [🛡 Default]  (muted) │
│                                   ├── [Usage bars...]        │
└──────────────────────────────────────────────────────────────┘
```

**After New Session (unlocked, collapsed, with edit hint):**
```
┌──────────────────────────────────────────────────────────────┐
│ [📁 Project] [🔀 Branch] [✓ OK] │ ✏️ [🤖 Claude] [💻 Son]  │
│                  (pulse highlight) │    [🛡 Default]          │
│                                   ├── [Usage bars...]        │
└──────────────────────────────────────────────────────────────┘
  (edit icon fades out after 3s)
```

**Locked settings card (fallback, als tóch geopend):**
```
┌──────────────────────────────────────────────────────────────┐
│ ⓘ Settings are locked for this session. [New Session]        │
│ ┌────────────┐ ┌────────────┐ ┌──────────────────┐          │
│ │ Provider ▾ │ │ Model    ▾ │ │ Permission Mode ▾│          │
│ │ (disabled) │ │ (disabled) │ │ (disabled)       │          │
│ └────────────┘ └────────────┘ └──────────────────┘          │
└──────────────────────────────────────────────────────────────┘
```

### UI States

| State | Visueel |
|-------|---------|
| Fresh load (no session) | Settings card expanded, combined bar toont alleen usage |
| Collapsed + Unlocked | Combined bar toont alle badges in 2 groepen gescheiden door extra ruimte, cursor pointer op settings |
| Collapsed + Locked | Combined bar toont lock-icoon, setting-badges muted (opacity 0.6, gedimde bg), cursor default op settings |
| Expanded + Locked (fallback) | Alert banner bovenaan card, disabled dropdowns, grayed-out labels |
| New Session pulse | Combined bar krijgt 1.2s highlight animatie + tijdelijk edit-icoon, badges keren terug naar normale stijl |
| Loading (page load) | Combined bar toont `--loading` modifier (grijze usage tekst), settings card respecteert FR-1 |

### Accessibility

- Lock-icoon krijgt `aria-label="Settings locked for this session"`
- Edit-hint icoon (FR-6, `bi-pencil`) krijgt `aria-label="Click to edit settings"`
- `#ai-provider-status` (bestaand `aria-live="polite"` region) wordt geüpdatet bij lock/unlock: "Provider settings locked" / "Provider settings unlocked"
- Alert in locked settings card is `role="alert"` met `aria-live="assertive"`
- Combined bar settings-sectie krijgt `aria-disabled="true"` wanneer locked
- Keyboard-activatie: `#claude-combined-bar` heeft `role="button"` maar geen eigen keydown handler; child click handlers op `#claude-combined-settings` en `#claude-combined-usage` verwerken clicks. De lock-check in `toggleSettingsExpanded()` vangt ook keyboard-geactiveerde clicks correct af.
- Pulse animatie respecteert `prefers-reduced-motion: reduce` — geen animatie, alleen kleurverandering
- Muted badge kleuren moeten WCAG AA contrast ratio (4.5:1) halen tegen de combined bar achtergrond (#e9ecef). **Let op:** de voorgestelde `#8a9bb5` haalt slechts ~2.3:1 — gebruik donkerdere tekstkleur `#5a6a80` (≈ 4.6:1) of verlaag alleen de opacity van de achtergrondkleur, niet van de tekst

## Technische overwegingen

### Backend

Geen backend wijzigingen vereist. Alle functionaliteit is client-side JavaScript en CSS.

### Frontend

#### JavaScript wijzigingen in `yii/views/ai-chat/index.php`

**1. Nieuwe state variable:**
```javascript
// In init() bij variabele declaraties (rond lijn 536)
this.providerLocked = false;
```

**2. `lockProvider()` uitbreiden (lijn 1037-1046):**
```javascript
lockProvider: function() {
    this.providerLocked = true;
    // Clean edit-hint state als nog actief (edge case: New Session → snel send)
    this._editHintActive = false;
    if (this._pulseTimer) { clearTimeout(this._pulseTimer); this._pulseTimer = null; }
    if (this._editHintTimer) { clearTimeout(this._editHintTimer); this._editHintTimer = null; }
    // Bestaande disabled logic behouden
    var el = document.getElementById('ai-provider');
    if (el) el.disabled = true;
    // ... (bestaande code)
    // Nieuw: visuele indicator
    document.getElementById('claude-combined-bar').classList.remove('claude-combined-bar--pulse');
    document.getElementById('claude-combined-bar').classList.add('claude-combined-bar--locked');
    this.updateSettingsSummary(); // rebuild badges met lock-icoon
    var statusEl = document.getElementById('ai-provider-status');
    if (statusEl) statusEl.textContent = 'Provider settings locked for this session';
},
```

**3. `unlockProvider()` uitbreiden (lijn 1048-1057):**
```javascript
unlockProvider: function() {
    this.providerLocked = false;
    // Bestaande enable logic behouden
    // ... (bestaande code)
    // Nieuw: visuele indicator verwijderen
    document.getElementById('claude-combined-bar').classList.remove('claude-combined-bar--locked');
    this.updateSettingsSummary(); // rebuild badges zonder lock-icoon
    var statusEl = document.getElementById('ai-provider-status');
    if (statusEl) statusEl.textContent = 'Provider settings unlocked';
},
```

**4. `toggleSettingsExpanded()` aanpassen (lijn 3189-3201):**
```javascript
toggleSettingsExpanded: function() {
    if (this.providerLocked) return; // FR-3: niet klikbaar bij lock
    // ... bestaande toggle logica
},
```

**NB:** De bestaande click handler op `#claude-combined-settings` (lijn 834-836) roept `toggleSettingsExpanded()` aan. De combined bar is één `role="button"` element; de klik op de settings-sectie vs usage-sectie wordt al onderscheiden door aparte event listeners op child-elementen. Geen wijziging nodig aan de event delegation structuur.

**5. `updateSettingsSummary()` aanpassen (lijn 3036-3081):**
- Badge volgorde: project → git branch → config status → [divider] → provider → model → permission mode. **NB:** Dit wijzigt de huidige volgorde (project → git branch → provider → config → model → permission) — config badge verplaatst naar vóór de divider.
- Config badge verplaatst van settings-groep naar context-groep (het is een health indicator, geen instelling)
- Na context-badges (project, git branch, config): voeg divider `<span class="claude-combined-bar__settings-divider">` toe
- Vóór provider badge: als `this.providerLocked`, voeg lock-icoon badge toe; bij unlock na newSession, voeg tijdelijk edit-icoon badge toe (verwijder na 3s via `setTimeout`)
- Setting-badges krijgen extra CSS class `badge-setting--locked` als `this.providerLocked`

**6. `init()` aanpassen voor FR-1:**
```javascript
// Na huidige init logica (rond lijn 580-586):
if (this.sessionHistory.length === 0) {
    this.expandSettings(); // FR-1: verse load = expanded
} else {
    this.collapseSettings(); // bestaand gedrag
}
```

**7. `newSession()` aanpassen voor FR-6:**
```javascript
// Clean bestaande timers (voorkom orphaned timers bij snelle opeenvolgende calls)
if (this._pulseTimer) clearTimeout(this._pulseTimer);
if (this._editHintTimer) clearTimeout(this._editHintTimer);
// Activeer edit-hint VOORDAT unlockProvider() updateSettingsSummary() aanroept
this._editHintActive = true;
// Vervang expandSettings() door:
this.unlockProvider(); // → roept updateSettingsSummary() aan, die nu _editHintActive=true ziet
// NIET: this.expandSettings();
this.syncCombinedBar();
// Pulse animatie triggeren:
var bar = document.getElementById('claude-combined-bar');
bar.classList.add('claude-combined-bar--pulse');
var self = this;
this._pulseTimer = setTimeout(function() {
    bar.classList.remove('claude-combined-bar--pulse');
    self._pulseTimer = null;
}, 1200);
// Edit-icoon verwijderen na 3s:
this._editHintTimer = setTimeout(function() {
    self._editHintActive = false;
    self._editHintTimer = null;
    self.updateSettingsSummary();
}, 3000);
```

**NB:** `lockProvider()` moet ook `_editHintActive = false` en timers cleanen als het wordt aangeroepen terwijl een edit-hint nog actief is.

**8. Locked alert in settings card (FR-4):**
- Voeg een verborgen `<div id="claude-settings-locked-alert">` toe in de HTML bovenaan de settings card
- `lockProvider()` toont de alert, `unlockProvider()` verbergt deze
- Alert bevat "New Session" knop die `newSession()` aanroept via `addEventListener` (geen inline `onclick`)

#### CSS wijzigingen in `yii/web/css/ai-chat.css`

**1. Locked modifier:**
```css
.claude-combined-bar--locked .claude-combined-bar__settings {
    cursor: default;
}
.claude-combined-bar--locked .badge.badge-setting {
    background-color: #5a6a80; /* gedimde versie van #5a7bb5 — WCAG AA ≈ 4.6:1 op #e9ecef */
    color: #fff;
}
```
NB: `badge-setting--locked` class wordt in JS toegevoegd als semantic marker maar heeft geen apart CSS-effect — de opacity/achtergrondkleur wordt al afgehandeld via de parent `.claude-combined-bar--locked` selector.

**2. Lock-icoon badge:**
```css
.badge.badge-lock {
    background-color: #6c757d;
    font-size: 0.65rem;
}
```

**3. Pulse animatie:**
```css
.claude-combined-bar--pulse {
    animation: combinedBarPulse 1.2s ease;
}
@keyframes combinedBarPulse {
    0%, 100% { background-color: #e9ecef; }
    30% { background-color: #d4edda; }
}
@media (prefers-reduced-motion: reduce) {
    .claude-combined-bar--pulse { animation: none; background-color: #d4edda; }
}
```

**4. Badge groepering:**
```css
.claude-combined-bar__settings-divider {
    width: 1px;
    min-height: 0.75rem;
    background: #ced4da;
    margin: 0 0.15rem;
    flex-shrink: 0;
}
@media (max-width: 575.98px) {
    .claude-combined-bar__settings-divider {
        display: none;
    }
    .claude-combined-bar__settings {
        gap: 0.25rem;
    }
}
```

**5. Locked alert:**
```css
.claude-settings-locked-alert {
    margin-bottom: 0.75rem;
    font-size: 0.85rem;
}
```

## Test scenarios

### Unit tests

Geen nieuwe unit tests nodig — dit is puur frontend logica (JS + CSS). Bestaande Codeception unit tests worden niet geraakt.

### Functionele / handmatige test scenarios

| Test | Input | Verwacht resultaat |
|------|-------|-------------------|
| Verse page load zonder sessie | Navigeer naar AI Chat zonder actieve sessie | Settings card is expanded, combined bar verbergt settings sectie |
| Page load met sessie history | Navigeer naar AI Chat met resume session | Settings collapsed maar niet locked; combined bar toont normale badges; lock pas bij eerste send |
| Eerste bericht versturen | Typ prompt, klik Send | Settings collapse + lock; combined bar toont lock-indicator |
| Klik op locked combined bar settings | Klik op settings-badges terwijl locked | Niets gebeurt (geen expand) |
| Klik op usage sectie terwijl locked | Klik op usage-bars rechts van divider | Usage panel togglet normaal |
| New Session | Klik "New Session" in dropdown | Settings unlocked maar collapsed; combined bar toont pulse animatie + tijdelijk edit-icoon; badges normaal; edit-icoon verdwijnt na 3s |
| New Session → expand settings | Na New Session, klik op combined bar settings | Settings card expandeert normaal |
| Provider wissel voor eerste send | Kies andere provider in dropdown | Badges in combined bar updaten met nieuwe provider naam |
| Eén provider beschikbaar | Configuratie met alleen Claude | Provider dropdown verborgen; lock-indicator toont op model/permission badges |
| Mobiel formaat | Resize browser naar < 768px | Badges wrappen netjes; lock-icoon blijft zichtbaar |

### Edge case tests

| Test | Scenario | Verwacht resultaat |
|------|----------|-------------------|
| Dubbele lock call | `lockProvider()` twee keer aanroepen | Geen crash; `this.providerLocked` blijft `true` |
| Unlock zonder voorafgaande lock | `unlockProvider()` bij verse staat | Geen crash; `this.providerLocked` blijft `false` |
| Provider wissel na unlock | New Session → wissel provider → send | Lock slaat nieuwe provider op in combined bar |
| Config check fail tijdens lock | AJAX `/check-config` retourneert fout | Config badge toont error state; lock blijft intact |
| Prefers-reduced-motion | OS instelling geen animaties | Pulse animatie is statische kleurverandering |
| Keyboard navigatie | Enter/Space op combined bar terwijl locked | Click handler detecteert lock via `this.providerLocked` en blokkeert settings-toggle; combined bar blijft focusbaar als geheel |
| Snelle newSession opeenvolging | Twee keer snel "New Session" klikken | Timers worden gecleaned; geen dubbele edit-hints of orphaned animaties |
| New Session → snel send (< 3s) | New Session → direct prompt sturen vóór edit-hint timer afloopt | `lockProvider()` cleant timers; pulse animatie stopt; edit-icoon verdwijnt; lock-indicator verschijnt correct |
| Direct send zonder settings wijziging | Page load → typ prompt → Send (settings niet aangeraakt) | Settings collapse + lock met default waarden; combined bar toont lock-indicator |
| Resume sessie → send | Resume sessie → typ follow-up → Send | Lock activeert bij send; combined bar toont lock-indicator |
| Resume sessie → wijzig settings → send | Resume sessie → expand settings → wijzig model → collapse → send | Lock activeert met gewijzigd model in combined bar |

### Regressie-scenario's

Bestaand gedrag dat ongewijzigd moet blijven na implementatie:

| Scenario | Verwacht bestaand gedrag |
|----------|------------------------|
| Provider wissel updatet dropdowns | `#ai-provider` change → model en permission mode dropdowns worden hervuld met provider-specifieke opties |
| Provider wissel updatet page title | Page title en h1 wijzigen naar "{provider.name} CLI" |
| Config check per provider | `checkConfigStatus()` stuurt huidige provider mee; badge updatet correct per provider |
| Usage fetch per provider | `fetchSubscriptionUsage()` stuurt huidige provider mee; usage updatet of toont "not available" |
| Custom fields rendering | `renderProviderCustomFields()` bouwt provider-specifieke velden in `#provider-custom-fields` |
| Send flow met streaming | `send()` → `lockProvider()` → streaming → response rendering — hele flow ongewijzigd |
| Summarize & New Session | `summarizeAndContinue()` → auto-`newSession()` — moet werken met nieuwe newSession flow |
| History accordion rendering | Sessieschrijding wordt correct gerendered bij resume |
| `collapseSettings()` bij send | Settings card wordt verborgen bij eerste send; combined bar toont badges |
