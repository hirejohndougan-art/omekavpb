'use strict';
// v1.8.0: synced oral history: timed transcript lines, click to jump, follow
// along, search inside the interview, topic index, caption files.
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

const KEY = process.env.VPB_API_KEY || 'testkey', CRED = process.env.VPB_API_CRED || 'testcred';
const lit = v => [{ type: 'literal', property_id: 'auto', '@value': v }];
/** A short silent WAV file. */
function wav(seconds = 1) {
  const rate = 8000, n = rate * seconds, b = Buffer.alloc(44 + n);
  b.write('RIFF', 0); b.writeUInt32LE(36 + n, 4); b.write('WAVEfmt ', 8); b.writeUInt32LE(16, 16); b.writeUInt16LE(1, 20); b.writeUInt16LE(1, 22);
  b.writeUInt32LE(rate, 24); b.writeUInt32LE(rate, 28); b.writeUInt16LE(1, 32); b.writeUInt16LE(8, 34); b.write('data', 36); b.writeUInt32LE(n, 40); b.fill(128, 44);
  return b;
}
async function makeItem(title, transcript, files) {
  const props = await H.api('GET', 'properties?term=bibo:content');
  const data = { 'dcterms:title': lit(title), 'o:is_public': true, 'o:site': [{ 'o:id': (await H.api('GET', 'sites?slug=' + H.SITE))[0]['o:id'] }],
    'o:media': files.map((f, k) => ({ 'o:ingester': 'upload', file_index: String(k) })) };
  if (transcript) data['bibo:content'] = [{ type: 'literal', property_id: props[0]['o:id'], '@value': transcript }];
  const fd = new FormData();
  fd.append('data', JSON.stringify(data));
  files.forEach((f, k) => fd.append(`file[${k}]`, new Blob([f.body], { type: f.type }), f.name));
  const r = await (await fetch(`${H.BASE}/api/items?key_identity=${KEY}&key_credential=${CRED}`, { method: 'POST', body: fd })).json();
  if (!r['o:id']) throw new Error('item not made: ' + JSON.stringify(r.errors || r));
  return r['o:id'];
}

(async () => {
  const t = H.suite('special blocks: synced oral history');
  const transcript = 'Interview with Mrs. Example.\n[00:00:05] I grew up on a farm by the bayou.\n[00:01:10] Then the storm came in 1943.\nWe lost the barn that night.\n(02:30) Later I worked at the depot, sorting strawberries.\n1:02:15 Strawberries again, for the last time.';
  const itemId = await makeItem('Synced interview', transcript, [{ body: wav(), type: 'audio/wav', name: 'interview.wav' }]);
  const prev = H.setting('disable_file_validation');
  H.setSetting('disable_file_validation', '"1"');
  let capItem;
  try {
    const vtt = 'WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nHello from the caption file.\n\n00:00:04.500 --> 00:00:08.000\n<v Speaker>Second line</v> &amp; more.\n';
    capItem = await makeItem('Captioned interview', '', [{ body: wav(), type: 'audio/wav', name: 'talk.wav' }, { body: Buffer.from(vtt), type: 'text/vtt', name: 'talk.vtt' }]);
  } finally { H.setSetting('disable_file_validation', prev || '"0"'); }

  const pageId = await H.createPage('Synced oral history', [
    { 'o:layout': 'specialOralHistory', 'o:data': { item_id: itemId, topics: [
      { title: 'The 1943 storm', time: '1:10', summary: 'The night the barn was lost.', keywords: 'storm, barn' },
      { title: 'Growing up', time: '0:05' }, { title: 'Bad time', time: 'soon' }
    ] } },
    { 'o:layout': 'specialOralHistory', 'o:data': { item_id: capItem, transcript_source: 'caption' } }
  ]);
  const page = await H.api('GET', 'site_pages/' + pageId);
  const html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${page['o:slug']}`)).text();
  const d = new JSDOM(html).window.document;
  t.ok(!/Fatal error|Warning:|Notice:/.test(html), 'renders without PHP errors');
  const [a, b] = d.querySelectorAll('.special-blocks-oral-history, [class*="oral-history"]').length >= 2 ? d.querySelectorAll('.special-blocks-oh-synced') : [null, null];
  t.ok(a && b, 'both blocks are synced');
  const cues = [...a.querySelectorAll('.special-blocks-oh-cue')];
  t.eq(cues.map(c => c.getAttribute('data-start')), ['0', '5', '70', '150', '3735'], 'times read from [00:00:05], [00:01:10], (02:30), and 1:02:15; text before the first time starts at 0');
  t.ok(/We lost the barn/.test(cues[2].textContent) && cues[2].querySelector('button.special-blocks-oh-time').textContent === '1:10', 'lines without a time stay with the line before; each line has a time button');
  const topics = [...a.querySelectorAll('.special-blocks-oh-topics li')].map(li => li.querySelector('button').textContent.replace(/\s+/g, ' ').trim());
  t.eq(topics, ['0:05 Growing up', '1:10 The 1943 storm'], 'topic index in time order; a topic without a real time is left out');
  t.ok(/barn was lost/.test(a.querySelector('.special-blocks-oh-topics').textContent) && /storm, barn/.test(a.textContent), 'topic summary and keywords');
  const bc = [...b.querySelectorAll('.special-blocks-oh-cue')];
  t.ok(bc.length === 2 && bc[0].getAttribute('data-start') === '1' && /Second line & more\./.test(bc[1].textContent) && b.getAttribute('data-caption-url'), 'a .vtt caption file on the item gives the timed lines (tags removed)');

  // Run the sync script.
  const live = new JSDOM(html, { runScripts: 'outside-only', pretendToBeVisual: true });
  const w = live.window;
  const box = w.document.querySelector('.special-blocks-oh-synced');
  const player = box.querySelector('audio');
  let now = 0;
  Object.defineProperty(player, 'currentTime', { get: () => now, set: v => { now = v; }, configurable: true });
  player.play = () => Promise.resolve();
  w.eval(fs.readFileSync(path.join(H.MODULE_DIR, 'asset/special-blocks/js/oral-history.js'), 'utf8'));
  await H.sleep(200);
  t.ok(!box.querySelector('.special-blocks-oh-tools').hidden, 'search and Follow along appear');
  now = 75; player.dispatchEvent(new w.Event('timeupdate'));
  const cur = () => [...box.querySelectorAll('.special-blocks-oh-cue')].findIndex(c => c.classList.contains('is-current'));
  t.ok(cur() === 2, 'while playing, the line being spoken is highlighted');
  box.querySelectorAll('.special-blocks-oh-cue')[3].querySelector('button').click();
  t.ok(now === 150 && cur() === 3, 'clicking a line\u2019s time jumps the recording there');
  box.querySelector('.special-blocks-oh-topics button').click();
  t.ok(now === 5, 'clicking a topic jumps there');
  const s = box.querySelector('.special-blocks-oh-search input');
  s.value = 'strawberries'; s.dispatchEvent(new w.Event('input'));
  t.ok(box.querySelectorAll('.special-blocks-oh-cue.is-hit').length === 2 && box.querySelectorAll('mark').length === 2 && /2 places mention this/.test(box.querySelector('.special-blocks-oh-count').textContent), 'search marks matches and counts them');
  s.value = 'zeppelin'; s.dispatchEvent(new w.Event('input'));
  t.ok(!box.querySelector('mark') && /Not mentioned/.test(box.querySelector('.special-blocks-oh-count').textContent), 'no matches says so');
  live.window.close();

  const ed = await H.openEditor(pageId);
  ed.w.VPB.store.state.sections.length && t.ok(!ed.errs.length, 'no editor errors');
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
