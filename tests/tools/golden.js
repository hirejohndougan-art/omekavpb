'use strict';
/**
 * Golden-master check for refactors: records what the editor renders (canvas,
 * palette, and the settings panel for every section, column, and block) plus the
 * exported block list, for a few pages. Record before a refactor, compare after.
 *
 *   node tests/tools/golden.js record <file> <pageId> [pageId...]
 *   node tests/tools/golden.js compare <file>
 */
const fs = require('fs');
const H = require('../lib/harness');

async function capture(pageId) {
  const ed = await H.openEditor(pageId);
  const { d, V } = ed, u = H.ui(ed);
  const shot = { errs: ed.errs, canvas: d.getElementById('secs').innerHTML, palette: d.getElementById('palette').innerHTML, notice: d.getElementById('notice').innerHTML, export: V.exportBlocks(), panels: {} };
  const targets = [...d.querySelectorAll('#secs [data-kind]')].map(e => [e.getAttribute('data-kind'), e.getAttribute('data-id')]);
  for (const [kind, id] of targets) {
    u.press(d.querySelector(`[data-kind="${kind}"][data-id="${id}"]`));
    shot.panels[kind + ':' + id] = normalize(d.getElementById('propsView').innerHTML);
  }
  return shot;
}

(async () => {
  const [mode, file, ...ids] = process.argv.slice(2);
  if (mode === 'record') {
    const out = {};
    for (const id of ids) out[id] = await capture(id);
    fs.writeFileSync(file, JSON.stringify(out));
    console.log('recorded', Object.keys(out).length, 'pages,', Object.values(out).reduce((n, s) => n + Object.keys(s.panels).length, 0), 'panels');
  } else if (mode === 'compare') {
    const want = JSON.parse(fs.readFileSync(file, 'utf8'));
    let diffs = 0;
    for (const id of Object.keys(want)) {
      const got = await capture(id);
      for (const k of ['errs', 'canvas', 'palette', 'notice', 'export']) {
        if (JSON.stringify(got[k]) !== JSON.stringify(want[id][k])) { diffs++; console.log(`page ${id}: ${k} differs`); showDiff(JSON.stringify(want[id][k]), JSON.stringify(got[k])); }
      }
      for (const p of Object.keys(want[id].panels)) {
        if (got.panels[p] !== want[id].panels[p]) { diffs++; console.log(`page ${id}: panel ${p} differs`); showDiff(want[id].panels[p] || '', got.panels[p] || ''); }
      }
    }
    console.log(diffs ? `${diffs} difference(s)` : 'identical to the recording');
    process.exit(diffs ? 1 : 0);
  } else {
    console.log('usage: golden.js record <file> <pageId...> | compare <file>'); process.exit(2);
  }
})().catch(e => { console.error(e); process.exit(2); });

// The "Link to page" list shows every page in the site, which grows as tests
// create pages; mask it so only the editor's own output is compared.
function normalize(html) { return html.replace(/(<select id="f-xp\d+">)[\s\S]*?(<\/select>)/g, '$1\u2026$2'); }

function showDiff(a, b) {
  a = String(a); b = String(b);
  let i = 0; while (i < a.length && a[i] === b[i]) i++;
  console.log('   expected: …' + a.slice(Math.max(0, i - 80), i + 120));
  console.log('   got:      …' + b.slice(Math.max(0, i - 80), i + 120));
}
