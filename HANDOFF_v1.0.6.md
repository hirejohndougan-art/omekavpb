# Handoff: Visual Page Builder 1.0.6 + Companion Theme 1.0.3

John: combine Page builder, Header & footer, and Menu builder under one menu
item called "Visual Builder", all shown on one page.

- One admin sidebar entry, **Visual Builder** (Modules). Route
  `admin/visual-page-builder-pages`, now at `/admin/visual-builder[/:site-slug]`.
- `/admin/visual-builder`: sites (skipped when only one; `?site=slug` from the
  switcher redirects to it).
- `/admin/visual-builder/:site`: cards for Header and Footer (Built / Not
  built yet, Edit), Menu (link count and style, Edit), Theme (Companion or not,
  Theme settings), then Pages (Find a page, Add new page, table with Open
  visual editor / View / Standard editor). A site switcher when there are
  several sites.
- The old site lists (`/admin/visual-page-builder/menu` and `/region`) redirect
  to the Visual Builder; their views were removed. The menu builder's back
  link goes to the site's Visual Builder page. Editing routes are unchanged.
- Docs and the theme's text updated (theme 1.0.3: text only).
- Tests: pages-list 15 (one nav entry, switcher, old addresses redirect, all
  cards), menu-builder and regions updated. Full suite passes.
