'use strict';
// Special Blocks in Omeka's standard page editor: the forms render, and a
// submitted form saves cleaned data. The form is submitted the way Omeka's
// page script does it: each block's __blockIndex__ becomes its position.
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('special blocks: standard editor');
  const fx = await H.specialFixtures();
  const pageId = await H.createPage('special-std', [
    { 'o:layout': 'specialRandomItems', 'o:data': { count: 4 } },
    { 'o:layout': 'specialAccordion', 'o:data': { panels: [{ title: 'One', body: '<p>First</p>' }] } }
  ]);
  const page = await H.api('GET', 'site_pages/' + pageId);
  const editUrl = `${H.BASE}/admin/site/s/${H.SITE}/page/${page['o:slug']}/edit`;
  const cookie = H.readCookie();
  const html = await (await fetch(editUrl, { headers: { Cookie: cookie } })).text();
  t.ok(!/Fatal error|Warning:|Notice:/.test(html), 'edit page renders without PHP errors');
  const d = new JSDOM(html).window.document;
  const forms = d.querySelectorAll('.special-blocks-form');
  t.ok(forms.length === 2, 'both block forms render');
  t.ok(/different handful of items/.test((d.querySelector('.special-blocks-form-about') || {}).textContent || ''), 'each form starts with what the block does');
  t.ok(/admin-form\.js/.test(html) && /admin-form\.css/.test(html), 'form script and styles load');
  const sitesField = d.querySelector('.special-blocks-field[data-key="site_ids"]');
  t.ok(sitesField && /"site_scope":\["selected"\]/.test(sitesField.getAttribute('data-show-if')), 'Sites field only applies to "Chosen sites"');
  t.ok(d.querySelectorAll('.special-blocks-list .special-blocks-row').length === 3, 'accordion: saved row plus two blank rows');

  // Fill in the form as an editor would.
  const blocks = [...d.querySelectorAll('.block.value')];
  blocks.forEach((b, i) => b.querySelectorAll('[name]').forEach(el => el.setAttribute('name', el.getAttribute('name').replace(/__blockIndex__/g, i))));
  const set = (name, v) => { const el = d.querySelector(`[name="${name}"]`); if (!el) throw new Error('no field ' + name); el.value = v; };
  set('o:block[0][o:data][site_scope]', 'selected');
  const sites = d.querySelector('[name="o:block[0][o:data][site_ids][]"]');
  [...sites.options].forEach(o => { o.selected = [String(fx.dhs), String(fx.legacy)].includes(o.value); });
  set('o:block[0][o:data][count]', '9');
  d.querySelector('[name="o:block[0][o:data][has_media]"][type="checkbox"]').checked = false;
  set('o:block[1][o:data][panels][1][title]', 'Two');
  set('o:block[1][o:data][panels][1][body]', '<p>Second</p>');

  const form = d.getElementById('site-page-form');
  const body = new URLSearchParams();
  [...form.elements].forEach(el => {
    if (!el.name || el.disabled) return;
    if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
    if (el.tagName === 'SELECT' && el.multiple) { [...el.selectedOptions].forEach(o => body.append(el.name, o.value)); return; }
    body.append(el.name, el.value);
  });
  const res = await fetch(editUrl, { method: 'POST', redirect: 'manual', headers: { Cookie: cookie, 'Content-Type': 'application/x-www-form-urlencoded' }, body });
  t.ok(res.status === 302, 'form accepted (redirect after save): ' + res.status);

  const saved = await H.api('GET', 'site_pages/' + pageId);
  const r = saved['o:block'].find(b => b['o:layout'] === 'specialRandomItems')['o:data'];
  t.eq([r.site_scope, r.site_ids, r.count, r.has_media], ['selected', [fx.dhs, fx.legacy], 9, false], 'random items settings saved and typed');
  const a = saved['o:block'].find(b => b['o:layout'] === 'specialAccordion')['o:data'];
  t.eq(a.panels.map(p => p.title), ['One', 'Two'], 'accordion: new row saved, blank row dropped');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
