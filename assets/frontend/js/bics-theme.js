/* BICS theme: header, mobile menu, countdowns, counters, programme tabs, reveal on scroll. No dependencies. */
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Header shadow once the page scrolls; back-to-top button
  var header = document.querySelector('.bx-header');
  var toTop = document.querySelector('.bx-totop');
  function onScroll() {
    var y = window.scrollY || document.documentElement.scrollTop;
    if (header) header.classList.toggle('is-scrolled', y > 10);
    if (toTop) toTop.classList.toggle('is-visible', y > 700);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  if (toTop) toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });

  // Mobile drawer
  var drawer = document.querySelector('.bx-drawer');
  if (drawer) {
    var opener = document.querySelector('.bx-burger');
    var lastFocus = null;
    var open = function () {
      lastFocus = document.activeElement;
      drawer.classList.add('is-open');
      drawer.setAttribute('aria-hidden', 'false');
      if (opener) opener.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
      var close = drawer.querySelector('.bx-drawer-close');
      if (close) setTimeout(function () { close.focus(); }, 50);
    };
    var shut = function () {
      drawer.classList.remove('is-open');
      drawer.setAttribute('aria-hidden', 'true');
      if (opener) opener.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
      if (lastFocus) lastFocus.focus();
    };
    if (opener) opener.addEventListener('click', open);
    drawer.querySelectorAll('[data-bx-close]').forEach(function (b) { b.addEventListener('click', shut); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && drawer.classList.contains('is-open')) shut(); });
    drawer.querySelectorAll('button.bx-acc').forEach(function (b) {
      b.addEventListener('click', function () {
        var li = b.parentElement;
        var isOpen = li.classList.toggle('is-open');
        b.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
    });
    // Same-page anchors close the drawer
    drawer.querySelectorAll('a[href*="#"]').forEach(function (a) { a.addEventListener('click', function () { if (a.pathname === location.pathname) shut(); }); });
  }

  // Countdowns: <div data-countdown="ISO date" data-done="text">  with <b data-u="d|h|m|s">
  var clocks = Array.prototype.slice.call(document.querySelectorAll('[data-countdown]'));
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function tick() {
    clocks = clocks.filter(function (c) {
      var left = Math.max(0, Math.floor((new Date(c.dataset.countdown) - new Date()) / 1000));
      if (!left) {
        var done = document.createElement('div');
        done.className = 'bx-countdown-done';
        done.textContent = c.dataset.done || '';
        c.replaceWith(done);
        return false;
      }
      var set = function (u, v) { var el = c.querySelector('[data-u="' + u + '"]'); if (el) el.textContent = v; };
      set('d', Math.floor(left / 86400));
      set('h', pad(Math.floor(left % 86400 / 3600)));
      set('m', pad(Math.floor(left % 3600 / 60)));
      set('s', pad(left % 60));
      return true;
    });
  }
  if (clocks.length) { tick(); setInterval(tick, 1000); }

  // Programme day tabs
  document.querySelectorAll('[data-bx-tabs]').forEach(function (wrap) {
    var tabs = wrap.querySelectorAll('[data-tab]');
    tabs.forEach(function (t) {
      t.addEventListener('click', function () {
        tabs.forEach(function (x) { x.classList.toggle('is-active', x === t); x.setAttribute('aria-selected', x === t ? 'true' : 'false'); });
        wrap.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== t.dataset.tab; });
      });
    });
  });

  // Count-up numbers + reveal, when they come into view
  function countUp(el) {
    var target = parseFloat(el.dataset.count);
    if (isNaN(target) || reduce) return;
    var suffix = el.dataset.suffix || '';
    var start = null, dur = 1400;
    var step = function (ts) {
      if (!start) start = ts;
      var p = Math.min(1, (ts - start) / dur);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * eased).toLocaleString() + suffix;
      if (p < 1) requestAnimationFrame(step);
    };
    el.textContent = '0' + suffix;
    requestAnimationFrame(step);
  }
  var watched = document.querySelectorAll('.bx-reveal, [data-count]');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        if (e.target.dataset.count !== undefined) countUp(e.target);
        e.target.classList.add('is-in');
        io.unobserve(e.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    watched.forEach(function (el) { io.observe(el); });
  } else {
    watched.forEach(function (el) { el.classList.add('is-in'); });
  }
})();
