/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Visual Page Builder editor: shared namespace, configuration, and small utilities.
 *
 * The editor is split into plain browser scripts (no build step). Each file adds
 * one part to the global `VPB` namespace, and edit.phtml loads them in order:
 *
 *   core, dom            utilities used everywhere
 *   model, store         page data: import/export, state, undo history
 *   fields, picker       sidebar form controls and the item/asset search picker
 *   blocks/*             one file per Omeka block type (preview card + sidebar form)
 *   actions              edits to sections, columns, and blocks
 *   canvas, drag, resize the page preview and its pointer interactions
 *   sidebar, save, app   the sidebar, saving, and startup/wiring
 *
 * Rule: a file may call any part's functions at run time (VPB.part.fn()), but
 * may only read a part while loading (e.g. `var F = VPB.fields;`) if that part
 * is loaded before it. tests/module/load-order.test.js checks this.
 */
(function () {
'use strict';

var VPB = window.VPB = window.VPB || {};

/* ---------- server-provided configuration (see BuilderController::editAction) ---------- */
var cfg = JSON.parse(document.getElementById('vpb-config').textContent);
VPB.cfg = cfg;

var LABELS = {};
cfg.layouts.forEach(function (l) { LABELS[l.name] = l.label; });

/* ---------- constants ---------- */
VPB.PRESETS = [
  { name: '1 column', w: [12] }, { name: '2 columns', w: [6, 6] }, { name: '3 columns', w: [4, 4, 4] },
  { name: 'Wide + narrow', w: [8, 4] }, { name: 'Narrow + wide', w: [4, 8] }, { name: '4 columns', w: [3, 3, 3, 3] }
];
// Background colors offered everywhere (pages, headers, footers): light ones
// first, then dark ones (text on a dark background turns white by itself,
// see VPB.isDark). Any other color: the custom color picker.
VPB.SWATCHES = [
  { hex: null, label: 'No background' },
  { hex: '#FFFFFF', label: 'White' }, { hex: '#F1EEE4', label: 'Warm paper' }, { hex: '#F3EBD9', label: 'Pale brass' },
  { hex: '#F4E4E1', label: 'Pale rose' }, { hex: '#E3ECE5', label: 'Pale green' }, { hex: '#E6ECF3', label: 'Pale blue' },
  { hex: '#ECEAF3', label: 'Pale lavender' }, { hex: '#EEEEEC', label: 'Pale gray' },
  { hex: '#D9C9A8', label: 'Sand' }, { hex: '#B9CBB9', label: 'Sage' }, { hex: '#C9D6E3', label: 'Sky' },
  { hex: '#0F5E54', label: 'Deep green' }, { hex: '#1F3A5F', label: 'Navy' }, { hex: '#8A3324', label: 'Brick' },
  { hex: '#4B2E4E', label: 'Plum' }, { hex: '#5A4632', label: 'Brown' }, { hex: '#2B2B2B', label: 'Charcoal' }
];
/**
 * CSS for a background image setting ({ asset, size, pos, overlay }) in the
 * editor, using the asset's URL from VPB.refs. '' if there's none.
 */
VPB.bgImageCss = function (img) {
  if (!img || !img.asset || !VPB.refs.assets[img.asset]) return '';
  var url = VPB.refs.assets[img.asset].thumb;
  var layers = (img.overlay ? 'linear-gradient(rgba(0,0,0,.45),rgba(0,0,0,.45)),' : '') + 'url("' + String(url).replace(/"/g, '%22') + '")';
  return 'background-image:' + layers + ';background-size:' + (img.size === 'contain' ? 'contain' : 'cover') +
    ';background-position:center ' + (img.pos || 'center') + ';background-repeat:no-repeat;' + (img.overlay ? 'color:#fff;' : '');
};
/** Whether a background color is dark enough to need white text. */
VPB.isDark = function (hex) {
  if (!hex || !/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(hex)) return false;
  var h = hex.slice(1);
  if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
  var c = [0, 2, 4].map(function (i) {
    var v = parseInt(h.substr(i, 2), 16) / 255;
    return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
  });
  // Relative luminance (WCAG); below 0.18 white text reads better than black.
  return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2] < 0.18;
};
VPB.DEV_W = { desktop: 960, tablet: 720, phone: 375 }; // Preview widths per device.
VPB.MIN_W = 2;      // Narrowest column, out of 12.
VPB.MAX_COLS = 6;   // Most columns in a section.
VPB.PAD_KEYS = ['padding_top', 'padding_right', 'padding_bottom', 'padding_left'];
VPB.HEX_RE = /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/;

/* ---------- utilities ---------- */
var uid = 0;
VPB.util = {
  /** A new id for a section ('s'), column ('c'), or block ('b'), unique in this session. */
  nid: function (prefix) { uid++; return prefix + uid.toString(36); },
  /** The value if it is a plain object, otherwise {}. */
  obj: function (v) { return v && typeof v === 'object' && !Array.isArray(v) ? v : {}; },
  clone: function (v) { return JSON.parse(JSON.stringify(v)); },
  clamp: function (v, a, b) { return Math.max(a, Math.min(b, v)); },
  /** Label of a block layout as registered in Omeka, or its name if Omeka doesn't know it. */
  labelFor: function (layout) { return LABELS[layout] || layout; },
  /** Whether Omeka has this block layout (false when its module is off). */
  known: function (layout) { return Object.prototype.hasOwnProperty.call(LABELS, layout); },
  /** Checkbox-style values from Omeka forms ("1", 1, true). */
  isOn: function (v) { return v === true || v === 1 || v === '1' || v === 'true'; }
};

/* ---------- lookups of items and assets the page refers to ---------- */
// Filled by the server for everything already on the page, and by picker searches.
VPB.refs = { items: VPB.util.obj(VPB.util.obj(cfg.refs).items), assets: VPB.util.obj(VPB.util.obj(cfg.refs).assets) };
VPB.thumbnailTypes = Array.isArray(cfg.thumbnailTypes) && cfg.thumbnailTypes.length ? cfg.thumbnailTypes : ['large', 'medium', 'square'];
VPB.sitePages = Array.isArray(cfg.sitePages) ? cfg.sitePages : [];
})();
