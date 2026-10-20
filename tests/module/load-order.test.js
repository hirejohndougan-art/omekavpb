'use strict';
// Each editor script may only read parts of VPB defined by scripts listed before
// it in edit.phtml. This loads the scripts in page order and fails on any
// load-time read of a part that doesn't exist yet. (Lint can't see this.)
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('script load order');
  const pageId = await H.createPage('load-order', [{ 'o:layout': 'html', 'o:data': { html: '<p>x</p>' } }]);
  const html = await (await fetch(H.editorUrl(pageId), { headers: { Cookie: H.readCookie() } })).text();
  const ed = await H.openEditor(pageId, { html }); // Finds the script list.
  const dom = new JSDOM(html.replace(/<script src="[^"]*VisualPageBuilder[^"]*"><\/script>/g, ''), {
    url: H.editorUrl(pageId), runScripts: 'outside-only', pretendToBeVisual: true,
    beforeParse(w) { w.matchMedia = () => ({ matches: false }); }
  });
  const w = dom.window;
  const missing = [];
  let current = null;
  const target = {};
  w.VPB = new Proxy(target, {
    get(o, k) {
      if (current && typeof k === 'string' && !(k in o)) missing.push(`${current} reads VPB.${k} before it is defined`);
      return o[k];
    }
  });
  for (const f of ed.files) {
    current = path.relative(H.MODULE_DIR, f);
    try { w.eval(fs.readFileSync(f, 'utf8')); } catch (e) { missing.push(`${current}: ${e.message}`); }
  }
  current = null;
  t.ok(ed.files.length > 20, 'script list read from the editor page: ' + ed.files.length + ' files');
  t.eq(missing, [], 'no script reads a part that loads later');
  const onDisk = [];
  (function walk(d) { for (const f of fs.readdirSync(d)) { const p = path.join(d, f); if (fs.statSync(p).isDirectory()) walk(p); else if (f.endsWith('.js')) onDisk.push(p); } })(path.join(H.MODULE_DIR, 'asset/js'));
  t.eq(onDisk.filter(f => !ed.files.includes(f)).map(f => path.relative(H.MODULE_DIR, f)), [], 'every script file is loaded by the editor page');
  t.ok(!ed.errs.length, 'editor starts without errors: ' + ed.errs);
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
