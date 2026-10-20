'use strict';
// The Table of contents preview lists the page's real sub-pages from the
// site's navigation, to the chosen depth.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('table of contents preview');
  const site = (await H.api('GET', 'sites?slug=' + H.SITE))[0];
  const original = site['o:navigation'];
  const child1 = await H.createPage('Depot history', [{ 'o:layout': 'pageTitle', 'o:data': {} }]);
  const child2 = await H.createPage('Track maps', [{ 'o:layout': 'pageTitle', 'o:data': {} }]);
  const grand = await H.createPage('1911 map', [{ 'o:layout': 'pageTitle', 'o:data': {} }]);
  const parent = await H.createPage('Railroad exhibit', [{ 'o:layout': 'tableOfContents', 'o:data': { depth: '1' } }]);
  const nav = (id, links = [], label = '') => ({ type: 'page', data: { id, label }, links });
  await H.api('PATCH', 'sites/' + site['o:id'], { 'o:navigation': original.concat([nav(parent, [nav(child1), nav(child2, [nav(grand)], 'Maps of the line')])]) });
  try {
    let ed = await H.openEditor(parent), u = H.ui(ed);
    const toc = () => [...u.el(u.blk('tableOfContents')).querySelectorAll('.pv-link')].map(x => x.textContent);
    t.eq(toc(), ['Depot history', 'Maps of the line'], 'depth 1: the page\u2019s sub-pages, using menu labels');
    u.select(u.blk('tableOfContents')); u.setVal('#f-dep', '2');
    t.eq(toc(), ['Depot history', 'Maps of the line', '1911 map'], 'depth 2 adds sub-sub-pages');
    const lonely = await H.createPage('Not in menu', [{ 'o:layout': 'tableOfContents', 'o:data': {} }]);
    ed = await H.openEditor(lonely); u = H.ui(ed);
    t.ok(/No pages are under this one/.test(u.el(u.blk('tableOfContents')).textContent), 'a page outside the menu says so');
  } finally {
    await H.api('PATCH', 'sites/' + site['o:id'], { 'o:navigation': original });
  }
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
