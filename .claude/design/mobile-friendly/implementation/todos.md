# Mobile-Friendly Implementation Todos

## Phase 1: Foundation

- [x] 1.1 Create `mobile.css` with base responsive utilities and import in layout
- [x] 1.2 Update navbar collapse breakpoint from xl to lg (992px)
- [x] 1.3 Create bottom navigation bar component (hidden ≥768px)
- [x] 1.4 Conditionally hide bottom nav on Claude chat page (server-side)

## Phase 2: Navigation & Search

- [x] 2.1 Integrate project-selector into hamburger menu on mobile
- [x] 2.2 Create fullscreen search overlay for mobile
- [x] 2.3 Style bottom nav icons and labels

## Phase 3: GridView Card Layout

- [x] 3.1 Create card layout CSS for mobile GridViews
- [x] 3.2 Update Project index view with responsive card/table toggle
- [x] 3.3 Update Context index view
- [x] 3.4 Update Field index view
- [x] 3.5 Update PromptTemplate index view
- [x] 3.6 Update PromptInstance index view
- [x] 3.7 Update Note index view
- [x] 3.8 Style pagination for mobile (prev/next only)
- [x] 3.9 Fix card-footer summary positioning on mobile

## Phase 4: Forms

- [x] 4.1 Add form stacking CSS for mobile
- [x] 4.2 Style full-width submit buttons on mobile
- [x] 4.3 Reduce Quill toolbar on mobile (hide indent, header, alignment, clean)
- [x] 4.4 Constrain Quill editor max-height on mobile (50vh)
- [x] 4.5 Constrain Select2 dropdown max-height on mobile (50vh)
- [x] 4.6 Style collapsible sections with chevron indicator

## Phase 5: Prompt Generation

- [x] 5.1 Add sticky navigation buttons for accordion steps
- [x] 5.2 Constrain generated prompt container height (60vh)
- [x] 5.3 Ensure Claude button is 44x44px minimum

## Phase 6: Modals

- [x] 6.1 Add fullscreen modal classes for mobile
- [x] 6.2 Style path preview modal for mobile
- [x] 6.3 Ensure modal close buttons are touch-friendly

## Phase 7: Copy Widget & Homepage

- [x] 7.1 Style copy buttons for 44x44px touch targets on mobile
- [x] 7.2 Add dropup behavior for format selector
- [x] 7.3 Scale homepage logo and CTA for mobile

## Phase 8: Edge Cases & Polish

- [x] 8.1 Hide bottom nav when soft keyboard is active
- [x] 8.2 Add text ellipsis for long content in cards/nav
- [x] 8.3 Add horizontal scroll for wide code blocks
- [x] 8.4 Apply iOS safe-area-inset-bottom to sticky elements
- [x] 8.5 Add -webkit-overflow-scrolling: touch for scrollable containers

## Phase 9: Final Validation

- [x] 9.1 Run linter (0 issues - PHP syntax check passed)
- [x] 9.2 Run unit tests (933 tests, 2293 assertions - ALL PASSED)
- [ ] 9.3 Manual test on 375px viewport
- [x] 9.4 Document final changes in insights.md
