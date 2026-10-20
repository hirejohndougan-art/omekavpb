/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * The sidebar's "All elements" view: a Blocks tab (the palette, with search
 * and an info button on every block), a Layers tab (an outline of the page,
 * see layers.js), and a Templates tab (ready-made pages, templates-panel.js).
 *
 * Palette groups, in order: Sections, Text, featured groups (a schema with
 * `paletteFeatured`, e.g. this module's Special Blocks), Omeka's blocks, then
 * any other modules' groups (a block's schema names its group).
 * Each block tile has an ⓘ button that opens a short plain-language
 * description with an "Add this block" button (descriptions: blocks/about.js
 * for Omeka's blocks; other modules send theirs with their schema).
 */
(function (VPB) {
'use strict';

var D = VPB.dom, h = D.h, svg = D.svg, presetIcon = D.presetIcon, I = VPB.icons;
var CFG = VPB.cfg, PRESETS = VPB.PRESETS, known = VPB.util.known;

// Palette view state. Survives re-renders, not page reloads.
var ui = { tab: 'blocks', q: '', info: null };

function closeSheets() { VPB.app.closeSheets(); }
function startPointer(e, info) { VPB.drag.start(e, info); }

/** Every tile the palette can show, grouped. */
function catalog() {
  var groups = [], featured = [];
  groups.push({ title: 'Sections', cls: 'secpal-group', tiles: PRESETS.map(function (p) {
    return { key: 'sec:' + p.name, label: p.name + ' section', search: p.name + ' section columns', icon: svg(presetIcon(p.w), 22), cls: 'secpal',
      down: function (e) { startPointer(e, { kind: 'newSec', preset: p }); }, tap: function () { VPB.actions.addSection(p); } };
  }) });
  if (known('html')) {
    groups.push({ title: 'Text', tiles: [
      { key: 'text:heading', label: 'Heading', icon: svg(I.heading), html: '<h2>New heading</h2>', about: VPB.blocks.about('html:heading') },
      { key: 'text:para', label: 'Paragraph', icon: svg(I.text), html: '<p>Write something here.</p>', about: VPB.blocks.about('html:para') }
    ].map(function (t) {
      t.search = t.label + ' text';
      t.down = function (e) { startPointer(e, { kind: 'newBlock', layout: 'html', html: t.html, label: t.label }); };
      t.tap = function () { VPB.actions.addBlock(VPB.model.newBlock('html', t.html)); };
      return t;
    }) });
  }
  var byGroup = {}, order = [];
  // A header or footer offers its own elements; pages offer theirs.
  // (placement comes from the block's schema: 'page', 'region', or 'both'.)
  var inRegion = !!CFG.region;
  CFG.layouts.forEach(function (l) {
    var placement = l.placement || 'page';
    // Item field blocks (placement "item") only when a Theme Builder template is being edited.
    if (placement === 'item') { if (!CFG.screen) return; }
    else if (inRegion ? !(placement === 'region' || placement === 'both' || l.name === 'lineBreak') : placement === 'region') return;
    var g = l.group || 'Omeka blocks';
    if (inRegion && l.group) g = 'Header & footer';
    if (!byGroup[g]) { byGroup[g] = []; order.push(g); }
    if (l.groupFeatured && featured.indexOf(g) < 0) featured.push(g);
    byGroup[g].push({ key: 'layout:' + l.name, label: l.label, search: l.label + ' ' + (l.description || '') + ' ' + (l.subgroup || ''), icon: svg(I.block, 18), about: VPB.blocks.about(l.name),
      subgroup: inRegion ? '' : (l.subgroup || ''),
      down: function (e) { startPointer(e, { kind: 'newBlock', layout: l.name, label: l.label }); },
      tap: function () { VPB.actions.addBlock(VPB.model.newBlock(l.name)); } });
  });
  order.sort(function (a, b) { return a === 'Omeka blocks' ? -1 : b === 'Omeka blocks' ? 1 : a.localeCompare(b); });
  var rest = [];
  order.forEach(function (g) {
    var group = { title: g, cls: 'pal-all', tiles: byGroup[g] };
    if (featured.indexOf(g) >= 0) groups.push(group); else rest.push(group);
  });
  return groups.concat(rest);
}

function tileEl(t) {
  var wrap = h('div', { class: 'pal-wrap' + (ui.info === t.key ? ' info-open' : '') });
  var b = h('button', { type: 'button', class: 'pal' + (t.cls ? ' ' + t.cls : ''), 'aria-label': 'Add ' + t.label });
  b.innerHTML = t.icon;
  b.appendChild(h('span', { class: 'pal-label', text: t.label }));
  b.addEventListener('pointerdown', t.down);
  b.addEventListener('click', function () { if (VPB.drag.consumeClick()) return; t.tap(); closeSheets(); });
  wrap.appendChild(b);
  if (t.about) {
    var i = h('button', { type: 'button', class: 'pal-info', 'aria-label': 'What does ' + t.label + ' do?', title: 'What does this block do?', 'aria-expanded': String(ui.info === t.key) });
    i.innerHTML = svg(I.info, 16);
    i.addEventListener('click', function () { ui.info = ui.info === t.key ? null : t.key; renderGroups(); });
    wrap.appendChild(i);
  }
  return wrap;
}

function infoPanel(t) {
  return h('div', { class: 'pal-about', role: 'region', 'aria-label': 'About ' + t.label }, [
    h('div', { class: 'pal-about-head' }, [
      h('b', { text: t.label }),
      D.iconBtn('Close', I.x, function () { ui.info = null; renderGroups(); })
    ]),
    h('p', { text: t.about.description }),
    t.about.goodFor ? h('p', { class: 'note' }, [h('b', { text: 'Good for: ' }), t.about.goodFor]) : null,
    t.about.worksWith ? h('p', { class: 'note' }, [h('b', { text: 'Works well with: ' }), t.about.worksWith]) : null,
    h('button', { type: 'button', class: 'btn small', text: 'Add this block', onclick: function () { t.tap(); closeSheets(); } })
  ]);
}

// Special Blocks' smaller groups that are folded closed (remembered).
var folded = (function () { try { return JSON.parse(localStorage.getItem('vpb.palFolded') || '{}') || {}; } catch (e) { return {}; } })();
function saveFolded() { try { localStorage.setItem('vpb.palFolded', JSON.stringify(folded)); } catch (e) { /* private mode */ } }

/**
 * A group's tiles in its smaller groups (Special Blocks: From your
 * collection, Telling history, Maps…), each with a heading that folds it.
 * While searching, every group is open.
 */
function subgroups(host, g, tiles, searching) {
  var order = (CFG.subgroups || []).slice();
  tiles.forEach(function (t) { var s = t.subgroup || 'More'; if (order.indexOf(s) < 0) order.push(s); });
  order.forEach(function (name) {
    var mine = tiles.filter(function (t) { return (t.subgroup || 'More') === name; });
    if (!mine.length) return;
    var open = searching || !folded[name];
    var id = 'palsub-' + name.replace(/[^a-z0-9]+/gi, '-').toLowerCase();
    var head = h('button', { type: 'button', class: 'pal-sub', 'aria-expanded': String(open), 'aria-controls': id }, [
      h('span', { class: 'pal-sub-arrow', 'aria-hidden': 'true', text: open ? '\u25BE' : '\u25B8' }),
      h('span', { text: name }), h('span', { class: 'pal-sub-count', text: String(mine.length) })
    ]);
    head.addEventListener('click', function () { folded[name] = open; saveFolded(); renderGroups(); var again = document.querySelector('[aria-controls="' + id + '"]'); if (again) again.focus(); });
    host.appendChild(head);
    if (!open) return;
    var grid = h('div', { class: 'palette' + (g.cls ? ' ' + g.cls : ''), id: id });
    mine.forEach(function (t) { grid.appendChild(tileEl(t)); });
    host.appendChild(grid);
    mine.forEach(function (t) { if (ui.info === t.key && t.about) host.appendChild(infoPanel(t)); });
  });
}

/** Draw the groups (not the search box, so typing keeps focus). */
function renderGroups() {
  var host = document.getElementById('palGroups');
  if (!host) return;
  host.innerHTML = '';
  var q = ui.q.trim().toLowerCase(), shown = 0;
  catalog().forEach(function (g) {
    var tiles = g.tiles.filter(function (t) { return !q || t.search.toLowerCase().indexOf(q) >= 0; });
    if (!tiles.length) return;
    shown += tiles.length;
    host.appendChild(h('div', { class: 'group-title', text: g.title }));
    if (tiles.some(function (t) { return t.subgroup; })) { subgroups(host, g, tiles, !!q); return; }
    var grid = h('div', { class: 'palette' + (g.cls ? ' ' + g.cls : '') });
    tiles.forEach(function (t) { grid.appendChild(tileEl(t)); });
    host.appendChild(grid);
    tiles.forEach(function (t) { if (ui.info === t.key && t.about) host.appendChild(infoPanel(t)); });
  });
  if (!shown) host.appendChild(h('p', { class: 'note', text: 'No block matches that search.' }));
}

function tabButton(id, label) {
  var on = ui.tab === id;
  var b = h('button', { type: 'button', role: 'tab', id: 'tab-' + id, 'aria-selected': String(on), 'aria-controls': 'pane-' + id, text: label });
  b.addEventListener('click', function () { ui.tab = id; render(); });
  return b;
}

/** Draw the whole "All elements" view. */
function render() {
  var root = document.getElementById('palette');
  root.innerHTML = '';
  root.appendChild(h('div', { class: 'seg pal-tabs', role: 'tablist', 'aria-label': 'Sidebar view' }, [tabButton('blocks', 'Blocks'), tabButton('layers', 'Layers'), tabButton('templates', 'Templates')]));
  if (ui.tab === 'templates') {
    var tpane = h('div', { id: 'pane-templates', role: 'tabpanel', 'aria-labelledby': 'tab-templates' });
    root.appendChild(tpane);
    VPB.templatesPanel.render(tpane);
    return;
  }
  if (ui.tab === 'layers') {
    var pane = h('div', { id: 'pane-layers', role: 'tabpanel', 'aria-labelledby': 'tab-layers' });
    root.appendChild(pane);
    VPB.layers.render(pane);
    return;
  }
  var search = h('input', { type: 'search', id: 'palSearch', placeholder: 'Find a block', 'aria-label': 'Find a block', autocomplete: 'off' });
  search.value = ui.q;
  search.addEventListener('input', function () { ui.q = search.value; renderGroups(); });
  root.appendChild(h('div', { id: 'pane-blocks', role: 'tabpanel', 'aria-labelledby': 'tab-blocks' }, [
    h('div', { class: 'field pal-search' }, [search]),
    h('div', { id: 'palGroups' })
  ]));
  renderGroups();
}

/** After the page changes: only the Layers outline depends on it. */
function refresh() { if (ui.tab === 'layers') render(); }

VPB.palette = { render: render, refresh: refresh };
})(window.VPB);
