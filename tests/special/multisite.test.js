'use strict';
// The special_blocks_site_ids search filter, straight through Omeka's API.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('special blocks: multi-site filter');
  const fx = await H.specialFixtures();
  const titles = async (q) => (await H.api('GET', q)).map(x => x['o:title']).sort();
  const both = `special_blocks_site_ids[]=${fx.dhs}&special_blocks_site_ids[]=${fx.legacy}`;
  const all = await titles(`items?${both}&per_page=100`);
  t.ok(all.includes('Legacy portrait') && all.includes('Railroad photo 0') && all.includes('Undated letter'), 'items from both sites');
  t.eq(all.filter(x => x === 'Shared map').length, 1, 'an item on both sites appears once');
  t.eq(await titles(`items?special_blocks_site_ids[]=${fx.legacy}`), ['Legacy portrait', 'Shared map'], 'items from one site');
  t.eq(await titles(`item_sets?${both}`), ['Legacy photos', 'Railroad'], 'item sets from both sites');
  t.eq(await titles(`item_sets?special_blocks_site_ids[]=${fx.legacy}`), ['Legacy photos'], 'item sets from one site');
  const total = (await H.api('GET', 'items?per_page=100')).length;
  t.eq((await H.api('GET', 'items?special_blocks_site_ids[]=abc&per_page=100')).length, total, 'junk ids are ignored (no filter)');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
