/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * Map of places: place pins by clicking a map in the sidebar.
 *
 * Adds a tool above the block's generated settings (registry.extend): an
 * OpenStreetMap map (the module's own, asset/map) showing the places. Click the map to
 * add a place there; drag a pin to move it. Latitude and longitude in each
 * place's settings change with it and can still be typed.
 */
(function (VPB) {
'use strict';

var h = VPB.dom.h;

function changed() { VPB.store.commit(); VPB.canvas.render(); VPB.app.refreshStatus(); VPB.sidebar.renderProps(); }

// The place just added by clicking the map, being named: { block, index }.
var naming = null;
// Which setting holds the places, per block type (Map of places, Story map).
var LIST = { specialMap: 'places', specialStoryMap: 'stops' };
function listOf(b) { return LIST[b.layout] || 'places'; }

/** Add a place at lat/lng to block b (also used by tests), then ask for its name and item. */
function addPlace(b, lat, lng) {
  var key = listOf(b);
  var places = Array.isArray(VPB.util.obj(b.data)[key]) ? b.data[key].slice() : [];
  places.push(key === 'stops'
    ? { title: '', date: '', lat: lat.toFixed(6), lng: lng.toFixed(6), item_id: null, img: 'item', asset_id: null, text: '', video: 'none', video_url: '' }
    : { name: '', lat: lat.toFixed(6), lng: lng.toFixed(6), note: '', item_id: null, img: 'item', asset_id: null });
  var patch = {}; patch[key] = places;
  b.data = Object.assign({}, b.data, patch);
  naming = { block: b.id, index: places.length - 1 };
  changed();
}

/** Set fields on place i of block b. */
function setPlace(b, i, fields) {
  var key = listOf(b), places = b.data[key].slice(), patch = {};
  places[i] = Object.assign({}, places[i], fields);
  patch[key] = places;
  b.data = Object.assign({}, b.data, patch);
}

/**
 * "New place" form shown right under the map after a click: name it, link an
 * item from the archive (optional), then Done. Remove takes the pin away.
 */
function namingForm(b) {
  var key = listOf(b), nameKey = key === 'stops' ? 'title' : 'name';
  var i = naming.index, place = b.data[key][i];
  if (!place) { naming = null; return null; }
  var box = h('div', { class: 'pm-new', role: 'group', 'aria-label': 'New place' });
  box.appendChild(h('b', { text: key === 'stops' ? 'New stop' : 'New place' }));
  var name = h('input', { type: 'text', id: 'pm-new-name', placeholder: 'Name, e.g. The depot', 'aria-label': 'Name of the new place' });
  name.value = place[nameKey] || '';
  name.addEventListener('input', function () { var f = {}; f[nameKey] = name.value; setPlace(b, i, f); VPB.app.refreshStatus(); });
  name.addEventListener('change', function () { VPB.store.commit(); VPB.canvas.render(); });
  name.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); done(); } });
  box.appendChild(h('label', { for: 'pm-new-name', text: 'Name' }));
  box.appendChild(name);
  if (key === 'stops') {
    // A story stop also needs its date (it sets the order).
    var date = h('input', { type: 'text', id: 'pm-new-date', placeholder: 'e.g. 1900-09-08, June 1911, circa 1920', 'aria-label': 'Date of the new stop' });
    date.value = place.date || '';
    date.addEventListener('input', function () { setPlace(b, i, { date: date.value }); VPB.app.refreshStatus(); });
    date.addEventListener('change', function () { VPB.store.commit(); VPB.canvas.render(); });
    box.appendChild(h('label', { for: 'pm-new-date', text: 'Date' }));
    box.appendChild(date);
  }
  var item = place.item_id && VPB.refs.items[place.item_id];
  if (item) {
    box.appendChild(h('p', { class: 'note', text: 'Linked item: ' + item.title }));
  }
  box.appendChild(VPB.picker.host(b, 'items', function (row) {
    VPB.refs.items[row.id] = row;
    var patch = { item_id: row.id };
    if (!b.data[key][i][nameKey]) patch[nameKey] = row.title; // An unnamed place takes the item's title.
    setPlace(b, i, patch);
    changed();
  }, { slot: 'pm-item', single: true, label: item ? 'Change the item\u2026' : 'Link an item (optional)\u2026' }));
  function done() {
    if (!b.data[key][i][nameKey]) { var f = {}; f[nameKey] = (key === 'stops' ? 'Stop ' : 'Place ') + (i + 1); setPlace(b, i, f); }
    naming = null;
    changed();
    VPB.dom.toast('Place saved on the map.');
  }
  box.appendChild(h('div', { class: 'pm-new-actions' }, [
    h('button', { type: 'button', class: 'btn small primary', text: 'Done', onclick: done }),
    h('button', { type: 'button', class: 'btn small', text: 'Remove this pin', onclick: function () {
      var rest = b.data[key].slice(), p2 = {}; rest.splice(i, 1); p2[key] = rest;
      b.data = Object.assign({}, b.data, p2);
      naming = null; changed();
    } })
  ]));
  setTimeout(function () { if (document.body.contains(name) && !place.item_id) name.focus(); }, 0);
  return box;
}

function mapTool(box, b) {
  var wrap = h('div', { class: 'field pm-tool' }, [h('span', { class: 'lbl', text: 'Place pins' })]);
  wrap.appendChild(VPB.fields.note('Click the map to add a place there; then name it and, if you like, link an item. Drag a pin to move it. Scroll or use + and \u2212 to zoom.'));
  var el = h('div', { class: 'pm-map', role: 'application', 'aria-label': 'Map for placing pins' });
  wrap.appendChild(el);
  if (naming && naming.block === b.id) {
    var form = namingForm(b);
    if (form) wrap.appendChild(form);
  } else if (naming) {
    naming = null; // A different block is selected now.
  }
  box.appendChild(wrap);
  if (!window.VpbMap) { el.textContent = 'The map couldn\u2019t load. Type latitude and longitude below instead.'; return; }
  // Draw once the sidebar is in the page (the map needs the element's size).
  setTimeout(function () {
    if (!document.body.contains(el)) return;
    var map = window.VpbMap.create(el, { wheelZoom: 'after-click', label: 'Map for placing pins. Click to add a place; drag a pin to move it.' });
    var key = listOf(b);
    var places = Array.isArray(VPB.util.obj(b.data)[key]) ? b.data[key] : [];
    var pts = [];
    places.forEach(function (p, i) {
      var lat = parseFloat(p.lat), lng = parseFloat(p.lng);
      if (isNaN(lat) || isNaN(lng)) return;
      pts.push([lat, lng]);
      map.addPin([lat, lng], { text: String(i + 1), title: (i + 1) + '. ' + (p.name || p.title || '') + ' (drag to move)', draggable: true,
        onDragEnd: function (ll) { setPlace(b, i, { lat: ll[0].toFixed(6), lng: ll[1].toFixed(6) }); changed(); } });
    });
    // Start at the pins, or at Galveston County when there are none yet.
    if (pts.length) map.fitBounds(pts, { padding: 24, maxZoom: 16 });
    else map.setView([29.46, -95.05], 11);
    map.on('click', function (ll) { addPlace(b, ll[0], ll[1]); });
  }, 0);
}

VPB.blocks.extend('specialMap', mapTool);
VPB.blocks.extend('specialStoryMap', mapTool);

VPB.placesMapTool = { addPlace: addPlace, naming: function () { return naming; } };
})(window.VPB);
