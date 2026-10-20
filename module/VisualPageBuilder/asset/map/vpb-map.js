/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * VpbMap: the module's own small map, written from scratch (no outside
 * library). OpenStreetMap tiles in the standard Web Mercator projection, with:
 * drag to pan (mouse, pen, touch), two-finger pinch zoom, scroll-wheel zoom
 * (optionally only after the map is clicked), double-click zoom, +/− buttons,
 * keyboard (arrows pan, + and − zoom), pins (numbered, clickable, draggable,
 * with popups), dashed lines between points, fitting points in view, gliding
 * to a point, and the OpenStreetMap credit.
 *
 *   var map = VpbMap.create(el, { wheelZoom: 'after-click', label: 'Map' });
 *   (options: tileUrl — template or function for other tile pictures; credit — HTML)
 *   map.setView([29.46, -95.05], 13);
 *   var pin = map.addPin([29.46, -95.05], { text: '1', title: 'The depot', popup: '<b>…</b>', draggable: false });
 *   map.addLine([[lat, lng], …], { dashed: true });
 *   map.fitBounds(points, { padding: 30, maxZoom: 16 });
 *   map.flyTo([lat, lng], 15, { duration: 1.2, animate: true });
 *   map.on('click', function (latlng) { … });
 *
 * Popup content is HTML the caller has already escaped. Pins are buttons, so
 * they're reachable by keyboard.
 */
(function (window) {
'use strict';

var TILE = 256, MIN_ZOOM = 1, MAX_ZOOM = 19, MAX_LAT = 85.0511287798;
var TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
var CREDIT = '\u00A9 <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors';

/** [lat, lng] at zoom z → world pixel {x, y}. */
function project(ll, z) {
  var lat = Math.max(-MAX_LAT, Math.min(MAX_LAT, ll[0]));
  var size = TILE * Math.pow(2, z), s = Math.sin(lat * Math.PI / 180);
  return { x: (ll[1] + 180) / 360 * size, y: (0.5 - Math.log((1 + s) / (1 - s)) / (4 * Math.PI)) * size };
}
/** World pixel {x, y} at zoom z → [lat, lng]. */
function unproject(p, z) {
  var size = TILE * Math.pow(2, z);
  var n = Math.PI - 2 * Math.PI * p.y / size;
  return [180 / Math.PI * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n))), p.x / size * 360 - 180];
}
function clampZoom(z) { return Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, z)); }
function el(tag, cls, parent) { var e = document.createElement(tag); if (cls) e.className = cls; if (parent) parent.appendChild(e); return e; }
function reduceMotion() { return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches); }

function create(container, opts) {
  opts = opts || {};
  var state = { center: [0, 0], zoom: 2, anim: null };
  var handlers = {};
  var pins = [], lines = [];
  var wheelOn = opts.wheelZoom === true || opts.wheelZoom === undefined;
  var wheelAfterClick = opts.wheelZoom === 'after-click';

  container.classList.add('vpb-map');
  if (!container.hasAttribute('tabindex')) container.setAttribute('tabindex', '0');
  if (opts.label && !container.getAttribute('aria-label')) container.setAttribute('aria-label', opts.label);
  container.setAttribute('aria-roledescription', 'map');
  var tilesEl = el('div', 'vpb-map-tiles', container);
  var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('class', 'vpb-map-lines'); svg.setAttribute('aria-hidden', 'true');
  container.appendChild(svg);
  var pinsEl = el('div', 'vpb-map-pins', container);
  var popup = el('div', 'vpb-map-popup', container); popup.hidden = true;
  popup.setAttribute('role', 'dialog');
  var zoomBox = el('div', 'vpb-map-zoom', container);
  var zin = el('button', '', zoomBox), zout = el('button', '', zoomBox);
  zin.type = zout.type = 'button'; zin.textContent = '+'; zout.textContent = '\u2212';
  zin.setAttribute('aria-label', 'Zoom in'); zout.setAttribute('aria-label', 'Zoom out');
  var credit = el('div', 'vpb-map-credit', container); credit.innerHTML = CREDIT;
  var tiles = {}; // 'z/x/y' → img
  // Tile pictures: OpenStreetMap unless the caller gives its own (a URL
  // template with {z} {x} {y}, or a function (z, x, y) → URL).
  var tileSrc = typeof opts.tileUrl === 'function' ? opts.tileUrl : function (z, x, y) {
    return (opts.tileUrl || TILE_URL).replace('{z}', z).replace('{x}', x).replace('{y}', y);
  };
  if (opts.credit) credit.innerHTML = opts.credit;

  function size() { return { w: container.clientWidth || 300, h: container.clientHeight || 200 }; }
  /** Top-left world pixel of the view. */
  function origin() { var c = project(state.center, state.zoom), s = size(); return { x: c.x - s.w / 2, y: c.y - s.h / 2 }; }
  function toPoint(ll) { var p = project(ll, state.zoom), o = origin(); return { x: p.x - o.x, y: p.y - o.y }; }
  function toLatLng(pt) { var o = origin(); return unproject({ x: o.x + pt.x, y: o.y + pt.y }, state.zoom); }
  function emit(name, arg) { (handlers[name] || []).forEach(function (fn) { fn(arg); }); }

  /**
   * Draw the view. Tiles come from the zoom level nearest the current
   * (possibly fractional) zoom, scaled to fit. Tiles from other levels stay
   * underneath until the new level has finished loading, so zooming never
   * flashes blank.
   */
  var renderQueued = false;
  function queueRender() { if (!renderQueued) { renderQueued = true; requestAnimationFrame(function () { renderQueued = false; render(); }); } }
  function render() {
    var cz = clampZoom(Math.round(state.zoom)), s = size();
    var n = Math.pow(2, cz), scale = Math.pow(2, state.zoom - cz);
    var c = project(state.center, cz);
    var o = { x: c.x - s.w / 2 / scale, y: c.y - s.h / 2 / scale };
    var x0 = Math.floor(o.x / TILE), y0 = Math.max(0, Math.floor(o.y / TILE));
    var x1 = Math.floor((o.x + s.w / scale) / TILE), y1 = Math.min(n - 1, Math.floor((o.y + s.h / scale) / TILE));
    var needed = {}, allLoaded = true;
    for (var tx = x0; tx <= x1; tx++) {
      for (var ty = y0; ty <= y1; ty++) {
        var key = cz + '/' + tx + '/' + ty;
        needed[key] = true;
        var t = tiles[key];
        if (!t) {
          var img = el('img', 'vpb-map-tile', tilesEl);
          img.alt = ''; img.draggable = false; img.decoding = 'async';
          t = tiles[key] = { img: img, z: cz, x: tx, y: ty, done: false };
          (function (t) {
            img.onload = function () { t.done = true; img.classList.add('loaded'); queueRender(); };
            img.onerror = function () { t.done = true; img.classList.add('failed'); queueRender(); };
          })(t);
          img.src = tileSrc(cz, ((tx % n) + n) % n, ty);
        }
        if (!t.done) allLoaded = false;
      }
    }
    Object.keys(tiles).forEach(function (k) {
      var t = tiles[k];
      var keep = needed[k] || (!allLoaded && t.done && !t.img.classList.contains('failed') && Math.abs(t.z - cz) <= 3);
      if (keep) {
        // Position any level's tile at the current zoom.
        var sc = Math.pow(2, state.zoom - t.z), cc = project(state.center, t.z);
        var left = (t.x * TILE - cc.x) * sc + s.w / 2, top = (t.y * TILE - cc.y) * sc + s.h / 2, w = TILE * sc;
        if (!needed[k] && (left > s.w || top > s.h || left + w < 0 || top + w < 0)) keep = false;
        else {
          t.img.style.transform = 'translate(' + left + 'px,' + top + 'px)';
          t.img.style.width = t.img.style.height = (w + 0.5) + 'px';
          t.img.style.zIndex = needed[k] ? 2 : 1;
        }
      }
      if (!keep) { t.img.remove(); delete tiles[k]; }
    });
    pins.forEach(placePin);
    drawLines();
    if (!popup.hidden && popup._pin) placePopup(popup._pin);
    zin.disabled = state.zoom >= MAX_ZOOM; zout.disabled = state.zoom <= MIN_ZOOM;
  }

  function placePin(pin) {
    var p = toPoint(pin.latlng);
    pin.el.style.transform = 'translate(' + Math.round(p.x) + 'px,' + Math.round(p.y) + 'px)';
  }
  function drawLines() {
    var s = size();
    svg.setAttribute('width', s.w); svg.setAttribute('height', s.h);
    while (svg.firstChild) svg.removeChild(svg.firstChild);
    lines.forEach(function (line) {
      var pl = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
      pl.setAttribute('points', line.points.map(function (ll) { var p = toPoint(ll); return p.x.toFixed(1) + ',' + p.y.toFixed(1); }).join(' '));
      pl.setAttribute('class', 'vpb-map-line' + (line.dashed ? ' dashed' : ''));
      svg.appendChild(pl);
    });
  }

  // ---- view changes
  function stopAnim() {
    if (state.anim) { cancelAnimationFrame(state.anim); state.anim = null; }
    if (zoomTarget !== null) { zoomTarget = null; }
  }
  function setView(ll, z, keepAnim) {
    if (!keepAnim) stopAnim();
    state.center = [Math.max(-MAX_LAT, Math.min(MAX_LAT, ll[0])), ll[1]];
    if (typeof z === 'number') state.zoom = clampZoom(z);
    render();
    emit('move');
  }
  /** Zoom to `z` keeping the spot under container point `pt` still (no animation). */
  function zoomTo(z, pt) {
    var s = size();
    pt = pt || { x: s.w / 2, y: s.h / 2 };
    var under = toLatLng(pt);
    state.zoom = clampZoom(z);
    var p = project(under, state.zoom);
    state.center = unproject({ x: p.x - pt.x + s.w / 2, y: p.y - pt.y + s.h / 2 }, state.zoom);
    render();
  }
  /**
   * Zoom by `dz` whole levels around `pt`, animated (about a quarter second;
   * instant with reduced motion). Zooming again while animating continues
   * from the level it was heading to.
   */
  var zoomTarget = null;
  function zoomAround(dz, pt) {
    var from = state.zoom, base = zoomTarget !== null ? zoomTarget : Math.round(from);
    var to = clampZoom(Math.round(base + dz));
    if (to === from && zoomTarget === null) return;
    stopAnim();
    if (reduceMotion()) { zoomTo(to, pt); emit('move'); return; }
    zoomTarget = to;
    var start = null;
    function step(t) {
      if (start === null) start = t;
      var k = Math.min(1, (t - start) / 250), e = 1 - Math.pow(1 - k, 3);
      zoomTo(from + (to - from) * e, pt);
      if (k < 1) state.anim = requestAnimationFrame(step);
      else { state.anim = null; zoomTarget = null; emit('move'); }
    }
    state.anim = requestAnimationFrame(step);
  }
  /** Pan by dx, dy pixels over `ms` (instant with reduced motion). */
  function animatePan(dx, dy, ms) {
    stopAnim();
    if (reduceMotion() || !ms) { panBy(dx, dy); return; }
    var start = null, done = 0;
    function step(t) {
      if (start === null) start = t;
      var k = Math.min(1, (t - start) / ms), e = 1 - Math.pow(1 - k, 3);
      panBy(dx * (e - done), dy * (e - done), true); done = e;
      if (k < 1) state.anim = requestAnimationFrame(step); else { state.anim = null; emit('move'); }
    }
    state.anim = requestAnimationFrame(step);
  }
  function panBy(dx, dy, keepAnim) {
    var c = project(state.center, state.zoom);
    setView(unproject({ x: c.x + dx, y: c.y + dy }, state.zoom), state.zoom, keepAnim);
  }
  function fitBounds(points, o) {
    o = o || {};
    if (!points.length) return;
    if (points.length === 1) { setView(points[0], o.maxZoom || 15); return; }
    var pad = o.padding == null ? 30 : o.padding, s = size(), z = o.maxZoom || MAX_ZOOM;
    for (; z > MIN_ZOOM; z--) {
      var ps = points.map(function (ll) { return project(ll, z); });
      var xs = ps.map(function (p) { return p.x; }), ys = ps.map(function (p) { return p.y; });
      if (Math.max.apply(null, xs) - Math.min.apply(null, xs) <= s.w - 2 * pad && Math.max.apply(null, ys) - Math.min.apply(null, ys) <= s.h - 2 * pad) break;
    }
    var q = points.map(function (ll) { return project(ll, z); });
    var cx = (Math.min.apply(null, q.map(function (p) { return p.x; })) + Math.max.apply(null, q.map(function (p) { return p.x; }))) / 2;
    var cy = (Math.min.apply(null, q.map(function (p) { return p.y; })) + Math.max.apply(null, q.map(function (p) { return p.y; }))) / 2;
    setView(unproject({ x: cx, y: cy }, z), z);
  }
  /** Glide to a point: zoom out a little if it's far, travel, zoom in. */
  function flyTo(ll, z, o) {
    o = o || {};
    z = clampZoom(typeof z === 'number' ? z : state.zoom);
    if (o.animate === false || reduceMotion()) { setView(ll, z); return; }
    stopAnim();
    var s = size(), z0 = state.zoom;
    var a = project(state.center, z0), b = project(ll, z0);
    var dist = Math.sqrt(Math.pow(b.x - a.x, 2) + Math.pow(b.y - a.y, 2)) / Math.max(s.w, s.h);
    var zMid = Math.min(z0, z) - (dist > 1 ? Math.min(4, Math.ceil(Math.log(dist) / Math.LN2) + 1) : 0);
    var from = state.center, start = null, dur = (o.duration || 1.2) * 1000;
    function step(t) {
      if (start === null) start = t;
      var k = Math.min(1, (t - start) / dur), e = k < 0.5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2;
      // Travel in projected space so the motion looks straight on the map.
      var zz = k < 0.5 ? z0 + (zMid - z0) * (k * 2) : zMid + (z - zMid) * ((k - 0.5) * 2);
      var pa = project(from, 20), pb = project(ll, 20);
      state.center = unproject({ x: pa.x + (pb.x - pa.x) * e, y: pa.y + (pb.y - pa.y) * e }, 20);
      state.zoom = k < 1 ? zz : z;
      render();
      if (k < 1) state.anim = requestAnimationFrame(step); else { state.anim = null; emit('move'); }
    }
    state.anim = requestAnimationFrame(step);
  }

  // ---- pins
  function addPin(ll, o) {
    o = o || {};
    var b = el('button', 'vpb-map-pin' + (o.className ? ' ' + o.className : '') + (o.text ? ' has-text' : ''), pinsEl);
    b.type = 'button';
    if (o.title) { b.title = o.title; b.setAttribute('aria-label', o.title); }
    // The button only carries the position; the shape inside is the
    // teardrop (rotating the button itself would skew its position).
    var shape = el('span', 'vpb-map-pin-shape', b);
    var label = el('span', 'vpb-map-pin-label', shape);
    label.textContent = o.text || '';
    var pin = { el: b, latlng: [ll[0], ll[1]], opts: o };
    pin.setLatLng = function (v) { pin.latlng = [v[0], v[1]]; placePin(pin); };
    pin.setClass = function (cls, on) { b.classList.toggle(cls, on); };
    pin.openPopup = function () { openPopup(pin); };
    var drag = null;
    b.addEventListener('pointerdown', function (e) {
      e.stopPropagation();
      if (!o.draggable) return;
      drag = { id: e.pointerId, x: e.clientX, y: e.clientY, moved: false, start: toPoint(pin.latlng) };
      b.setPointerCapture && b.setPointerCapture(e.pointerId);
    });
    b.addEventListener('pointermove', function (e) {
      if (!drag || e.pointerId !== drag.id) return;
      var dx = e.clientX - drag.x, dy = e.clientY - drag.y;
      if (!drag.moved && Math.abs(dx) + Math.abs(dy) < 4) return;
      drag.moved = true;
      b.classList.add('dragging');
      pin.latlng = toLatLng({ x: drag.start.x + dx, y: drag.start.y + dy });
      placePin(pin);
    });
    b.addEventListener('pointerup', function () {
      if (drag && drag.moved) { b.classList.remove('dragging'); pin.justDragged = true; if (o.onDragEnd) o.onDragEnd(pin.latlng); }
      drag = null;
    });
    b.addEventListener('click', function (e) {
      e.stopPropagation();
      if (pin.justDragged) { pin.justDragged = false; return; }
      if (o.popup) openPopup(pin);
      if (o.onClick) o.onClick();
    });
    pins.push(pin);
    placePin(pin);
    return pin;
  }
  function openPopup(pin) {
    popup.innerHTML = '';
    var close = el('button', 'vpb-map-popup-close', popup);
    close.type = 'button'; close.textContent = '\u00D7'; close.setAttribute('aria-label', 'Close');
    close.addEventListener('click', closePopup);
    var body = el('div', 'vpb-map-popup-body', popup);
    body.innerHTML = pin.opts.popup; // already escaped by the caller
    popup.setAttribute('aria-label', pin.opts.title || 'Place');
    popup.hidden = false; popup._pin = pin;
    placePopup(pin);
    close.focus({ preventScroll: true });
    // Nudge the map so the whole popup is visible.
    var pr = popup.getBoundingClientRect(), cr = container.getBoundingClientRect(), m = 10, dx = 0, dy = 0;
    if (pr.left < cr.left + m) dx = pr.left - cr.left - m;
    else if (pr.right > cr.right - m) dx = pr.right - cr.right + m;
    if (pr.top < cr.top + m) dy = pr.top - cr.top - m;
    else if (pr.bottom > cr.bottom - m) dy = pr.bottom - cr.bottom + m;
    if (dx || dy) animatePan(dx, dy, 250);
  }
  function placePopup(pin) {
    var p = toPoint(pin.latlng);
    popup.style.transform = 'translate(' + Math.round(p.x) + 'px,' + Math.round(p.y - 34) + 'px) translate(-50%, -100%)';
  }
  function closePopup() {
    if (popup.hidden) return;
    var pin = popup._pin;
    popup.hidden = true; popup._pin = null;
    if (pin && document.activeElement && popup.contains(document.activeElement)) pin.el.focus({ preventScroll: true });
  }

  // ---- interaction: drag to pan, pinch, wheel, double-click, keys
  var pointers = {}, gesture = null;
  container.addEventListener('pointerdown', function (e) {
    if (e.target.closest('.vpb-map-zoom, .vpb-map-popup, .vpb-map-credit, .vpb-map-pin')) return;
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    stopAnim();
    pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
    container.setPointerCapture && container.setPointerCapture(e.pointerId);
    var ids = Object.keys(pointers);
    if (ids.length === 1) gesture = { type: 'pan', x: e.clientX, y: e.clientY, sx: e.clientX, sy: e.clientY, moved: false, track: [{ x: e.clientX, y: e.clientY, t: Date.now() }] };
    else if (ids.length === 2) {
      var a = pointers[ids[0]], c = pointers[ids[1]];
      gesture = { type: 'pinch', d: Math.hypot(a.x - c.x, a.y - c.y) || 1, z: state.zoom, moved: true };
    }
  });
  container.addEventListener('pointermove', function (e) {
    if (!pointers[e.pointerId] || !gesture) return;
    pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
    if (gesture.type === 'pan') {
      var dx = e.clientX - gesture.x, dy = e.clientY - gesture.y;
      if (!gesture.moved && Math.abs(e.clientX - gesture.sx) + Math.abs(e.clientY - gesture.sy) < 4) return;
      gesture.moved = true;
      container.classList.add('panning');
      gesture.x = e.clientX; gesture.y = e.clientY;
      gesture.track.push({ x: e.clientX, y: e.clientY, t: Date.now() });
      if (gesture.track.length > 6) gesture.track.shift();
      panBy(-dx, -dy);
    } else {
      var ids = Object.keys(pointers), a = pointers[ids[0]], b = pointers[ids[1]];
      if (!a || !b) return;
      var r = container.getBoundingClientRect(), mid = { x: (a.x + b.x) / 2 - r.left, y: (a.y + b.y) / 2 - r.top };
      var target = gesture.z + Math.log(Math.hypot(a.x - b.x, a.y - b.y) / gesture.d) / Math.LN2;
      var under = toLatLng(mid);
      state.zoom = clampZoom(target);
      var p = project(under, state.zoom), s = size();
      state.center = unproject({ x: p.x - mid.x + s.w / 2, y: p.y - mid.y + s.h / 2 }, state.zoom);
      render();
    }
  });
  function endPointer(e) {
    if (!pointers[e.pointerId]) return;
    delete pointers[e.pointerId];
    container.classList.remove('panning');
    if (!gesture) return;
    if (gesture.type === 'pinch') {
      // Settle on a whole zoom level so tiles are sharp.
      zoomAround(0, null);
      gesture = Object.keys(pointers).length ? { type: 'pan', x: 0, y: 0, sx: 0, sy: 0, moved: true, stale: true } : null;
      return;
    }
    if (e.type === 'pointerup' && !gesture.moved) tap(e);
    else if (e.type === 'pointerup' && gesture.type === 'pan' && gesture.track) glide(gesture.track);
    if (!Object.keys(pointers).length) gesture = null;
  }
  /** Keep drifting a little after a quick drag, slowing to a stop. */
  function glide(track) {
    if (reduceMotion() || track.length < 2) return;
    var a = track[0], b = track[track.length - 1], dt = b.t - a.t;
    if (Date.now() - b.t > 80 || dt <= 0) return; // the finger had stopped
    var vx = (b.x - a.x) / dt, vy = (b.y - a.y) / dt; // px per ms
    var speed = Math.sqrt(vx * vx + vy * vy);
    if (speed < 0.3) return;
    if (speed > 1.5) { vx *= 1.5 / speed; vy *= 1.5 / speed; } // a flick, not a fling across the county
    var last = null;
    function step(t) {
      var ms = last === null ? 16 : Math.min(40, t - last); last = t;
      vx *= Math.pow(0.93, ms / 16); vy *= Math.pow(0.93, ms / 16);
      panBy(-vx * ms, -vy * ms, true);
      if (Math.sqrt(vx * vx + vy * vy) > 0.02) state.anim = requestAnimationFrame(step); else { state.anim = null; emit('move'); }
    }
    stopAnim();
    state.anim = requestAnimationFrame(step);
  }
  /**
   * A tap or click (no drag). Two within 300 ms and 30 px zoom in there
   * (shift: out) instead; so a single one is reported a moment later, and a
   * double one isn't reported as two clicks (no stray pins in the editor).
   */
  var lastTap = null, tapTimer = null;
  function tap(e) {
    var r = container.getBoundingClientRect(), pt = { x: e.clientX - r.left, y: e.clientY - r.top }, now = Date.now();
    closePopup();
    if (wheelAfterClick) wheelOn = true;
    if (lastTap && now - lastTap.t < 300 && Math.abs(pt.x - lastTap.x) < 30 && Math.abs(pt.y - lastTap.y) < 30) {
      clearTimeout(tapTimer); lastTap = null;
      zoomAround(e.shiftKey ? -1 : 1, pt);
      return;
    }
    lastTap = { x: pt.x, y: pt.y, t: now };
    var ll = toLatLng(pt);
    clearTimeout(tapTimer);
    tapTimer = setTimeout(function () { lastTap = null; emit('click', ll); }, 260);
  }
  container.addEventListener('pointerup', endPointer);
  container.addEventListener('pointercancel', endPointer);
  var wheelAt = 0;
  container.addEventListener('wheel', function (e) {
    if (!wheelOn) return;
    e.preventDefault();
    var now = Date.now();
    if (now - wheelAt < 220 || Math.abs(e.deltaY) < 1) return;
    wheelAt = now;
    var r = container.getBoundingClientRect();
    zoomAround(e.deltaY < 0 ? 1 : -1, { x: e.clientX - r.left, y: e.clientY - r.top });
  }, { passive: false });
  // Double-clicks are handled by tap(); stop the browser selecting text.
  container.addEventListener('dblclick', function (e) { e.preventDefault(); });
  container.addEventListener('keydown', function (e) {
    if (e.target !== container) { if (e.key === 'Escape') closePopup(); return; }
    var step = 80, done = true;
    if (e.key === 'ArrowLeft') panBy(-step, 0);
    else if (e.key === 'ArrowRight') panBy(step, 0);
    else if (e.key === 'ArrowUp') panBy(0, -step);
    else if (e.key === 'ArrowDown') panBy(0, step);
    else if (e.key === '+' || e.key === '=') zoomAround(1);
    else if (e.key === '-' || e.key === '_') zoomAround(-1);
    else if (e.key === 'Escape') closePopup();
    else done = false;
    if (done) e.preventDefault();
  });
  if (wheelAfterClick) {
    wheelOn = false;
    container.addEventListener('focus', function () { wheelOn = true; });
    container.addEventListener('mouseleave', function () { wheelOn = false; });
    container.addEventListener('blur', function () { wheelOn = false; });
  }
  zin.addEventListener('click', function (e) { e.stopPropagation(); zoomAround(1); });
  zout.addEventListener('click', function (e) { e.stopPropagation(); zoomAround(-1); });
  if (window.ResizeObserver) new ResizeObserver(function () { render(); }).observe(container);
  else window.addEventListener('resize', render);

  var api = {
    setView: function (ll, z) { setView(ll, z); return api; },
    fitBounds: function (pts, o) { fitBounds(pts, o); return api; },
    flyTo: function (ll, z, o) { flyTo(ll, z, o); return api; },
    getZoom: function () { return state.zoom; },
    getCenter: function () { return state.center.slice(); },
    addPin: addPin,
    addLine: function (pts, o) { lines.push({ points: pts.slice(), dashed: !!(o && o.dashed) }); drawLines(); return api; },
    on: function (name, fn) { (handlers[name] = handlers[name] || []).push(fn); return api; },
    toLatLng: toLatLng, toPoint: toPoint, closePopup: closePopup, el: container
  };
  setView([0, 0], 2);
  return api;
}

window.VpbMap = { create: create, project: project, unproject: unproject };
})(window);
