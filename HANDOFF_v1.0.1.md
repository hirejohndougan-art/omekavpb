# Handoff: Visual Page Builder 1.0.1 (theme unchanged, 1.0.0)

John: the sidebar should always be expanded when opening a page, header, or
footer; headers and footers need their own elements, not the page ones.

- **Sidebar:** no longer remembers "collapsed" in localStorage; `app.js` opens
  it every time. Collapsing still works for the session. (Sidebar width is
  still remembered.)
- **Separate palettes:** `AbstractSchemaBlock::PLACEMENT` = 'page' (default),
  'region' (header/footer only), or 'both', sent as `placement` in the editor
  config (other modules' blocks default to 'page').
  - region: Logo, Site title, Site menu, Copyright line.
  - both: Social links, Breadcrumbs, Search box, Buttons, Announcement bar,
    Open now, Visit info, Newsletter sign-up, Partners and sponsors.
  - In a header/footer the palette shows Sections, Text, a "Header & footer"
    group (region + both), and Line break; everything else is hidden.
  - On pages, region-only blocks are hidden.
- Tests: regions +4 (palette contents, open sidebar), v011 +2. Full suite passes.
