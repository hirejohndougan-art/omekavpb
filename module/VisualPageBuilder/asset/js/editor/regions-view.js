/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * The site's header and footer around the page in the editor.
 *
 * Editing a page: the built header shows above it and the footer below
 * (read-only; as the Companion Theme shows them). Clicking one goes to edit
 * it, first asking to save if the page has unsaved changes.
 * Editing a header or footer: the other one shows too, and a "page" band in
 * between leads back to the page it was opened from (or to the Visual
 * Builder), asking the same way.
 */
(function (VPB) {
'use strict';

var h = VPB.dom.h, CFG = VPB.cfg;

/** Go to url, asking first if there are unsaved changes. */
function leaveTo(url, what) {
  if (!VPB.store.isDirty()) { go(url); return; }
  var box = h('div', { class: 'lv-overlay', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'lvTitle' });
  var card = h('div', { class: 'lv-card' }, [
    h('h2', { id: 'lvTitle', text: 'Save your changes first?' }),
    h('p', { text: 'You have unsaved changes on this ' + (CFG.region || 'page') + '. Save them before editing ' + what + '?' })
  ]);
  function close() { box.remove(); document.removeEventListener('keydown', onKey, true); }
  function onKey(e) { if (e.key === 'Escape') { e.stopPropagation(); close(); } }
  var saveGo = h('button', { type: 'button', class: 'btn primary', text: 'Save and continue', onclick: function () {
    saveGo.disabled = true;
    VPB.save.save().then(function (ok) { if (ok) { VPB.store.markSaved(VPB.store.snap()); go(url); } else { saveGo.disabled = false; } });
  } });
  var noSave = h('button', { type: 'button', class: 'btn', text: 'Continue without saving', onclick: function () { VPB.store.markSaved(VPB.store.snap()); go(url); } });
  var cancel = h('button', { type: 'button', class: 'btn', text: 'Cancel', onclick: close });
  card.appendChild(h('div', { class: 'lv-actions' }, [saveGo, noSave, cancel]));
  box.appendChild(card);
  document.body.appendChild(box);
  document.addEventListener('keydown', onKey, true);
  saveGo.focus();
}
function go(url) { VPB.regionsView.go(url); }

/** A read-only band showing region html, clickable to go and edit it. */
function band(region, html) {
  var label = region === 'header' ? 'Site header' : 'Site footer';
  var safe = VPB.dom.sanitize(html);
  var inner = h('div', { class: 'rv-content', 'aria-hidden': 'true' });
  inner.appendChild(safe.fragment);
  // Nothing inside is interactive here.
  Array.prototype.forEach.call(inner.querySelectorAll('a, button, input, select, textarea, [tabindex]'), function (el) { el.setAttribute('tabindex', '-1'); });
  var url = CFG.regionUrls[region] + (CFG.region ? '' : (CFG.regionUrls[region].indexOf('?') >= 0 ? '&' : '?') + 'from=' + CFG.page['o:id']);
  var btn = h('button', { type: 'button', class: 'rv-band rv-' + region, 'aria-label': 'Edit the ' + label.toLowerCase(), title: 'Click to edit the ' + label.toLowerCase() }, [
    h('span', { class: 'rv-tag', text: label + ' \u00B7 click to edit' }), inner
  ]);
  btn.addEventListener('click', function () { leaveTo(url, 'the ' + label.toLowerCase()); });
  return btn;
}

/** In a header/footer: the band standing for the page between them. */
function pageBand() {
  var fp = CFG.fromPage;
  var url = fp ? fp.url : CFG.hubUrl;
  var text = fp ? 'Page: \u201C' + fp.title + '\u201D \u00B7 click to go back to editing it' : 'Page content \u00B7 click to go back to the Visual Builder';
  var btn = h('button', { type: 'button', class: 'rv-band rv-page' }, [h('span', { class: 'rv-tag', text: text })]);
  btn.addEventListener('click', function () { leaveTo(url, fp ? 'the page' : 'other parts of the site'); });
  return btn;
}

function render() {
  var top = document.getElementById('regionTop'), bottom = document.getElementById('regionBottom');
  if (!top || !bottom) return;
  top.innerHTML = ''; bottom.innerHTML = '';
  if (VPB.store.state.preview) return;
  var html = CFG.regionHtml || {};
  if (!CFG.region) {
    if (html.header) top.appendChild(band('header', html.header));
    if (html.footer) bottom.appendChild(band('footer', html.footer));
  } else if (CFG.region === 'header') {
    bottom.appendChild(pageBand());
    if (html.footer) bottom.appendChild(band('footer', html.footer));
  } else {
    if (html.header) top.appendChild(band('header', html.header));
    top.appendChild(pageBand());
  }
}

// The top bar's Header / Footer buttons ask the same way, and remember the page.
['header', 'footer'].forEach(function (region) {
  Array.prototype.forEach.call(document.querySelectorAll('a[href="' + CFG.regionUrls[region] + '"]'), function (a) {
    if (!CFG.region) a.setAttribute('href', CFG.regionUrls[region] + '?from=' + CFG.page['o:id']);
    a.addEventListener('click', function (e) { e.preventDefault(); leaveTo(a.getAttribute('href'), 'the site ' + region); });
  });
});

// go() is a property so tests can watch where it would navigate.
VPB.regionsView = { render: render, leaveTo: leaveTo, go: function (url) { window.location.assign(url); } };
})(window.VPB);
