# Handoff: Visual Page Builder 1.0.4 (theme unchanged, 1.0.2)

John: Header & footer and Menu builder show under Modules; the page builder
should too, choosing a site there to reach its pages.

- `PagesController` + route `admin/visual-page-builder-pages`
  (`/admin/visual-page-builder/pages[/:site-slug]`) + "Page builder" first in
  the Modules section of the admin sidebar.
- Sites list (with page counts) → a site's pages sorted by title: title and
  address, public/private, last changed, **Open visual editor**, View,
  Standard editor; buttons for Edit header / Edit footer / Edit menu / Add new
  page (Omeka's own add-page); "Find a page" (`?q=`, title or address).
  Hidden header/footer pages are left out. Permission: site update.
- Test `module/pages-list.test.js` (11). Full suite passes.
