# UI Modernization Plan for PromptManager

This document outlines the strategy for modernizing the user interface of the PromptManager application. The goal is to achieve a modern, polished look and feel while leveraging the existing Yii2 architecture, avoiding a costly full-stack rewrite.

## Strategic Approach: Progressive Modernization

We will maintain the server-side rendering (PHP/Yii2) foundation but enhance it with modern CSS libraries and lightweight JavaScript frameworks. This allows us to keep existing business logic, RBAC, and controller routing intact.

### Phase 1: The "Visual Lift" (High Impact, Low Effort)

**Goal:** Remove the "legacy" aesthetic (gradients, bevels, tight spacing) in favor of a flat, clean, content-focused design.

1.  **Theme Replacement**
    *   **Current State:** Uses `css/spacelab.min.css` (Bootswatch Spacelab), which has a dated, gradient-heavy appearance.
    *   **Action:** Replace with a modern Bootstrap 5 theme.
        *   *Candidates:* Bootswatch "Zephyr" (clean/airy), "Litera" (clean/corporate), or "Pulse" (minimalist).
        *   *Implementation:* Update `yii/assets/AppAsset.php` and replace the CSS file in `yii/web/css/`.

2.  **Typography Overhaul**
    *   **Action:** Introduce a modern, variable-weight font family.
    *   **Recommendation:** `Inter`, `Roboto`, or `Open Sans`.
    *   **Implementation:** Add Google Fonts import to layout or host locally. Update CSS variables to apply globally.

3.  **Modern Layout Principles**
    *   **Spacing:** Increase padding and whitespace. Move away from dense data grids where possible.
    *   **Depth:** Use CSS shadows (`shadow-sm`, `shadow-lg`) instead of borders to define cards and sections.
    *   **Radius:** standardizing border-radius (e.g., `rounded-3`).

### Phase 2: Enhanced Interactivity (The "App" Feel)

**Goal:** Reduce page reloads and provide instant feedback, mimicking a Single Page Application (SPA) experience without the SPA complexity.

1.  **Adopt Alpine.js**
    *   **Why:** It offers React-like declarative behavior directly in the HTML markup. Perfect for toggling UI elements, modals, dropdowns, and simple client-side logic.
    *   **Implementation:** Add Alpine.js via CDN or NPM to `AppAsset.php`. Refactor complex jQuery snippets in views to Alpine components.

2.  **Evaluate HTMX**
    *   **Why:** Allows "clicking a button" to replace a chunk of HTML on the page with a server response, rather than reloading the whole page.
    *   **Use Cases:** Search results, form submissions (in modals), pagination, and tab switching.

### Phase 3: Component Refactoring

1.  **Standardize Cards:** Convert arbitrary `<div>` containers into standardized Bootstrap Cards with consistent headers and footers.
2.  **Navigation:** Modernize the Navbar (currently dark/heavy) to perhaps a lighter, cleaner implementation or a sidebar layout if screen real estate permits.

## Implementation Steps

1.  Create a new git branch `feature/ui-modernization`.
2.  Download/Compile new Bootstrap 5 theme.
3.  Update `AppAsset.php`.
4.  Review and fix minor layout breakages (navbar alignment, button colors).
5.  Iteratively apply modern typography and spacing.
