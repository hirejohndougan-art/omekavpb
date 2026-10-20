/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Menu builder: edit a site's menu (Omeka's site navigation) and pick a menu
 * style, with a live preview. Server side: Controller/Admin/MenuController.
 *
 * The menu is a tree of links in Omeka's own format:
 *   { type: 'page' | 'url' | 'browse' | 'browseItemSets', data: {label, id | url | query}, links: [...] }
 * Items are edited in place; reordering uses buttons (up, down, indent,
 * outdent) so it works with a keyboard and on phones.
 */
(function () {
'use strict';

var CFG = JSON.parse(document.getElementById('vpb-menu-config').textContent);
var MAX_DEPTH = 4;
var state = { links: JSON.parse(JSON.stringify(CFG.navigation || [])), style: CFG.style, colors: Object.assign({}, CFG.colors || {}), device: 'desktop', saved: '' };
var PAGES = {};
CFG.pages.forEach(function (p) { PAGES[p.id] = p.title; });

/* ---------- helpers ---------- */
function h(tag, attrs, kids) {
  var el = document.createElement(tag);
  Object.keys(attrs || {}).forEach(function (k) {
    var v = attrs[k];
    if (v === null || v === undefined || v === false) return;
    if (k === 'class') el.className = v;
    else if (k === 'text') el.textContent = v;
    else if (k.indexOf('on') === 0) el.addEventListener(k.slice(2), v);
    else el.setAttribute(k, v === true ? '' : v);
  });
  (kids || []).forEach(function (c) { if (c) el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
  return el;
}
function snap() { return JSON.stringify([state.links, state.style, cleanColors(state.colors)]); }
var COLOR_KEYS = ['bar', 'link', 'hover', 'dropBg', 'dropLink'];
/** Only set, valid colors, in a fixed order (as MenuSettings::cleanColors). */
function cleanColors(c) { var o = {}; COLOR_KEYS.forEach(function (k) { if (c && /^#[0-9a-f]{6}$/i.test(c[k] || '')) o[k] = c[k].toLowerCase(); }); return o; }
function dirty() { return snap() !== state.saved; }
var toastTimer;
function toast(msg) {
  var t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  clearTimeout(toastTimer); toastTimer = setTimeout(function () { t.classList.remove('show'); }, 3400);
}
function status() {
  var s = document.getElementById('status');
  s.textContent = dirty() ? 'Unsaved changes' : 'All changes saved';
  s.className = 'status' + (dirty() ? ' dirty' : '');
}
/** What a link is called in the menu. */
function labelOf(link) {
  var d = link.data || {};
  if (d.label) return d.label;
  if (link.type === 'page') return PAGES[d.id] || 'Missing page';
  if (link.type === 'browse') return 'Browse';
  if (link.type === 'browseItemSets') return 'Collections';
  return d.url || 'Link';
}
function kindOf(link) {
  return { page: 'Page', url: 'Web address', browse: 'Browse items', browseItemSets: 'Browse collections' }[link.type] || link.type;
}
function depthOf(list) {
  var d = 0;
  list.forEach(function (l) { d = Math.max(d, 1 + depthOf(l.links || [])); });
  return d;
}

/* ---------- tree editing ---------- */
function changed() { renderTree(); renderPreview(); redrawStyles(); renderColors(); status(); }
/** A style or color change: no tree to redraw. */
function lookChanged() { renderPreview(); redrawStyles(); renderColors(); status(); }
// The small pictures on the style cards use your links: redraw them (after a pause while typing).
var stylesTimer = null;
function redrawStyles(wait) {
  clearTimeout(stylesTimer);
  stylesTimer = setTimeout(function () {
    var had = document.activeElement && document.activeElement.name === 'mbStyle' ? document.activeElement.id : null;
    renderStyles();
    if (had && document.getElementById(had)) document.getElementById(had).focus();
  }, wait || 0);
}

function move(list, i, dir) {
  var j = i + dir;
  if (j < 0 || j >= list.length) return;
  var t = list[i]; list[i] = list[j]; list[j] = t;
  changed();
}
function indent(list, i, level) {
  if (i === 0) { toast('The first item can\u2019t be indented: there\u2019s nothing above it to go under.'); return; }
  if (level + depthOf([list[i]]) > MAX_DEPTH) { toast('Menus can be at most ' + MAX_DEPTH + ' levels deep.'); return; }
  var item = list.splice(i, 1)[0];
  list[i - 1].links = (list[i - 1].links || []).concat([item]);
  changed();
}
function outdent(list, i, parentList, parentIndex) {
  if (!parentList) return;
  var item = list.splice(i, 1)[0];
  parentList.splice(parentIndex + 1, 0, item);
  changed();
}

function iconBtn(label, text, fn, disabled) {
  return h('button', { type: 'button', class: 'icon-btn mb-tool', 'aria-label': label, title: label, text: text, disabled: !!disabled, onclick: fn });
}

function itemEditor(link) {
  var box = h('div', { class: 'mb-edit' });
  var id = 'mb-' + Math.random().toString(36).slice(2, 8);
  var label = h('input', { type: 'text', id: id + '-label', placeholder: link.type === 'page' ? 'Leave empty to use the page title' : '' });
  label.value = (link.data && link.data.label) || '';
  label.addEventListener('input', function () { link.data.label = label.value; renderPreview(); redrawStyles(400); status(); });
  label.addEventListener('change', renderTree);
  box.appendChild(h('div', { class: 'field' }, [h('label', { for: id + '-label', text: 'Label' }), label]));
  if (link.type === 'url') {
    var url = h('input', { type: 'text', id: id + '-url', placeholder: 'https://… or /s/…' });
    url.value = link.data.url || '';
    url.addEventListener('input', function () { link.data.url = url.value.trim(); status(); });
    box.appendChild(h('div', { class: 'field' }, [h('label', { for: id + '-url', text: 'Address' }), url]));
  }
  if (link.type === 'page') {
    var sel = h('select', { id: id + '-page' });
    CFG.pages.forEach(function (p) {
      var o = h('option', { value: String(p.id), text: p.title });
      if (p.id === link.data.id) o.selected = true;
      sel.appendChild(o);
    });
    sel.addEventListener('change', function () { link.data.id = parseInt(sel.value, 10); changed(); });
    box.appendChild(h('div', { class: 'field' }, [h('label', { for: id + '-page', text: 'Page' }), sel]));
  }
  if (link.type === 'browse' || link.type === 'browseItemSets') {
    var q = h('input', { type: 'text', id: id + '-query', placeholder: 'Optional, e.g. fulltext_search=railroad' });
    q.value = link.data.query || '';
    q.addEventListener('input', function () { link.data.query = q.value.replace(/^.*\?/, ''); status(); });
    box.appendChild(h('div', { class: 'field' }, [h('label', { for: id + '-query', text: 'Only items matching this search (optional)' }), q]));
  }
  return box;
}

var openItem = null; // The link being edited (object reference).

function renderList(list, level, parentList, parentIndex) {
  var ul = h('ul', { class: 'mb-list', role: level === 1 ? 'none' : 'group' });
  list.forEach(function (link, i) {
    link.data = link.data || {};
    link.links = link.links || [];
    var name = labelOf(link);
    var li = h('li', { class: 'mb-item' + (openItem === link ? ' open' : ''), role: 'treeitem', 'aria-level': String(level), 'aria-expanded': link.links.length ? 'true' : null });
    var row = h('div', { class: 'mb-row' }, [
      h('button', { type: 'button', class: 'mb-name', 'aria-expanded': String(openItem === link), onclick: function () { openItem = openItem === link ? null : link; renderTree(); } }, [
        h('b', { text: name }), h('small', { text: kindOf(link) })
      ]),
      iconBtn('Move ' + name + ' up', '\u2191', function () { move(list, i, -1); }, i === 0),
      iconBtn('Move ' + name + ' down', '\u2193', function () { move(list, i, 1); }, i === list.length - 1),
      iconBtn('Indent ' + name + ' (make it a sub-item)', '\u2192', function () { indent(list, i, level); }, i === 0),
      iconBtn('Outdent ' + name, '\u2190', function () { outdent(list, i, parentList, parentIndex); }, !parentList),
      iconBtn('Remove ' + name, '\u00D7', function () {
        if (link.links.length && !window.confirm('Remove \u201C' + name + '\u201D and the ' + link.links.length + ' item(s) under it?')) return;
        list.splice(i, 1); changed();
      })
    ]);
    li.appendChild(row);
    if (openItem === link) li.appendChild(itemEditor(link));
    if (link.links.length) li.appendChild(renderList(link.links, level + 1, list, i));
    ul.appendChild(li);
  });
  return ul;
}

function renderTree() {
  var host = document.getElementById('mbTree');
  host.innerHTML = '';
  if (!state.links.length) host.appendChild(h('p', { class: 'note', text: 'The menu is empty. Add pages or links below.' }));
  else host.appendChild(renderList(state.links, 1, null, null));
}

/* ---------- adding ---------- */
function renderAdd() {
  var host = document.getElementById('mbAdd');
  host.innerHTML = '';
  var sel = h('select', { id: 'mbAddPage', 'aria-label': 'Page to add' });
  CFG.pages.forEach(function (p) { sel.appendChild(h('option', { value: String(p.id), text: p.title })); });
  host.appendChild(h('div', { class: 'mb-add-row' }, [sel, h('button', { type: 'button', class: 'btn small', text: 'Add page', onclick: function () {
    state.links.push({ type: 'page', data: { label: '', id: parseInt(sel.value, 10), is_public: true }, links: [] }); changed();
  } })]));
  var label = h('input', { type: 'text', id: 'mbAddLabel', placeholder: 'Label', 'aria-label': 'Link label' });
  var url = h('input', { type: 'text', id: 'mbAddUrl', placeholder: 'https://… or /s/…', 'aria-label': 'Link address' });
  host.appendChild(h('div', { class: 'mb-add-row' }, [label, url, h('button', { type: 'button', class: 'btn small', text: 'Add link', onclick: function () {
    if (!label.value.trim() || !url.value.trim()) { toast('Give the link a label and an address.'); return; }
    state.links.push({ type: 'url', data: { label: label.value.trim(), url: url.value.trim() }, links: [] });
    label.value = ''; url.value = ''; changed();
  } })]));
  host.appendChild(h('div', { class: 'mb-add-row' }, [
    h('button', { type: 'button', class: 'btn small', text: 'Add \u201CBrowse items\u201D', onclick: function () { state.links.push({ type: 'browse', data: { label: 'Browse', query: '' }, links: [] }); changed(); } }),
    h('button', { type: 'button', class: 'btn small', text: 'Add \u201CCollections\u201D', onclick: function () { state.links.push({ type: 'browseItemSets', data: { label: 'Collections', query: '' }, links: [] }); changed(); } })
  ]));
}

/* ---------- styles and preview ---------- */
// Stand-in links for the small pictures while the menu is still empty.
var SAMPLE = [
  { type: 'page', data: { label: 'Home' }, links: [] },
  { type: 'page', data: { label: 'Collections' }, links: [{ type: 'page', data: { label: 'Photographs' }, links: [] }, { type: 'page', data: { label: 'Maps' }, links: [] }, { type: 'page', data: { label: 'Letters' }, links: [] }, { type: 'page', data: { label: 'Oral histories' }, links: [] }] },
  { type: 'page', data: { label: 'Visit' }, links: [] },
  { type: 'page', data: { label: 'About' }, links: [] }
];
var VERTICAL = {};
CFG.styles.forEach(function (s) { if (s.vertical) VERTICAL[s.value] = true; });
// CSS custom properties the drawings and the theme use for each color.
var VARS = { bar: '--pb-nav-bg', link: '--pb-nav-link', hover: '--pb-nav-hover', dropBg: '--pb-nav-drop-bg', dropLink: '--pb-nav-drop-link' };
function colorVars(colors) {
  var c = cleanColors(colors);
  return Object.keys(c).map(function (k) { return VARS[k] + ':' + c[k]; }).join(';');
}

/**
 * Each style is a card with a small picture of the menu drawn in that style
 * and colors (your own links, or sample ones while the menu is too small to
 * show the difference), its name, and what it does. Click a card to choose
 * it. Two groups: menus across the top, and menus down the side.
 */
function renderStyles() {
  var host = document.getElementById('mbStyles');
  host.innerHTML = '';
  // Your own links once there are enough to tell the styles apart (a few
  // links, one with sub-pages); sample ones until then.
  var own = state.links.length >= 3 && state.links.some(function (l) { return l.links && l.links.length; });
  var links = own ? state.links.slice(0, 5) : SAMPLE;
  var hint = document.getElementById('mbStylesHint');
  if (hint) hint.textContent = own ? 'Each picture shows your menu in that style. Click one to choose it; the preview at the top shows it on your site.'
    : 'Each picture shows a sample menu in that style (yours will show once it has a few links and a sub-page). Click one to choose it.';
  [[false, 'Across the top'], [true, 'Down the side (vertical)']].forEach(function (group) {
    host.appendChild(h('h3', { class: 'mb-style-group', text: group[1] }));
    CFG.styles.filter(function (s) { return !!s.vertical === group[0]; }).forEach(function (s) {
      var id = 'mb-style-' + s.value;
      var input = h('input', { type: 'radio', name: 'mbStyle', id: id, value: s.value });
      input.checked = s.value === state.style;
      input.addEventListener('change', function () { state.style = s.value; lookChanged(); });
      var pic = h('div', { class: 'mb-mini-frame', 'aria-hidden': 'true' }, [drawMenu(s.value, links, { mini: true })]);
      host.appendChild(h('label', { class: 'mb-style', for: id }, [pic, h('span', { class: 'mb-style-text' }, [h('span', { class: 'mb-style-name' }, [input, h('b', { text: s.label })]), h('small', { text: s.info })])]));
    });
  });
}

/**
 * A simplified drawing of a menu in a style and the chosen colors. The real
 * menu is drawn by the Companion Theme (view/common/vpb-menu.phtml).
 * opts.mini: the small picture on a style's card (one sub-menu shown open,
 * no page below). opts.device: 'tablet' or 'phone' draws the Menu button
 * with the list opened under it, as the theme does on small screens.
 */
function drawMenu(style, links, opts) {
  opts = opts || {};
  var mini = !!opts.mini, small = opts.device === 'tablet' || opts.device === 'phone';
  var c = cleanColors(state.colors);
  var box = h('div', { class: 'mb-preview style-' + style + (mini ? ' mb-mini' : '') + (VERTICAL[style] ? ' pv-side' + (style === 'sidebar-right' ? ' pv-side-right' : '') : '') + (c.bar ? ' pv-has-bg' : '') + (small ? ' pv-small' : '') });
  var vars = colorVars(state.colors);
  if (vars) box.setAttribute('style', vars);
  var opened = false;
  function list(items, level) {
    // The small pictures show a few sub-pages, enough to see the shape.
    if (mini && level > 1) items = items.slice(0, style === 'mega' ? 4 : 2);
    return h('ul', { class: 'pv-l' + level }, items.map(function (l) {
      var li = h('li', {}, [h('span', { class: 'pv-a', text: labelOf(l) })]);
      if (level < (mini ? 2 : 3) && l.links && l.links.length && style !== 'bar') {
        li.appendChild(list(l.links, level + 1));
        // In the small picture, the first link with sub-pages is shown open.
        if (mini && level === 1 && !opened) { opened = true; li.className = 'pv-open'; }
      }
      return li;
    }));
  }
  var logo = h('span', { class: 'pv-logo', text: CFG.siteTitle });
  var header;
  if (small) {
    // Small screens: every style becomes a Menu button with the list under it.
    header = h('div', { class: 'pv-header' }, [logo, h('span', { class: 'pv-menu-btn', text: '☰ Menu' }), h('div', { class: 'pv-phone-list' }, [list(links, 1)])]);
  } else if (style === 'split') {
    // Half the menu on each side of the logo.
    var half = Math.ceil(links.length / 2);
    header = h('div', { class: 'pv-header' }, [list(links.slice(0, half), 1), logo, list(links.slice(half), 1)]);
  } else if (style === 'button') {
    header = h('div', { class: 'pv-header' }, [logo, h('span', { class: 'pv-menu-btn', text: '☰ Menu' }), list(links, 1)]);
  } else {
    header = h('div', { class: 'pv-header' }, [logo, list(links, 1)]);
  }
  if (style === 'two-tier' && !small) box.appendChild(h('div', { class: 'pv-topbar' }, [h('span', { text: CFG.siteTitle }), h('span', { class: 'pv-search', text: 'Search' })]));
  box.appendChild(header);
  box.appendChild(h('div', { class: 'pv-page' }, [h('span', { text: 'Page content' })]));
  return box;
}

/* ---------- the preview at the top: your site, at the chosen width ---------- */
var DEVICES = { desktop: { w: 1280, label: 'Desktop' }, tablet: { w: 820, label: 'Tablet' }, phone: { w: 390, label: 'Phone' } };
var frame = null, draftTimer = null, draftSeq = 0;

/**
 * With the Companion Theme the preview is the site itself (its home page in
 * a frame at desktop, tablet, or phone width, scaled to fit), showing the
 * unsaved menu, style, and colors: they're sent to the server as a draft
 * kept in your session only (MenuController::draftAction). Other themes get
 * the drawing.
 */
function renderPreview() {
  var cur = CFG.styles.filter(function (x) { return x.value === state.style; })[0];
  var dev = DEVICES[state.device];
  document.getElementById('mbPreviewSub').textContent = '· ' + (cur ? cur.label : '') + ' · ' + dev.label;
  var stage = document.getElementById('mbStage');
  // The drawing: always kept (for other themes, and while the site loads).
  var sketch = document.getElementById('mbPreview');
  if (!sketch) {
    sketch = h('div', { id: 'mbPreview' });
    stage.appendChild(h('div', { class: 'mb-sketch-wrap' }, [sketch]));
  }
  var box = drawMenu(state.style, state.links.length ? state.links : SAMPLE, { device: state.device });
  sketch.className = box.className;
  sketch.setAttribute('style', (box.getAttribute('style') || '') + ';width:' + Math.min(dev.w, 1280) + 'px');
  sketch.innerHTML = '';
  while (box.firstChild) sketch.appendChild(box.firstChild);
  var note = document.getElementById('mbPreviewNote');
  if (!CFG.themeIsCompanion) {
    note.textContent = state.links.length ? 'A drawing of the menu at this width.' : 'Sample links shown. Add items to see your own menu.';
    document.getElementById('mbNotice').innerHTML = '';
    document.getElementById('mbNotice').appendChild(h('p', { class: 'notice', text: 'This site doesn’t use the Companion Theme, so its theme draws the menu its own way. The menu you arrange here still appears; the style and colors only apply with the Companion Theme.' }));
    return;
  }
  sketch.parentNode.hidden = true;
  var notice = document.getElementById('mbNotice');
  notice.innerHTML = '';
  if (CFG.headerWithoutMenu) {
    notice.appendChild(h('p', { class: 'notice' }, ['Your site\u2019s header was built in the Visual Builder and has no Site menu block, so the menu doesn\u2019t show on the site. ', h('a', { href: CFG.headerUrl, text: 'Add a Site menu block to the header' }), '.']));
  }
  note.textContent = 'Your site’s home page with these changes (not saved yet). Point at or tab to the menu to open sub-pages; links don’t leave the preview.';
  if (!frame) {
    frame = h('iframe', { class: 'mb-frame', title: 'Preview of your site with this menu', loading: 'eager' });
    frame.addEventListener('load', keepInPreview);
    stage.appendChild(h('div', { class: 'mb-frame-box', id: 'mbFrameBox' }, [frame]));
    window.addEventListener('resize', fitFrame);
  }
  fitFrame();
  sendDraft();
}
/** Size the frame to the device width, scaled down to fit the panel. */
function fitFrame() {
  if (!frame) return;
  var boxEl = document.getElementById('mbFrameBox'), w = DEVICES[state.device].w;
  var avail = boxEl.parentNode.clientWidth || w;
  var scale = Math.min(1, avail / w), height = state.device === 'phone' ? 640 : 520;
  frame.style.width = w + 'px';
  frame.style.height = (height / scale) + 'px';
  frame.style.transform = 'scale(' + scale + ')';
  boxEl.style.width = (w * scale) + 'px';
  boxEl.style.height = height + 'px';
}
/** Send the unsaved menu to the server (after a pause), then reload the frame. */
function sendDraft() {
  clearTimeout(draftTimer);
  draftTimer = setTimeout(function () {
    var seq = ++draftSeq;
    fetch(CFG.draftUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ csrf: CFG.csrf, navigation: state.links, style: state.style, colors: cleanColors(state.colors) })
    }).then(function (r) { return r.json(); }).then(function (body) {
      if (seq !== draftSeq || !body.ok || !frame) return;
      frame.src = body.url + '&t=' + Date.now();
    }).catch(function () { /* The drawing stays useful; nothing to do. */ });
  }, 350);
}
/** Links inside the preview don't navigate away (menus still open). */
function keepInPreview() {
  try {
    var doc = frame.contentDocument;
    doc.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('a[href]');
      if (a) { e.preventDefault(); toast('Links don’t leave the preview.'); }
    }, true);
  } catch (e) { /* Not the same site: leave it. */ }
}
function renderDevices() {
  Array.prototype.forEach.call(document.querySelectorAll('#mbDevices button'), function (b) {
    b.setAttribute('aria-pressed', String(b.getAttribute('data-dev') === state.device));
    b.onclick = function () { state.device = b.getAttribute('data-dev'); renderDevices(); renderPreview(); };
  });
}

/* ---------- colors ---------- */
var COLOR_LABELS = [['bar', 'Menu background'], ['link', 'Links'], ['hover', 'Pointed-at link and current page'], ['dropBg', 'Drop-downs and panels'], ['dropLink', 'Links in drop-downs and panels']];
// What the theme uses when a color isn't set (for the pickers and the contrast check).
var DEFAULTS = { bar: '#ffffff', link: '#1f2933', hover: '#0f5e54', dropBg: '#ffffff', dropLink: '#1f2933' };
function luminance(hex) {
  var n = parseInt(hex.slice(1), 16), rgb = [n >> 16 & 255, n >> 8 & 255, n & 255].map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
  return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
}
function contrast(a, b) { var x = luminance(a), y = luminance(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
/** Which scheme the current colors match, or '' (custom). */
function currentScheme() {
  var mine = JSON.stringify(cleanColors(state.colors));
  var hit = CFG.schemes.filter(function (sc) { return JSON.stringify(cleanColors(sc.colors)) === mine; })[0];
  return hit ? hit.value : '';
}
function renderColors() {
  var host = document.getElementById('mbColors');
  if (!host) return;
  var focused = document.activeElement && host.contains(document.activeElement) ? document.activeElement.id : null;
  host.innerHTML = '';
  var cur = currentScheme();
  var schemes = h('div', { class: 'mb-schemes', role: 'radiogroup', 'aria-label': 'Color scheme' });
  CFG.schemes.forEach(function (sc) {
    var id = 'mb-scheme-' + sc.value, c = sc.colors || {};
    var input = h('input', { type: 'radio', name: 'mbScheme', id: id, value: sc.value });
    input.checked = cur === sc.value;
    input.addEventListener('change', function () { state.colors = Object.assign({}, sc.colors || {}); lookChanged(); });
    var chips = h('span', { class: 'mb-chips', 'aria-hidden': 'true' }, ['bar', 'link', 'hover'].map(function (k) {
      return h('i', { style: 'background:' + (c[k] || (sc.value === 'theme' ? 'transparent' : DEFAULTS[k])) + (sc.value === 'theme' ? ';border-style:dashed' : '') });
    }));
    schemes.appendChild(h('label', { class: 'mb-scheme', for: id }, [input, chips, h('span', { text: sc.label })]));
  });
  host.appendChild(schemes);
  if (!cur) host.appendChild(h('p', { class: 'note', text: 'Custom colors.' }));
  var grid = h('div', { class: 'mb-color-grid' });
  COLOR_LABELS.forEach(function (row) {
    var key = row[0], id = 'mb-color-' + key, val = cleanColors(state.colors)[key];
    var pick = h('input', { type: 'color', id: id, value: val || DEFAULTS[key] });
    function take() { state.colors = Object.assign({}, state.colors); state.colors[key] = pick.value.toLowerCase(); }
    pick.addEventListener('input', function () { take(); renderPreview(); redrawStyles(250); status(); });
    pick.addEventListener('change', function () { take(); lookChanged(); });
    var reset = h('button', { type: 'button', class: 'btn small', text: val ? 'Use theme’s' : 'Theme’s', disabled: !val, title: 'Use the theme’s own color' });
    reset.addEventListener('click', function () { var c = Object.assign({}, state.colors); delete c[key]; state.colors = c; lookChanged(); });
    grid.appendChild(h('div', { class: 'mb-color-row' }, [h('label', { for: id, text: row[1] }), pick, reset]));
  });
  host.appendChild(grid);
  // Readability: text against what it sits on.
  var col = Object.assign({}, DEFAULTS, cleanColors(state.colors)), warn = [];
  if (contrast(col.link, col.bar) < 4.5) warn.push('links on the menu background');
  if (contrast(col.hover, col.bar) < 3) warn.push('the pointed-at link on the menu background');
  if (contrast(col.dropLink, col.dropBg) < 4.5) warn.push('links in drop-downs');
  if (warn.length) host.appendChild(h('p', { class: 'note warn', role: 'status', text: 'Hard to read: ' + warn.join(', ') + '. Pick colors with more difference between them.' }));
  if (focused && document.getElementById(focused)) document.getElementById(focused).focus();
}

/* ---------- saving ---------- */
var saving = false;
function save() {
  if (saving) return;
  saving = true;
  var btn = document.getElementById('saveBtn');
  btn.disabled = true;
  var sent = snap();
  fetch(CFG.saveUrl, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ csrf: CFG.csrf, navigation: state.links, style: state.style, colors: cleanColors(state.colors) })
  }).then(function (r) { return r.json(); }).then(function (body) {
    if (!body.ok) throw new Error((body.errors || ['Saving failed.']).join(' '));
    CFG.csrf = body.csrf;
    // Show what the server kept (unsafe links and missing pages are dropped),
    // unless the user went on editing while it saved.
    if (snap() === sent) {
      state.links = body.navigation; state.style = body.style; state.colors = Object.assign({}, body.colors || {});
      state.saved = snap();
    } else {
      state.saved = sent;
    }
    openItem = null; changed(); toast('Menu saved.');
  }).catch(function (e) { toast('Not saved. ' + e.message); })
    .then(function () { saving = false; btn.disabled = false; status(); });
}

document.getElementById('saveBtn').addEventListener('click', save);
document.addEventListener('keydown', function (e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); }
});
window.addEventListener('beforeunload', function (e) { if (dirty()) { e.preventDefault(); e.returnValue = ''; } });

renderTree(); // Fills in missing data/links on old menus before the baseline is taken.
state.saved = snap();
renderAdd(); renderDevices(); renderStyles(); renderColors(); renderPreview(); status();
window.VisualPageBuilderMenu = { state: state, save: save };
})();
