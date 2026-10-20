/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Search picker for items and assets, plus the move/remove buttons used in
 * attachment lists. Results come from the module's lookup endpoint
 * (BuilderController::lookupAction) and are cached in VPB.refs.
 */
(function (VPB) {
'use strict';

var D = VPB.dom, h = D.h, thumbImg = D.thumbImg, iconBtn = D.iconBtn, I = VPB.icons;
var CFG = VPB.cfg, REFS = VPB.refs;
function renderProps() { VPB.sidebar.renderProps(); }

/** A search box with live results. `type` is 'items' or 'assets'. */
function picker(type, q0, onQuery, onPick, slot) {
  var id = 'pk-' + type + (slot ? '-' + slot : '');
  var box = h('div', { class: 'picker' });
  var inp = h('input', { type: 'search', id: id, placeholder: type === 'assets' ? 'Search by file name' : 'Search by title, word, or item number', autocomplete: 'off' });
  var list = h('div', { class: 'pk-list', role: 'listbox', 'aria-label': type === 'assets' ? 'Matching images' : 'Matching items' });
  var status = h('p', { class: 'note', 'aria-live': 'polite' });
  inp.value = q0 || '';
  var timer = null, seq = 0;
  function run() {
    var mine = ++seq;
    status.textContent = 'Searching\u2026';
    var url = CFG.lookupUrl + (CFG.lookupUrl.indexOf('?') >= 0 ? '&' : '?') + 'type=' + type + '&q=' + encodeURIComponent(inp.value.trim());
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (body) {
        if (mine !== seq) return;
        var rows = (body && body[type]) || [];
        list.innerHTML = '';
        status.textContent = rows.length ? '' : (inp.value.trim() ? 'Nothing matches that search.' : (type === 'assets' ? 'No images uploaded yet. Add them under Assets in the Omeka admin.' : 'No items yet.'));
        rows.forEach(function (row) {
          REFS[type][row.id] = row;
          var btn = h('button', { type: 'button', class: 'pk-row', role: 'option' }, [
            thumbImg(row.thumb),
            h('span', { class: 'pk-t' }, [h('b', { text: row.title || ('#' + row.id) }), h('small', { text: (type === 'assets' ? 'Asset #' : 'Item #') + row.id + (row.isPublic === false ? ' \u00B7 private' : '') })])
          ]);
          btn.addEventListener('click', function () { onPick(row); });
          list.appendChild(btn);
        });
      })
      .catch(function () { if (mine === seq) status.textContent = 'Search failed. Check your connection.'; });
  }
  inp.addEventListener('input', function () { onQuery(inp.value); clearTimeout(timer); timer = setTimeout(run, 300); });
  box.appendChild(h('label', { for: id, class: 'lbl', text: type === 'assets' ? 'Add an image' : 'Add an item' }));
  box.appendChild(inp);
  box.appendChild(status);
  box.appendChild(list);
  run();
  return box;
}

// Which picker is open: { block, type, slot, q }. Survives sidebar re-renders
// so several items can be added in a row.
var openPick = null;
/**
 * A button that opens a search picker in place, for block `b`.
 * opts.slot  tells pickers apart when one block has several (e.g. "before"
 *            and "after" photos); the search field's id is pk-<type>-<slot>.
 * opts.label button text (default "Add an item…" / "Add an image…").
 * opts.single close the picker after one pick (for one-value fields).
 */
function pickerHost(b, type, onPick, opts) {
  opts = opts || {};
  var slot = opts.slot || '';
  var host = h('div', { class: 'pk-host' });
  var label = opts.label || (type === 'assets' ? 'Add an image\u2026' : 'Add an item\u2026');
  if (openPick && openPick.block === b.id && openPick.type === type && openPick.slot === slot) {
    host.appendChild(picker(type, openPick.q, function (q) { if (openPick) openPick.q = q; }, function (row) {
      if (opts.single) openPick = null;
      onPick(row);
    }, slot));
    host.appendChild(h('button', { type: 'button', class: 'btn small', text: opts.single ? 'Cancel' : 'Done adding', onclick: function () { openPick = null; renderProps(); } }));
  } else {
    host.appendChild(h('button', { type: 'button', class: 'btn small', text: label, onclick: function () {
      openPick = { block: b.id, type: type, slot: slot, q: '' }; renderProps();
      var i = document.getElementById('pk-' + type + (slot ? '-' + slot : '')); if (i && i.focus) i.focus();
    } }));
  }
  return host;
}

function rowTools(list, i, onChange) {
  var tools = h('div', { class: 'att-tools' });
  tools.appendChild(iconBtn('Move up', I.up, function () { if (i > 0) { var t = list[i - 1]; list[i - 1] = list[i]; list[i] = t; onChange(); } }));
  tools.appendChild(iconBtn('Move down', I.down, function () { if (i < list.length - 1) { var t = list[i + 1]; list[i + 1] = list[i]; list[i] = t; onChange(); } }));
  tools.appendChild(iconBtn('Remove', I.del, function () { list.splice(i, 1); onChange(); }));
  return tools;
}

/** Close any open picker unless it belongs to the block about to be selected. */
function closeUnless(blockId) { if (openPick && openPick.block !== blockId) openPick = null; }

VPB.picker = { host: pickerHost, rowTools: rowTools, closeUnless: closeUnless };
})(window.VPB);
