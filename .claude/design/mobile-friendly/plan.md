# Mobile-Friendly PromptManager — Technisch Plan

## Overzicht

Alle wijzigingen zijn CSS-first met minimale JavaScript. Geen backend wijzigingen. De aanpak is mobile-first responsive enhancement bovenop het bestaande desktop design.

**Referentiepunt:** De mobiele CSS in `claude-chat.css` (regels 1458-1566) dient als bewezen patroon.

**Dependency check:** Verifieer voor implementatie:
- [x] Bootstrap Icons beschikbaar via CDN in `_base.php` regel 17
- [x] Geen conflicterende `@media (max-width: 767.98px)` regels in site.css — enige 767px media query is `@media (max-width: 767px)` op regel 68 voor `.nav li > form > button.logout`, dit conflicteert niet

---

## Pre-implementatie: Viewport Meta Tag

**Bestand:** `yii/views/layouts/_base.php`

**Wijziging:** Voeg `viewport-fit=cover` toe voor iOS safe area support (regel 13):

```php
// Van:
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
// Naar:
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no, viewport-fit=cover']);
```

**Waarom:** Zonder `viewport-fit=cover` werken alle `env(safe-area-inset-*)` waarden niet op iOS Safari.

---

## Fase 1: Foundation — Navigatie & Layout

### 1.1 Navbar Breakpoint Verlagen

**Bestand:** `yii/views/layouts/main.php`

**Wijziging 1:** Verander `navbar-expand-xl` naar `navbar-expand-lg` (regel 19):

```php
// Van:
'options' => ['class' => 'navbar-expand-xl navbar-dark bg-primary fixed-top'],
// Naar:
'options' => ['class' => 'navbar-expand-lg navbar-dark bg-primary fixed-top'],
```

**Wijziging 2:** Pas de project selector positionering JS aan (regel 178):

```javascript
// Van:
const isDesktop = window.innerWidth >= 1200; // Bootstrap xl breakpoint
// Naar:
const isDesktop = window.innerWidth >= 992; // Bootstrap lg breakpoint
```

**Wijziging 3:** Pas de bestaande quick-search media query aan in `yii/web/css/site.css` (regel 535):

```css
/* Van: @media (max-width: 1199px) — wordt: */
@media (max-width: 991.98px) {
    .navbar .quick-search-container {
        margin-left: 0;
        margin-top: 0.5rem;
        width: 100%;
        margin-right: 0;
    }

    .navbar #quick-search-input,
    .navbar #quick-search-input:focus {
        flex: 1;
        width: auto;
    }

    .navbar #quick-search-results {
        width: 100%;
        left: 0;
        right: 0;
    }
}
```

**Wijziging 4:** Voeg nieuwe media query toe voor project-context-wrapper in `yii/web/css/site.css` (na bestaande `.project-context-wrapper` regels, circa regel 700):

```css
@media (max-width: 991.98px) {
    .project-context-wrapper {
        position: static;
        transform: none;
        width: 100%;
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
    }
    .project-context-wrapper .form-select {
        background-color: rgba(255, 255, 255, 0.15);
        width: 100%;
    }
}
```

**Impact:** Navbar collapsed op < 992px in plaats van < 1200px. Hamburger menu eerder zichtbaar. Project selector integreert in het hamburger menu.

**Designbesluit — Project selector positie:**

De `.project-context-wrapper` staat buiten het navbar collapse element (regel 120-128 in main.php). Dit betekent dat de selector op mobiel **boven** het hamburger menu verschijnt (niet erin). Dit is een bewuste UX-keuze:
- Voordeel: Project context altijd zichtbaar, geen extra tap nodig
- Nadeel: Minder conventionele hamburger menu integratie

Alternatief (buiten scope): HTML-verplaatsing naar binnen `NavBar::begin()`/`NavBar::end()` vereist Yii2 widget customization.

**Verificatie (stap 1.1):**
1. Open app in Chrome DevTools
2. Resize naar 992px breed → hamburger icoon moet verschijnen
3. Resize naar 991px breed → navbar moet collapsed zijn
4. Click hamburger → menu opent, quick search full-width
5. Project selector zichtbaar boven het collapsed menu

---

### 1.2 Bottom Navigation Bar + Search Overlay (Atomaire stap)

**Afhankelijkheid:** Fase 1.1 moet afgerond zijn (navbar collapse werkt).

**Bestanden:**
- `yii/views/layouts/main.php` — HTML toevoegen
- `yii/web/css/site.css` — styling
- `yii/web/js/quick-search.js` — refactor voor gedeelde functies

#### 1.2.1 Bottom Nav HTML

**Bestand:** `yii/views/layouts/main.php`

Voeg bottom nav HTML toe na `</main>` (regel 142), voor de advanced search modal include. Alleen voor ingelogde gebruikers en niet op Claude chat pagina:

```php
<?php if (!Yii::$app->user->isGuest && Yii::$app->controller->id !== 'claude'): ?>
<nav class="bottom-nav d-md-none" id="bottom-nav">
    <?php
    $controller = Yii::$app->controller->id;
    $action = Yii::$app->controller->action->id;
    ?>
    <a href="<?= \yii\helpers\Url::to(['/prompt-instance/create']) ?>"
       class="<?= ($controller === 'prompt-instance' && $action === 'create') ? 'active' : '' ?>">
        <i class="bi bi-lightning"></i><span>Generate</span>
    </a>
    <a href="<?= \yii\helpers\Url::to(['/note/index']) ?>"
       class="<?= ($controller === 'note') ? 'active' : '' ?>">
        <i class="bi bi-journal-text"></i><span>Notes</span>
    </a>
    <a href="<?= \yii\helpers\Url::to(['/claude/index']) ?>"
       class="<?= ($controller === 'claude') ? 'active' : '' ?>">
        <i class="bi bi-terminal"></i><span>Claude</span>
    </a>
    <button type="button" id="bottom-nav-search">
        <i class="bi bi-search"></i><span>Search</span>
    </button>
</nav>
<?php endif; ?>
```

**Active state:** Server-side via PHP `$controller` check — simpel en betrouwbaar.

#### 1.2.2 Search Overlay HTML

**Bestand:** `yii/views/layouts/main.php`

Voeg HTML toe direct na de bottom nav, binnen dezelfde `!Yii::$app->user->isGuest` check:

```html
<div class="quick-search-overlay" id="quick-search-overlay">
    <div class="quick-search-overlay-header">
        <input type="text" id="mobile-search-input" class="form-control" placeholder="Search..." autocomplete="off">
        <button type="button" id="close-search-overlay" class="btn btn-link"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="quick-search-overlay-results" id="mobile-search-results"></div>
</div>
```

#### 1.2.3 Bottom Nav & Overlay CSS

**Bestand:** `yii/web/css/site.css`

Voeg toe **aan het einde van het bestand** (na alle bestaande regels):

```css
/* ============================================
   MOBILE: Bottom Navigation Bar
   ============================================ */
.bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: calc(56px + env(safe-area-inset-bottom, 0px));
    padding-bottom: env(safe-area-inset-bottom, 0px);
    background: #fff;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: space-around;
    align-items: center;
    z-index: 1030;
}

.bottom-nav a,
.bottom-nav button {
    display: flex;
    flex-direction: column;
    align-items: center;
    font-size: 0.7rem;
    color: #6c757d;
    text-decoration: none;
    padding: 4px 12px;
    min-width: 64px;
    min-height: 44px;
    background: none;
    border: none;
    cursor: pointer;
}

.bottom-nav a.active {
    color: var(--bs-primary);
}

.bottom-nav i {
    font-size: 1.25rem;
    margin-bottom: 2px;
}

@media (max-width: 767.98px) {
    main > .container {
        padding-bottom: calc(56px + env(safe-area-inset-bottom, 0px) + 20px);
    }
}

/* ============================================
   MOBILE: Search Overlay
   ============================================ */
.quick-search-overlay {
    position: fixed;
    inset: 0;
    z-index: 1050;
    background: #fff;
    display: none;
    flex-direction: column;
}

.quick-search-overlay.show {
    display: flex;
}

.quick-search-overlay-header {
    display: flex;
    align-items: center;
    padding: env(safe-area-inset-top, 8px) 12px 8px 12px;
    border-bottom: 1px solid #dee2e6;
    gap: 8px;
}

.quick-search-overlay-header input {
    flex: 1;
    font-size: 1rem;
    min-height: 44px;
}

.quick-search-overlay-header .btn {
    min-width: 44px;
    min-height: 44px;
    color: #6c757d;
}

.quick-search-overlay-results {
    flex: 1;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

/* Search result item styling (herbruikbaar voor overlay) */
.quick-search-overlay .quick-search-group-title {
    padding: 8px 14px;
    font-size: 0.7rem;
    font-weight: 700;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    background-color: #e9ecef;
    border-bottom: 1px solid #dee2e6;
}

.quick-search-overlay .quick-search-item {
    display: block;
    padding: 12px 14px;
    color: #212529;
    text-decoration: none;
    border-bottom: 1px solid #f0f0f0;
}

.quick-search-overlay .quick-search-item:active {
    background-color: #e7f1ff;
}

.quick-search-overlay .quick-search-item-name {
    font-weight: 500;
    font-size: 0.95rem;
    margin-bottom: 2px;
}

.quick-search-overlay .quick-search-item-subtitle {
    font-size: 0.8rem;
    color: #6c757d;
}

.quick-search-overlay .quick-search-item mark {
    background-color: #fff3cd;
    padding: 1px 3px;
    border-radius: 2px;
}

.quick-search-overlay .quick-search-empty,
.quick-search-overlay .quick-search-loading {
    padding: 20px;
    text-align: center;
    color: #6c757d;
    font-style: italic;
}
```

#### 1.2.4 Quick Search JavaScript — Additieve Aanpak

**Bestand:** `yii/web/js/quick-search.js`

**Aanpak:** Voeg mobiele functionaliteit toe aan het EINDE van het bestaande bestand. De bestaande desktop code (194 regels) blijft onaangeroerd om regressie te voorkomen.

**Toevoegen na regel 194** (na de laatste `});`):

```javascript
// ============================================
// MOBILE: Search Overlay (addition to existing desktop code)
// ============================================
(function initMobileSearch() {
  var bottomNavSearch = document.getElementById("bottom-nav-search");
  var overlay = document.getElementById("quick-search-overlay");
  var mobileInput = document.getElementById("mobile-search-input");
  var mobileResults = document.getElementById("mobile-search-results");
  var closeBtn = document.getElementById("close-search-overlay");

  // Exit early if mobile elements don't exist (desktop view)
  if (!bottomNavSearch || !overlay) return;

  var debounceTimer = null;
  var abortController = null;

  // Reuse groupLabels from global scope (defined in desktop code above)
  var groupLabels = {
    contexts: "Contexts", fields: "Fields", templates: "Templates",
    instances: "Generated", notes: "Notes"
  };

  function escapeHtml(text) {
    var div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function highlightMatch(text, query) {
    if (!query) return escapeHtml(text);
    var escaped = escapeHtml(text);
    var regex = new RegExp("(" + query.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + ")", "gi");
    return escaped.replace(regex, "<mark>$1</mark>");
  }

  function renderMobileResults(data, query) {
    var html = "";
    var hasResults = false;

    Object.entries(groupLabels).forEach(function([key, label]) {
      var items = data[key];
      if (!items || items.length === 0) return;
      hasResults = true;
      html += '<div class="quick-search-group">';
      html += '<div class="quick-search-group-title">' + escapeHtml(label) + "</div>";
      items.forEach(function(item) {
        html += '<a href="' + escapeHtml(item.url) + '" class="quick-search-item">';
        html += '<div class="quick-search-item-name">' + highlightMatch(item.name, query) + "</div>";
        html += '<div class="quick-search-item-subtitle">' + escapeHtml(item.subtitle) + "</div>";
        html += "</a>";
      });
      html += "</div>";
    });

    if (!hasResults) {
      html = '<div class="quick-search-empty">No results found</div>';
    }
    mobileResults.innerHTML = html;
  }

  function performMobileSearch(query) {
    if (abortController) abortController.abort();
    if (query.length < 2) {
      mobileResults.innerHTML = "";
      return;
    }

    abortController = new AbortController();
    mobileResults.innerHTML = '<div class="quick-search-loading">Searching...</div>';

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    var headers = { "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" };
    if (csrfToken) headers["X-CSRF-Token"] = csrfToken.getAttribute("content");

    fetch("/search/quick?q=" + encodeURIComponent(query), {
      method: "GET", headers: headers, signal: abortController.signal
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
      if (result.success) {
        renderMobileResults(result.data, query);
      } else {
        mobileResults.innerHTML = '<div class="quick-search-empty">Search failed</div>';
      }
    })
    .catch(function(error) {
      if (error.name !== "AbortError") {
        mobileResults.innerHTML = '<div class="quick-search-empty">Search failed</div>';
      }
    });
  }

  bottomNavSearch.addEventListener("click", function() {
    overlay.classList.add("show");
    mobileInput.focus();
  });

  closeBtn.addEventListener("click", function() {
    overlay.classList.remove("show");
    mobileInput.value = "";
    mobileResults.innerHTML = "";
  });

  mobileInput.addEventListener("input", function() {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function() {
      performMobileSearch(mobileInput.value.trim());
    }, 300);
  });

  mobileInput.addEventListener("keydown", function(e) {
    if (e.key === "Escape") {
      overlay.classList.remove("show");
      mobileInput.value = "";
      mobileResults.innerHTML = "";
    }
  });
})();
```

**Structuur:** De mobiele code is volledig zelfstandig in een IIFE. Helper functies (`escapeHtml`, `highlightMatch`, `groupLabels`) worden gedupliceerd om de desktop code niet te hoeven refactoren. Dit is een bewuste trade-off: ~30 regels duplicatie vs. risico op desktop regressie.

**Verificatie (stap 1.2):**
1. Resize browser naar 767px breed
2. Bottom nav moet verschijnen met 4 items
3. Tap "Search" → overlay opent
4. Type zoekopdracht → resultaten verschijnen
5. Tap X → overlay sluit
6. Navigeer naar /claude/index → bottom nav mag NIET zichtbaar zijn
7. Resize naar 768px+ → bottom nav verdwijnt

---

## Fase 2: Grid/Tabel Views — Responsive Cards

### 2.1 CSS Card-Layout voor Tabellen

**Bestand:** `yii/web/css/site.css`

Voeg toe aan het einde van het bestand:

```css
/* ============================================
   MOBILE: Table-to-Card transformation
   ============================================ */
@media (max-width: 767.98px) {
    .grid-view table[data-responsive="true"] thead {
        display: none;
    }

    .grid-view table[data-responsive="true"] tbody tr {
        display: block;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        margin-bottom: 0.75rem;
        padding: 12px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }

    .grid-view table[data-responsive="true"] tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 0;
        border: none;
    }

    .grid-view table[data-responsive="true"] tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        font-size: 0.8rem;
        color: #6c757d;
        flex-shrink: 0;
        margin-right: 12px;
    }

    /* Action column: no label, right-aligned */
    .grid-view table[data-responsive="true"] tbody td:last-child {
        justify-content: flex-end;
        padding-top: 8px;
        margin-top: 4px;
        border-top: 1px solid #f0f0f0;
    }

    .grid-view table[data-responsive="true"] tbody td:last-child::before {
        display: none;
    }

    /* Touch-friendly action buttons */
    .grid-view .btn-sm {
        min-width: 44px;
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
}
```

### 2.2 View-specifieke Wijzigingen

#### Pager CSS Classes — Huidige vs. Gewenste Staat

**Huidige staat** (5 van 6 views — note/index.php wijkt af):
```php
'pager' => [
    'options' => ['class' => 'pagination justify-content-center m-3'],
    'linkOptions' => ['class' => 'page-link'],
    'disabledListItemSubTagOptions' => ['class' => 'page-link'],
    'prevPageLabel' => 'Previous',
    'nextPageLabel' => 'Next',
    'firstPageLabel' => 'First',      // ontbreekt in note/index.php
    'lastPageLabel' => 'Last',        // ontbreekt in note/index.php
    'pageCssClass' => 'page-item',
    'firstPageCssClass' => 'page-item',   // ← ZONDER suffix
    'lastPageCssClass' => 'page-item',    // ← ZONDER suffix
    'nextPageCssClass' => 'page-item',    // ← ZONDER suffix
    'prevPageCssClass' => 'page-item',    // ← ZONDER suffix
    'activePageCssClass' => 'active',
    'disabledPageCssClass' => 'disabled',
],
```

**Gewenste staat** (alle 6 views — met suffixen voor mobiele CSS targeting):
```php
'pager' => [
    'options' => ['class' => 'pagination justify-content-center m-3'],
    'linkOptions' => ['class' => 'page-link'],
    'disabledListItemSubTagOptions' => ['class' => 'page-link'],
    'prevPageLabel' => 'Previous',
    'nextPageLabel' => 'Next',
    'firstPageLabel' => 'First',
    'lastPageLabel' => 'Last',
    'pageCssClass' => 'page-item',
    'firstPageCssClass' => 'page-item first',   // ← MET suffix
    'lastPageCssClass' => 'page-item last',     // ← MET suffix
    'nextPageCssClass' => 'page-item next',     // ← MET suffix
    'prevPageCssClass' => 'page-item prev',     // ← MET suffix
    'activePageCssClass' => 'active',
    'disabledPageCssClass' => 'disabled',
],
```

**Reden:** De mobiele paginatie CSS (§2.3) verbergt tussenliggende paginanummers met `.page-item:not(.prev):not(.next):not(.first):not(.last):not(.active)`. Zonder de suffixen werkt deze selector niet.

#### View: `project/index.php`

**Huidige staat:** Heeft `data-responsive="true"` op tableOptions (regel 46). Pager mist specifieke CSS class suffixen.

**Wijzigingen:**
1. Voeg `data-label` attributen toe aan kolommen:

```php
[
    'attribute' => 'name',
    'contentOptions' => ['data-label' => 'Name'],
],
[
    'attribute' => 'label',
    'contentOptions' => ['data-label' => 'Label'],
    'value' => static fn(Project $model) => $model->label ?: '—',
],
[
    'attribute' => 'description',
    'format' => 'text',
    'contentOptions' => ['data-label' => 'Description'],
    // bestaande value callback behouden
],
[
    'attribute' => 'updated_at',
    'label' => 'Updated',
    'format' => ['datetime', 'php:Y-m-d H:i:s'],
    'contentOptions' => ['data-label' => 'Updated'],
],
```

2. Update pager configuratie — voeg suffixen toe aan de 4 navigatie CSS classes:
```php
// Wijzig:
'firstPageCssClass' => 'page-item',
'lastPageCssClass' => 'page-item',
'nextPageCssClass' => 'page-item',
'prevPageCssClass' => 'page-item',
// Naar:
'firstPageCssClass' => 'page-item first',
'lastPageCssClass' => 'page-item last',
'nextPageCssClass' => 'page-item next',
'prevPageCssClass' => 'page-item prev',
```

#### View: `context/index.php`

**Huidige staat:** Heeft `data-responsive="true"` (regel 53). Pager mist suffixen.

**Wijzigingen:**
1. Voeg `data-label` attributen toe: Name, Project, Default, Shared, Updated
2. Update pager CSS classes met suffixen (zie project/index.php)

#### View: `field/index.php`

**Huidige staat:** Heeft `data-responsive="true"` (regel 41). Pager mist suffixen.

**Wijzigingen:**
1. Voeg `data-label` attributen toe: Name, Type, Project, Updated
2. Update pager CSS classes met suffixen

#### View: `prompt-template/index.php`

**Huidige staat:** Heeft `data-responsive="true"` (regel 49). Pager mist suffixen.

**Wijzigingen:**
1. Voeg `data-label` attributen toe: Name, Project, Updated
2. Update pager CSS classes met suffixen

#### View: `prompt-instance/index.php`

**Huidige staat:** Heeft `data-responsive="true"` (regel 67). Pager mist suffixen.

**Wijzigingen:**
1. Voeg `data-label` attributen toe: Label, Template, Updated
2. Update pager CSS classes met suffixen

#### View: `note/index.php` (meeste wijzigingen)

**Huidige staat:** Mist `data-responsive="true"` op tableOptions (regel 78-80). Pager mist `firstPageLabel`, `lastPageLabel`, `firstPageCssClass`, `lastPageCssClass`, `nextPageCssClass`, en `prevPageCssClass`.

**Huidige pager configuratie (regel 81-90):**
```php
'pager' => [
    'options' => ['class' => 'pagination justify-content-center m-3'],
    'linkOptions' => ['class' => 'page-link'],
    'disabledListItemSubTagOptions' => ['class' => 'page-link'],
    'prevPageLabel' => 'Previous',
    'nextPageLabel' => 'Next',
    // ONTBREEKT: firstPageLabel, lastPageLabel
    'pageCssClass' => 'page-item',
    // ONTBREEKT: firstPageCssClass, lastPageCssClass, nextPageCssClass, prevPageCssClass
    'activePageCssClass' => 'active',
    'disabledPageCssClass' => 'disabled',
],
```

**Wijzigingen:**
1. Voeg `data-responsive` toe aan tableOptions:
```php
'tableOptions' => [
    'class' => 'table table-striped table-hover mb-0',
    'data-responsive' => 'true',
],
```

2. Vervang pager configuratie (voeg 6 ontbrekende keys toe):
```php
'pager' => [
    'options' => ['class' => 'pagination justify-content-center m-3'],
    'linkOptions' => ['class' => 'page-link'],
    'disabledListItemSubTagOptions' => ['class' => 'page-link'],
    'prevPageLabel' => 'Previous',
    'nextPageLabel' => 'Next',
    'firstPageLabel' => 'First',              // NIEUW
    'lastPageLabel' => 'Last',                // NIEUW
    'pageCssClass' => 'page-item',
    'firstPageCssClass' => 'page-item first', // NIEUW
    'lastPageCssClass' => 'page-item last',   // NIEUW
    'nextPageCssClass' => 'page-item next',   // NIEUW
    'prevPageCssClass' => 'page-item prev',   // NIEUW
    'activePageCssClass' => 'active',
    'disabledPageCssClass' => 'disabled',
],
```

3. Voeg `data-label` attributen toe: Name, Type, Scope, Updated

### 2.3 Paginatie Vereenvoudigd op Mobiel + Summary Fix

**Bestand:** `yii/web/css/site.css`

```css
@media (max-width: 767.98px) {
    /* Hide intermediate page numbers, keep prev/next/active */
    .pagination .page-item:not(.prev):not(.next):not(.first):not(.last):not(.active) {
        display: none;
    }
    .pagination .page-item .page-link {
        min-width: 44px;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Fix summary/pager overlap in card-footer */
    .card-footer.position-relative {
        position: static !important;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }
    .card-footer .position-absolute {
        position: static !important;
        transform: none !important;
    }
}
```

### 2.4 Tabel Responsive Wrapper op Tablet

**Bestand:** `yii/web/css/site.css`

```css
@media (min-width: 768px) and (max-width: 991.98px) {
    .grid-view {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}
```

**Verificatie (Fase 2):**
1. Open elke index view op 375px breed
2. Verifieer card-layout met labels
3. Verifieer actieknoppen ≥ 44px
4. Verifieer paginatie toont alleen prev/next/active
5. Verifieer summary niet overlapt met paginatie
6. Open op 800px breed → tabel horizontaal scrollbaar

---

## Fase 3: Formulieren

### 3.1 Form Responsive Overrides

**Bestand:** `yii/web/css/site.css`

```css
@media (max-width: 767.98px) {
    /* Larger touch targets for inputs */
    .form-control,
    .form-select {
        min-height: 44px;
        font-size: 1rem;
    }

    /* Full-width submit/cancel buttons on small screens */
    .text-end .btn,
    .d-flex .btn-primary[type="submit"],
    .d-flex .btn-secondary {
        width: 100%;
        min-height: 48px;
        margin-bottom: 0.5rem;
    }
}

@media (max-width: 575.98px) {
    /* Stack button groups on xs */
    .text-end {
        text-align: center !important;
    }
}
```

### 3.2 Quill Editor Toolbar Responsive

**Bestand:** `yii/web/css/site.css`

```css
@media (max-width: 767.98px) {
    /* Hide advanced toolbar groups on mobile */
    .ql-toolbar .ql-indent,
    .ql-toolbar .ql-header,
    .ql-toolbar .ql-align,
    .ql-toolbar .ql-clean {
        display: none;
    }

    /* Constrain editor height */
    .resizable-editor-container {
        max-height: 50vh;
        min-height: 120px;
    }

    /* Fixed toolbar adjustments */
    .resizable-editor-container .ql-toolbar.ql-toolbar-fixed {
        left: 0;
        right: 0;
    }
}
```

### 3.3 Select2 Mobiel Optimalisatie

**Bestand:** `yii/web/css/site.css`

```css
@media (max-width: 767.98px) {
    .select2-container--open .select2-dropdown {
        max-height: 50vh;
    }
    .select2-container--open .select2-results__options {
        max-height: 45vh;
    }
    .select2-container .select2-selection--multiple .select2-selection__rendered {
        flex-wrap: wrap;
    }
}
```

### 3.4 Collapsible Secties Standaard Dicht op Mobiel

**Zie Fase 7** — Dit script is geconsolideerd met het soft keyboard script in één `<script>` blok.

**Verificatie (Fase 3):**
1. Open een formulier op 375px breed
2. Verifieer inputs hebben 44px hoogte
3. Verifieer Quill toolbar past zonder overflow
4. Verifieer Select2 dropdown max 50vh
5. Verifieer submit knop full-width

---

## Fase 4: Prompt Generatie Flow

### 4.1 Sticky Navigatieknoppen

**Bestand:** `yii/views/prompt-instance/_form.php`

**HTML wijziging:** Voeg `prompt-step-buttons` class toe (regel 194):

```php
// Van:
<div class="form-group mt-4 text-end">
// Naar:
<div class="form-group mt-4 text-end prompt-step-buttons">
```

**Bestand:** `yii/web/css/site.css`

```css
@media (max-width: 767.98px) {
    .prompt-step-buttons {
        position: sticky;
        bottom: calc(56px + env(safe-area-inset-bottom, 0px));
        background: #fff;
        border-top: 1px solid #dee2e6;
        padding: 8px 12px;
        z-index: 1020;
        margin: 0 -12px;
        text-align: center;
    }
    .prompt-step-buttons .btn {
        min-height: 44px;
    }
}
```

### 4.2 Generated Prompt Container

**Bestand:** `yii/web/css/site.css`

```css
@media (max-width: 767.98px) {
    #final-prompt-container {
        max-height: 60vh;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
}
```

**Verificatie (Fase 4):**
1. Open /prompt-instance/create op 375px
2. Navigeer door 3-stappen flow
3. Verifieer sticky buttons boven bottom nav
4. Verifieer gegenereerde prompt scrollbaar

---

## Fase 5: Modals

### 5.1 Fullscreen Modals op Mobiel

**Bestand:** `yii/web/css/site.css`

```css
@media (max-width: 575.98px) {
    .modal .modal-dialog {
        max-width: 100%;
        margin: 0;
        min-height: 100vh;
    }
    .modal .modal-content {
        min-height: 100vh;
        border-radius: 0;
        border: none;
    }
}

@media (max-width: 767.98px) {
    #advancedSearchModal .modal-dialog {
        max-width: 100%;
        margin: 0;
        min-height: 100vh;
    }
    #advancedSearchModal .modal-content {
        min-height: 100vh;
        border-radius: 0;
    }

    #path-preview-modal .modal-dialog {
        max-width: 100%;
        margin: 0;
    }
    #path-preview-modal pre {
        max-height: 80vh;
    }
}
```

**Verificatie (Fase 5):**
1. Open een modal op 375px
2. Verifieer fullscreen weergave
3. Verifieer close knop bereikbaar

---

## Fase 6: Homepage & Kleine Aanpassingen

### 6.1 Responsive Homepage

**Bestand:** `yii/web/css/site.css`

```css
@media (max-width: 575.98px) {
    .site-index img {
        max-width: 150px;
    }
    .site-index .btn-lg {
        width: 100%;
    }
}
```

### 6.2 Copy Widget Touch Targets

**Bestand:** `yii/web/css/site.css`

```css
@media (max-width: 767.98px) {
    .copy-button-container .btn,
    .cli-copy-button-container .btn {
        min-width: 44px;
        min-height: 44px;
    }
}
```

### 6.3 Copy Widget Feedback — Geen Wijziging Nodig

**Analyse:** `CopyToClipboardWidget` (yii/widgets/CopyToClipboardWidget.php:64-84) toont feedback via een CSS class toggle op de button zelf (`btn-outline-secondary` → `btn-primary` voor 250ms). Er is geen toast, dus geen positionering conflict met de bottom nav.

**Geen CSS wijziging vereist voor copy feedback.**

**Verificatie (Fase 6):**
1. Open homepage op 375px
2. Verifieer logo 150px max
3. Verifieer CTA full-width
4. Test copy → button kleurt tijdelijk blauw (dit werkt ongeacht bottom nav)

---

## Fase 7: Soft Keyboard Handling + Collapsibles (Geconsolideerd)

**Bestand:** `yii/views/layouts/main.php` (inline script onderaan, vóór `endContent()`)

Dit script combineert:
- Collapsible secties standaard dichtklappen op mobiel (was §3.4)
- Soft keyboard detection voor bottom nav hiding

```html
<script>
(function() {
    var isMobile = window.matchMedia('(max-width: 767.98px)').matches;

    // Collapse non-accordion sections on mobile (Fase 3.4)
    if (isMobile) {
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.collapse.show:not(.accordion-collapse)')
                .forEach(function(el) { el.classList.remove('show'); });
        });
    }

    // Hide bottom nav when soft keyboard opens (Fase 7)
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', function() {
            var nav = document.getElementById('bottom-nav');
            if (!nav) return;
            var keyboardOpen = window.visualViewport.height < window.innerHeight * 0.75;
            nav.classList.toggle('d-none', keyboardOpen);
        });
    }
})();
</script>
```

**Verificatie (Fase 7):**
1. Open formulier op echte mobiele device
2. Tap in input veld → keyboard verschijnt
3. Verifieer bottom nav verdwijnt wanneer keyboard open is
4. Sluit keyboard → bottom nav keert terug
5. Verifieer collapsible secties (niet-accordions) starten dichtgeklapt op mobiel

---

## Implementatievolgorde met Afhankelijkheden

```
Stap 0: Pre-implementatie
└── _base.php viewport meta tag
    └── [Geen afhankelijkheden]

Stap 1: Foundation (atomair)
├── 1.1 Navbar breakpoint
│   └── [Afhankelijk van: niets]
└── 1.2 Bottom nav + Search overlay (samen implementeren)
    └── [Afhankelijk van: 1.1 afgerond]

Stap 2: Grid/Tabel Views
├── 2.1 CSS card-layout
├── 2.2 data-label in 6 views
├── 2.3 Paginatie CSS
└── 2.4 Tablet scroll
    └── [Afhankelijk van: 1.2 afgerond (voor bottom padding)]

Stap 3: Formulieren
├── 3.1 Form responsive
├── 3.2 Quill toolbar
├── 3.3 Select2
└── 3.4 Collapsibles
    └── [Afhankelijk van: niets specifiek]

Stap 4: Prompt Generatie
├── 4.1 Sticky buttons
└── 4.2 Generated prompt container
    └── [Afhankelijk van: 1.2 (bottom nav hoogte)]

Stap 5: Modals
└── 5.1 Fullscreen modals
    └── [Afhankelijk van: niets]

Stap 6: Kleine aanpassingen
├── 6.1 Homepage
├── 6.2 Copy touch targets
└── 6.3 Toast positioning
    └── [Afhankelijk van: 1.2 (bottom nav hoogte)]

Stap 7: Soft keyboard
└── [Afhankelijk van: 1.2 (bottom nav element)]
```

---

## Bestanden die Wijzigen

### CSS (additief — aan einde van bestand)
- `yii/web/css/site.css` — bulk van de mobiele CSS + aanpassing bestaande media query (1199px → 991.98px)
  - **Volgorde van toe te voegen CSS blokken:**
    1. Bottom Navigation Bar (§1.2.3)
    2. Search Overlay (§1.2.3)
    3. Table-to-Card transformation (§2.1)
    4. Paginatie vereenvoudigd + Summary fix (§2.3)
    5. Tablet scroll (§2.4)
    6. Form responsive (§3.1)
    7. Quill toolbar (§3.2)
    8. Select2 (§3.3)
    9. Prompt step buttons (§4.1)
    10. Generated prompt container (§4.2)
    11. Fullscreen modals (§5.1)
    12. Homepage (§6.1)
    13. Copy touch targets (§6.2)

### JavaScript (refactor + toevoeging)
- `yii/web/js/quick-search.js` — refactor naar gedeelde search/render functies + mobiele overlay binding

### PHP Views (HTML wijzigingen)
- `yii/views/layouts/_base.php` — viewport meta tag: wijzig regel 13 `content` attribuut van `'width=device-width, initial-scale=1, shrink-to-fit=no'` naar `'width=device-width, initial-scale=1, shrink-to-fit=no, viewport-fit=cover'`
- `yii/views/layouts/main.php` — navbar class, bottom nav HTML, search overlay HTML, inline JS (collapse + keyboard)
- `yii/views/project/index.php` — `data-label` attributen + pager CSS classes
- `yii/views/context/index.php` — `data-responsive` + `data-label` attributen + pager CSS classes
- `yii/views/field/index.php` — `data-responsive` + `data-label` attributen + pager CSS classes
- `yii/views/prompt-template/index.php` — `data-responsive` + `data-label` attributen + pager CSS classes
- `yii/views/prompt-instance/index.php` — `data-responsive` + `data-label` attributen + pager CSS classes
- `yii/views/prompt-instance/_form.php` — `prompt-step-buttons` wrapper class
- `yii/views/note/index.php` — `data-responsive` + `data-label` + pager normalisatie

### Geen Wijzigingen
- Backend PHP (controllers, models, services)
- Database / Migraties
- Quill editor-init.js (npm source)
- Config bestanden
- `yii/views/claude/index.php`
- `yii/web/css/claude-chat.css`

---

## Geschrapte Requirements t.o.v. Spec

| Requirement | Reden |
|-------------|-------|
| REQ-CHAT-03 (swipe-to-collapse) | Vereist touch event library; buiten scope van CSS-first aanpak |
| REQ-GEN-02 (fullscreen Select2 met checkboxes) | Contradiceert CSS-first beslissing; Select2 krijgt betere max-height constraints |
| REQ-GEN-03 (fullscreen template select) | Zelfde reden als REQ-GEN-02 |

---

## Risico's & Mitigatie

| Risico | Impact | Mitigatie |
|--------|--------|----------|
| CSS breakpoint conflicten met bestaande styling | Desktop layout breekt | Alle nieuwe CSS in media queries; test desktop na elke fase |
| Select2 dropdown gedrag op iOS | Selectie niet bruikbaar | Test op echte iOS device; fallback naar native `<select>` indien nodig |
| Fixed positioning + iOS Safari keyboard | Elementen overlappen | Gebruik `env(safe-area-inset-*)` consistent; test met soft keyboard |
| GridView `data-label` rendering met `display: block` op `<tr>` | Cards tonen niet correct | Verifieer Yii2 GridView HTML output; test CSS op daadwerkelijke DOM structuur |
| Bottom nav overlapt content | Laatste items onbereikbaar | `padding-bottom` met `calc()` + `env()` op main container |
| Quick search overlay JS binding | Zoekfunctie werkt niet op mobiel | Hergebruik gedeelde fetch logica; test debounce en resultaat rendering |

---

## Verificatie Checklist

### Na elke fase

- [ ] Desktop regressie (geen layout breaks op 1200px+)
- [ ] Tablet (768-991px): navbar collapsed, tabellen scrollbaar
- [ ] Mobiel (375px): bottom nav, cards, formulieren, modals

### Eindverificatie

| Test | Schermformaat | Verwacht |
|------|--------------|----------|
| Navigatie + hamburger menu | 375px (iPhone) | Menu opent/sluit, alle links werken |
| Bottom nav | 375px | 4 iconen zichtbaar, navigatie werkt |
| Search overlay | 375px | Opent via bottom nav, zoekt correct |
| Project index card-layout | 375px | Cards in plaats van tabel |
| Project create formulier | 375px | Alle velden invulbaar, submit werkt |
| Prompt generatie 3-stappen | 375px | Volledige flow van selectie tot save |
| Context select (Select2) | 375px | Selectie bruikbaar, geen overflow |
| Quill editor | 375px | Toolbar past, tekst invoerbaar |
| Claude chat | 375px | Geen bottom nav, bestaande functionaliteit behouden |
| Tablet tabel scroll | 768px | Tabel horizontaal scrollbaar |
| Landscape orientation | 667px x 375px | Layout herberekent correct |
| iOS Safari safe area | iPhone met notch | Bottom nav respecteert safe area |
| Soft keyboard | Echte device | Bottom nav verdwijnt bij keyboard |
