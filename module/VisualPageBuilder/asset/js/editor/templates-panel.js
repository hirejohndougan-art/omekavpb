/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * The sidebar's Templates tab: pick a page type, see its templates as small
 * diagrams, and either replace the page with one or add it to the end.
 * Applying is one undo step. Templates themselves are in templates/*.js.
 */
(function (VPB) {
'use strict';

var D = VPB.dom, h = D.h, U = VPB.util;
var ui = { type: 'home' };

/** Short label for a template block: "Text", "Gallery"… */
function label(entry) {
  if (entry[0] === 'html') return /^<h2>/.test(entry[1].html || '') && !/<p>/.test(entry[1].html) ? 'Heading' : 'Text';
  return U.labelFor(entry[0]);
}

/** A small picture of the template: one row per section, one box per column. */
function diagram(tpl) {
  var wrap = h('div', { class: 'tpl-diagram', 'aria-hidden': 'true' });
  tpl.sections.forEach(function (s) {
    var row = h('div', { class: 'tpl-row' + (s.bg ? ' tinted' : '') });
    s.cols.forEach(function (w, ci) {
      var col = h('div', { class: 'tpl-col' });
      col.style.flexGrow = w;
      (s.blocks[ci] || []).forEach(function (entry) { col.appendChild(h('span', { text: label(entry) })); });
      row.appendChild(col);
    });
    wrap.appendChild(row);
  });
  return wrap;
}

/**
 * Turn a template into editor sections. Blocks whose type isn't available
 * (their module is off) are left out and counted.
 */
function build(tpl) {
  var skipped = 0;
  var sections = tpl.sections.map(function (s) {
    var sec = VPB.model.newSection(s.cols.slice());
    if (s.bg) sec.bg = s.bg;
    if (s.pad !== undefined) sec.pad = s.pad;
    sec.cols.forEach(function (col, ci) {
      (s.blocks[ci] || []).forEach(function (entry) {
        if (!U.known(entry[0])) { skipped++; return; }
        var b = VPB.model.newBlock(entry[0], entry[0] === 'html' ? entry[1].html : undefined);
        if (entry[0] !== 'html') b.data = U.clone(entry[1]);
        col.blocks.push(b);
      });
    });
    return sec;
  });
  return { sections: sections, skipped: skipped };
}

function apply(tpl, replace) {
  var state = VPB.store.state;
  var hasContent = state.sections.some(function (s) { return s.cols.some(function (c) { return c.blocks.length; }); });
  if (replace && hasContent && !window.confirm('Replace everything on this page with the \u201C' + tpl.name + '\u201D template? You can undo this.')) return;
  var built = build(tpl);
  // Adding to a page that only has empty sections replaces them, so no gaps.
  state.sections = replace || !hasContent ? built.sections : state.sections.concat(built.sections);
  state.sel = null;
  VPB.store.commit();
  VPB.app.renderAll();
  var msg = (replace ? 'Page replaced with \u201C' : 'Added \u201C') + tpl.name + '\u201D. Replace the [bracketed] text with your own. Undo brings back what was there.';
  if (built.skipped) msg += ' ' + built.skipped + (built.skipped === 1 ? ' block was' : ' blocks were') + ' left out because their type isn\u2019t available.';
  D.toast(msg, 7000);
}

/**
 * A ready-made section goes where a "+" bar was clicked (state.insertAt), or
 * after the selected section, or at the end.
 */
function addSection(tpl) {
  var state = VPB.store.state;
  var built = build(tpl);
  var at = state.sections.length;
  if (typeof state.insertAt === 'number') at = Math.min(state.insertAt, at);
  else if (state.sel && state.sel.kind === 'sec') {
    state.sections.forEach(function (s, i) { if (s.id === state.sel.id) at = i + 1; });
  }
  Array.prototype.splice.apply(state.sections, [at, 0].concat(built.sections));
  state.insertAt = null;
  state.sel = { kind: 'sec', id: built.sections[0].id };
  VPB.store.commit();
  VPB.app.renderAll();
  D.toast('Added the \u201C' + tpl.name + '\u201D section. Replace the [bracketed] text with your own.');
}

function card(tpl) {
  var single = VPB.templates.TYPES.some(function (t) { return t.id === tpl.type && t.single; });
  return h('article', { class: 'tpl-card', 'aria-label': tpl.name }, [
    diagram(tpl),
    h('h3', { text: tpl.name }),
    h('p', { class: 'note', text: tpl.description }),
    h('div', { class: 'tpl-actions' }, single ? [
      h('button', { type: 'button', class: 'btn small', text: 'Add this section', onclick: function () { addSection(tpl); } })
    ] : [
      h('button', { type: 'button', class: 'btn small', text: 'Use for this page', onclick: function () { apply(tpl, true); } }),
      h('button', { type: 'button', class: 'btn small', text: 'Add to the end', onclick: function () { apply(tpl, false); } })
    ])
  ]);
}

/** Draw the tab into `host`. */
function render(host) {
  host.appendChild(h('p', { class: 'note', text: VPB.cfg.region
    ? 'Ready-made ' + VPB.cfg.region + 's to start from. The logo, menu, and visit info fill in from your site settings.'
    : 'Ready-made pages to start from. Replace the [bracketed] text with your own; photo and item blocks fill in from your collection.' }));
  // A header shows header templates, a footer footer templates, and pages
  // only page templates.
  var region = VPB.cfg.region || null;
  var types = VPB.templates.TYPES.filter(function (t) { return (t.region || null) === region; });
  if (!types.some(function (t) { return t.id === ui.type; })) ui.type = types[0].id;
  var sel = h('select', { id: 'tplType', 'aria-label': 'Kind of page' });
  types.forEach(function (t) {
    var o = h('option', { value: t.id, text: t.label + ' (' + VPB.templates.ofType(t.id).length + ')' });
    if (t.id === ui.type) o.selected = true;
    sel.appendChild(o);
  });
  var list = h('div', { class: 'tpl-list' });
  function draw() {
    list.innerHTML = '';
    VPB.templates.ofType(ui.type).forEach(function (tpl) { list.appendChild(card(tpl)); });
  }
  sel.addEventListener('change', function () { ui.type = sel.value; draw(); });
  host.appendChild(h('div', { class: 'field' }, [h('label', { for: 'tplType', text: 'Kind of page' }), sel]));
  host.appendChild(list);
  draw();
}

VPB.templatesPanel = { render: render, build: build, apply: apply, addSection: addSection };
})(window.VPB);
