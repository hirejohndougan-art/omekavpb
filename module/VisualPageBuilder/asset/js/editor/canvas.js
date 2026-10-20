/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * The page canvas: renders sections, columns, and blocks with their toolbars,
 * drop placeholders during a drag, and the column resize bars.
 */
(function (VPB) {
'use strict';

var D = VPB.dom, h = D.h, svg = D.svg, I = VPB.icons;
var S = VPB.store, state = S.state, A = VPB.actions;
var DEV_W = VPB.DEV_W;
function commit() { S.commit(); }
function renderAll() { VPB.app.renderAll(); }
function openSettings() { VPB.app.openSettings(); }
function startPointer(e, info) { VPB.drag.start(e, info); }
function startResize(e, secId, ci, handle) { VPB.resize.start(e, secId, ci, handle); }
function moveSec(id, d) { A.moveSec(id, d); }
function dupSec(id) { A.dupSec(id); }
function delSec(id) { A.delSec(id); }
function moveBlock(id, d) { A.moveBlock(id, d); }
function dupBlock(id) { A.dupBlock(id); }
function delBlock(id) { A.delBlock(id); }
function shiftBorder(s, ci, d) { return A.shiftBorder(s, ci, d); }
function blockBody(b) { return VPB.blocks.preview(b); }
function blockName(b) { return VPB.blocks.name(b); }

function isSel(kind, id) { return !state.preview && state.sel && state.sel.kind === kind && state.sel.id === id; }
/** Hidden on the device being previewed (layout data vpb_hide). */
function hiddenAtDev(b) {
  return !!(b.ld && b.ld.vpb_hide && String(b.ld.vpb_hide).split(',').indexOf(state.dev) >= 0);
}
/** The block's text size (percent) at the previewed width, or null for the theme's size. */
function textAtDev(b) {
  var key = 'vpb_text_' + state.dev;
  if (state.dev !== 'desktop' && b.ld && b.ld[key] !== undefined && b.ld[key] !== '') return +b.ld[key];
  return null;
}
/** The block's padding at the previewed width: its tablet or phone value when set, else its padding. */
function paddingAtDev(b) {
  var key = 'vpb_pad_' + state.dev;
  if (state.dev !== 'desktop' && b.ld && b.ld[key] !== undefined && b.ld[key] !== '') return +b.ld[key];
  return b.pad;
}
function toolBtn(label, icon, fn, cls) {
  var b = h('button', { type: 'button', 'aria-label': label, title: label, class: cls || null });
  b.innerHTML = svg(icon, 15, '#FFFFFF');
  if (fn) b.addEventListener('click', function (e) { e.stopPropagation(); fn(); });
  return b;
}
function renderCanvas() {
  var drag = VPB.drag.current();
  var root = document.getElementById('secs');
  root.innerHTML = '';
  document.getElementById('page').style.width = DEV_W[state.dev] + 'px';
  document.body.classList.toggle('editing', !state.preview);
  var dragging = drag && drag.started;
  var movingSec = dragging && drag.kind === 'moveSec' ? drag.id : null;
  var view = state.sections.filter(function (s) { return s.id !== movingSec; });
  if (!view.length && !dragging) {
    root.appendChild(h('div', { class: 'empty-page', text: 'This page is empty. Drag a section here from the sidebar, or click the + to add one, then drop blocks into its columns.' }));
    if (!state.preview) root.appendChild(insBar(0));
    return;
  }
  var items = view.map(function (s, i) { return { s: s, i: i }; });
  if (dragging && drag.target && drag.target.mode === 'sec') {
    var secDrag = drag.kind === 'newSec' || drag.kind === 'moveSec';
    items.splice(drag.target.index, 0, { ph: true, text: secDrag ? 'Drop section here' : 'Drop here to start a new section' });
  }
  // "+" bars before, between, and after sections: drop targets while
  // dragging, and click to add a section or block right there. Where a
  // dragged item would land, the drop placeholder takes the bar's place.
  var showBars = !state.preview;
  var at = 0; // section index the next bar stands for
  items.forEach(function (it, n) {
    if (it.ph) { root.appendChild(h('div', { class: 'sec ph', text: it.text })); return; }
    if (showBars && !(n > 0 && items[n - 1].ph)) root.appendChild(insBar(at));
    root.appendChild(secEl(it.s, it.i));
    at++;
  });
  if (showBars && !(items.length && items[items.length - 1].ph)) root.appendChild(insBar(at));
}
/** A "+" bar standing for section position `index`. */
function insBar(index) {
  var chosen = state.insertAt === index;
  var btn = h('button', { type: 'button', class: 'ins-plus', 'aria-label': chosen ? 'Adding here: pick a section or block in the sidebar' : 'Add a section or block here', title: 'Add here (or drag a section or block here)', 'aria-pressed': String(chosen) }, [h('span', { 'aria-hidden': 'true', text: '+' })]);
  btn.addEventListener('pointerdown', function (e) { e.stopPropagation(); });
  btn.addEventListener('click', function (e) { e.stopPropagation(); VPB.app.addAt(index); });
  return h('div', { class: 'ins' + (chosen ? ' chosen' : ''), 'data-index': String(index) }, [h('span', { class: 'ins-line', 'aria-hidden': 'true' }), btn]);
}
function secEl(s, si) {
  var wrap = h('div', { class: 'sec' + (isSel('sec', s.id) ? ' selected' : ''), 'data-id': s.id, 'data-kind': 'sec' });
  if (s.anchor && !state.preview) wrap.appendChild(h('span', { class: 'anchor-tag', title: 'Older link name', text: '#' + VPB.model.slug(s.anchor) }));
  wrap.appendChild(h('span', { class: 'chip', text: 'Section ' + (si + 1) + ', ' + s.cols.length + (s.cols.length === 1 ? ' column' : ' columns') }));
  var tools = h('div', { class: 'tools' });
  var hd = toolBtn('Drag section', I.handle, null, 'handle');
  hd.addEventListener('pointerdown', function (e) { e.stopPropagation(); startPointer(e, { kind: 'moveSec', id: s.id }); });
  tools.appendChild(hd);
  tools.appendChild(toolBtn('Move section up', I.up, function () { moveSec(s.id, -1); }));
  tools.appendChild(toolBtn('Move section down', I.down, function () { moveSec(s.id, 1); }));
  tools.appendChild(toolBtn('Duplicate section', I.dup, function () { dupSec(s.id); }));
  tools.appendChild(toolBtn('Section settings', I.set, openSettings, 'set-btn'));
  tools.appendChild(toolBtn('Delete section', I.del, function () { delSec(s.id); }));
  wrap.appendChild(tools);
  var inner = h('div', { class: 'sec-inner' });
  var st = 'padding:' + (s.pad || 0) + 'px;';
  if (s.bg) st += 'background:' + s.bg + ';';
  if (VPB.isDark(s.bg)) st += 'color:#fff;';
  st += VPB.bgImageCss(s.img);
  var mode = respMode(s);
  if (mode === 'stack') st += 'grid-template-columns:minmax(0,1fr);';
  else if (mode === 'two') st += 'grid-template-columns:repeat(2,minmax(0,1fr));';
  inner.setAttribute('style', st);
  s.cols.forEach(function (c, ci) { inner.appendChild(colEl(s, c, ci)); });
  wrap.appendChild(inner);
  return wrap;
}
/** How this section's columns sit at the previewed width: 'same', 'two', or 'stack'. */
function respMode(s) {
  if (state.dev === 'desktop' || s.cols.length < 2) return 'same';
  var r = VPB.model.cleanResp(s.resp);
  return state.dev === 'phone' ? r.phone : r.tablet;
}
function colEl(s, c, ci) {
  var drag = VPB.drag.current(), resizing = VPB.resize.current();
  var mode = respMode(s), phone = mode !== 'same';
  var el = h('div', { class: 'col' + (isSel('col', c.id) ? ' selected' : '') + (c.w <= 4 || phone ? ' narrow' : ''), 'data-id': c.id, 'data-kind': 'col' });
  var st = phone ? '' : 'grid-column:span ' + c.w + ';';
  if (c.bg) st += 'background:' + c.bg + ';padding:12px;';
  if (VPB.isDark(c.bg)) st += 'color:#fff;';
  st += VPB.bgImageCss(c.img);
  el.setAttribute('style', st);
  el.appendChild(h('span', { class: 'chip', text: 'Column ' + (ci + 1) + ', ' + c.w + ' of 12' }));
  var dragging = drag && drag.started;
  var moving = dragging && drag.kind === 'moveBlock' ? drag.id : null;
  var items = c.blocks.filter(function (b) { return b.id !== moving; }).map(function (b, i) { return { b: b, i: i }; });
  if (dragging && drag.target && drag.target.mode === 'col' && drag.target.cid === c.id) items.splice(drag.target.index, 0, { ph: true });
  if (!items.length) {
    var empty = h('div', { class: 'col-empty' }, [h('span', { text: 'Drop blocks here' })]);
    // A section with nothing in any column can be removed right here (its
    // own Delete button only shows once the section itself is selected).
    var sectionEmpty = s.cols.every(function (col) { return !col.blocks.length; });
    if (sectionEmpty && ci === 0 && !state.preview && !dragging) {
      var rm = h('button', { type: 'button', class: 'btn small sec-empty-remove', text: 'Remove this empty section' });
      rm.addEventListener('pointerdown', function (e) { e.stopPropagation(); });
      rm.addEventListener('click', function (e) { e.stopPropagation(); VPB.actions.delSec(s.id); });
      empty.appendChild(rm);
    }
    el.appendChild(empty);
  }
  items.forEach(function (it) {
    if (it.ph) el.appendChild(h('div', { class: 'blk ph', text: 'Drop here' }));
    else el.appendChild(blkEl(it.b, it.i));
  });
  if (!phone && !state.preview && ci < s.cols.length - 1) {
    var active = resizing && resizing.secId === s.id && resizing.ci === ci;
    var r = h('button', { type: 'button', class: 'resize' + (active ? ' active' : ''), 'aria-label': 'Resize columns ' + (ci + 1) + ' and ' + (ci + 2) + ' with the left and right arrow keys' });
    r.appendChild(h('span', {}));
    r.appendChild(h('em', { class: 'w-badge', text: c.w + ' | ' + s.cols[ci + 1].w }));
    r.addEventListener('pointerdown', function (e) { startResize(e, s.id, ci, r); });
    r.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      e.preventDefault();
      shiftBorder(s, ci, e.key === 'ArrowLeft' ? -1 : 1);
      commit(); renderAll();
      var again = document.querySelectorAll('.sec[data-id="' + s.id + '"] .resize');
      if (again[ci]) again[ci].focus();
    });
    el.appendChild(r);
  }
  return el;
}
function blkEl(b, bi) {
  var wrap = h('div', { class: 'blk' + (isSel('block', b.id) ? ' selected' : '') + (b.ld && b.ld.vpb_hidden && !state.preview ? ' is-hidden' : '') + (hiddenAtDev(b) ? ' is-hidden-dev' : ''), 'data-id': b.id, 'data-kind': 'block', 'data-bi': bi });
  if (b.anchor && !state.preview) wrap.appendChild(h('span', { class: 'anchor-tag', title: 'Older link name', text: '#' + VPB.model.slug(b.anchor) }));
  wrap.appendChild(h('span', { class: 'chip', text: blockName(b) }));
  var tools = h('div', { class: 'tools' });
  var hd = toolBtn('Drag block', I.handle, null, 'handle');
  hd.addEventListener('pointerdown', function (e) { e.stopPropagation(); startPointer(e, { kind: 'moveBlock', id: b.id }); });
  tools.appendChild(hd);
  tools.appendChild(toolBtn('Move up', I.up, function () { moveBlock(b.id, -1); }));
  tools.appendChild(toolBtn('Move down', I.down, function () { moveBlock(b.id, 1); }));
  tools.appendChild(toolBtn('Duplicate', I.dup, function () { dupBlock(b.id); }));
  tools.appendChild(toolBtn('Block settings', I.set, openSettings, 'set-btn'));
  // Hide this block on one device (desktop, tablet or phone); the page shows it on the others.
  var hiddenOn = (b.ld && b.ld.vpb_hide ? String(b.ld.vpb_hide) : '').split(',');
  ['desktop', 'tablet', 'phone'].forEach(function (dev) {
    var off = hiddenOn.indexOf(dev) >= 0;
    tools.appendChild(h('button', { type: 'button', class: 'dev-hide', 'aria-pressed': off ? 'true' : 'false', text: dev.charAt(0).toUpperCase() + dev.slice(1), title: (off ? 'Show' : 'Hide') + ' this block on ' + dev, onclick: function (e) { e.stopPropagation(); A.toggleDevice(b.id, dev); } }));
  });
  var hidden = !!(b.ld && b.ld.vpb_hidden);
  if (VPB.actions.isRequired(b)) {
    // Required blocks can be hidden on the public page, never deleted.
    tools.appendChild(h('span', { class: 'req-tag', title: 'Required by this template', text: 'Required' }));
    tools.appendChild(h('button', { type: 'button', class: 'req-hide', 'aria-pressed': hidden ? 'true' : 'false', text: hidden ? 'Show' : 'Hide', title: hidden ? 'Show this block on the page' : 'Hide this block on the page', onclick: function (e) { e.stopPropagation(); A.toggleHidden(b.id); } }));
  } else tools.appendChild(toolBtn('Delete block', I.del, function () { delBlock(b.id); }));
  wrap.appendChild(tools);
  var inner = h('div', { class: 'inner' }, [blockBody(b)]);
  var st = '';
  var pad = paddingAtDev(b);
  if (pad !== null && pad !== undefined) st += 'padding:' + pad + 'px;';
  var text = textAtDev(b);
  if (text) st += 'font-size:' + text + '%;';
  if (b.bg) st += 'background:' + b.bg + ';';
  if (VPB.isDark(b.bg)) st += 'color:#fff;';
  st += VPB.bgImageCss(b.img);
  if (b.align) st += 'text-align:' + b.align + ';';
  inner.setAttribute('style', st);
  wrap.appendChild(inner);
  return wrap;
}

VPB.canvas = { render: renderCanvas };
})(window.VPB);
