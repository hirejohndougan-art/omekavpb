/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * DOM helpers: element builder, icons, toast, and the HTML sanitizer used for
 * previews.
 */
(function (VPB) {
'use strict';

/* ---------- icons (static SVG path strings only) ---------- */
VPB.icons = {
  heading: '<path d="M6 5v14M18 5v14M6 12h12"/>',
  text: '<path d="M4 6h16M4 11h16M4 16h10"/>',
  block: '<rect x="4" y="5" width="16" height="14" rx="2"/><path d="M8 10h8M8 14h5"/>',
  handle: '<circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/>',
  up: '<path d="M12 19V5M5 12l7-7 7 7"/>',
  down: '<path d="M12 5v14M5 12l7 7 7-7"/>',
  dup: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 00-1-1H5a1 1 0 00-1 1v10a1 1 0 001 1h3"/>',
  set: '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
  del: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
  x: '<path d="M6 6l12 12M18 6L6 18"/>',
  back: '<path d="M15 18l-6-6 6-6"/>',
  none: '<path d="M5 19L19 5"/>',
  minus: '<path d="M6 12h12"/>',
  plus: '<path d="M6 12h12M12 6v12"/>',
  info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5v.01"/>',
  section: '<rect x="3" y="5" width="18" height="14" rx="2"/>',
  column: '<rect x="9" y="4" width="6" height="16" rx="1"/>'
};
/** An inline SVG icon from one of the path strings above. */
function svg(inner, size, color) {
  var s = size || 20;
  return '<svg width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" stroke="' + (color || 'currentColor') + '" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + inner + '</svg>';
}
/** Small diagram of a column layout, e.g. [8, 4]. */
function presetIcon(ws) {
  var x = 3, g = 1.5, avail = 18 - g * (ws.length - 1), out = '';
  ws.forEach(function (v) { var w = avail * v / 12; out += '<rect x="' + x.toFixed(2) + '" y="6" width="' + w.toFixed(2) + '" height="12" rx="1"/>'; x += w + g; });
  return out;
}

/**
 * Build an element. attrs: 'class', 'text' (textContent), 'on…' (event
 * listeners), anything else becomes an attribute (null/undefined/false skip).
 */
function h(tag, attrs, kids) {
  var el = document.createElement(tag);
  if (attrs) Object.keys(attrs).forEach(function (k) {
    var v = attrs[k];
    if (v === null || v === undefined || v === false) return;
    if (k === 'class') el.className = v;
    else if (k === 'text') el.textContent = v;
    else if (k.indexOf('on') === 0) el.addEventListener(k.slice(2), v);
    else el.setAttribute(k, v === true ? '' : v);
  });
  if (kids) (Array.isArray(kids) ? kids : [kids]).forEach(function (c) { if (c) el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
  return el;
}
function iconBtn(label, icon, fn, cls, size) {
  var b = h('button', { type: 'button', class: cls || 'icon-btn', 'aria-label': label, title: label });
  b.innerHTML = svg(icon, size || 16);
  if (fn) b.addEventListener('click', fn);
  return b;
}
/** A square thumbnail. Only http(s) or site-relative image URLs are used. */
function thumbImg(url, cls) {
  var wrap = h('span', { class: 'thumb' + (cls ? ' ' + cls : ''), 'aria-hidden': 'true' });
  if (typeof url === 'string' && /^(https?:\/\/|\/)/i.test(url)) wrap.appendChild(h('img', { src: url, alt: '', loading: 'lazy' }));
  return wrap;
}

/** A row of thumbnails, with "+N" when there are more than shown. */
function thumbStrip(urls, more) {
  var s = h('div', { class: 'b-thumbs' });
  urls.forEach(function (u) { s.appendChild(thumbImg(u)); });
  if (more) s.appendChild(h('span', { class: 'more', text: '+' + more }));
  return s;
}

var toastTimer;
/** Show a short message at the bottom of the screen. */
function toast(msg, ms) {
  var t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(function () { t.classList.remove('show'); }, ms || 3400);
}
function scrollToId(id) { var el = document.querySelector('[data-id="' + id + '"]'); if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' }); }

/* ---------- HTML sanitizing for previews ---------- */
// The editor previews block HTML inside the admin page, so anything that could
// run script or load active content is removed first. `removed` tells the caller
// that the preview differs from the saved HTML, so the block is locked.
var STRIP = { SCRIPT: 1, STYLE: 1, IFRAME: 1, OBJECT: 1, EMBED: 1, LINK: 1, META: 1, BASE: 1, FORM: 1, INPUT: 1, BUTTON: 1, TEXTAREA: 1, SELECT: 1, VIDEO: 1, AUDIO: 1, SOURCE: 1, SVG: 1, MATH: 1 };
// Form controls are kept in live previews (a search box should look like one);
// previews are shown inert, so they can't be used.
var FORM_TAGS = { FORM: 1, INPUT: 1, BUTTON: 1, TEXTAREA: 1, SELECT: 1 };
/**
 * opts.keepForms  keep form controls (for inert previews of server HTML).
 */
function sanitize(html, opts) {
  var keepForms = !!(opts && opts.keepForms);
  var tpl = document.createElement('template');
  tpl.innerHTML = String(html || '');
  var removed = false;
  (function walk(node) {
    Array.prototype.slice.call(node.childNodes).forEach(function (n) {
      if (n.nodeType === 1) {
        var tag = n.nodeName.toUpperCase();
        if (STRIP[tag] && !(keepForms && FORM_TAGS[tag])) { n.remove(); removed = true; return; }
        if (keepForms && tag === 'FORM') n.removeAttribute('action');
        Array.prototype.slice.call(n.attributes).forEach(function (a) {
          var name = a.name.toLowerCase(), val = a.value.replace(/\s+/g, '').toLowerCase();
          if (name.indexOf('on') === 0 || name === 'srcdoc' || ((name === 'href' || name === 'src' || name === 'xlink:href' || name === 'action') && (val.indexOf('javascript:') === 0 || val.indexOf('data:text') === 0))) {
            n.removeAttribute(a.name); removed = true;
          }
        });
        walk(n);
      } else if (n.nodeType === 8) { n.remove(); }
    });
  })(tpl.content);
  return { fragment: tpl.content, removed: removed };
}

VPB.dom = { h: h, svg: svg, presetIcon: presetIcon, iconBtn: iconBtn, thumbImg: thumbImg, thumbStrip: thumbStrip, toast: toast, scrollToId: scrollToId, sanitize: sanitize };
})(window.VPB);
