# Handoff: Visual Page Builder v0.9.0 + Page Builder theme 0.1.0

John asked whether the module could install a base theme so there are no
template issues, plus a menu builder and menu templates. I explained a module
shouldn't install a theme, proposed three parts, and asked; he chose all three.

## 1. Template fix (module)
`BuilderController::saveAction()` removes the page's `template_name`, so a
theme page template (e.g. the default theme's "Homepage", which loops blocks
itself) can't ignore builder columns. The editor shows a notice when a page
has one.

## 2. Page Builder theme (`theme/page-builder/`, separate zip)
- `layout.phtml`: header (logo/title, menu, search), content, footer (text,
  visit info from the module's site settings, social links). Settings become
  CSS variables. System font stacks only; no outside requests.
- `common/vpb-menu.phtml`: draws the site navigation in the chosen style
  (site setting `vpb_menu_style`, else the theme's own setting). Depth: bar 1,
  dropdown/centered 2, mega/sidebar 3. Sub-menus open with buttons
  (`aria-expanded`/`aria-controls`) plus hover on wide screens; Escape and
  outside clicks close them; phones get a "Menu" button.
- `style.css`: one section per style, small-screen menu, Special Blocks take
  `--pb-accent`, light styling for Omeka's own item/browse pages.

## 3. Menu builder (module)
- `MenuController` + route `admin/visual-page-builder-menu` +
  "Menu builder" under Modules in the admin sidebar + a **Menu** button in the
  page editor. Permission: site update.
- Saves `o:navigation` (partial site update) after cleaning: types page / url /
  browse / browseItemSets, existing pages only, safe URLs, ≤4 levels, ≤300
  links; style to the site setting. Returns what was stored.
- `asset/menu-builder/`: tree with up/down/indent/outdent/remove (buttons,
  not drag), inline editing, add page/link/browse/collections, style choice
  with a sketch preview, unsaved-changes guard, Ctrl+S.

## Tests
`npm test`: 18 suites, 339 checks, all pass. New: `module/menu-builder` (20),
`theme/theme` (23). One bug caught and fixed: after saving, the status said
"Unsaved changes" when the server had dropped an unsafe link.
ESLint (now including the theme script) and php-cs-fixer clean.

## Not verified
Real browser (menus, hover and keyboard behavior, phone layout), DHS's
content in the theme, Omeka 4.1. The mockup doesn't show the menu builder.
