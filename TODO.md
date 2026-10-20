# Visual Page Builder — to-do list

Kept by John's request (Oct 2026). Newest additions at the top of each part.
Update this file whenever an item is added, done, or dropped.

## To do

1. **Move the "Visual Builder" admin menu link under "Sites".** Right now it's
   in Omeka's Modules section at the bottom of the admin side menu. Move it to
   the top section (navigation container `AdminSite`, under Sites) in
   `config/module.config.php`, in one place only (not duplicated in Modules).
   Same page, address, and permissions. Update the test that checks the menu
   (`module/pages-list.test.js`) and the install guide.

## To do later

2. **Replace File** — its own separate Omeka S module (not part of Visual Page
   Builder): replace a media file in place from the item edit page, keeping the
   media record, position, title, description, links, and page blocks;
   thumbnails remade. Open question: keep a backup copy of the old file?

## Waiting on John's decision

3. **Faceted search** on the public site (decade, subject, place, type,
   collection), done inside Omeka, reading dates loosely like the Timeline.
4. **Matching against existing records** — a lookup service for tools like
   OpenRefine.

## On hold

5. **Omeka Classic version** of the page builder (Simple Pages and/or Exhibit
   Builder; for the Texas City library site, the forum, or both — not decided).

## Decided not to build

- Zoomable image viewer, IIIF tiling, or a custom image server.

## Notes kept elsewhere

- Things to build in Polybius instead: `POLYBIUS-NOTES.md`.
