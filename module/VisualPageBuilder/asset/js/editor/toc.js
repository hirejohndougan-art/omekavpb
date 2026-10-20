/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * The page's spots for the Table of contents, worked out in the editor the
 * way Anchors::plan() and JumpLinks::entries() do on the site, so the
 * editor's preview, its "Found on this page" list, link suggestions, and
 * Check page all agree with the public page.
 *
 * Spots, in reading order (section by section, column by column):
 * - named: a section or block with an older Link name (before v1.14.0)
 * - link: a Table link block (name + level 1-3)
 * - heading: a Heading 2, 3, or 4 typed in a text block (level 1-3). A
 *   heading can be left out (data-toc-hide) or renamed (data-toc-name);
 *   in the editor those live on the heading itself, and are saved in the
 *   text block's layout data, vpb_toc (see model.js).
 */
(function (VPB) {
'use strict';

var obj = VPB.util.obj;
// "Headings to include" → deepest level listed (0: only Table links).
var DEPTH = { h2: 1, h3: 2, h4: 3, none: 0 };

function parse(html) { var t = document.createElement('template'); t.innerHTML = String(html || ''); return t.content; }
function slug(s) { return VPB.model.slug(s); }
function clean(s) { return String(s || '').replace(/\s+/g, ' ').trim(); }

/** Heading 2–4s in some HTML, in order: [{index, level (2-4), text, own, hide, name}]. */
function headingsIn(html) {
  return [].map.call(parse(html).querySelectorAll('h2,h3,h4'), function (el, i) {
    return { index: i, level: +el.tagName[1], text: clean(el.textContent), own: el.id || '', hide: el.hasAttribute('data-toc-hide'), name: clean(el.getAttribute('data-toc-name')) };
  });
}
/** A Table link's level, 1-3. */
function linkLevel(b) { var n = parseInt(obj(b.data).level, 10); return n >= 1 && n <= 3 ? n : 1; }

/** Every spot on the page, in reading order, with the id the site will give it. */
function plan() {
  var used = {}, order = [];
  VPB.store.state.sections.forEach(function (sec) {
    order.push({ item: sec, block: null, sec: sec });
    sec.cols.forEach(function (c) { c.blocks.forEach(function (b) { order.push({ item: b, block: b, sec: sec }); }); });
  });
  function unique(base) { var id = base; for (var n = 2; used[id]; n++) id = base + '-' + n; used[id] = 1; return id; }
  var named = new Map(), links = new Map(), heads = new Map();
  order.forEach(function (o) {
    var id = o.item.anchor ? slug(o.item.anchor) : '';
    if (!id || used[id]) return;
    used[id] = 1;
    named.set(o.item, { kind: 'named', id: id, label: clean(o.item.anchorLabel) || (id.charAt(0).toUpperCase() + id.slice(1).replace(/-/g, ' ')), level: 1 });
  });
  order.forEach(function (o) {
    if (o.block && o.block.layout === 'html') headingsIn(obj(o.block.data).html).forEach(function (hd) { if (hd.own) used[hd.own] = 1; });
  });
  order.forEach(function (o) {
    if (!o.block || o.block.layout !== 'specialTableLink') return;
    var name = clean(obj(o.block.data).name);
    links.set(o.block, { kind: 'link', id: unique(slug(name) || 'section'), label: name, level: linkLevel(o.block) });
  });
  order.forEach(function (o) {
    if (!o.block || o.block.layout !== 'html') return;
    heads.set(o.block, headingsIn(obj(o.block.data).html).filter(function (hd) { return hd.text || hd.own; }).map(function (hd) {
      return { kind: 'heading', id: hd.own || unique(slug(hd.text) || 'section'), label: hd.name || hd.text, text: hd.text, name: hd.name, hide: hd.hide, level: hd.level - 1, tag: 'H' + hd.level, index: hd.index };
    }));
  });
  var out = [];
  order.forEach(function (o) {
    var target = o.block ? { kind: 'block', id: o.block.id } : { kind: 'sec', id: o.sec.id };
    function add(spot) { out.push(Object.assign(spot, { block: o.block, item: o.item, target: target })); }
    if (named.has(o.item)) add(named.get(o.item));
    if (o.block && links.has(o.block)) add(links.get(o.block));
    if (o.block && heads.has(o.block)) heads.get(o.block).forEach(add);
  });
  return out;
}

/**
 * What a Table of contents with these "Headings to include" shows: every
 * spot, each with `listed` (and `why` when not), plus `shown`: the listed
 * ones with their nesting level and number ("1.2"), as on the site.
 */
function entries(levels) {
  var depth = Object.prototype.hasOwnProperty.call(DEPTH, levels) ? DEPTH[levels] : 2;
  var all = plan();
  all.forEach(function (s) {
    s.listed = true;
    if (s.kind === 'heading') {
      if (s.hide) { s.listed = false; s.why = 'left out'; } else if (s.level > depth) { s.listed = false; s.why = depth ? 'deeper than chosen' : 'headings not included'; }
    } else if (s.kind === 'link') {
      if (!s.label) { s.listed = false; s.why = 'no name yet'; } else if (depth && s.level > depth) { s.listed = false; s.why = 'deeper than chosen'; }
    }
  });
  var prev = 0, counters = [0, 0, 0, 0];
  var shown = all.filter(function (s) { return s.listed; }).map(function (s) {
    var level = Math.min(s.level, prev + 1);
    counters[level]++;
    for (var k = level + 1; k <= 3; k++) counters[k] = 0;
    prev = level;
    return { id: s.id, label: s.label, level: level, num: counters.slice(1, level + 1).join('.'), spot: s };
  });
  return { all: all, shown: shown };
}

/** Old "Jump links" settings (before v1.14.0) in today's terms, as JumpLinks::upgradeData(). */
function upgradeData(d) {
  d = Object.assign({}, obj(d));
  if (typeof d.source === 'string') d.levels = d.source === 'named' ? 'none' : (d.levels === 'h2h3' ? 'h3' : 'h2');
  else if (d.levels === 'h2h3') d.levels = 'h3';
  delete d.source;
  return d;
}

VPB.toc = { plan: plan, entries: entries, headingsIn: headingsIn, upgradeData: upgradeData, DEPTH: DEPTH };
})(window.VPB);
