# Handoff: Visual Page Builder 1.14.2 (theme unchanged, 1.0.4) — menu page scrolls

John (screenshot of Visual Builder > Menu): "are these the menu styles? the
list goes further than i can scroll". Yes, the 15 Companion Theme menu styles.

- Cause: the menu page loads `css/editor.css`, which sets `body{height:100%;
  overflow:hidden}` for the page editor (it scrolls inside its panels).
- `menu-builder.css`: `body.menu-builder` height auto + overflow auto (and
  `html:has(body.menu-builder)` height auto); top bar sticky; the Preview moved
  above the style list (`.mb-preview-wrap`, sticky under the top bar, static
  on phones) so choosing a style shows it at once; styles in a grid
  (`.mb-styles`, two columns where there is room); panels `align-self:start`.
- Checked in Chromium at 1917, 1366, and 390 px: the last style can be reached
  and picked. `module/menu-builder.test.js` passes.
