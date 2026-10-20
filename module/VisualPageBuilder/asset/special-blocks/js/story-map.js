/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Story map. Shows one stop at a time: the map moves to it
 * (glides, or jumps with reduced motion), its pane shows, and its timeline
 * button is marked current. Play advances every N seconds (never starts by
 * itself) and stops at a stop with a video; Back to the start, Previous,
 * Next, and the timeline buttons move by hand. Videos load only when their
 * stop is shown.
 */
(function () {
  'use strict';
  function setUp(box) {
    if (box.getAttribute('data-ready')) return;
    var stops;
    try { stops = JSON.parse(box.getAttribute('data-stops')) || []; } catch (e) { return; }
    if (!stops.length) return;
    box.setAttribute('data-ready', '1');
    var panes = Array.prototype.slice.call(box.querySelectorAll('.special-blocks-story-pane'));
    var dots = Array.prototype.slice.call(box.querySelectorAll('.special-blocks-story-timeline button'));
    var controls = box.querySelector('.special-blocks-story-controls');
    var playBtn = box.querySelector('.special-blocks-story-play');
    var status = box.querySelector('.special-blocks-story-status');
    var seconds = Math.max(2, parseInt(box.getAttribute('data-seconds'), 10) || 6);
    var loop = box.getAttribute('data-loop') === '1';
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var cur = 0, timer = null, map = null, markers = [];
    box.classList.add('is-enhanced');
    controls.hidden = false;

    var mapEl = box.querySelector('.special-blocks-story-mapbox');
    if (window.VpbMap && mapEl && !mapEl.classList.contains('special-blocks-map-placeholder')) {
      map = window.VpbMap.create(mapEl, { wheelZoom: 'after-click', label: mapEl.getAttribute('aria-label') || 'Map' });
      stops.forEach(function (s, i) {
        markers.push(map.addPin([s.lat, s.lng], { text: String(i + 1), title: (i + 1) + '. ' + s.title, className: 'special-blocks-story-pin', onClick: function () { pause(); show(i); } }));
      });
      map.fitBounds(stops.map(function (s) { return [s.lat, s.lng]; }), { padding: 40, maxZoom: 15 });
    }

    function show(i) {
      cur = Math.max(0, Math.min(stops.length - 1, i));
      panes.forEach(function (p, j) {
        p.hidden = j !== cur;
        if (j !== cur) {
          // Stop any video on stops that aren't shown.
          Array.prototype.forEach.call(p.querySelectorAll('iframe'), function (f) { if (f.getAttribute('src')) f.removeAttribute('src'); });
          Array.prototype.forEach.call(p.querySelectorAll('video'), function (v) { try { v.pause(); } catch (e) { /* not playing */ } });
        } else {
          Array.prototype.forEach.call(p.querySelectorAll('iframe[data-src]'), function (f) { if (!f.getAttribute('src')) f.setAttribute('src', f.getAttribute('data-src')); });
        }
      });
      dots.forEach(function (d, j) { d.setAttribute('aria-current', j === cur ? 'step' : 'false'); });
      markers.forEach(function (m, j) { m.setClass('is-current', j === cur); });
      if (map) {
        var s = stops[cur], zoom = Math.max(Math.round(map.getZoom()), 14);
        map.flyTo([s.lat, s.lng], zoom, { duration: 1.2, animate: !reduce });
      }
      status.textContent = '';
    }
    function setPlaying(on) {
      playBtn.setAttribute('aria-pressed', String(on));
      playBtn.textContent = on ? '\u275A\u275A ' + playBtn.getAttribute('data-pause') : '\u25B6 ' + playBtn.getAttribute('data-play');
    }
    function pause() { clearInterval(timer); timer = null; setPlaying(false); }
    function step() {
      if (cur >= stops.length - 1) {
        if (loop) show(0); else { pause(); return; }
      } else show(cur + 1);
      if (stops[cur].video) { pause(); status.textContent = status.getAttribute('data-video-note'); }
    }
    function play() {
      if (cur >= stops.length - 1 && !loop) show(0);
      setPlaying(true);
      clearInterval(timer);
      timer = setInterval(step, seconds * 1000);
    }
    playBtn.addEventListener('click', function () { if (timer) pause(); else play(); });
    box.querySelector('.special-blocks-story-rewind').addEventListener('click', function () { pause(); show(0); });
    box.querySelector('.special-blocks-story-prev').addEventListener('click', function () { pause(); show(cur - 1); });
    box.querySelector('.special-blocks-story-next').addEventListener('click', function () { pause(); show(cur + 1); });
    dots.forEach(function (d, j) { d.addEventListener('click', function () { pause(); show(j); }); });
    show(0);
    box.storyMap = { show: show, play: play, pause: pause, current: function () { return cur; } };
  }
  function init() { Array.prototype.forEach.call(document.querySelectorAll('section.special-blocks-story-map'), setUp); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  window.addEventListener('load', init);
})();
