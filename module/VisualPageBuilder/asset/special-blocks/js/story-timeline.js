/* SPDX-License-Identifier: GPL-2.0-or-later */
/*
 * Special Blocks: Story timeline (in the manner of Knight Lab's TimelineJS).
 *
 * Shows one slide at a time. The Previous and Next arrows name the slides
 * they lead to. The time bar under the slides has a marker for every event at
 * its place in time; clicking one shows its slide, and the bar slides along to
 * keep the current marker in view. Zoom in and out widens or narrows the bar
 * (places are percentages, so nothing moves out of place); after each zoom the
 * markers are re-stacked into as few rows as fit, and the year labels thinned
 * or filled in. Arrow keys (inside the timeline), Home, End, and swiping on
 * the slides also move. Each slide can have its own address (#…) to share.
 *
 * The server lays out the time bar for the first paint (StoryTimeline::layout),
 * with the same marker width, row limit, and tick steps as here.
 * Videos and web embeds load only on their own slide, and stop when it's left.
 */
(function () {
  'use strict';
  var MARKER_WIDTH = 140; // px, StoryTimeline::MARKER_WIDTH
  var MAX_ROWS = 4; // StoryTimeline::MAX_ROWS
  var STEPS = [1 / 12, 0.25, 1, 2, 5, 10, 25, 50, 100, 500, 1000]; // years, smallest first
  var LABEL_GAP = 80; // px between year labels
  var MAX_ZOOM = 64;
  var MAX_PX_PER_YEAR = 6000;

  function each(list, fn) { Array.prototype.forEach.call(list, fn); }

  function setUp(box) {
    if (box.getAttribute('data-ready') || box.classList.contains('is-preview')) return;
    var slides = Array.prototype.slice.call(box.querySelectorAll('.stl-slide'));
    if (!slides.length) return;
    box.setAttribute('data-ready', '1');

    var nav = box.querySelector('.stl-nav');
    var viewport = box.querySelector('.stl-viewport');
    var track = box.querySelector('.stl-track');
    var markers = Array.prototype.slice.call(box.querySelectorAll('.stl-marker'));
    var spanbars = Array.prototype.slice.call(box.querySelectorAll('.stl-spanbar'));
    var bands = Array.prototype.slice.call(box.querySelectorAll('.stl-band'));
    var ticks = Array.prototype.slice.call(box.querySelectorAll('.stl-tick'));
    var axis = box.querySelector('.stl-axis');
    var prevBtn = box.querySelector('.stl-prev');
    var nextBtn = box.querySelector('.stl-next');
    var zoomIn = box.querySelector('.stl-zoom-in');
    var zoomOut = box.querySelector('.stl-zoom-out');
    var homeBtn = box.querySelector('.stl-home');
    var beginBtn = box.querySelector('.stl-begin');
    var min = parseFloat(box.getAttribute('data-min'));
    var max = parseFloat(box.getAttribute('data-max'));
    var span = max - min || 1;
    var zoom = parseFloat(box.getAttribute('data-zoom')) || 1;
    var links = box.getAttribute('data-links') === '1';
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var cur = -1;
    var markerFor = {};
    markers.forEach(function (m) { markerFor[m.getAttribute('data-slide')] = m; });

    box.classList.add('is-enhanced');
    nav.hidden = false;
    if (beginBtn) beginBtn.hidden = false;

    /* ---------- slides ---------- */

    function peekHtml(i) {
      var s = slides[i];
      if (!s) return '';
      var date = s.querySelector('.stl-date');
      var title = s.querySelector('.stl-headline');
      var out = document.createDocumentFragment();
      if (date) {
        var d = document.createElement('span');
        d.className = 'stl-peek-date';
        d.textContent = date.firstChild ? date.firstChild.textContent.trim() : date.textContent;
        out.appendChild(d);
      }
      var t = document.createElement('span');
      t.className = 'stl-peek-title';
      t.textContent = title ? title.textContent : '';
      out.appendChild(t);
      return out;
    }
    function setArrow(btn, i, word) {
      var peek = btn.querySelector('.stl-peek');
      peek.textContent = '';
      if (i < 0 || i >= slides.length) { btn.hidden = true; return; }
      btn.hidden = false;
      peek.appendChild(peekHtml(i));
      var title = slides[i].querySelector('.stl-headline');
      btn.setAttribute('aria-label', word + (title ? ': ' + title.textContent : ''));
    }

    function stopMedia(slide) {
      each(slide.querySelectorAll('iframe'), function (f) { if (f.getAttribute('src')) f.removeAttribute('src'); });
      each(slide.querySelectorAll('video, audio'), function (v) { try { v.pause(); } catch (e) { /* not playing */ } });
    }
    function startMedia(slide) {
      each(slide.querySelectorAll('iframe[data-src]'), function (f) { if (!f.getAttribute('src')) f.setAttribute('src', f.getAttribute('data-src')); });
    }

    function show(i, how) {
      i = Math.max(0, Math.min(slides.length - 1, i));
      how = how || {};
      if (i === cur) return;
      var from = cur;
      cur = i;
      slides.forEach(function (s, j) {
        if (j === i) {
          s.hidden = false;
          startMedia(s);
          if (from >= 0 && !reduce) {
            s.classList.remove('stl-in-next', 'stl-in-prev');
            void s.offsetWidth; // Restart the animation.
            s.classList.add(i > from ? 'stl-in-next' : 'stl-in-prev');
          }
        } else if (!s.hidden) {
          s.hidden = true;
          stopMedia(s);
        }
      });
      setArrow(prevBtn, i - 1, prevBtn.getAttribute('data-word'));
      setArrow(nextBtn, i + 1, nextBtn.getAttribute('data-word'));
      markers.forEach(function (m) { m.setAttribute('aria-current', m.getAttribute('data-slide') === String(i) ? 'step' : 'false'); });
      spanbars.forEach(function (b) { b.classList.toggle('is-current', b.getAttribute('data-for') === String(i)); });
      if (homeBtn) homeBtn.disabled = i === 0;
      centerOn(i, !how.instant);
      if (links && how.remember && history.replaceState) {
        try { history.replaceState(null, '', '#' + slides[i].id); } catch (e) { /* file: or sandboxed */ }
      }
      if (how.focus) {
        var h = slides[i].querySelector('.stl-headline');
        if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
      }
    }
    function go(delta) { show(cur + delta, { remember: true }); }

    /* ---------- time bar ---------- */

    function trackWidth() { return track.getBoundingClientRect().width || track.offsetWidth || 0; }

    function centerOn(i, smooth) {
      var m = markerFor[String(i)];
      if (!m || !viewport.clientWidth) return;
      var w = trackWidth();
      var x = (parseFloat(m.getAttribute('data-pos')) - min) / span * w;
      var left = Math.max(0, x - viewport.clientWidth / 3);
      if (viewport.scrollTo) viewport.scrollTo({ left: left, behavior: smooth && !reduce ? 'smooth' : 'auto' });
      else viewport.scrollLeft = left;
    }

    /* Re-stack markers into as few rows as fit, group by group. */
    function stack() {
      var w = trackWidth();
      if (!w) return;
      var offset = 0;
      bands.forEach(function (band, k) {
        var ends = [];
        markers.forEach(function (m) {
          if (m.getAttribute('data-band') !== String(k)) return;
          var pos = parseFloat(m.getAttribute('data-pos'));
          var end = m.hasAttribute('data-end') ? parseFloat(m.getAttribute('data-end')) : pos;
          var left = (pos - min) / span * w;
          // Near the right end, a marker hangs to the left of its line instead.
          var flip = !m.hasAttribute('data-end') && left + MARKER_WIDTH > w;
          var right = flip ? left : Math.max(left + MARKER_WIDTH, (end - min) / span * w);
          if (flip) left -= MARKER_WIDTH;
          m.classList.toggle('is-flipped', flip);
          var row = 0;
          while (row < MAX_ROWS - 1 && ends[row] !== undefined && ends[row] > left) row++;
          ends[row] = Math.max(ends[row] === undefined ? -Infinity : ends[row], right + 6);
          m.style.setProperty('--stl-row', String(offset + row));
          var bar = box.querySelector('.stl-spanbar[data-for="' + m.getAttribute('data-slide') + '"]');
          if (bar) bar.style.setProperty('--stl-row', String(offset + row));
        });
        var rows = Math.max(1, ends.length);
        band.style.setProperty('--stl-top', String(offset));
        band.style.setProperty('--stl-band-rows', String(rows));
        offset += rows;
      });
      track.style.setProperty('--stl-rows', String(offset));
    }

    /* Label the ticks that fit: the smallest step leaving LABEL_GAP between labels,
       and only ticks on that step (1930 on a 10-year step, not 1925). */
    function label() {
      var w = trackWidth();
      if (!w) return;
      var perYear = w / span;
      var finest = Infinity;
      ticks.forEach(function (t) { finest = Math.min(finest, parseFloat(t.getAttribute('data-step'))); });
      var shown = STEPS[STEPS.length - 1];
      for (var k = 0; k < STEPS.length; k++) {
        if (STEPS[k] >= finest - 1e-6 && STEPS[k] * perYear >= LABEL_GAP) { shown = STEPS[k]; break; }
      }
      ticks.forEach(function (t) {
        var v = parseFloat(t.getAttribute('data-pos'));
        t.classList.toggle('is-labeled', Math.abs(v / shown - Math.round(v / shown)) < 1e-4);
      });
      axis.classList.toggle('is-dense', finest * perYear < 6);
    }

    function maxZoom() {
      var vw = viewport.clientWidth || 900;
      var z = 1;
      while (z < MAX_ZOOM && vw * z * 2 / span <= MAX_PX_PER_YEAR) z *= 2;
      return z;
    }
    function setZoom(z) {
      zoom = Math.max(1, Math.min(maxZoom(), z));
      track.style.width = (zoom * 100) + '%';
      zoomOut.disabled = zoom <= 1;
      zoomIn.disabled = zoom >= maxZoom();
      box.setAttribute('data-zoom', String(zoom));
      stack();
      label();
      centerOn(cur, false);
    }

    /* ---------- wiring ---------- */

    prevBtn.setAttribute('data-word', prevBtn.getAttribute('aria-label'));
    nextBtn.setAttribute('data-word', nextBtn.getAttribute('aria-label'));
    prevBtn.addEventListener('click', function () { go(-1); });
    nextBtn.addEventListener('click', function () { go(1); });
    if (beginBtn) beginBtn.addEventListener('click', function () { show(1, { remember: true, focus: true }); });
    if (homeBtn) homeBtn.addEventListener('click', function () { show(0, { remember: true }); });
    zoomIn.addEventListener('click', function () { setZoom(zoom * 2); });
    zoomOut.addEventListener('click', function () { setZoom(zoom / 2); });
    markers.forEach(function (m) {
      m.addEventListener('click', function () { show(parseInt(m.getAttribute('data-slide'), 10), { remember: true }); });
    });

    box.addEventListener('keydown', function (e) {
      if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
      var tag = (e.target.tagName || '').toLowerCase();
      if (/^(input|textarea|select|video|audio|iframe)$/.test(tag) || e.target.isContentEditable) return;
      var to = { ArrowLeft: cur - 1, ArrowRight: cur + 1, Home: 0, End: slides.length - 1 }[e.key];
      if (to === undefined) return;
      e.preventDefault();
      var onMarker = e.target.classList && e.target.classList.contains('stl-marker');
      show(to, { remember: true });
      if (onMarker && markerFor[String(cur)]) markerFor[String(cur)].focus({ preventScroll: true });
    });

    // Swipe the slides on touch screens.
    var stage = box.querySelector('.stl-stage');
    var startX = null, startY = null;
    stage.addEventListener('touchstart', function (e) {
      if (e.touches.length !== 1) { startX = null; return; }
      startX = e.touches[0].clientX; startY = e.touches[0].clientY;
    }, { passive: true });
    stage.addEventListener('touchend', function (e) {
      if (startX === null) return;
      var dx = e.changedTouches[0].clientX - startX, dy = e.changedTouches[0].clientY - startY;
      startX = null;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) go(dx < 0 ? 1 : -1);
    }, { passive: true });

    each(box.querySelectorAll('.stl-slide'), function (s) {
      s.addEventListener('animationend', function () { s.classList.remove('stl-in-next', 'stl-in-prev'); });
    });

    var resizeTimer = null;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () { setZoom(zoom); }, 150);
    });

    function fromHash() {
      var id = decodeURIComponent((location.hash || '').slice(1));
      if (!id) return -1;
      for (var j = 0; j < slides.length; j++) if (slides[j].id === id) return j;
      return -1;
    }
    window.addEventListener('hashchange', function () {
      var j = fromHash();
      if (j >= 0) show(j);
    });

    var start = fromHash();
    if (start < 0) start = parseInt(box.getAttribute('data-start'), 10) || 0;
    // Every slide starts hidden; show() reveals the first.
    slides.forEach(function (s) { s.hidden = true; });
    setZoom(zoom);
    show(start, { instant: true });
    if (fromHash() >= 0 && box.scrollIntoView) box.scrollIntoView({ block: 'start' });

    box.storyTimeline = {
      show: function (i) { show(i, { remember: true }); },
      next: function () { go(1); },
      prev: function () { go(-1); },
      zoomIn: function () { setZoom(zoom * 2); },
      zoomOut: function () { setZoom(zoom / 2); },
      current: function () { return cur; },
      zoom: function () { return zoom; }
    };
  }

  function init() { each(document.querySelectorAll('section.special-blocks-stl'), setUp); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  window.addEventListener('load', init);
})();
