# Visual Page Builder for Omeka S

Build site pages visually. Add sections, choose their columns, drag the bar
between columns to resize them, and drag blocks into place. Pages are saved in
Omeka's own Grid layout. Includes forty-seven **Special Blocks** (random items,
hero banner, then and now, events, and more), described below.

Requires **Omeka S 4.1 or later** (tested on 4.2.1).

## Install

1. Download `VisualPageBuilder.zip`. On Windows, right-click it and choose
   **Extract All**. You get one folder, `VisualPageBuilder`, with
   `Module.php` and the rest directly inside it.
2. Upload that `VisualPageBuilder` folder into your Omeka S `modules/` folder,
   so you have `modules/VisualPageBuilder/Module.php`.
3. In Omeka's admin, go to **Modules** and click **Install** next to
   Visual Page Builder (or **Upgrade** if an older version is there).

The zip has no folder of its own inside it. If your unzip tool puts the files
loose instead of in a `VisualPageBuilder` folder, make that folder yourself and
move them in. Back up your database before installing or upgrading any module.

## Use

1. Open a site page in Omeka's admin (Sites › your site › Pages › a page).
2. Click **Open visual editor**, next to Save.
3. Arrange the page, then click **Save** (or press Ctrl+S).

The first time you open a page made in the standard editor, the visual editor
arranges its blocks into sections. Nothing is saved until you click Save.

- Text and headings can be typed directly on the page.
- Select any other core block to edit it in the sidebar: pick items (search by
  title, word, or item number), choose which file to show, write captions, and
  reorder or remove them; pick asset images and link them to pages; set a browse
  preview's search, count, and heading; and set the options of the date, table
  of contents, list of sites, line break, oEmbed, and IIIF blocks.
- Blocks added by other modules are edited in the sidebar too when the module
  describes their settings (the Special Blocks here use the same mechanism; see "Blocks from other
  modules" below). Others appear as labeled cards and are set up in Omeka's
  standard editor. The **Standard editor** button in the top bar takes you there.
- In the sidebar, **Blocks** lists everything you can add. Type in **Find a
  block** to narrow it down. Every block has an **ⓘ** button that explains, in
  plain words, what it does and what it's good for, with an **Add this block**
  button. The same explanation shows under a block's name in its settings.
- **Templates** offers 30 ready-made pages, five each for home pages,
  browse and explore, stories and exhibits, about, contact and visit, and
  events and support. Pick one and choose **Use for this page** (replaces the
  page, after asking) or **Add to the end**. Either is one undo step. Replace
  the [bracketed] text with your own; item blocks (random items, collection
  cards, galleries…) fill in from your collection by themselves, and buttons
  point to "#" until you choose where they go.
- **Layers** shows an outline of the page (sections, columns, blocks). Click a
  row to open its settings; the arrows move sections and blocks.
- Drag the sidebar's right edge to make it wider (280 px up to 60% of the
  window). Double-click the edge to reset it. With the edge focused, the arrow
  keys resize it too. The width is remembered in your browser.
- Blocks are drawn close to how they look on the site: images with captions,
  the real results of a Browse preview, and the real items of Special Blocks. These previews can't be clicked; select the
  block to change it.
- The sidebar toggle (top left, or Ctrl+\\) hides the sidebar for a wider view.
  **Preview** hides all editing marks.

## Editing extras

- **Preview on site**: the unsaved page shown in a frame on the real site
  (theme, header, footer), at desktop/tablet/phone width. The server saves a
  private copy (`vpb-preview-<page id>`), hidden from lists and visitors,
  replaced on each preview and deleted when the page is saved.
- **Typed text**: a formatting bar: style (p, h2–h4, blockquote), 10
  web-safe fonts, size, bold, italic, underline, strikethrough, text and
  highlight color, bulleted/numbered lists, alignment, link/unlink (checked
  like the server's `safeUrl`), clear formatting. Written as inline styles,
  which Omeka's HTML cleaner keeps. "Insert an image" from Assets.
- **Titles**: every Special Block (except Logo, Site title, Copyright line,
  Breadcrumbs, Announcement bar) has Title and "Show the title" first in its
  settings (`AbstractSchemaBlock::fields()`); blocks that had no title get
  one drawn above them.
- **Smaller screens** (section settings): tablets 601–896px one per row /
  two per row / as on desktop; phones one or two per row. Saved in
  `vpb_section.resp`; classes `vpb-tablet-*`, `vpb-phone-*` (public.css).
- **Ready-made sections**: 10 single-section templates in the Templates tab.
- **Header and footer around the page** (`regions-view.js`): the built header
  and footer (rendered on the server, `cfg.regionHtml`) show faded above and
  below the page; clicking goes to edit them (`?from=<page id>` remembers
  the page), asking Save and continue / Continue without saving / Cancel
  when there are unsaved changes. In a header or footer, a page band leads
  back.

## Page tools (v1.12)

- **History**: table `vpb_page_revision` (created on install/upgrade and on
  first use), a copy of the page before each visual-editor save, newest 50
  per page, removed with the page and on uninstall. `history` action lists
  and returns versions; the editor loads one as unsaved changes.
- **Duplicate** (Visual Builder page, POST + CSRF): private copy, blocks and
  attachments copied, slug `…-copy`.
- **Download / import layout**: JSON `{format: "visual-page-builder-layout",
  version: 1, title, page: {o:layout, o:layout_data, o:block}}`, all in the
  browser.
- **Check page** (`page-check.js`): editor-only checks, never blocks saving.
- **Print**: stylesheets are loaded for all media; print rules in public.css
  and the theme.

## Backgrounds

Sections, columns, and blocks each have a **Background** (18 colors or any
color with the picker) and a **Background image** (from Assets): fill the area
or show the whole picture, top/center/bottom, and optional darkening that
turns the text white. Images use Omeka's own background image settings for
sections and blocks, and the module's for columns.

## Third-party code

None. The maps (Map of places, Story map, and the editor's pin tool) use the
module's own small map, `asset/map/vpb-map.js` (written for this module,
GPL-2.0-or-later). Map pictures come from OpenStreetMap's tile servers,
credited on each map (OpenStreetMap data is ODbL).

## Visual Builder (under Modules)

**Modules › Visual Builder** is the one place for everything: choose a site
(skipped if there's only one; a switcher moves between sites) and one page
shows its **Header** and **Footer** (built or not, Edit), its **Menu** (links,
style, Edit), its **Theme**, and its **Pages** (Find a page, Add new page,
and Open visual editor / View / Standard editor for each).

## Header & footer

**Modules › Visual Builder › Edit header / Edit footer** (or the **Header** /
**Footer** buttons in the page editor) opens a site's header or footer in the visual editor. The
Templates tab then offers five header or five footer templates, and the
Blocks tab offers only header and footer elements: Logo, Site title, Site
menu (the menu builder's menu; "Plain list" for footer site maps), Social
links, Copyright line, Search box, Buttons, Breadcrumbs, Announcement bar,
Open now, Visit info, Newsletter sign-up, Partners, text, and spacing. Logo,
Site title, Site menu, and Copyright line appear only there, not on pages.
(Blocks choose this with `PLACEMENT`: page, region, or both.)

The **Companion Theme for Visual Page Builder** shows a built header or
footer on every page; until one is built it uses its own. Other themes draw
their own header and footer.

Under the hood each is a site page with a reserved slug (`vpb-site-header`,
`vpb-site-footer`; see `src/SiteRegions.php`): public so visitors can see its
blocks, but its own address answers "not found", and it's left out of page
lists in the editor and menu builder.

## Menu builder

**Modules › Visual Builder › Edit menu** (or the **Menu** button in the page
editor) opens a
site's menu: add pages, web addresses, "Browse items", and "Collections";
reorder with the arrows; **Indent** makes an item a sub-item of the one above
(up to four levels). Choose one of nineteen menu styles (each card shows a
picture of the menu in that style) and its colors, and see it at the top of the
page on your own site at **Desktop**, **Tablet**, or **Phone** width:

- simple bar, drop-down menus, large panels, side menu, logo left / menu
  right, rounded buttons, tabs, two rows (a colored strip with name and
  search above), menu button only (a panel listing every page), logo in the
  middle (half the menu each side);
- centered logo with the menu underneath: plain, in a color band, newspaper
  masthead (between rules), with dividers, or in small capitals;
- down the side (vertical): side menu on the left or right, a vertical list
  with everything showing, sub-pages flying out to the side, or links in a
  panel.

**Colors** (v1.16.0): eight schemes (Theme colors, Light, Cream and ink,
Charcoal, Navy and gold, Bayou green, Brick, Sky) or your own five colors:
menu background, links, pointed-at link and current page, drop-downs and
panels, links in them. A warning shows when text would be hard to read.
Saved as the site setting `vpb_menu_colors` (`MenuSettings`), reaching the
theme as CSS custom properties `--pb-nav-*` on the menu. The preview is the
site's home page in a frame, showing unsaved changes from a draft kept in the
editor's session (`MenuController::draftAction`, `?vpb_menu_draft=1`, read
by the `vpbMenu` view helper, only for someone who may edit the site). The
**Site menu** block in a header always follows the Menu page (its only other
choice is a plain list, for footers).

The menu is saved to Omeka's own site navigation, so every theme shows it.
The style is drawn by the **Companion Theme for Visual Page Builder** (in the same download); other themes show the menu in their own style.

## The Companion Theme for Visual Page Builder

A theme made to go with this module (see its own README): pages look the way
you build them, Special Blocks take its colors, the menu builder's styles
work, and the footer can show the site's Visit info. Settings: logo, colors,
fonts, page width, search box, footer text, and social links.

## Theme page templates

Some themes offer page templates (such as a "Homepage" template) that lay out
blocks their own way and would ignore your columns. Pages saved in the visual
editor always use the standard template; the editor tells you if a page had
another one.

## Special Blocks

Forty-seven extra blocks for historical societies and archives, listed in the
sidebar under **Special Blocks**, right after Sections and Text. They also work in Omeka's standard page
editor, where each form starts with a plain explanation of the block.

### Blocks that show items

Each of these has a **Show from** setting: **This site**, **Chosen sites** (one
or more), or **All sites**. Most can also be narrowed to item sets and to
items that have images or files.

| Block | What it shows |
|---|---|
| Random items | A shuffled selection, different on each visit |
| Recently added items | The newest items, by date added or last changed |
| Item spotlight | One item, large, with its description: item of the day, random, or one you pick |
| On this day | Items dated to today's day (or month) in past years |
| Collection cards | Item sets as cards with a cover image and item count |
| Collection counts | Live numbers: items, items with files, item sets |

Links always go to an item's page on a site it belongs to: this site if it's
on it, otherwise one of the chosen sites. "All sites" means items that are on
at least one site (an item on no site has no public page).

**On this day** reads each item's date (Dublin Core Date by default; you can
pick another property) and matches values written `YYYY-MM-DD`. Undated items
and approximate dates like "circa 1920s" are skipped.

**Item of the day** picks the same item all day for every visitor, then a new
one the next day.

### Other blocks

| Block | What it shows |
|---|---|
| Search box | A search field for one site's items (this site or another), optionally limited to item sets |
| Hero banner | A wide image with a title, subtitle, and button over it |
| Then and now | Two photos of the same place with a slider to compare them; each photo comes from an item or an uploaded asset |
| Quote | A pull quote with attribution, optionally linked to its source item |
| Accordion | Collapsible sections, for FAQs or long text |
| Buttons | One to six buttons linking to pages or web addresses |
| Visit info | Hours, address, directions link, admission, phone, email, notes: typed into the block, or taken from a site's Visit info settings (see below) |
| Upcoming events | Events typed into the block and/or read from a calendar feed (see below); past events drop off by themselves |

### More Special Blocks

| Block | What it shows |
|---|---|
| Announcement bar | A notice ("Closed Monday for the holiday") shown only between two dates; visitors can close it |
| Featured slideshow | Large photos that take turns, with titles, text, and buttons; Pause, arrows, dots; holds still for visitors who ask for reduced motion |
| Gallery | A photo grid (items you choose, or from sites and item sets) that opens each photo large, with arrows between them |
| Explore by topic | Picture tiles you set up, each linking to a page, a search, or anywhere |
| Browse by decade | Decades with item counts, each linking to that decade's items (dates must start with the year) |
| Subjects list | The most-used subjects (or places, or any field) with counts, as buttons, a list, or a word cloud |
| Oral history | An item's recording with its transcript beside it |
| Document and transcription | An item's scanned page beside its typed transcription |
| People | Staff, board, or volunteer cards with photo, role, and a few lines |
| Partners and sponsors | A row of logos linking to partners' websites |
| Fundraising progress | A goal with a progress bar you update |
| Newsletter sign-up | A sign-up form that sends straight to your mailing service (Mailchimp etc.) |
| How to cite and reuse | A filled-in citation for the page, with a Copy button, and your reuse rules |

### And for collections, history, and visitors

| Block | What it shows |
|---|---|
| Who's who in this photo | A photo with numbered markers: click the photo in the sidebar to add one, drag to move it (or type across/down, 0–100); visitors tap one to see the name and note; a numbered list names everyone |
| Item table | Items in a table with the columns you choose; click a heading to sort |
| Milestones | A timeline of moments you type, each with an optional photo |
| Did you know? | One fact from your list, a new one each day (or each visit) |
| Open now | "Open now until 4 pm" / "Closed now · opens Tuesday at 10 am", from the site's Visit info hours ("Tuesday to Saturday \| 10 am to 4 pm") and closed dates, or hours typed into the block; kept current in the visitor's browser every minute |
| Embed from a trusted site | Google Maps, Forms, Calendar, YouTube (privacy-enhanced), Vimeo, Internet Archive; any other address is refused |
| Share this page | Copy link, email, print, Facebook, X, LinkedIn; plain links, no tracking |
| Breadcrumbs | "Home › Exhibits › This page" from the site menu; works in the header |
| Map of places | An OpenStreetMap map (the module's own map code) with pins you place by clicking (or typing coordinates), each linked to an item if you like; "tour" numbers the stops and joins them (walking tours); a list of places is printed under the map |
| Timeline | Entries (date, title, linked item, picture, details) plus dated items, across with Earlier/Later buttons or down the page |
| Table of contents (this page) | `specialJumpLinks`. "On this page", built from the page's headings like a word processor: Heading 2 → section, 3 → subsection, 4 → sub-subsection ("Headings to include": down to H2/H3/H4, or none = only Table links). A heading can be left out or renamed (text block layout data `vpb_toc: [{i, hide, name}]`, `i` = which H2–H4 in the block; in the editor these sit on the heading as `data-toc-hide`/`data-toc-name`, see `model.js` tocApply/tocExtract). Nested lists, optional numbers 1, 1.1, 2. Reading order is section by section, column by column (`Anchors::readingOrder`). Older Link names (`vpb_anchor`/`vpb_anchor_label`) still listed; old settings (`source`, `h2h3`) mapped by `JumpLinks::upgradeData()`. Ids planned once per page by `Anchors::plan()` (also on standard-editor pages, `PageLayout::addAnchors`); the editor mirrors it in `toc.js` |
| Table link | `specialTableLink` (v1.14.0). Name + level for a spot with no heading; renders an empty `span.vpb-table-link` with the planned id; no space on the site (cancels the column gap); dashed bar in the editor (`blocks/toc-blocks.js`) |
| Footnotes | Lists the page's footnotes (typed as `<sup class="vpb-footnote">note</sup>` via the formatting bar); without it they go at the end of the page (`Footnotes` + `PageLayout`) |
| Shared section | Shows a shared section (a hidden public page `vpb-shared-…`, 404 at its own address) via `pageLayout()`; edit once, every page updates; self-inclusion guarded |
| Archive quiz | Multiple-choice questions answered by searching the archive: "Search the archive" link per question (items or whole site), hint, explanation, and the answer's item after checking; score and Try again. Checked in the browser, nothing stored; answers lightly scrambled in the page (not secure) |
| Archive quiz | Multiple-choice questions answered by searching the archive: a ready-made search link (items or the whole site), hint, explanation, and the answer's item after checking; score and Try again. Checked in the browser; answers lightly scrambled in the page; nothing stored |
| Oral history (synced, v1.8) | With times in the transcript ([00:12:40], (12:40), or 12:40 at line starts) or a .vtt/.srt file on the item: highlight + follow along, click a line/time to jump, search inside the interview, captions on video, topic index (time, title, summary, keywords). Plain transcripts show as before. Omeka needs vtt/srt allowed in Settings › Security |
| Story map | A map with a timeline under it and an information pane: Play/Pause/Back/Next moves through stops in date order; each stop has a place, date, linked item, background photo, text, and a YouTube/Vimeo or item video. Never autoplays; pauses at videos; jumps instead of gliding with reduced motion; every pane is shown without JavaScript |

Notes on these:

- **Transcripts** (Oral history, Document and transcription) come from one of
  the item's fields, `bibo:content` ("Content") by default; you can pick
  another. Long transcripts scroll in their box.
- **Subjects list** counts only public items and public values, read straight
  from the database (Omeka's API can't count by value).
- **Newsletter sign-up** needs your service's form address, which must start
  with `https://`. Mailchimp's is in its embedded form code, in `action="…"`.
  Omeka stores nothing; sign-ups go straight to the service.
- **Browse by decade** links go to this site's item browse page.

The search box searches one site because search results open on a site's own
item browse page, which only lists that site's items.

### Time zone

Everything about "today" and "now" (announcements, events, on this day, item
of the day, did you know, open now, the copyright year) follows the time zone
in Omeka's settings (Settings › General), not the web server's clock.

### Visit info: enter it once

Each site has a **Visit info** group under Sites › (the site) › Settings:
hours (one line per row, days and times separated by `|`, for example
`Tuesday to Saturday | 10 am to 4 pm`), a note about hours, address,
directions link, admission, phone, email, and more information, plus closed
dates (one per line: `2026-11-26 | Thanksgiving`), which Open now uses. Set a Visit
info block's **Where the details come from** to **A site's visit info** and
pick the site (or leave it on this site), and the block always shows the
current details. Change the hours once and every page updates.

### Upcoming events: calendar feeds

An Upcoming events block can read a calendar feed: an iCalendar (`.ics`)
address. In Google Calendar it's under Settings › (your calendar) ›
"Public address in iCal format"; Outlook and most calendar apps can publish
one too. The feed's events are listed together with any typed into the block.

- The server checks the feed at most once an hour and keeps a copy in
  `files/visual-page-builder/`. If the calendar can't be reached, the last
  copy is used, and the page always still shows.
- Times are shown in the time zone set in Omeka's settings.
- Repeating events show their first date only. Cancelled events are left out.

### Safety

- Links only accept `https://`, `http://`, site paths (`/s/…`), `mailto:`, and
  `tel:`. Anything else (like `javascript:`) is removed when the page is saved.
- Rich text (accordion sections, visit notes) is cleaned by Omeka's HTML
  purifier when saved, following Omeka's "Use HTMLPurifier" setting.
- Everything else is printed as plain text.
- Private items, item sets, and sites only appear to people allowed to see them.

### Changing the look

Every block wraps its output in `.special-blocks` plus a block class such as
`.special-blocks-random-items`. Colors and spacing come from CSS custom
properties you can override in your theme:

```css
.special-blocks { --sb-accent: #7a2e1c; --sb-radius: 0; }
```

Available: `--sb-gap`, `--sb-radius`, `--sb-accent`, `--sb-accent-text`,
`--sb-muted`, `--sb-border`, `--sb-surface`. See `asset/special-blocks/css/special-blocks.css`.

To change a block's markup, copy its template from
`view/common/block-layout/special-blocks/` into your theme at the same path.
Each template lists the variables it receives at the top.

## How pages are stored

Everything is saved as ordinary Omeka page data:

- The page uses Omeka's Grid layout with 12 columns.
- Each section is an Omeka **block group**. Its background, padding, and class
  use Omeka's standard block settings. Its column widths are stored in the
  group's layout data under `vpb_section`.
- Each block keeps Omeka's own `grid_column_position` and `grid_column_span`,
  plus `vpb_col`, the index of the column it sits in.
- The page's layout data gets `visual_page_builder: {version: 1}`.

## Upgrading from the separate Society Blocks module

Early test versions (before 0.5.0) shipped these blocks as a separate module, "Society
Blocks". If you installed it: in **Modules**, uninstall Society Blocks first
(this keeps your pages' blocks), delete its folder, then upgrade this module.
The upgrade switches every page that used them over to the Special Blocks here,
with their settings.

## If you remove the module

Pages keep working. Omeka still shows every block in its section, at the right
position and width, with its background and other settings. The one difference:
when a column holds several blocks, Omeka lines them up row by row alongside the
other columns instead of stacking them in a column.

Special Blocks stop showing on the public site (Omeka skips block types it
doesn't know) and keep their settings; reinstalling brings them back.

The module adds no database tables, so uninstalling it leaves nothing behind.

## Limits in this version

- The editor's preview approximates your theme: it uses the block's real
  content but not your theme's fonts and colors. Use **View page** to see the
  real result. The table of contents lists the page's real sub-pages from the
  site's navigation.
- oEmbed and IIIF blocks are checked by Omeka when you save. If an address
  can't be read, the save stops with Omeka's message and nothing is lost.
- Only text blocks can be edited on the page. Links, images inside text, and
  other rich formatting are edited in the standard editor.
- Text blocks containing embedded content (like a video) are shown locked, so
  the editor can't damage the embed. Edit those in the standard editor.
- On phones and narrow windows, columns stack in order, following Omeka's own
  Grid layout behavior.

## Permissions

Anyone who can edit a page in Omeka can use the visual editor on it. Everyone
else is refused, and Omeka checks permissions again when saving.

## For developers: code map

No build step: the editor is plain JavaScript files loaded in order by
`view/visual-page-builder/admin/builder/edit.phtml`, sharing one global
namespace, `VPB`.

| Path | What it does |
|---|---|
| `Module.php` | Adds the "Open visual editor" button; route access |
| `config/module.config.php` | Route, controller and service factories, the `pageLayout` override |
| `src/Controller/Admin/PagesController.php`, `view/visual-page-builder/admin/pages/` | Modules › Visual Builder: sites, then one page per site (header, footer, menu, theme, pages) |
| `src/SiteRegions.php`, `src/Controller/Admin/RegionController.php`, `src/View/Helper/VpbSiteRegion.php` | Header & footer: storage as reserved pages, opening them in the editor, and rendering them for the theme |
| `src/Controller/Admin/MenuController.php`, `view/visual-page-builder/admin/menu/`, `asset/menu-builder/` | The menu builder (validated save to the site's navigation; style in the site setting `vpb_menu_style`) |
| `src/Controller/Admin/BuilderController.php` | `edit` (editor page + its config), `save` (JSON POST, CSRF, block whitelist), `lookup` (picker search), `preview` (live previews) |
| `src/SpecialBlocks.php` | The Special Blocks: layout name → class |
| `src/Site/BlockLayout/` | One class per Special Block, plus `AbstractSchemaBlock` (form, save, render, help, preview), `AbstractItemBlock` (item grids), `DisplayFields` |
| `src/Schema/` | Field format (`Schema`), data cleaning (`Normalizer`), standard editor forms (`FormBuilder`) |
| `src/Stdlib/ImageSource.php`, `ValueCounts.php`, `CalendarFeed.php` | "Item or asset" images; value counts (Subjects list); calendar feeds (Upcoming events) |
| `src/Form/VisitInfoSettings.php` | The Visit info group in each site's settings |
| `src/Stdlib/ItemSource.php` | The "Show from" fields (this site / chosen sites / all sites), queries, link sites |
| `src/Api/MultiSiteFilter.php` | The `special_blocks_site_ids` search filter (several sites at once) |
| `src/Service/BlockLayout/SchemaBlockFactory.php`, `src/View/Helper/SpecialBlocksNumber.php` | Builds the blocks; formats counts |
| `view/common/block-layout/special-blocks/`, `asset/special-blocks/` | Special Blocks templates, public CSS, the then-and-now slider, standard editor helpers |
| `src/Stdlib/ResourceLookup.php` | Item/asset search, site and item set lists, and the titles/thumbnails the editor shows |
| `src/View/Helper/PageLayout.php` | Public rendering of builder pages as real columns |
| `asset/js/editor/core.js` | `VPB` namespace, server config, constants, small utilities |
| `asset/js/editor/dom.js` | Element builder, icons, toast, preview sanitizer |
| `asset/js/editor/model.js` | Omeka page JSON ⇄ sections/columns/blocks (storage format) |
| `asset/js/editor/store.js` | Editor state, selection lookups, undo/redo |
| `asset/js/editor/fields.js`, `picker.js` | Sidebar form controls; item/asset search picker |
| `asset/js/editor/previews.js` | Live previews fetched from the server (cached per settings) |
| `asset/js/editor/palette.js`, `layers.js` | The Blocks tab (search, ⓘ help, groups) and the Layers tab |
| `asset/js/editor/templates/`, `templates-panel.js` | Page templates: `catalog.js` explains the format; one file per kind of page (five templates each); the Templates tab |
| `asset/js/editor/side-resize.js` | Dragging the sidebar edge to resize it |
| `asset/js/editor/blocks/` | One file per Omeka block type (settings form + preview); `registry.js` explains the parts; `about.js` has the help text for Omeka's blocks; `schema.js` builds forms for other modules' blocks |
| `asset/js/editor/actions.js` | Move, duplicate, delete, column widths, palette adds |
| `asset/js/editor/canvas.js`, `drag.js`, `resize.js` | Page preview, drag and drop, column resizing |
| `asset/js/editor/sidebar.js`, `save.js`, `app.js` | Sidebar views, saving, startup and wiring |

**Supporting another block type** inside this module: add
`asset/js/editor/blocks/<name>.js` that calls `VPB.blocks.register('<layout>',
{ card, editor })`, and list it in `edit.phtml` after `blocks/registry`.
Everything else picks it up. Keys you don't set in `o:data` are kept as they were.

Development tools (tests, linting, the test Omeka install) live in the
transfer kit outside this folder, not in the module.

### Special Blocks: one schema per block

Each block lists its settings once, in `schema()`. That one list drives:

- the standard editor form (`FormBuilder`),
- cleaning the data on save (`Normalizer`, from `onHydrate`),
- the Visual Page Builder sidebar (`visualPageBuilderSchema()`),
- the text Omeka's site search indexes (`getFulltextText()`).

Each block also has `DESCRIPTION` and `GOOD_FOR` constants: plain-language help
shown at the top of its standard editor form and behind its ⓘ button in the
Visual Page Builder. `visualPageBuilderPreview()` renders the block's real
template from unsaved settings for that module's live preview, so templates
get `$blockKey` (the block id, or "preview") rather than relying on a saved
block.

A field looks like this (full format in `src/Schema/Schema.php`):

```php
[
    'key' => 'count', 'type' => Schema::TYPE_NUMBER, 'label' => 'How many to show',
    'default' => 6, 'min' => 1, 'max' => 24, 'group' => 'Display', 'summary' => true,
]
```

`show_if` hides a field unless another has certain values, in both editors:
`'show_if' => ['site_scope' => ['selected']]`.

### Adding a page template

Add a `VPB.templates.add('<kind>', { id, name, description, sections })` call
to the right file in `asset/js/editor/templates/` (format and helpers in
`catalog.js`). `tests/module/templates.test.js` checks every template's blocks
and settings against the real block schemas, then applies, saves, and renders
each one, so a typo in a setting name fails the tests.

### Adding a Special Block

1. Write `src/Site/BlockLayout/YourBlock.php` extending `AbstractSchemaBlock`
   (or `AbstractItemBlock` for an item grid). Give it `getLabel()`, `schema()`,
   `const TEMPLATE`, `const DESCRIPTION`, `const GOOD_FOR`, and `viewVars()` if
   the template needs more than the saved settings (it receives the site and a
   `$blockKey`, never a saved block, so previews work).
2. Write `view/common/block-layout/special-blocks/<template>.phtml`.
3. Add it to `src/SpecialBlocks.php`. Never rename a layout name once pages use it.

No JavaScript is needed for the Visual Page Builder: it builds the sidebar
form from the schema.

## Blocks from other modules

Any module can have its blocks edited in this sidebar without writing
JavaScript: give the block layout class a public `visualPageBuilderSchema()`
method that returns an array like this. Only `fields` is required.

```php
return [
    'fields' => [/* see the table below */],
    'description' => 'Shows a different handful of items each time someone visits.',
    'goodFor' => 'Home pages that feel fresh on every visit.',
    'paletteGroup' => 'Your Blocks',             // its own group in the palette
    'paletteFeatured' => true,                   // list it right after Sections and Text
    'preview' => true,                           // has visualPageBuilderPreview()
    'stylesheets' => [['module' => 'YourModule', 'file' => 'css/blocks.css']],
];
```

The editor then builds the sidebar form and the block's summary card from the
fields (`asset/js/editor/blocks/schema.js`), and shows `description` and
`goodFor` behind the block's ⓘ button.

For a live preview, also add
`visualPageBuilderPreview(PhpRenderer $view, array $data, SiteRepresentation $site): string`,
returning the block's HTML for unsaved settings (ideally from the block's real
template). The editor shows it inert (it can't be clicked) after removing
scripts and event handlers, and loads the listed `stylesheets` so it looks
right.

Each field is an array:

| Key | Meaning |
|---|---|
| `key`, `type`, `label` | Required. `key` is the `o:data` key. |
| `info`, `placeholder`, `default` | Optional help text, placeholder, default value. |
| `options` | For `select`: `[value => label]`. |
| `min`, `max` | Bounds for `number`; most rows for `list`. |
| `show_if` | `[otherKey => [values…]]`: only shown when another field has one of these values. |
| `fields` | Sub-fields of each row, for `list`. |
| `group` | Heading the field appears under. |
| `summary` | Show this value on the block's card on the page. |

Types: `text`, `textarea`, `html`, `number`, `select`, `checkbox`, `url`,
`date`, `site`, `sites`, `item_sets`, `asset`, `item`, `page`, `list`.

Values are saved in their natural types (`true`, `12`, `[3, 4]`). The block's
own module should still clean them when Omeka saves the page (in
`onHydrate()`), since the standard editor and the API can send anything.
The Special Blocks in this module are a complete example: see `src/Schema/`
and `src/Site/BlockLayout/AbstractSchemaBlock.php`.

## License

Copyright (C) 2026 Dickinson Historical Society

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any later
version. (SPDX: `GPL-2.0-or-later`.)

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
The full text is in the `LICENSE` file.
