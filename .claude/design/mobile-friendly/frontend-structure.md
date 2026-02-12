# Frontend Structure Analysis

## Layout Hierarchy

```
yii/views/layouts/
├── _base.php              # Base HTML skeleton (html/head/body)
├── main.php               # Main application layout (extends _base)
├── fatal.php              # Error layout (extends _base)
├── _advanced-search-modal.php   # Partial: search modal
├── _bottom-nav.php        # Partial: mobile bottom navigation
└── _mobile-search-overlay.php   # Partial: mobile search overlay
```

### Layout Nesting

1. **`_base.php`** - Root template
   - Registers `AppAsset`
   - Sets meta tags (viewport, description, charset)
   - Registers Bootstrap Icons CDN
   - Contains `<html>`, `<head>`, `<body>` structure
   - Body uses Bootstrap flex layout (`d-flex flex-column h-100`)

2. **`main.php`** - Primary layout (extends `_base.php` via `beginContent()`)
   - `<header>`: NavBar with logo, navigation, search, user menu
   - `<main>`: Container with breadcrumbs, alerts, content
   - Includes partials: `_advanced-search-modal`, `_bottom-nav`, `_mobile-search-overlay`
   - Contains inline JavaScript for project selector and mobile search

3. **`fatal.php`** - Minimal error layout (extends `_base.php`)
   - Simple container with content only
   - No navbar or navigation

## Asset Bundles

### AppAsset (`yii/assets/AppAsset.php`)

**Load Order:**
1. `yii\web\YiiAsset` (jQuery, yii.js)
2. `yii\bootstrap5\BootstrapAsset` (Bootstrap CSS)
3. `yii\bootstrap5\BootstrapPluginAsset` (Bootstrap JS)
4. Application CSS (in order):
   - `css/spacelab.min.css` - Bootswatch Spacelab theme
   - `css/site.css` - Application-specific styles
   - `css/mobile.css` - Mobile responsive styles
5. Application JS:
   - `js/form.js`
   - `js/quick-search.js`
   - `js/advanced-search.js`

### Additional Asset Bundles

| Bundle | Purpose | Loaded On-demand |
|--------|---------|------------------|
| `QuillAsset` | Quill rich text editor | Editor views |
| `HighlightAsset` | Syntax highlighting | Code preview |
| `PathSelectorFieldAsset` | File path selector | Field forms |

## CSS Files

### 1. `spacelab.min.css`
- **Purpose**: Bootswatch theme (Bootstrap 5 variant)
- **Source**: Third-party, minified
- **Modifies**: Bootstrap default colors, typography

### 2. `site.css` (~700 lines)
- **Purpose**: Application-specific styles
- **Key sections**:
  - Container/footer layout
  - GridView sorting icons
  - Form validation error styling
  - Quill editor customizations (toolbar, sticky toolbar)
  - Path preview modal (dark theme)
  - Select2 styling
  - Quick Search dropdown
  - Advanced Search modal
  - Project context selector (fixed position)
- **Media queries**: Only `@media (max-width: 991.98px)` for project selector

### 3. `mobile.css` (~700 lines)
- **Purpose**: Mobile-responsive styles
- **Primary breakpoint**: `767.98px` (Bootstrap md)
- **Key sections**:
  - Bottom navigation bar
  - Navbar adjustments
  - Fullscreen search overlay
  - GridView card layout
  - Form responsive styles
  - Quill editor mobile optimizations
  - Select2 mobile adjustments
  - Modal fullscreen styles
  - Touch-friendly button sizes

### 4. `claude-chat.css` (~1500 lines)
- **Purpose**: Claude chat page specific styles
- **Scope**: `.claude-chat-page` class prefix
- **Features**: Chat messages, streaming UI, thinking blocks, usage meters

## Widgets Used in Layouts

### Yii2 Bootstrap 5 Widgets (from `yii\bootstrap5\`)

| Widget | Location | Purpose |
|--------|----------|---------|
| `NavBar` | `main.php:15-108` | Top navigation bar |
| `Nav` | `main.php:22-64, 79-106` | Navigation items |
| `Breadcrumbs` | `main.php:136` | Page breadcrumbs |
| `Html` | Various | HTML helper |

### Custom Widgets (from `app\widgets\`)

| Widget | Location | Purpose |
|--------|----------|---------|
| `Alert` | `main.php:139` | Flash message display |
| `MobileCardView` | Index views | Mobile-friendly card grid |
| `QuillViewerWidget` | View pages | Read-only Quill content |
| `CopyToClipboardWidget` | View pages | Copy button |
| `PathSelectorWidget` | Field forms | File path selector |
| `PathPreviewWidget` | Field forms | File content preview |
| `ContentViewerWidget` | View pages | Content display |

## View Rendering Flow

```
Request → Controller → Layout (main.php)
                           ↓
                      beginContent(_base.php)
                           ↓
                      _base.php renders:
                        - AppAsset registration
                        - Meta tags
                        - head()
                        - beginBody()
                        - $content (from main.php)
                        - endBody()
                           ↓
                      main.php renders:
                        - NavBar::begin/end
                        - Nav widgets
                        - Quick search HTML
                        - Project selector
                        - Breadcrumbs
                        - Alert widget
                        - $content (actual view)
                        - Modal partials
                        - Bottom nav partial
                        - Inline JavaScript
```

## Key HTML Structure (main.php)

```html
<body class="d-flex flex-column h-100">
    <header id="header">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
            <!-- NavBar widget output -->
            <!-- Nav widget output (menu items) -->
            <!-- Quick search container -->
            <!-- Nav widget output (user menu) -->
        </nav>
    </header>

    <div class="project-context-wrapper">
        <!-- Project dropdown (fixed position, outside navbar) -->
    </div>

    <main id="main" class="flex-shrink-0" role="main">
        <div class="container mt-5 pt-5">
            <!-- Breadcrumbs (hidden on mobile) -->
            <!-- Alert widget -->
            <!-- View content -->
        </div>
    </main>

    <!-- Advanced search modal -->

    <nav class="mobile-bottom-nav">
        <!-- Bottom navigation (visible on mobile only) -->
    </nav>

    <div class="mobile-search-overlay">
        <!-- Fullscreen search (triggered from bottom nav) -->
    </div>

    <script>
        // Project selector positioning
        // Mobile search functionality
        // Keyboard detection
    </script>
</body>
```

## Responsive Breakpoints Summary

| Breakpoint | Value | Usage |
|------------|-------|-------|
| xs | < 576px | Extra small screens, fullscreen modals |
| sm | 576-767.98px | Small tablets |
| md | 768-991.98px | Tablets, bottom nav appears |
| lg | 992-1199.98px | Desktops, navbar collapse |
| xl | >= 1200px | Large desktops, brand text visible |

## File Organization

```
yii/
├── assets/
│   ├── AppAsset.php          # Main asset bundle
│   ├── QuillAsset.php        # Quill editor
│   ├── HighlightAsset.php    # Syntax highlighting
│   └── PathSelectorFieldAsset.php
├── views/
│   └── layouts/
│       ├── _base.php         # HTML skeleton
│       ├── main.php          # Main layout
│       ├── fatal.php         # Error layout
│       └── _*.php            # Partials
├── web/
│   ├── css/
│   │   ├── spacelab.min.css  # Theme
│   │   ├── site.css          # App styles
│   │   ├── mobile.css        # Mobile styles
│   │   └── claude-chat.css   # Chat page
│   └── js/
│       ├── form.js
│       ├── quick-search.js
│       └── advanced-search.js
└── widgets/
    ├── Alert.php
    ├── MobileCardView.php
    └── ...
```
