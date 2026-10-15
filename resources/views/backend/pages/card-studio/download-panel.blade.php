{{--
  Shared card-download options + progress (Card Design Studio › Previews, Card Printing).
  JS: CardDownloads.options() → {formats, dpi, sides, bleed, pdfPack}
      CardDownloads.run(items, ids, {templateId, eventId, label})   items = [{name, code, design, data}] for PNG/JPG/EPS; ids for PDF
      CardDownloads.one(item, id, fmt, {templateId, eventId})       one participant, one format
--}}
@once
<style>
  .cdl { border: 1px solid var(--ad-line); border-radius: 16px; background: var(--ad-surface); padding: 12px 14px; }
  .cdl-row { display: flex; flex-wrap: wrap; gap: 10px 18px; align-items: center; }
  .cdl-lbl { font-size: 11.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--ad-muted); }
  .cdl-fmts { display: flex; flex-wrap: wrap; gap: 6px; }
  .cdl-fmts label { display: inline-flex; align-items: center; gap: 6px; border: 1.5px solid var(--ad-line); border-radius: 10px; padding: 5px 10px; font-size: 13px; font-weight: 700; cursor: pointer; color: var(--ad-text); user-select: none; }
  .cdl-fmts label:has(input:checked) { border-color: var(--ad-brand); background: var(--ad-brand-50); color: var(--ad-brand); }
  .cdl-fmts input { margin: 0; }
  .cdl-fmts small { font-weight: 600; color: var(--ad-muted); }
  .cdl select { width: auto; }
  .cdl-note { font-size: 12px; color: var(--ad-muted); margin-top: 8px; }
  .cdl-prog { height: 10px; border-radius: 999px; background: var(--ad-chip); overflow: hidden; }
  .cdl-prog i { display: block; height: 100%; width: 0; background: linear-gradient(90deg, var(--ad-brand), #b22e6b); transition: width .2s; }
  .cdl-overlay { position: fixed; inset: 0; z-index: 2000; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(10,20,40,.45); backdrop-filter: blur(2px); }
  .cdl-overlay.on { display: flex; animation: cdlIn .15s ease; } @keyframes cdlIn { from { opacity: 0; } to { opacity: 1; } }
  .cdl-box { width: 100%; max-width: 440px; background: var(--ad-surface, #fff); color: var(--ad-text, #1d2433); border-radius: 18px; padding: 24px; box-shadow: 0 30px 60px -20px rgba(0,0,0,.5); }
  .cdl-box h5 { color: var(--ad-ink, #1d2433); }
  .cdl-one { display: inline-flex; gap: 4px; flex-wrap: wrap; }
  .cdl-one button { border: 1px solid var(--ad-line); background: var(--ad-surface); color: var(--ad-text); border-radius: 8px; font-size: 12px; font-weight: 700; padding: 3px 8px; }
  .cdl-one button:hover { border-color: var(--ad-brand); color: var(--ad-brand); }
  .cdl-one button:disabled { opacity: .5; }
</style>
@endonce
<div class="cdl" id="{{ $id ?? 'cdl' }}">
  <div class="cdl-row">
    <span class="cdl-lbl"><i class="bi bi-download"></i> Download as</span>
    <div class="cdl-fmts" role="group" aria-label="File formats">
      <label title="Picture with transparent-free white card, DPI written in the file"><input type="checkbox" class="form-check-input" name="cdl-fmt" value="png" checked> PNG</label>
      <label><input type="checkbox" class="form-check-input" name="cdl-fmt" value="jpg"> JPG</label>
      <label title="Vector PDF — sharpest for printing"><input type="checkbox" class="form-check-input" name="cdl-fmt" value="pdf"> PDF <small>vector</small></label>
      <label title="Encapsulated PostScript with CMYK colours for print shops"><input type="checkbox" class="form-check-input" name="cdl-fmt" value="eps"> EPS <small>CMYK</small></label>
    </div>
    <select class="form-select form-select-sm" name="cdl-dpi" aria-label="Resolution"><option value="300">300 DPI · print</option><option value="600">600 DPI · high</option></select>
    <select class="form-select form-select-sm" name="cdl-sides" aria-label="Sides"><option value="both">Front + back</option><option value="front">Front only</option><option value="back">Back only</option></select>
    <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="cdl-bleed" id="{{ ($id ?? 'cdl') }}-bleed"><label class="form-check-label small" for="{{ ($id ?? 'cdl') }}-bleed">Include bleed</label></div>
    <select class="form-select form-select-sm d-none" name="cdl-pack" aria-label="PDF files"><option value="one">PDF: one file</option><option value="zip">PDF: one per person</option></select>
  </div>
  <div class="cdl-note"><i class="bi bi-info-circle"></i> Exact card size (e.g. 85.6 × 54 mm). PNG / JPG / EPS are drawn at the chosen DPI with the original photos; PDF keeps text and QR codes as vectors. Several cards come as one ZIP.</div>
</div>

@once
<div class="cdl-overlay" id="cdlProgress" role="status" aria-live="polite" aria-hidden="true">
  <div class="cdl-box">
    <h5 class="fw-bold mb-1" id="cdlProgressT"><i class="bi bi-file-earmark-zip"></i> Preparing your cards…</h5>
    <p class="text-muted small mb-3" id="cdlProgressS">Drawing each card at print resolution.</p>
    <div class="cdl-prog"><i id="cdlProgressBar"></i></div>
    <div class="small text-muted mt-2" id="cdlProgressN"></div>
  </div>
</div>
<script>
window.CardDownloads = (function () {
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var R = { pdf: @json(route('card-export.pdf')), data: @json(route('card-export.data')) };
  var base = @json(url('/') . '/');
  function icons() { return window.CARD_ICONS || {}; }   // set by the page (icon set of the studio)
  function panel(id) { return document.getElementById(id || 'cdl'); }
  function options(id) {
    var p = panel(id), f = [].slice.call(p.querySelectorAll('[name=cdl-fmt]:checked')).map(function (x) { return x.value; });
    return { formats: f, dpi: +p.querySelector('[name=cdl-dpi]').value, sides: p.querySelector('[name=cdl-sides]').value, bleed: p.querySelector('[name=cdl-bleed]').checked, pdfPack: p.querySelector('[name=cdl-pack]').value };
  }
  document.querySelectorAll('.cdl').forEach(function (p) {
    function sync() { var pdf = p.querySelector('[value=pdf]').checked, many = p.querySelectorAll('[name=cdl-fmt]:checked').length; p.querySelector('[name=cdl-pack]').classList.toggle('d-none', !pdf || many > 1); }
    p.addEventListener('change', sync); sync();
  });
  /*
   * Progress overlay — deliberately not a Bootstrap modal: it often opens on top of another modal (the Card Printing
   * preview), and two stacked Bootstrap modals leave a backdrop / scroll lock behind when one closes.
   */
  function progress(show, title, n, total) {
    var el = document.getElementById('cdlProgress');
    if (!show) { el.classList.remove('on'); el.setAttribute('aria-hidden', 'true'); return; }
    if (title) document.getElementById('cdlProgressT').innerHTML = title;
    document.getElementById('cdlProgressBar').style.width = total ? Math.round(n / total * 100) + '%' : '8%';
    document.getElementById('cdlProgressN').textContent = total ? n + ' of ' + total + ' files' : '';
    el.classList.add('on'); el.setAttribute('aria-hidden', 'false');
  }
  function pdf(ids, o, ctx, pack) {
    return fetch(R.pdf, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/pdf, application/zip, application/json' },
      body: JSON.stringify({ ids: ids, template_id: ctx.templateId || null, event_id: ctx.eventId || null, sides: o.sides, bleed: o.bleed, marks: false, pack: pack }) })
      .then(function (r) {
        if (!r.ok) return r.json().catch(function () { return {}; }).then(function (d) { throw new Error(d.message || 'The PDF could not be made.'); });
        var cd = r.headers.get('Content-Disposition') || '', m = cd.match(/filename="?([^";]+)"?/);
        return r.blob().then(function (b) { return { blob: b, name: m ? m[1] : 'cards.pdf' }; });
      });
  }
  function stamp() { var d = new Date(); return d.getFullYear() + ('0' + (d.getMonth() + 1)).slice(-2) + ('0' + d.getDate()).slice(-2); }
  /** many cards (ZIP), or a single file when only one comes out */
  function run(items, ids, ctx) {
    ctx = ctx || {};
    var o = options(ctx.panel), raster = o.formats.filter(function (f) { return f !== 'pdf'; }), wantPdf = o.formats.indexOf('pdf') > -1;
    if (!o.formats.length) return Swal.fire('Choose a format', 'Tick PNG, JPG, PDF or EPS.', 'info');
    if (!ids.length) return Swal.fire('Nobody selected', 'Choose at least one participant.', 'info');
    var label = ctx.label || 'cards';
    // only PDF: straight from the server
    if (!raster.length) {
      progress(true, '<i class="bi bi-file-earmark-pdf"></i> Making the PDF…', 0, 0);
      return pdf(ids, o, ctx, ids.length > 1 ? o.pdfPack : 'one').then(function (f) { progress(false); CardExport.save(f.blob, f.name); })
        .catch(function (e) { progress(false); Swal.fire('Not downloaded', e.message, 'warning'); });
    }
    progress(true, '<i class="bi bi-file-earmark-zip"></i> Preparing ' + items.length + ' ' + (items.length === 1 ? 'card' : 'cards') + '…', 0, 1);
    var single = items.length === 1 && raster.length === 1 && !wantPdf && !(items[0].design.double_sided && o.sides === 'both');
    if (single) {
      var it = items[0], side = o.sides === 'back' && it.design.double_sided ? 'back' : 'front';
      return CardExport.file(it.design, side, it.data, raster[0], { dpi: o.dpi, bleed: o.bleed, icons: icons(), base: base, name: it.name })
        .then(function (b) { progress(false); CardExport.save(b, CardExport.fileName(it, side, raster[0], false)); })
        .catch(function (e) { progress(false); Swal.fire('Not downloaded', e.message, 'warning'); });
    }
    return CardExport.bulk(items, { formats: raster, sides: o.sides, dpi: o.dpi, bleed: o.bleed, icons: icons(), base: base }, function (n, total) { progress(true, null, n, total); })
      .then(function (zipBlob) {
        if (!wantPdf) return zipBlob;
        // add the vector PDF to the same ZIP
        return pdf(ids, o, ctx, 'one').then(function (f) {
          return JSZip.loadAsync(zipBlob).then(function (z) { z.file('PDF/' + f.name, f.blob); return z.generateAsync({ type: 'blob' }); });
        });
      })
      .then(function (blob) { progress(false); CardExport.save(blob, label + '-' + stamp() + '.zip'); })
      .catch(function (e) { progress(false); Swal.fire('Not downloaded', e.message || 'Please try again.', 'warning'); });
  }
  /** one participant, one format, from a quick button */
  function one(item, id, fmt, ctx) {
    ctx = ctx || {};
    var o = options(ctx.panel);
    if (fmt === 'pdf') {
      progress(true, '<i class="bi bi-file-earmark-pdf"></i> Making the PDF…', 0, 0);
      return pdf([id], o, ctx, 'one').then(function (f) { progress(false); CardExport.save(f.blob, f.name); }).catch(function (e) { progress(false); Swal.fire('Not downloaded', e.message, 'warning'); });
    }
    var sides = item.design.double_sided && o.sides === 'both' ? ['front', 'back'] : [o.sides === 'back' && item.design.double_sided ? 'back' : 'front'];
    progress(true, '<i class="bi bi-image"></i> Drawing the card…', 0, sides.length);
    var done = 0;
    return sides.reduce(function (p, s) {
      return p.then(function () { return CardExport.file(item.design, s, item.data, fmt, { dpi: o.dpi, bleed: o.bleed, icons: icons(), base: base, name: item.name })
        .then(function (b) { done++; progress(true, null, done, sides.length); CardExport.save(b, CardExport.fileName(item, s, fmt, sides.length > 1)); }); });
    }, Promise.resolve()).then(function () { progress(false); }).catch(function (e) { progress(false); Swal.fire('Not downloaded', e.message, 'warning'); });
  }
  function fetchItems(ids, ctx) {
    return fetch(R.data, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ ids: ids, template_id: ctx.templateId || null, event_id: ctx.eventId || null }) })
      .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.message || 'Could not load the cards.'); return d; }); })
      .then(function (d) { var t = {}; d.templates.forEach(function (x) { t[x.id] = x.design; }); return d.items.map(function (i) { return { id: i.id, name: i.name, code: i.code, design: t[i.template_id], data: i.data }; }); });
  }
  return { options: options, run: run, one: one, fetchItems: fetchItems };
})();
</script>
@endonce
