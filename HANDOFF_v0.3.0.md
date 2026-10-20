# Handoff: Visual Page Builder v0.3.0 + Society Blocks v0.1.0

Supplements CONTINUE_HERE.md. John asked for custom blocks (starting with
random items) that can be set to a specific site, several sites, or all sites,
fully customizable, with clean, documented code.

## Decision: a separate module
Society Blocks is its own module, not part of the page builder. Reason: the
page builder promises pages keep working if it's removed; blocks inside it
would vanish with it. Separate, they also work in Omeka's standard editor
without the page builder. (John didn't object; merging later is possible.)

## Society Blocks 0.1.0: 14 blocks
Item blocks with **Show from: This site / Chosen sites / All sites**, plus
item set and "has files" filters: Random items, Recently added items, Item
spotlight (daily/random/fixed), On this day, Collection cards, Collection
counts. Others: Search box (one site: Omeka results pages are per-site),
Hero banner, Then and now (slider; item or asset per side), Quote, Accordion
(`<details>`), Buttons, Visit info, Upcoming events (typed into the block).

Architecture (details in its README):
- One `schema()` per block drives the standard editor form (`FormBuilder`),
  data cleaning on save (`Normalizer` via `onHydrate`), the page builder's
  sidebar (`visualPageBuilderSchema()`), and site search text.
- `MultiSiteFilter` adds an API search key, `society_blocks_site_ids[]`, for
  items and item sets (Omeka's `site_id` takes one site). Subquery, so no
  duplicates.
- `ItemSource` builds queries and picks the site each link goes to.
- Templates are theme-overridable; CSS uses `--sb-*` custom properties.

## Visual Page Builder 0.3.0
- Any block layout with a `visualPageBuilderSchema()` method gets a sidebar
  form and card built from its schema (`asset/js/editor/blocks/schema.js`),
  documented in the README, "Blocks from other modules".
- Config now includes all sites and item sets (for checklists), and references
  for schema `item`/`asset` fields (titles, thumbnails).
- The picker supports several pickers per block (`slot`) and single picks.
- Controller gets the BlockLayoutManager injected (was the view helper).

## Tests (all against live Omeka 4.2.1, two sites)
`npm test`: 10 suites, 177 checks, all pass. New: society render (39),
standard editor form submit (8), page builder sidebar (17), multi-site API
filter (6), PHP unit checks (28). Page builder suites unchanged and passing;
the golden-master comparison shows only the palette changed (new block types).
Also checked by hand: the page builder's own suites pass with Society Blocks
deactivated, a page full of Society Blocks still returns 200 with it off, the
HTML purifier cleans accordion text when Omeka's setting is on, and the
0.2.1 → 0.3.0 upgrade works. ESLint and php-cs-fixer (Omeka's rules): clean.

A bug was caught by the PHP unit checks and fixed: an unset dropdown inside a
list row saved as blank instead of its default (`Schema::defaultValue()`).

## Not verified
- Real browser: the then-and-now slider, the standard editor's show/hide
  script, layout of the new blocks in a real theme, the sidebar forms.
- Omeka 4.1, DHS's server and theme. A real oEmbed fetch (unchanged).
- The Design-canvas mockup does not show Society Blocks yet.

## Open
- License for both modules (Omeka modules are usually GPL-3.0).
- Visit info is per block (no site-wide "enter once" setting yet).
- Upcoming events are typed in; no calendar feed.
