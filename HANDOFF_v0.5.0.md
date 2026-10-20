# Handoff: Visual Page Builder v0.5.0 (Special Blocks merged in)

## What John asked
"Wait, are the society blocks a different module? I wanted them to be part of
the visual page builder module." Earlier I had recommended a separate module
and asked; he didn't answer, I built it separate anyway. He also asked for the
blocks to be listed first in the sidebar and called **Special Blocks**.

## What changed
- The 14 blocks now live in VisualPageBuilder. The SocietyBlocks module is
  retired (not in the kit; its last code is in the v0.4.0 kit).
- Names: layouts `society*` → `special*`; CSS `society-blocks*` →
  `special-blocks*`; templates `view/common/block-layout/special-blocks/`;
  assets `asset/special-blocks/`; search key `special_blocks_site_ids`; view
  helper `specialBlocksNumber`; classes under `VisualPageBuilder\` (Schema,
  Site\BlockLayout, Stdlib\ItemSource, Api\MultiSiteFilter,
  Service\BlockLayout\SchemaBlockFactory, SpecialBlocks).
- Palette: Sections, Text, then "Special Blocks" (schema `paletteFeatured`), then Omeka blocks (0.5.1), mockup too.
- `Module::install()`/`upgrade()` (from < 0.5.0) rename existing `society*`
  page blocks to `special*`, keeping their settings. It `require_once`s
  `src/SpecialBlocks.php` because Omeka doesn't autoload a module's classes
  during install/upgrade (first attempt failed with "class not found").
- Docs: the Special Blocks documentation is merged into the module README,
  with an "Upgrading from the separate Society Blocks module" section.

## Upgrade path (checked in the test install)
Society Blocks 0.2.x installed, page builder 0.4.x → uninstall Society Blocks
(pages keep their blocks' data) → delete its folder → upgrade the page builder
to 0.5.0. Result: all 183 old blocks across the test pages renamed, the old all-blocks page
renders every block with the new classes.

## Tests
`npm test`: 11 suites, 225 checks, all pass (Special Blocks suites renamed to
`tests/special/`). ESLint and php-cs-fixer clean. The rename migration itself
was checked by hand (above), not by an automated test.

## Not verified
Unchanged: real browser, DHS's theme and server, Omeka 4.1.
