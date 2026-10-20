# Handoff: Visual Page Builder 1.0.3 + Companion Theme 1.0.2 (colors)

John: more default colors, then a custom color picker, for headers, footers,
and pages. (One editor serves all three, so one change covers them.)

- `VPB.SWATCHES` (core.js): 18 colors + none: White, Warm paper, Pale brass,
  Pale rose, Pale green, Pale blue, Pale lavender, Pale gray, Sand, Sage, Sky,
  Deep green, Navy, Brick, Plum, Brown, Charcoal (+ none).
- `bgField` (fields.js): native `<input type="color">` picker beside the code
  box, kept in step; live while dragging, one undo step on release.
- Dark backgrounds (`VPB.isDark`, WCAG luminance < 0.18; same in PHP as
  `PageLayout::isDark`): editor draws white text; saving adds class
  `vpb-on-dark` to sections/blocks (stripped again on load so it never shows
  in the CSS class field); PageLayout adds it to dark columns; public.css
  makes text white and Special Blocks' muted/border colors light; the
  Companion Theme makes menu links white and keeps drop-down boxes readable.
- New test `module/colors.test.js` (10). Full suite passes.
