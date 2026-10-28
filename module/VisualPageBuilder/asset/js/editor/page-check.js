/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Check page: looks over the page in the editor for common problems and
 * lists them, each with "Show me" to select the block. Nothing is changed and
 * saving is never blocked. Everything is checked in the editor itself.
 */
(function (VPB) {
'use strict';

var h = VPB.dom.h, state = VPB.store.state;
var VAGUE = /^(click here|here|read more|more|link|this|this link|learn more)$/i;

function parse(html) { var t = document.createElement('template'); t.innerHTML = html || ''; return t.content; }
function slug(s) { return VPB.model.slug(s); }

/** All problems: [{level: 'fix'|'tip', text, target: {kind, id} | null}] */
function run() {
  var out = [], all = [], anchors = {}, headingSeq = [];
  state.sections.forEach(function (sec, si) {
    if (sec.anchor) anchors[slug(sec.anchor)] = (anchors[slug(sec.anchor)] || 0) + 1;
    var blocks = [];
    sec.cols.forEach(function (c) { c.blocks.forEach(function (b) { blocks.push(b); all.push({ b: b, sec: sec }); if (b.anchor) anchors[slug(b.anchor)] = (anchors[slug(b.anchor)] || 0) + 1; }); });
    if (!blocks.length) out.push({ level: 'tip', text: 'Section ' + (si + 1) + ' is empty. It won\u2019t show on the site; you can remove it.', target: { kind: 'sec', id: sec.id } });
  });
  Object.keys(anchors).forEach(function (a) { if (anchors[a] > 1) out.push({ level: 'fix', text: 'The link name #' + a + ' is used ' + anchors[a] + ' times. Each spot needs its own.', target: null }); });
  // Every id the site will give (headings, Table links, older link names), as Anchors::plan does.
  var used = {};
  VPB.toc.plan().forEach(function (s) { used[s.id] = 1; });
  all.forEach(function (x) {
    if (x.b.layout !== 'html') return;
    [].forEach.call(parse(x.b.data && x.b.data.html).querySelectorAll('h1,h2,h3,h4,h5,h6'), function (hd) {
      headingSeq.push({ level: +hd.tagName[1], text: hd.textContent.trim(), b: x.b });
      if (hd.id) used[hd.id] = 1;
    });
  });
  var footnotes = 0, hasFootList = false;
  all.forEach(function (x) {
    var b = x.b, d = b.data || {}, tgt = { kind: 'block', id: b.id };
    if (b.layout === 'html') {
      var frag = parse(d.html);
      [].forEach.call(frag.querySelectorAll('img'), function (img) {
        if (!img.hasAttribute('alt')) out.push({ level: 'fix', text: 'A picture in a text block has no description for people who can\u2019t see it.', target: tgt });
      });
      [].forEach.call(frag.querySelectorAll('a'), function (a) {
        var href = (a.getAttribute('href') || '').trim(), text = a.textContent.trim();
        if (!href || href === 'https://' || href === 'http://') out.push({ level: 'fix', text: 'A link (\u201C' + (text || 'no text') + '\u201D) doesn\u2019t go anywhere.', target: tgt });
        else if (href[0] === '#' && href.length > 1 && !used[href.slice(1)] && !/^#vpb-fn/.test(href)) out.push({ level: 'fix', text: 'A link goes to ' + href + ', but nothing on this page has that name (a heading, Table link, or link name).', target: tgt });
        if (!text && !a.querySelector('img[alt]:not([alt=""])')) out.push({ level: 'fix', text: 'A link has no words, so screen readers can\u2019t say where it goes.', target: tgt });
        else if (VAGUE.test(text)) out.push({ level: 'tip', text: 'A link just says \u201C' + text + '\u201D. Words saying where it goes help everyone (and search engines).', target: tgt });
      });
      footnotes += frag.querySelectorAll('sup.vpb-footnote').length;
    }
    if (b.layout === 'specialFootnotes') hasFootList = true;
    if (b.layout === 'specialSharedSection' && !(parseInt(d.section, 10) > 0)) out.push({ level: 'fix', text: 'A Shared section block has no shared section chosen.', target: tgt });
    if (b.layout === 'specialJumpLinks' && !VPB.toc.entries(VPB.toc.upgradeData(d).levels || 'h3').shown.length) out.push({ level: 'fix', text: 'The Table of contents has nothing to list yet. Add headings to your text (Heading 2, 3, or 4), or a Table link.', target: tgt });
    if (b.layout === 'specialTableLink' && !String(d.name || '').trim()) out.push({ level: 'fix', text: 'A Table link has no name, so it isn\u2019t listed in the Table of contents.', target: tgt });
    if (b.layout === 'specialMap' && Array.isArray(d.places) && d.places.some(function (p) { return isNaN(parseFloat(p.lat)) || isNaN(parseFloat(p.lng)); })) out.push({ level: 'fix', text: 'A place on a map has no position, so it won\u2019t show.', target: tgt });
    if (b.layout === 'specialStoryMap' && Array.isArray(d.stops) && d.stops.some(function (s) { return !String(s.date || '').match(/\d{4}/) && !s.item_id; })) out.push({ level: 'fix', text: 'A story map stop has no date (or linked item with one), so it won\u2019t show.', target: tgt });
    if (b.layout === 'specialStoryTimeline' && Array.isArray(d.events) && d.events.some(function (e) { return !String(e.date || '').match(/\d/) && !e.item_id; })) out.push({ level: 'fix', text: 'A story timeline event has no date (or linked item with one), so it won\u2019t show.', target: tgt });
    if (b.layout === 'specialArchiveQuiz' && Array.isArray(d.questions) && d.questions.some(function (q) { return String(q.choices || '').split('\n').filter(function (c) { return c.trim(); }).length < 2; })) out.push({ level: 'fix', text: 'A quiz question has fewer than two choices, so it won\u2019t show.', target: tgt });
  });
  if (hasFootList && !footnotes) out.push({ level: 'tip', text: 'There\u2019s a Footnotes block but no footnotes yet (formatting bar \u203A footnote button).', target: null });
  // Headings: no h1 (the page title is the h1), and no skipped levels.
  var prev = 1;
  headingSeq.forEach(function (x) {
    if (x.level === 1) out.push({ level: 'fix', text: 'The heading \u201C' + x.text + '\u201D is a top-level heading; the page title already is one. Use Heading 2.', target: { kind: 'block', id: x.b.id } });
    else if (x.level > prev + 1) out.push({ level: 'fix', text: 'The heading \u201C' + x.text + '\u201D skips a level (Heading ' + prev + ' to Heading ' + x.level + '). Screen-reader users navigate by these.', target: { kind: 'block', id: x.b.id } });
    prev = x.level;
  });
  return out;
}

function open() {
  var issues = run();
  var fixes = issues.filter(function (i) { return i.level === 'fix'; }).length;
  var box = h('div', { class: 'lv-overlay', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Check page' });
  var list = h('ul', { class: 'pc-list' });
  issues.forEach(function (i) {
    var li = h('li', { class: 'pc-' + i.level }, [h('span', { class: 'pc-tag', text: i.level === 'fix' ? 'Fix' : 'Tip' }), h('span', { text: i.text })]);
    if (i.target) li.appendChild(h('button', { type: 'button', class: 'btn small', text: 'Show me', onclick: function () { close(); VPB.app.select(i.target); var el = document.querySelector('[data-id="' + i.target.id + '"]'); if (el && el.scrollIntoView) el.scrollIntoView({ block: 'center' }); } }));
    list.appendChild(li);
  });
  function close() { box.remove(); document.removeEventListener('keydown', onKey, true); }
  function onKey(e) { if (e.key === 'Escape') { e.stopPropagation(); close(); } }
  box.appendChild(h('div', { class: 'lv-card pt-card' }, [
    h('h2', { text: 'Check page' }),
    h('p', { role: 'status', text: !issues.length ? 'No problems found.' : fixes + ' to fix, ' + (issues.length - fixes) + ' tip' + (issues.length - fixes === 1 ? '' : 's') + '.' }),
    issues.length ? list : null,
    h('div', { class: 'lv-actions' }, [h('button', { type: 'button', class: 'btn primary', text: 'Close', onclick: close })])
  ]));
  document.body.appendChild(box);
  document.addEventListener('keydown', onKey, true);
  box.querySelector('button').focus();
}

VPB.pageCheck = { run: run, open: open };
})(window.VPB);
