/* SPDX-License-Identifier: GPL-2.0-or-later */
/**
 * "Who's who in this photo": place markers by clicking the photo.
 *
 * Adds a tool above the block's generated settings (registry.extend): the
 * photo with its numbered markers. Click the photo to add a marker there;
 * drag a marker to move it. The Across/Down numbers in each marker's
 * settings change with it, and still work for keyboard users.
 */
(function (VPB) {
'use strict';

var h = VPB.dom.h, REFS = VPB.refs;

function imageUrl(b) {
  var d = VPB.util.obj(b.data);
  if (d.item_id && REFS.items[d.item_id]) return REFS.items[d.item_id].large || REFS.items[d.item_id].thumb;
  if (d.asset_id && REFS.assets[d.asset_id]) return REFS.assets[d.asset_id].thumb;
  return null;
}

function changed() { VPB.store.commit(); VPB.canvas.render(); VPB.app.refreshStatus(); }

/** Percent position of a pointer event inside the photo, 0–100. */
function percent(img, e) {
  var r = img.getBoundingClientRect();
  var x = r.width ? (e.clientX - r.left) / r.width * 100 : 50;
  var y = r.height ? (e.clientY - r.top) / r.height * 100 : 50;
  return [Math.round(Math.max(0, Math.min(100, x))), Math.round(Math.max(0, Math.min(100, y)))];
}

VPB.blocks.extend('specialPhotoHotspots', function (box, b) {
  var url = imageUrl(b);
  var wrap = h('div', { class: 'field hs-tool' }, [h('span', { class: 'lbl', text: 'Place markers' })]);
  if (!url) {
    wrap.appendChild(VPB.fields.note('Choose the photo below first; then you can click on it to place markers.'));
    box.appendChild(wrap);
    return;
  }
  wrap.appendChild(VPB.fields.note('Click the photo to add a marker there. Drag a marker to move it. Then type its name below.'));
  var stage = h('div', { class: 'hs-stage' });
  var img = h('img', { src: url, alt: '', draggable: 'false' });
  stage.appendChild(img);
  var markers = Array.isArray(VPB.util.obj(b.data).markers) ? b.data.markers : [];
  markers.forEach(function (m, i) {
    var dot = h('button', { type: 'button', class: 'hs-dot', text: String(i + 1), 'aria-label': 'Marker ' + (i + 1) + (m.label ? ': ' + m.label : '') + '. Drag to move.' });
    dot.style.left = (m.x === undefined ? 50 : m.x) + '%';
    dot.style.top = (m.y === undefined ? 50 : m.y) + '%';
    dot.addEventListener('pointerdown', function (e) {
      e.preventDefault(); e.stopPropagation();
      function move(ev) {
        var p = percent(img, ev);
        dot.style.left = p[0] + '%'; dot.style.top = p[1] + '%';
        m.x = p[0]; m.y = p[1];
      }
      function up() {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', up);
        b.data = Object.assign({}, b.data, { markers: markers.slice() });
        changed(); VPB.sidebar.renderProps();
      }
      window.addEventListener('pointermove', move);
      window.addEventListener('pointerup', up);
    });
    stage.appendChild(dot);
  });
  img.addEventListener('click', function (e) {
    var p = percent(img, e);
    var next = markers.concat([{ label: 'Marker ' + (markers.length + 1), note: '', x: p[0], y: p[1] }]);
    b.data = Object.assign({}, b.data, { markers: next });
    changed(); VPB.sidebar.renderProps();
    VPB.dom.toast('Marker ' + next.length + ' added. Type its name in the Markers list below.');
  });
  wrap.appendChild(stage);
  box.appendChild(wrap);
});
})(window.VPB);
