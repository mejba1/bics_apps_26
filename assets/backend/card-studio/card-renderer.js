/*
 * BICS card renderer — draws a card template (App\Support\CardPresets format, millimetres) as SVG.
 * Shared by the Card Design Studio editor, the template gallery and the participant previews.
 *
 *   CardRenderer.render(design, 'front', data, { icons, base, mode, trim, guides, marks, selectable })  → <svg>
 *   CardRenderer.ready(design)  → Promise (fonts used by the design are loaded)
 *
 * data: { values: {name: …}, photo, logo, initials, qr: {profile, nfc} }  (App\Support\CardData)
 * Needs qrcode-generator (window.qrcode) for QR codes.
 */
(function (global) {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';
  var PT = 25.4 / 72;            // 1 pt in mm
  var CARD_RADIUS = 3.18;        // CR80 corner radius (trimmed preview only)
  var uid = 0;
  var ctx = null;
  var fontsAsked = {};

  function el(name, attrs, parent) {
    var n = document.createElementNS(NS, name);
    if (attrs) for (var k in attrs) if (attrs[k] !== null && attrs[k] !== undefined && attrs[k] !== '') n.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(n);
    return n;
  }
  function r2(v) { return Math.round(v * 1000) / 1000; }

  function fontCss(e, px) { return (e.italic ? 'italic ' : '') + (e.weight || 400) + ' ' + px + 'px "' + (e.font || 'Plus Jakarta Sans') + '", sans-serif'; }

  /** Text width in mm at a font size in mm. */
  function measure(e, text, sizeMm) {
    if (!ctx) ctx = document.createElement('canvas').getContext('2d');
    ctx.font = fontCss(e, 100);
    var w = ctx.measureText(text).width / 100 * sizeMm;
    return w + (e.letter || 0) * sizeMm * Math.max(0, text.length - 1);
  }

  function fill(text, data, mode) {
    return String(text || '').replace(/\{\{\s*([a-z_]+)\s*\}\}/g, function (m, k) {
      if (mode === 'placeholders') return '{' + k + '}';
      var v = data && data.values ? data.values[k] : undefined;
      return v === undefined || v === null ? '' : String(v);
    });
  }

  /** Greedy word wrap; explicit new lines are kept. */
  function wrap(e, text, sizeMm, width) {
    var out = [];
    String(text).split('\n').forEach(function (para) {
      var words = para.split(/\s+/).filter(Boolean), line = '';
      if (!words.length) { out.push(''); return; }
      words.forEach(function (w) {
        var t = line ? line + ' ' + w : w;
        if (!line || measure(e, t, sizeMm) <= width) line = t;
        else { out.push(line); line = w; }
      });
      out.push(line);
    });
    while (out.length > 1 && out[out.length - 1] === '') out.pop();
    return out;
  }

  function ellipsize(e, line, sizeMm, width) {
    if (measure(e, line, sizeMm) <= width) return line;
    while (line.length > 1 && measure(e, line + '…', sizeMm) > width) line = line.slice(0, -1);
    return line.replace(/\s+$/, '') + '…';
  }

  /** Lines and the font size used (shrinks when "fit" is on). */
  function layoutText(e, text) {
    var size = e.size * PT, maxLines = Math.max(1, e.maxLines || 1), lh = e.line || 1.15, lines;
    var minSize = Math.max(2 * PT, size * 0.35);
    for (var i = 0; i < 40; i++) {
      lines = wrap(e, text, size, e.w);
      var widest = Math.max.apply(null, lines.map(function (l) { return measure(e, l, size); }));
      var ok = lines.length <= maxLines && widest <= e.w + 0.01 && (lines.length * size * lh <= e.h + 0.01 || lines.length === 1);
      if (ok || !e.fit || size <= minSize) break;
      size = Math.max(minSize, size * 0.94);
    }
    if (lines.length > maxLines) { lines = lines.slice(0, maxLines); lines[maxLines - 1] = lines[maxLines - 1] + '…'; }
    lines = lines.map(function (l) { return ellipsize(e, l, size, e.w); });
    return { lines: lines, size: size, lh: size * lh };
  }

  function drawText(g, e, data, opts) {
    var text = fill(e.text, data, opts.mode);
    if (e.upper) text = text.toUpperCase();
    text = text.replace(/[ \t]+/g, ' ').trim();
    if (!text) return;
    var L = layoutText(e, text);
    var total = L.lines.length * L.lh;
    var top = e.valign === 'top' ? e.y : e.valign === 'bottom' ? e.y + e.h - total : e.y + (e.h - total) / 2;
    var anchor = e.align === 'center' ? 'middle' : e.align === 'right' ? 'end' : 'start';
    var x = e.align === 'center' ? e.x + e.w / 2 : e.align === 'right' ? e.x + e.w : e.x;
    var t = el('text', {
      'font-family': '"' + e.font + '", sans-serif', 'font-size': r2(L.size), 'font-weight': e.weight || 400,
      'font-style': e.italic ? 'italic' : null, fill: e.color || '#000000', 'text-anchor': anchor,
      'letter-spacing': e.letter ? r2(e.letter * L.size) : null, 'xml:space': 'preserve'
    }, g);
    L.lines.forEach(function (line, i) {
      var ts = el('tspan', { x: r2(x), y: r2(top + i * L.lh + L.lh / 2 + L.size * 0.35) }, t);
      ts.textContent = line;
    });
  }

  function imageUrl(e, data, opts) {
    if (e.source === 'photo') return data && data.photo;
    if (e.source === 'logo') return data && data.logo;
    return e.src ? (/^(https?:|data:|\/)/.test(e.src) ? e.src : (opts.base || '') + e.src) : '';
  }

  function drawImage(g, e, data, opts, defs) {
    var url = imageUrl(e, data, opts), id = 'cr' + (++uid);
    var clip = el('clipPath', { id: id }, defs);
    el('rect', { x: e.x, y: e.y, width: e.w, height: e.h, rx: e.radius ? Math.min(e.radius, e.w / 2, e.h / 2) : null }, clip);
    var inner = el('g', { 'clip-path': 'url(#' + id + ')' }, g);
    if (url) {
      el('image', { href: url, x: e.x, y: e.y, width: e.w, height: e.h, preserveAspectRatio: 'xMidYMid ' + (e.fit === 'contain' ? 'meet' : 'slice') }, inner);
    } else {
      // no photo / logo / file: a soft placeholder
      el('rect', { x: e.x, y: e.y, width: e.w, height: e.h, fill: '#e3ebf7' }, inner);
      if (e.source === 'photo') {
        var ini = data && data.initials ? data.initials : '';
        if (opts.mode === 'placeholders' || !ini) {
          var s = Math.min(e.w, e.h);
          el('circle', { cx: e.x + e.w / 2, cy: e.y + e.h * 0.4, r: s * 0.18, fill: '#a9bcd9' }, inner);
          el('ellipse', { cx: e.x + e.w / 2, cy: e.y + e.h * 0.92, rx: s * 0.34, ry: s * 0.28, fill: '#a9bcd9' }, inner);
        } else {
          var t = el('text', { x: r2(e.x + e.w / 2), y: r2(e.y + e.h / 2 + Math.min(e.w, e.h) * 0.13), 'text-anchor': 'middle', 'font-family': '"Plus Jakarta Sans", sans-serif',
            'font-weight': 800, 'font-size': r2(Math.min(e.w, e.h) * 0.36), fill: '#1861c5' }, inner);
          t.textContent = ini;
        }
      } else if (opts.editor) {
        var t2 = el('text', { x: r2(e.x + e.w / 2), y: r2(e.y + e.h / 2 + 1), 'text-anchor': 'middle', 'font-family': 'sans-serif', 'font-size': r2(Math.min(3, e.h * 0.4)), fill: '#6b7f9e' }, inner);
        t2.textContent = e.source === 'logo' ? 'Organization logo' : 'Image';
      }
    }
    if (e.border > 0) el('rect', { x: e.x, y: e.y, width: e.w, height: e.h, rx: e.radius ? Math.min(e.radius, e.w / 2, e.h / 2) : null, fill: 'none', stroke: e.borderColor || '#ffffff', 'stroke-width': e.border }, g);
  }

  function qrText(e, data, opts) {
    if (e.source === 'custom') return fill(e.text, data, opts.mode === 'placeholders' ? 'data' : opts.mode) || 'https://example.com';
    var q = data && data.qr ? data.qr : {};
    return (e.source === 'nfc' ? q.nfc : q.profile) || 'https://example.com/p/MBR-000000';
  }

  function drawQr(g, e, data, opts) {
    var s = Math.min(e.w, e.h), x0 = e.x + (e.w - s) / 2, y0 = e.y + (e.h - s) / 2;
    if (e.bg) el('rect', { x: x0, y: y0, width: s, height: s, fill: e.bg }, g);
    if (typeof global.qrcode !== 'function') { el('rect', { x: x0, y: y0, width: s, height: s, fill: 'none', stroke: '#999', 'stroke-width': 0.2 }, g); return; }
    var qr = global.qrcode(0, 'M');
    qr.addData(qrText(e, data, opts), 'Byte');
    qr.make();
    var n = qr.getModuleCount(), q = 2, cell = s / (n + 2 * q), d = '';
    for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) {
      if (qr.isDark(r, c)) d += 'M' + r2(x0 + (c + q) * cell) + ' ' + r2(y0 + (r + q) * cell) + 'h' + r2(cell) + 'v' + r2(cell) + 'h' + r2(-cell) + 'z';
    }
    el('path', { d: d, fill: e.color || '#000000', 'shape-rendering': 'crispEdges' }, g);
  }

  function drawIcon(g, e, opts) {
    var icon = opts.icons && opts.icons[e.icon];
    if (!icon) return;
    var s = Math.min(e.w, e.h) / 16;
    var ig = el('g', { transform: 'translate(' + r2(e.x + (e.w - 16 * s) / 2) + ' ' + r2(e.y + (e.h - 16 * s) / 2) + ') scale(' + r2(s) + ')', fill: e.color || '#000000' }, g);
    icon.paths.forEach(function (p) { el('path', { d: p.d, 'fill-rule': p.rule || null }, ig); });
  }

  function drawElement(parent, e, data, opts, defs) {
    if (e.hidden && !opts.showHidden) return null;
    var g = el('g', { 'data-id': opts.selectable ? e.id : null, opacity: e.opacity < 1 ? e.opacity : null,
      transform: e.rotate ? 'rotate(' + e.rotate + ' ' + r2(e.x + e.w / 2) + ' ' + r2(e.y + e.h / 2) + ')' : null }, parent);
    switch (e.type) {
      case 'text': drawText(g, e, data, opts); break;
      case 'image': drawImage(g, e, data, opts, defs); break;
      case 'qr': drawQr(g, e, data, opts); break;
      case 'icon': drawIcon(g, e, opts); break;
      case 'rect':
        el('rect', { x: e.x, y: e.y, width: e.w, height: e.h, rx: e.radius ? Math.min(e.radius, e.w / 2, e.h / 2) : null, fill: e.fill || 'none',
          stroke: e.stroke && e.strokeWidth ? e.stroke : null, 'stroke-width': e.stroke && e.strokeWidth ? e.strokeWidth : null }, g);
        break;
      case 'ellipse':
        el('ellipse', { cx: e.x + e.w / 2, cy: e.y + e.h / 2, rx: e.w / 2, ry: e.h / 2, fill: e.fill || 'none',
          stroke: e.stroke && e.strokeWidth ? e.stroke : null, 'stroke-width': e.stroke && e.strokeWidth ? e.strokeWidth : null }, g);
        break;
      case 'line':
        el('line', { x1: e.x, y1: r2(e.y + e.h / 2), x2: r2(e.x + e.w), y2: r2(e.y + e.h / 2), stroke: e.stroke || '#000000', 'stroke-width': e.strokeWidth || 0.3 }, g);
        break;
    }
    if (opts.selectable) {
      // invisible hit area so thin / empty elements can be clicked
      el('rect', { x: e.x, y: e.y, width: Math.max(e.w, 0.5), height: Math.max(e.h, 1), fill: 'transparent', 'class': 'cr-hit' }, g);
    }
    return g;
  }

  function render(design, side, data, opts) {
    opts = opts || {};
    var w = +design.width, h = +design.height, b = opts.trim ? 0 : (+design.bleed || 0), m = opts.marks ? 6 : 0;
    var vx = -b - m, vy = -b - m, vw = w + 2 * (b + m), vh = h + 2 * (b + m);
    var svg = el('svg', { xmlns: NS, viewBox: [vx, vy, vw, vh].map(r2).join(' '), width: opts.widthPx ? opts.widthPx : r2(vw) + 'mm',
      height: opts.widthPx ? r2(opts.widthPx * vh / vw) : r2(vh) + 'mm', 'class': 'cr-card' });
    var defs = el('defs', null, svg);
    var face = design[side] || { background: {}, elements: [] }, bg = face.background || {};
    var root = el('g', null, svg);
    if (opts.trim) {
      var cid = 'cr' + (++uid), cp = el('clipPath', { id: cid }, defs);
      el('rect', { x: 0, y: 0, width: w, height: h, rx: opts.radius === undefined ? CARD_RADIUS : opts.radius }, cp);
      root.setAttribute('clip-path', 'url(#' + cid + ')');
    }
    el('rect', { x: -b, y: -b, width: w + 2 * b, height: h + 2 * b, fill: bg.color || '#ffffff' }, root);
    if (bg.image) el('image', { href: /^(https?:|data:|\/)/.test(bg.image) ? bg.image : (opts.base || '') + bg.image, x: -b, y: -b, width: w + 2 * b, height: h + 2 * b,
      preserveAspectRatio: 'xMidYMid ' + (bg.fit === 'contain' ? 'meet' : 'slice') }, root);
    (face.elements || []).forEach(function (e) { drawElement(root, e, data, opts, defs); });

    if (opts.guides) {
      var gg = el('g', { 'class': 'cr-guides', 'pointer-events': 'none', fill: 'none' }, svg);
      if (b) el('rect', { x: -b + 0.05, y: -b + 0.05, width: w + 2 * b - 0.1, height: h + 2 * b - 0.1, stroke: '#e5484d', 'stroke-width': 0.15, 'stroke-dasharray': '0.8 0.6' }, gg);
      el('rect', { x: 0, y: 0, width: w, height: h, rx: CARD_RADIUS, stroke: '#111827', 'stroke-width': 0.18 }, gg);
      var s = +design.safe || 0;
      if (s) el('rect', { x: s, y: s, width: w - 2 * s, height: h - 2 * s, stroke: '#1d9bf0', 'stroke-width': 0.15, 'stroke-dasharray': '0.6 0.6' }, gg);
    }
    if (opts.marks) {
      var mk = el('g', { stroke: '#000000', 'stroke-width': 0.12 }, svg), L = 4, o = b + 1;
      [[0, 0], [w, 0], [0, h], [w, h]].forEach(function (p) {
        var sx = p[0] === 0 ? -1 : 1, sy = p[1] === 0 ? -1 : 1;
        el('line', { x1: p[0] + sx * o, y1: p[1], x2: p[0] + sx * (o + L), y2: p[1] }, mk);
        el('line', { x1: p[0], y1: p[1] + sy * o, x2: p[0], y2: p[1] + sy * (o + L) }, mk);
      });
    }
    return svg;
  }

  /** Load the Google fonts a design uses (the page must link the font CSS). */
  function ready(design) {
    if (!document.fonts || !document.fonts.load) return Promise.resolve();
    var want = {};
    ['front', 'back'].forEach(function (s) {
      ((design[s] || {}).elements || []).forEach(function (e) { if (e.type === 'text') want[(e.italic ? 'italic ' : '') + (e.weight || 400) + ' 12px "' + e.font + '"'] = 1; });
    });
    want['800 12px "Plus Jakarta Sans"'] = 1;
    return Promise.all(Object.keys(want).map(function (f) {
      if (!fontsAsked[f]) fontsAsked[f] = document.fonts.load(f).catch(function () {});
      return fontsAsked[f];
    })).then(function () { return document.fonts.ready; });
  }

  global.CardRenderer = { render: render, ready: ready, fill: fill, layoutText: layoutText, PT: PT };
})(window);
