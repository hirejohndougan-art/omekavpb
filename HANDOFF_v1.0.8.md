# Handoff: Visual Page Builder 1.0.8 (theme unchanged, 1.0.3)

John: while editing, a button to return to the site's page under Modules.

- Editor top bar (pages, header, footer): "← Visual Builder" button
  (`#backToHub`, config `hubUrl` → `/admin/visual-builder/:site`), first after
  the sidebar toggle. The site name in the breadcrumb now also goes there
  (it used to go to Omeka's own page list). Unsaved changes still trigger the
  browser's "leave page?" warning.
- Menu builder: its back link is now the same button style.
- Class is `hub-back` (not `back-btn`, which the sidebar already uses for
  its own "back" button; the first try collided and a test caught it).
- Test: pages-list +2. Full suite passes.
