# Mobile-Friendly Implementation Context

## Goal

Make PromptManager fully mobile-usable (320px–1024px) through CSS-first approach with minimal JS.

## Scope

- Navigation: hamburger at 992px, bottom nav at <768px
- GridViews: card layout on mobile, scrollable tables on tablet
- Forms: stacked fields, reduced Quill toolbar, touch-friendly inputs
- Modals: fullscreen on mobile
- Prompt generation: sticky nav buttons, constrained heights
- Homepage: responsive logo and CTA

## Key References

| File | Purpose |
|------|---------|
| `yii/views/layouts/main.php` | Main layout, navbar, bottom nav insertion point |
| `yii/web/css/claude-chat.css` | Reference for mobile patterns (767.98px breakpoint) |
| `yii/web/css/site.css` | Main site styles, extend here |
| `npm/src/js/editor-init.js` | Quill editor initialization |
| `yii/views/*/index.php` | GridView pages to add card layout |
| `yii/views/prompt-instance/_form.php` | Prompt generation form |

## Breakpoints (Bootstrap 5 consistent)

| Name | Width | Target |
|------|-------|--------|
| xs | <576px | Phone portrait |
| sm | 576–767px | Phone landscape |
| md | 768–991px | Tablet |
| lg | 992–1199px | Small laptop |
| xl | ≥1200px | Desktop |

Primary mobile breakpoint: **767.98px** (consistent with claude-chat.css)

## Constraints

- No new JS libraries (use Bootstrap 5, Quill, Select2)
- CSS changes are additive — don't break desktop
- No backend changes
- Server-side conditional for bottom nav on Claude chat page only
