/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * The page model: converts Omeka's page JSON into sections -> columns -> blocks
 * and back.
 *
 * Storage format (see README "How pages are stored"):
 * - A section is a `blockGroup` block. `o:data.span` is its number of child
 *   blocks; column widths live in its layout data under `vpb_section.cols`.
 * - Each child block carries Omeka's own `grid_column_position` and
 *   `grid_column_span` plus `vpb_col`, the index of its column. Children are
 *   interleaved row by row, so the page still reads sensibly without the module.
 *
 * In-memory shapes:
 *   section { id, cols: [col], ld, bg, pad, cls, align }
 *   col     { id, w (1-12), bg, blocks: [block] }
 *   block   { id, layout, data, att: [attachment], ld, bg, pad, align, cls }
 * `ld` holds any layout-data keys the editor doesn't manage, kept untouched.
 */
(function (VPB) {
'use strict';

var U = VPB.util, nid = U.nid, obj = U.obj, clone = U.clone, clamp = U.clamp;
var PAD_KEYS = VPB.PAD_KEYS, HEX_RE = VPB.HEX_RE;

/* ---------- import: Omeka page JSON -> sections ---------- */
function mkCol(w, blocks, bg, img) { return { id: nid('c'), w: w, bg: bg || null, img: img || null, blocks: blocks || [] }; }
/**
 * A background image setting, cleaned: { asset: id, size: 'cover'|'contain',
 * pos: 'top'|'center'|'bottom', overlay: bool }, or null.
 */
/**
 * How a section's columns arrange on smaller screens:
 * { tablet: 'stack'|'two'|'same', phone: 'stack'|'two' } (stack = one per row).
 */
/** "Visit Us!" → "visit-us" (as Anchors::slug on the server). */
function slug(text) {
  var t = String(text || '');
  if (t.normalize) t = t.normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
  t = t.toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 60).replace(/^-+|-+$/g, '');
  return t && /^[0-9]/.test(t) ? 'n-' + t : t;
}
function cleanResp(x) {
  x = obj(x);
  return {
    tablet: ['two', 'same'].indexOf(x.tablet) >= 0 ? x.tablet : 'stack',
    phone: x.phone === 'two' ? 'two' : 'stack'
  };
}
function cleanImg(x) {
  x = obj(x);
  var id = parseInt(x.asset, 10);
  if (!id) return null;
  return {
    asset: id,
    size: x.size === 'contain' ? 'contain' : 'cover',
    pos: ['top', 'bottom'].indexOf(x.pos) >= 0 ? x.pos : 'center',
    overlay: !!x.overlay
  };
}
function splitStyle(ld) {
  // Pull the settings the editor manages out of a layout_data object; the rest stays in ld.
  ld = clone(obj(ld));
  var out = { ld: ld, bg: null, img: null, pad: null, align: '', cls: '', anchor: '', anchorLabel: '' };
  // Link name (anchor) and its table-of-contents label.
  if (typeof ld.vpb_anchor === 'string') out.anchor = ld.vpb_anchor;
  if (typeof ld.vpb_anchor_label === 'string') out.anchorLabel = ld.vpb_anchor_label;
  delete ld.vpb_anchor; delete ld.vpb_anchor_label;
  // Background image (Omeka 4.1+ stores these for blocks and sections).
  if (ld.background_image_asset) {
    out.img = cleanImg({ asset: ld.background_image_asset, size: ld.background_image_size, pos: ld.background_image_position_y,
      overlay: typeof ld.class === 'string' && /(^|\s)vpb-overlay(\s|$)/.test(ld.class) });
  }
  ['background_image_asset', 'background_image_size', 'background_image_position_x', 'background_image_position_y'].forEach(function (k) { delete ld[k]; });
  if (typeof ld.background_color === 'string' && HEX_RE.test(ld.background_color)) { out.bg = ld.background_color; delete ld.background_color; }
  var pads = PAD_KEYS.map(function (k) { return ld[k]; });
  if (pads.every(function (p) { return typeof p === 'string' && /^\d+$/.test(p) && p === pads[0]; })) {
    out.pad = parseInt(pads[0], 10); PAD_KEYS.forEach(function (k) { delete ld[k]; });
  }
  if (['left', 'center', 'right', 'justify'].indexOf(ld.alignment_text) >= 0) { out.align = ld.alignment_text; delete ld.alignment_text; }
  if (typeof ld.class === 'string') {
    // vpb-on-dark is added on save for dark backgrounds; it isn't the user's.
    out.cls = ld.class.split(/\s+/).filter(function (c) { return c && c !== 'vpb-on-dark' && c !== 'vpb-overlay'; }).join(' ');
    delete ld.class;
  }
  return out;
}
/**
 * A text block's table-of-contents settings per heading (layout data
 * vpb_toc: [{i, hide, name}], i = which Heading 2-4 in the block) are kept on
 * the headings themselves while editing (data-toc-hide, data-toc-name), so
 * they move with the heading as the text changes. tocApply puts them on;
 * tocExtract takes them off for saving.
 */
function tocApply(html, list) {
  if (!Array.isArray(list) || !list.length) return html;
  var t = document.createElement('template');
  t.innerHTML = String(html || '');
  var hs = t.content.querySelectorAll('h2,h3,h4');
  list.forEach(function (row) {
    var el = row && hs[parseInt(row.i, 10)];
    if (!el) return;
    if (row.hide) el.setAttribute('data-toc-hide', '1');
    if (typeof row.name === 'string' && row.name.trim()) el.setAttribute('data-toc-name', row.name.trim());
  });
  return t.innerHTML;
}
/** {html, toc}: the HTML without the editor's heading attributes, and the vpb_toc list (or null). */
function tocExtract(html) {
  html = String(html || '');
  if (html.indexOf('data-toc-') < 0) return { html: html, toc: null };
  var t = document.createElement('template');
  t.innerHTML = html;
  var toc = [];
  [].forEach.call(t.content.querySelectorAll('h2,h3,h4'), function (el, i) {
    var row = { i: i };
    if (el.hasAttribute('data-toc-hide')) row.hide = true;
    var name = (el.getAttribute('data-toc-name') || '').trim();
    if (name) row.name = name;
    if (row.hide || row.name) toc.push(row);
  });
  [].forEach.call(t.content.querySelectorAll('[data-toc-hide], [data-toc-name]'), function (el) { el.removeAttribute('data-toc-hide'); el.removeAttribute('data-toc-name'); });
  return { html: t.innerHTML, toc: toc.length ? toc : null };
}
function toBlock(raw) {
  var st = splitStyle(raw['o:layout_data']);
  delete st.ld.grid_column_position; delete st.ld.grid_column_span; delete st.ld.vpb_col;
  var data = clone(raw['o:data'] || {});
  if (raw['o:layout'] === 'html') {
    if (st.ld.vpb_toc) data.html = tocApply(data.html, st.ld.vpb_toc);
    delete st.ld.vpb_toc;
  }
  if (raw['o:layout'] === 'specialJumpLinks') data = VPB.toc.upgradeData(data);
  return {
    id: nid('b'), layout: raw['o:layout'], data: data, att: (raw['o:attachment'] || []).map(function (a) {
      return { 'o:item': a['o:item'] ? { 'o:id': a['o:item']['o:id'] } : null, 'o:media': a['o:media'] ? { 'o:id': a['o:media']['o:id'] } : null, 'o:caption': a['o:caption'] || '' };
    }),
    ld: st.ld, bg: st.bg, img: st.img, pad: st.pad, align: st.align, cls: st.cls, anchor: st.anchor, anchorLabel: st.anchorLabel
  };
}
function sectionFrom(groupLd, cols) {
  var st = splitStyle(groupLd || {});
  delete st.ld.vpb_section; delete st.ld.grid_column_position; delete st.ld.grid_column_span;
  return { id: nid('s'), cols: cols, ld: st.ld, bg: st.bg, img: st.img, pad: st.pad, cls: st.cls, align: st.align, anchor: st.anchor, anchorLabel: st.anchorLabel };
}
function importPage(page) {
  var grid = page['o:layout'] === 'grid';
  var gridCols = grid ? (parseInt(obj(page['o:layout_data']).grid_columns, 10) || 12) : 12;
  var blocks = page['o:block'] || [];
  var sections = [], pending = [];

  function rowSplit(list, groupLd) {
    var rows = [], cur = [], cursor = 1;
    list.forEach(function (b) {
      var ld = obj(b['o:layout_data']);
      var span = grid ? (parseInt(ld.grid_column_span, 10) || gridCols) : gridCols;
      var w = clamp(Math.round(span * 12 / gridCols), 1, 12);
      var p = grid ? parseInt(ld.grid_column_position, 10) : NaN;
      var start = isNaN(p) ? null : clamp(Math.round((p - 1) * 12 / gridCols) + 1, 1, 12);
      var s = start === null ? cursor : start;
      if (cur.length && (s < cursor || s + w - 1 > 12)) { rows.push(cur); cur = []; cursor = 1; s = start === null ? 1 : start; }
      if (s + w - 1 > 12) w = 13 - s;
      cur.push({ s: s, w: w, b: b }); cursor = s + w;
    });
    if (cur.length) rows.push(cur);
    rows.forEach(function (row) {
      var full = row.length === 1 && row[0].s === 1 && row[0].w === 12;
      var last = sections[sections.length - 1];
      if (full && !groupLd && last && last.mergeable) { last.cols[0].blocks.push(toBlock(row[0].b)); return; }
      var cols = [], at = 1;
      row.forEach(function (it) {
        if (it.s > at) cols.push(mkCol(it.s - at));
        cols.push(mkCol(it.w, [toBlock(it.b)]));
        at = it.s + it.w;
      });
      if (at <= 12) cols.push(mkCol(13 - at));
      var sec = sectionFrom(groupLd, cols);
      sec.mergeable = full && !groupLd;
      sections.push(sec);
    });
  }
  function flush() { if (pending.length) { rowSplit(pending, null); pending = []; } }

  for (var i = 0; i < blocks.length;) {
    var b = blocks[i];
    if (b['o:layout'] !== 'blockGroup') { pending.push(b); i++; continue; }
    flush();
    var span = Math.max(0, parseInt(obj(b['o:data']).span, 10) || 0);
    var kids = [], j = i + 1;
    while (j < blocks.length && kids.length < span && blocks[j]['o:layout'] !== 'blockGroup') { kids.push(blocks[j]); j++; }
    var gld = obj(b['o:layout_data']);
    var vs = obj(gld.vpb_section);
    if (Array.isArray(vs.cols) && vs.cols.length) {
      var at = 1, cols = [];
      vs.cols.forEach(function (c) {
        if (at > 12) return;
        var w = clamp(parseInt(c && c.w, 10) || 1, 1, 13 - at);
        cols.push(mkCol(w, [], c && typeof c.bg === 'string' && HEX_RE.test(c.bg) ? c.bg : null, c ? cleanImg(c.img) : null));
        at += w;
      });
      if (at <= 12) cols.push(mkCol(13 - at));
      var sec = sectionFrom(gld, cols);
      sec.resp = cleanResp(vs.resp);
      kids.forEach(function (k) {
        var ci = parseInt(obj(k['o:layout_data']).vpb_col, 10);
        if (isNaN(ci) || !cols[ci]) ci = 0;
        cols[ci].blocks.push(toBlock(k));
      });
      sections.push(sec);
    } else {
      var before = sections.length;
      rowSplit(kids, gld);
      if (sections.length === before) sections.push(sectionFrom(gld, [mkCol(12)]));
    }
    i = j;
  }
  flush();
  sections.forEach(function (s) { delete s.mergeable; });
  return sections;
}
/* ---------- export: sections -> Omeka o:block list ---------- */
function applyStyle(ld, item) {
  if (item.bg) ld.background_color = item.bg;
  var anchor = slug(item.anchor || '');
  if (anchor) {
    ld.vpb_anchor = anchor;
    if (item.anchorLabel && String(item.anchorLabel).trim()) ld.vpb_anchor_label = String(item.anchorLabel).trim();
  }
  var img = cleanImg(item.img);
  if (img) {
    ld.background_image_asset = String(img.asset);
    ld.background_image_size = img.size;
    ld.background_image_position_y = img.pos;
    ld.background_image_position_x = 'center';
  }
  if (item.pad !== null && item.pad !== undefined) PAD_KEYS.forEach(function (k) { ld[k] = String(item.pad); });
  if (item.align) ld.alignment_text = item.align;
  // Dark backgrounds get white text on the public site (public.css).
  var overlay = img && img.overlay;
  var cls = [item.cls, overlay ? 'vpb-overlay' : '', VPB.isDark(item.bg) || overlay ? 'vpb-on-dark' : ''].filter(Boolean).join(' ');
  if (cls) ld.class = cls;
  return ld;
}
/**
 * The page's blocks in Omeka's format. If `refs` is an array, it receives the
 * in-memory block behind each exported block (null for section groups), in
 * the same order, so saved data can be matched back afterward.
 */
function exportBlocks(refs) {
  var out = [];
  VPB.store.state.sections.forEach(function (sec) {
    // A section with no blocks isn't saved: it would only add empty space.
    if (sec.cols.every(function (c) { return !c.blocks.length; })) return;
    var starts = [], at = 1;
    sec.cols.forEach(function (c) { starts.push(at); at += c.w; });
    // Interleave row by row so the page still reads sensibly if the module is removed.
    var ordered = [], maxLen = 0;
    sec.cols.forEach(function (c) { maxLen = Math.max(maxLen, c.blocks.length); });
    for (var r = 0; r < maxLen; r++) sec.cols.forEach(function (c, ci) { if (c.blocks[r]) ordered.push({ b: c.blocks[r], ci: ci }); });
    var gld = applyStyle(clone(sec.ld), sec);
    gld.vpb_section = { cols: sec.cols.map(function (c) { return { w: c.w, bg: c.bg || null, img: cleanImg(c.img) }; }), resp: cleanResp(sec.resp) };
    out.push({ 'o:layout': 'blockGroup', 'o:data': { span: ordered.length }, 'o:layout_data': gld, 'o:attachment': [] });
    if (refs) refs.push(null);
    ordered.forEach(function (o) {
      var b = o.b, ld = applyStyle(clone(b.ld), b);
      ld.grid_column_position = String(starts[o.ci]);
      ld.grid_column_span = String(sec.cols[o.ci].w);
      ld.vpb_col = o.ci;
      var data = b.data;
      if (b.layout === 'html') {
        var x = tocExtract(obj(b.data).html);
        if (x.toc) { data = Object.assign({}, b.data, { html: x.html }); ld.vpb_toc = x.toc; }
      }
      out.push({ 'o:layout': b.layout, 'o:data': data, 'o:layout_data': ld, 'o:attachment': b.att });
      if (refs) refs.push(b);
    });
  });
  return out;
}

/* ---------- new blocks and sections ---------- */
function newBlock(layout, html) {
  var def = VPB.blocks.get(layout);
  var data = def && def.newData ? def.newData(html) : {};
  return { id: nid('b'), layout: layout, data: data, att: [], ld: {}, bg: null, pad: null, align: '', cls: '' };
}
function newSection(ws) { return { id: nid('s'), cols: ws.map(function (w) { return mkCol(w); }), ld: {}, bg: null, img: null, pad: null, cls: '', align: '' }; }
/** A one-column section holding a block (used when a block is dropped between sections). */
function sectionWith(block) { return { id: nid('s'), cols: [mkCol(12, [block])], ld: {}, bg: null, img: null, pad: null, cls: '', align: '' }; }

VPB.model = {
  cleanImg: cleanImg, cleanResp: cleanResp, slug: slug, tocApply: tocApply, tocExtract: tocExtract,
  importPage: importPage, exportBlocks: exportBlocks,
  mkCol: mkCol, newBlock: newBlock, newSection: newSection, sectionWith: sectionWith
};
})(window.VPB);
