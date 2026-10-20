'use strict';
// v1.0.7: "+" bars before, between, and after sections, always shown while
// editing: click one, then pick a section or block, and it goes there.
const H = require('../lib/harness');

(async () => {
  const t = H.suite('insert bars');
  const pageId = await H.createPage('Insert bars', [{ 'o:layout': 'html', 'o:data': { html: '<p>First</p>' } }, { 'o:layout': 'html', 'o:data': { html: '<p>Second</p>' } }]);
  const ed = await H.openEditor(pageId);
  const { w, d, V } = ed;
  // Two sections, so the page has a bar above, between, and below.
  V.state.sections.push(w.VPB.model.sectionWith(V.state.sections[0].cols[0].blocks.pop()));
  w.VPB.app.renderAll();
  const bars = () => [...d.querySelectorAll('#secs > .ins')];
  t.eq(bars().map(b => b.getAttribute('data-index')), ['0', '1', '2'], 'a + bar above, between, and below the sections');
  t.ok(bars().every(b => b.querySelector('button.ins-plus') && b.querySelector('.ins-plus').textContent === '+'), 'each bar has a + in a box');

  // Click the middle +, then pick a section preset.
  bars()[1].querySelector('.ins-plus').click();
  t.ok(V.state.insertAt === 1 && bars()[1].classList.contains('chosen') && /goes where the \+ is/.test(d.getElementById('toast').textContent), 'clicking + marks the spot and explains what to do');
  t.ok(d.querySelector('.pal-tile, [data-key^="sec:"], .secpal') , 'the Blocks panel is open');
  const secTile = [...d.querySelectorAll('.pal-label')].find(x => /^2 columns section$/i.test(x.textContent)) || [...d.querySelectorAll('.pal-label')].find(x => /section$/.test(x.textContent));
  secTile.closest('button, [role="button"], .pal-tile').click();
  t.ok(V.state.sections.length === 3 && V.state.sections[1].cols.every(c => !c.blocks.length) && V.state.insertAt === null, 'the new section goes between the two');

  // Click the top +, then pick a paragraph: it starts a new section there.
  bars()[0].querySelector('.ins-plus').click();
  const para = [...d.querySelectorAll('.pal-label')].find(x => x.textContent === 'Paragraph');
  para.closest('button, [role="button"], .pal-tile').click();
  t.ok(V.state.sections.length === 4 && V.state.sections[0].cols[0].blocks[0].layout === 'html', 'a block picked after + starts a new section at that spot');

  // Escape cancels a chosen spot.
  bars()[2].querySelector('.ins-plus').click();
  d.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
  t.ok(V.state.insertAt === null && !d.querySelector('.ins.chosen'), 'Escape cancels');

  // Hidden in Preview, back after.
  d.getElementById('previewBtn') ? d.getElementById('previewBtn').click() : null;
  if (V.state.preview) t.ok(!bars().length, 'no bars in Preview');
  t.ok(!ed.errs.length, 'no editor errors ' + ed.errs);
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
