# Handoff: Visual Page Builder 1.13.0 (theme unchanged, 1.0.4) — palette groups

John asked whether the Special Blocks could be grouped by similarity or by
what works together. Proposed nine groups; he asked about Jump links' table
of contents (it IS the table of contents; its partner is the Link name
setting); then "go ahead and make these changes".

- `SpecialBlocks::GROUPS` (ordered; every layout exactly once, tested):
  From your collection, Telling history, Maps, Recordings and documents,
  Visiting and events, Getting involved (incl. Social links, which pages
  can use), Finding your way, Reuse, Header and footer (region-only blocks:
  Logo, Site title, Site menu, Copyright; in header/footer editing the
  palette shows its own flat "Header & footer" group, no subgroups).
- `SpecialBlocks::WORKS_WITH`: short notes, shown as "Works well with:" in
  the ⓘ info. `groupOf()`; schema gains `paletteSubgroup` and `worksWith`;
  editor config gains `subgroups` (order); layouts rows get `subgroup` and
  `worksWith`.
- Palette (`palette.js` `subgroups()`): foldable headings with counts,
  folded state in localStorage `vpb.palFolded`, all open while searching
  (search also matches group names).
- Jump links' label is now "Table of contents (jump links)" (layout name
  `specialJumpLinks` unchanged, so saved pages are unaffected).
- Test `module/palette-groups.test.js` (9). Full suite passes.
