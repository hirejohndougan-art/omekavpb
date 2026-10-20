# Handoff: Visual Page Builder v0.4.0 + Society Blocks v0.2.0

Supplements CONTINUE_HERE.md. John asked to build everything the mockup
showed into the modules ("let's build it all in").

## Page builder 0.4.0
| Feature | Where |
|---|---|
| ⓘ on every block tile: plain description, "good for", Add this block; description also under a block's name in Settings | `palette.js`, `blocks/about.js` (Omeka's blocks), schema `description`/`goodFor` (other modules), `registry.about()` |
| Find a block (search box; keeps focus while typing) | `palette.js` |
| Palette groups: Sections, Text, Omeka blocks, one per other module (schema `paletteGroup`) | `palette.js`, controller |
| Layers tab: outline, click to select (opens settings), arrows reorder | `layers.js` |
| Resizable sidebar: drag the edge (280 px to 60% of window), window-level listeners so releasing anywhere ends it, arrow keys/Home/End, double-click reset, remembered; ARIA separator | `side-resize.js`, `edit.phtml` (#sideResize), `--side-w` CSS variable |
| Realistic previews: media/item-with-metadata/asset (images, titles, captions), list of sites, page dates, oEmbed thumbnail, IIIF frame, table of contents (shape only) | each `blocks/*.js` `render()` |
| Live server previews: Browse preview's real results; other modules' blocks render their own HTML | `previews.js` (cache per settings, 400 ms debounce, inert, sanitized with form controls kept), controller `previewAction` (CSRF, POST, permission check), `ResourceLookup::browsePreview()` |

The palette code moved from `sidebar.js` to `palette.js`.

## Society Blocks 0.2.0
- `DESCRIPTION` / `GOOD_FOR` per block: shown in the standard editor form and
  in the page builder.
- `visualPageBuilderPreview()`: renders the real template from unsaved
  settings. To make that possible, `viewVars()` now gets `($view, $data,
  $site, $blockKey)` instead of a saved block; templates use `$blockKey`.
- Schema also sends `paletteGroup`, `stylesheets`, `preview`.

## Tests
`npm test`: 11 suites, 225 checks, all pass. New suite
`module/sidebar-tools.test.js` (38): search, help panels, Layers, every resize
behavior including a release outside the window, live previews (real items,
inert, no reshuffle on redraw, updates on change), preview endpoint guards.
Permissions gained preview checks (researcher 403, logged-out redirect).
Updated: block-settings now checks the realistic previews instead of cards.
ESLint and php-cs-fixer clean. Upgrades 0.3.0 → 0.4.0 and 0.1.0 → 0.2.0 work.

No golden-master comparison this time: the canvas changed on purpose.

## Not verified
Real browser look and feel (all of the above runs in jsdom), real pointer
dragging feel, how previews look next to DHS's actual theme, Omeka 4.1.
