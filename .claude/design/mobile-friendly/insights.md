# Mobile-Friendly PromptManager — Inzichten & Beslissingen

## Beslissingen

### 1. CSS-first aanpak, geen component library

**Beslissing:** Gebruik CSS media queries en minimale JS, geen externe mobile UI library.

**Reden:** De applicatie draait al op Bootstrap 5 met Bootswatch Spacelab theme. Een extra framework (bijv. Framework7, Ionic) zou conflicten veroorzaken en de bundle groter maken. Bootstrap 5 heeft voldoende responsive utilities.

### 2. Bottom navigation bar in plaats van floating action button

**Beslissing:** Een vaste bottom nav met 4 items (Generate, Notes, Claude, Search).

**Reden:** De 4 primaire acties zijn gelijkwaardig — geen enkele verdient een FAB boven de rest. Een bottom nav is het standaard patroon voor mobile web apps (Material Design, Apple HIG).

### 3. CSS tabel-naar-card transformatie in plaats van ListView alternatief

**Beslissing:** Gebruik CSS `display: block` op `<tr>/<td>` met `data-label` attributen, in plaats van een aparte ListView voor mobiel.

**Reden:**
- Eén HTML-output voor alle viewports (geen dubbele rendering)
- Yii2 GridView behoudt sorteer- en filterfunctionaliteit
- Minder PHP-code wijzigingen
- Bewezen patroon (responsive tables CSS pattern)

### 4. Bestaande breakpoint hergebruik

**Beslissing:** 767.98px als primair mobiel breakpoint (consistent met claude-chat.css).

**Reden:** De Claude chat-interface heeft dit breakpoint al bewezen. Consistentie voorkomt edge cases waar de ene component mobiel is en de andere niet.

### 5. Geen fullscreen Select2 op mobiel

**Beslissing:** Select2 blijft een dropdown maar met betere max-height constraints.

**Alternatief overwogen:** Fullscreen select overlay. Afgewezen omdat dit een significante JS-wijziging vereist in Select2's rendering, wat fragiel is.

### 6. Navbar expand verlagen naar lg

**Beslissing:** Van `navbar-expand-xl` (1200px) naar `navbar-expand-lg` (992px).

**Reden:** Op tablets (768–1199px) is de volledige navbar te breed met project selector en search. Het hamburger menu werkt beter op deze schermformaten.

---

## Aandachtspunten voor Implementatie

### Quill Editor Fixed Toolbar

De huidige `.ql-toolbar-fixed` positioneert op `top: 56px`. Op mobiel met een geopend hamburger menu kan dit conflicteren. De fixed toolbar moet `z-index` lager zijn dan het hamburger menu (1035 vs 1030).

### GridView Clickable Rows

De huidige index views hebben `onclick` handlers op `<tr>` elementen. Bij de CSS card-transformatie moeten deze blijven werken. De hele card moet klikbaar zijn — test of `display: block` op `<tr>` het click event behoudt.

### GridView CSS Selector Structuur

Yii2 GridView genereert: `<div class="grid-view"><table data-responsive="true">`. Het `data-responsive` attribuut zit op de `<table>`, niet op de wrapper `.grid-view` div. CSS selectors moeten dus `.grid-view table[data-responsive="true"]` gebruiken — niet `.grid-view[data-responsive="true"]`.

### Note Index View — Ontbrekend Attribuut

`note/index.php` is de enige index view die géén `data-responsive="true"` op de GridView tableOptions heeft. Dit moet toegevoegd worden, anders werkt de card-layout CSS niet op deze pagina.

### Project Selector Positionering

De huidige JS in `main.php` (regels 174-199) berekent de positie van de project selector op basis van het "Notes" link element. Op mobiel waar het hamburger menu is ingesteld, bestaat dit element niet altijd in de viewport. De JS moet graceful omgaan met een ontbrekend `#nav-notes a` element.

**Breakpoint sync:** De JS checkt `window.innerWidth >= 1200` (xl breakpoint). Na wijziging naar `navbar-expand-lg` moet dit `>= 992` worden, anders is de project selector incorrect gepositioneerd tussen 992-1199px.

### iOS Safe Area

De Claude chat CSS gebruikt `env(safe-area-inset-bottom)` correct. Dezelfde techniek moet consistent op de bottom nav en sticky knoppen.

**`viewport-fit=cover` is vereist:** `_base.php` heeft dit momenteel NIET in de viewport meta tag. Dit moet toegevoegd worden als pre-implementatie stap — zonder dit werken alle `env(safe-area-inset-*)` waarden niet op iOS Safari.

### Claude Chat Body Class — Opgelost

`claude-chat-page` is een class op een `<div>` container, niet op `<body>`. Een CSS regel `body.claude-chat-page .bottom-nav` werkt daarom niet. **Opgelost:** Bottom nav wordt server-side niet gerenderd als `Yii::$app->controller->id === 'claude'`. Dit is eenvoudiger dan een body class hack + `!important` override.

### Bestaande Quick Search Breakpoint

De huidige media query in site.css voor quick search responsiveness gebruikt `@media (max-width: 1199px)`. Bij navbar-expand-lg moet dit `991.98px` worden, anders gedraagt de quick search zich als mobiel terwijl de navbar nog volledig zichtbaar is.

### Soft Keyboard Interactie

Wanneer een Quill editor of input field focus krijgt op mobiel, duwt het soft keyboard de viewport omhoog. De bottom nav moet dan verborgen worden via `visualViewport` API:

```javascript
if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', () => {
        const keyboardOpen = window.visualViewport.height < window.innerHeight * 0.75;
        document.getElementById('bottom-nav')?.classList.toggle('d-none', keyboardOpen);
    });
}
```

### Niet-bestaande Classes in Origineel Plan

De volgende classes bestaan niet in de codebase en moeten ofwel aangemaakt worden (via HTML-wijzigingen) ofwel vervangen door bestaande selectors:

| Originele class | Status | Oplossing |
|-----------------|--------|-----------|
| `.form-actions` | Bestaat niet | Gebruik `.text-end .btn` (werkelijke DOM structuur) |
| `.form-group .btn` | `.form-group` niet in views | Vervangen door `.text-end .btn` en `.d-flex .btn` |
| `.prompt-step-buttons` | Bestaat niet | Voeg class toe aan `prompt-instance/_form.php` |
| `.generated-prompt-viewer` | Bestaat niet | Gebruik `#final-prompt-container` |
| `.copy-to-clipboard` | Bestaat niet | Gebruik `.copy-button-container` en `.cli-copy-button-container` |
| `.copy-toast` | Niet van toepassing | CopyToClipboardWidget toont feedback via CSS class toggle op button, geen toast element — §6.3 in plan.md aangepast |
| `form.js` | Bestand bestaat niet | Gebruik inline script in `main.php` |

### Paginatie CSS Classes — Kritieke Fix (Geverifieerd)

**Huidige staat geverifieerd:** Alle 5 index views (behalve note/index.php) hebben:
```php
'firstPageCssClass' => 'page-item',  // ZONDER suffix
'lastPageCssClass' => 'page-item',   // ZONDER suffix
'nextPageCssClass' => 'page-item',   // ZONDER suffix
'prevPageCssClass' => 'page-item',   // ZONDER suffix
```

De mobiele paginatie CSS (§2.3 in plan.md) vereist suffixen voor de selector `.page-item:not(.prev):not(.next):not(.first):not(.last):not(.active)` om te werken.

**Oplossing:** Wijzig alle 6 views naar `'page-item first'`, `'page-item last'`, `'page-item next'`, `'page-item prev'`.

### Paginatie Summary/Pager Overlap op Mobiel

Alle index views gebruiken `position-absolute start-0 top-50 translate-middle-y` voor de summary tekst in de card-footer. Op mobiel (<768px) overlapt dit met de gecentreerde paginatie. **Oplossing:** Op mobiel de card-footer naar een flexbox column layout omzetten via CSS (`position: static !important`, `display: flex`, `flex-direction: column`).

### Note Index Pager Afwijking

`note/index.php` mist niet alleen `data-responsive` op tableOptions, maar ook `firstPageLabel`, `lastPageLabel`, `firstPageCssClass`, `lastPageCssClass`, `nextPageCssClass` en `prevPageCssClass` in de pager configuratie. Dit moet genormaliseerd worden naar het standaardpatroon van de andere 5 index views.

### Media Query Inconsistentie

De bestaande site.css heeft twee media queries die niet consistent zijn:
- Regel 535: `@media (max-width: 1199px)` — zonder `.98`
- Regel 683: `@media (max-width: 1199.98px)` — met `.98`

Beide worden verlaagd naar `991.98px`. De inconsistentie (zonder `.98`) wordt hierbij genormaliseerd naar de Bootstrap 5 conventie.

### Touch Target CSS: Alleen Mobiel

Touch target CSS (min-width/min-height 44px) voor `.grid-view .btn-sm`, `.copy-button-container .btn`, en `.cli-copy-button-container .btn` moet binnen een `@media (max-width: 767.98px)` query staan. Anders forceert het 44x44px afmetingen op desktop, wat onnodig groot is.

### Collapsible Secties: Veiligere Selector

De oorspronkelijke selector `.card .collapse.show` was te breed en treft ook niet-formulier collapsibles. De veiligere selector is `.collapse.show:not(.accordion-collapse)` — dit sluit Bootstrap accordions uit die hun eigen state management hebben.

### Project Selector HTML Positie

De `.project-context-wrapper` staat buiten het navbar collapse `<div>`. Op mobiel met `position: static` verschijnt hij in de DOM flow maar **niet** binnen het hamburger menu. Dit kan visueel verwarrend zijn. **Nader onderzoek nodig:** verplaats de wrapper HTML naar binnen het collapse element, of accepteer dat hij altijd zichtbaar is boven het collapsed menu.

### Quick-search.js — Additieve Aanpak

**Beslissing:** Voeg mobiele code toe aan het EINDE van quick-search.js, in plaats van het bestand volledig te herschrijven.

**Reden:** De bestaande desktop code (194 regels) is werkend en getest. Een volledige refactor introduceert regressierisico. De mobiele code dupliceert ~30 regels helper functies (`escapeHtml`, `highlightMatch`, `groupLabels`) maar dit is een acceptabele trade-off.

**Trade-off:** Helper duplicatie vs. regressierisico. Desktop functionaliteit blijft 100% ongewijzigd.

### Inline Scripts — Consolidatie

Twee inline `<script>` blokken (collapsible secties + soft keyboard handling) zijn geconsolideerd tot één blok in main.php. Dit verbetert onderhoudbaarheid en vermindert DOM-parsing overhead.

---

## Bug Fixes (Post-Implementation)

### MobileCardView Not Showing Items

**Probleem:** Na initiële implementatie werden MobileCardView cards niet getoond op mobiel.

**Oorzaak:** CSS selector `.grid-view .mobile-card-view { display: block; }` verwachtte dat MobileCardView een child was van `.grid-view`. Maar MobileCardView wordt vóór de GridView gerenderd als een aparte widget, niet erin.

**Fix:** Verander de CSS selector van `.grid-view .mobile-card-view` naar simpelweg `.mobile-card-view` binnen de mobile media query.

### Project Selector Hidden on Mobile

**Probleem:** Na initiële implementatie was de project context selector onzichtbaar op mobiel.

**Oorzaak:** De CSS `position: static !important` verwijderde de fixed positioning, maar de element staat buiten de navbar collapse wrapper. Wanneer de navbar collapsed, is dit element niet onderdeel van het hamburger menu en heeft geen zichtbare plek in de viewport.

**Fix:** Behoud `position: fixed` op mobiel, maar positioneer de selector boven de bottom nav bar (via `bottom: calc(70px + env(safe-area-inset-bottom) + 8px)`). Dit maakt de selector altijd zichtbaar als een floating element linksonder.

---

## Wat Niet Te Doen

1. **Geen aparte mobiele views** — dit verdubbelt de onderhoudslast
2. **Geen JavaScript-only responsive logic** — CSS media queries zijn stabieler en sneller
3. **Geen touch event libraries** — Bootstrap 5 werkt al met touch events
4. **Geen veranderingen aan de Quill editor source** (`npm/src/js/editor-init.js`) — alle aanpassingen via CSS
5. **Geen viewport-afhankelijke server-side rendering** — de server stuurt dezelfde HTML
6. **Geen swipe-to-collapse** — vereist touch event handling buiten CSS-first scope
7. **Geen fullscreen Select2 overlays** — te fragiel, standaard dropdown met max-height volstaat
8. **Geen volledige herschrijving van quick-search.js** — additieve aanpak voorkomt desktop regressie
