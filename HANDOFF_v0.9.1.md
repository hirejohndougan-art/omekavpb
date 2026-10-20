# Handoff: Visual Page Builder v0.9.1 + Companion Theme 0.1.1

John: "Place the theme and the module in the same zip folder with an
instruction file on how to install them and use them. Page builder needs to
be called Companion Theme for visual page builder."

- Theme renamed: display name **Companion Theme for Visual Page Builder**,
  folder `visual-page-builder-companion` (was `page-builder`). The menu
  builder's "is this site using the companion theme" check, docs, tests, and
  lint config follow. Old installs: delete `themes/page-builder`.
- One download, `dist/Visual-Page-Builder-<version>.zip`, built by
  `npm run package`: `VisualPageBuilder/`, `visual-page-builder-companion/`,
  and `HOW TO INSTALL AND USE.txt` at the top of the zip, so Windows
  "Extract All" gives one folder with those three inside (checked).
- The guide is plain text (opens in Notepad): what's inside, backup, module
  install/upgrade (incl. from Society Blocks), theme install and settings,
  using the editor, Special Blocks, Visit info, calendar feeds, menu builder,
  good-to-know, and troubleshooting. Source: `dist-docs/`.
- No code behavior changed. `npm test`: 18 suites, all pass.
