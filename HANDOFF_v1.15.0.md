# Handoff: Visual Page Builder 1.15.0 (theme unchanged, 1.0.4) — menu styles as pictures

John: "I would like them to actually be mocked up on that page where you click
them to choose them and can see what you are choosing."

- `menu-builder.js`: `renderPreview()` split into `drawMenu(style, links, mini)`
  (the existing sketch of each style) and the chosen-style preview. Each style
  card (`label.mb-style`) now holds a small picture (`.mb-mini-frame`,
  aria-hidden; the label text names it) above its radio, name, and text.
- Pictures use the site's own links when the menu has 3+ links and one with
  sub-pages; otherwise sample links (Home, Collections > Photographs, Maps,
  Letters, Oral histories, Visit, About). `#mbStylesHint` says which.
- In a picture, the first link with sub-pages is drawn open (`li.pv-open`):
  a drop-down, the large panel, the side menu's indented sub-pages, or the
  Menu button's panel. Sub-pages trimmed to 2 (4 for Large panels).
- Pictures redraw when the menu changes (debounced while typing a label).
- Larger "Your menu in this style: <name>" preview stays, below the cards.
- Cards: grid (min 230 px), selected card outlined; checked at 1917 and 390 px.
- Test: `module/menu-builder.test.js` +2.
