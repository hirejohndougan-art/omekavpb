'use strict';
// v1.10.0: the module's own map (asset/map/vpb-map.js), replacing Leaflet.
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');
const H = require('../lib/harness');

const t = H.suite('own map (vpb-map.js)');
(async () => {
const dom = new JSDOM('<!doctype html><div id="m" style="width:600px;height:400px"></div>', { runScripts: 'outside-only', pretendToBeVisual: true });
const w = dom.window, d = w.document;
w.matchMedia = () => ({ matches: true }); // reduced motion: flyTo jumps (testable)
Object.defineProperty(w.HTMLElement.prototype, 'clientWidth', { get() { return this.id === 'm' ? 600 : 0; } });
Object.defineProperty(w.HTMLElement.prototype, 'clientHeight', { get() { return this.id === 'm' ? 400 : 0; } });
w.HTMLElement.prototype.getBoundingClientRect = function () { return { left: 0, top: 0, width: 600, height: 400, right: 600, bottom: 400 }; };
w.eval(fs.readFileSync(path.join(H.MODULE_DIR, 'asset/map/vpb-map.js'), 'utf8'));
const V = w.VpbMap;
const near = (a, b, e = 1e-6) => Math.abs(a - b) < e;

// Projection: standard Web Mercator.
const p0 = V.project([0, 0], 0);
t.ok(near(p0.x, 128) && near(p0.y, 128), 'lat/lng 0,0 is the middle of the world at zoom 0');
const ll = V.unproject(V.project([29.4608, -95.0513], 13), 13);
t.ok(near(ll[0], 29.4608) && near(ll[1], -95.0513), 'project → unproject round-trips');
const tile = V.project([29.4608, -95.0513], 12);
t.ok(Math.floor(tile.x / 256) === 966 && Math.floor(tile.y / 256) === 1696, 'Dickinson is OpenStreetMap tile 12/966/1696 (matches the standard OpenStreetMap tile formula)');

const el = d.getElementById('m');
const map = V.create(el, { wheelZoom: 'after-click', label: 'Test map' });
map.setView([29.46, -95.05], 13);
t.ok(el.getAttribute('aria-label') === 'Test map' && el.getAttribute('tabindex') === '0' && el.getAttribute('aria-roledescription') === 'map', 'the map is labeled and keyboard-focusable');
const imgs = [...el.querySelectorAll('.vpb-map-tile')];
t.ok(imgs.length >= 6 && imgs.every(i => /^https:\/\/tile\.openstreetmap\.org\/13\/\d+\/\d+\.png$/.test(i.src)), 'loads OpenStreetMap tiles for the view (' + imgs.length + ')');
t.ok(/OpenStreetMap/.test(el.querySelector('.vpb-map-credit').textContent), 'credits OpenStreetMap');

// Pins: placed where they belong.
let clicked = 0;
const pin = map.addPin([29.46, -95.05], { text: '1', title: 'Depot', popup: '<b>Depot</b>', onClick: () => clicked++ });
t.ok(/translate\(300px,\s*200px\)/.test(pin.el.style.transform) && pin.el.tagName === 'BUTTON' && pin.el.getAttribute('aria-label') === 'Depot', 'a pin at the center sits at the middle; pins are labeled buttons');
pin.el.click();
const popup = el.querySelector('.vpb-map-popup');
t.ok(!popup.hidden && /Depot/.test(popup.textContent) && clicked === 1, 'clicking a pin opens its popup');
el.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
popup.querySelector('.vpb-map-popup-close').click();
t.ok(popup.hidden, 'the popup closes');

// Points ↔ positions.
const back = map.toLatLng(map.toPoint([29.5, -95.1]));
t.ok(near(back[0], 29.5, 1e-9) && near(back[1], -95.1, 1e-9), 'screen position ↔ lat/lng round-trips');

// Keyboard: arrows pan, + and − zoom.
const c0 = map.getCenter();
el.focus(); el.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
t.ok(map.getCenter()[1] > c0[1], 'ArrowRight pans east');
el.dispatchEvent(new w.KeyboardEvent('keydown', { key: '+', bubbles: true }));
t.ok(map.getZoom() === 14, '+ zooms in');
el.querySelector('.vpb-map-zoom button[aria-label="Zoom out"]').click();
t.ok(map.getZoom() === 13, 'the − button zooms out');

// fitBounds keeps every point inside, with padding.
const pts = [[29.40, -95.10], [29.52, -94.98], [29.47, -95.03]];
map.fitBounds(pts, { padding: 30, maxZoom: 16 });
t.ok(pts.every(p => { const q = map.toPoint(p); return q.x >= 29 && q.x <= 571 && q.y >= 29 && q.y <= 371; }) && map.getZoom() < 16, 'fitting puts every point in view (zoom ' + map.getZoom() + ')');
map.fitBounds([[29.46, -95.05]], { maxZoom: 15 });
t.ok(map.getZoom() === 15, 'one point: its zoom');

// flyTo (reduced motion → jumps), lines, click → lat/lng.
map.flyTo([29.40, -95.10], 14, { duration: 1 });
t.ok(near(map.getCenter()[0], 29.40) && map.getZoom() === 14, 'flyTo lands on the point (jumps with reduced motion)');
map.addLine(pts, { dashed: true });
const line = el.querySelector('svg.vpb-map-lines polyline');
t.ok(line && line.getAttribute('class') === 'vpb-map-line dashed' && line.getAttribute('points').split(' ').length === 3, 'dashed line through the points');
let got = null;
map.on('click', v => { got = v; });
const pe = (type, x, y, id = 1) => (w.PointerEvent ? new w.PointerEvent(type, { clientX: x, clientY: y, pointerId: id, bubbles: true, button: 0, pointerType: 'mouse' }) : null);
if (w.PointerEvent) {
  el.dispatchEvent(pe('pointerdown', 300, 200)); el.dispatchEvent(pe('pointerup', 300, 200));
  t.ok(got === null, 'a click is reported a moment later (in case it\u2019s a double click)');
  await H.sleep(320);
  t.ok(got && near(got[0], 29.40, 1e-6) && near(got[1], -95.10, 1e-6), 'a click (no drag) reports the lat/lng clicked');
  // Double click: zooms in there, and isn't reported as clicks.
  got = null; const zd = map.getZoom();
  el.dispatchEvent(pe('pointerdown', 300, 200)); el.dispatchEvent(pe('pointerup', 300, 200));
  el.dispatchEvent(pe('pointerdown', 302, 201)); el.dispatchEvent(pe('pointerup', 302, 201));
  await H.sleep(320);
  t.ok(map.getZoom() === zd + 1 && got === null, 'a double click (or double tap) zooms in and adds no click');
  const before = map.getCenter();
  el.dispatchEvent(pe('pointerdown', 300, 200)); el.dispatchEvent(pe('pointermove', 200, 200)); el.dispatchEvent(pe('pointerup', 200, 200));
  t.ok(map.getCenter()[1] > before[1], 'dragging pans the map');
  // Pinch: two pointers moving apart zoom in.
  const z0 = map.getZoom();
  el.dispatchEvent(pe('pointerdown', 250, 200, 11)); el.dispatchEvent(pe('pointerdown', 350, 200, 12));
  el.dispatchEvent(pe('pointermove', 150, 200, 11)); el.dispatchEvent(pe('pointermove', 450, 200, 12));
  el.dispatchEvent(pe('pointerup', 150, 200, 11)); el.dispatchEvent(pe('pointerup', 450, 200, 12));
  t.ok(map.getZoom() === z0 + 2, 'pinching apart zooms in (and settles on a whole level): ' + z0 + ' → ' + map.getZoom());
} else {
  t.ok(true, 'pointer events not available in this test browser (checked in tests/browser)');
}
// Wheel only after the map was clicked/focused.
const zw = map.getZoom();
el.dispatchEvent(new w.Event('mouseleave'));
el.dispatchEvent(new w.WheelEvent('wheel', { deltaY: -100, clientX: 300, clientY: 200, bubbles: true, cancelable: true }));
t.ok(map.getZoom() === zw, 'the scroll wheel scrolls the page until the map is clicked');
el.dispatchEvent(new w.Event('focus'));
el.dispatchEvent(new w.WheelEvent('wheel', { deltaY: -100, clientX: 300, clientY: 200, bubbles: true, cancelable: true }));
t.ok(map.getZoom() === zw + 1, 'then it zooms');
t.done();
})().catch(e => { console.log('CRASH', e.stack); process.exitCode = 2; });
