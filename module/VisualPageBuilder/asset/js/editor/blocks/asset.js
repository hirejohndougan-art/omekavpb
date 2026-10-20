/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Asset: uploaded images (Omeka "assets"), each with an optional caption and
 * page link. o:data is a list of {id, page, alt_link_title, caption}, the same
 * shape Omeka's own form saves.
 */
(function (VPB) {
'use strict';

var D = VPB.dom, h = D.h, thumbImg = D.thumbImg, thumbStrip = D.thumbStrip, toast = D.toast;
var F = VPB.fields, obj = VPB.util.obj, REFS = VPB.refs;

/** The image entries in o:data, whether stored as a list or a keyed object. */
function entries(b) {
  var d = b.data, out = [];
  var list = Array.isArray(d) ? d : Object.keys(obj(d)).sort(function (x, y) { return x - y; }).map(function (k) { return d[k]; });
  list.forEach(function (e) { if (e && typeof e === 'object' && !Array.isArray(e) && 'id' in e) out.push(e); });
  return out;
}

function card(b) {
  var ents = entries(b);
  if (!ents.length) return [h('span', { text: 'No images yet. Select this block to add some.' })];
  return [
    thumbStrip(ents.slice(0, 6).map(function (e) { var r = REFS.assets[e.id]; return r ? r.thumb : null; }), ents.length > 6 ? ents.length - 6 : 0),
    h('span', { text: ents.length === 1 ? '1 image' : ents.length + ' images' })
  ];
}

function editor(box, b) {
  var list = entries(b).map(function (e) { return Object.assign({ id: '', page: '', alt_link_title: '', caption: '' }, e); });
  var wrap = h('div', { class: 'field' }, [h('span', { class: 'lbl', text: 'Images' })]);
  function store() { b.data = list.map(function (e) { return e; }); }
  function redraw() { store(); VPB.store.commit(); VPB.canvas.render(); VPB.app.refreshStatus(); VPB.sidebar.renderProps(); }
  if (!list.length) wrap.appendChild(F.note('No images yet.'));
  list.forEach(function (e, i) {
    var ref = e.id !== '' ? REFS.assets[e.id] : null;
    var row = h('div', { class: 'att' });
    row.appendChild(h('div', { class: 'att-head' }, [
      thumbImg(ref ? ref.thumb : null, 'wide'),
      h('span', { class: 'pk-t' }, [h('b', { text: ref ? ref.title : e.id !== '' ? 'Asset #' + e.id + ' (not found)' : 'No image' }), h('small', { text: e.id !== '' ? 'Asset #' + e.id : '' })]),
      VPB.picker.rowTools(list, i, redraw)
    ]));
    var cap = h('textarea', { id: 'f-xc' + i, rows: 2, placeholder: 'Optional' });
    cap.value = e.caption || '';
    cap.addEventListener('change', function () { e.caption = cap.value; store(); F.changed(); });
    row.appendChild(F.field('Caption', 'f-xc' + i, cap));
    var pageOpts = [['', 'No link']].concat(VPB.sitePages.map(function (p) { return [String(p.id), p.title]; }));
    row.appendChild(F.selectField('Link to page', 'f-xp' + i, pageOpts, e.page === null || e.page === undefined ? '' : String(e.page), function (v) {
      e.page = v; if (!v) e.alt_link_title = ''; store(); VPB.sidebar.renderProps();
    }));
    if (e.page !== '' && e.page !== null && e.page !== undefined) {
      row.appendChild(F.textField('Link text (optional)', 'f-xl' + i, e.alt_link_title, function (v) { e.alt_link_title = v; store(); }, { placeholder: 'Defaults to the page title' }));
    }
    wrap.appendChild(row);
  });
  wrap.appendChild(VPB.picker.host(b, 'assets', function (a) {
    list.push({ id: String(a.id), page: '', alt_link_title: '', caption: '' });
    redraw();
    toast('Added \u201C' + (a.title || 'image') + '\u201D.');
    var i = document.getElementById('pk-assets'); if (i && i.focus) i.focus();
  }));
  box.appendChild(wrap);
}

/** The images as the site shows them: image, link text, caption. */
function render(b) {
  var ents = entries(b);
  if (!ents.length) return null;
  var wrap = h('div', { class: 'pv-assets' });
  ents.forEach(function (e) {
    var r = REFS.assets[e.id], fig = h('figure', { class: 'pv-fig' });
    fig.appendChild(thumbImg(r ? r.thumb : null, 'pv-img wide'));
    if (e.page) {
      var page = null;
      VPB.sitePages.forEach(function (p) { if (String(p.id) === String(e.page)) page = p; });
      fig.appendChild(h('span', { class: 'pv-link', text: e.alt_link_title || (page ? page.title : 'Link') }));
    }
    if (e.caption) fig.appendChild(h('figcaption', { class: 'pv-caption', text: e.caption }));
    wrap.appendChild(fig);
  });
  return wrap;
}

VPB.blocks.register('asset', { card: card, editor: editor, render: render });
})(window.VPB);
