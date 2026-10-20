# Handoff: Visual Page Builder 1.16.0 + Companion Theme 1.1.0 — menu colors, vertical menus, device preview

John: "can we have a way to edit the color schemes of them? when editing the
headers, the menus should only be editable from these options. also we need
vertical menus too. and when a user chooses a menu there also needs to be an
option at the top of these page to switch between desktop tablet and mobile
views so the user can see what they look like."

## Colors
- `src/MenuSettings.php`: five colors (bar, link, hover, dropBg, dropLink),
  `SCHEMES` (theme, light, cream, dark/Charcoal, navy, bayou, brick, sky),
  `cleanColors()` (hex only), `cssVars()` → `--pb-nav-bg/link/hover/drop-bg/
  drop-link`, `classes()` → `pb-nav-colored`, `pb-nav-has-bg`, `pb-nav-has-drop`.
- Saved with the menu as site setting `vpb_menu_colors` (MenuController save).
- Menu page: "Colors" panel (left column, under the menu): scheme radio cards
  with swatches, five color pickers with "Use theme's", contrast warning
  (links/bar 4.5, hover/bar 3, drop links/drop bg 4.5).
- Theme `style.css`: every menu color reads the vars with the old value as
  fallback (links, hover/current, drop-down/panel backgrounds and links, band,
  pills active = hover bg + bar-colored text, tabs underline, Menu button,
  phone menu). Built-header dark-section overrides skip colored menus.

## Vertical menus
- `MenuController::STYLES` + `sidebar-right`, `vertical-list`,
  `vertical-flyout`, `vertical-boxed` (19 styles); `VERTICAL` lists the five
  side styles (with `sidebar`). Menu page groups cards "Across the top" /
  "Down the side (vertical)".
- Theme: body class `pb-menu-side` (+ `pb-menu-side-right`) when the theme's
  own header is used; the grid keeps Omeka's user bar across the top
  (rows fixed). Nav rules stack all vertical styles (also in a Site menu
  block): list = everything showing; flyout = boxes to the right; boxed =
  panel with lines; sidebar(-right)/boxed show the current page's sub-pages
  and open others with their buttons. `theme.ini` select gained the 4.

## Device preview
- Top bar: Desktop / Tablet / Phone (`#mbDevices`, same look as the editor).
- With the Companion Theme the preview at the top is the site's home page in
  an iframe at 1280 / 820 / 390 px, scaled to fit. Every change posts a draft
  (`MenuController::draftAction`, CSRF, cleaned like save) into the session
  (`VpbMenu::SESSION`); the frame loads `?vpb_menu_draft=1`. The `vpbMenu`
  view helper (`src/View/Helper/VpbMenu.php`) returns style, colors, vars,
  classes, and a navigation container built from the draft (Omeka's link
  types + translator, `ConstructedNavigationFactory`), only for users who may
  update the site. Draft mode hides Omeka's user bar. Links in the frame
  don't navigate. Save clears the draft.
- Other themes: the drawing at the chosen width (`pv-small` = Menu button +
  list under it).

## Header menus follow the Menu page
- `SiteMenu` block: `style` is now `site` | `list` only; older saved styles
  map to `site` (`data()`); renders through `vpbMenu` (colors, draft links).
- Editor: `blocks/site-menu.js` adds "Edit the menu, its style, and colors"
  (link to the Menu page) at the top of the block's settings.
- `special-blocks.css` site-menu fallback wrapped in `:where()` (no weight):
  it used to beat the theme, which hid sub-menus in built headers.

## Tests
New `module/menu-look.test.js` (33). menu-builder counts 19. Full suite,
browser sweep (48 blocks), axe (menu page contrast fixed) pass. Manual
Chromium checks: dropdown/flyout/band/side-right with colors, phone menu.

Also: the draft preview loads the site's home page (or first page) by its
own address, since the site address redirects and would drop the draft flag;
and the Menu page warns (with a link) when a header built in the Visual
Builder has no Site menu block, because then the menu doesn't show at all.
