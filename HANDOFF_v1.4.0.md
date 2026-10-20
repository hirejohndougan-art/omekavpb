# Handoff: Visual Page Builder 1.4.0 (theme unchanged, 1.0.3)

John: when editing a page, the header (and footer) should show; clicking
between header and page should ask whether to save first.

- Server: `BuilderController::regionHtml()` renders the site's built header
  and footer with the `vpbSiteRegion` helper (works in the admin; errors are
  caught and the band left out) into `cfg.regionHtml`; `fromPage()` reads
  `?from=<page id>` (passed through `RegionController`) into `cfg.fromPage`.
- `regions-view.js`: `#regionTop` / `#regionBottom` around `#secs`. On a page:
  faded, sanitized, non-focusable header/footer bands ("Site header · click
  to edit"). In a header: page band (names the page it came from, else back
  to the Visual Builder) + footer below; in a footer: header + page band
  above. Hidden in Preview. Clicking: no unsaved changes → go; otherwise a
  dialog: Save and continue (uses `VPB.save.save()`, which now returns a
  promise of true/false) / Continue without saving / Cancel (Escape). The
  top bar's Header/Footer buttons use the same question and add `?from=`.
  `VPB.regionsView.go` is overridable for tests.
- Styles in editor.css (`rv-*`, `lv-*`). The bands use the module's own
  styles, not the theme's, so they're an approximation.
- Test `theme/region-view.test.js` (12); harness `openEditor(id, {query})`.
  Full suite passes.
