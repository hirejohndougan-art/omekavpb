/* SPDX-License-Identifier: GPL-2.0-or-later */
/** Text and headings (Omeka's HTML block): edited directly on the canvas. */
(function (VPB) {
'use strict';

var h = VPB.dom.h, sanitize = VPB.dom.sanitize, obj = VPB.util.obj, labelFor = VPB.util.labelFor;

function isLocked(b) { return sanitize(obj(b.data).html).removed; }

// Link addresses allowed from the formatting bar (as Normalizer::safeUrl).
var SAFE_URL = /^(https?:\/\/[^\s]+|mailto:[^\s]+|tel:[^\s]+|#[^\s]*|\/(?!\/)[^\s]*)$/i;
// Web-safe fonts (on nearly every computer and phone): label, CSS value.
var FONTS = [
  ['Default (theme font)', 'inherit'], ['Arial', 'Arial, Helvetica, sans-serif'], ['Verdana', 'Verdana, Geneva, sans-serif'],
  ['Tahoma', 'Tahoma, Geneva, sans-serif'], ['Trebuchet MS', '"Trebuchet MS", sans-serif'], ['Georgia', 'Georgia, serif'],
  ['Times New Roman', '"Times New Roman", Times, serif'], ['Garamond', 'Garamond, "Times New Roman", serif'],
  ['Courier New', '"Courier New", Courier, monospace'], ['System font', 'system-ui, sans-serif']
];
// Where the cursor last was in a text block: { block, div, range }.
var caret = null, active = null;
document.addEventListener('selectionchange', function () {
  var sel = window.getSelection && window.getSelection();
  if (active && sel && sel.rangeCount && active.div.contains(sel.anchorNode)) {
    caret = { block: active.block, div: active.div, range: sel.getRangeAt(0).cloneRange() };
  }
});

/** Put an asset's picture into text block b, at the cursor if it was there. */
function insertImage(b, row) {
  var alt = window.prompt('Describe the picture for people who can\u2019t see it:', row.title || '') || '';
  var img = document.createElement('img');
  img.src = row.thumb; img.alt = alt;
  img.style.maxWidth = '100%';
  if (caret && caret.block === b.id && document.body.contains(caret.div)) {
    var r = caret.range;
    r.collapse(false);
    r.insertNode(img);
    b.data = Object.assign({}, obj(b.data), { html: caret.div.innerHTML });
  } else {
    var p = document.createElement('p'); p.appendChild(img);
    b.data = Object.assign({}, obj(b.data), { html: String(obj(b.data).html || '') + p.outerHTML });
  }
  VPB.store.commit(); VPB.app.renderAll();
  VPB.dom.toast('Picture added.');
}


// Standard text-editing icons (24×24, drawn with lines like most editors).
var ICONS = {
  alignLeft: '<path d="M4 6h16M4 10h10M4 14h16M4 18h10"/>',
  alignCenter: '<path d="M4 6h16M7 10h10M4 14h16M7 18h10"/>',
  alignRight: '<path d="M4 6h16M10 10h10M4 14h16M10 18h10"/>',
  alignJustify: '<path d="M4 6h16M4 10h16M4 14h16M4 18h16"/>',
  bullets: '<circle cx="5" cy="7" r="1.3" fill="currentColor" stroke="none"/><circle cx="5" cy="12" r="1.3" fill="currentColor" stroke="none"/><circle cx="5" cy="17" r="1.3" fill="currentColor" stroke="none"/><path d="M9 7h11M9 12h11M9 17h11"/>',
  numbers: '<path d="M9 7h11M9 12h11M9 17h11"/><text x="2.2" y="9" font-size="6.5" font-family="system-ui,sans-serif" fill="currentColor" stroke="none">1</text><text x="2.2" y="14.2" font-size="6.5" font-family="system-ui,sans-serif" fill="currentColor" stroke="none">2</text><text x="2.2" y="19.4" font-size="6.5" font-family="system-ui,sans-serif" fill="currentColor" stroke="none">3</text>',
  quote: '<path d="M7 7c-2 1-3 3-3 6v3h5v-5H6c0-2 1-3 2-3.5M17 7c-2 1-3 3-3 6v3h5v-5h-3c0-2 1-3 2-3.5"/>',
  link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
  unlink: '<path d="M10 14a4 4 0 0 0 5.7 0l1.2-1.2M14 10a4 4 0 0 0-5.7 0l-1.2 1.2M4 4l16 16"/>',
  clear: '<path d="M6 5h12M12 5l-3 14M5 19h7M15 14l5 5M20 14l-5 5"/>',
  footnote: '<path d="M4 18h9M6 6h7M9.5 6v12"/><text x="15" y="10" font-size="8" font-family="system-ui,sans-serif" fill="currentColor" stroke="none">1</text>'
};
function iconSvg(name) {
  return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + ICONS[name] + '</svg>';
}

/** The text block's editable element on the page, if it's there. */
function editableOf(b) { return document.querySelector('.blk[data-id="' + b.id + '"] .b-html[contenteditable="true"]'); }

/**
 * Text formatting in the sidebar for a text block: style, font, size, bold,
 * italic, underline, strikethrough, colors, lists, quote, alignment (left,
 * center, right, justify), link, footnote, clear. It works on the words
 * selected in the block on the page: buttons keep that selection (they don't
 * take focus); lists and color pickers remember it and put it back.
 * Formatting is written as inline styles (styleWithCSS), which Omeka's HTML
 * cleaner keeps.
 */
function formatPanel(b) {
  var panel = h('div', { class: 'rt-panel', role: 'toolbar', 'aria-label': 'Text formatting' });
  panel.appendChild(h('span', { class: 'lbl', text: 'Text formatting' }));
  var rows = [h('div', { class: 'rt-row' }), h('div', { class: 'rt-row' }), h('div', { class: 'rt-row' })];
  rows.forEach(function (r) { panel.appendChild(r); });
  var stateful = []; // [button, command] to show pressed for the selection
  function current() {
    var div = editableOf(b);
    if (!div) return null;
    if (caret && caret.block === b.id && caret.div === div) {
      var sel = window.getSelection();
      if (!(sel.rangeCount && div.contains(sel.anchorNode))) { div.focus(); sel.removeAllRanges(); sel.addRange(caret.range); }
    } else if (!div.contains(document.activeElement)) {
      div.focus();
    }
    return div;
  }
  function run(cmd, value) {
    var div = current();
    if (!div) { VPB.dom.toast('Click into the text on the page first.'); return; }
    try { document.execCommand('styleWithCSS', false, true); } catch (e) { /* older browsers */ }
    document.execCommand(cmd, false, value);
    b.data = Object.assign({}, obj(b.data), { html: div.innerHTML });
    VPB.app.refreshStatus(); VPB.store.commit();
    if (VPB.tocBlocks) VPB.tocBlocks.refreshSoon();
    showState();
  }
  function tool(row, label, content, fn, opts) {
    opts = opts || {};
    var btn = h('button', { type: 'button', class: 'rt-btn' + (opts.cls ? ' ' + opts.cls : ''), 'aria-label': label, title: label });
    if (opts.icon) btn.innerHTML = iconSvg(opts.icon); else btn.textContent = content;
    btn.addEventListener('mousedown', function (e) { e.preventDefault(); }); // keep the selection on the page
    btn.addEventListener('click', fn);
    if (opts.state) { btn.setAttribute('aria-pressed', 'false'); stateful.push([btn, opts.state]); }
    rows[row].appendChild(btn);
    return btn;
  }
  function choose(row, label, options, fn) {
    var sel = h('select', { class: 'rt-sel', 'aria-label': label, title: label });
    sel.appendChild(h('option', { value: '', text: label }));
    options.forEach(function (o) { sel.appendChild(h('option', { value: o[0], text: o[1] })); });
    sel.addEventListener('change', function () { if (sel.value === '') return; fn(sel.value); sel.value = ''; });
    rows[row].appendChild(sel);
  }
  function colorTool(row, label, cmd, start) {
    var input = h('input', { type: 'color', class: 'rt-color', 'aria-label': label, title: label });
    input.value = start;
    input.addEventListener('change', function () { run(cmd, input.value); });
    rows[row].appendChild(input);
  }
  choose(0, 'Style', [['p', 'Paragraph'], ['h2', 'Heading 2'], ['h3', 'Heading 3'], ['h4', 'Heading 4'], ['blockquote', 'Quote']], function (v) { run('formatBlock', v); });
  choose(0, 'Font', FONTS.map(function (f) { return [f[1], f[0]]; }), function (v) { run('fontName', v); });
  choose(0, 'Size', [['2', 'Small'], ['3', 'Normal'], ['4', 'Large'], ['5', 'Larger'], ['6', 'Huge']], function (v) { run('fontSize', v); });
  tool(1, 'Bold (Ctrl+B)', 'B', function () { run('bold'); }, { cls: 'b', state: 'bold' });
  tool(1, 'Italic (Ctrl+I)', 'I', function () { run('italic'); }, { cls: 'i', state: 'italic' });
  tool(1, 'Underline (Ctrl+U)', 'U', function () { run('underline'); }, { cls: 'u', state: 'underline' });
  tool(1, 'Strikethrough', 'S', function () { run('strikeThrough'); }, { cls: 's', state: 'strikeThrough' });
  colorTool(1, 'Text color', 'foreColor', '#1f1b16');
  colorTool(1, 'Highlight color', 'hiliteColor', '#fff3a3');
  tool(1, 'Clear formatting', '', function () { run('removeFormat'); }, { icon: 'clear' });
  tool(2, 'Align left', '', function () { run('justifyLeft'); }, { icon: 'alignLeft', state: 'justifyLeft' });
  tool(2, 'Align center', '', function () { run('justifyCenter'); }, { icon: 'alignCenter', state: 'justifyCenter' });
  tool(2, 'Align right', '', function () { run('justifyRight'); }, { icon: 'alignRight', state: 'justifyRight' });
  tool(2, 'Justify', '', function () { run('justifyFull'); }, { icon: 'alignJustify', state: 'justifyFull' });
  tool(2, 'Bulleted list', '', function () { run('insertUnorderedList'); }, { icon: 'bullets', state: 'insertUnorderedList' });
  tool(2, 'Numbered list', '', function () { run('insertOrderedList'); }, { icon: 'numbers', state: 'insertOrderedList' });
  tool(2, 'Quote', '', function () { run('formatBlock', 'blockquote'); }, { icon: 'quote' });
  tool(2, 'Link', '', function () {
    var div = current();
    if (!div) { VPB.dom.toast('Click into the text on the page first.'); return; }
    var range = caret && caret.block === b.id ? caret.range.cloneRange() : null;
    var spots = VPB.fields.anchorsInUse().map(function (x) { return '#' + x.id; });
    var url = window.prompt('Link to a web address (https://…), a page (/s/your-site/page/…)' +
      (spots.length ? ', or a spot on this page: ' + spots.join(', ') : '') + ':', spots.length ? spots[0] : 'https://');
    if (url === null) return;
    url = url.trim();
    if (!SAFE_URL.test(url) || url === 'https://') { VPB.dom.toast('That isn\u2019t a link address the editor can use.'); return; }
    if (range) { div.focus(); var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range); }
    if (window.getSelection().isCollapsed) run('insertHTML', '<a href="' + url.replace(/"/g, '%22') + '">' + url.replace(/[<&]/g, '') + '</a>');
    else run('createLink', url);
  }, { icon: 'link' });
  tool(2, 'Remove link', '', function () { run('unlink'); }, { icon: 'unlink' });
  tool(2, 'Add a footnote', '', function () {
    var range = caret && caret.block === b.id ? caret.range.cloneRange() : null;
    var note = window.prompt('Footnote text (it will be numbered and listed under "Notes"):', '');
    if (!note || !note.trim()) return;
    var div = editableOf(b);
    if (range && div) { div.focus(); var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range); }
    var safe = note.trim().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    run('insertHTML', '<sup class="vpb-footnote">' + safe + '</sup>\u200B');
  }, { icon: 'footnote' });
  // Show which formatting applies where the cursor is.
  function showState() {
    var div = editableOf(b), sel = window.getSelection();
    var inside = div && sel && sel.rangeCount && div.contains(sel.anchorNode);
    stateful.forEach(function (x) {
      var on = false;
      if (inside) { try { on = document.queryCommandState(x[1]); } catch (e) { on = false; } }
      x[0].setAttribute('aria-pressed', String(!!on));
    });
  }
  panelState = showState;
  return panel;
}
// The sidebar panel's "which buttons are on" refresh, run as the cursor moves.
var panelState = null;
document.addEventListener('selectionchange', function () { if (panelState) panelState(); if (tocState) tocState(); });

/** The Heading 2–4 the cursor is in, in text block b: {el, index} or null. */
function headingAtCursor(b) {
  var div = editableOf(b), sel = window.getSelection && window.getSelection();
  if (!div || !sel || !sel.rangeCount || !div.contains(sel.anchorNode)) return undefined; // cursor elsewhere
  var n = sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentNode;
  var hd = n && n.closest ? n.closest('h2,h3,h4') : null;
  if (!hd || !div.contains(hd)) return null;
  return { el: hd, index: [].indexOf.call(div.querySelectorAll('h2,h3,h4'), hd) };
}
/**
 * "Table of contents" for the heading the cursor is in: show it there or
 * not, and an optional shorter name. Saved on the heading (see model.js).
 */
function tocPanel(b) {
  var box = h('div', { class: 'rt-toc', role: 'group', 'aria-label': 'This heading in the table of contents' });
  var at = null; // index of the heading being set
  function heading() { var div = editableOf(b); return div && at !== null ? div.querySelectorAll('h2,h3,h4')[at] || null : null; }
  function store(rerender) {
    var div = editableOf(b);
    if (!div) return;
    b.data = Object.assign({}, obj(b.data), { html: div.innerHTML });
    VPB.app.refreshStatus();
    if (rerender) { VPB.store.commit(); VPB.canvas.render(); }
  }
  function draw() {
    box.innerHTML = '';
    var hd = heading();
    box.appendChild(h('span', { class: 'lbl', text: 'Table of contents' }));
    if (!hd) {
      box.appendChild(h('p', { class: 'note', text: 'Headings are listed in the Table of contents: Heading 2 as a section, Heading 3 a subsection, Heading 4 a sub-subsection. Click into a heading to leave it out or give it a shorter name there.' }));
      return;
    }
    var level = { H2: 'a section', H3: 'a subsection', H4: 'a sub-subsection' }[hd.tagName.toUpperCase()];
    box.appendChild(h('p', { class: 'note', text: 'This heading is ' + level + ' (' + hd.tagName.replace('H', 'Heading ') + ').' }));
    var show = h('input', { type: 'checkbox', id: 'f-toc-show' });
    show.checked = !hd.hasAttribute('data-toc-hide');
    show.addEventListener('change', function () {
      var el = heading(); if (!el) return;
      if (show.checked) el.removeAttribute('data-toc-hide'); else el.setAttribute('data-toc-hide', '1');
      store(true);
    });
    box.appendChild(h('div', { class: 'field check' }, [h('label', { for: 'f-toc-show' }, [show, h('span', { text: 'Show in the table of contents' })])]));
    var name = h('input', { type: 'text', id: 'f-toc-name', placeholder: hd.textContent.trim() });
    name.value = hd.getAttribute('data-toc-name') || '';
    function setName() {
      var el = heading(); if (!el) return;
      if (name.value.trim()) el.setAttribute('data-toc-name', name.value.trim()); else el.removeAttribute('data-toc-name');
    }
    name.addEventListener('input', function () { setName(); store(false); });
    name.addEventListener('change', function () { setName(); store(true); });
    box.appendChild(h('div', { class: 'field' }, [h('label', { for: 'f-toc-name', text: 'Name in the table of contents (optional)' }), name, h('p', { class: 'note', text: 'Leave it empty to use the heading itself.' })]));
  }
  // Follow the cursor while it's in this block's text (and stay put while
  // you're using the controls here).
  tocState = function () {
    if (!document.body.contains(box)) { tocState = null; return; }
    var found = headingAtCursor(b);
    if (found === undefined) return;
    var next = found ? found.index : null;
    if (next !== at || !box.firstChild) { at = next; draw(); }
  };
  if (caret && caret.block === b.id && document.body.contains(caret.div)) {
    var n = caret.range.startContainer;
    n = n.nodeType === 1 ? n : n.parentNode;
    var hd = n && n.closest ? n.closest('h2,h3,h4') : null;
    if (hd && caret.div.contains(hd)) at = [].indexOf.call(caret.div.querySelectorAll('h2,h3,h4'), hd);
  }
  draw();
  return box;
}
var tocState = null;

VPB.blocks.register('html', {
  name: function (b) {
    return /^\s*<h[1-6][\s>]/i.test(obj(b.data).html || '') ? 'Heading' : labelFor('html');
  },
  newData: function (html) { return { html: html || '<p>Write something here.</p>' }; },
  // Shown sanitized. Blocks whose HTML had to be changed for the preview
  // (embeds, scripts) are locked so editing can't damage them.
  preview: function (b) {
    var res = sanitize(obj(b.data).html);
    var div = h('div', { class: 'b-html' + (res.removed ? ' locked' : '') });
    div.appendChild(res.fragment);
    if (VPB.store.state.preview || res.removed) return div;
    div.setAttribute('contenteditable', 'true');
    function store() {
      b.data = Object.assign({}, obj(b.data), { html: div.innerHTML });
      VPB.app.refreshStatus();
      if (VPB.tocBlocks) VPB.tocBlocks.refreshSoon(); // headings feed the Table of contents
    }
    div.addEventListener('input', store);
    div.addEventListener('blur', VPB.store.commit);
    // Remember where the cursor was, for "Insert an image" in the sidebar.
    div.addEventListener('focus', function () { active = { block: b.id, div: div }; });
    return div;
  },
  note: function (b) {
    if (!isLocked(b)) {
      var box = h('div', {}, [formatPanel(b), tocPanel(b), h('p', { class: 'note', text: 'Type directly on the page. Select words there, then format them here. To add a picture, put the cursor where it should go, then choose it below.' })]);
      box.appendChild(VPB.picker.host(b, 'assets', function (row) {
        VPB.refs.assets[row.id] = row;
        insertImage(b, row);
      }, { slot: 'rtimg', single: true, label: 'Insert an image\u2026' }));
      return box;
    }
    return h('p', { class: 'note', text: 'This text contains embedded content, like a video or form, that the visual editor can\u2019t show safely. To avoid losing it, edit this block in the standard editor.' });
  }
});
VPB.textTools = { insertImage: insertImage, SAFE_URL: SAFE_URL, FONTS: FONTS, formatPanel: formatPanel, tocPanel: tocPanel };
})(window.VPB);
