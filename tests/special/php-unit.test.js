'use strict';
// Runs the PHP unit checks (php/unit.php) as part of npm test.
const { spawnSync } = require('child_process');
const path = require('path');
const r = spawnSync('php', [path.join(__dirname, 'php', 'unit.php')], { stdio: 'inherit' });
process.exitCode = r.status === 0 ? 0 : 1;
