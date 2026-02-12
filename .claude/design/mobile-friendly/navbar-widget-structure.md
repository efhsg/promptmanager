# NavBar Widget Structure Analysis

## Yii2 Bootstrap 5 NavBar Widget

### Widget Usage in `main.php`

```php
NavBar::begin([
    'brandLabel' => Html::img(...) . '<span>...</span>',
    'brandUrl' => Yii::$app->homeUrl,
    'options' => ['class' => 'navbar-expand-lg navbar-dark bg-primary fixed-top'],
]);

// Nav widgets and custom HTML here

NavBar::end();
```

### Generated HTML Structure

```html
<!-- NavBar::begin() generates: -->
<nav id="w0" class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
    <div class="container">
        <!-- Brand link -->
        <a class="navbar-brand" href="/">
            <img src="..." alt="..." height="40">
            <span class="d-none d-xl-inline">&nbsp;&nbsp;&nbsp;PromptManager</span>
        </a>

        <!-- Hamburger toggle button (mobile) -->
        <button type="button" class="navbar-toggler"
                data-bs-toggle="collapse"
                data-bs-target="#w0-collapse"
                aria-controls="w0-collapse"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Collapsible content container -->
        <div id="w0-collapse" class="collapse navbar-collapse">
```

```html
            <!-- Nav::widget() output (menu items) -->
            <ul id="w1" class="navbar-nav me-auto ms-1">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" ...>Prompts</a>
                    <ul class="dropdown-menu">...</ul>
                </li>
                <li class="nav-item" id="nav-notes">
                    <a class="nav-link" href="/note/index">Notes</a>
                </li>
            </ul>

            <!-- Quick search container (custom HTML) -->
            <div class="quick-search-container ms-auto me-3">
                <button class="btn btn-sm advanced-search-btn">...</button>
                <input id="quick-search-input" ...>
                <div id="quick-search-results"></div>
            </div>

            <!-- Nav::widget() output (user menu) -->
            <ul id="w2" class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle">
                        <i class="bi bi-person-circle"></i> Username
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">...</ul>
                </li>
            </ul>
```

```html
        </div><!-- /.navbar-collapse -->
    </div><!-- /.container -->
</nav>
<!-- NavBar::end() generates closing tags -->
```

## Key Structural Points

### What's INSIDE the Collapse Container

Everything between `NavBar::begin()` and `NavBar::end()` goes inside `.navbar-collapse`:

- Navigation menus (`Nav::widget()`)
- Quick search container
- User menu

### What's OUTSIDE the Collapse Container

Elements placed **after** `NavBar::end()` are outside the navbar structure:

```php
NavBar::end();  // Closes .navbar-collapse, .container, and <nav>

// This is OUTSIDE the navbar:
echo '<div class="project-context-wrapper">...</div>';
```

### Current Project Selector Positioning

The project selector is **outside** the navbar, positioned with `position: fixed`:

```html
</nav>  <!-- NavBar ends here -->

<div class="project-context-wrapper">
    <select class="form-select" id="project-context-selector">...</select>
</div>
```

CSS positioning (from `site.css`):

```css
.project-context-wrapper {
    position: fixed;
    top: 28px;                    /* Vertically centered in 56px navbar */
    left: 200px;                  /* Default left position */
    transform: translateY(-50%);
    z-index: 1031;                /* Above navbar (1030) */
}
```

JavaScript repositioning (from `main.php`):

```javascript
function positionProjectDropdown() {
    const isDesktop = window.innerWidth >= 992;
    if (isDesktop) {
        // Position relative to Notes link
        const notesLink = document.querySelector('#nav-notes a');
        wrapper.style.left = (linkRect.right + 20) + 'px';
        wrapper.style.top = (linkCenterY - (wrapperHeight / 2)) + 'px';
    } else {
        // Reset to CSS defaults (centered via transform)
        wrapper.style.left = '';
        wrapper.style.top = '';
    }
}
```

## Diagram: NavBar Structure

```
┌──────────────────────────────────────────────────────────────────────┐
│ <nav class="navbar fixed-top">                                       │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │ <div class="container">                                        │  │
│  │  ┌──────────────┐  ┌────────────┐  ┌─────────────────────────┐│  │
│  │  │ .navbar-brand│  │ .navbar-   │  │ #w0-collapse            ││  │
│  │  │              │  │ toggler    │  │ .navbar-collapse        ││  │
│  │  │ [Logo][Name] │  │ [Hamburger]│  │                         ││  │
│  │  │              │  │            │  │  ┌───────────────────┐  ││  │
│  │  └──────────────┘  └────────────┘  │  │ Nav (menu)        │  ││  │
│  │                                    │  │ [Prompts][Notes]  │  ││  │
│  │                                    │  └───────────────────┘  ││  │
│  │                                    │  ┌───────────────────┐  ││  │
│  │                                    │  │ Quick Search      │  ││  │
│  │                                    │  │ [🔍][input]       │  ││  │
│  │                                    │  └───────────────────┘  ││  │
│  │                                    │  ┌───────────────────┐  ││  │
│  │                                    │  │ Nav (user)        │  ││  │
│  │                                    │  │ [👤 Username ▼]   │  ││  │
│  │                                    │  └───────────────────┘  ││  │
│  │                                    └─────────────────────────┘│  │
│  └────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ (Outside navbar)
                                    ▼
┌──────────────────────────────────────────────────────────────────────┐
│ <div class="project-context-wrapper">   ← position: fixed           │
│   [Project Dropdown ▼]                                               │
└──────────────────────────────────────────────────────────────────────┘
```

## Mobile Behavior (< 992px)

When collapsed:

```
┌─────────────────────────────────────────┐
│ [Logo] ---- [Project ▼] ---- [☰]        │  ← Navbar header
└─────────────────────────────────────────┘
                    │
                    │ (When hamburger clicked)
                    ▼
┌─────────────────────────────────────────┐
│ Prompts ▼                               │
│ Notes                                   │
│ ────────────────────────────────────────│
│ [🔍] [Search input............]         │
│ ────────────────────────────────────────│
│ 👤 Username ▼                           │
└─────────────────────────────────────────┘
```

## Adding Elements INSIDE Navbar but OUTSIDE Collapse

To place an element inside the navbar visually but outside the collapse area, you need to modify the widget or use custom HTML:

### Option 1: Modify NavBar Widget Container

```php
NavBar::begin([
    // ... options
    'innerContainerOptions' => ['class' => 'container'],
    'collapseOptions' => ['id' => 'navbarCollapse'],
]);

// This goes INSIDE collapse
echo Nav::widget([...]);

NavBar::end();

// This would still be OUTSIDE the entire navbar
```

### Option 2: Custom HTML Between Elements

The widget doesn't support placing content between brand and toggler or between toggler and collapse. You would need to:

1. Use `NavBar::$renderInnerContainer = false` and manage structure manually
2. Or use JavaScript to reposition elements after page load

### Current Implementation Choice

The project uses **JavaScript repositioning** to place the project selector:

1. Element is placed outside navbar in HTML
2. CSS `position: fixed` positions it relative to viewport
3. JavaScript calculates position based on Notes link
4. On resize, position is recalculated

**Advantages:**
- No modification to Yii2 widgets needed
- Flexible positioning
- Responsive behavior controllable

**Disadvantages:**
- Position flash on page load before JS runs
- Requires recalculation on resize
- Extra z-index management

## Related CSS Classes

| Class | Source | Purpose |
|-------|--------|---------|
| `.navbar-expand-lg` | Bootstrap | Collapse at < 992px |
| `.navbar-dark` | Bootstrap | Light text/icons |
| `.bg-primary` | Bootstrap | Primary background |
| `.fixed-top` | Bootstrap | Fixed positioning |
| `.navbar-collapse` | Bootstrap | Collapsible container |
| `.navbar-toggler` | Bootstrap | Hamburger button |
| `.navbar-nav` | Bootstrap | Nav item container |
| `.nav-link` | Bootstrap | Nav item links |
| `.dropdown` | Bootstrap | Dropdown container |
| `.dropdown-menu-end` | Bootstrap | Right-aligned dropdown |

## Z-Index Stack

| Element | Z-Index | Notes |
|---------|---------|-------|
| `.fixed-top` navbar | 1030 | Bootstrap default |
| `.project-context-wrapper` | 1031 | Above navbar |
| `.modal-backdrop` | 1040 | Modal backdrop |
| `.modal` | 1050 | Modal content |
| `.mobile-search-overlay` | 1060 | Above modals |

## Navbar Height

- **Desktop**: 56px (Bootstrap 5 default with padding)
- **Mobile**: Same, but content stacks in collapse
- **Project selector positioning**: Vertically centered at `top: 28px` with `translateY(-50%)`
