'use strict';
// Runs every *.test.js under tests/ one at a time and reports a summary.
// Needs a running Omeka S test install (see CONTINUE_HERE.md §7) seeded with tests/tools/seed.js.
const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = __dirname;
const files = [];
(function walk(dir) {
  for (const f of fs.readdirSync(dir).sort()) {
    const p = path.join(dir, f);
    if (fs.statSync(p).isDirectory()) { if (f !== 'node_modules') walk(p); } else if (f.endsWith('.test.js')) files.push(p);
  }
})(root);

const only = process.argv[2];
let failed = 0;
for (const f of files) {
  if (only && !f.includes(only)) continue;
  const r = spawnSync(process.execPath, [f], { stdio: 'inherit', timeout: 120000 });
  if (r.status !== 0) { failed++; if (r.error) console.log('  ' + r.error.message); }
}
console.log(failed ? `\n${failed} test file(s) failed.` : '\nAll test files passed.');
process.exit(failed ? 1 : 0);
