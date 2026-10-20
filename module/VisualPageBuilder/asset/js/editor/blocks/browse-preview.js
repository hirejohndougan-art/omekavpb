/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Browse preview: a list of items, item sets, or media from a search. */
(function (VPB) {
'use strict';

var h = VPB.dom.h, F = VPB.fields, B = VPB.blocks, obj = VPB.util.obj;
var COMPONENTS = ['resource-heading', 'resource-body', 'thumbnail'];

/** Keep only the query string, so a pasted search address works too. */
function browseQuery(v) {
  v = String(v || '').trim();
  var q = v.indexOf('?');
  if (q >= 0) v = v.slice(q + 1);
  return v.replace(/^[?&]+/, '').replace(/#.*$/, '');
}

VPB.blocks.register('browsePreview', {
  // The real search results, fetched from the server (see previews.js).
  render: function (b) {
    var body = VPB.previews.get(b);
    if (!body || !Array.isArray(body.items)) return null;
    var d = obj(b.data), comps = Array.isArray(d.components) ? d.components : COMPONENTS;
    var wrap = h('div', { class: 'pv-browse' });
    if (d.heading) wrap.appendChild(h('h3', { class: 'pv-h3', text: d.heading }));
    if (!body.items.length) { wrap.appendChild(F.note('No items match this search yet.')); return wrap; }
    var grid = h('div', { class: 'pv-grid' });
    body.items.forEach(function (it) {
      var card = h('div', { class: 'pv-card' });
      if (comps.indexOf('thumbnail') >= 0) card.appendChild(VPB.dom.thumbImg(it.thumb, 'pv-img'));
      if (comps.indexOf('resource-heading') >= 0) card.appendChild(h('span', { class: 'pv-title', text: it.title }));
      if (comps.indexOf('resource-body') >= 0 && it.description) card.appendChild(h('span', { class: 'pv-caption', text: it.description }));
      grid.appendChild(card);
    });
    wrap.appendChild(grid);
    var link = d['link-text'] === undefined ? 'Browse all' : d['link-text'];
    if (link) wrap.appendChild(h('span', { class: 'pv-link', text: link }));
    return wrap;
  },
  card: function (b) {
    var d = obj(b.data);
    var what = { items: 'items', item_sets: 'item sets', media: 'media' }[d.resource_type || 'items'] || 'items';
    return [h('span', { text: [d.heading ? '\u201C' + d.heading + '\u201D' : null, 'Up to ' + (d.limit || 12) + ' ' + what + (d.query ? ' matching ' + d.query : ', newest first')].filter(Boolean).join(' \u00B7 ') })];
  },
  editor: function (box, b) {
    box.appendChild(F.selectField('Show', 'f-rt', [['items', 'Items'], ['item_sets', 'Item sets'], ['media', 'Media']], B.val(b, 'resource_type', 'items'), function (v) { B.set(b, 'resource_type', v); }));
    box.appendChild(F.textField('Search query', 'f-q', B.val(b, 'query', ''), function (v) { B.set(b, 'query', browseQuery(v)); }, { placeholder: 'e.g. fulltext_search=railroad' }));
    box.appendChild(F.note('Tip: run a search on your public site, then paste its address here. Only the part after the \u201C?\u201D is kept. Leave it empty to show the newest.'));
    box.appendChild(F.numberField('How many to show', 'f-lim', B.val(b, 'limit', '12'), function (v) { B.set(b, 'limit', v || '12'); }));
    box.appendChild(F.textField('Heading above the list', 'f-hd', B.val(b, 'heading', ''), function (v) { B.set(b, 'heading', v); }, { placeholder: 'Optional' }));
    box.appendChild(F.textField('Link text', 'f-lt', B.val(b, 'link-text', 'Browse all'), function (v) { B.set(b, 'link-text', v); }, { placeholder: 'Leave empty for no link' }));
    // Omeka's default when components were never set is all three.
    var comps = B.val(b, 'components', null);
    var initial = Array.isArray(comps) ? comps.slice() : COMPONENTS.slice();
    var cw = h('div', { class: 'field' }, [h('span', { class: 'lbl', text: 'For each result, show' })]);
    [['resource-heading', 'Title'], ['resource-body', 'Description'], ['thumbnail', 'Thumbnail']].forEach(function (c) {
      cw.appendChild(F.checkField(c[1], 'f-c-' + c[0], initial.indexOf(c[0]) >= 0, function (on) {
        var cur = Array.isArray(B.val(b, 'components', null)) ? B.val(b, 'components', null).slice() : initial.slice();
        cur = cur.filter(function (x) { return x !== c[0]; });
        if (on) cur.push(c[0]);
        cur.sort(function (x, y) { return COMPONENTS.indexOf(x) - COMPONENTS.indexOf(y); });
        B.set(b, 'components', cur);
      }));
    });
    box.appendChild(cw);
  }
});
})(window.VPB);
