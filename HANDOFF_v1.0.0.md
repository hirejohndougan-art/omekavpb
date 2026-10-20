# Handoff: Visual Page Builder 1.0.0 + Companion Theme 1.0.0

John: "All of this should be version 1." Both are now **1.0.0**, the first
public release (for the Omeka S forum). No code changes from 0.12.1 / 0.3.1.

- `module.ini` and `theme.ini` versions; download is
  `dist/Visual-Page-Builder-1.0.0.zip`.
- README headings that named internal versions ("Added in 0.7.0") now
  describe the blocks instead; the Society Blocks upgrade note says "early
  test versions".
- Upgrading from any 0.x works (checked 0.12.1 → 1.0.0 on the test install);
  `Module::upgrade()` still converts Society Blocks pages from before 0.5.0.
- Versioning from here: 1.0.x for fixes, 1.x.0 for new features (semver).
