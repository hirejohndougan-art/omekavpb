# Static checks (v1.8.2 code check)

- **PHPStan** (level 5, PHP 7.4 target): `php phpstan.phar analyse -c tests/static/phpstan.neon`
  (phpstan.phar 1.12 from GitHub releases; paths in the two files assume the
  container layout: kit in /home/claude/vpb, Omeka in /home/claude/omeka-s).
  Expected remaining output is false alarms: Laminas view-helper and
  controller-plugin magic methods (`$view->api()`, `$this->translate()`, …),
  `array_filter(…, 'strlen')`, redirect return types, and dynamic array keys.
- **CSS validity**: `node tests/browser/csscheck.js <css files>` (needs `npm install --no-save css-tree`).
- **Duplicate ids / labels / radio groups**: `python3 tests/browser/dupids2.py`.
