/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * The "Page" menu in the editor's top bar:
 * - History…: earlier saved versions of this page (kept on every save);
 *   load one as unsaved changes (Undo takes it back; Save keeps it).
 * - Download layout: this page's layout as a .json file (backup, another
 *   site, sharing a design).
 * - Import layout…: load such a file, replacing the page or adding to it.
 * - Check page…: common problems (VPB.pageCheck, page-check.js).
 */
(function (VPB) {
'use strict';

var h = VPB.dom.h, CFG = VPB.cfg, state = VPB.store.state;
var FORMAT = 'visual-page-builder-layout';

function dialog(title, body, actions) {
  var box = h('div', { class: 'lv-overlay', role: 'dialog', 'aria-modal': 'true', 'aria-label': title });
  var card = h('div', { class: 'lv-card pt-card' }, [h('h2', { text: title })].concat(body));
  function close() { box.remove(); document.removeEventListener('keydown', onKey, true); if (menuBtn) menuBtn.focus(); }
  function onKey(e) { if (e.key === 'Escape') { e.stopPropagation(); close(); } }
  var row = h('div', { class: 'lv-actions' });
  actions.concat([{ text: 'Close', fn: close }]).forEach(function (a) {
    row.appendChild(h('button', { type: 'button', class: 'btn' + (a.primary ? ' primary' : ''), text: a.text, onclick: function () { if (a.fn !== close) a.fn(close); else close(); } }));
  });
  card.appendChild(row);
  box.appendChild(card);
  document.body.appendChild(box);
  document.addEventListener('keydown', onKey, true);
  var first = card.querySelector('button, input, [tabindex]');
  if (first) first.focus();
  return close;
}

/** Put page data ({'o:layout', 'o:layout_data', 'o:block'}) into the editor. */
function load(page, mode) {
  var sections = VPB.model.importPage(page);
  state.sections = mode === 'add' ? state.sections.concat(sections) : sections;
  state.sel = null;
  VPB.store.commit();
  VPB.app.renderAll();
}

// ---- History
function history() {
  var list = h('div', { class: 'pt-list', text: 'Loading\u2026' });
  var close = dialog('Page history', [h('p', { class: 'note', text: 'A copy of the page is kept each time it\u2019s saved in the visual editor (the newest 50). Loading one replaces what\u2019s in the editor as unsaved changes: Undo takes it back, Save keeps it.' }), list], []);
  fetch(CFG.historyUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (body) {
    list.textContent = '';
    if (!body.ok) { list.textContent = (body.errors || ['History isn\u2019t available.']).join(' '); return; }
    if (!body.versions.length) { list.textContent = 'No earlier versions yet. One is kept each time you save.'; return; }
    body.versions.forEach(function (v) {
      var when = new Date(v.created);
      var btn = h('button', { type: 'button', class: 'btn small', text: 'Load this version' });
      btn.addEventListener('click', function () {
        btn.disabled = true;
        fetch(CFG.historyUrl + '?version=' + v.id, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (b) {
          if (!b.ok) throw new Error((b.errors || []).join(' '));
          load(b.page, 'replace');
          close();
          VPB.dom.toast('Loaded the version from ' + when.toLocaleString() + '. Save to keep it, or Undo to go back.', 7000);
        }).catch(function (e) { btn.disabled = false; VPB.dom.toast('Couldn\u2019t load it. ' + e.message); });
      });
      list.appendChild(h('div', { class: 'pt-row' }, [
        h('span', {}, [h('b', { text: when.toLocaleString() }), h('small', { text: (v.user ? ' \u00B7 saved by ' + v.user : '') + ' \u00B7 \u201C' + v.title + '\u201D' })]),
        btn
      ]));
    });
  }).catch(function () { list.textContent = 'History couldn\u2019t be loaded. Check your connection.'; });
}

// ---- Export
function download() {
  var page = CFG.page || {};
  var data = {
    format: FORMAT, version: 1, exported: new Date().toISOString(),
    title: page['o:title'] || '',
    page: { 'o:layout': 'grid', 'o:layout_data': page['o:layout_data'] || {}, 'o:block': VPB.model.exportBlocks() }
  };
  var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
  var a = h('a', { href: URL.createObjectURL(blob), download: ((page['o:slug'] || 'page') + '-layout.json') });
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
  VPB.dom.toast('Layout downloaded (unsaved changes included).');
}

// ---- Import
function importFile() {
  var input = h('input', { type: 'file', accept: '.json,application/json', style: 'display:none' });
  input.addEventListener('change', function () {
    var file = input.files && input.files[0];
    input.remove();
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { VPB.dom.toast('That file is too large to be a page layout.'); return; }
    var reader = new FileReader();
    reader.onload = function () { offer(String(reader.result || ''), file.name); };
    reader.readAsText(file);
  });
  document.body.appendChild(input);
  input.click();
}
/** Check a layout file's text and ask how to use it. Returns false if it isn't one. */
function offer(text, name) {
  var data;
  try { data = JSON.parse(text); } catch (e) { data = null; }
  if (!data || data.format !== FORMAT || !data.page || !Array.isArray(data.page['o:block'])) {
    VPB.dom.toast('\u201C' + name + '\u201D isn\u2019t a Visual Page Builder layout file.');
    return false;
  }
  var n = data.page['o:block'].length;
  dialog('Import layout', [
    h('p', { text: '\u201C' + (data.title || name) + '\u201D, ' + n + ' block' + (n === 1 ? '' : 's') + '.' }),
    h('p', { class: 'note', text: 'Items and pictures chosen on another Omeka site won\u2019t match this one; choose them again where needed. Nothing is saved until you click Save.' })
  ], [
    { text: 'Replace this page\u2019s content', primary: true, fn: function (close) { load(data.page, 'replace'); close(); VPB.dom.toast('Layout imported. Undo takes it back.'); } },
    { text: 'Add to the end', fn: function (close) { load(data.page, 'add'); close(); VPB.dom.toast('Layout added at the end. Undo takes it back.'); } }
  ]);
  return true;
}

// ---- The menu
var menuBtn = document.getElementById('pageMenuBtn');
if (menuBtn) {
  var menu = h('div', { class: 'pt-menu', role: 'menu', hidden: true });
  [['History\u2026', history], ['Download layout', download], ['Import layout\u2026', importFile], ['Check page\u2026', function () { VPB.pageCheck.open(); }]].forEach(function (m) {
    menu.appendChild(h('button', { type: 'button', role: 'menuitem', text: m[0], onclick: function () { hide(); m[1](); } }));
  });
  // Button and menu share a positioned wrapper so the menu opens right under it.
  var wrapEl = h('span', { class: 'pt-wrap' });
  menuBtn.parentNode.insertBefore(wrapEl, menuBtn);
  wrapEl.appendChild(menuBtn);
  wrapEl.appendChild(menu);
  var show = function () { menu.hidden = false; menuBtn.setAttribute('aria-expanded', 'true'); menu.querySelector('button').focus(); };
  var hide = function () { menu.hidden = true; menuBtn.setAttribute('aria-expanded', 'false'); };
  menuBtn.addEventListener('click', function () { if (menu.hidden) show(); else hide(); });
  document.addEventListener('click', function (e) { if (!menu.hidden && e.target !== menuBtn && !menu.contains(e.target)) hide(); });
  menu.addEventListener('keydown', function (e) {
    var items = [].slice.call(menu.querySelectorAll('button')), i = items.indexOf(document.activeElement);
    if (e.key === 'Escape') { hide(); menuBtn.focus(); }
    else if (e.key === 'ArrowDown') { e.preventDefault(); items[(i + 1) % items.length].focus(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); items[(i - 1 + items.length) % items.length].focus(); }
  });
}

VPB.pageTools = { history: history, download: download, importFile: importFile, offer: offer, load: load, FORMAT: FORMAT };
})(window.VPB);
