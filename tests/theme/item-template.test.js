'use strict';
// Item templates (Theme Builder, Phase 2): a live Item template draws the item
// page with the item field blocks; back in draft, the item page falls back to
// Omeka's own item page. The item has no image, so the main image shows its
// placeholder.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('item templates');
  const cookie = H.readCookie();
  const titleProp = (await H.api('GET', 'properties?term=dcterms:title'))[0];
  const item = await H.api('POST', 'items', {
    'dcterms:title': [{ type: 'literal', '@value': 'Template test photograph', property_id: titleProp['o:id'] }]
  });
  const templateName = 'Test item template ' + Date.now();
  const itemUrl = H.BASE + '/s/' + H.SITE + '/item/' + item['o:id'];
  const themeUrl = H.BASE + '/admin/visual-builder/' + H.SITE + '/theme';
  const post = (body) => fetch(themeUrl, {
    method: 'POST', headers: { Cookie: cookie, 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(body), redirect: 'manual'
  });
  let templateId = null;
  try {
    const csrf = (await (await fetch(themeUrl, { headers: { Cookie: cookie } })).text()).match(/name="vpb_theme_csrf" value="([^"]+)"/)[1];
    const added = await post({ vpb_theme_csrf: csrf, vpb_action: 'add', screen: 'item', scope: 'all', value: '', name: templateName });
    t.ok(added.status < 400, 'the Theme Builder adds an Item template');

    // The new template's Edit link on the Theme Builder page carries its id.
    const listing = await (await fetch(themeUrl, { headers: { Cookie: cookie } })).text();
    const from = listing.indexOf(templateName);
    const edit = from < 0 ? null : listing.slice(from).match(/href="[^"]*\/visual-page-builder\/[^"/]+\/(\d+)"[^>]*>[^<]*Edit/);
    t.ok(!!edit, 'the new template is listed with an Edit link');
    templateId = edit ? Number(edit[1]) : null;

    if (!templateId) throw new Error('no template to edit');
    const ed = await H.openEditor(templateId);
    t.ok(ed.cfg.required.includes('itemTitle') && ed.cfg.required.includes('itemMainImage'), 'the editor knows Title and Main image are required');
    const saved = await H.saveBlocks(ed, [
      { 'o:layout': 'itemTitle', 'o:data': {} },
      { 'o:layout': 'itemMainImage', 'o:data': {} },
      { 'o:layout': 'itemDescription', 'o:data': {} },
      { 'o:layout': 'itemSubjects', 'o:data': {} }
    ]);
    t.ok(saved.body.ok, 'the item field blocks save on the template');

    const live = await post({ vpb_theme_csrf: csrf, vpb_action: 'status', template_id: templateId, status: 'live' });
    t.ok(live.status < 400, 'the template can be made live');

    let html = await (await fetch(itemUrl)).text();
    t.ok(html.includes('special-blocks-item-title') && html.includes('Template test photograph'), 'the live template draws the item title');
    t.ok(html.includes('special-blocks-item-placeholder'), 'an item with no image shows the placeholder');

    // Hiding Title on the template: the block stays, the public page drops it.
    const ed2 = await H.openEditor(templateId);
    const withHidden = ed2.V.exportBlocks().map(b => b['o:layout'] === 'itemTitle' ? Object.assign({}, b, { 'o:layout_data': Object.assign({}, b['o:layout_data'], { vpb_hidden: '1' }) }) : b);
    const hid = await H.saveBlocks(ed2, withHidden);
    t.ok(hid.body.ok, 'a required block can be hidden and saved');
    html = await (await fetch(itemUrl)).text();
    t.ok(!html.includes('special-blocks-item-title') && html.includes('special-blocks-item-placeholder'), 'a hidden block is not drawn on the public item page');
    const unhid = ed2.V.exportBlocks().map(b => b['o:layout'] === 'itemTitle' ? Object.assign({}, b, { 'o:layout_data': Object.assign({}, b['o:layout_data'], { vpb_hidden: undefined }) }) : b);
    await H.saveBlocks(ed2, unhid);
    await post({ vpb_theme_csrf: csrf, vpb_action: 'status', template_id: templateId, status: 'draft' });
    html = await (await fetch(itemUrl)).text();
    t.ok(!html.includes('special-blocks-item-title'), 'back in draft, the item page falls back to Omeka\'s own item page');
  } finally {
    if (templateId) await H.api('DELETE', 'site_pages/' + templateId).catch(() => {});
    await H.api('DELETE', 'items/' + item['o:id']).catch(() => {});
  }
  t.done();
})().catch(e => { console.error('CRASH', e); process.exitCode = 1; });
