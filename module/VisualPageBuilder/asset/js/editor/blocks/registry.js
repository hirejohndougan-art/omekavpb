/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Block type registry.
 *
 * Each Omeka block layout the editor understands registers a definition here
 * (one file per layout in this folder). All parts are optional:
 *
 *   name(block)         Name shown on the block's chip and in the sidebar.
 *   newData(html)       o:data for a newly added block.
 *   preview(block)      The whole canvas preview, for blocks shown as themselves
 *                       (text, page title, line break), used even when the
 *                       layout's module is off.
 *   render(block)       A preview that looks like the public page (media,
 *                       browse preview…). Falls back to `card` while loading.
 *   card(block)         Nodes shown under the label on the block's summary card.
 *   editor(box, block)  Append the block's settings controls to `box`.
 *   note(block)         A sidebar note to show instead of settings.
 *   afterSave(data)     Adjust o:data as Omeka returned it after a save.
 *
 * Layouts without a definition, or from a module that isn't active, get a
 * plain card and are saved exactly as they were loaded.
 */
(function (VPB) {
'use strict';

var h = VPB.dom.h, U = VPB.util, obj = U.obj, known = U.known, labelFor = U.labelFor;
var defs = {};
// Plain-language help: layout (or "html:heading"/"html:para") → {description, goodFor}.
var abouts = {};

function register(layout, def) { defs[layout] = def; }
// Extra sidebar tools for schema-built blocks (see blocks/schema.js):
// layout → function (box, block) that adds to the generated form.
var extras = {};
/** Add a tool above a schema-built block's settings (e.g. click-to-place markers). */
function extend(layout, fn) { extras[layout] = fn; }
function extra(layout) { return extras[layout] || null; }
/** Set the help text for a layout (see blocks/about.js). */
function describe(key, help) { abouts[key] = help; }
/**
 * Help text for a layout name, "html:heading"/"html:para", or a block:
 * {description, goodFor}, or null. Other modules' blocks send theirs with
 * their schema (cfg.layouts[i].description / goodFor).
 */
function about(keyOrBlock) {
  var key = keyOrBlock;
  if (keyOrBlock && typeof keyOrBlock === 'object') {
    key = keyOrBlock.layout === 'html' ? (/^\s*<h[1-6][\s>]/i.test(obj(keyOrBlock.data).html || '') ? 'html:heading' : 'html:para') : keyOrBlock.layout;
  }
  if (abouts[key]) {
    var partners = null;
    VPB.cfg.layouts.forEach(function (l) { if (l.name === key && l.worksWith) partners = l.worksWith; });
    return partners && !abouts[key].worksWith ? Object.assign({}, abouts[key], { worksWith: partners }) : abouts[key];
  }
  var out = null;
  VPB.cfg.layouts.forEach(function (l) { if (l.name === key && l.description) out = { description: l.description, goodFor: l.goodFor || '', worksWith: l.worksWith || '' }; });
  return out;
}
function get(layout) { return Object.prototype.hasOwnProperty.call(defs, layout) ? defs[layout] : null; }
/** Whether the sidebar can edit this layout's settings. */
function canEdit(layout) { var d = get(layout); return !!(d && d.editor) && known(layout); }
function name(b) { var d = get(b.layout); return d && d.name ? d.name(b) : labelFor(b.layout); }

/** A setting from the block's o:data, or `dflt` when missing or null. */
function val(b, key, dflt) {
  var d = obj(b.data);
  return d[key] === undefined || d[key] === null ? dflt : d[key];
}
/** Set one o:data key, keeping the others. */
function set(b, key, v) {
  var d = Object.assign({}, obj(b.data));
  d[key] = v;
  b.data = d;
}

/** The block's preview on the page canvas. */
function preview(b) {
  var d = get(b.layout);
  if (d && d.preview) return d.preview(b);
  if (!known(b.layout)) {
    return h('div', { class: 'b-card unknown' }, [
      h('b', { text: 'Unavailable block: ' + b.layout }),
      h('span', { text: 'Its module may be turned off. It will be kept exactly as it is.' })
    ]);
  }
  if (d && d.render) {
    var rendered = d.render(b);
    if (rendered) return rendered;
  }
  var n = b.att.length;
  if (!canEdit(b.layout)) {
    return h('div', { class: 'b-card' }, [
      h('b', { text: labelFor(b.layout) }),
      h('span', { text: (n ? n + (n === 1 ? ' attached item. ' : ' attached items. ') : '') + 'Content is set in the standard editor.' })
    ]);
  }
  return h('div', { class: 'b-card' }, [h('b', { text: labelFor(b.layout) })].concat(d.card ? d.card(b) : []));
}

/** The "Content" part of the block's settings panel. */
function sidebar(b) {
  var d = get(b.layout);
  if (d && d.note) return d.note(b);
  if (!known(b.layout)) return h('p', { class: 'note', text: 'This block comes from a module that isn\u2019t active. It will be saved exactly as it is.' });
  if (canEdit(b.layout)) {
    var box = h('div', { class: 'content-ed' });
    d.editor(box, b);
    return box;
  }
  return h('p', { class: 'note', text: 'This block\u2019s settings come from another module, so they\u2019re set in Omeka\u2019s standard editor. Save here first, then open it there.' });
}

function afterSave(b, data) { var d = get(b.layout); return d && d.afterSave ? d.afterSave(data) : data; }

VPB.blocks = { register: register, extend: extend, extra: extra, describe: describe, about: about, get: get, canEdit: canEdit, name: name, val: val, set: set, preview: preview, sidebar: sidebar, afterSave: afterSave };
})(window.VPB);
