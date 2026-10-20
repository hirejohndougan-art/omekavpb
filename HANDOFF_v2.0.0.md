# Handoff: Visual Page Builder 2.0.0 + Companion Theme 2.0.0 — Phase 1 (Theme Builder foundations)

Source: `claude/vpb-2-build-plan.md` (Phase 1) and `claude/theme-builder-decisions.md`.
Phase 0 results: `claude/vpb-2-phase0-results.md` (CSV Import settings, search-results finding, SiteGround questions).

Phase 1 is partly done. What is built and tested is listed first; what is not is listed in "Not built yet".

## Built
- **Template core (pure PHP, no Omeka calls)** in `src/Templates/`:
  - `Screens.php`: the nine screens (Item, Media, Item set, Browse items, Browse item sets, Search results, Page not found, Header, Footer) with their Omeka view and required block types (plan decision 10).
  - `Resolver.php`: picks one template for a screen and record. Precedence: record, resource template, item set, class, all. Ties go to the lowest `order`. Draft templates are ignored.
  - `Required.php`: which required blocks a template is missing.
  - `GlobalStyles.php`: the Global styles fields, their cleaning, defaults and CSS variables. Defaults equal the Companion Theme 2.0 defaults (a test checks this against `theme.ini`).
- **Template storage** (`src/Templates/Store.php`): templates are hidden site pages with slug `vpb-template-…` (same pattern as shared sections and the header/footer). Settings live in layout data under `vpb_template`: screen, scope, value, status, order. Create, duplicate (reuses `PagesController::copyBlocks`), set live/draft (live needs the required blocks), move up/down.
- **Theme Builder screen**: route `admin/visual-page-builder-theme` → `/admin/visual-builder/:site-slug/theme`, controller `Controller/Admin/ThemeController.php`, view `view/visual-page-builder/admin/theme/index.phtml`. Linked from the site hub ("Theme Builder" button). It has:
  - Templates grouped by screen, with Live/Draft state, missing required blocks, Make live, Duplicate, and move up/down.
  - An "Add a template" form: screen, where it shows (every one, or a record, resource template, item set or class by id), name.
  - A Global styles form: primary, secondary, text, background and link colors, heading and text fonts, text size, corners. Saved as the Companion Theme's settings for the site (`theme_settings_visual-page-builder-companion`).
- **Hidden pages**: `SiteRegions::TEMPLATE_PREFIX = 'vpb-template-'`; `isHiddenSlug()` includes it, so templates stay out of the site's menus and the hub's page list.
- **Companion Theme 2.0.0** (`theme/…/config/theme.ini`, `view/layout/layout.phtml`, `asset/css/style.css`):
  - New settings: secondary color, text color, link color (blank = primary), text size (small/medium/large), corners (square/soft/round). Defaults match 1.1.0's look.
  - The layout emits `--pb-secondary`, `--pb-ink`, `--pb-link`, `--pb-text-size`, `--pb-radius`. The stylesheet uses them. `--pb-muted` was renamed `--pb-secondary` everywhere.
- **Version**: module 2.0.0 (`config/module.ini`), theme 2.0.0. No schema change; `upgrade()` needs nothing new. Upgrade tested: `needs_upgrade` → `active`.

## Tests (all passing)
- `tests/php/run.php` (new; 49 checks): screens, required blocks, resolver precedence, global styles, and theme.ini defaults matching GlobalStyles. Run with `npm run test:php`; `npm test` runs it too.
- `npm test` (existing Node suite): all files passed.
- `tests/browser/theme_builder.py` (new): a real browser run of the Theme Builder: page loads, phone width has no sideways scroll, add, the "cannot go live" rule, duplicate, and Global styles save. It leaves its templates on the test install (there is no delete button yet).
- Lint: `npm run lint` (ESLint) clean; `npm run lint:php` (php-cs-fixer 3.64.0, Omeka rules) clean on all 89 files. The fixer is fetched into `tools/` per `tools/README.md`.
- On the test install (Omeka 4.2.1): the Companion Theme renders the saved Global styles (primary, link following primary, large text, round corners); the public page returns 200; the hub's page list hides templates.

## Not built yet (Phase 1 gaps, as the plan lists them)
- **Not wired to public screens.** `Resolver` and `Store::live()` exist but no Omeka view calls them yet. Phase 2 adds the item views (`vpbTemplate`). Until then, live templates have no effect on the site.
- **Header and footer are not converted into templates.** They are still the region pages (`vpb-site-header`, `vpb-site-footer`). The plan's "they become templates in the hub" step is not done.
- **Required blocks in the editor.** The editor does not show "Required" in place of Delete, and does not offer Hide. The list and the check exist (`Screens`, `Required`, `Store::setStatus`).
- **Required block names are placeholders.** Blocks are matched by their layout name (`title`, `main-image`, `results`, …). The real item and list blocks come in Phase 2 and Phase 4; they must register layouts with these names, or `Screens` must map them.
- **Add template dialog** is a form on the page, not a dialog. There is no site picker inside the dialog (the hub's site switch does that job), and no "start from a copy" option when adding.
- **No delete** for templates (plan does not list it for Phase 1; add with a confirmation step).
- **Global styles live preview** is not built. Saving shows the values; there is no preview beside the form.
- **Grouped cards** on the hub are a plain list per screen, not the card layout in the mockup.
- **Templates are not checked against required blocks on publish** beyond the Make-live button. Check page (Phase 2) must also warn about templates.

## Notes for the next session
- Field template blocks for Phase 2 are registered through `block_layouts` with `SchemaBlockFactory` like the other Special Blocks. Their layout names should match the keys in `Screens::TITLE`, `MAIN_IMAGE`, and so on.
- The Theme Builder's `Store::all()` reads every page of the site (fine for a few dozen templates; revisit if a site gets hundreds).
- `ThemeController::manager()` gets Omeka's API manager from the service locator. The `api()` plugin is a wrapper and cannot be passed to `Store`.
- The admin layout's jQuery is loaded from a CDN, which is unreachable in the sandbox. The browser test's console shows "jQuery is not defined" on every admin page, including the existing hub. This is the sandbox, not the module.
