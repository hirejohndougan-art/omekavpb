# Handoff: Visual Page Builder 1.8.2 (theme unchanged, 1.0.3) — code check

John: "Do a thorough code check." (After the 1.8.1 browser bug sweep.)

## What was checked
- **PHP static analysis**: PHPStan 1.12 level 5, PHP 7.4 target, on src/,
  Module.php, config (`tests/static/`). After setting aside known false
  alarms (Laminas view-helper/controller-plugin magic methods, strlen
  callbacks, redirect return types, dynamic keys), nothing real remained.
- **PHP 7.4 compatibility**: no PHP 8-only syntax or functions
  (str_contains, match, nullsafe, attributes, union/mixed types, promoted
  constructors, enums, readonly).
- **Security review**: every innerHTML/insertAdjacentHTML in the JS (all
  static markup or sanitized; text via textContent); every unescaped echo in
  templates (only the three rich-text fields — Citation reuse, Accordion
  body, Visit info notes — which go through Omeka's HTML purifier like
  Omeka's own HTML block); editor actions all go through `loadPage()`
  (page update permission), JSON POSTs check CSRF.
- **JS lint**: ESLint recommended + no-unused-vars, no-shadow, eqeqeq,
  strict, no-implicit-globals, ES2017 syntax: clean.
- **CSS validity** (css-tree, all module and theme stylesheets): clean.
- **Duplicate ids / labels / radio groups** with two of every interactive
  block on one page (public and editor).
- Full suite (4.2.1), browser sweep: clean.

## Fixed
1. Editor: server previews of two identical blocks (Story map, Timeline,
   Quiz, Oral history, Search box) put duplicate ids on the editor page.
   `previews.js` now drops id/for/aria-* references inside previews (they're
   inert pictures of the block anyway).
2. Synced oral history: the current-line highlight used `color-mix()` only;
   older browsers got no background. Added a plain fallback.
