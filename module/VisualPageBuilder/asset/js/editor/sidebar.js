/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * The sidebar: the "Add" palette, the settings panel for the selected section,
 * column, or block, the selection bar, and the first-open notice.
 */
(function (VPB) {
'use strict';

var D = VPB.dom, h = D.h, svg = D.svg, presetIcon = D.presetIcon, iconBtn = D.iconBtn, I = VPB.icons;
var U = VPB.util, obj = U.obj, known = U.known, labelFor = U.labelFor;
var F = VPB.fields, segField = F.segField, padField = F.padField, bgField = F.bgField, classField = F.classField;
var S = VPB.store, state = S.state, A = VPB.actions;
var findSec = S.findSec, findCol = S.findCol, findBlock = S.findBlock;
var CFG = VPB.cfg, PRESETS = VPB.PRESETS;
function commit() { S.commit(); }
function renderAll() { VPB.app.renderAll(); }
function select(sel) { VPB.app.select(sel); }
function blockName(b) { return VPB.blocks.name(b); }
function setLayout(s, ws) { A.setLayout(s, ws); }
function addColumn(s) { A.addColumn(s); }
function removeColumn(id) { A.removeColumn(id); }
function adjustColWidth(id, d) { A.adjustColWidth(id, d); }

function pathBar(parts) {
  var bar = h('nav', { class: 'path', 'aria-label': 'Selection path' });
  parts.forEach(function (p, i) {
    if (i) bar.appendChild(h('span', { text: '\u203A', 'aria-hidden': 'true' }));
    if (p.sel) bar.appendChild(h('button', { type: 'button', text: p.label, onclick: function () { select(p.sel); } }));
    else bar.appendChild(h('span', { text: p.label }));
  });
  return bar;
}
function widthRow(labelText, c, ci) {
  return h('div', { class: 'wrow' }, [
    h('span', { class: 'wl', text: labelText }),
    iconBtn('Make column ' + (ci + 1) + ' narrower', I.minus, function () { adjustColWidth(c.id, -1); }),
    iconBtn('Make column ' + (ci + 1) + ' wider', I.plus, function () { adjustColWidth(c.id, 1); })
  ]);
}
function renderProps() {
  var p = document.getElementById('propsView');
  p.innerHTML = '';
  var back = h('button', { type: 'button', class: 'back-btn' });
  back.innerHTML = svg(I.back, 16);
  back.appendChild(h('span', { text: 'All elements' }));
  back.addEventListener('click', function () { state.panel = 'add'; renderSide(); });
  p.appendChild(back);
  var sel = state.sel;
  if (!sel) return;

  if (sel.kind === 'sec') {
    var rs = findSec(sel.id), s = rs.sec;
    p.appendChild(h('div', { class: 'p-head' }, [h('h2', { text: 'Section ' + (rs.si + 1) }), h('p', { text: s.cols.length + (s.cols.length === 1 ? ' column' : ' columns') })]));
    var lay = h('div', { class: 'p-sec' }, [h('h3', { text: 'Columns' })]);
    var ws = s.cols.map(function (c) { return c.w; });
    var pr = h('div', { class: 'presets', role: 'group', 'aria-label': 'Column layout' });
    PRESETS.forEach(function (ps) {
      var same = ps.w.length === ws.length && ps.w.every(function (v, i) { return v === ws[i]; });
      var b = h('button', { type: 'button', 'aria-label': ps.name, title: ps.name, 'aria-pressed': String(same) });
      b.innerHTML = svg(presetIcon(ps.w), 24);
      b.addEventListener('click', function () { setLayout(s, ps.w.slice()); commit(); renderAll(); });
      pr.appendChild(b);
    });
    lay.appendChild(h('div', { class: 'field' }, [h('span', { class: 'lbl', text: 'Layout' }), pr]));
    var wf = h('div', { class: 'field' }, [h('span', { class: 'lbl', text: 'Column widths (out of 12)' })]);
    s.cols.forEach(function (c, ci) { wf.appendChild(widthRow('Column ' + (ci + 1) + ': ' + c.w, c, ci)); });
    lay.appendChild(wf);
    lay.appendChild(h('button', { type: 'button', class: 'btn small', text: 'Add a column', onclick: function () { addColumn(s); } }));
    lay.appendChild(h('p', { class: 'note', text: 'You can also drag the bar between two columns on the page. On phones, columns stack one above the other.' }));
    p.appendChild(lay);
    var st = h('div', { class: 'p-sec' }, [h('h3', { text: 'Style' })]);
    st.appendChild(bgField(s, 'f-sbg'));
    st.appendChild(F.bgImageField(s, 'f-simg'));
    var oldName = F.anchorField(s, 'f-sanchor'); // only where an older Link name is set
    if (oldName) st.appendChild(oldName);
    if (!VPB.cfg.isShared && !VPB.cfg.region) {
      var mk = h('button', { type: 'button', class: 'btn small', text: 'Make this a shared section\u2026', title: 'Save this section to reuse on other pages; change it once and every page updates' });
      mk.addEventListener('click', function () { VPB.sharedSections.makeShared(s); });
      st.appendChild(h('div', { class: 'field' }, [mk]));
    }
    // Columns on smaller screens (saved with the section; see public.css).
    if (s.cols.length > 1) {
      var resp = VPB.model.cleanResp(s.resp);
      var small = h('div', { class: 'p-sec' }, [h('h3', { text: 'Smaller screens' })]);
      small.appendChild(segField('On tablets', [['stack', 'One per row'], ['two', 'Two per row'], ['same', 'As on desktop']], resp.tablet, function (v) { s.resp = Object.assign(VPB.model.cleanResp(s.resp), { tablet: v }); }));
      small.appendChild(segField('On phones', [['stack', 'One per row'], ['two', 'Two per row']], resp.phone, function (v) { s.resp = Object.assign(VPB.model.cleanResp(s.resp), { phone: v }); }));
      small.appendChild(F.note('Use Tablet and Phone at the top to see it.'));
      st.appendChild(small);
    }
    st.appendChild(padField(s, 'f-spad'));
    st.appendChild(classField(s, 'f-scls'));
    p.appendChild(st);
    return;
  }

  if (sel.kind === 'col') {
    var rc = findCol(sel.id), c = rc.col;
    p.appendChild(pathBar([{ label: 'Section ' + (rc.si + 1), sel: { kind: 'sec', id: rc.sec.id } }, { label: 'Column ' + (rc.ci + 1) }]));
    p.appendChild(h('div', { class: 'p-head' }, [h('h2', { text: 'Column ' + (rc.ci + 1) + ' of ' + rc.sec.cols.length }), h('p', { text: c.blocks.length + (c.blocks.length === 1 ? ' block' : ' blocks') })]));
    var cl = h('div', { class: 'p-sec' }, [h('h3', { text: 'Width' })]);
    var bar = h('div', { class: 'span-bar', 'aria-hidden': 'true' });
    for (var i = 0; i < 12; i++) bar.appendChild(h('i', { class: i < c.w ? 'on' : null }));
    cl.appendChild(bar);
    cl.appendChild(widthRow(c.w + ' of 12 columns', c, rc.ci));
    cl.appendChild(h('p', { class: 'note', text: 'Widening this column narrows the one next to it, so the section always adds up to 12.' }));
    p.appendChild(cl);
    var cs = h('div', { class: 'p-sec' }, [h('h3', { text: 'Style' })]);
    cs.appendChild(bgField(c, 'f-cbg'));
    cs.appendChild(F.bgImageField(c, 'f-cimg'));
    p.appendChild(cs);
    var cr = h('div', { class: 'p-sec' });
    cr.appendChild(h('button', { type: 'button', class: 'btn small danger', text: 'Remove this column', onclick: function () { removeColumn(c.id); } }));
    cr.appendChild(h('p', { class: 'note', text: 'Any blocks in it move to the column beside it.' }));
    p.appendChild(cr);
    return;
  }

  var rb = findBlock(sel.id), b = rb.block;
  p.appendChild(pathBar([{ label: 'Section ' + (rb.si + 1), sel: { kind: 'sec', id: rb.sec.id } }, { label: 'Column ' + (rb.ci + 1), sel: { kind: 'col', id: rb.col.id } }, { label: blockName(b) }]));
  var help = VPB.blocks.about(b);
  p.appendChild(h('div', { class: 'p-head' }, [
    h('h2', { text: blockName(b) }),
    h('p', { text: known(b.layout) ? 'Block type: ' + labelFor(b.layout) : 'Unavailable block type' }),
    help ? h('p', { class: 'p-about', text: help.description }) : null
  ]));
  var c2 = h('div', { class: 'p-sec' }, [h('h3', { text: 'Content' })]);
  c2.appendChild(VPB.blocks.sidebar(b));
  p.appendChild(c2);
  var l = h('div', { class: 'p-sec' }, [h('h3', { text: 'Layout' })]);
  l.appendChild(h('p', { class: 'note', text: 'A block fills the width of its column. To change the width, select the column or drag the bar between columns.' }));
  // Blocks with their own Alignment setting (logo, menu, social links…) use
  // that; a second, general one here wouldn't move them and only confuses.
  var layoutInfo = CFG.layouts.filter(function (x) { return x.name === b.layout; })[0];
  var ownAlign = layoutInfo && layoutInfo.schema && layoutInfo.schema.fields.some(function (f) { return f.key === 'align'; });
  if (ownAlign) {
    l.appendChild(h('p', { class: 'note', text: 'Alignment for this block is under Content, above.' }));
  } else {
    l.appendChild(segField('Text alignment', [['', 'Default'], ['left', 'Left'], ['center', 'Center'], ['right', 'Right']], b.align || '', function (v) { b.align = v; }));
  }
  l.appendChild(padField(b, 'f-pad'));
  // Padding on smaller screens, for this block only (blank: same as desktop).
  l.appendChild(F.devPadField(b, 'tablet', 'f-pad-tablet'));
  l.appendChild(F.devPadField(b, 'phone', 'f-pad-phone'));
  l.appendChild(F.devTextField(b, 'tablet', 'f-text-tablet'));
  l.appendChild(F.devTextField(b, 'phone', 'f-text-phone'));
  p.appendChild(l);
  var s2 = h('div', { class: 'p-sec' }, [h('h3', { text: 'Style' })]);
  s2.appendChild(bgField(b, 'f-bg'));
  s2.appendChild(F.bgImageField(b, 'f-img'));
  var oldBlockName = F.anchorField(b, 'f-anchor'); // only where an older Link name is set
  if (oldBlockName) s2.appendChild(oldBlockName);
  s2.appendChild(classField(b, 'f-cls'));
  p.appendChild(s2);
}
function selName() {
  var s = state.sel; if (!s) return '';
  if (s.kind === 'sec') { var r = findSec(s.id); return r ? 'Section ' + (r.si + 1) : ''; }
  if (s.kind === 'col') { var c = findCol(s.id); return c ? 'Column ' + (c.ci + 1) + ' of section ' + (c.si + 1) : ''; }
  var b = findBlock(s.id); return b ? blockName(b.block) : '';
}
function renderSelBar() {
  var bar = document.getElementById('selBar');
  bar.innerHTML = '';
  if (!state.sel || state.preview) return;
  bar.appendChild(h('div', { class: 'selbar' }, [
    h('span', {}, ['Selected: ', h('b', { text: selName() })]),
    h('button', { type: 'button', text: 'Settings', onclick: function () { state.panel = 'settings'; renderSide(); } })
  ]));
}
function renderSide() {
  var sheetOpen = VPB.app.isSheetOpen();
  if (state.panel === 'settings' && !state.sel) state.panel = 'add';
  document.getElementById('palView').hidden = state.panel !== 'add';
  document.getElementById('propsView').hidden = state.panel !== 'settings';
  if (state.panel === 'settings') renderProps(); else { renderSelBar(); VPB.palette.refresh(); }
  document.getElementById('openBlocks').setAttribute('aria-pressed', String(sheetOpen && state.panel === 'add'));
  document.getElementById('openProps').setAttribute('aria-pressed', String(sheetOpen && state.panel === 'settings'));
}
function renderNotice() {
  var n = document.getElementById('notice');
  n.innerHTML = '';
  var ld = obj(CFG.page['o:layout_data']);
  if (!ld.visual_page_builder) {
    n.appendChild(h('div', { class: 'notice', text: 'This page was made in Omeka\u2019s standard editor. The first time you save here, its blocks are arranged into sections and the page switches to Omeka\u2019s Grid layout. Every block and its settings are kept.' }));
  }
  if (CFG.region) {
    n.innerHTML = ''; // Only this note for the site's header or footer.
    n.appendChild(h('div', { class: 'notice', text: CFG.region === 'header'
      ? 'This is the site\u2019s header: it\u2019s shown at the top of every page by the Companion Theme for Visual Page Builder. Start from a header template (Templates tab) or build your own with Logo, Site menu, Search box, and other blocks.'
      : 'This is the site\u2019s footer: it\u2019s shown at the bottom of every page by the Companion Theme for Visual Page Builder. Start from a footer template (Templates tab) or build your own.' }));
    return;
  }
  if (ld.template_name) {
    n.appendChild(h('div', { class: 'notice', text: 'This page uses the theme\u2019s \u201C' + ld.template_name + '\u201D page template, which lays out blocks its own way and would ignore your columns. Saving here switches the page to the standard template so it looks the way you build it.' }));
  }
}

VPB.sidebar = { renderProps: renderProps, renderSide: renderSide, renderNotice: renderNotice };
})(window.VPB);
