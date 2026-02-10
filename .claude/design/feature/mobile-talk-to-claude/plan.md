# Plan: Mobiele versie Talk to Claude

## Overzicht

Dit plan onderzoekt de haalbaarheid van een mobiele versie van de "Talk to Claude" functionaliteit en beschrijft een concrete aanpak om de bestaande desktop-georiënteerde chat-interface responsive te maken voor mobiele apparaten. De huidige implementatie (`yii/views/claude/index.php`, ~2905 regels) is gebouwd op Bootstrap 5 maar mist mobiel-specifieke optimalisaties. De aanpak is **responsive-first CSS + minimale JS-aanpassingen** — geen aparte mobiele app of nieuw backend-werk nodig.

## Analyse van de huidige situatie

### Wat al werkt op mobiel

1. **Viewport meta tag** is aanwezig in `_base.php:13`: `width=device-width, initial-scale=1, shrink-to-fit=no`
2. **Bootstrap 5 grid** wordt gebruikt (bijv. `col-md-6` in settings)
3. **SSE streaming** werkt op alle moderne mobiele browsers (Chrome, Safari, Firefox)
4. **Fetch API** wordt ondersteund op alle mobiele browsers
5. **AJAX endpoints** zijn device-agnostisch — geen backend-wijzigingen nodig
6. **Bestaande responsive CSS** in `claude-chat.css:1085-1090` verbergt al usage labels/bars op `max-width: 576px`

### Knelpunten op mobiel

| # | Probleem | Locatie | Impact |
|---|----------|---------|--------|
| 1 | **Quill toolbar te breed** — 9+ format-groepen wrappen slecht op smal scherm | `index.php:163-216` | Toolbar neemt halve viewport in, editor nauwelijks zichtbaar |
| 2 | **Sticky prompt editor** blokkeert viewport — `top: 62px` + `min-height: 10em` laat weinig ruimte voor content | `claude-chat.css:1304-1324` | Op 375px breed scherm ziet gebruiker alleen de editor, geen responses |
| 3 | **Focus mode** gebruikt `position: fixed` fullscreen — goed op desktop, maar op mobiel conflicteert dit met virtueel toetsenbord | `claude-chat.css:1327-1382` | Editor wordt verborgen achter toetsenbord op iOS/Android |
| 4 | **Alt+key shortcuts** onbruikbaar op mobiel — Alt+S (send), Alt+R (reply), Alt+G (go), Alt+F (focus) | `index.php:643-666` | Geen sneltoetsen beschikbaar op touch devices |
| 5 | **Combined bar** met badges + usage is te compact op mobiel — tekst valt weg | `claude-chat.css:52-94` | Badges overlappen of worden onleesbaar |
| 6 | **Modals** (`modal-lg`, `modal-dialog-scrollable`) — dialog breedte/hoogte niet mobiel-geoptimaliseerd | `index.php:304-395` | Save dialog en stream modal vullen niet goed op mobiel |
| 7 | **Container padding** `py-4` + navbar offset — content begint pas na ~120px van bovenkant | `index.php:75`, `site.css:1-3` | Minder bruikbare ruimte op kleine schermen |
| 8 | **Accordion history items** — collapse/expand touch targets zijn klein | `claude-chat.css:857-914` | Moeilijk te tikken op touchscreen |
| 9 | **Copy/Go/Reply knoppen** in message headers zijn klein (0.8rem) | `claude-chat.css:262-277` | Touch targets kleiner dan 44px minimum (Apple HIG) |
| 10 | **Resizable editor** met `resize: vertical` — resize handle werkt niet op touch | `site.css:126-133` | Gebruiker kan editor-hoogte niet aanpassen op mobiel |
| 11 | **Navbar** met project-context dropdown — op mobiel overlap met breadcrumbs | `main.php:120-128`, `site.css:683-689` | Dropdown kan content overlappen |

### Wat NIET hoeft te veranderen

- **Backend** (ClaudeController, ClaudeCliService, ClaudeWorkspaceService) — volledig device-agnostisch
- **SSE streaming protocol** — werkt identiek op mobiel
- **RBAC/security** — geen wijzigingen nodig
- **Quill Delta opslag** — formaat is onafhankelijk van weergave
- **Session management** (`--resume`) — werkt via server-side state
- **JavaScript logica** (ClaudeChat object) — alleen UI-interactie aanpassingen

## Voorgestelde aanpak

### Strategie: Responsive CSS + touch-optimalisatie

Geen aparte mobiele view of controller. Alle aanpassingen via:
1. **CSS media queries** in `claude-chat.css` (hoofdzakelijk `@media (max-width: 767.98px)`)
2. **Minimale JS-aanpassingen** voor touch detection en viewport-handling
3. **HTML-wijzigingen** alleen waar nodig voor touch targets

### Fase 1: CSS-only responsive fixes (laag risico, hoog rendement)

#### 1.1 Quill toolbar responsive

**Bestand:** `yii/web/css/claude-chat.css`

```css
@media (max-width: 767.98px) {
    /* Verberg minder gebruikte formatting opties op mobiel */
    .claude-chat-page #claude-quill-toolbar .ql-formats:nth-child(n+4) {
        display: none;
    }
    /* Compactere toolbar */
    .claude-chat-page #claude-quill-toolbar {
        gap: 2px;
        padding: 4px;
    }
}
```

**Alternatief:** Scrollbare toolbar met `overflow-x: auto; flex-wrap: nowrap;`

#### 1.2 Sticky editor mobiel-optimalisatie

**Bestand:** `yii/web/css/claude-chat.css`

```css
@media (max-width: 767.98px) {
    .claude-chat-page .claude-prompt-card-sticky {
        position: relative; /* Niet sticky op mobiel */
        top: auto;
    }
    .claude-chat-page .claude-prompt-card-sticky .resizable-editor-container {
        max-height: 40vh;
        min-height: auto;
    }
    .claude-chat-page .claude-prompt-card-sticky .resizable-editor {
        min-height: 6em;
    }
}
```

#### 1.3 Touch-friendly knoppen en targets

**Bestand:** `yii/web/css/claude-chat.css`

```css
@media (max-width: 767.98px) {
    /* Minimale touch target 44px (Apple HIG) */
    .claude-chat-page .claude-message__go,
    .claude-chat-page .claude-message__copy,
    .claude-chat-page .claude-collapsible-summary__reply {
        min-height: 44px;
        min-width: 44px;
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
    }
    /* Grotere accordion headers */
    .claude-chat-page .claude-collapsible-summary {
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
    }
    .claude-chat-page .claude-message--claude .claude-message__header {
        padding: 0.6rem 0.75rem;
    }
}
```

#### 1.4 Combined bar en usage responsive

**Bestand:** `yii/web/css/claude-chat.css`

```css
@media (max-width: 767.98px) {
    .claude-chat-page .claude-combined-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .claude-chat-page .claude-combined-bar__divider {
        width: 100%;
        height: 1px;
        min-height: 1px;
    }
}
```

#### 1.5 Container padding mobiel

**Bestand:** `yii/web/css/claude-chat.css`

```css
@media (max-width: 767.98px) {
    .claude-chat-page.container {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
        padding-top: 1rem;
    }
}
```

#### 1.6 Modals fullscreen op mobiel

**Bestand:** `yii/web/css/claude-chat.css`

```css
@media (max-width: 767.98px) {
    #claudeStreamModal .modal-dialog,
    #saveDialogSelectModal .modal-dialog {
        margin: 0;
        max-width: 100%;
        height: 100%;
    }
    #claudeStreamModal .modal-content,
    #saveDialogSelectModal .modal-content {
        border-radius: 0;
        height: 100%;
    }
}
```

### Fase 2: JavaScript touch-optimalisaties (beperkt risico)

#### 2.1 Send knop prominent op mobiel

**Bestand:** `yii/views/claude/index.php` (regel 228-242)

De action buttons rij (`d-flex justify-content-between`) werkt al redelijk, maar de Send knop zou een grotere touch target moeten krijgen via CSS (zie 1.3).

#### 2.2 Virtueel toetsenbord handling

**Bestand:** `yii/views/claude/index.php` (in ClaudeChat JS object)

```javascript
// Detecteer virtueel toetsenbord en scroll editor in beeld
if ('visualViewport' in window) {
    window.visualViewport.addEventListener('resize', function() {
        // Scroll editor in beeld wanneer toetsenbord opent
        var editor = document.querySelector('.claude-prompt-card-sticky');
        if (editor && document.activeElement.closest('.claude-prompt-card-sticky')) {
            editor.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    });
}
```

#### 2.3 Focus mode aanpassing voor mobiel

**Bestand:** `yii/web/css/claude-chat.css`

```css
@media (max-width: 767.98px) {
    /* Focus mode: gebruik vh eenheden die reageren op virtueel toetsenbord */
    .claude-chat-page.claude-focus-mode .claude-prompt-card-sticky {
        height: 100dvh; /* Dynamic viewport height */
    }
}
```

#### 2.4 Resize handle vervangen door auto-grow op mobiel

**Bestand:** Kleine JS toevoeging in `ClaudeChat.init()`

```javascript
// Op touch devices: auto-grow textarea i.p.v. resize handle
if ('ontouchstart' in window) {
    var textarea = document.getElementById('claude-followup-textarea');
    textarea.style.resize = 'none';
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, window.innerHeight * 0.4) + 'px';
    });
}
```

### Fase 3: UX-verbeteringen (optioneel, na validatie fase 1+2)

| Verbetering | Beschrijving | Complexiteit |
|-------------|-------------|--------------|
| Pull-to-refresh | Nieuwe sessie starten met pull gesture | Middel |
| Swipe-to-collapse | Swipe op accordion items | Middel |
| Bottom-anchored input | Editor altijd onderaan (à la chat-apps) | Hoog |
| Haptic feedback | Vibration API bij send/receive | Laag |
| PWA manifest | App-achtige ervaring met install prompt | Middel |

## Bestanden te wijzigen

| # | Bestand | Wijziging | Omvang |
|---|---------|-----------|--------|
| 1 | `yii/web/css/claude-chat.css` | Toevoegen van `@media (max-width: 767.98px)` blokken voor alle componenten | ~120 regels CSS |
| 2 | `yii/views/claude/index.php` | Minimale JS: virtual keyboard handling, touch detection, auto-grow textarea | ~30 regels JS |

## Bestanden die NIET wijzigen

- `yii/controllers/ClaudeController.php` — geen backend-wijzigingen
- `yii/services/ClaudeCliService.php` — device-agnostisch
- `yii/services/ClaudeWorkspaceService.php` — device-agnostisch
- `yii/services/ClaudeCliCompletionClient.php` — device-agnostisch
- `yii/handlers/ClaudeQuickHandler.php` — device-agnostisch
- `yii/views/layouts/_base.php` — viewport meta al aanwezig
- `yii/views/layouts/main.php` — al responsive via Bootstrap xl breakpoint
- `npm/src/js/editor-init.js` — Quill init niet geraakt
- Alle migraties — geen database-wijzigingen
- Alle tests — geen gedragswijzigingen in backend

## Migratie plan

Geen migraties nodig. Alle wijzigingen zijn puur frontend (CSS + minimale JS).

## Test plan

| # | Test | Methode | Acceptatiecriterium |
|---|------|---------|---------------------|
| 1 | Toolbar past op 375px breed | Chrome DevTools responsive mode (iPhone SE) | Alle zichtbare knoppen bruikbaar, geen overflow |
| 2 | Editor niet sticky op mobiel | Scroll test op 375px | Content scrollt vrij, editor niet vastgeplakt |
| 3 | Send knop touch target ≥44px | DevTools element inspectie | Min-height/width 44px |
| 4 | Stream modal fullscreen op mobiel | Open modal op 375px | Vult volledig scherm, geen ronde hoeken |
| 5 | Virtueel toetsenbord verbergt editor niet | Test op fysiek iOS/Android device | Editor scrollt in beeld bij focus |
| 6 | Combined bar leesbaar op 375px | Visuele inspectie | Badges en usage niet overlappend |
| 7 | Bestaande desktop-functionaliteit ongewijzigd | Test op 1920px breed | Alles werkt als voorheen |
| 8 | SSE streaming werkt op mobiele Safari | Test op fysiek iOS device | Stream events worden ontvangen en gerenderd |
| 9 | Copy/paste werkt op mobiel | Test clipboard API op mobiel | Tekst wordt gekopieerd naar klembord |
| 10 | Accordion expand/collapse werkt op touch | Tikken op accordion header | Item opent/sluit correct |

## Beveiligingschecklist

Geen nieuwe endpoints, geen nieuwe datastromen. Alle bestaande beveiligingsmaatregelen (RBAC, CSRF, ownership checks) blijven ongewijzigd. CSS-wijzigingen hebben geen beveiligingsimpact.

## Afhankelijkheden

Geen nieuwe afhankelijkheden. Alles wordt gerealiseerd met bestaande Bootstrap 5, CSS media queries, en standaard Web API's (VisualViewport API, `ontouchstart` detection).

## Risico's en mitigaties

| Risico | Impact | Mitigatie |
|--------|--------|-----------|
| iOS Safari SSE quirks (connection drop bij scherm lock) | Gebruiker verliest stream output | ClaudeChat heeft al error recovery in `onStreamError`; session kan hervatten via `--resume` |
| Quill editor performance op oudere mobiele devices | Trage input bij lange prompts | Textarea fallback bestaat al (`Switch to plain text`); op mobiel evt. standaard textarea |
| Virtueel toetsenbord hoogte verschilt per device/OS | Editor mogelijk deels verborgen | `100dvh` + VisualViewport API vangt meeste gevallen; fallback naar `100vh` |
| CSS cascade conflicten met bestaande regels | Onverwachte stijl-veranderingen | Alle nieuwe CSS scoped achter `@media (max-width: 767.98px)` — geen invloed op desktop |
| `position: sticky` + virtueel toetsenbord op iOS | Element springt of verdwijnt | Op mobiel `position: relative` gebruiken (fase 1.2) |

## Open vragen

1. **Voorkeur voor toolbar aanpak?** Er zijn twee opties:
   - **A)** Verberg minder gebruikte knoppen op mobiel (eenvoudiger, minder functionaliteit)
   - **B)** Horizontaal scrollbare toolbar (alle knoppen beschikbaar, iets complexer)

2. **Standaard input mode op mobiel?** Op mobiel is de Quill rich-text editor zwaarder dan een plain textarea. Optie: automatisch starten in textarea-modus op touch devices, met optie om naar Quill te wisselen.

3. **Bottom-anchored editor (à la WhatsApp/iMessage)?** Dit is een grotere UX-wijziging die de layout fundamenteel verandert (editor onderaan, responses erboven scrollend). Het sluit beter aan bij mobiele chat-patronen maar vereist meer werk. Wil je dit in scope of als apart vervolgproject?

## Conclusie

De mobiele versie is **goed haalbaar** met relatief beperkte inspanning:

- **Fase 1** (CSS-only): ~120 regels CSS, puur additief, nul risico voor desktop
- **Fase 2** (JS touch): ~30 regels JS, minimaal invasief
- **Fase 3** (UX optioneel): alleen op basis van gebruikersfeedback

Het backend is volledig device-agnostisch. De enige uitdaging zit in de frontend: de Quill editor toolbar, sticky positioning, en touch targets. Dit zijn allemaal oplosbare CSS-problemen.

**Geschatte implementatietijd:** Fase 1+2 samen vormen een compacte taak.
