/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * "Preview on site": shows the unsaved page exactly as visitors would see it,
 * with the site's theme, header, footer, and menu, in a frame at the chosen
 * device width. The server saves a private copy of the page for this
 * (BuilderController::themePreviewAction); nothing is published.
 */
(function (VPB) {
'use strict';

var h = VPB.dom.h, CFG = VPB.cfg, state = VPB.store.state;
var W = { desktop: '100%', tablet: '768px', phone: '390px' };
var box = null;

function close() {
  if (!box) return;
  box.remove(); box = null;
  // Remove the private preview copy (it would otherwise sit in Omeka's page list).
  fetch(CFG.themePreviewUrl, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ csrf: CFG.csrf, discard: true })
  }).then(function (r) { return r.json(); }).then(function (body) { if (body && body.csrf) CFG.csrf = body.csrf; }).catch(function () { /* removed on the next save */ });
  document.removeEventListener('keydown', onKey, true);
  var b = document.getElementById('sitePreviewBtn'); if (b) b.focus();
}
function onKey(e) { if (e.key === 'Escape') { e.stopPropagation(); close(); } }

function open() {
  var btn = document.getElementById('sitePreviewBtn');
  btn.disabled = true;
  VPB.dom.toast('Making the preview\u2026');
  fetch(CFG.themePreviewUrl, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ csrf: CFG.csrf, blocks: VPB.model.exportBlocks() })
  }).then(function (r) { return r.json(); }).then(function (body) {
    if (!body.ok) throw new Error((body.errors || ['The preview couldn\u2019t be made.']).join(' '));
    CFG.csrf = body.csrf;
    show(body.url);
  }).catch(function (e) { VPB.dom.toast('No preview. ' + e.message); })
    .then(function () { btn.disabled = false; });
}

function show(url) {
  close();
  var frame = h('iframe', { class: 'sp-frame', src: url, title: 'Preview of the page on the site' });
  frame.style.width = W[state.dev] || '100%';
  var devs = h('div', { class: 'seg', role: 'group', 'aria-label': 'Preview width' }, ['desktop', 'tablet', 'phone'].map(function (d) {
    var b = h('button', { type: 'button', 'aria-pressed': String(d === state.dev), text: d[0].toUpperCase() + d.slice(1) });
    b.addEventListener('click', function () {
      frame.style.width = W[d];
      Array.prototype.forEach.call(devs.children, function (x) { x.setAttribute('aria-pressed', String(x === b)); });
    });
    return b;
  }));
  box = h('div', { class: 'sp-overlay', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Preview on site' }, [
    h('div', { class: 'sp-bar' }, [
      h('strong', { text: 'Preview on site' }),
      h('span', { class: 'note', text: 'Not saved or published; this is how it would look.' }),
      devs,
      h('a', { class: 'btn', href: url, target: '_blank', rel: 'noopener noreferrer', text: 'Open in a new tab' }),
      h('button', { type: 'button', class: 'btn primary', id: 'sitePreviewClose', text: 'Back to editing', onclick: close })
    ]),
    h('div', { class: 'sp-stage' }, [frame])
  ]);
  document.body.appendChild(box);
  document.addEventListener('keydown', onKey, true);
  document.getElementById('sitePreviewClose').focus();
}

var btn = document.getElementById('sitePreviewBtn');
if (btn) btn.addEventListener('click', open);
VPB.sitePreview = { open: open, show: show, close: close };
})(window.VPB);
