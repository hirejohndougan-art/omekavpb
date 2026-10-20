'use strict';
// v1.3.0: every Special Block's title can be changed or hidden, and typed
// text gets a full web-safe formatting bar whose styles survive saving.
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

(async () => {
  const t = H.suite('titles and text formatting');
  const pageId = await H.createPage('Titles and text', [
    { 'o:layout': 'specialRandomItems', 'o:data': { heading: 'Renamed title', count: 2 } },
    { 'o:layout': 'specialRecentItems', 'o:data': { heading: 'Hidden title', show_heading: false, count: 2 } },
    { 'o:layout': 'specialQuote', 'o:data': { heading: 'A quote title', text: 'Quote body' } },
    { 'o:layout': 'specialLogo', 'o:data': {} },
    { 'o:layout': 'html', 'o:data': { html: '<h2 style="font-family: Georgia, serif; color: #8a3324;">Styled heading</h2><p><u>Under</u> <s>struck</s> <span style="font-size: x-large; background-color: #fff3a3;">big</span></p><ol><li>One</li></ol><blockquote><p>Quoted</p></blockquote><p style="text-align: center;">Centered</p>' } }
  ]);
  const ed = await H.openEditor(pageId);
  const { w, V } = ed, u = H.ui(ed);

  // Title settings come first, for every Special Block that can have one.
  const schemaFor = n => ed.cfg.layouts.find(l => l.name === n).schema.fields.map(f => f.key);
  const special = ed.cfg.layouts.filter(l => l.name.startsWith('special'));
  const without = special.filter(l => !l.schema.fields.some(f => f.key === 'show_heading')).map(l => l.name).sort();
  t.eq(without, ['specialAnnouncement', 'specialBreadcrumbs', 'specialCopyright', 'specialLogo', 'specialSiteTitle', 'specialTableLink'], 'every Special Block has "Show the title" except those whose title is their content');
  t.eq(schemaFor('specialQuote').slice(0, 2), ['heading', 'show_heading'], 'title settings first (also on blocks that had none before)');
  u.select(V.state.sections[0].cols[0].blocks.find(b => b.layout === 'specialRandomItems'));
  t.ok(/Show the title/.test(u.side().textContent) && [...u.side().querySelectorAll('label')].some(l => l.textContent === 'Title'), 'the sidebar shows Title and Show the title');

  // Formatting bar.
  const textBlock = V.state.sections.flatMap(s => s.cols.flatMap(c => c.blocks)).find(b => b.layout === 'html');
  u.select(textBlock);
  const bar = u.side().querySelector('.rt-panel');
  const labels = [...bar.querySelectorAll('[aria-label]')].map(x => x.getAttribute('aria-label'));
  t.ok(['Style', 'Font', 'Size', 'Bold (Ctrl+B)', 'Italic (Ctrl+I)', 'Underline (Ctrl+U)', 'Strikethrough', 'Text color', 'Highlight color', 'Bulleted list', 'Numbered list', 'Quote', 'Align left', 'Align center', 'Align right', 'Justify', 'Link', 'Remove link', 'Add a footnote', 'Clear formatting'].every(l => labels.includes(l)), 'the sidebar\u2019s text formatting has style, font, size, B/I/U/S, colors, lists, quote, alignment (left, center, right, justify), links, footnote, clear');
  t.ok(['Align left', 'Align center', 'Align right', 'Justify'].every(l => bar.querySelector(`[aria-label="${l}"] svg path`)), 'alignment uses the usual line icons');
  t.ok(w.VPB.textTools.FONTS.length === 10 && w.VPB.textTools.FONTS.every(f => /serif|monospace|inherit/.test(f[1])), 'ten web-safe fonts, each with a fallback');
  t.ok([...bar.querySelector('select[aria-label="Style"]').options].map(o => o.textContent).join() === 'Style,Paragraph,Heading 2,Heading 3,Heading 4,Quote', 'styles: paragraph, headings, quote');

  // Public page, with Omeka's HTML cleaner on: styles and tags survive.
  const prev = await H.setting('use_htmlpurifier');
  await H.setSetting('use_htmlpurifier', '1');
  try {
    const res = await H.saveBlocks(ed, V.exportBlocks());
    const html = await (await fetch(`${H.BASE}/s/${H.SITE}/page/${res.body.page['o:slug']}`)).text();
    const doc = new JSDOM(html).window.document;
    t.ok(/Renamed title/.test(html) && !/Hidden title/.test(html), 'changed title shown; hidden title left out');
    t.ok(/A quote title/.test(html) && html.indexOf('A quote title') < html.indexOf('Quote body'), 'blocks that had no title get it above them');
    const h2 = [...doc.querySelectorAll('h2')].find(x => x.textContent === 'Styled heading');
    t.ok(h2 && /Georgia/.test(h2.getAttribute('style') || '') && /color/.test(h2.getAttribute('style') || ''), 'font and color survive the HTML cleaner: ' + (h2 && h2.getAttribute('style')));
    t.ok(/font-size:\s*x-large/.test(html) && /background-color/.test(html) && doc.querySelector('ol li') && doc.querySelector('blockquote') && /text-align:\s*center/.test(html), 'size, highlight, numbered list, quote, and alignment survive');
    t.ok(/<u>Under<\/u>|text-decoration:\s*underline/.test(html) && /<s>struck<\/s>|line-through/.test(html), 'underline and strikethrough survive');
  } finally {
    await H.setSetting('use_htmlpurifier', prev);
  }
  t.ok(!ed.errs.length, 'no editor errors ' + ed.errs);
  t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
