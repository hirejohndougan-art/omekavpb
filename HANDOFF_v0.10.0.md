# Handoff: Visual Page Builder v0.10.0 + Companion Theme 0.2.0 (headers and footers)

John: "I don't see how I can build headers and footers." I explained they
were fixed in the theme (settings only), proposed four parts, asked; he chose
all four.

## How it works
- **Storage:** each site's header and footer is a site page with a reserved
  slug, `vpb-site-header` / `vpb-site-footer` (`src/SiteRegions.php`). That
  reuses the whole editor (blocks, undo, templates, permissions).
  - Public, so anonymous visitors can see the blocks; its own address
    answers 404 (`Module::hideRegionPages`, dispatch listener on
    `Omeka\Controller\Site\PageController`).
  - Left out of the editor's page lists (`BuilderController::sitePages`) and
    the menu builder's.
  - Known gap: Omeka's own site search may list "Site header"/"Site footer"
    as pages if their blocks contain matching text. Not handled.
- **Opening:** Modules › Header & footer (`RegionController`, route
  `admin/visual-page-builder-region`) lists sites; `/region/:site/:region`
  makes the page once and redirects to the editor. The editor has Header and
  Footer buttons; `cfg.region` puts it in header/footer mode (notice; the
  Templates tab offers only header or footer templates).
- **Blocks (Special Blocks):** Logo (falls back to the theme's logo setting,
  then the site name), Site title (+ tagline), Site menu (menu builder style,
  a chosen style, or "Plain list" for site maps; same markup as the theme's
  menu so its CSS/JS work), Social links (7 networks; email validated),
  Copyright line ([year], [site]).
- **Templates:** `templates/regions.js`, kinds `header` and `footer` (catalog
  TYPES with `region`).
- **Theme:** `layout.phtml` uses `$this->vpbSiteRegion($site, 'header'|'footer')`
  (module view helper) and falls back to its own header/footer when empty or
  without the module.

## Tests
`npm test`: 19 suites, all pass. New `theme/regions.test.js` (18). Templates
test now covers 40 templates. Theme test scoped its column check to the page
body. ESLint and php-cs-fixer clean.

## Not verified
Real browser; how built headers/footers look in DHS's real content.
The mockup doesn't show header/footer editing.
