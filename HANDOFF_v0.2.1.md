# Visual Page Builder — Handoff v0.2.1

Supplements CONTINUE_HERE.md. John asked whether the code followed best
practice for human developers; the honest answer was "partly", and he asked
for the cleanup. **No behavior change is intended or observed.**

## What changed
**Editor JavaScript** — the single ~1,400-line `asset/js/editor.js` is now 27
files under `asset/js/editor/`, plain browser scripts (still no build step)
sharing one namespace, `VPB`:
- `core`, `dom`, `model`, `store`, `fields`, `picker`, `actions`, `canvas`,
  `drag`, `resize`, `sidebar`, `save`, `app`, each with a header comment.
- `blocks/registry.js` + one file per block type. The long if/else chains for
  previews, sidebar forms, names, new-block data, and post-save fixes are gone;
  each block file registers `{ name, newData, preview, card, editor, note, afterSave }`.
- `edit.phtml` loads them from one ordered list. Load-order rule documented in
  `core.js` and enforced by a test.

**PHP**
- Item/asset search and the reference data moved out of the controller into
  `src/Stdlib/ResourceLookup.php` (service `VisualPageBuilder\ResourceLookup`,
  built by a factory with Omeka's API manager).
- Controller now built by `BuilderControllerFactory` (lookup service + thumbnail
  sizes injected); it went from 360 to 267 lines of request handling only.
- Checked with Omeka S's own `.php-cs-fixer.dist.php`: one fix applied (`??=`).

**Tooling / tests**
- `package.json` scripts: `test`, `seed`, `lint`, `lint:php`, `fix:php`,
  `golden:record`, `golden:compare`, `package`.
- The three print-only tests (e2e, rt, edge) were rewritten as asserting
  suites on a shared harness (`tests/lib/harness.js`), plus permissions and
  load-order suites; `npm test` runs all and exits non-zero on failure.
- `tests/tools/seed.js` builds all fixtures through the API (no image library needed).
- `tests/tools/golden.js`: records/compares everything the editor renders.

## How "no behavior change" was checked
1. Before touching code, the new suites were run on v0.2.0: 75 checks, all passed.
2. A golden master of v0.2.0 was recorded: canvas, palette, notice, exported
   data, and the settings panel for every section/column/block on 4 pages
   (43 panels).
3. After the split: all suites pass (79 checks incl. load order), and the golden
   comparison is **identical** to v0.2.0. (One masked item: the asset block's
   "Link to page" list, which grows as tests add pages.)
4. The first split attempt had a real bug (block files read `VPB.fields` before
   it loaded; selecting a Media block crashed). The block-settings suite caught
   it; the new load-order test now catches that class of mistake directly and
   was shown to fail on the broken order.
5. Upgrade 0.2.0 → 0.2.1 through Modules › Upgrade works; ESLint and
   php-cs-fixer report nothing.

## Not verified (unchanged from v0.2.0)
Real-browser look and feel, mouse dragging and resizing, a real oEmbed/IIIF
fetch, Omeka 4.1, DHS's server and theme.

## Upgrade note
Replace the whole `modules/VisualPageBuilder` folder (don't unzip over it):
the old `asset/js/editor.js` is no longer used, and leaving it is harmless but
confusing.
