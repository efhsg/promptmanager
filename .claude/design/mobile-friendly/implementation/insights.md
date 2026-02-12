# Mobile-Friendly Implementation Insights

## Decisions

| Decision | Rationale |
|----------|-----------|
| Create separate `mobile.css` | Keep mobile styles isolated, easier to maintain |
| Use 767.98px as primary breakpoint | Consistent with existing claude-chat.css |
| Server-side bottom nav exclusion | Cleaner than CSS body class hack |
| Create MobileCardView widget | Reusable across all index views, follows DRY |
| Change navbar collapse from xl to lg | 992px is better for tablets, follows Bootstrap convention |

## Findings

1. **Navbar breakpoint**: Changed from `navbar-expand-xl` to `navbar-expand-lg` for better tablet support.
2. **Project selector positioning**: Uses JavaScript to position on desktop (relative to nav-notes link), falls back to CSS centered on mobile.
3. **Quick search**: Already had responsive CSS at 1199px, updated to 991.98px to match new navbar breakpoint.
4. **Index views**: All use consistent GridView pattern - MobileCardView widget provides unified mobile card layout.
5. **Soft keyboard detection**: Uses viewport resize + focus events for cross-browser support.

## Implementation Summary

### Files Created

| File | Purpose |
|------|---------|
| `yii/web/css/mobile.css` | All mobile-specific styles (450+ lines) |
| `yii/widgets/MobileCardView.php` | Reusable widget for mobile card layouts |
| `yii/views/layouts/_bottom-nav.php` | Bottom navigation bar partial |
| `yii/views/layouts/_mobile-search-overlay.php` | Fullscreen search overlay |

### Files Modified

| File | Changes |
|------|---------|
| `yii/assets/AppAsset.php` | Added mobile.css to CSS bundle |
| `yii/views/layouts/main.php` | Updated navbar breakpoint, added bottom nav & search overlay, added mobile JS |
| `yii/web/css/site.css` | Updated project-context-wrapper and quick-search breakpoints from 1199px to 991.98px |
| `yii/views/project/index.php` | Added MobileCardView |
| `yii/views/context/index.php` | Added MobileCardView |
| `yii/views/field/index.php` | Added MobileCardView |
| `yii/views/prompt-template/index.php` | Added MobileCardView |
| `yii/views/prompt-instance/index.php` | Added MobileCardView |
| `yii/views/note/index.php` | Added MobileCardView |
| `yii/views/prompt-instance/_form.php` | Added prompt-generation-nav class for sticky buttons |

## Pitfalls

1. **`:has()` CSS selector**: Used for hiding Quill toolbar groups, but not supported in all browsers. Added fallback using direct element selectors.

## Open Questions

1. **Manual testing required**: Need to test on actual 375px device/emulator to verify all interactions.

## Test Status

- [x] PHP syntax validation: All files pass
- [x] Codeception unit tests: 933 tests, 2293 assertions, 21 skipped - ALL PASSED
- [ ] Manual 375px viewport test: Requires browser
