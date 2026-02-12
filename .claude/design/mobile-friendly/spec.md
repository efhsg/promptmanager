# Mobile-Friendly PromptManager — Functionele Specificatie

## 1. Doel

De volledige PromptManager-applicatie mobiel bruikbaar maken, zodat alle functionaliteit (projects, contexts, fields, templates, prompt generation, notes en Claude CLI chat) comfortabel werkt op smartphones (320px–480px) en tablets (481px–1024px).

De Claude CLI chat-interface dient als referentie-implementatie: die heeft al mobiele optimalisaties (toolbar hiding, touch targets, safe-area support). Dezelfde kwaliteit moet gelden voor de rest van de applicatie.

---

## 2. Scope

### In scope

| Gebied | Huidige staat | Gewenst |
|--------|--------------|---------|
| **Navigatie (navbar)** | Collapse op xl (1200px), project-selector fixed positioned, quick search in navbar | Hamburger menu op md (768px), bottom navigation bar voor primaire acties, project-selector geïntegreerd in navigatie |
| **Grid/tabel views** | Bootstrap `table` in GridView, geen column hiding | Card-layout op mobiel, responsive tabel op tablet |
| **Formulieren** | Standaard ActiveForm, Quill toolbar volledig zichtbaar | Gestapelde velden, verkleinde Quill toolbar, touch-friendly inputs |
| **Prompt generatie** | 3-stappen accordion met Select2 en Quill editors | Zelfde flow maar geoptimaliseerd voor touch, grotere knoppen, mobiel-vriendelijke Select2 |
| **Modals** | `modal-lg` (80vw), niet geoptimaliseerd | Fullscreen modals op mobiel (<768px) |
| **Claude CLI chat** | Heeft al mobiele CSS (767.98px breakpoint) | Verfijnen en consistent maken met rest van app |
| **Kopieer-functionaliteit** | CopyToClipboardWidget met dropdown | Touch-friendly met grotere tap targets |
| **Homepage** | Jumbotron met 200px logo | Responsive logo, compactere spacing |

### Buiten scope

- Native app / PWA-installatie (kan als vervolg)
- Offline functionaliteit
- Push notifications
- Nieuwe features of business logic wijzigingen
- Backend/API wijzigingen (puur frontend)

---

## 3. Gebruikers & Toegang

Geen wijzigingen in het toegangsmodel. Alle bestaande RBAC-regels (ProjectOwnerRule, ContextOwnerRule, etc.) blijven ongewijzigd. Dit is een pure presentatielaag-aanpassing.

---

## 4. Breakpoints

Consistent met Bootstrap 5 en bestaande Claude chat CSS:

| Naam | Breedte | Doelgroep |
|------|---------|-----------|
| **xs** | < 576px | Smartphones portrait |
| **sm** | 576px – 767px | Smartphones landscape |
| **md** | 768px – 991px | Tablets |
| **lg** | 992px – 1199px | Kleine laptops |
| **xl** | ≥ 1200px | Desktop (huidige design) |

Primaire mobiele breakpoint: **767.98px** (consistent met claude-chat.css).

---

## 5. Requirements per Component

### 5.1 Navigatie

**REQ-NAV-01**: De navbar collapse breakpoint verlaagt van xl (1200px) naar lg (992px).

**REQ-NAV-02**: Op schermen < 768px verschijnt een bottom navigation bar met de 4 meest gebruikte acties:
- Prompts (link naar prompt generation)
- Notes (link naar notes index)
- Claude (link naar Claude chat)
- Search (opent zoekoverlay)

**REQ-NAV-03**: De project-selector wordt geïntegreerd in het hamburger menu op mobiel (niet meer fixed positioned). Op desktop blijft het huidige gedrag. **Implementatienoot:** De `.project-context-wrapper` staat momenteel buiten het navbar collapse `<div>`. CSS `position: static` + `width: 100%` integreert hem visueel in het hamburger menu mits de wrapper in de collapse flow zit. Verifieer bij implementatie of HTML-verplaatsing nodig is.

**REQ-NAV-04**: Quick search wordt op mobiel vervangen door een zoekicoon in de bottom nav dat een fullscreen zoekoverlay opent. De quick-search dropdown (350px breed) wordt op mobiel fullscreen.

**REQ-NAV-05**: Breadcrumbs blijven verborgen op < md (bestaand gedrag behouden).

**Acceptatiecriteria:**
- [ ] Navbar collapsed op < 992px met werkende hamburger toggle
- [ ] Bottom nav zichtbaar op < 768px met 4 iconen + labels
- [ ] Project-selector in hamburger menu op < 768px
- [ ] Quick search fullscreen overlay op < 768px
- [ ] Alle links navigeren correct
- [ ] Bottom nav verdwijnt op ≥ 768px

---

### 5.2 Grid/Tabel Views (Index pagina's)

Betreft: Project, Context, Field, PromptTemplate, PromptInstance, Note index pagina's.

**REQ-GRID-01**: Op schermen < 768px worden GridView-tabellen vervangen door een card-layout. Elke rij wordt een kaart met:
- Primaire info (naam) als kaart-titel
- Secondaire info (project, type, datum) als meta
- Actieknoppen onderaan de kaart

**REQ-GRID-02**: Op tablets (768px–991px) worden tabellen horizontaal scrollbaar met `table-responsive` wrapper.

**REQ-GRID-03**: Actieknoppen (update, delete, Claude) hebben minimaal 44x44px touch target op mobiel (< 768px). Op desktop blijven de huidige afmetingen.

**REQ-GRID-04**: Paginatie wordt vereenvoudigd op mobiel: alleen vorige/volgende knoppen + huidige pagina indicator.

**REQ-GRID-05**: De "Showing X to Y of Z" summary verplaatst boven de paginatie op mobiel (niet overlappend). **Implementatienoot:** De huidige card-footer gebruikt `position-absolute` voor de summary, wat op mobiel overlapt met de centered paginatie. Op mobiel moet de card-footer naar een flexbox column layout omgezet worden.

**Acceptatiecriteria:**
- [ ] Card-layout zichtbaar op < 768px voor alle 6 index views
- [ ] Tabel scrollbaar op tablets (768–991px)
- [ ] Alle actieknoppen ≥ 44x44px
- [ ] Paginatie navigeerbaar op 320px breed scherm
- [ ] Clickable rows werken op touch devices (hele card is klikbaar)

---

### 5.3 Formulieren

Betreft: Project, Context, Field, PromptTemplate, PromptInstance, Note formulieren.

**REQ-FORM-01**: Formuliervelden stapelen verticaal op < 768px. `col-md-6` paren worden full-width.

**REQ-FORM-02**: Submit/cancel knoppen worden full-width op < 576px, met minimaal 48px hoogte.

**REQ-FORM-03**: Quill editor toolbar wordt gereduceerd op < 768px:
- Verberg: indent, header-select, alignment, clean groepen (consistent met claude-chat.css patroon)
- Behoud: bold, italic, list, link, code, custom buttons (clearEditor, smartPaste, loadMd)

**REQ-FORM-04**: Quill editor containers krijgen `max-height: 50vh` op mobiel (was 800px).

**REQ-FORM-05**: Select2 dropdowns krijgen `max-height: 50vh` op mobiel met verbeterde scroll.

**REQ-FORM-06**: Inklapbare secties (collapsibles) in formulieren zijn standaard dichtgeklapt op mobiel, met duidelijke visuele indicator (chevron) dat ze uitklapbaar zijn.

**REQ-FORM-07**: Formulier labels worden nooit afgekapt; ze wrappen naar een volgende regel.

**Acceptatiecriteria:**
- [ ] Alle formulieren bruikbaar op 320px breed scherm
- [ ] Geen horizontale scroll in formulieren
- [ ] Quill toolbar past binnen scherm zonder overflow
- [ ] Submit-knop bereikbaar zonder scrollen voorbij formulier
- [ ] Select2 dropdown sluit niet af buiten viewport
- [ ] Touch targets ≥ 44px voor alle interactieve elementen

---

### 5.4 Prompt Generatie (Create/Edit)

Dit is de meest complexe flow en verdient speciale aandacht.

**REQ-GEN-01**: De 3-stappen accordion werkt op mobiel met dezelfde flow:
1. Selectie (contexts + template)
2. Velden invullen
3. Gegenereerde prompt bekijken

**REQ-GEN-02**: ~~Context multi-select (Select2) wordt fullscreen op mobiel met checkboxes in plaats van een dropdown.~~ **Geschrapt** — contradiceert CSS-first beslissing. In plaats daarvan: Select2 behoudt dropdown-gedrag maar krijgt betere `max-height` constraints op mobiel (zie §5.3 REQ-FORM-05).

**REQ-GEN-03**: ~~Template select wordt fullscreen op mobiel met een lijst-weergave.~~ **Geschrapt** — zelfde reden als REQ-GEN-02.

**REQ-GEN-04**: Navigatieknoppen (Previous/Next/Save) zijn sticky aan de onderkant van het scherm op mobiel (boven de bottom nav).

**REQ-GEN-05**: De gegenereerde prompt in stap 3 is scrollbaar in een container met `max-height: 60vh`.

**REQ-GEN-06**: De "Edit" toggle in stap 3 werkt correct op mobiel: schakelt tussen read-only viewer en Quill editor.

**REQ-GEN-07**: De Claude-knop (terminal icoon) in stap 3 is duidelijk zichtbaar en ≥ 44x44px.

**Acceptatiecriteria:**
- [ ] Volledige prompt generatie flow werkt op 320px scherm
- [ ] Elk stap is bereikbaar via accordion tap
- [ ] Context/template selectie bruikbaar op touch
- [ ] Velden invullen werkt voor alle field types (text, select, multi-select, code, select-invert, file, directory, string, number)
- [ ] Gegenereerde prompt leesbaar en kopieerbaar op mobiel
- [ ] Navigatie tussen stappen intuïtief

---

### 5.5 Modals

**REQ-MODAL-01**: Alle modals worden fullscreen op < 576px (`modal-fullscreen-sm-down`).

**REQ-MODAL-02**: Advanced search modal wordt fullscreen op < 768px (`modal-fullscreen-md-down`).

**REQ-MODAL-03**: Path preview modal (donker thema) is scrollbaar op mobiel met `max-height: 80vh` voor de code preview.

**REQ-MODAL-04**: Import modals (Markdown, YouTube) zijn volledig bruikbaar op mobiel.

**Acceptatiecriteria:**
- [ ] Geen modal content buiten viewport op mobiel
- [ ] Modal close-knop bereikbaar (≥ 44x44px)
- [ ] Scrollbare content binnen modal werkt correct (geen body scroll leaking)
- [ ] Keyboard (soft keyboard) duwt modal niet buiten beeld

---

### 5.6 Claude CLI Chat

De chat-interface heeft al mobiele CSS. Deze requirements zorgen voor consistentie met de rest van de app.

**REQ-CHAT-01**: Bottom navigation bar van de hoofdapp (REQ-NAV-02) wordt niet gerenderd op de Claude chat pagina (chat heeft eigen sticky input). Server-side via controller check — geen body class hack nodig.

**REQ-CHAT-02**: De bestaande mobiele optimalisaties (toolbar hiding, touch targets, safe-area) blijven behouden.

**REQ-CHAT-03**: ~~Chat history accordion is touch-friendly met swipe-to-collapse ondersteuning.~~ **Geschrapt** — swipe-detectie vereist touch event handling/library, buiten scope van CSS-first aanpak. Chat accordion blijft bruikbaar via standaard Bootstrap tap-to-toggle.

**Acceptatiecriteria:**
- [ ] Geen dubbele bottom bars (app nav + chat input)
- [ ] Chat volledig functioneel op 320px scherm
- [ ] Bestaande mobiele tests blijven slagen

---

### 5.7 Copy-to-Clipboard Widget

**REQ-COPY-01**: Copy-knoppen hebben minimaal 44x44px touch target op mobiel (< 768px). Op desktop blijven de huidige afmetingen.

**REQ-COPY-02**: Format-selector dropdown opent boven de knop als er niet genoeg ruimte onder is (dropdown-menu-end + dropup).

**REQ-COPY-03**: ~~Toast notificatie ("Copied!") is zichtbaar boven de bottom nav.~~ **Geschrapt** — CopyToClipboardWidget toont feedback via CSS class toggle op de button zelf (tijdelijk `btn-primary`), niet via een toast. Geen positionering conflict met bottom nav.

**Acceptatiecriteria:**
- [ ] Kopiëren werkt op mobiel (clipboard API)
- [ ] Format selectie bruikbaar op touch
- [ ] Feedback (button kleurwijziging) zichtbaar

---

### 5.8 Homepage

**REQ-HOME-01**: Logo schaalt proportioneel (`max-width: 150px` op < 576px, `200px` op desktop).

**REQ-HOME-02**: CTA-knop is full-width op < 576px.

**REQ-HOME-03**: Features pagina (indien aanwezig) gebruikt gestapelde kaarten op mobiel.

**Acceptatiecriteria:**
- [ ] Homepage leesbaar en aantrekkelijk op 320px
- [ ] CTA duidelijk zichtbaar en klikbaar

---

## 6. Edge Cases

### 6.1 Soft Keyboard

**EDGE-KB-01**: Bij focus op een input veld schuift de viewport correct mee. De bottom navigation bar verdwijnt wanneer de soft keyboard actief is.

**EDGE-KB-02**: Quill editor toolbars die `position: fixed` gebruiken conflicteren niet met het soft keyboard.

### 6.2 Lange Content

**EDGE-CONTENT-01**: Projectnamen langer dan het scherm worden afgekapt met ellipsis in card-titles en navigatie.

**EDGE-CONTENT-02**: Quill Delta content met brede code blocks is horizontaal scrollbaar binnen de container (niet de hele pagina).

### 6.3 Select2 op Mobiel

**EDGE-SELECT-01**: Select2 met veel opties (>20) moet performant scrollen op mobiel.

**EDGE-SELECT-02**: Select2 multi-select tags wrappen correct en duwen de container niet buiten het scherm.

### 6.4 Orientation Change

**EDGE-ORIENT-01**: Bij rotatie van portrait naar landscape herberekent de layout correct. Geen vastzittende fixed positioned elementen.

### 6.5 iOS Safari Specifiek

**EDGE-IOS-01**: `env(safe-area-inset-bottom)` toegepast op bottom navigation en sticky knoppen (bestaand patroon uit claude-chat.css).

**EDGE-IOS-02**: `-webkit-overflow-scrolling: touch` voor scrollbare containers (smooth scrolling).

### 6.6 Bestaande Data

**EDGE-DATA-01**: Geen enkele backend wijziging. Quill Delta JSON, placeholders, en alle bestaande data blijft exact werken.

---

## 7. Niet-functionele Eisen

**NFR-01**: Geen extra JavaScript bibliotheken. Gebruik bestaande Bootstrap 5, Quill en Select2.

**NFR-02**: CSS wijzigingen zijn additief — bestaande desktop styling wordt niet gebroken.

**NFR-03**: Performance: geen extra HTTP requests voor mobiele layout. Alles via CSS media queries en beperkte JS viewport-detectie.

**NFR-04**: Alle wijzigingen zijn puur CSS + minimale JS (viewport detection, bottom nav toggle). Geen backend wijzigingen.

**NFR-05**: De bestaande responsive patronen in claude-chat.css dienen als referentie. Gebruik dezelfde breakpoints en technieken.

---

## 8. Teststrategie

### Handmatige Tests

| Test | Schermformaat | Verwacht |
|------|--------------|----------|
| Navigatie + hamburger menu | 375px (iPhone) | Menu opent/sluit, alle links werken |
| Bottom nav | 375px | 4 iconen zichtbaar, navigatie werkt |
| Project index card-layout | 375px | Cards in plaats van tabel |
| Project create formulier | 375px | Alle velden invulbaar, submit werkt |
| Prompt generatie 3-stappen | 375px | Volledige flow van selectie tot save |
| Context select (Select2) | 375px | Selectie bruikbaar, geen overflow |
| Quill editor | 375px | Toolbar past, tekst invoerbaar |
| Claude chat | 375px | Bestaande functionaliteit behouden |
| Tablet tabel scroll | 768px | Tabel horizontaal scrollbaar |
| Landscape orientation | 667px × 375px | Layout herberekent correct |

### Geen Automated Tests Nodig

Dit is een pure CSS/UI wijziging. De bestaande Codeception tests valideren backend-logica die niet wijzigt. Visuele regressie is handmatig te valideren.

---

## 9. Aannames

1. De applicatie wordt primair op een smartphone browser (Chrome/Safari) gebruikt, niet in een webview of embedded context.
2. Minimum ondersteund scherm: 320px breed (iPhone SE).
3. Touch targets volgen Apple's HIG (44x44 points) als minimum.
4. De bestaande Bootswatch Spacelab theme en Bootstrap 5 versie blijven ongewijzigd.
5. Er is geen server-side rendering verschil nodig — de server levert dezelfde HTML, CSS doet het responsive werk. Uitzondering: bottom nav wordt conditioneel niet gerenderd op de Claude chat pagina, en pager configuratie krijgt extra CSS classes voor mobiele targeting.
