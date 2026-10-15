/*
 * BICS Card Design Studio — the editor (resources/views/backend/pages/card-studio/studio.blade.php).
 * The design is edited in millimetres and drawn with CardRenderer (card-renderer.js); config comes from window.CardStudio.
 */
(function () {
  'use strict';
  var S = window.CardStudio;
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var MM = 96 / 25.4;                       // CSS px per mm at 100 %
  var TYPE_ICON = { text: 'bi-fonts', image: 'bi-image', qr: 'bi-qr-code', rect: 'bi-square', ellipse: 'bi-circle', line: 'bi-slash-lg', icon: 'bi-star' };
  var TYPE_NAME = { text: 'Text', image: 'Image', qr: 'QR code', rect: 'Box', ellipse: 'Circle', line: 'Line', icon: 'Icon' };

  var design = clone(S.design);
  var side = 'front', sel = [], zoom = 4, fitted = true, showGuides = true;
  var dataMode = 'sample', data = S.sample, participantName = '';
  var undo = [], redo = [], dirty = false, baseVersion = S.template.version, clipboard = null;
  var drag = null, snapLines = [], drawQueued = false, pendingSnap = null, uploadTarget = null;

  var $ = function (id) { return document.getElementById(id); };
  var canvas = $('cs-canvas'), stage = $('cs-stage'), svgBox = $('cs-svg'), overlay = $('cs-overlay'), props = $('cs-props');

  /* ---------------- helpers ---------------- */
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function r1(v) { return Math.round(v * 10) / 10; }
  function r2(v) { return Math.round(v * 100) / 100; }
  function newId() { return 'e' + Math.random().toString(36).slice(2, 8); }
  function face() { return design[side]; }
  function els() { return face().elements; }
  function byId(id) { return els().find(function (e) { return e.id === id; }); }
  function selected() { return sel.map(byId).filter(Boolean); }
  function movable() { return selected().filter(function (e) { return !e.locked; }); }
  function toast(msg, icon) { Swal.fire({ toast: true, position: 'top-end', icon: icon || 'success', title: msg, showConfirmButton: false, timer: 2200 }); }
  function bleed() { return +design.bleed || 0; }
  function px(mm) { return (mm + bleed()) * zoom; }
  function toMm(ev) { var r = stage.getBoundingClientRect(); return { x: (ev.clientX - r.left) / zoom - bleed(), y: (ev.clientY - r.top) / zoom - bleed() }; }
  function isTyping(ev) { var t = ev.target; return t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable); }
  function json(method, url, body) {
    return fetch(url, { method: method, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, status: r.status, d: d }; }); });
  }
  function errorOf(res) { var e = res.d.errors || {}; var k = Object.keys(e)[0]; return k ? e[k][0] : (res.d.message || 'Please try again.'); }

  /* ---------------- history ---------------- */
  function snapshot() { return JSON.stringify({ design: design, side: side, sel: sel }); }
  function checkpoint(snap) {
    undo.push(snap || snapshot());
    if (undo.length > 120) undo.shift();
    redo = [];
    markDirty();
  }
  function restore(s) { var o = JSON.parse(s); design = o.design; side = o.side; sel = o.sel; }
  function doUndo() { if (!undo.length) return; redo.push(snapshot()); restore(undo.pop()); markDirty(); renderAll(); }
  function doRedo() { if (!redo.length) return; undo.push(snapshot()); restore(redo.pop()); markDirty(); renderAll(); }
  function markDirty() {
    dirty = true;
    $('cs-save').classList.add('is-dirty');
    $('cs-status').textContent = 'Unsaved changes';
    clearTimeout(markDirty.t);
    markDirty.t = setTimeout(saveDraft, 1000);
  }
  function draftKey() { return 'cs-draft-' + S.template.id; }
  function saveDraft() { try { localStorage.setItem(draftKey(), JSON.stringify({ base: baseVersion, design: design, at: Date.now() })); } catch (e) {} }
  function clearDraft() { try { localStorage.removeItem(draftKey()); } catch (e) {} }

  /* ---------------- drawing ---------------- */
  function renderData() { return dataMode === 'placeholders' ? S.sample : data; }

  function drawCanvas() {
    var b = bleed(), W = design.width + 2 * b, H = design.height + 2 * b;
    var svg = CardRenderer.render(design, side, renderData(), {
      icons: S.icons, base: S.base, guides: showGuides, selectable: true, editor: true,
      mode: dataMode === 'placeholders' ? 'placeholders' : 'data', widthPx: W * zoom
    });
    svg.querySelectorAll('[data-id]').forEach(function (g) { var e = byId(g.getAttribute('data-id')); if (e && e.locked) g.style.pointerEvents = 'none'; });
    svgBox.replaceChildren(svg);
    stage.style.width = W * zoom + 'px';
    stage.style.height = H * zoom + 'px';
  }

  function drawOverlay() {
    overlay.innerHTML = '';
    var list = selected();
    list.forEach(function (e) {
      var box = document.createElement('div');
      box.className = 'cs-selbox' + (list.length > 1 ? ' multi' : '') + (e.locked ? ' locked' : '');
      box.style.left = px(e.x) + 'px'; box.style.top = px(e.y) + 'px';
      box.style.width = Math.max(e.w * zoom, 2) + 'px'; box.style.height = Math.max(e.h * zoom, 2) + 'px';
      if (e.rotate) box.style.transform = 'rotate(' + e.rotate + 'deg)';
      if (list.length === 1 && !e.locked) {
        ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'].forEach(function (h) {
          if (e.type === 'line' && /n|s/.test(h) && h.length === 1) return;
          var d = document.createElement('div'); d.className = 'cs-h'; d.dataset.h = h;
          d.style.left = (/w/.test(h) ? 0 : /e/.test(h) ? 100 : 50) + '%';
          d.style.top = (/n/.test(h) ? 0 : /s/.test(h) ? 100 : 50) + '%';
          box.appendChild(d);
        });
        var line = document.createElement('div'); line.className = 'cs-rotline'; box.appendChild(line);
        var rot = document.createElement('div'); rot.className = 'cs-h'; rot.dataset.h = 'rot'; rot.style.left = '50%'; rot.style.top = '-22px'; box.appendChild(rot);
      }
      overlay.appendChild(box);
    });
    if (list.length > 1) {
      var bb = bbox(list), g = document.createElement('div');
      g.className = 'cs-groupbox';
      g.style.left = px(bb.x) - 3 + 'px'; g.style.top = px(bb.y) - 3 + 'px'; g.style.width = bb.w * zoom + 6 + 'px'; g.style.height = bb.h * zoom + 6 + 'px';
      overlay.appendChild(g);
    }
    snapLines.forEach(function (s) {
      var d = document.createElement('div');
      d.className = 'cs-snap ' + (s.x !== undefined ? 'v' : 'h');
      if (s.x !== undefined) d.style.left = px(s.x) + 'px'; else d.style.top = px(s.y) + 'px';
      overlay.appendChild(d);
    });
    if (drag && drag.mode === 'marquee' && drag.rect) {
      var m = document.createElement('div'), r = drag.rect;
      m.className = 'cs-marquee';
      m.style.left = px(r.x) + 'px'; m.style.top = px(r.y) + 'px'; m.style.width = r.w * zoom + 'px'; m.style.height = r.h * zoom + 'px';
      overlay.appendChild(m);
    }
    if (drag && drag.tip) {
      var t = document.createElement('div'); t.className = 'cs-tip'; t.textContent = drag.tip.text;
      t.style.left = px(drag.tip.x) + 'px'; t.style.top = px(drag.tip.y) + 'px';
      overlay.appendChild(t);
    }
  }

  function queueDraw() {
    if (drawQueued) return;
    drawQueued = true;
    requestAnimationFrame(function () { drawQueued = false; drawCanvas(); drawOverlay(); syncGeomFields(); });
  }

  function renderAll() {
    if (side === 'back' && !design.double_sided) side = 'front';
    sel = sel.filter(function (id) { return byId(id); });
    document.querySelectorAll('#cs-sides [data-side]').forEach(function (b) {
      b.classList.toggle('active', b.dataset.side === side);
      if (b.dataset.side === 'back') b.classList.toggle('d-none', !design.double_sided);
    });
    $('cs-undo').disabled = !undo.length; $('cs-redo').disabled = !redo.length;
    $('cs-guides').classList.toggle('active', showGuides);
    if (fitted) fitZoom(); else updateZoomLabel();
    drawCanvas(); drawOverlay(); renderLayers(); renderProps();
  }

  /* ---------------- zoom ---------------- */
  function fitZoom() {
    var b = bleed(), W = design.width + 2 * b, H = design.height + 2 * b;
    var cw = canvas.clientWidth - 80, ch = canvas.clientHeight - 80;
    zoom = Math.max(1, Math.min(cw / W, ch / H, 14));
    fitted = true; updateZoomLabel();
  }
  function setZoom(z) { zoom = Math.max(1, Math.min(z, 30)); fitted = false; updateZoomLabel(); drawCanvas(); drawOverlay(); }
  function updateZoomLabel() { $('cs-zoom-label').textContent = Math.round(zoom / MM * 100) + '%'; }

  /* ---------------- geometry / snapping ---------------- */
  function bbox(list) {
    var x1 = Infinity, y1 = Infinity, x2 = -Infinity, y2 = -Infinity;
    list.forEach(function (e) { x1 = Math.min(x1, e.x); y1 = Math.min(y1, e.y); x2 = Math.max(x2, e.x + e.w); y2 = Math.max(y2, e.y + e.h); });
    return { x: x1, y: y1, w: x2 - x1, h: y2 - y1 };
  }
  function targets(axis, skip) {
    var size = axis === 'x' ? design.width : design.height, s = +design.safe || 0, b = bleed();
    var t = [0, size / 2, size, -b, size + b];
    if (s) t.push(s, size - s);
    els().forEach(function (e) {
      if (skip[e.id] || e.hidden || e.rotate) return;
      var p = axis === 'x' ? e.x : e.y, l = axis === 'x' ? e.w : e.h;
      t.push(p, p + l / 2, p + l);
    });
    return t;
  }
  /** Best snap for some edges: {delta, at} or null. */
  function snap(edges, tg) {
    var thr = 6 / zoom, best = null;
    edges.forEach(function (c) { tg.forEach(function (t) { var d = t - c; if (Math.abs(d) <= thr && (!best || Math.abs(d) < Math.abs(best.delta))) best = { delta: d, at: t }; }); });
    return best;
  }

  /* ---------------- mouse ---------------- */
  stage.addEventListener('mousedown', function (ev) {
    if (ev.button !== 0) return;
    var p = toMm(ev), handle = ev.target.closest('.cs-h');
    var snap0 = snapshot();
    if (handle) {
      var e = selected()[0];
      drag = { mode: handle.dataset.h === 'rot' ? 'rotate' : 'resize', h: handle.dataset.h, start: p, snap: snap0, o: { id: e.id, x: e.x, y: e.y, w: e.w, h: e.h, rotate: e.rotate || 0 } };
      ev.preventDefault(); return;
    }
    var g = ev.target.closest('[data-id]'), id = g ? g.getAttribute('data-id') : null;
    if (id) {
      if (ev.shiftKey) {
        if (sel.indexOf(id) >= 0) { sel = sel.filter(function (x) { return x !== id; }); renderAfterSelect(); return; }
        sel.push(id);
      } else if (sel.indexOf(id) < 0) sel = [id];
      renderAfterSelect();
      var orig = {};
      movable().forEach(function (e) { orig[e.id] = { x: e.x, y: e.y }; });
      drag = { mode: 'move', start: p, snap: snap0, orig: orig };
    } else {
      if (!ev.shiftKey) sel = [];
      drag = { mode: 'marquee', start: p, base: sel.slice() };
      renderAfterSelect();
    }
    ev.preventDefault();
  });

  stage.addEventListener('dblclick', function (ev) {
    var g = ev.target.closest('[data-id]'); if (!g) return;
    var e = byId(g.getAttribute('data-id'));
    if (e && e.type === 'text') { var ta = props.querySelector('[data-p=text]'); if (ta) { ta.focus(); ta.select(); } }
    if (e && e.type === 'image' && e.source === 'upload') pickFile(e.id);
  });

  window.addEventListener('mousemove', function (ev) {
    if (!drag) { if (ev.target.closest && ev.target.closest('#cs-stage')) { var q = toMm(ev); $('cs-pos').textContent = 'x ' + r1(q.x) + ' · y ' + r1(q.y) + ' mm'; } return; }
    var p = toMm(ev), dx = p.x - drag.start.x, dy = p.y - drag.start.y;
    if (!drag.changed && Math.abs(dx) * zoom < 2 && Math.abs(dy) * zoom < 2) return;
    if (!drag.changed && drag.mode !== 'marquee') { checkpoint(drag.snap); }
    drag.changed = true; snapLines = [];
    if (drag.mode === 'move') moveTo(dx, dy, ev);
    else if (drag.mode === 'resize') resizeTo(p, ev);
    else if (drag.mode === 'rotate') rotateTo(p, ev);
    else marqueeTo(p, ev);
    queueDraw();
  });

  window.addEventListener('mouseup', function () {
    if (!drag) return;
    var changed = drag.changed, mode = drag.mode;
    drag = null; snapLines = [];
    if (changed && mode !== 'marquee') { renderAll(); }
    else { drawOverlay(); if (mode === 'marquee') { renderLayers(); renderProps(); } }
  });

  function moveTo(dx, dy, ev) {
    var list = movable(); if (!list.length) return;
    if (ev.shiftKey) { if (Math.abs(dx) > Math.abs(dy)) dy = 0; else dx = 0; }
    var moved = list.map(function (e) { var o = drag.orig[e.id]; return { x: o.x + dx, y: o.y + dy, w: e.w, h: e.h }; });
    var bb = bbox(moved), skip = {};
    list.forEach(function (e) { skip[e.id] = 1; });
    if (!ev.altKey) {
      var sx = snap([bb.x, bb.x + bb.w / 2, bb.x + bb.w], targets('x', skip));
      var sy = snap([bb.y, bb.y + bb.h / 2, bb.y + bb.h], targets('y', skip));
      if (sx) { dx += sx.delta; snapLines.push({ x: sx.at }); }
      if (sy) { dy += sy.delta; snapLines.push({ y: sy.at }); }
    }
    list.forEach(function (e) { var o = drag.orig[e.id]; e.x = r2(o.x + dx); e.y = r2(o.y + dy); });
    var nb = bbox(list);
    drag.tip = { x: nb.x + nb.w / 2, y: nb.y + nb.h, text: 'x ' + r1(nb.x) + '  y ' + r1(nb.y) + ' mm' };
  }

  function resizeTo(p, ev) {
    var o = drag.o, e = byId(o.id), h = drag.h; if (!e) return;
    var dx = p.x - drag.start.x, dy = p.y - drag.start.y, a = -(o.rotate || 0) * Math.PI / 180;
    var lx = dx * Math.cos(a) - dy * Math.sin(a), ly = dx * Math.sin(a) + dy * Math.cos(a);
    var x = o.x, y = o.y, w = o.w, hh = o.h;
    if (/e/.test(h)) w = o.w + lx;
    if (/w/.test(h)) { w = o.w - lx; x = o.x + lx; }
    if (/s/.test(h)) hh = o.h + ly;
    if (/n/.test(h)) { hh = o.h - ly; y = o.y + ly; }
    var keep = (ev.shiftKey || e.type === 'qr' || e.type === 'icon') && h.length === 2;
    if (keep) {
      var s = Math.max(w / o.w, hh / o.h);
      w = o.w * s; hh = o.h * s;
      if (/w/.test(h)) x = o.x + o.w - w;
      if (/n/.test(h)) y = o.y + o.h - hh;
    }
    if (!ev.altKey && !o.rotate && !keep) {
      var skip = {}; skip[e.id] = 1;
      if (/e/.test(h)) { var s1 = snap([x + w], targets('x', skip)); if (s1) { w += s1.delta; snapLines.push({ x: s1.at }); } }
      if (/w/.test(h)) { var s2 = snap([x], targets('x', skip)); if (s2) { x += s2.delta; w -= s2.delta; snapLines.push({ x: s2.at }); } }
      if (/s/.test(h)) { var s3 = snap([y + hh], targets('y', skip)); if (s3) { hh += s3.delta; snapLines.push({ y: s3.at }); } }
      if (/n/.test(h)) { var s4 = snap([y], targets('y', skip)); if (s4) { y += s4.delta; hh -= s4.delta; snapLines.push({ y: s4.at }); } }
    }
    var minW = 1, minH = e.type === 'line' ? 0.1 : 1;
    if (w < minW) { if (/w/.test(h)) x -= minW - w; w = minW; }
    if (hh < minH) { if (/n/.test(h)) y -= minH - hh; hh = minH; }
    e.x = r2(x); e.y = r2(y); e.w = r2(w); e.h = r2(hh);
    drag.tip = { x: e.x + e.w / 2, y: e.y + e.h, text: r1(e.w) + ' × ' + r1(e.h) + ' mm' };
  }

  function rotateTo(p, ev) {
    var o = drag.o, e = byId(o.id); if (!e) return;
    var cx = o.x + o.w / 2, cy = o.y + o.h / 2;
    var deg = Math.atan2(p.y - cy, p.x - cx) * 180 / Math.PI + 90;
    if (ev.shiftKey) deg = Math.round(deg / 15) * 15;
    else { var n = Math.round(deg / 90) * 90; if (Math.abs(deg - n) < 4) deg = n; deg = Math.round(deg); }
    deg = ((deg + 180) % 360 + 360) % 360 - 180;
    e.rotate = deg;
    drag.tip = { x: cx, y: o.y + o.h, text: deg + '°' };
  }

  function marqueeTo(p, ev) {
    var x1 = Math.min(p.x, drag.start.x), y1 = Math.min(p.y, drag.start.y), x2 = Math.max(p.x, drag.start.x), y2 = Math.max(p.y, drag.start.y);
    drag.rect = { x: x1, y: y1, w: x2 - x1, h: y2 - y1 };
    var hit = els().filter(function (e) { return !e.hidden && !e.locked && e.x < x2 && e.x + e.w > x1 && e.y < y2 && e.y + e.h > y1; }).map(function (e) { return e.id; });
    sel = drag.base.concat(hit.filter(function (id) { return drag.base.indexOf(id) < 0; }));
  }

  function renderAfterSelect() { drawOverlay(); renderLayers(); renderProps(); }

  /* ---------------- adding ---------------- */
  function centered(w, h) { return { x: r2((design.width - w) / 2), y: r2((design.height - h) / 2), w: w, h: h }; }
  function base(type, name, w, h) { return Object.assign({ id: newId(), type: type, name: name, rotate: 0, opacity: 1, locked: false, hidden: false }, centered(w, h)); }
  function textEl(name, text, extra) {
    return Object.assign(base('text', name, Math.min(design.width - 6, 44), 7), { text: text, font: 'Plus Jakarta Sans', size: 9, weight: 600, italic: false, color: '#101a2e',
      align: 'center', valign: 'middle', upper: false, letter: 0, line: 1.15, fit: true, maxLines: 1 }, extra || {});
  }
  function add(kind, field) {
    var e, s = Math.min(design.width, design.height);
    switch (kind) {
      case 'text': e = textEl('Text', 'Your text'); break;
      case 'field': e = textEl(S.placeholders[field] || field, '{{' + field + '}}'); break;
      case 'photo': e = Object.assign(base('image', 'Photo', s * 0.45, s * 0.45), { source: 'photo', src: '', fit: 'cover', radius: s * 0.225, border: 0, borderColor: '#ffffff' }); break;
      case 'logo': e = Object.assign(base('image', 'Organization logo', s * 0.4, s * 0.2), { source: 'logo', src: '', fit: 'contain', radius: 0, border: 0, borderColor: '#ffffff' }); break;
      case 'image': e = Object.assign(base('image', 'Image', s * 0.4, s * 0.3), { source: 'upload', src: '', fit: 'contain', radius: 0, border: 0, borderColor: '#ffffff' }); break;
      case 'qr': e = Object.assign(base('qr', 'QR code', s * 0.55, s * 0.55), { source: 'profile', text: '', color: '#000000', bg: '#ffffff' }); break;
      case 'rect': e = Object.assign(base('rect', 'Box', s * 0.5, s * 0.25), { fill: '#0e4ca1', stroke: '', strokeWidth: 0, radius: 1 }); break;
      case 'ellipse': e = Object.assign(base('ellipse', 'Circle', s * 0.3, s * 0.3), { fill: '#b22e6b', stroke: '', strokeWidth: 0 }); break;
      case 'line': e = Object.assign(base('line', 'Line', s * 0.6, 0.6), { stroke: '#101a2e', strokeWidth: 0.3 }); break;
      case 'icon': e = Object.assign(base('icon', 'Icon', 6, 6), { icon: Object.keys(S.icons)[0], color: '#0e4ca1' }); break;
    }
    if (!e) return;
    if (els().length >= 80) { Swal.fire('Too many layers', 'A side can have up to 80 layers.', 'info'); return; }
    checkpoint();
    els().push(e); sel = [e.id];
    renderAll();
    if (kind === 'image') pickFile(e.id);
  }
  document.querySelectorAll('[data-add]').forEach(function (b) { b.addEventListener('click', function () { add(b.dataset.add); }); });
  document.querySelectorAll('[data-add-field]').forEach(function (b) { b.addEventListener('click', function () { add('field', b.dataset.addField); }); });

  /* ---------------- selection actions ---------------- */
  function removeSel() {
    var list = selected().filter(function (e) { return !e.locked; }); if (!list.length) return;
    checkpoint();
    var ids = list.map(function (e) { return e.id; });
    face().elements = els().filter(function (e) { return ids.indexOf(e.id) < 0; });
    sel = []; renderAll();
  }
  function duplicateSel(offset) {
    var list = selected(); if (!list.length) return;
    checkpoint();
    var ids = [];
    list.forEach(function (e) { var c = clone(e); c.id = newId(); c.locked = false; c.x = r2(c.x + (offset === undefined ? 2 : offset)); c.y = r2(c.y + (offset === undefined ? 2 : offset)); c.name = c.name.replace(/( copy)?$/, ' copy').slice(0, 40); els().splice(els().indexOf(e) + 1 + ids.length, 0, c); ids.push(c.id); });
    sel = ids; renderAll();
  }
  function copySel() { var list = selected(); if (list.length) { clipboard = clone(list); toast(list.length + ' copied'); } }
  function paste() {
    if (!clipboard) return;
    checkpoint();
    var ids = [];
    clipboard.forEach(function (c0) { var c = clone(c0); c.id = newId(); if (byId(c0.id)) { c.x = r2(c.x + 2); c.y = r2(c.y + 2); } els().push(c); ids.push(c.id); });
    sel = ids; renderAll();
  }
  function nudge(dx, dy) {
    var list = movable(); if (!list.length) return;
    if (!nudge.t) checkpoint();
    clearTimeout(nudge.t); nudge.t = setTimeout(function () { nudge.t = null; }, 600);
    list.forEach(function (e) { e.x = r2(e.x + dx); e.y = r2(e.y + dy); });
    drawCanvas(); drawOverlay(); syncGeomFields();
  }
  function arrange(dir) {
    var list = selected(); if (!list.length) return;
    checkpoint();
    var arr = els(), ids = list.map(function (e) { return e.id; });
    var picked = arr.filter(function (e) { return ids.indexOf(e.id) >= 0; }), rest = arr.filter(function (e) { return ids.indexOf(e.id) < 0; });
    if (dir === 'front') face().elements = rest.concat(picked);
    else if (dir === 'back') face().elements = picked.concat(rest);
    else {
      var order = dir === 'up' ? arr.slice().reverse() : arr.slice();
      for (var i = 1; i < order.length; i++) {
        if (ids.indexOf(order[i].id) >= 0 && ids.indexOf(order[i - 1].id) < 0) { var t = order[i - 1]; order[i - 1] = order[i]; order[i] = t; }
      }
      face().elements = dir === 'up' ? order.reverse() : order;
    }
    renderAll();
  }
  function align(how, toCard) {
    var list = movable(); if (!list.length) return;
    checkpoint();
    var ref = (toCard || list.length === 1) ? { x: 0, y: 0, w: design.width, h: design.height } : bbox(list);
    list.forEach(function (e) {
      if (how === 'left') e.x = ref.x;
      if (how === 'hcenter') e.x = r2(ref.x + (ref.w - e.w) / 2);
      if (how === 'right') e.x = r2(ref.x + ref.w - e.w);
      if (how === 'top') e.y = ref.y;
      if (how === 'vmiddle') e.y = r2(ref.y + (ref.h - e.h) / 2);
      if (how === 'bottom') e.y = r2(ref.y + ref.h - e.h);
    });
    renderAll();
  }
  function distribute(axis) {
    var list = movable(); if (list.length < 3) return;
    checkpoint();
    var k = axis === 'h' ? 'x' : 'y', L = axis === 'h' ? 'w' : 'h';
    list.sort(function (a, b) { return a[k] - b[k]; });
    var first = list[0], last = list[list.length - 1];
    var total = list.reduce(function (s, e) { return s + e[L]; }, 0);
    var gap = (last[k] + last[L] - first[k] - total) / (list.length - 1), pos = first[k];
    list.forEach(function (e) { e[k] = r2(pos); pos += e[L] + gap; });
    renderAll();
  }

  /* ---------------- layers ---------------- */
  var dragLayer = null;
  function renderLayers() {
    var box = $('cs-layers'), list = els().slice().reverse();
    $('cs-layer-count').textContent = list.length ? list.length + '' : '';
    box.innerHTML = list.map(function (e) {
      return '<div class="cs-layer' + (sel.indexOf(e.id) >= 0 ? ' on' : '') + (e.hidden ? ' is-hidden' : '') + '" draggable="true" data-l="' + e.id + '">' +
        '<i class="bi ' + TYPE_ICON[e.type] + ' ti"></i><span class="nm">' + esc(e.name || TYPE_NAME[e.type]) + '</span>' +
        '<button class="tg' + (e.hidden ? ' act' : '') + '" data-tg="hidden" title="' + (e.hidden ? 'Show' : 'Hide') + '"><i class="bi ' + (e.hidden ? 'bi-eye-slash' : 'bi-eye') + '"></i></button>' +
        '<button class="tg' + (e.locked ? ' act' : '') + '" data-tg="locked" title="' + (e.locked ? 'Unlock' : 'Lock') + '"><i class="bi ' + (e.locked ? 'bi-lock-fill' : 'bi-unlock') + '"></i></button></div>';
    }).join('') || '<div class="cs-empty">Nothing on this side yet.<br>Use <b>Add</b> above.</div>';
    box.querySelectorAll('.cs-layer').forEach(function (row) {
      var id = row.dataset.l;
      row.addEventListener('click', function (ev) {
        var tg = ev.target.closest('[data-tg]');
        if (tg) { var e = byId(id); checkpoint(); e[tg.dataset.tg] = !e[tg.dataset.tg]; renderAll(); return; }
        if (ev.shiftKey) { if (sel.indexOf(id) >= 0) sel = sel.filter(function (x) { return x !== id; }); else sel.push(id); }
        else sel = [id];
        renderAfterSelect();
      });
      row.addEventListener('dragstart', function (ev) { dragLayer = id; ev.dataTransfer.effectAllowed = 'move'; try { ev.dataTransfer.setData('text/plain', id); } catch (e) {} });
      row.addEventListener('dragover', function (ev) {
        if (!dragLayer || dragLayer === id) return;
        ev.preventDefault();
        var r = row.getBoundingClientRect(), above = ev.clientY < r.top + r.height / 2;
        row.classList.toggle('drop-above', above); row.classList.toggle('drop-below', !above);
      });
      row.addEventListener('dragleave', function () { row.classList.remove('drop-above', 'drop-below'); });
      row.addEventListener('drop', function (ev) {
        ev.preventDefault();
        var above = row.classList.contains('drop-above');
        row.classList.remove('drop-above', 'drop-below');
        if (!dragLayer || dragLayer === id) return;
        checkpoint();
        var arr = els(), moving = byId(dragLayer);
        arr.splice(arr.indexOf(moving), 1);
        var idx = arr.indexOf(byId(id));
        // the list is shown top layer first: "above" in the list = later in the array
        arr.splice(above ? idx + 1 : idx, 0, moving);
        dragLayer = null; renderAll();
      });
      row.addEventListener('dragend', function () { dragLayer = null; });
    });
  }

  /* ---------------- properties ---------------- */
  function num(label, key, val, unit, step, min, max, attrs) {
    return '<div class="cs-f"><label>' + label + '</label><div class="cs-unit"><input type="number" class="form-control" data-p="' + key + '" value="' + (val === undefined || val === null ? '' : val) + '"' +
      ' step="' + (step || 0.1) + '"' + (min !== undefined ? ' min="' + min + '"' : '') + (max !== undefined ? ' max="' + max + '"' : '') + (attrs || '') + '>' + (unit ? '<span>' + unit + '</span>' : '') + '</div></div>';
  }
  function color(label, key, val, allowNone) {
    var none = allowNone && !val;
    return '<div class="cs-f"><label>' + label + '</label><div class="cs-color"><input type="color" data-p="' + key + '" data-k="color" value="' + (val || '#000000') + '"' + (none ? ' disabled' : '') + '>' +
      '<input type="text" class="form-control" data-p="' + key + '" data-k="hex" value="' + (val || '') + '" maxlength="7"' + (none ? ' disabled placeholder="none"' : '') + '>' +
      (allowNone ? '<label class="none"><input type="checkbox" data-none="' + key + '"' + (none ? ' checked' : '') + '> none</label>' : '') + '</div></div>';
  }
  function select(label, key, val, opts) {
    return '<div class="cs-f"><label>' + label + '</label><select class="form-select" data-p="' + key + '">' +
      opts.map(function (o) { return '<option value="' + esc(o[0]) + '"' + (String(o[0]) === String(val) ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('') + '</select></div>';
  }
  function check(label, key, val) {
    return '<div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" data-p="' + key + '" id="cp-' + key + '"' + (val ? ' checked' : '') + '><label class="form-check-label small" for="cp-' + key + '">' + label + '</label></div>';
  }
  function seg(key, val, opts) {
    return '<div class="cs-seg" data-seg="' + key + '">' + opts.map(function (o) { return '<button type="button" data-v="' + o[0] + '" title="' + o[2] + '" class="' + (o[0] === val ? 'on' : '') + '"><i class="bi ' + o[1] + '"></i></button>'; }).join('') + '</div>';
  }
  function sec(title, body, extra) { return '<div class="cs-sec"><div class="cs-sec-h">' + title + (extra || '') + '</div>' + body + '</div>'; }

  function renderProps() {
    var list = selected(), h = '';
    if (!list.length) h = cardProps();
    else if (list.length > 1) h = multiProps(list);
    else h = elementProps(list[0]);
    props.innerHTML = h;
    bindProps();
  }

  function cardProps() {
    var sizeKey = '';
    Object.keys(S.sizes).forEach(function (k) { var s = S.sizes[k]; if (Math.abs(s[1] - design.width) < 0.05 && Math.abs(s[2] - design.height) < 0.05) sizeKey = k; });
    var bg = face().background;
    var h = sec('Card', select('Size', 'c:size', sizeKey, Object.keys(S.sizes).map(function (k) { return [k, S.sizes[k][0]]; }).concat([['', 'Custom size']])) +
      '<div class="cs-grid2 mt-2">' + num('Width', 'c:width', design.width, 'mm', 0.1, 20, 300) + num('Height', 'c:height', design.height, 'mm', 0.1, 20, 300) +
      num('Bleed', 'c:bleed', design.bleed, 'mm', 0.5, 0, 10) + num('Safe margin', 'c:safe', design.safe, 'mm', 0.5, 0, 20) + '</div>' +
      '<div class="mt-2">' + check('Two-sided (front and back)', 'c:double_sided', design.double_sided) + '</div>' +
      '<div class="small text-muted">Bleed is printed beyond the cut so colours reach the edge — extend backgrounds into it. Keep text inside the safe margin.</div>');
    h += sec('Background · ' + (side === 'front' ? 'front' : 'back'), color('Colour', 'b:color', bg.color) +
      '<div class="cs-f mt-2"><label>Image</label>' + (bg.image ? '<img class="cs-thumb-img mb-1" src="' + esc(S.base + bg.image) + '">' : '') +
      '<div class="d-flex gap-1"><button class="btn btn-sm btn-outline-primary" data-act="bg-upload"><i class="bi bi-upload"></i> ' + (bg.image ? 'Replace' : 'Upload') + '</button>' +
      (bg.image ? '<button class="btn btn-sm btn-outline-danger" data-act="bg-remove"><i class="bi bi-x-lg"></i></button>' : '') + '</div></div>' +
      (bg.image ? '<div class="mt-2">' + select('Image fit', 'b:fit', bg.fit, [['cover', 'Fill the card (crop)'], ['contain', 'Show whole image']]) + '</div>' : '') +
      '<div class="small text-muted mt-1">For a full-bleed artwork, use ' + r1(design.width + 2 * bleed()) + ' × ' + r1(design.height + 2 * bleed()) + ' mm at 300 dpi (' +
      Math.round((design.width + 2 * bleed()) / 25.4 * 300) + ' × ' + Math.round((design.height + 2 * bleed()) / 25.4 * 300) + ' px).</div>');
    h += '<div class="cs-empty"><i class="bi bi-hand-index fs-4 d-block mb-1"></i>Click something on the card to edit it.</div>';
    return h;
  }

  function multiProps(list) {
    return sec(list.length + ' selected', '<div class="d-flex gap-1 flex-wrap">' +
      '<button class="btn btn-sm btn-light" data-act="dup"><i class="bi bi-files"></i> Duplicate</button>' +
      '<button class="btn btn-sm btn-light text-danger" data-act="del"><i class="bi bi-trash"></i> Delete</button></div>') +
      sec('Align', alignButtons() + '<div class="d-flex gap-1 mt-2"><button class="btn btn-sm btn-light flex-fill" data-act="dist-h"' + (list.length < 3 ? ' disabled' : '') + '><i class="bi bi-distribute-horizontal"></i> Space evenly ↔</button>' +
        '<button class="btn btn-sm btn-light flex-fill" data-act="dist-v"' + (list.length < 3 ? ' disabled' : '') + '><i class="bi bi-distribute-vertical"></i> ↕</button></div>' +
        '<div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="cs-to-card"><label class="form-check-label small" for="cs-to-card">Align to the card (not to each other)</label></div>') +
      sec('Order', orderButtons());
  }

  function alignButtons() {
    return '<div class="cs-seg">' + [['left', 'bi-align-start', 'Left'], ['hcenter', 'bi-align-center', 'Centre'], ['right', 'bi-align-end', 'Right'], ['top', 'bi-align-top', 'Top'], ['vmiddle', 'bi-align-middle', 'Middle'], ['bottom', 'bi-align-bottom', 'Bottom']]
      .map(function (a) { return '<button type="button" data-align="' + a[0] + '" title="Align ' + a[2].toLowerCase() + '"><i class="bi ' + a[1] + '"></i></button>'; }).join('') + '</div>';
  }
  function orderButtons() {
    return '<div class="cs-seg"><button data-order="front" title="Bring to front"><i class="bi bi-chevron-double-up"></i></button><button data-order="up" title="Forward ( ] )"><i class="bi bi-chevron-up"></i></button>' +
      '<button data-order="down" title="Backward ( [ )"><i class="bi bi-chevron-down"></i></button><button data-order="back" title="Send to back"><i class="bi bi-chevron-double-down"></i></button></div>';
  }

  function elementProps(e) {
    var h = sec(TYPE_NAME[e.type], '<div class="cs-f mb-2"><label>Layer name</label><input class="form-control" data-p="name" value="' + esc(e.name) + '" maxlength="40"></div>' +
      '<div class="cs-grid2">' + num('X', 'x', e.x, 'mm') + num('Y', 'y', e.y, 'mm') + num('Width', 'w', e.w, 'mm', 0.1, 0.1) + num('Height', 'h', e.h, 'mm', 0.1, 0.05) +
      num('Rotate', 'rotate', e.rotate || 0, '°', 1, -180, 180) + num('Opacity', 'opacity', Math.round((e.opacity === undefined ? 1 : e.opacity) * 100), '%', 5, 0, 100) + '</div>' +
      '<div class="d-flex gap-3 mt-2">' + check('Locked', 'locked', e.locked) + check('Hidden', 'hidden', e.hidden) + '</div>',
      ' <span class="d-flex gap-1"><button class="btn btn-sm btn-light py-0" data-act="dup" title="Duplicate (Ctrl+D)"><i class="bi bi-files"></i></button><button class="btn btn-sm btn-light py-0 text-danger" data-act="del" title="Delete (Del)"><i class="bi bi-trash"></i></button></span>');
    h += sec('Position on card', alignButtons() + '<div class="mt-2">' + orderButtons() + '</div>');
    if (e.type === 'text') h += textProps(e);
    if (e.type === 'image') h += imageProps(e);
    if (e.type === 'qr') h += qrProps(e);
    if (e.type === 'rect') h += sec('Style', color('Fill', 'fill', e.fill, true) + '<div class="mt-2">' + color('Border', 'stroke', e.stroke, true) + '</div><div class="cs-grid2 mt-2">' +
      num('Border width', 'strokeWidth', e.strokeWidth, 'mm', 0.1, 0, 10) + num('Corner radius', 'radius', e.radius, 'mm', 0.5, 0, 200) + '</div>');
    if (e.type === 'ellipse') h += sec('Style', color('Fill', 'fill', e.fill, true) + '<div class="mt-2">' + color('Border', 'stroke', e.stroke, true) + '</div><div class="cs-grid2 mt-2">' +
      num('Border width', 'strokeWidth', e.strokeWidth, 'mm', 0.1, 0, 10) + '</div>');
    if (e.type === 'line') h += sec('Style', color('Colour', 'stroke', e.stroke) + '<div class="cs-grid2 mt-2">' + num('Thickness', 'strokeWidth', e.strokeWidth, 'mm', 0.05, 0.05, 10) + '</div>' +
      '<div class="small text-muted mt-1">Use Rotate for an angled or vertical line.</div>');
    if (e.type === 'icon') h += sec('Icon', '<div class="cs-icons mb-2">' + Object.keys(S.icons).map(function (k) {
      var ic = S.icons[k];
      return '<button type="button" data-icon="' + k + '" title="' + esc(ic.label) + '" class="' + (k === e.icon ? 'on' : '') + '"><svg viewBox="0 0 16 16" fill="currentColor">' +
        ic.paths.map(function (p) { return '<path d="' + p.d + '"' + (p.rule ? ' fill-rule="' + p.rule + '"' : '') + '/>'; }).join('') + '</svg></button>';
    }).join('') + '</div>' + color('Colour', 'color', e.color));
    return h;
  }

  function textProps(e) {
    return sec('Text', '<div class="cs-f"><textarea class="form-control" rows="2" data-p="text" maxlength="500">' + esc(e.text) + '</textarea></div>' +
      '<select class="form-select form-select-sm mt-1" data-act="insert-field"><option value="">+ Insert a participant field…</option>' +
      Object.keys(S.placeholders).map(function (k) { return '<option value="' + k + '">' + esc(S.placeholders[k]) + '</option>'; }).join('') + '</select>' +
      '<div class="small text-muted mt-1">Fields like <code>{{name}}</code> are replaced with each participant\'s details. Empty fields print nothing.</div>') +
      sec('Font', select('Font', 'font', e.font, S.fonts.map(function (f) { return [f, f]; })) +
        '<div class="cs-grid2 mt-2">' + num('Size', 'size', e.size, 'pt', 0.5, 2, 120) + select('Weight', 'weight', e.weight, [[300, 'Light'], [400, 'Regular'], [500, 'Medium'], [600, 'Semi-bold'], [700, 'Bold'], [800, 'Extra-bold']]) + '</div>' +
        '<div class="mt-2">' + color('Colour', 'color', e.color) + '</div>' +
        '<div class="cs-grid2 mt-2"><div class="cs-f"><label>Align</label>' + seg('align', e.align, [['left', 'bi-text-left', 'Left'], ['center', 'bi-text-center', 'Centre'], ['right', 'bi-text-right', 'Right']]) + '</div>' +
        '<div class="cs-f"><label>Vertical</label>' + seg('valign', e.valign, [['top', 'bi-align-top', 'Top'], ['middle', 'bi-align-middle', 'Middle'], ['bottom', 'bi-align-bottom', 'Bottom']]) + '</div></div>' +
        '<div class="cs-grid2 mt-2">' + num('Letter spacing', 'letter', e.letter, 'em', 0.01, -0.1, 0.5) + num('Line height', 'line', e.line, '×', 0.05, 0.8, 3) + num('Max. lines', 'maxLines', e.maxLines, '', 1, 1, 10) + '</div>' +
        '<div class="d-flex flex-wrap gap-3 mt-2">' + check('Italic', 'italic', e.italic) + check('UPPERCASE', 'upper', e.upper) + check('Shrink to fit', 'fit', e.fit) + '</div>' +
        '<div class="small text-muted">"Shrink to fit" makes long names smaller so they stay inside the box.</div>');
  }

  function imageProps(e) {
    var src = e.source === 'upload' && e.src ? '<img class="cs-thumb-img mb-1" src="' + esc(S.base + e.src) + '">' : '';
    return sec('Image', select('Shows', 'source', e.source, [['photo', 'Participant photo'], ['logo', 'Participant organization logo'], ['upload', 'A fixed image (upload)']]) +
      (e.source === 'upload' ? '<div class="mt-2">' + src + '<button class="btn btn-sm btn-outline-primary" data-act="img-upload"><i class="bi bi-upload"></i> ' + (e.src ? 'Replace image' : 'Upload image') + '</button></div>' : '') +
      (e.source === 'photo' ? '<div class="small text-muted mt-1">Without a photo, the participant\'s initials are shown.</div>' : '') +
      (e.source === 'logo' ? '<div class="small text-muted mt-1">Left empty when the participant has no logo.</div>' : '') +
      '<div class="mt-2">' + select('Fit', 'fit', e.fit, [['cover', 'Fill the box (crop)'], ['contain', 'Show whole image']]) + '</div>' +
      '<div class="cs-grid2 mt-2">' + num('Corner radius', 'radius', e.radius, 'mm', 0.5, 0, 200) + num('Border', 'border', e.border, 'mm', 0.1, 0, 10) + '</div>' +
      '<button class="btn btn-sm btn-light mt-2" data-act="circle"><i class="bi bi-circle"></i> Make round</button>' +
      '<div class="mt-2">' + color('Border colour', 'borderColor', e.borderColor) + '</div>');
  }

  function lum(hex) {
    var c = (hex || '#000000').replace('#', ''), rgb = [0, 2, 4].map(function (i) { var v = parseInt(c.substr(i, 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
    return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
  }
  function qrProps(e) {
    var l1 = lum(e.color), l2 = lum(e.bg || '#ffffff'), contrast = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    var warn = [];
    if (contrast < 4) warn.push('Low contrast — the QR code may not scan. Use a dark colour on a light background.');
    if (l1 > l2) warn.push('Light code on a dark background: some phone scanners cannot read inverted QR codes.');
    if (Math.min(e.w, e.h) < 15) warn.push('Smaller than 15 mm — may be hard to scan.');
    return sec('QR code', select('Opens', 'source', e.source, [['profile', 'Participant profile (permanent link)'], ['nfc', 'Same link as the NFC card'], ['custom', 'Custom text / link']]) +
      (e.source === 'custom' ? '<div class="cs-f mt-2"><label>Text or link (fields allowed)</label><input class="form-control" data-p="text" value="' + esc(e.text) + '" maxlength="300" placeholder="https://…"></div>' : '') +
      '<div class="cs-grid2 mt-2">' + color('Code', 'color', e.color) + color('Background', 'bg', e.bg) + '</div>' +
      warn.map(function (w) { return '<div class="cs-warn"><i class="bi bi-exclamation-triangle"></i> ' + w + '</div>'; }).join(''));
  }

  /** Update X / Y / W / H / rotate fields while dragging. */
  function syncGeomFields() {
    var list = selected(); if (list.length !== 1) return;
    var e = list[0];
    ['x', 'y', 'w', 'h', 'rotate'].forEach(function (k) { var f = props.querySelector('[data-p="' + k + '"]'); if (f && document.activeElement !== f) f.value = e[k] || 0; });
  }

  function setValue(key, raw, input) {
    var v = raw;
    if (key.indexOf('c:') === 0) {
      var k = key.slice(2);
      if (k === 'size') { if (!raw) return; design.width = S.sizes[raw][1]; design.height = S.sizes[raw][2]; fitted = true; renderAll(); return; }
      if (k === 'double_sided') design.double_sided = !!raw;
      else {
        v = parseFloat(raw);
        var lim = { width: [20, 300], height: [20, 300], bleed: [0, 10], safe: [0, 20] }[k];
        if (isNaN(v) || v < lim[0] || v > lim[1]) return;
        design[k] = v;
      }
      if (k === 'width' || k === 'height' || k === 'bleed') fitted = true;
      return 'card';
    }
    if (key.indexOf('b:') === 0) { face().background[key.slice(2)] = raw; return 'draw'; }
    var e = selected()[0]; if (!e) return;
    if (['x', 'y', 'w', 'h', 'rotate', 'size', 'letter', 'line', 'strokeWidth', 'radius', 'border'].indexOf(key) >= 0) { v = parseFloat(raw); if (isNaN(v)) return; }
    if (key === 'opacity') { v = parseFloat(raw); if (isNaN(v)) return; v = Math.max(0, Math.min(1, v / 100)); }
    if (key === 'maxLines' || key === 'weight') { v = parseInt(raw, 10); if (isNaN(v)) return; }
    if (key === 'w' && v < 0.1) return;
    e[key] = v;
    if (key === 'name') { var row = document.querySelector('[data-l="' + e.id + '"] .nm'); if (row) row.textContent = v; return 'none'; }
    if (key === 'source' || key === 'locked' || key === 'hidden') return 'all';
    return 'draw';
  }

  function bindProps() {
    props.querySelectorAll('[data-p]').forEach(function (f) {
      var key = f.dataset.p;
      f.addEventListener('focus', function () { pendingSnap = snapshot(); });
      var handler = function () {
        if (pendingSnap) { checkpoint(pendingSnap); pendingSnap = null; } else if (f.type === 'checkbox' || f.tagName === 'SELECT') checkpoint();
        var raw = f.type === 'checkbox' ? f.checked : f.value;
        if (f.dataset.k === 'hex') { if (!/^#[0-9a-f]{6}$/i.test(raw)) return; var c = f.parentNode.querySelector('[data-k=color]'); if (c) c.value = raw.toLowerCase(); raw = raw.toLowerCase(); }
        if (f.dataset.k === 'color') { var t = f.parentNode.querySelector('[data-k=hex]'); if (t) t.value = raw; }
        var r = setValue(key, raw, f);
        if (r === 'all' || (r === 'card' && f.type === 'checkbox')) { renderAll(); return; }
        if (r === 'card') { if (fitted) fitZoom(); drawCanvas(); drawOverlay(); return; }
        if (r === 'draw') { drawCanvas(); drawOverlay(); }
      };
      f.addEventListener(f.type === 'checkbox' || f.tagName === 'SELECT' ? 'change' : 'input', handler);
      f.addEventListener('blur', function () { pendingSnap = null; });
      if (f.type === 'number' || f.dataset.k) f.addEventListener('change', function () { var e = selected()[0]; if (e && e.type === 'qr') renderProps(); });
    });
    props.querySelectorAll('[data-none]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        var e = selected()[0], key = cb.dataset.none; if (!e) return;
        checkpoint();
        e[key] = cb.checked ? '' : (key === 'stroke' ? '#101a2e' : '#0e4ca1');
        if (key === 'stroke' && !cb.checked && !e.strokeWidth) e.strokeWidth = 0.3;
        renderAll();
      });
    });
    props.querySelectorAll('[data-seg]').forEach(function (g) {
      g.querySelectorAll('button').forEach(function (b) {
        b.addEventListener('click', function () { var e = selected()[0]; if (!e) return; checkpoint(); e[g.dataset.seg] = b.dataset.v; g.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); drawCanvas(); drawOverlay(); });
      });
    });
    props.querySelectorAll('[data-align]').forEach(function (b) { b.addEventListener('click', function () { var tc = $('cs-to-card'); align(b.dataset.align, tc && tc.checked); }); });
    props.querySelectorAll('[data-order]').forEach(function (b) { b.addEventListener('click', function () { arrange(b.dataset.order); }); });
    props.querySelectorAll('[data-icon]').forEach(function (b) { b.addEventListener('click', function () { var e = selected()[0]; checkpoint(); e.icon = b.dataset.icon; if (/^Icon$|icon$/i.test(e.name) || e.name === 'Icon') e.name = S.icons[e.icon].label + ' icon'; renderAll(); }); });
    props.querySelectorAll('[data-act]').forEach(function (b) {
      var act = b.dataset.act;
      b.addEventListener(b.tagName === 'SELECT' ? 'change' : 'click', function () {
        var e = selected()[0];
        if (act === 'dup') duplicateSel();
        if (act === 'del') removeSel();
        if (act === 'dist-h') distribute('h');
        if (act === 'dist-v') distribute('v');
        if (act === 'circle' && e) { checkpoint(); var s = Math.min(e.w, e.h); e.w = s; e.h = s; e.radius = r2(s / 2); renderAll(); }
        if (act === 'img-upload' && e) pickFile(e.id);
        if (act === 'bg-upload') pickFile('__bg');
        if (act === 'bg-remove') { checkpoint(); face().background.image = ''; renderAll(); }
        if (act === 'insert-field' && b.value) {
          var ta = props.querySelector('[data-p=text]'), tok = '{{' + b.value + '}}';
          checkpoint();
          var at = ta.selectionStart || ta.value.length;
          ta.value = ta.value.slice(0, at) + tok + ta.value.slice(ta.selectionEnd || at);
          e.text = ta.value; b.value = '';
          drawCanvas(); drawOverlay(); ta.focus();
        }
      });
    });
  }

  /* ---------------- uploads ---------------- */
  function pickFile(target) { uploadTarget = target; $('cs-file').value = ''; $('cs-file').click(); }
  $('cs-file').addEventListener('change', function () {
    var f = this.files[0]; if (!f) return;
    var fd = new FormData(); fd.append('image', f);
    $('cs-status').textContent = 'Uploading…';
    fetch(S.urls.upload, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        $('cs-status').textContent = dirty ? 'Unsaved changes' : '';
        if (!res.ok) { Swal.fire('Not uploaded', errorOf(res), 'warning'); return; }
        checkpoint();
        if (uploadTarget === '__bg') face().background.image = res.d.path;
        else {
          var e = byId(uploadTarget);
          if (e) {
            e.src = res.d.path; e.source = 'upload';
            // keep the picture's proportions
            var img = new Image();
            img.onload = function () { if (img.naturalWidth && img.naturalHeight) { var ratio = img.naturalHeight / img.naturalWidth; e.h = r2(e.w * ratio); renderAll(); } };
            img.src = res.d.url;
          }
        }
        renderAll();
      })
      .catch(function () { Swal.fire('Not uploaded', 'Please try again.', 'warning'); });
  });

  /* ---------------- toolbar ---------------- */
  document.querySelectorAll('#cs-sides [data-side]').forEach(function (b) { b.addEventListener('click', function () { side = b.dataset.side; sel = []; renderAll(); }); });
  $('cs-undo').addEventListener('click', doUndo);
  $('cs-redo').addEventListener('click', doRedo);
  $('cs-zoom-in').addEventListener('click', function () { setZoom(zoom * 1.2); });
  $('cs-zoom-out').addEventListener('click', function () { setZoom(zoom / 1.2); });
  $('cs-zoom-label').addEventListener('click', function () { fitZoom(); drawCanvas(); drawOverlay(); });
  $('cs-zoom-real').addEventListener('click', function () { setZoom(MM); });
  $('cs-guides').addEventListener('click', function () { showGuides = !showGuides; this.classList.toggle('active', showGuides); drawCanvas(); });
  canvas.addEventListener('wheel', function (ev) { if (!ev.ctrlKey) return; ev.preventDefault(); setZoom(zoom * (ev.deltaY < 0 ? 1.1 : 1 / 1.1)); }, { passive: false });
  canvas.addEventListener('mousedown', function (ev) { if (ev.target === canvas && !ev.shiftKey) { sel = []; renderAfterSelect(); } });
  window.addEventListener('resize', function () { if (fitted) { fitZoom(); drawCanvas(); drawOverlay(); } });
  $('cs-name').addEventListener('input', markDirty);

  // what the fields show
  $('cs-data-mode').addEventListener('change', function () {
    var v = this.value;
    $('cs-search-wrap').classList.toggle('d-none', v !== 'participant');
    if (v === 'participant') { $('cs-search').focus(); return; }
    dataMode = v; data = S.sample; drawCanvas();
  });
  var searchTimer, results = [];
  $('cs-search').addEventListener('input', function () {
    clearTimeout(searchTimer); var q = this.value.trim(), list = $('cs-search-list');
    if (q.length < 2) { list.style.display = 'none'; return; }
    searchTimer = setTimeout(function () {
      fetch(S.urls.participants + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (rows) {
        results = rows;
        list.innerHTML = rows.map(function (p, i) { return '<button data-i="' + i + '"><b>' + esc(p.name) + '</b> <span class="text-muted">' + esc(p.code) + '</span><br><span class="small text-muted">' + esc(p.organization || '') + (p.category ? ' · ' + esc(p.category) : '') + '</span></button>'; }).join('') ||
          '<div class="p-2 small text-muted">No participants found.</div>';
        list.style.display = 'block';
        list.querySelectorAll('[data-i]').forEach(function (b) { b.addEventListener('mousedown', function (ev) { ev.preventDefault(); usePerson(results[+b.dataset.i]); }); });
      });
    }, 250);
  });
  $('cs-search').addEventListener('blur', function () { setTimeout(function () { $('cs-search-list').style.display = 'none'; }, 150); });
  function usePerson(p) {
    $('cs-search-list').style.display = 'none';
    $('cs-search').value = p.name + ' · ' + p.code;
    fetch(S.urls.data + p.id, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      data = d; dataMode = 'participant'; participantName = p.name;
      CardRenderer.ready(design).then(drawCanvas);
    });
  }

  /* ---------------- keyboard ---------------- */
  document.addEventListener('keydown', function (ev) {
    var ctrl = ev.ctrlKey || ev.metaKey, k = ev.key;
    if (ctrl && k.toLowerCase() === 's') { ev.preventDefault(); save(); return; }
    if (document.querySelector('.modal.show') || document.querySelector('.swal2-container')) return;
    if (isTyping(ev)) { if (k === 'Escape') ev.target.blur(); return; }
    if (ctrl && k.toLowerCase() === 'z' && !ev.shiftKey) { ev.preventDefault(); doUndo(); return; }
    if (ctrl && (k.toLowerCase() === 'y' || (k.toLowerCase() === 'z' && ev.shiftKey))) { ev.preventDefault(); doRedo(); return; }
    if (ctrl && k.toLowerCase() === 'd') { ev.preventDefault(); duplicateSel(); return; }
    if (ctrl && k.toLowerCase() === 'c') { copySel(); return; }
    if (ctrl && k.toLowerCase() === 'v') { ev.preventDefault(); paste(); return; }
    if (ctrl && k.toLowerCase() === 'a') { ev.preventDefault(); sel = els().filter(function (e) { return !e.locked && !e.hidden; }).map(function (e) { return e.id; }); renderAfterSelect(); return; }
    if (k === 'Delete' || k === 'Backspace') { ev.preventDefault(); removeSel(); return; }
    if (k === 'Escape') { sel = []; renderAfterSelect(); return; }
    if (k === ']') { arrange(ctrl ? 'front' : 'up'); return; }
    if (k === '[') { arrange(ctrl ? 'back' : 'down'); return; }
    if (k === '+' || k === '=') { setZoom(zoom * 1.2); return; }
    if (k === '-') { setZoom(zoom / 1.2); return; }
    if (k === '0') { fitZoom(); drawCanvas(); drawOverlay(); return; }
    var step = ev.shiftKey ? 2 : ev.altKey ? 0.1 : 0.5;
    if (k === 'ArrowLeft') { ev.preventDefault(); nudge(-step, 0); }
    if (k === 'ArrowRight') { ev.preventDefault(); nudge(step, 0); }
    if (k === 'ArrowUp') { ev.preventDefault(); nudge(0, -step); }
    if (k === 'ArrowDown') { ev.preventDefault(); nudge(0, step); }
  });
  document.addEventListener('keyup', function (ev) { if (/^Arrow/.test(ev.key) && selected().length === 1) renderProps(); });

  /* ---------------- save / versions ---------------- */
  function save(note, force) {
    var btn = $('cs-save'); btn.disabled = true;
    $('cs-status').textContent = 'Saving…';
    json('PUT', S.urls.save, { design: design, name: $('cs-name').value.trim(), note: note || null, base: baseVersion, force: !!force }).then(function (res) {
      btn.disabled = false;
      if (res.status === 409 && res.d.conflict) {
        $('cs-status').textContent = 'Not saved';
        Swal.fire({ title: 'Saved by someone else', text: res.d.message + ' Save yours anyway (theirs stays in the version history)?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Save mine' })
          .then(function (r) { if (r.isConfirmed) save(note, true); });
        return;
      }
      if (!res.ok) { $('cs-status').textContent = 'Not saved'; Swal.fire('Not saved', errorOf(res), 'warning'); return; }
      baseVersion = res.d.version; S.versions = res.d.versions;
      // the server cleans the design (e.g. drops a missing image) — keep the editor in line
      design = res.d.design;
      dirty = false; clearDraft();
      btn.classList.remove('is-dirty');
      $('cs-status').textContent = 'Saved · version ' + baseVersion;
      toast(res.d.message);
      renderAll();
    }).catch(function () { btn.disabled = false; $('cs-status').textContent = 'Not saved'; Swal.fire('Not saved', 'Check your connection and try again.', 'warning'); });
  }
  $('cs-save').addEventListener('click', function () { save(); });
  $('cs-save-note').addEventListener('click', function () {
    Swal.fire({ title: 'Save with a note', input: 'text', inputPlaceholder: 'e.g. Bigger names for the speaker badges', inputAttributes: { maxlength: 200 }, showCancelButton: true, confirmButtonText: 'Save' })
      .then(function (r) { if (r.isConfirmed) save(r.value); });
  });

  var versionsModal = new bootstrap.Modal($('csVersions'));
  $('cs-versions-btn').addEventListener('click', function () {
    $('cs-version-list').innerHTML = S.versions.map(function (v) {
      return '<div class="list-group-item d-flex justify-content-between align-items-center gap-2"><div><b>Version ' + v.version + '</b>' + (v.version === baseVersion ? ' <span class="badge bg-success-subtle text-success-emphasis">current</span>' : '') +
        '<div class="small text-muted">' + esc(v.at) + (v.user ? ' · ' + esc(v.user) : '') + '</div>' + (v.note ? '<div class="small">' + esc(v.note) + '</div>' : '') + '</div>' +
        '<button class="btn btn-sm btn-outline-primary" data-v="' + v.version + '">Open</button></div>';
    }).join('') || '<div class="p-3 text-muted">No versions yet.</div>';
    $('cs-version-list').querySelectorAll('[data-v]').forEach(function (b) {
      b.addEventListener('click', function () {
        fetch(S.urls.version + b.dataset.v, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
          checkpoint();
          design = d.design; sel = []; fitted = true;
          versionsModal.hide();
          CardRenderer.ready(design).then(renderAll);
          toast('Version ' + d.version + ' opened — Save to keep it', 'info');
        });
      });
    });
    versionsModal.show();
  });

  // preview
  var pvModal = new bootstrap.Modal($('csPreview'));
  function drawPreview() {
    var marks = $('cs-pv-marks').checked, box = $('cs-pv');
    box.classList.toggle('marks', marks);
    box.innerHTML = '';
    (design.double_sided ? ['front', 'back'] : ['front']).forEach(function (s) {
      var fig = document.createElement('figure'), h = Math.min(window.innerHeight - 260, 460);
      var W = design.width + (marks ? 2 * (bleed() + 6) : 0), H = design.height + (marks ? 2 * (bleed() + 6) : 0);
      fig.appendChild(CardRenderer.render(design, s, renderData(), { icons: S.icons, base: S.base, trim: !marks, marks: marks, mode: dataMode === 'placeholders' ? 'placeholders' : 'data', widthPx: Math.round(h * W / H) }));
      var cap = document.createElement('figcaption'); cap.textContent = (s === 'front' ? 'Front' : 'Back') + ' · ' + design.width + ' × ' + design.height + ' mm' + (marks ? ' + ' + design.bleed + ' mm bleed' : '');
      fig.appendChild(cap); box.appendChild(fig);
    });
  }
  $('cs-preview-btn').addEventListener('click', function () { drawPreview(); pvModal.show(); });
  $('cs-pv-marks').addEventListener('change', drawPreview);

  window.addEventListener('beforeunload', function (ev) { if (dirty) { ev.preventDefault(); ev.returnValue = ''; } });

  /* ---------------- start ---------------- */
  CardRenderer.ready(design).then(function () {
    renderAll();
    var draft = null;
    try { draft = JSON.parse(localStorage.getItem(draftKey()) || 'null'); } catch (e) {}
    if (draft && draft.base === baseVersion && JSON.stringify(draft.design) !== JSON.stringify(design)) {
      Swal.fire({ title: 'Unsaved changes found', text: 'You have changes from ' + new Date(draft.at).toLocaleString() + ' that were not saved. Bring them back?', icon: 'question', showCancelButton: true, confirmButtonText: 'Bring back', cancelButtonText: 'Discard' })
        .then(function (r) { if (r.isConfirmed) { checkpoint(); design = draft.design; CardRenderer.ready(design).then(renderAll); } else clearDraft(); });
    } else if (draft) clearDraft();
  });
})();
