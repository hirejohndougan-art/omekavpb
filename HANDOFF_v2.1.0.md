# Handoff: Visual Page Builder 2.1.0 + Companion Theme 2.1.0 — Phase 2 (item template and item field blocks)

Source: `claude/vpb-2-build-plan.md` (Phase 2) and `claude/theme-builder-decisions.md`.
Previous release: `HANDOFF_v2.0.0.md` (Phase 1).

Phase 2 is partly done. What is built and tested is listed first; what is not is listed in "Not built yet".

## Built
- **Item field blocks** (`src/Site/BlockLayout/Item*.php`, base `AbstractItemField.php`): Title, Main image, Description, Date, Details (a list of fields), One field, Subjects (tags), Item sets, Cite, Download, Previous/next. They are listed under "Item fields" in the block palette and only appear while editing a Theme Builder template (placement `item`).
  - Outside a template (or on a normal page) a field draws nothing. In the editor preview it shows a sample label.
  - Title and Main image are "required" for the Item screen (`Screens`): the editor shows "Required" in place of Delete on those blocks, and the Delete key is refused for them. Templates can't go live without them (Phase 1 rule, unchanged).
  - **Hide**: a required block has a Hide/Show button. Hidden blocks stay in the template (so the required rule still passes) and are not drawn on the public page. The flag is `vpb_hidden` in the block's layout data; `PageLayout::renderBlock` skips such blocks; the editor dims them.
- **Placeholder** (`src/Templates/Placeholder.php`): an item with no usable file shows a placeholder labelled with its kind ("Photograph", "Document"…): the template label, else the class label, else "Item" (plan decision 12). `mediaKind()` turns a media type into image, audio, pdf, other or none.
- **Current record** (`src/Templates/CurrentRecord.php`): the item being drawn is pushed while a template renders, so field blocks read it, and popped afterwards.
- **Public wiring**:
  - Module view helper `vpbTemplate($site, $item)` (`src/View/Helper/VpbTemplate.php`): builds the item's record (id, resource template, class, item sets), asks `Resolver` for the live Item template, and returns its HTML, or null.
  - Companion Theme `view/omeka/site/item/show.phtml`: draws the template when there is one. Otherwise it draws Omeka's own item page exactly as before (title, "Item" heading, page blocks). `view.show.before/after` still fire.
- **Theme Builder**: each template row has an **Edit** link, which opens the template in the page editor.
- **Block templates**: `view/common/block-layout/special-blocks/item-*.phtml` (11 files). CSS for them is appended to `asset/special-blocks/css/special-blocks.css`.
- **Fix found during testing**: a helper named `texts()` clashed with the parent class's `texts()`, which made the editor page return 500. Renamed to `itemTexts()`.
- **Version**: module 2.1.0, theme 2.1.0. No schema change; `upgrade()` needs nothing new.

## Tests
- `tests/php/run.php`: 58 checks pass (Screens, Resolver, Placeholder, CurrentRecord, GlobalStyles).
- `tests/theme/item-template-class.test.js` (new, 5 checks): an "all items" template and a class template (Still Image); an item of the class gets the class template, others get the all-items one.
- `tests/theme/item-template.test.js` (new, 10 checks, incl. hide and show): creates an Item template through the Theme Builder, checks that Title and Main image are required in the editor, fills it with item blocks, makes it live, and checks that the public item page draws the title and the placeholder (the test item has no image). Back in draft, the item page falls back to Omeka's own page.
- `npm run lint` clean. `npm run lint:php` not re-run in this session (fixer phar is in `tools/`).
- `npm test`: exit 0; 39 JS test files pass, PHP 58/58.

## Test environment note
- On the first full run after a long session, `menu-look.test.js` ("the editor sees the draft") failed: the admin session on the test install had a stale validator id, so the public request was treated as logged out. Restarting the Omeka test server (`test-env/start-omeka.sh`) and re-running cleared it. The menu code is unchanged since 2.0.0.

## Not built yet (Phase 2 gaps)
- **Browser (Playwright) test** for the item template editor (drag, placeholder, Required label, Hide). Only the node tests run.
- **Check page** warnings for templates missing required blocks on the page list. The Theme Builder already shows "Missing:" per template.
- **Deleting templates**: still missing (Phase 1 gap). Note that the API delete of a site page returns 500 on this install (Omeka core's `deleteFulltextPreSitePage` fails for these pages), so the test cleans up with SQL where needed.
- Phase 1 gaps still open: header and footer not converted into templates; Global styles live preview; add form is not a dialog; grouped cards on the hub.

## Install and upgrade
- Upgrading from 2.0.0: no schema change. Omeka shows the module as "needs upgrade"; running the upgrade is enough. Tested on the test install by setting the module version row to 2.1.0 (no migration needed).
- The companion theme must be updated too, so the Item page override is picked up.
