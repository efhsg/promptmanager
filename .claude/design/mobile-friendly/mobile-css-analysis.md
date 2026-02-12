# Mobile CSS Analysis

## Breakpoints in Use

### Primary Breakpoints

| Breakpoint | Value | Files Using | Consistency |
|------------|-------|-------------|-------------|
| **md (mobile)** | `max-width: 767.98px` | mobile.css, claude-chat.css | Primary mobile breakpoint |
| **lg (tablet)** | `max-width: 991.98px` | site.css, mobile.css | Navbar collapse, project selector |
| **sm (xs)** | `max-width: 575.98px` | mobile.css | Extra-small specific styles |

### Secondary Breakpoints

| Breakpoint | Value | Usage |
|------------|-------|-------|
| `min-width: 768px` and `max-width: 991.98px` | Tablet range | Scrollable tables |
| `min-width: 576px` | Small and up | Modal sizing, logo sizing |
| `orientation: landscape` and `max-height: 500px` | Landscape phones | Compact bottom nav |

## CSS File Analysis

### site.css - Media Queries

```css
/* Line 68-75: Logout button mobile */
@media (max-width: 767px) {
    .nav li > form > button.logout { ... }
}

/* Line 535-554: Quick search responsive */
@media (max-width: 991.98px) {
    .navbar .quick-search-container { ... }
    .navbar #quick-search-input { ... }
    .navbar #quick-search-results { ... }
}

/* Line 686-692: Project selector tablet */
@media (max-width: 991.98px) {
    .project-context-wrapper { centered horizontally }
}
```

### mobile.css - Complete Structure

| Section | Lines | Status | Description |
|---------|-------|--------|-------------|
| Bottom Navigation | 9-84 | Done | Mobile bottom nav bar |
| Navbar Adjustments | 88-131 | Done | Collapse, project selector |
| Search Overlay | 133-182 | Done | Fullscreen search |
| GridView Cards | 184-297 | Done | Card layout for tables |
| Form Responsive | 299-374 | Done | Form stacking, buttons |
| Quill Editor | 376-410 | Done | Toolbar simplification |
| Select2 | 412-431 | Done | Dropdown max-height |
| Prompt Generation | 433-468 | Done | Sticky nav, containers |
| Modals | 470-576 | Done | Fullscreen modals |
| Copy Widget | 578-596 | Done | Touch targets |
| Homepage | 598-631 | Done | Logo, CTA buttons |
| Edge Cases | 633-706 | Done | Ellipsis, scrolling, orientation |

### claude-chat.css - Mobile Section

```css
/* Lines 1455-1566: Mobile optimizations */
@media (max-width: 767.98px) {
    /* Page header compact */
    /* Toolbar groups hidden */
    /* Editor height */
    /* Sticky offset */
    /* Focus mode safe areas */
    /* Action buttons wrap */
    /* Combined bar stacking */
    /* Container padding */
    /* History items compact */
    /* Message actions touch targets */
    /* Stream preview height */
}
```

## Component Mobile Status

### Navigation & Layout

| Component | Status | Notes |
|-----------|--------|-------|
| Top navbar | Done | Fixed, collapsible at lg |
| Bottom nav | Done | Shows at < 768px, 4 items |
| Project selector | Done | Centered on mobile |
| Breadcrumbs | Done | Hidden on mobile |
| Quick search | Done | Hidden input, uses bottom nav trigger |
| Search overlay | Done | Fullscreen modal experience |

### Content Display

| Component | Status | Notes |
|-----------|--------|-------|
| GridView tables | Done | Hidden on mobile, cards shown |
| MobileCardView | Done | Card layout with touch actions |
| Pagination | Done | Simplified, hides page numbers |
| Modals | Done | Fullscreen < 576px and < 768px |
| Quill editor | Done | Reduced toolbar, max-height |
| Code blocks | Done | Horizontal scroll |

### Forms

| Component | Status | Notes |
|-----------|--------|-------|
| Form columns | Done | Stack to 100% width |
| Buttons | Done | Full width, min-height 44/48px |
| Form actions | Done | Column layout with gap |
| Labels | Done | No truncation |
| Select2 | Done | Max-height 50vh |

### Specialized Pages

| Component | Status | Notes |
|-----------|--------|-------|
| Claude chat | Done | Extensive mobile styles |
| Prompt generation | Done | Sticky nav, container height |
| Path preview | Done | Scrollable content |
| Advanced search | Done | Fullscreen modal |

### Touch Targets

| Component | Min Size | Status |
|-----------|----------|--------|
| Bottom nav links | 44x44px | Done |
| Action buttons | 44x44px | Done |
| Close buttons | 44x44px | Done |
| Form buttons | 48px height | Done |
| GridView actions | 44x44px | Done |

## Identified Inconsistencies

### Breakpoint Values

1. **`767px` vs `767.98px`**
   - `site.css:68` uses `767px` (old)
   - `mobile.css` uses `767.98px` (correct Bootstrap 5)
   - **Recommendation**: Update to `767.98px` for consistency

### Missing Mobile Styles

1. **Index views without MobileCardView**
   - Some views may not implement MobileCardView
   - Tables remain visible without card alternative
   - **Status**: Check each index view

2. **Action column buttons in tables**
   - Some may lack touch-friendly sizing
   - **Status**: Covered by generic rule in mobile.css

### Z-Index Layers

| Element | Z-Index | Notes |
|---------|---------|-------|
| Navbar | 1030 (Bootstrap) | Fixed top |
| Project selector | 1031 | Above navbar |
| Bottom nav | 1030 | Same as navbar |
| Search overlay | 1060 | Above everything |
| Modals | 1050 (Bootstrap) | Standard |

## Recommendations

### Immediate

1. Update `site.css:68` breakpoint from `767px` to `767.98px`
2. Verify all index views use MobileCardView

### Future Improvements

1. Consider CSS custom properties for breakpoints
2. Add `prefers-reduced-motion` where missing
3. Test with iOS safe-area-inset on notched devices
4. Consider dark mode media query support

## Testing Checklist

- [ ] iPhone SE (320px width)
- [ ] iPhone 12/13/14 (390px width)
- [ ] iPad Mini (768px width)
- [ ] iPad (1024px width)
- [ ] Landscape orientation
- [ ] iOS Safari (safe areas)
- [ ] Android Chrome
- [ ] Soft keyboard behavior
