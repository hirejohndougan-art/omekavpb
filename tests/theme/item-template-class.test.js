'use strict';
// Item templates by class (Theme Builder, Phase 2): an item of a class gets that
// class's template; other items get the template for all items.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('item templates by class');
  const cookie = H.readCookie();
  const titleProp = (await H.api('GET', 'properties?term=dcterms:title'))[0];
  const classId = (await H.api('GET', 'resource_classes?term=dctype:StillImage'))[0]['o:id'];
  const titled = (text, extra = {}) => H.api('POST', 'items', Object.assign({
    'dcterms:title': [{ type: 'literal', '@value': text, property_id: titleProp['o:id'] }]
  }, extra));
  const plain = await titled('Plain item for class test');
  const photo = await titled('Photo item for class test', { 'o:resource_class': { 'o:id': classId } });
  const themeUrl = H.BASE + '/admin/visual-builder/' + H.SITE + '/theme';
  const stamp = Date.now();
  const names = { all: 'Class test all ' + stamp, cls: 'Class test photos ' + stamp };
  const created = [];
  const post = (body) => fetch(themeUrl, {
    method: 'POST', headers: { Cookie: cookie, 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams(body), redirect: 'manual'
  });
  const idOf = async (name) => {
    const listing = await (await fetch(themeUrl, { headers: { Cookie: cookie } })).text();
    const from = listing.indexOf(name);
    const m = from < 0 ? null : listing.slice(from).match(/href="[^"]*\/visual-page-builder\/[^"/]+\/(\d+)"[^>]*>[^<]*Edit/);
    return m ? Number(m[1]) : null;
  };
  try {
    const csrf = (await (await fetch(themeUrl, { headers: { Cookie: cookie } })).text()).match(/name="vpb_theme_csrf" value="([^"]+)"/)[1];
    await post({ vpb_theme_csrf: csrf, vpb_action: 'add', screen: 'item', scope: 'all', value: '', name: names.all });
    await post({ vpb_theme_csrf: csrf, vpb_action: 'add', screen: 'item', scope: 'class', value: String(classId), name: names.cls });
    const allId = await idOf(names.all), clsId = await idOf(names.cls);
    t.ok(allId && clsId, 'both templates are added');
    if (!allId || !clsId) throw new Error('templates missing');
    created.push(allId, clsId);

    const blocksFor = (tag) => [{ 'o:layout': 'itemTitle', 'o:data': { tag } }, { 'o:layout': 'itemMainImage', 'o:data': {} }];
    t.ok((await H.saveBlocks(await H.openEditor(allId), blocksFor('h1'))).body.ok, 'the all-items template saves');
    t.ok((await H.saveBlocks(await H.openEditor(clsId), blocksFor('h2'))).body.ok, 'the class template saves');
    for (const id of [allId, clsId]) {
      await post({ vpb_theme_csrf: csrf, vpb_action: 'status', template_id: id, status: 'live' });
    }

    const plainHtml = await (await fetch(H.BASE + '/s/' + H.SITE + '/item/' + plain['o:id'])).text();
    const photoHtml = await (await fetch(H.BASE + '/s/' + H.SITE + '/item/' + photo['o:id'])).text();
    t.ok(/<h1 class="special-blocks-item-title">Plain item/.test(plainHtml), 'an item of another class gets the all-items template');
    t.ok(/<h2 class="special-blocks-item-title">Photo item/.test(photoHtml), 'an item of the class gets the class template');
  } finally {
    for (const id of created) await H.api('DELETE', 'site_pages/' + id).catch(() => {});
    for (const it of [plain, photo]) await H.api('DELETE', 'items/' + it['o:id']).catch(() => {});
  }
  t.done();
})().catch(e => { console.error('CRASH', e); process.exitCode = 1; });
