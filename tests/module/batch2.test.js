'use strict';
// v1.2.0: ready-made sections, links and pictures in typed text, columns on
// smaller screens, and Preview on site.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('v1.2.0 editor features');
  const fx = await H.fixtures();
  const pageId = await H.createPage('Batch two', [{ 'o:layout': 'html', 'o:data': { html: '<p>Hello</p>' } }, { 'o:layout': 'html', 'o:data': { html: '<p>World</p>' } }]);
  const ed = await H.openEditor(pageId);
  const { w, d, V } = ed, u = H.ui(ed);

  // ---- ready-made sections
  [...d.querySelectorAll('[role="tab"]')].find(b => b.textContent === 'Templates').click();
  const sel = d.getElementById('tplType'); sel.value = 'sections'; sel.dispatchEvent(new w.Event('change'));
  t.ok(d.querySelectorAll('.tpl-card').length === 10 && [...d.querySelectorAll('.tpl-card button')].every(b => b.textContent === 'Add this section'), 'ten ready-made sections, each with "Add this section"');
  w.VPB.app.addAt(0);
  [...d.querySelectorAll('.tpl-card')].find(c => /Quote band/.test(c.textContent)).querySelector('button').click();
  t.ok(V.state.sections[0].bg === '#F1EEE4' && V.state.sections[0].cols[0].blocks[0].layout === 'specialQuote' && V.state.sections.length === 2, 'added where the + was, without replacing the page');

  // ---- typed text: formatting bar, links, pictures
  const textBlock = V.state.sections[1].cols[0].blocks[0];
  u.select(textBlock);
  t.ok(u.side().querySelectorAll('.rt-panel .rt-btn').length >= 7 && !u.el(textBlock).querySelector('.rt-bar'), 'text formatting is in the sidebar, not over the text');
  const ok = w.VPB.textTools.SAFE_URL;
  t.ok(['https://example.org', '/s/dhs/page/about', 'mailto:a@b.org', '#top'].every(x => ok.test(x)) && !['javascript:alert(1)', '//evil.example', 'data:text/html,x'].some(x => ok.test(x)), 'link addresses checked like the server does');
  w.prompt = () => 'A test picture';
  w.VPB.refs.assets[fx.asset] = w.VPB.refs.assets[fx.asset] || { id: fx.asset, title: 'Banner', thumb: '/files/asset/x.png' };
  w.VPB.textTools.insertImage(textBlock, w.VPB.refs.assets[fx.asset]);
  t.ok(/<img [^>]*alt="A test picture"/.test(textBlock.data.html) && /Unsaved/.test(u.status()), 'a picture from Assets goes into the text, with its description');
  u.select(textBlock);
  t.ok([...u.side().querySelectorAll('button')].some(b => b.textContent === 'Insert an image\u2026'), 'the sidebar offers "Insert an image"');

  // ---- columns on smaller screens
  const sec = w.VPB.model.newSection([6, 6]);
  sec.cols[0].blocks.push(w.VPB.model.newBlock('html', '<p>Left</p>'));
  sec.cols[1].blocks.push(w.VPB.model.newBlock('html', '<p>Right</p>'));
  V.state.sections.push(sec);
  w.VPB.app.renderAll();
  u.select({ kind: 'sec', id: sec.id });
  t.ok(/Smaller screens/.test(u.side().textContent) && /On tablets/.test(u.side().textContent) && /On phones/.test(u.side().textContent), 'section settings: smaller screens');
  [...u.side().querySelectorAll('.segw')].find(g => g.getAttribute('aria-label') === 'On tablets').querySelector('button:nth-child(2)').click();
  [...u.side().querySelectorAll('.segw')].find(g => g.getAttribute('aria-label') === 'On phones').querySelector('button:nth-child(2)').click();
  t.eq(sec.resp, { tablet: 'two', phone: 'two' }, 'two per row on tablets and phones');
  d.querySelector('[data-dev="tablet"]').click();
  const inner = d.querySelector(`.sec[data-id="${sec.id}"] .sec-inner`);
  t.ok(/repeat\(2/.test(inner.getAttribute('style')), 'the Tablet view shows two per row');
  d.querySelector('[data-dev="desktop"]').click();

  // ---- Preview on site (before saving)
  t.ok(d.getElementById('sitePreviewBtn'), '"Preview on site" button');
  w.VPB.sitePreview.open(); await H.sleep(2500);
  const frame = d.querySelector('.sp-overlay iframe.sp-frame');
  t.ok(frame && /vpb-preview-/.test(frame.getAttribute('src')), 'shows the page on the site in a frame: ' + (frame && frame.getAttribute('src')));
  const previewUrl = frame.getAttribute('src');
  const asAdmin = await (await fetch(H.BASE + previewUrl, { headers: { Cookie: H.readCookie() } })).text();
  t.ok(/A test picture/.test(asAdmin) && /vpb-tablet-two/.test(asAdmin), 'the preview has the unsaved changes');
  t.ok((await fetch(H.BASE + previewUrl)).status >= 400, 'visitors can\u2019t open the preview');
  const hub = await (await fetch(`${H.BASE}/admin/visual-builder/dhs?q=vpb-preview`, { headers: { Cookie: H.readCookie() } })).text();
  t.ok(/No pages match/.test(hub), 'the preview copy isn\u2019t in the page list');
  const copyTitle = (await H.api('GET', 'site_pages?slug=vpb-preview-' + pageId))[0]['o:title'];
  t.ok(/Preview copy of .* \(not published\)/.test(copyTitle), 'the private copy is named so it isn\u2019t mistaken for the page: ' + copyTitle);
  d.getElementById('sitePreviewClose').click();
  t.ok(!d.querySelector('.sp-overlay'), 'Back to editing closes it');
  await H.sleep(1200);
  t.ok(!(await H.api('GET', 'site_pages?slug=vpb-preview-' + pageId)).length, 'and removes the private copy');
  w.VPB.sitePreview.open(); await H.sleep(2500); // make one again, for the save check below

  // ---- save: smaller-screen classes on the site; preview copy removed
  const res = await H.saveBlocks(ed, V.exportBlocks());
  const pub = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${res.body.page['o:slug']}`)).text();
  t.ok(/vpb-tablet-two vpb-phone-two/.test(pub) && /vpb-tablet-stack vpb-phone-stack/.test(pub), 'public sections carry their smaller-screen setting');
  const left = await H.api('GET', 'site_pages?slug=vpb-preview-' + pageId);
  t.ok(!left.length, 'saving removes the preview copy');
  // Deleting a page deletes a leftover preview copy too.
  const gone = await H.createPage('Delete me', [{ 'o:layout': 'html', 'o:data': { html: '<p>x</p>' } }]);
  const siteId = (await H.api('GET', 'sites?slug=' + H.SITE))[0]['o:id'];
  await H.api('POST', 'site_pages', { 'o:site': { 'o:id': siteId }, 'o:title': 'copy', 'o:slug': 'vpb-preview-' + gone, 'o:is_public': false });
  await H.api('DELETE', 'site_pages/' + gone);
  t.ok(!(await H.api('GET', 'site_pages?slug=vpb-preview-' + gone)).length, 'deleting a page also deletes its preview copy');
  t.ok(!ed.errs.length, 'no editor errors ' + ed.errs);
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
