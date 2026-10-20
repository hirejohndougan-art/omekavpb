# Handoff: Visual Page Builder 1.0.5 (theme unchanged, 1.0.2)

John (screenshot): after removing the empty sections, the header in the
editor still had a big empty area below the blocks.

Cause: the editor's page area (`.secs`) has `min-height: 220px`, sensible for
pages, not for a header. Now `app.js` adds `editing-region` and
`editing-header`/`editing-footer` to the body when `cfg.region` is set, and
editor.css removes the minimum height there, trims padding, and centers the
columns vertically (as the Companion Theme does for built headers), so the
menu lines up with the logo and search box. Pages are unchanged.

The public site was already fine (empty sections skipped since 1.0.2).
Tests: regions +1, v011 +1. Full suite passes.
