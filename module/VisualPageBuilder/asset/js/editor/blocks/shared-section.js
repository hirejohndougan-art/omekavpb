/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Shared sections in the editor:
 * - the Shared section block's sidebar: choose which shared section to show,
 *   open it to edit, or go to the list (Visual Builder › Shared sections);
 * - "Make this a shared section" (section settings): saves the section as a
 *   new shared section and puts a Shared section block in its place;
 * - a note at the top while editing a shared section itself.
 */
(function (VPB) {
'use strict';

var h = VPB.dom.h, CFG = VPB.cfg;

VPB.blocks.extend('specialSharedSection', function (box, b) {
  var list = CFG.sharedSections || [];
  var wrap = h('div', { class: 'field' });
  var id = 'f-shared-pick';
  wrap.appendChild(h('label', { for: id, text: 'Which shared section' }));
  var sel = h('select', { id: id });
  sel.appendChild(h('option', { value: '0', text: list.length ? 'Choose one\u2026' : 'No shared sections yet' }));
  var cur = parseInt(VPB.util.obj(b.data).section, 10) || 0;
  list.forEach(function (s) {
    var o = h('option', { value: String(s.id), text: s.title });
    if (s.id === cur) o.selected = true;
    sel.appendChild(o);
  });
  sel.addEventListener('change', function () {
    b.data = Object.assign({}, b.data, { section: parseInt(sel.value, 10) || 0 });
    VPB.store.commit(); VPB.app.renderAll();
  });
  wrap.appendChild(sel);
  var chosen = list.filter(function (s) { return s.id === cur; })[0];
  var links = h('p', { class: 'note' });
  if (chosen) {
    var edit = h('a', { href: chosen.url, text: 'Edit \u201C' + chosen.title + '\u201D' });
    edit.addEventListener('click', function (e) { e.preventDefault(); VPB.regionsView.leaveTo(chosen.url, 'the shared section'); });
    links.appendChild(edit);
    links.appendChild(document.createTextNode(' \u00B7 changes show on every page that uses it. '));
  }
  links.appendChild(h('a', { href: CFG.sharedHubUrl, text: 'All shared sections' }));
  wrap.appendChild(links);
  box.appendChild(wrap);
});

/** Save section `sec` as a new shared section and show it here instead. */
function makeShared(sec) {
  var title = window.prompt('Name for the shared section (e.g. "Visit us strip"):', '');
  if (title === null) return;
  var M = VPB.model, state = VPB.store.state;
  // The section on its own, as Omeka blocks.
  var saved = state.sections;
  state.sections = [sec];
  var blocks = M.exportBlocks();
  state.sections = saved;
  fetch(CFG.makeSharedUrl, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ csrf: CFG.csrf, title: title.trim(), blocks: blocks })
  }).then(function (r) { return r.json(); }).then(function (body) {
    if (!body.ok) throw new Error((body.errors || ['It couldn\u2019t be saved.']).join(' '));
    CFG.csrf = body.csrf;
    CFG.sharedSections = (CFG.sharedSections || []).concat([{ id: body.id, title: body.title, url: body.url }]);
    var blk = M.newBlock('specialSharedSection');
    blk.data = Object.assign({}, blk.data, { section: body.id });
    var at = state.sections.indexOf(sec);
    state.sections.splice(at, 1, M.sectionWith(blk));
    state.sel = { kind: 'block', id: blk.id };
    VPB.store.commit(); VPB.app.renderAll();
    VPB.dom.toast('Saved as the shared section \u201C' + body.title + '\u201D. Save this page to keep the change; use the Shared section block to show it on other pages.', 7000);
  }).catch(function (e) { VPB.dom.toast('Not made shared. ' + e.message); });
}

// While editing a shared section itself: say so.
if (CFG.isShared) {
  var page = document.getElementById('page');
  if (page) page.insertBefore(h('p', { class: 'shared-banner', role: 'note', text: 'You\u2019re editing a shared section. Changes appear on every page that shows it once you save.' }), page.firstChild);
}

VPB.sharedSections = { makeShared: makeShared };
})(window.VPB);
