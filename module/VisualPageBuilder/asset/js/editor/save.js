/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Saving: posts the exported blocks to BuilderController::saveAction, then takes
 * each block's data back as Omeka stored it (purified HTML, fetched embeds).
 */
(function (VPB) {
'use strict';

var clone = VPB.util.clone, toast = VPB.dom.toast;
var S = VPB.store, state = S.state, CFG = VPB.cfg;
function commit() { S.commit(); }
function snap() { return S.snap(); }
function exportBlocks(refs) { return VPB.model.exportBlocks(refs); }
function refreshStatus(text, cls) { VPB.app.refreshStatus(text, cls); }
function renderNotice() { VPB.sidebar.renderNotice(); }
function renderCanvas() { VPB.canvas.render(); }
function renderProps() { VPB.sidebar.renderProps(); }

var saving = false;

/** Save the page. Shows progress and errors in the status line and a toast. Returns a promise: true if saved. */
function save() {
  if (saving) return Promise.resolve(false);
  // Blur first so a half-typed field or text block fires its change and is included.
  var editing = document.activeElement;
  if (editing && (editing.isContentEditable || /INPUT|TEXTAREA|SELECT/.test(editing.tagName || ''))) editing.blur();
  commit();
  saving = true;
  var btn = document.getElementById('saveBtn');
  btn.disabled = true;
  refreshStatus('Saving\u2026', null);
  var sentSnap = snap(), sentRefs = [];
  return fetch(CFG.saveUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ csrf: CFG.csrf, blocks: exportBlocks(sentRefs) })
  }).then(function (res) {
    return res.json().catch(function () { return { ok: false, errors: ['The server returned an unexpected response (HTTP ' + res.status + ').'] }; });
  }).then(function (body) {
    if (!body.ok) throw new Error((body.errors || ['Saving failed.']).join(' '));
    if (body.csrf) CFG.csrf = body.csrf;
    if (body.page) CFG.page = body.page;
    S.markSaved(sentSnap);
    syncSaved(body.page, sentRefs, sentSnap);
    renderNotice();
    refreshStatus();
    toast('Saved.');
    return true;
  }).catch(function (err) {
    refreshStatus('Not saved', 'error');
    toast('Not saved. ' + (err && err.message ? err.message : 'Check your connection and try again.'), 8000);
    return false;
  }).then(function (ok) { saving = false; btn.disabled = false; return ok; });
}
/**
 * After a save, take each block's o:data as Omeka stored it (Omeka may clean
 * HTML or fetch an embed), unless the user kept editing during the save.
 */
function syncSaved(page, refs, sentSnap) {
  var saved = page && Array.isArray(page['o:block']) ? page['o:block'] : null;
  if (!saved || saved.length !== refs.length || snap() !== sentSnap) return;
  refs.forEach(function (b, i) {
    if (!b || saved[i]['o:layout'] !== b.layout) return;
    b.data = VPB.blocks.afterSave(b, clone(saved[i]['o:data'] || {}));
    // Headings' table-of-contents settings go back on the headings (model.js).
    var toc = saved[i]['o:layout_data'] && saved[i]['o:layout_data'].vpb_toc;
    if (b.layout === 'html' && toc) b.data.html = VPB.model.tocApply(b.data.html, toc);
  });
  S.rebase();
  renderCanvas();
  if (state.panel === 'settings') renderProps();
}

VPB.save = { save: save };
})(window.VPB);
