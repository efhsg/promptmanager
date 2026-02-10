# Plan: Mobiele versie van Talk to Claude

## Overzicht

Dit plan beschrijft hoe de bestaande "Talk to Claude" chat-interface (`/claude/index`) geschikt gemaakt kan worden voor mobiel gebruik. De huidige pagina gebruikt een single-column stacked layout met Bootstrap 5, wat een goede basis vormt. De grootste uitdagingen liggen bij de Quill rich text editor (slechte mobiele ervaring), de brede toolbar, sticky positioning, en de vele modals die op kleine schermen moeilijk te bedienen zijn.

Dit plan is een **haalbaarheidsonderzoek met concrete implementatiestappen** — geen speculatie maar gebaseerd op de daadwerkelijke code.

---

## Analyse van huidige mobiele staat

### Wat werkt al

| Component | Status | Reden |
|-----------|--------|-------|
| Pagina-layout | Goed | Single-column `.container`, Bootstrap responsive |
| Settings badges | Goed | `flex-wrap` is al ingesteld |
| Usage summary | Goed | Heeft al `@media (max-width: 576px)` regel die labels/bars verbergt |
| Streaming preview | Goed | Vaste hoogte, scrollbaar |
| Accordion history | Goed | Bootstrap accordion is mobile-native |
| Save dialog modals | Redelijk | Bootstrap modals zijn responsive maar krap op mobile |
| Copy knoppen | Goed | `navigator.clipboard` API werkt op mobile |
| SSE streaming | Goed | `fetch()` + `ReadableStream` werkt in mobile browsers |
| Cancel functionaliteit | Goed | Geen platform-afhankelijkheden |

### Wat problemen geeft

| Component | Probleem | Impact | Bestand:regel |
|-----------|----------|--------|---------------|
| **Quill toolbar** | 9 `ql-formats` groepen + command dropdown passen niet op 320-400px breed scherm | Toolbar breekt visueel, knoppen worden onbereikbaar | `yii/views/claude/index.php:163-216` |
| **Quill editor** | `min-height: 10em` is te groot voor mobiel; `resize: vertical` werkt niet op touch | Editor neemt te veel ruimte in | `yii/web/css/claude-chat.css:1311-1320` |
| **Sticky prompt card** | `position: sticky; top: 62px` conflicteert met mobiele navbar (andere hoogte na collapse) | Editor kan achter navbar verdwijnen of te hoog zitten | `yii/web/css/claude-chat.css:1304-1309` |
| **Focus mode** | `position: fixed` fullscreen overlay werkt, maar er is geen manier om te scrollen voorbij het toetsenbord | Virtueel toetsenbord bedekt editor | `yii/web/css/claude-chat.css:1326-1382` |
| **Keyboard shortcuts** | `Ctrl+Enter`, `Alt+S`, `Alt+G`, `Alt+F`, `Alt+R` bestaan niet op mobiel | Geen snelle manier om te senden of navigeren | `yii/views/claude/index.php:642-680` |
| **Command dropdown** | Native `<select>` met optgroups is functioneel maar neemt veel ruimte in toolbar | Past niet in compacte mobiele toolbar | `yii/views/claude/index.php:428-467` |
| **Page header** | `h1.h3` met icon is breed | Neemt onnodig ruimte in op mobiel | `yii/views/claude/index.php:77-81` |
| **Action buttons** | "Last prompt" + "Send" naast editor toggle link | Layout kan breken op smalle schermen | `yii/views/claude/index.php:228-242` |
| **Navbar** | `navbar-expand-xl` met `fixed-top`, hoogte varieert per breakpoint | Sticky top offset moet meebewegen | `yii/views/layouts/main.php:19` |
| **Container padding** | `container mt-5 pt-5` in main layout geeft veel top-padding | Verspilt kostbare schermruimte op mobiel | `yii/views/layouts/main.php:133` |

---

## Implementatiestrategie

De strategie is **CSS-first, progressive enhancement**: geen aparte mobiele view of controller nodig. Alle wijzigingen zijn CSS media queries en minimale JS-aanpassingen in de bestaande bestanden.

### Geen nieuwe bestanden nodig

De hele mobiele optimalisatie past in:
1. `yii/web/css/claude-chat.css` — responsive CSS toevoegen
2. `yii/views/claude/index.php` — minimale HTML/JS aanpassingen
3. `yii/web/css/site.css` — optioneel: globale mobiele tweaks

### Geen backend-wijzigingen nodig

De controller, services, en models hoeven niet te veranderen. Alle API-endpoints werken identiek op mobiel. SSE streaming via `fetch()` is platform-onafhankelijk.

---

## Files to Modify

### 1. `yii/web/css/claude-chat.css`

**Wat verandert:**

Toevoegen van een `@media (max-width: 767.98px)` blok (Bootstrap `md` breakpoint) met:

- **Toolbar compact**: Verberg minder gebruikte toolbar-groepen (align, indent, header-select). Toon alleen: bold/italic/code, lists, code-block, en de utility-knoppen (clear, smart paste, focus). Command dropdown verplaatsen naar boven de toolbar of verbergen achter een "meer" indicator.
- **Editor compacter**: `min-height: 6em` in plaats van `10em`; `max-height: 40vh` in plaats van `min(60vh, 500px)`.
- **Sticky aanpassing**: `top: 56px` (mobiele navbar hoogte) in plaats van `62px`.
- **Focus mode fix**: `padding-bottom: env(safe-area-inset-bottom)` voor iOS; editor container krijgt `overflow-y: auto` zodat content scrollbaar is boven virtueel toetsenbord.
- **Action buttons**: Stack verticaal als er te weinig ruimte is: `flex-wrap: wrap`.
- **Page header**: Verklein `h3` naar `h5` of verberg titel-tekst, toon alleen icon.
- **Response messages**: `claude-message__body` max-height aanpassen van desktop-waarde naar iets dat op mobiel werkt.

**Wat verandert NIET:**
- Desktop styling blijft 100% intact (alle wijzigingen zitten in media queries)
- Kleurenschema, animaties, existing responsive regel op 576px

**Pseudo-diff:**
```css
/* === MOBILE OPTIMIZATIONS === */
@media (max-width: 767.98px) {
    /* Compactere page header */
    .claude-chat-page .h3 {
        font-size: 1.1rem;
    }

    /* Toolbar: verberg minder essentiële groepen */
    .claude-chat-page #claude-quill-toolbar .ql-formats:nth-child(4), /* indent */
    .claude-chat-page #claude-quill-toolbar .ql-formats:nth-child(5), /* header select */
    .claude-chat-page #claude-quill-toolbar .ql-formats:nth-child(6)  /* align */ {
        display: none;
    }

    /* Compactere editor */
    .claude-chat-page .claude-prompt-card-sticky .resizable-editor-container {
        max-height: 40vh;
    }
    .claude-chat-page .claude-prompt-card-sticky .resizable-editor {
        min-height: 6em;
    }

    /* Sticky offset aanpassen voor mobiele navbar */
    .claude-chat-page .claude-prompt-card-sticky {
        top: 56px;
    }

    /* Focus mode: safe area voor iOS */
    .claude-chat-page.claude-focus-mode .claude-prompt-card-sticky {
        padding-bottom: env(safe-area-inset-bottom, 0px);
    }

    /* Action buttons wrappen */
    .claude-chat-page .claude-prompt-section .mt-3 {
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    /* Send button: volledige breedte op mobiel */
    .claude-chat-page #claude-send-btn {
        flex: 1;
        min-width: 120px;
    }
}
```

---

### 2. `yii/views/claude/index.php`

**Wat verandert:**

- **Touch-friendly send**: Toevoegen van een touch event listener naast de bestaande keyboard shortcuts, zodat de Send-knop prominent genoeg is (al het geval, maar verifiëren dat `touch-action` niet geblokkeerd is).
- **Viewport meta check**: Verifiëren dat `_base.php` layout een correcte `<meta name="viewport" content="width=device-width, initial-scale=1">` heeft (Bootstrap vereist dit).
- **Optional: textarea als default op mobile**: Op mobiele apparaten de Quill editor optioneel vervangen door de plaintext textarea als standaard input-modus, omdat Quill op mobiel aanzienlijk slechter werkt dan een native `<textarea>`. Dit kan via een JS-check op viewport-breedte bij init.

**Pseudo-diff voor JS (in `init` method):**
```javascript
init: function() {
    this.prefillFromDefaults();
    this.checkConfigStatus();
    this.fetchSubscriptionUsage();
    this.updateSettingsSummary();
    this.setupEventListeners();
    this.startUsageAutoRefresh();

    // Op mobiel: switch naar textarea als default
    if (window.innerWidth < 768 && this.inputMode === 'quill')
        this.switchToTextareaNoConfirm();
},
```

Plus een helper `switchToTextareaNoConfirm` die hetzelfde doet als `switchToTextarea` maar zonder de `confirm()` dialog (want er is geen formatting verlies bij eerste load).

**Wat verandert NIET:**
- Alle PHP backend-logica
- URL-structuur en routing
- Quill editor initialisatie (blijft beschikbaar als fallback)
- SSE streaming implementatie
- Session management
- Save dialog workflow
- RBAC en permission checks
- Alle bestaande keyboard shortcuts (ze doen niets op mobiel, maar verwijderen heeft geen zin)

---

### 3. `yii/views/layouts/_base.php` (verificatie — geen wijziging nodig)

Viewport meta tag is al aanwezig op regel 13:
```php
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
```

**Geen wijziging nodig.**

---

## Implementation Order

1. **CSS media queries** (`claude-chat.css`) — Alle responsive aanpassingen. Verificatie: visuele inspectie in browser dev tools mobile emulation.
2. **Viewport meta verificatie** (`_base.php`) — Check en fix indien nodig.
3. **JS mobile defaults** (`index.php`) — Textarea als default op mobiel, `switchToTextareaNoConfirm` helper.
4. **Testing** — Handmatig testen op mobile viewport widths (320px, 375px, 414px) via browser dev tools.

---

## Security Checklist

| Item | Status |
|------|--------|
| Geen nieuwe endpoints | N.v.t. — alle bestaande endpoints blijven ongewijzigd |
| Geen nieuwe user input | N.v.t. — CSS-only en minor JS viewport check |
| CSRF tokens | Ongewijzigd — alle fetch calls behouden X-CSRF-Token header |
| XSS | Ongewijzigd — DOMPurify sanitization blijft intact |
| RBAC | Ongewijzigd — controller `behaviors()` niet aangepast |

---

## Migration Plan

Geen migratie nodig. Alle wijzigingen zijn frontend-only (CSS + JS).

---

## Test Plan

Geen geautomatiseerde tests nodig — dit zijn puur visuele/CSS-wijzigingen. Handmatig testen:

| Scenario | Methode |
|----------|---------|
| Toolbar past op 320px breed scherm | Chrome DevTools → iPhone SE emulation |
| Editor is compact maar bruikbaar op mobiel | Chrome DevTools → iPhone 12/13 emulation |
| Sticky prompt editor zit correct onder navbar | Scroll test in mobile emulation |
| Focus mode werkt met virtueel toetsenbord | Chrome DevTools met keyboard overlay simulatie |
| Send button is groot genoeg voor touch (min 44px) | Inspectie in DevTools |
| Streaming werkt op mobiel | Functionele test in mobile emulation |
| Textarea is default input op mobiel | Viewport resize test |
| Save dialog modal bruikbaar op mobiel | Modal open/close test |
| Usage bars tonen correct (bestaande 576px regel) | Visuele inspectie |

---

## What Does NOT Change

| Component | Reden |
|-----------|-------|
| `ClaudeController.php` | Geen backend-wijzigingen nodig |
| `ClaudeCliService.php` | Geen backend-wijzigingen nodig |
| `ClaudeQuickHandler.php` | Geen backend-wijzigingen nodig |
| `ClaudeWorkspaceService.php` | Geen backend-wijzigingen nodig |
| Alle models en query classes | Geen datamodel-wijzigingen |
| RBAC rules | Geen permissie-wijzigingen |
| SSE streaming protocol | Werkt al op mobiel |
| Save dialog logica | Alleen visuele aanpassingen via CSS |
| Keyboard shortcuts | Blijven bestaan voor desktop, onzichtbaar op mobiel |
| Quill editor als optie | Blijft beschikbaar, alleen niet meer default op mobiel |
| Desktop ervaring | Alle wijzigingen in media queries |

---

## Dependencies

Geen nieuwe dependencies.

---

## Risks and Mitigations

| Risico | Impact | Mitigatie |
|--------|--------|-----------|
| Quill editor werkt slecht op sommige mobiele browsers (Android WebView, oudere Safari) | Editor niet bruikbaar | Textarea als default op mobiel; Quill toggle beschikbaar als fallback |
| iOS virtueel toetsenbord bedekt editor in focus mode | Gebruiker kan niet typen | `env(safe-area-inset-bottom)` + `visualViewport` API voor dynamische aanpassing |
| Toolbar verbergen kan power users frustreren die mobiel formatting willen | Minder functionaliteit | Quill editor (met volledige toolbar) blijft beschikbaar via "Switch to rich editor" toggle |
| Sticky navbar hoogte verschilt per apparaat/browser | Editor overlapt met navbar of zweeft | Variabele `top` waarde via CSS custom property, of `calc()` met een veilige marge |
| `window.innerWidth` check bij init mist landscape → portrait rotatie | Verkeerde modus na rotatie | `matchMedia` listener of `resize` event voor dynamische aanpassing |

---

## Open Questions

1. **Moet de Quill editor volledig uitgeschakeld worden op mobiel, of alleen niet-default?** — Het huidige plan maakt textarea de default maar houdt Quill beschikbaar via toggle. Als Quill problemen geeft op specifieke mobiele browsers, kan het volledig verborgen worden.

2. **Is er een minimaal ondersteund mobiel viewport?** — Dit plan optimaliseert voor 320px+ (iPhone SE). Smallere viewports worden niet expliciet ondersteund.

3. **Wordt er een dedicated mobiele app overwogen?** — Dit plan gaat uit van de bestaande web-app. Een PWA (Progressive Web App) of native app zou een apart project zijn met andere architectuuroverwegingen (offline support, push notifications, native UI).

---

## Samenvatting

De "Talk to Claude" functionaliteit is **goed te gebruiken op mobiel** met relatief kleine aanpassingen:

- **Laag risico**: Alle wijzigingen zijn CSS media queries + een kleine JS viewport check
- **Geen backend-wijzigingen**: De API-laag werkt al platform-onafhankelijk
- **Estimated impact**: ~100 regels CSS + ~15 regels JS
- **Core insight**: De single-column layout en Bootstrap 5 basis maken 80% van het werk al. De resterende 20% is toolbar-optimalisatie, editor-sizing, en textarea-als-default op mobiel.
