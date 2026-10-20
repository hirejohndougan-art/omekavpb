/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Sidebar forms for blocks that describe their own settings.
 *
 * A block layout from another module can give the editor a schema (a list of
 * fields) through a visualPageBuilderSchema() method on its PHP class; the
 * server passes it along as cfg.layouts[i].schema. For every such layout this
 * file registers a block definition that builds the sidebar form and the
 * canvas card from the schema, so the module needs no JavaScript of its own.
 *
 * Field format (the same one the Society Blocks module documents):
 *   { key, type, label, info?, default?, options?: [[value, label]…], min?, max?,
 *     placeholder?, show_if?: {otherKey: [values…]}, fields? (for lists),
 *     summary? (show on the card), group? (heading in the form) }
 * Types: text, textarea, html, number, select, checkbox, url, date, site,
 * sites, item_sets, asset, item, page, list. Unknown types show a note.
 *
 * Values are written in their natural types (true, 12, [3, 4]); the block's
 * own module cleans them again when Omeka saves the page.
 *
 * If the layout also has a visualPageBuilderPreview() method (cfg flag
 * `preview`), the canvas shows the HTML it returns (see previews.js).
 */
(function (VPB) {
'use strict';

var D = VPB.dom, h = D.h, thumbImg = D.thumbImg, thumbStrip = D.thumbStrip;
var F = VPB.fields, B = VPB.blocks, U = VPB.util, REFS = VPB.refs, CFG = VPB.cfg;
var SITES = Array.isArray(CFG.sites) ? CFG.sites : [];
var ITEM_SETS = Array.isArray(CFG.itemSets) ? CFG.itemSets : [];

function emptyValue(type) {
  if (type === 'checkbox') return false;
  if (type === 'sites' || type === 'item_sets' || type === 'list') return [];
  if (type === 'site' || type === 'asset' || type === 'item' || type === 'page' || type === 'number') return null;
  return '';
}
/** A field's value in `data`, or its default. */
function valueOf(field, data) {
  var v = U.obj(data)[field.key];
  if (v === undefined || v === null) return field.default !== undefined ? field.default : emptyValue(field.type);
  return v;
}
/** Whether a field applies, given the other values (its show_if rule). */
function applies(field, data, fields) {
  if (!field.show_if) return true;
  return Object.keys(field.show_if).every(function (key) {
    var other = null;
    fields.forEach(function (f) { if (f.key === key) other = f; });
    var v = other ? valueOf(other, data) : U.obj(data)[key];
    return field.show_if[key].some(function (want) { return want === v || String(want) === String(v); });
  });
}
function titleOf(list, id) {
  for (var i = 0; i < list.length; i++) if (list[i].id === id) return list[i].title;
  return '#' + id;
}
function optionLabel(field, v) {
  var out = String(v);
  (field.options || []).forEach(function (o) { if (o[0] === String(v)) out = o[1]; });
  return out;
}
function pageTitle(id) {
  var out = '#' + id;
  VPB.sitePages.forEach(function (p) { if (p.id === id) out = p.title; });
  return out;
}

/* ---------- canvas card ---------- */
/** Short text for a value on the card, or '' to leave it out. */
function display(field, v) {
  switch (field.type) {
    case 'checkbox': return v ? 'yes' : '';
    case 'select': return optionLabel(field, v);
    case 'sites': return v.length ? v.map(function (id) { return titleOf(SITES, id); }).join(', ') : '';
    case 'item_sets': return v.length ? v.map(function (id) { return titleOf(ITEM_SETS, id); }).join(', ') : '';
    case 'site': return v ? titleOf(SITES, v) : '';
    case 'page': return v ? pageTitle(v) : '';
    case 'item': return v ? (REFS.items[v] ? REFS.items[v].title : 'Item #' + v) : '';
    case 'asset': return v ? (REFS.assets[v] ? REFS.assets[v].title : 'Asset #' + v) : '';
    case 'list': return v.length ? v.length + (v.length === 1 ? ' entry' : ' entries') : '';
    default: return String(v || '').slice(0, 80);
  }
}
function card(fields) {
  return function (b) {
    var parts = [], thumbs = [];
    fields.forEach(function (f) {
      var v = valueOf(f, b.data);
      if (f.type === 'asset' && v && REFS.assets[v]) thumbs.push(REFS.assets[v].thumb);
      if (f.type === 'item' && v && REFS.items[v]) thumbs.push(REFS.items[v].thumb);
      if (!f.summary || !applies(f, b.data, fields)) return;
      var text = display(f, v);
      if (text !== '') parts.push(f.label + ': ' + text);
    });
    var out = [];
    if (thumbs.length) out.push(thumbStrip(thumbs.slice(0, 4), 0));
    out.push(h('span', { text: parts.length ? parts.join(' \u00B7 ') : 'Select this block to set it up.' }));
    return out;
  };
}

/* ---------- sidebar form ---------- */
/** A checklist for choosing several sites or item sets. */
function checklist(label, id, choices, chosen, onSet) {
  var wrap = h('fieldset', { class: 'field checklist', id: id });
  wrap.appendChild(h('legend', { class: 'lbl', text: label }));
  if (!choices.length) wrap.appendChild(F.note('None available.'));
  choices.forEach(function (c) {
    var on = chosen.indexOf(c.id) >= 0;
    wrap.appendChild(F.checkField(c.title, id + '-' + c.id, on, function (now) {
      var next = chosen.filter(function (x) { return x !== c.id; });
      if (now) next.push(c.id);
      onSet(next);
    }));
  });
  return wrap;
}

/** One picked item or asset, with Change and Remove buttons. */
function pickedResource(b, field, v, onSet, slot) {
  var isAsset = field.type === 'asset';
  var ref = v ? (isAsset ? REFS.assets[v] : REFS.items[v]) : null;
  var wrap = h('div', { class: 'field' }, [h('span', { class: 'lbl', text: field.label })]);
  if (v) {
    wrap.appendChild(h('div', { class: 'att' }, [h('div', { class: 'att-head' }, [
      thumbImg(ref ? ref.thumb : null, isAsset ? 'wide' : null),
      h('span', { class: 'pk-t' }, [h('b', { text: ref ? ref.title : (isAsset ? 'Asset #' : 'Item #') + v }), h('small', { text: (isAsset ? 'Asset #' : 'Item #') + v })]),
      D.iconBtn('Remove ' + field.label, VPB.icons.del, function () { onSet(null); VPB.sidebar.renderProps(); })
    ])]));
  }
  wrap.appendChild(VPB.picker.host(b, isAsset ? 'assets' : 'items', function (row) {
    (isAsset ? REFS.assets : REFS.items)[row.id] = row;
    onSet(row.id);
    VPB.sidebar.renderProps();
  }, { slot: slot, single: true, label: v ? 'Change\u2026' : (isAsset ? 'Choose an image\u2026' : 'Choose an item\u2026') }));
  if (field.info) wrap.appendChild(F.note(field.info));
  return wrap;
}

/**
 * The control for one field. `onSet(value, rerender)` stores the value;
 * `rerender` asks for the sidebar to be rebuilt (other fields may appear).
 */
function control(b, field, v, id, onSet) {
  var withInfo = function (el) { if (field.info && field.type !== 'list') el.appendChild(F.note(field.info)); return el; };
  switch (field.type) {
    case 'text': case 'url':
      return withInfo(F.textField(field.label, id, v, function (x) { onSet(x.trim()); }, { placeholder: field.placeholder || null, type: field.type === 'url' ? 'url' : 'text' }));
    case 'textarea': case 'html':
      var ta = h('textarea', { id: id, rows: field.type === 'html' ? 5 : 3, placeholder: field.placeholder || null });
      ta.value = v || '';
      ta.addEventListener('change', function () { onSet(ta.value); F.changed(); });
      return withInfo(F.field(field.label, id, ta));
    case 'number':
      return withInfo(F.numberField(field.label, id, v === null ? '' : v, function (x) { onSet(x === '' ? null : parseInt(x, 10)); }, { placeholder: field.placeholder || null }));
    case 'date':
      return withInfo(F.textField(field.label, id, v, function (x) { onSet(x.trim()); }, { type: 'date' }));
    case 'select':
      return withInfo(F.selectField(field.label, id, field.options || [], String(v), function (x) { onSet(x, true); }));
    case 'checkbox':
      return withInfo(F.checkField(field.label, id, !!v, function (on) { onSet(on, true); }));
    case 'page':
      return withInfo(F.selectField(field.label, id, [['', 'None']].concat(VPB.sitePages.map(function (p) { return [String(p.id), p.title]; })), v ? String(v) : '', function (x) { onSet(x ? parseInt(x, 10) : null, true); }));
    case 'site':
      return withInfo(F.selectField(field.label, id, [['', field.placeholder || 'None']].concat(SITES.map(function (s) { return [String(s.id), s.title]; })), v ? String(v) : '', function (x) { onSet(x ? parseInt(x, 10) : null, true); }));
    case 'sites':
      return withInfo(checklist(field.label, id, SITES, v || [], function (x) { onSet(x, true); }));
    case 'item_sets':
      return withInfo(checklist(field.label, id, ITEM_SETS, v || [], function (x) { onSet(x, true); }));
    case 'asset': case 'item':
      return pickedResource(b, field, v, function (x) { onSet(x); F.changed(); }, id);
  }
  return F.note(field.label + ': this kind of setting can only be changed in the standard editor.');
}

/** Rows of a list field, each with its sub-fields and move/remove buttons. */
// Which row of each list is open for editing: 'blockId:key' → index.
var openRows = {};

/**
 * A list of entries (places, timeline entries, slides…). Each row shows a
 * one-line summary (number, first field, a second detail, a picture) and opens
 * to edit when clicked, one at a time; a new row opens by itself. Fields with
 * show_if inside a row follow that row's values.
 */
function listControl(b, field, rows, id, onSet) {
  var wrap = h('div', { class: 'field' }, [h('span', { class: 'lbl', text: field.label })]);
  if (field.info) wrap.appendChild(F.note(field.info));
  var copy = rows.map(function (r) { return Object.assign({}, r); });
  var key = b.id + ':' + field.key;
  if (openRows[key] === undefined && copy.length === 1) openRows[key] = 0;
  function save() { onSet(copy.map(function (r) { return r; })); }
  function redraw() { save(); VPB.store.commit(); VPB.canvas.render(); VPB.app.refreshStatus(); VPB.sidebar.renderProps(); }
  var subs = field.fields || [];
  // Keys that other fields in a row depend on (show_if): changing them redraws.
  var drivers = {};
  subs.forEach(function (sub) { Object.keys(sub.show_if || {}).forEach(function (k) { drivers[k] = true; }); });
  if (!copy.length) wrap.appendChild(F.note('None yet.'));
  copy.forEach(function (row, i) {
    var open = openRows[key] === i;
    var box = h('div', { class: 'att lrow' + (open ? ' open' : '') });
    var first = subs[0], second = subs[1];
    var title = first ? display(first, valueOf(first, row)) : '';
    var detail = second && second.type !== 'item' && second.type !== 'asset' ? display(second, valueOf(second, row)) : '';
    var thumb = null;
    subs.forEach(function (sub) {
      var v = valueOf(sub, row);
      if (thumb || !v || (sub.show_if && !applies(sub, row, subs))) return;
      if (sub.type === 'asset' && REFS.assets[v]) thumb = REFS.assets[v].thumb;
      if (sub.type === 'item' && REFS.items[v]) thumb = REFS.items[v].thumb;
    });
    var head = h('button', { type: 'button', class: 'lrow-head', 'aria-expanded': String(open), onclick: function () { openRows[key] = open ? -1 : i; VPB.sidebar.renderProps(); } }, [
      thumb ? h('img', { class: 'lrow-thumb', src: thumb, alt: '' }) : h('span', { class: 'lrow-thumb empty', 'aria-hidden': 'true' }),
      h('span', { class: 'lrow-text' }, [h('b', { text: (i + 1) + '. ' + (title || 'New entry') }), detail ? h('small', { text: detail }) : null])
    ]);
    box.appendChild(h('div', { class: 'att-head' }, [head, VPB.picker.rowTools(copy, i, function () {
      if (openRows[key] === i) openRows[key] = -1;
      redraw();
    })]));
    if (open) {
      subs.forEach(function (sub) {
        if (sub.show_if && !applies(sub, row, subs)) return;
        box.appendChild(control(b, sub, valueOf(sub, row), id + '-' + i + '-' + sub.key, function (x, rerender) {
          row[sub.key] = x; save();
          if (rerender || drivers[sub.key]) { VPB.store.commit(); VPB.canvas.render(); VPB.sidebar.renderProps(); }
        }));
      });
    }
    wrap.appendChild(box);
  });
  var max = field.max || 50;
  if (copy.length < max) {
    wrap.appendChild(h('button', { type: 'button', class: 'btn small', text: 'Add an entry', onclick: function () {
      var row = {};
      (field.fields || []).forEach(function (sub) { row[sub.key] = sub.default !== undefined ? sub.default : emptyValue(sub.type); });
      copy.push(row); openRows[key] = copy.length - 1; redraw();
    } }));
  }
  return wrap;
}

function editor(fields, layout) {
  return function (box, b) {
    var tool = B.extra(layout);
    if (tool) tool(box, b); // A layout-specific tool goes first (see registry.extend).
    var group = null;
    fields.forEach(function (field) {
      if (field.editor_hidden || !applies(field, b.data, fields)) return; // editor_hidden: set by a block's own sidebar tool
      if (field.group && field.group !== group) {
        group = field.group;
        box.appendChild(h('h4', { text: group }));
      }
      var id = 'sf-' + field.key;
      var set = function (x, rerender) {
        B.set(b, field.key, x);
        if (rerender) VPB.sidebar.renderProps();
      };
      box.appendChild(field.type === 'list'
        ? listControl(b, field, valueOf(field, b.data) || [], id, set)
        : control(b, field, valueOf(field, b.data), id, set));
    });
  };
}

CFG.layouts.forEach(function (l) {
  if (!l.schema || !Array.isArray(l.schema.fields) || B.get(l.name)) return;
  var fields = l.schema.fields;
  var def = { card: card(fields), editor: editor(fields, l.name) };
  if (l.preview) {
    // The module renders the block itself; show that, or the card while loading.
    def.render = function (b) {
      var body = VPB.previews.get(b);
      return body && typeof body.html === 'string' ? VPB.previews.htmlNode(body.html) : null;
    };
  }
  B.register(l.name, def);
});

VPB.schemaForms = { valueOf: valueOf, applies: applies };
})(window.VPB);
