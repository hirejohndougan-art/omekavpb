/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Form controls for the settings sidebar. Each returns a `.field` element.
 *
 * Controls that change data call `onSet`/`onPick` with the new value and then
 * record an undo step and refresh the page preview. Segmented buttons re-render
 * the whole editor so their pressed state updates.
 */
(function (VPB) {
'use strict';

var D = VPB.dom, h = D.h, svg = D.svg, toast = D.toast, I = VPB.icons;
var SWATCHES = VPB.SWATCHES, HEX_RE = VPB.HEX_RE;
function commit() { VPB.store.commit(); }
function renderAll() { VPB.app.renderAll(); }
function renderCanvas() { VPB.canvas.render(); }
function refreshStatus() { VPB.app.refreshStatus(); }
/** A value changed: record an undo step and refresh the preview. */
function changed() { commit(); renderCanvas(); refreshStatus(); }

function field(labelText, id, control) { return h('div', { class: 'field' }, [h('label', { for: id, text: labelText }), control]); }
function segField(labelText, opts, cur, onPick) {
  var wrap = h('div', { class: 'segw', role: 'group', 'aria-label': labelText });
  opts.forEach(function (o) {
    var btn = h('button', { type: 'button', 'aria-pressed': String(o[0] === cur), text: o[1] });
    btn.addEventListener('click', function () { onPick(o[0]); commit(); renderAll(); });
    wrap.appendChild(btn);
  });
  return h('div', { class: 'field' }, [h('span', { class: 'lbl', text: labelText }), wrap]);
}
function padField(target, id) {
  var cur = target.pad;
  var out = h('span', { class: 'val', text: cur === null || cur === undefined ? 'Theme default' : cur + ' px' });
  var r = h('input', { type: 'range', id: id, min: 0, max: 64, step: 4 });
  r.value = cur || 0;
  r.addEventListener('input', function () { target.pad = +r.value; out.textContent = target.pad + ' px'; renderCanvas(); refreshStatus(); });
  r.addEventListener('change', commit);
  var reset = h('button', { type: 'button', class: 'btn small', text: 'Use theme default' });
  reset.addEventListener('click', function () { target.pad = null; commit(); renderAll(); });
  return h('div', { class: 'field' }, [h('label', { for: id, text: 'Padding' }), r, h('div', { class: 'hex-row' }, [out, reset])]);
}
/**
 * Background image for a section, column, or block (target.img): choose an
 * image from Assets, how it fills the area, where it sits, and an optional
 * dark layer over it that also turns the text white.
 */
function bgImageField(target, id) {
  var img = target.img || null;
  var box = h('div', { class: 'field' }, [h('span', { class: 'lbl', text: 'Background image' })]);
  function set(next) { target.img = next; commit(); renderAll(); }
  if (img && img.asset) {
    var ref = VPB.refs.assets[img.asset];
    box.appendChild(h('div', { class: 'att' }, [h('div', { class: 'att-head' }, [
      ref ? h('img', { class: 'pk-thumb wide', src: ref.thumb, alt: '' }) : null,
      h('span', { class: 'pk-t' }, [h('b', { text: ref ? ref.title : 'Asset #' + img.asset })]),
      VPB.dom.iconBtn('Remove background image', VPB.icons.del, function () { set(null); })
    ])]));
  }
  box.appendChild(VPB.picker.host(target, 'assets', function (row) {
    VPB.refs.assets[row.id] = row;
    set(Object.assign({ size: 'cover', pos: 'center', overlay: false }, img || {}, { asset: row.id }));
  }, { slot: 'bgimg', single: true, label: img ? 'Change image\u2026' : 'Choose an image\u2026' }));
  if (img && img.asset) {
    box.appendChild(segField('Fit', [['cover', 'Fill the area'], ['contain', 'Whole picture']], img.size, function (v) { img.size = v; }));
    box.appendChild(segField('Position', [['top', 'Top'], ['center', 'Center'], ['bottom', 'Bottom']], img.pos, function (v) { img.pos = v; }));
    var cb = h('input', { type: 'checkbox', id: id + '-ov' });
    cb.checked = !!img.overlay;
    cb.addEventListener('change', function () { img.overlay = cb.checked; commit(); renderAll(); });
    box.appendChild(h('label', { class: 'check', for: id + '-ov' }, [cb, document.createTextNode(' Darken it so text is readable (text turns white)')]));
  } else {
    box.appendChild(note('Upload images under Assets in Omeka\u2019s admin first.'));
  }
  return box;
}
/**
 * Every spot on the page that can be linked to (#id) right now: headings,
 * Table links, and older link names, with the ids the site gives them
 * (see toc.js). [{id, label, kind, item}]
 */
function anchorsInUse() {
  return VPB.toc.plan();
}

/**
 * An older Link name on a section or block (before v1.14.0, when headings
 * and Table links took over). Only shown where one is set, so existing
 * links keep working and it can still be changed or cleared. Copy link
 * copies the full address.
 */
function anchorField(target, id) {
  if (!target.anchor) return null;
  var box = h('div', { class: 'field anchor-field' });
  box.appendChild(h('label', { for: id, text: 'Older link name' }));
  var input = h('input', { type: 'text', id: id, spellcheck: 'false' });
  input.value = target.anchor || '';
  var hint = h('p', { class: 'note' });
  var copy = h('button', { type: 'button', class: 'btn small', text: 'Copy link' });
  var labelIn = h('input', { type: 'text', id: id + '-label', placeholder: 'e.g. Visiting us' });
  labelIn.value = target.anchorLabel || '';
  function show() {
    var s = VPB.model.slug(input.value);
    var clash = s && anchorsInUse().some(function (a) { return a.id === s && a.item !== target; });
    hint.textContent = !s ? 'Cleared: this spot will no longer have its own link once you save.'
      : clash ? 'Another spot on this page already uses #' + s + '. Choose a different name.'
      : 'Links to \u2026/' + (VPB.cfg.page && VPB.cfg.page['o:slug'] || 'page') + '#' + s + ' keep working, and it\u2019s listed in the Table of contents. For new spots, use a heading or a Table link instead; clear this to remove it.';
    hint.classList.toggle('warn', !!clash);
    copy.disabled = !s;
  }
  input.addEventListener('input', function () { target.anchor = input.value; show(); VPB.app.refreshStatus(); });
  input.addEventListener('change', function () { target.anchor = VPB.model.slug(input.value); input.value = target.anchor; show(); commit(); VPB.canvas.render(); });
  labelIn.addEventListener('change', function () { target.anchorLabel = labelIn.value.trim(); commit(); renderAll(); });
  copy.addEventListener('click', function () {
    var url = (VPB.cfg.publicUrl || location.href) + '#' + VPB.model.slug(input.value);
    var done = function () { toast('Link copied: ' + url); };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(url).then(done, function () { window.prompt('Copy this link:', url); });
    else window.prompt('Copy this link:', url);
  });
  box.appendChild(h('div', { class: 'hex-row' }, [input, copy]));
  box.appendChild(hint);
  box.appendChild(h('label', { for: id + '-label', text: 'Name in the table of contents (optional)' }));
  box.appendChild(labelIn);
  show();
  return box;
}
/** "#abc" → "#aabbcc" (the color picker needs six digits). */
function full(hex) {
  if (!hex || !HEX_RE.test(hex)) return null;
  return hex.length === 4 ? '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3] : hex.toLowerCase();
}
function bgField(target, id) {
  var sw = h('div', { class: 'swatches', role: 'group', 'aria-label': 'Background' });
  SWATCHES.forEach(function (o) {
    var btn = h('button', { type: 'button', class: 'sw', 'aria-label': o.label, title: o.label, 'aria-pressed': String((target.bg || null) === o.hex) });
    btn.style.background = o.hex || 'var(--panel)';
    if (!o.hex) btn.innerHTML = svg(I.none, 16);
    btn.addEventListener('click', function () { target.bg = o.hex; commit(); renderAll(); });
    sw.appendChild(btn);
  });
  // Custom color: a color picker and a code box, kept in step.
  var hex = h('input', { type: 'text', id: id, placeholder: '#RRGGBB', maxlength: 7, 'aria-label': 'Custom color code' });
  hex.value = target.bg || '';
  var picker = h('input', { type: 'color', id: id + '-pick', class: 'color-pick', 'aria-label': 'Pick a custom color', title: 'Pick any color' });
  picker.value = full(target.bg) || '#ffffff';
  hex.addEventListener('change', function () {
    var v = hex.value.trim();
    if (v === '') { target.bg = null; commit(); renderAll(); return; }
    if (v[0] !== '#') v = '#' + v;
    if (!HEX_RE.test(v)) { toast('Enter a color like #F1EEE4.'); hex.value = target.bg || ''; return; }
    target.bg = v.toUpperCase(); commit(); renderAll();
  });
  // While dragging in the picker, show the color live; save one undo step at the end.
  picker.addEventListener('input', function () { target.bg = picker.value.toUpperCase(); hex.value = target.bg; renderCanvas(); refreshStatus(); });
  picker.addEventListener('change', function () { target.bg = picker.value.toUpperCase(); commit(); renderAll(); });
  var darkNote = target.bg && VPB.isDark(target.bg) ? h('p', { class: 'note', text: 'Dark background: text on it turns white so it stays readable.' }) : null;
  return h('div', { class: 'field' }, [
    h('span', { class: 'lbl', text: 'Background' }), sw,
    h('label', { for: id + '-pick', text: 'Custom color' }),
    h('div', { class: 'hex-row' }, [picker, hex]),
    darkNote
  ]);
}
function classField(target, id) {
  var inp = h('input', { type: 'text', id: id, placeholder: 'optional' });
  inp.value = target.cls || '';
  inp.addEventListener('change', function () { target.cls = inp.value.trim(); commit(); });
  return field('CSS class', id, inp);
}
function selectField(labelText, id, opts, cur, onPick) {
  var sel = h('select', { id: id });
  opts.forEach(function (o) {
    var op = h('option', { value: o[0], text: o[1] });
    if (String(o[0]) === String(cur)) op.selected = true;
    sel.appendChild(op);
  });
  sel.addEventListener('change', function () { onPick(sel.value); changed(); });
  return field(labelText, id, sel);
}
function textField(labelText, id, cur, onSet, attrs) {
  var inp = h('input', Object.assign({ type: 'text', id: id }, attrs || {}));
  inp.value = cur === null || cur === undefined ? '' : String(cur);
  inp.addEventListener('change', function () { onSet(inp.value); changed(); });
  return field(labelText, id, inp);
}
function numberField(labelText, id, cur, onSet, attrs) {
  return textField(labelText, id, cur, function (v) {
    v = v.trim();
    onSet(/^\d+$/.test(v) ? String(parseInt(v, 10)) : '');
  }, Object.assign({ inputmode: 'numeric', pattern: '[0-9]*' }, attrs || {}));
}
function checkField(labelText, id, on, onSet) {
  var inp = h('input', { type: 'checkbox', id: id });
  inp.checked = !!on;
  inp.addEventListener('change', function () { onSet(inp.checked); changed(); });
  return h('div', { class: 'field check' }, [h('label', { for: id }, [inp, h('span', { text: labelText })])]);
}
function note(text) { return h('p', { class: 'note', text: text }); }

VPB.fields = {
  bgImageField: bgImageField, anchorField: anchorField, anchorsInUse: anchorsInUse,
  field: field, segField: segField, padField: padField, bgField: bgField, classField: classField,
  selectField: selectField, textField: textField, numberField: numberField, checkField: checkField,
  note: note, changed: changed
};
})(window.VPB);
