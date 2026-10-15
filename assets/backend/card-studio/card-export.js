/**
 * Card exports in the browser (Card Design Studio › Previews and Card Printing).
 *   CardExport.file(design, side, data, 'png'|'jpg'|'eps', { dpi, bleed, icons, base, name })  → Promise<Blob>
 *   CardExport.bulk(items, { formats, sides, dpi, bleed, icons, base, folder }, onProgress)   → Promise<Blob> (ZIP)
 *   CardExport.save(blob, fileName)
 *
 * The card is drawn by CardRenderer (same as the previews) at its exact size in millimetres × DPI, with the
 * participant's original photo and the design's fonts embedded, so the picture matches the print PDF:
 *   PNG / JPG — 300 or 600 DPI, the DPI is written into the file (print programs size it correctly)
 *   EPS       — CMYK image (black text = pure K), exact millimetre bounding box, Flate-compressed (PostScript 3)
 * PDF (vector) is made on the server (App\Http\Controllers\CardExportController).
 */
(function (global) {
  'use strict';
  var FONT_CSS = 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Inter:wght@300..800&family=Montserrat:ital,wght@0,300..800;1,300..800&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400;1,700&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Oswald:wght@300..700&family=Playfair+Display:ital,wght@0,400..800;1,400..800&family=Lora:ital,wght@0,400..700;1,400..700&family=Merriweather:ital,wght@0,300;0,400;0,700;1,400&display=swap';
  var MM_PER_INCH = 25.4;
  var dataUrls = {}, fontBlocks = null, fontCss = {};

  /* ---------- helpers ---------- */
  function blobToDataUrl(b) { return new Promise(function (res, rej) { var r = new FileReader(); r.onload = function () { res(r.result); }; r.onerror = rej; r.readAsDataURL(b); }); }
  function toDataUrl(url) {
    if (!url || /^data:/.test(url)) return Promise.resolve(url);
    if (!dataUrls[url]) dataUrls[url] = fetch(url, { credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error('image ' + r.status); return r.blob(); }).then(blobToDataUrl).catch(function () { return null; });
    return dataUrls[url];
  }
  function usedFamilies(design) {
    var f = { 'Plus Jakarta Sans': 1 };   // initials placeholder font
    ['front', 'back'].forEach(function (s) { ((design[s] || {}).elements || []).forEach(function (e) { if (e.type === 'text' && e.font) f[e.font] = 1; }); });
    return Object.keys(f);
  }
  /** @font-face blocks (latin + latin-ext) of the studio fonts, with the font files inlined. */
  function fontStyle(design) {
    var fams = usedFamilies(design), key = fams.sort().join('|');
    if (fontCss[key]) return fontCss[key];
    if (!fontBlocks) fontBlocks = fetch(FONT_CSS).then(function (r) { return r.text(); }).then(function (css) {
      var out = [], re = /\/\*\s*([a-z-]+)\s*\*\/\s*(@font-face\s*{[^}]*})/g, m;
      while ((m = re.exec(css))) if (m[1] === 'latin' || m[1] === 'latin-ext') out.push(m[2]);
      return out;
    }).catch(function () { return []; });
    fontCss[key] = fontBlocks.then(function (blocks) {
      var mine = blocks.filter(function (b) { return fams.some(function (f) { return b.indexOf("font-family: '" + f + "'") > -1; }); });
      return Promise.all(mine.map(function (b) {
        var u = (b.match(/url\(([^)]+)\)/) || [])[1];
        return u ? toDataUrl(u.replace(/["']/g, '')).then(function (d) { return d ? b.replace(u, d) : ''; }) : '';
      })).then(function (list) { return list.join('\n'); });
    });
    return fontCss[key];
  }
  function printData(data) {
    var d = Object.assign({}, data);
    if (data && data.print) { if (data.print.photo) d.photo = data.print.photo; if (data.print.logo) d.logo = data.print.logo; }
    return d;
  }

  /* ---------- drawing ---------- */
  /** The card as a canvas: exact millimetres × DPI (plus the bleed when asked). */
  function canvas(design, side, data, o) {
    o = o || {};
    var dpi = o.dpi || 300, bleed = o.bleed ? (+design.bleed || 0) : 0;
    var wMm = +design.width + 2 * bleed, hMm = +design.height + 2 * bleed;
    var px = Math.round(wMm / MM_PER_INCH * dpi), py = Math.round(hMm / MM_PER_INCH * dpi);
    return Promise.all([CardRenderer.ready(design), fontStyle(design)]).then(function (r) {
      // relative picture paths in a design (e.g. assets/…/logo.png) are resolved against the site address
      var base = o.base || global.CARD_BASE || (document.querySelector('base') || {}).href || '';
      var svg = CardRenderer.render(design, side, printData(data), { icons: o.icons || global.CARD_ICONS, base: base, trim: !o.bleed, radius: 0, widthPx: px });
      svg.setAttribute('width', px); svg.setAttribute('height', py);
      var st = document.createElementNS('http://www.w3.org/2000/svg', 'style'); st.textContent = r[1];
      svg.insertBefore(st, svg.firstChild);
      // pictures inside an SVG drawn on a canvas must be data: URLs
      var imgs = Array.prototype.slice.call(svg.querySelectorAll('image'));
      return Promise.all(imgs.map(function (im) {
        var h = im.getAttribute('href') || im.getAttributeNS('http://www.w3.org/1999/xlink', 'href');
        return toDataUrl(h).then(function (d) { if (d) im.setAttribute('href', d); else im.remove(); });
      })).then(function () {
        var src = new XMLSerializer().serializeToString(svg);
        var url = URL.createObjectURL(new Blob([src], { type: 'image/svg+xml;charset=utf-8' }));
        return new Promise(function (res, rej) {
          var img = new Image();
          img.onload = function () {
            var c = document.createElement('canvas'); c.width = px; c.height = py;
            var g = c.getContext('2d');
            if (o.white) { g.fillStyle = '#ffffff'; g.fillRect(0, 0, px, py); }
            g.drawImage(img, 0, 0, px, py);
            URL.revokeObjectURL(url);
            res({ canvas: c, wMm: wMm, hMm: hMm, dpi: dpi });
          };
          img.onerror = function () { URL.revokeObjectURL(url); rej(new Error('The card could not be drawn.')); };
          img.src = url;
        });
      });
    });
  }

  /* ---------- PNG / JPG with DPI ---------- */
  var CRC = (function () { var t = [], c; for (var n = 0; n < 256; n++) { c = n; for (var k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } return t; })();
  function crc32(bytes) { var c = 0xffffffff; for (var i = 0; i < bytes.length; i++) c = CRC[(c ^ bytes[i]) & 0xff] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; }
  function u32(n) { return [(n >>> 24) & 255, (n >>> 16) & 255, (n >>> 8) & 255, n & 255]; }
  function pngWithDpi(buf, dpi) {
    var b = new Uint8Array(buf), ppm = Math.round(dpi / 0.0254);
    var type = [112, 72, 89, 115], data = u32(ppm).concat(u32(ppm), [1]);       // "pHYs", pixels per metre
    var chunk = new Uint8Array(u32(9).concat(type, data, u32(crc32(new Uint8Array(type.concat(data))))));
    var at = 8 + 8 + 13 + 4;                                                     // after the IHDR chunk
    var out = new Uint8Array(b.length + chunk.length); out.set(b.subarray(0, at)); out.set(chunk, at); out.set(b.subarray(at), at + chunk.length);
    return out;
  }
  function jpgWithDpi(buf, dpi) {
    var b = new Uint8Array(buf);
    if (b[6] === 0x4a && b[7] === 0x46 && b[8] === 0x49 && b[9] === 0x46) { b[13] = 1; b[14] = dpi >> 8; b[15] = dpi & 255; b[16] = dpi >> 8; b[17] = dpi & 255; }   // JFIF density in DPI
    return b;
  }
  function toBlob(c, type, q) { return new Promise(function (res) { c.toBlob(res, type, q); }); }

  /* ---------- EPS (CMYK) ---------- */
  function deflate(bytes) {
    if (!global.CompressionStream) return Promise.resolve(null);
    var s = new Blob([bytes]).stream().pipeThrough(new CompressionStream('deflate'));
    return new Response(s).arrayBuffer().then(function (a) { return new Uint8Array(a); });
  }
  function ascii85(bytes) {
    var out = [], line = 0;
    function push(s) { out.push(s); line += s.length; if (line >= 75) { out.push('\n'); line = 0; } }
    for (var i = 0; i < bytes.length; i += 4) {
      var n = bytes.length - i, b0 = bytes[i], b1 = n > 1 ? bytes[i + 1] : 0, b2 = n > 2 ? bytes[i + 2] : 0, b3 = n > 3 ? bytes[i + 3] : 0;
      var v = ((b0 << 24) >>> 0) + (b1 << 16) + (b2 << 8) + b3;
      if (v === 0 && n >= 4) { push('z'); continue; }
      var c = []; for (var k = 4; k >= 0; k--) { c[k] = String.fromCharCode(33 + v % 85); v = Math.floor(v / 85); }
      push(c.join('').slice(0, n >= 4 ? 5 : n + 1));
    }
    return out.join('') + '~>';
  }
  function eps(r, name) {
    var c = r.canvas, w = c.width, h = c.height, px = c.getContext('2d').getImageData(0, 0, w, h).data;
    var cmyk = new Uint8Array(w * h * 4);
    for (var i = 0, j = 0; i < px.length; i += 4, j += 4) {
      var a = px[i + 3] / 255, R = (px[i] * a + 255 * (1 - a)) / 255, G = (px[i + 1] * a + 255 * (1 - a)) / 255, Bl = (px[i + 2] * a + 255 * (1 - a)) / 255;
      var K = 1 - Math.max(R, G, Bl);
      if (K >= 0.999) { cmyk[j + 3] = 255; continue; }
      cmyk[j] = Math.round((1 - R - K) / (1 - K) * 255); cmyk[j + 1] = Math.round((1 - G - K) / (1 - K) * 255);
      cmyk[j + 2] = Math.round((1 - Bl - K) / (1 - K) * 255); cmyk[j + 3] = Math.round(K * 255);
    }
    var wPt = r.wMm / MM_PER_INCH * 72, hPt = r.hMm / MM_PER_INCH * 72;
    return deflate(cmyk).then(function (z) {
      var filters = z ? '/ASCII85Decode filter /FlateDecode filter' : '/ASCII85Decode filter';
      var head = '%!PS-Adobe-3.0 EPSF-3.0\n%%Creator: BICS Card Design Studio\n%%Title: ' + String(name || 'card').replace(/[()\\\n]/g, '') + '\n' +
        '%%CreationDate: ' + new Date().toISOString() + '\n%%BoundingBox: 0 0 ' + Math.ceil(wPt) + ' ' + Math.ceil(hPt) + '\n%%HiResBoundingBox: 0 0 ' + wPt.toFixed(3) + ' ' + hPt.toFixed(3) + '\n' +
        '%%LanguageLevel: ' + (z ? 3 : 2) + '\n%%DocumentProcessColors: Cyan Magenta Yellow Black\n%%Pages: 1\n%%EndComments\n%%BeginProlog\n%%EndProlog\n%%Page: 1 1\n' +
        'gsave\n' + wPt.toFixed(3) + ' ' + hPt.toFixed(3) + ' scale\n/DeviceCMYK setcolorspace\n' +
        '<< /ImageType 1 /Width ' + w + ' /Height ' + h + ' /BitsPerComponent 8 /Decode [0 1 0 1 0 1 0 1]\n   /ImageMatrix [' + w + ' 0 0 -' + h + ' 0 ' + h + '] /DataSource currentfile ' + filters + ' >> image\n';
      return new Blob([head, ascii85(z || cmyk), '\ngrestore\nshowpage\n%%Trailer\n%%EOF\n'], { type: 'application/postscript' });
    });
  }

  /* ---------- public ---------- */
  function file(design, side, data, fmt, o) {
    o = Object.assign({ dpi: 300 }, o || {});
    return canvas(design, side, data, Object.assign({}, o, { white: fmt !== 'png' })).then(function (r) {
      if (fmt === 'eps') return eps(r, o.name);
      if (fmt === 'jpg') return toBlob(r.canvas, 'image/jpeg', 0.95).then(function (b) { return b.arrayBuffer(); }).then(function (a) { return new Blob([jpgWithDpi(a, r.dpi)], { type: 'image/jpeg' }); });
      return toBlob(r.canvas, 'image/png').then(function (b) { return b.arrayBuffer(); }).then(function (a) { return new Blob([pngWithDpi(a, r.dpi)], { type: 'image/png' }); });
    });
  }
  var zipLib = null;
  function jszip() {
    if (global.JSZip) return Promise.resolve(global.JSZip);
    if (!zipLib) zipLib = new Promise(function (res, rej) {
      var s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
      s.onload = function () { res(global.JSZip); }; s.onerror = function () { rej(new Error('The ZIP tool could not be loaded. Check the internet connection.')); };
      document.head.appendChild(s);
    });
    return zipLib;
  }
  function safe(s) { return String(s || '').normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9._-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'card'; }
  function fileName(item, side, fmt, double) { return safe(item.code) + '-' + safe(item.name) + (double ? '-' + side : '') + '.' + fmt; }
  /** items: [{ name, code, design, data }] */
  function bulk(items, o, onProgress) {
    o = Object.assign({ formats: ['png'], sides: 'both', dpi: 300 }, o || {});
    return jszip().then(function (JSZip) {
      var zip = new JSZip(), jobs = [];
      items.forEach(function (it) {
        var sides = it.design.double_sided && o.sides !== 'front' ? (o.sides === 'back' ? ['back'] : ['front', 'back']) : ['front'];
        sides.forEach(function (s) { o.formats.forEach(function (f) { jobs.push([it, s, f, sides.length > 1 || it.design.double_sided]); }); });
      });
      var done = 0, i = 0;
      function next() {
        if (i >= jobs.length) return Promise.resolve();
        var j = jobs[i++];
        return file(j[0].design, j[1], j[0].data, j[2], Object.assign({}, o, { name: j[0].name })).then(function (blob) {
          zip.file((o.formats.length > 1 ? j[2].toUpperCase() + '/' : '') + fileName(j[0], j[1], j[2], j[3]), blob, { compression: j[2] === 'eps' ? 'DEFLATE' : 'STORE' });
        }, function () { /* one bad card must not stop the batch */ }).then(function () { done++; if (onProgress) onProgress(done, jobs.length); return next(); });
      }
      return next().then(function () { return zip.generateAsync({ type: 'blob' }); });
    });
  }
  function save(blob, name) {
    var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name;
    document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 2000);
  }
  global.CardExport = { file: file, bulk: bulk, save: save, fileName: fileName, canvas: canvas };
})(window);
