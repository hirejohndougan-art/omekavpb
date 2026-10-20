/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Table of contents and Table link in the editor (loaded after
 * blocks/schema, which builds their settings forms).
 *
 * - Table of contents (specialJumpLinks): the canvas shows the real list,
 *   worked out from the page as it is now (toc.js), and its settings start
 *   with "Found on this page": every heading and Table link, with the ones
 *   left out dimmed and why. Click one to go to it.
 * - Table link (specialTableLink): a thin dashed bar with its name on the
 *   canvas (it takes no space on the site), dragged like any block.
 */
(function (VPB) {
'use strict';

var h = VPB.dom.h, B = VPB.blocks, obj = VPB.util.obj;
var KIND = { heading: function (s) { return s.tag; }, link: function () { return 'Link'; }, named: function () { return 'Older link name'; } };

function levelsOf(b) { return VPB.toc.upgradeData(b.data).levels || 'h3'; }

/** The list as the site will show it: nested by level, numbered if chosen. */
function tocPreview(b) {
  var shown = VPB.toc.entries(levelsOf(b)).shown;
  var numbered = !!setting(b, 'numbered'), style = setting(b, 'style'), row = style === 'row';
  var nav = h('div', { class: 'b-live special-blocks special-blocks-jump-links special-blocks-jump-' + (row ? 'row' : style === 'box' ? 'box' : 'list') + (numbered ? ' special-blocks-jump-numbered' : ''), inert: true });
  var title = String(setting(b, 'heading') || '').trim();
  if (setting(b, 'show_heading') !== false && title) nav.appendChild(h('h2', { class: 'special-blocks-heading', text: title }));
  if (!shown.length && levelsOf(b) === 'none' && VPB.toc.plan().some(function (x) { return x.kind === 'heading'; })) {
    nav.appendChild(h('p', { class: 'special-blocks-note', text: 'This one is set to list only Table links (\u201CNo headings\u201D, kept from its older setting). To list your headings, change Headings to include in its settings.' }));
    return nav;
  }
  if (!shown.length) {
    nav.appendChild(h('p', { class: 'special-blocks-note', text: 'Nothing to list yet. Add headings to your text (Heading 2, 3, or 4 in Text formatting), or drag in a Table link for a spot with no heading.' }));
    return nav;
  }
  var tag = numbered ? 'ol' : 'ul';
  var root = h(tag, { class: 'special-blocks-jump-list' }), stack = [root], last = null;
  shown.forEach(function (e) {
    var level = row ? 1 : e.level;
    while (stack.length < level && last) { var sub = h(tag); last.appendChild(sub); stack.push(sub); }
    while (stack.length > level) stack.pop();
    last = h('li', { class: 'special-blocks-jump-level-' + e.level });
    if (numbered) { last.appendChild(h('span', { class: 'special-blocks-jump-num', text: e.num })); last.appendChild(document.createTextNode(' ')); }
    last.appendChild(h('a', { href: '#' + e.id, tabindex: '-1', text: e.label }));
    stack[stack.length - 1].appendChild(last);
  });
  nav.appendChild(root);
  return nav;
}

/** A setting's value with the schema default when the block hasn't saved one yet. */
function setting(b, key) {
  var l = VPB.cfg.layouts.filter(function (x) { return x.name === b.layout; })[0];
  var f = l && l.schema ? l.schema.fields.filter(function (x) { return x.key === key; })[0] : null;
  return f ? VPB.schemaForms.valueOf(f, b.data) : obj(b.data)[key];
}

/* ---------- Changing headings and Table links from the list ---------- */

/** Change heading `index` (among H2-H4) of text block `blk`; fn(el) may return a replacement element. */
function editHeading(blk, index, fn) {
  var t = document.createElement('template');
  t.innerHTML = String(obj(blk.data).html || '');
  var el = t.content.querySelectorAll('h2,h3,h4')[index];
  if (!el) return;
  var out = fn(el);
  if (out && out !== el) el.parentNode.replaceChild(out, el);
  blk.data = Object.assign({}, obj(blk.data), { html: t.innerHTML });
}
/** Set a spot's level (1 section, 2 subsection, 3 sub-subsection). For a heading that's its style: Heading 2, 3, 4. */
function setLevel(s, lv) {
  lv = Math.max(1, Math.min(3, lv));
  if (s.kind === 'link') { B.set(s.block, 'level', String(lv)); return; }
  if (s.kind !== 'heading') return;
  editHeading(s.block, s.index, function (el) {
    var n = document.createElement('h' + (lv + 1));
    [].forEach.call(el.attributes, function (a) { n.setAttribute(a.name, a.value); });
    while (el.firstChild) n.appendChild(el.firstChild);
    return n;
  });
}
function setShown(s, on) {
  editHeading(s.block, s.index, function (el) { if (on) el.removeAttribute('data-toc-hide'); else el.setAttribute('data-toc-hide', '1'); });
}
function setName(s, name) {
  name = String(name || '').trim();
  if (s.kind === 'link') { B.set(s.block, 'name', name); return; }
  editHeading(s.block, s.index, function (el) { if (name) el.setAttribute('data-toc-name', name); else el.removeAttribute('data-toc-name'); });
}
function changed() { VPB.store.commit(); VPB.app.renderAll(); }
function keyOf(s) { return (s.block ? s.block.id : s.target.id) + ':' + s.kind + ':' + (s.index === undefined ? '' : s.index); }
var openKey = null; // the entry whose settings are open in the list

var ARROW = {
  left: '<path d="M15 6l-6 6 6 6"/>', right: '<path d="M9 6l6 6-6 6"/>',
  grip: '<path d="M8 12h8M5 9l-3 3 3 3M19 9l3 3-3 3"/>'
};
function icon(name) { return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + ARROW[name] + '</svg>'; }
function iconButton(label, name, fn, disabled) {
  var btn = h('button', { type: 'button', class: 'toc-ib', 'aria-label': label, title: label, disabled: !!disabled });
  btn.innerHTML = icon(name);
  btn.addEventListener('click', fn);
  return btn;
}
var LEVEL_NAMES = { 1: 'Section', 2: 'Subsection', 3: 'Sub-subsection' };

/**
 * "Found on this page": every heading and Table link, in order, indented by
 * level. Each entry can be indented or outdented (the arrows, or drag the
 * grip sideways): for a heading that changes it between Heading 2, 3, and 4.
 * Click an entry to open its settings: show it or not, its name in the table
 * of contents, its level, and a button to go to it on the page.
 */
function foundTool(box, b) {
  var res = VPB.toc.entries(levelsOf(b));
  var spots = res.all.filter(function (s) { return s.block !== b; });
  var count = res.shown.length, skipped = spots.length - count;
  var wrap = h('div', { class: 'toc-found', role: 'group', 'aria-label': 'Found on this page' });
  wrap.appendChild(h('span', { class: 'lbl', text: 'Found on this page (' + count + ' listed' + (skipped ? ', ' + skipped + ' left out' : '') + ')' }));
  if (!spots.length) {
    wrap.appendChild(h('p', { class: 'note', text: 'No headings or Table links on this page yet. Add headings to your text (Heading 2, 3, or 4 in Text formatting), or drag in a Table link.' }));
    box.appendChild(wrap);
    return;
  }
  var ul = h('ul', { class: 'toc-found-list' });
  spots.forEach(function (s) {
    var key = keyOf(s), open = openKey === key, movable = s.kind === 'heading' || s.kind === 'link';
    var li = h('li', { class: 'toc-row' + (open ? ' open' : ''), style: '--toc-indent:' + ((s.level - 1) * 18) + 'px' });
    var line = h('div', { class: 'toc-line' });
    if (movable) {
      var grip = h('button', { type: 'button', class: 'toc-ib toc-grip', 'aria-label': 'Change the level of “' + (s.label || 'this entry') + '”: drag sideways, or use the left and right arrow keys', title: 'Drag sideways to indent or outdent' });
      grip.innerHTML = icon('grip');
      grip.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
        e.preventDefault();
        setLevel(s, s.level + (e.key === 'ArrowRight' ? 1 : -1)); changed();
        var again = document.querySelector('.toc-row[data-key="' + key + '"] .toc-grip'); if (again) again.focus();
      });
      grip.addEventListener('pointerdown', function (e) {
        e.preventDefault(); e.stopPropagation();
        var x0 = e.clientX, step = 0;
        function move(ev) {
          step = Math.max(1 - s.level, Math.min(3 - s.level, Math.round((ev.clientX - x0) / 24)));
          li.style.setProperty('--toc-indent', ((s.level - 1 + step) * 18) + 'px');
          li.classList.add('dragging');
        }
        function up() {
          document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', up);
          li.classList.remove('dragging');
          if (step) { setLevel(s, s.level + step); changed(); }
        }
        document.addEventListener('pointermove', move); document.addEventListener('pointerup', up);
      });
      line.appendChild(grip);
    }
    var item = h('button', { type: 'button', class: 'toc-found-item' + (s.listed ? '' : ' off'), 'aria-expanded': String(open), title: s.listed ? 'Settings for this entry' : 'Not listed: ' + s.why }, [
      h('span', { class: 'toc-kind', text: KIND[s.kind](s) }),
      h('span', { class: 'toc-name', text: s.label || '(no name yet)' }),
      s.listed ? null : h('em', { text: s.why })
    ]);
    item.addEventListener('click', function () { openKey = open ? null : key; VPB.sidebar.renderProps(); });
    line.appendChild(item);
    if (movable) {
      line.appendChild(iconButton('Outdent (bigger section)', 'left', function () { setLevel(s, s.level - 1); changed(); }, s.level <= 1));
      line.appendChild(iconButton('Indent (make it a subsection)', 'right', function () { setLevel(s, s.level + 1); changed(); }, s.level >= 3));
    }
    li.setAttribute('data-key', key);
    li.appendChild(line);
    if (open) li.appendChild(entryPanel(s));
    ul.appendChild(li);
  });
  wrap.appendChild(ul);
  wrap.appendChild(h('p', { class: 'note', text: 'Click an entry to rename it, leave it out, or change its level. Indenting a heading makes it a smaller heading on the page (Heading 2 → 3 → 4), like a word processor.' }));
  box.appendChild(wrap);
}

/** The settings for one entry, opened in the list. */
function entryPanel(s) {
  var p = h('div', { class: 'toc-entry' });
  var id = 'toc-e-' + keyOf(s).replace(/[^a-z0-9]/gi, '-');
  if (s.kind === 'heading') {
    var show = h('input', { type: 'checkbox', id: id + '-show' });
    show.checked = !s.hide;
    show.addEventListener('change', function () { setShown(s, show.checked); changed(); });
    p.appendChild(h('div', { class: 'field check' }, [h('label', { for: id + '-show' }, [show, h('span', { text: 'Show in the table of contents' })])]));
  }
  if (s.kind === 'heading' || s.kind === 'link') {
    var name = h('input', { type: 'text', id: id + '-name', placeholder: s.kind === 'heading' ? s.text : 'e.g. Where it happened (map)' });
    name.value = s.kind === 'heading' ? s.name || '' : s.label || '';
    name.addEventListener('change', function () { setName(s, name.value); changed(); });
    name.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); name.blur(); } });
    p.appendChild(h('div', { class: 'field' }, [h('label', { for: id + '-name', text: s.kind === 'heading' ? 'Name in the table of contents (optional)' : 'Name in the table of contents' }), name,
      s.kind === 'heading' ? h('p', { class: 'note', text: 'Leave it empty to use the heading itself.' }) : null]));
    var lvl = h('select', { id: id + '-level' });
    [1, 2, 3].forEach(function (n) { lvl.appendChild(h('option', { value: String(n), text: LEVEL_NAMES[n] + (s.kind === 'heading' ? ' (Heading ' + (n + 1) + ')' : '') })); });
    lvl.value = String(s.level);
    lvl.addEventListener('change', function () { setLevel(s, parseInt(lvl.value, 10)); changed(); });
    p.appendChild(h('div', { class: 'field' }, [h('label', { for: id + '-level', text: 'Level' }), lvl]));
  } else {
    p.appendChild(h('p', { class: 'note', text: 'An older link name. Change or clear it in that section’s or block’s settings, under Style.' }));
  }
  p.appendChild(h('button', { type: 'button', class: 'btn small', text: 'Show it on the page', onclick: function () {
    openKey = null;
    VPB.app.select(s.target);
    var el = document.querySelector('[data-id="' + s.target.id + '"]');
    if (el && el.scrollIntoView) el.scrollIntoView({ block: 'center' });
  } }));
  return p;
}

var LEVEL = { 1: 'section', 2: 'subsection', 3: 'sub-subsection' };
function linkPreview(b) {
  var d = obj(b.data), name = String(d.name || '').trim(), lv = parseInt(d.level, 10) || 1;
  return h('div', { class: 'b-tablelink' }, [
    h('span', { class: 'tl-mark', 'aria-hidden': 'true', text: '⚓' }),
    h('b', { text: name ? 'Table link: ' + name : 'Table link (give it a name in its settings)' }),
    h('span', { text: (LEVEL[lv] || 'section') + ' · no space on the site' })
  ]);
}

/** Redraw the Table of contents blocks on the canvas (as headings are typed). */
var timer = null;
function refreshSoon() {
  clearTimeout(timer);
  timer = setTimeout(function () {
    VPB.store.state.sections.forEach(function (sec) { sec.cols.forEach(function (c) { c.blocks.forEach(function (b) {
      if (b.layout !== 'specialJumpLinks') return;
      var inner = document.querySelector('.blk[data-id="' + b.id + '"] > .inner');
      if (inner && inner.firstChild) inner.replaceChild(tocPreview(b), inner.firstChild);
    }); }); });
  }, 250);
}

var jl = B.get('specialJumpLinks');
if (jl) jl.render = tocPreview;
B.extend('specialJumpLinks', foundTool);
var tl = B.get('specialTableLink');
if (tl) {
  tl.preview = linkPreview;
  tl.name = function (b) { var n = String(obj(b.data).name || '').trim(); return n ? 'Table link: ' + n : 'Table link'; };
}

VPB.tocBlocks = { tocPreview: tocPreview, foundTool: foundTool, setLevel: setLevel, setShown: setShown, setName: setName, linkPreview: linkPreview, refreshSoon: refreshSoon };
})(window.VPB);
