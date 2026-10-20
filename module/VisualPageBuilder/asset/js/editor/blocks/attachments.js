/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Item attachments (o:attachment), shared by the Media embed and Item with
 * metadata blocks: the sidebar list with item search, and the canvas thumbnails.
 */
(function (VPB) {
'use strict';

var D = VPB.dom, h = D.h, thumbImg = D.thumbImg, thumbStrip = D.thumbStrip, toast = D.toast;
var F = VPB.fields, REFS = VPB.refs;

/** Thumbnail URL for an attachment: the chosen file, else the item's main one. */
function attachmentThumb(a) {
  var ref = a['o:item'] ? REFS.items[a['o:item']['o:id']] : null;
  var mid = a['o:media'] ? a['o:media']['o:id'] : null, u = ref ? ref.thumb : null;
  if (ref && mid) ref.media.forEach(function (m) { if (m.id === mid) u = m.thumb; });
  return u;
}

/** Canvas card content: thumbnails and a count or title. */
function card(b) {
  var n = b.att.length;
  if (!n) return [h('span', { text: 'No items yet. Select this block to add some.' })];
  var first = b.att[0]['o:item'] ? REFS.items[b.att[0]['o:item']['o:id']] : null;
  return [
    thumbStrip(b.att.slice(0, 6).map(attachmentThumb), n > 6 ? n - 6 : 0),
    h('span', { text: n === 1 ? (first ? first.title : '1 item') : n + ' items' })
  ];
}

/**
 * Sidebar list of attached items with a search picker. `itemOnly` hides the
 * file choice and caption, like Omeka's own form for Item with metadata.
 */
function editor(b, itemOnly) {
  var wrap = h('div', { class: 'field' }, [h('span', { class: 'lbl', text: 'Items' })]);
  function redraw() { VPB.store.commit(); VPB.canvas.render(); VPB.app.refreshStatus(); VPB.sidebar.renderProps(); }
  if (!b.att.length) wrap.appendChild(F.note('No items yet.'));
  b.att.forEach(function (a, i) {
    var itemId = a['o:item'] ? a['o:item']['o:id'] : null;
    var ref = itemId ? REFS.items[itemId] : null;
    var mediaId = a['o:media'] ? a['o:media']['o:id'] : null;
    var mref = null;
    if (ref && mediaId) ref.media.forEach(function (m) { if (m.id === mediaId) mref = m; });
    var row = h('div', { class: 'att' });
    row.appendChild(h('div', { class: 'att-head' }, [
      thumbImg(mref ? mref.thumb : ref ? ref.thumb : null),
      h('span', { class: 'pk-t' }, [
        h('b', { text: ref ? ref.title : itemId ? 'Item #' + itemId + ' (not found)' : 'No item' }),
        h('small', { text: itemId ? 'Item #' + itemId + (ref && ref.isPublic === false ? ' \u00B7 private: visitors won\u2019t see it' : '') : '' })
      ]),
      VPB.picker.rowTools(b.att, i, redraw)
    ]));
    if (!itemOnly) {
      if (ref && ref.media.length > 1) {
        var opts = [['', 'Item\u2019s main file']].concat(ref.media.map(function (m) { return [String(m.id), m.title || ('File #' + m.id)]; }));
        row.appendChild(F.selectField('Show which file', 'f-am' + i, opts, mediaId ? String(mediaId) : '', function (v) {
          a['o:media'] = v ? { 'o:id': parseInt(v, 10) } : null;
          VPB.sidebar.renderProps();
        }));
      }
      var cap = h('textarea', { id: 'f-ac' + i, rows: 2, placeholder: 'Optional' });
      cap.value = a['o:caption'] || '';
      cap.addEventListener('change', function () { a['o:caption'] = cap.value; F.changed(); });
      row.appendChild(F.field('Caption', 'f-ac' + i, cap));
    }
    wrap.appendChild(row);
  });
  wrap.appendChild(VPB.picker.host(b, 'items', function (it) {
    b.att.push({ 'o:item': { 'o:id': it.id }, 'o:media': null, 'o:caption': '' });
    redraw();
    toast('Added \u201C' + (it.title || 'item') + '\u201D.');
    var i = document.getElementById('pk-items'); if (i && i.focus) i.focus();
  }));
  return wrap;
}

/**
 * What the block looks like on the site: the chosen files with titles and
 * captions. `opts.horizontal` lays them side by side; `opts.titles` is
 * Omeka's show_title_option (item_title | file_name | no_title).
 */
function render(b, opts) {
  if (!b.att.length) return null;
  var wrap = h('div', { class: 'pv-media' + (opts.horizontal ? ' horizontal' : '') });
  b.att.forEach(function (a) {
    var ref = a['o:item'] ? REFS.items[a['o:item']['o:id']] : null;
    var mid = a['o:media'] ? a['o:media']['o:id'] : null, mref = null;
    if (ref && mid) ref.media.forEach(function (m) { if (m.id === mid) mref = m; });
    var fig = h('figure', { class: 'pv-fig' });
    fig.appendChild(thumbImg(attachmentThumb(a), 'pv-img'));
    var title = opts.titles === 'no_title' ? '' : opts.titles === 'file_name' ? (mref ? mref.title : (ref && ref.media[0] ? ref.media[0].title : '')) : (ref ? ref.title : '');
    if (title) fig.appendChild(h('span', { class: 'pv-title', text: title }));
    if (opts.caption !== false && a['o:caption']) {
      var cap = VPB.dom.sanitize(a['o:caption']);
      var fc = h('figcaption', { class: 'pv-caption' });
      fc.appendChild(cap.fragment);
      fig.appendChild(fc);
    }
    wrap.appendChild(fig);
  });
  return wrap;
}

VPB.attachments = { card: card, editor: editor, render: render };
})(window.VPB);
