/**
 * BICS photo gallery: filter buttons + full-screen viewer (keyboard, swipe, preloading).
 * Without JavaScript every photo still opens as a normal link.
 */
(function () {
  'use strict';

  document.querySelectorAll('[data-gallery]').forEach(function (gallery) {
    var items = Array.prototype.slice.call(gallery.querySelectorAll('.gal-item'));
    var filters = Array.prototype.slice.call(gallery.querySelectorAll('.gal-filter'));
    var current = 'all';

    /* ---------------- Smooth loading: fade each photo in over its blurred preview ---------------- */
    gallery.classList.add('gal-js');
    items.forEach(function (item) {
      var img = item.querySelector('img.gal-img');
      if (!img) { item.classList.add('is-loaded'); return; }
      var done = function () { item.classList.add('is-loaded'); };
      if (img.complete && img.naturalWidth) done();
      else { img.addEventListener('load', done, { once: true }); img.addEventListener('error', done, { once: true }); }
    });

    // Start loading photos well before they scroll into view (browsers' own lazy loading starts late)
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (!e.isIntersecting) return;
          var img = e.target.querySelector('img.gal-img');
          if (img && img.loading === 'lazy') img.loading = 'eager';
          io.unobserve(e.target);
        });
      }, { rootMargin: '1200px 0px' });
      items.forEach(function (item) { if (!item.classList.contains('is-loaded')) io.observe(item); });
    }

    /* ---------------- Filters ---------------- */
    filters.forEach(function (btn) {
      btn.addEventListener('click', function () {
        current = btn.dataset.filter;
        filters.forEach(function (b) {
          var on = b === btn;
          b.classList.toggle('is-active', on);
          b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        items.forEach(function (item) {
          var show = current === 'all' || item.dataset.group === current;
          item.classList.toggle('is-hidden', !show);
          item.classList.remove('is-entering');
          if (show) { void item.offsetWidth; item.classList.add('is-entering'); }
        });
      });
    });

    function visible() { return items.filter(function (i) { return !i.classList.contains('is-hidden'); }); }

    /* ---------------- Lightbox ---------------- */
    var lb = null, img, stage, countEl, capEl, index = 0, list = [], lastFocus = null;

    function build() {
      lb = document.createElement('div');
      lb.className = 'gal-lb';
      lb.setAttribute('role', 'dialog');
      lb.setAttribute('aria-modal', 'true');
      lb.setAttribute('aria-label', 'Photo viewer');
      lb.innerHTML =
        '<div class="gal-lb-top"><span class="gal-lb-count" aria-live="polite"></span>' +
        '<button type="button" class="gal-lb-btn" data-act="close" aria-label="Close (Esc)"><i class="bi bi-x-lg"></i></button></div>' +
        '<div class="gal-lb-stage"><span class="gal-lb-spinner"></span><img class="gal-lb-img" alt="">' +
        '<button type="button" class="gal-lb-btn gal-lb-prev" data-act="prev" aria-label="Previous photo"><i class="bi bi-chevron-left"></i></button>' +
        '<button type="button" class="gal-lb-btn gal-lb-next" data-act="next" aria-label="Next photo"><i class="bi bi-chevron-right"></i></button></div>' +
        '<div class="gal-lb-cap"></div>';
      document.body.appendChild(lb);
      img = lb.querySelector('.gal-lb-img');
      stage = lb.querySelector('.gal-lb-stage');
      countEl = lb.querySelector('.gal-lb-count');
      capEl = lb.querySelector('.gal-lb-cap');

      lb.addEventListener('click', function (e) {
        var act = e.target.closest('[data-act]');
        if (act) { e.stopPropagation(); ({ close: close, prev: prev, next: next })[act.dataset.act](); return; }
        if (e.target === stage || e.target === lb) close(); // click on the dark background
      });
      img.addEventListener('load', function () { img.classList.remove('is-loading'); stage.classList.remove('is-loading'); });

      // Swipe left / right on touch screens
      var startX = null, startY = null;
      stage.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; startY = e.touches[0].clientY; }, { passive: true });
      stage.addEventListener('touchend', function (e) {
        if (startX === null) return;
        var dx = e.changedTouches[0].clientX - startX, dy = e.changedTouches[0].clientY - startY;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) (dx < 0 ? next : prev)();
        startX = null;
      });
    }

    function show(i) {
      index = (i + list.length) % list.length;
      var link = list[index].querySelector('.gal-link');
      stage.classList.add('is-loading');
      img.classList.add('is-loading');
      img.src = link.getAttribute('href');
      img.alt = link.querySelector('img').alt;
      countEl.textContent = (index + 1) + ' / ' + list.length;
      var group = link.dataset.groupLabel, cap = link.dataset.caption;
      capEl.innerHTML = '';
      if (group) { var chip = document.createElement('span'); chip.className = 'gal-chip'; chip.textContent = group; capEl.appendChild(chip); }
      if (cap && cap !== group) { var p = document.createElement('p'); p.textContent = cap; capEl.appendChild(p); }
      var single = list.length < 2;
      lb.querySelector('.gal-lb-prev').hidden = single;
      lb.querySelector('.gal-lb-next').hidden = single;
      // Preload neighbours for instant next / previous
      [index + 1, index - 1].forEach(function (n) {
        var l = list[(n + list.length) % list.length];
        if (l) (new Image()).src = l.querySelector('.gal-link').getAttribute('href');
      });
    }
    function next() { show(index + 1); }
    function prev() { show(index - 1); }

    function open(item) {
      if (!lb) build();
      list = visible();
      lastFocus = document.activeElement;
      lb.classList.add('is-open');
      document.body.classList.add('gal-lock');
      show(list.indexOf(item));
      lb.querySelector('[data-act="close"]').focus();
      document.addEventListener('keydown', onKey);
    }
    function close() {
      lb.classList.remove('is-open');
      document.body.classList.remove('gal-lock');
      document.removeEventListener('keydown', onKey);
      img.removeAttribute('src');
      if (lastFocus) lastFocus.focus();
    }
    function onKey(e) {
      if (e.key === 'Escape') close();
      else if (e.key === 'ArrowRight') next();
      else if (e.key === 'ArrowLeft') prev();
      else if (e.key === 'Tab') { // keep keyboard focus inside the viewer
        var f = Array.prototype.filter.call(lb.querySelectorAll('button'), function (b) { return !b.hidden; });
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    }

    items.forEach(function (item) {
      item.querySelector('.gal-link').addEventListener('click', function (e) {
        if (e.ctrlKey || e.metaKey || e.shiftKey) return; // let people open in a new tab
        e.preventDefault();
        open(item);
      });
    });
  });
})();
