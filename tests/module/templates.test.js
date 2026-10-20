'use strict';
// v0.8.0: page templates. Every template is checked against the real block
// schemas, applied, saved, and rendered on the public site.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('page templates');
  const pageId = await H.createPage('Template test', [{ 'o:layout': 'html', 'o:data': { html: '<p>Original content</p>' } }]);
  const ed = await H.openEditor(pageId);
  const { w, d, V } = ed, u = H.ui(ed);
  const VPB = w.VPB;
  t.ok(!ed.errs.length, 'editor loads ' + ed.errs);

  // ---- the catalog
  const types = VPB.templates.TYPES.map(x => x.id);
  t.eq(types, ['home', 'browse', 'story', 'about', 'contact', 'events', 'sections', 'header', 'footer'], 'six kinds of page, ready-made sections, headers, and footers');
  t.ok(types.every(x => VPB.templates.ofType(x).length === (x === 'sections' ? 10 : 5)), 'five templates of each kind; ten ready-made sections');
  const all = VPB.templates.all();
  t.ok(new Set(all.map(x => x.id)).size === 50, '50 templates with unique ids');

  // ---- every block in every template matches a real block and its settings
  const schemas = {};
  ed.cfg.layouts.forEach(l => { if (l.schema) schemas[l.name] = l.schema.fields; });
  const problems = [];
  const checkData = (fields, data, where) => Object.keys(data).forEach(k => {
    const f = fields.find(x => x.key === k);
    if (!f) { problems.push(where + ': unknown setting "' + k + '"'); return; }
    if (f.type === 'list') data[k].forEach((row, i) => checkData(f.fields, row, where + '.' + k + '[' + i + ']'));
    if (f.type === 'select' && !f.options.some(o => o[0] === String(data[k]))) problems.push(where + ': "' + k + '" = ' + data[k] + ' is not an option');
  });
  all.forEach(tpl => tpl.sections.forEach((s, si) => {
    if (s.cols.reduce((a, b) => a + b, 0) !== 12) problems.push(tpl.id + ' section ' + si + ': columns don\u2019t add up to 12');
    if (s.blocks.length !== s.cols.length) problems.push(tpl.id + ' section ' + si + ': blocks for ' + s.blocks.length + ' columns, has ' + s.cols.length);
    s.blocks.flat().forEach(([layout, data]) => {
      const where = tpl.id + ' ' + layout;
      if (!ed.cfg.layouts.some(l => l.name === layout)) problems.push(where + ': not an available block');
      else if (schemas[layout]) checkData(schemas[layout], data, where);
    });
  }));
  t.eq(problems, [], 'every template block and setting is valid');

  // ---- the Templates tab
  [...d.querySelectorAll('[role="tab"]')].find(b => b.textContent === 'Templates').click();
  t.ok(d.querySelectorAll('.tpl-card').length === 5 && d.querySelectorAll('#tplType option').length === 7, 'on a page, the tab shows five home templates and the page kinds plus ready-made sections (no headers or footers)');
  const sel = d.getElementById('tplType'); sel.value = 'about'; sel.dispatchEvent(new w.Event('change'));
  t.ok([...d.querySelectorAll('.tpl-card h3')].map(x => x.textContent).includes('Questions and answers'), 'switching kinds lists their templates');
  t.ok(d.querySelector('.tpl-diagram .tpl-row .tpl-col span'), 'each template has a diagram');
  let confirmed = 0; w.confirm = () => { confirmed++; return true; };
  u.button('Use for this page', d.querySelector('.tpl-card')).click();
  t.ok(confirmed === 1 && !/Original content/.test(JSON.stringify(V.state.sections)) && V.state.sections.length === 3, 'replacing asks first, then replaces the page');
  d.getElementById('undo').click();
  t.ok(/Original content/.test(JSON.stringify(V.state.sections)), 'undo brings the old page back');
  u.button('Add to the end', d.querySelector('.tpl-card')).click();
  t.ok(/Original content/.test(JSON.stringify(V.state.sections)) && V.state.sections.length === 4, 'adding keeps what was there');

  // ---- apply, save, and render every template
  const failed = [];
  for (const tpl of all) {
    VPB.templatesPanel.apply(tpl, true);
    const res = await H.saveBlocks(ed, V.exportBlocks());
    if (res.status !== 200 || !res.body.ok) { failed.push(tpl.id + ' save ' + res.status + ' ' + JSON.stringify(res.body.errors)); continue; }
    const html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${res.body.page['o:slug']}`)).text();
    if (/Fatal error|Warning:|Notice:/.test(html) || !/\[|special-blocks/.test(html)) failed.push(tpl.id + ' public page problem');
  }
  t.eq(failed, [], 'all 50 templates save and render');
  t.ok(!ed.errs.length, 'no runtime errors ' + ed.errs);
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
