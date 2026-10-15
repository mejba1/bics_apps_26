@extends('backend.layouts.master')
@section('title')
Certificates
@endsection
@push('styles')
<style>
  .ct-tabs .nav-link { font-weight: 700; }
  .ct-tpl { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
  .ct-tpl label { cursor: pointer; border: 2px solid rgba(0,0,0,.1); border-radius: 12px; padding: 8px; text-align: center; font-size: 12.5px; font-weight: 600; }
  .ct-tpl input { display: none; }
  .ct-tpl input:checked + label { border-color: #1861c5; background: rgba(24,97,197,.06); }
  .ct-mini { height: 62px; border-radius: 6px; margin-bottom: 6px; position: relative; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,.08); }
  .ct-mini.classic { box-shadow: inset 0 0 0 4px #0e4ca1, inset 0 0 0 7px #fff, inset 0 0 0 8px #c9a227; }
  .ct-mini.modern::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 30%; background: #0e4ca1; border-right: 3px solid #b22e6b; }
  .ct-mini.elegant::before { content: ""; position: absolute; left: 0; top: 0; border-top: 30px solid #0e4ca1; border-right: 30px solid transparent; }
  .ct-mini.elegant::after { content: ""; position: absolute; right: 0; bottom: 0; border-bottom: 24px solid #c9a227; border-left: 24px solid transparent; }
  .ct-mini i { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 40%; height: 6px; background: #1d2433; border-radius: 3px; opacity: .6; }
  .ct-mini.modern i { left: 66%; }
  .ct-sec { font-size: 13px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #1861c5; margin: 18px 0 10px; }
  .ct-img { display: flex; align-items: center; gap: 10px; }
  .ct-img img { max-height: 44px; max-width: 120px; border: 1px solid rgba(0,0,0,.1); border-radius: 6px; padding: 3px; background: #fff; }
  .ct-sig { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; padding: 10px 12px; margin-bottom: 10px; }
  .ct-preview { position: sticky; top: 80px; }
  .ct-preview iframe { width: 100%; aspect-ratio: 297 / 210; border: 1px solid rgba(0,0,0,.12); border-radius: 12px; background: #f3f5f9; }
  .ct-kpi { border-radius: 12px; padding: 12px 14px; border: 1px solid rgba(0,0,0,.08); }
  .ct-kpi .n { font-size: 1.5rem; font-weight: 800; line-height: 1; }
  .ct-kpi .l { font-size: 12.5px; color: var(--ad-muted, #6b7489); margin-top: 4px; }
  .ct-rule { display: flex; gap: 10px; padding: 10px 12px; border: 1px solid rgba(0,0,0,.08); border-radius: 12px; margin-bottom: 8px; cursor: pointer; }
  .ct-rule:has(input:checked) { border-color: #1861c5; background: rgba(24,97,197,.06); }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Certificates <span class="text-muted fw-normal fs-5">· {{ $event->event_short }}</span></h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Certificates</li></ol></nav>
  </div>
  <form method="GET" action="{{ route('certificates.index') }}" class="d-flex align-items-center gap-2">
    <label class="small fw-semibold text-nowrap" for="ct-event"><i class="bi bi-calendar-event"></i> Event</label>
    <select class="form-select" id="ct-event" name="event" onchange="this.form.submit()" style="min-width:220px">
      @foreach($events as $ev)<option value="{{ $ev->id }}" @selected($ev->id === $event->id)>{{ $ev->event_short }}{{ $ev->venue_code !== $ev->event_short ? ' · ' . $ev->venue_code : '' }}{{ $ev->is_current ? ' (current)' : '' }}</option>@endforeach
    </select>
  </form>
</div>

<section class="section">
  <ul class="nav nav-tabs ct-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-design" type="button"><i class="bi bi-palette"></i> Design</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-email" type="button"><i class="bi bi-envelope"></i> Email</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-people" type="button" id="tab-people-btn"><i class="bi bi-people"></i> Participants</button></li>
  </ul>

  <div class="tab-content">
    {{-- ================= DESIGN ================= --}}
    <div class="tab-pane fade show active" id="tab-design">
      <div class="row g-4">
        <div class="col-xl-5">
          <form class="card" id="ct-design" novalidate>
            <div class="card-body pt-3">
              <div class="ct-sec mt-0">Template</div>
              <div class="ct-tpl">
                @foreach($templates as $k => $label)
                <div><input type="radio" name="template" id="tpl-{{ $k }}" value="{{ $k }}"><label for="tpl-{{ $k }}"><div class="ct-mini {{ $k }}"><i></i></div>{{ \Illuminate\Support\Str::before($label, ' —') }}</label></div>
                @endforeach
              </div>

              <div class="ct-sec">Colours</div>
              <div class="row g-2">
                @foreach(['primary' => 'Main', 'accent' => 'Accent', 'gold' => 'Gold / lines', 'paper' => 'Paper'] as $k => $label)
                <div class="col-3"><label class="form-label small mb-1" for="c-{{ $k }}">{{ $label }}</label><input type="color" class="form-control form-control-color w-100" id="c-{{ $k }}" name="{{ $k }}"></div>
                @endforeach
              </div>

              <div class="ct-sec">Texts</div>
              <div class="row g-2">
                <div class="col-6"><label class="form-label small mb-1">Title</label><input class="form-control" name="title" maxlength="60"></div>
                <div class="col-6"><label class="form-label small mb-1">Subtitle</label><input class="form-control" name="subtitle" maxlength="80"></div>
                <div class="col-12"><label class="form-label small mb-1">Line above the name</label><input class="form-control" name="presented" maxlength="160"></div>
                <div class="col-12"><label class="form-label small mb-1">Text under the name</label><textarea class="form-control" name="body" rows="3" maxlength="600"></textarea></div>
                <div class="col-12"><label class="form-label small mb-1">Small note (optional)</label><input class="form-control" name="footer_note" maxlength="200" placeholder="e.g. CPD points: 6"></div>
                <div class="col-7"><label class="form-label small mb-1">Name style</label><select class="form-select" name="name_style">@foreach($nameStyles as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="col-5 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="d-title" name="include_title"><label class="form-check-label small" for="d-title">Include Dr / Prof</label></div></div>
              </div>
              <details class="small text-muted mt-2"><summary>Placeholders</summary><div class="mt-1">@foreach($placeholders as $ph => $desc)<code>{{ $ph }}</code> {{ $desc }}<br>@endforeach <code>**text**</code> = bold</div></details>

              <div class="ct-sec">Logos &amp; background</div>
              @foreach(['logo' => 'Main logo', 'logo2' => 'Second logo (partner / organiser)', 'background' => 'Own background artwork (A4 landscape, replaces the template frame)'] as $k => $label)
              <div class="mb-2">
                <label class="form-label small mb-1">{{ $label }}</label>
                <div class="ct-img" data-img="{{ $k }}">
                  <input type="hidden" name="{{ $k }}">
                  <img alt="" style="display:none">
                  <label class="btn btn-sm btn-outline-primary mb-0"><i class="bi bi-upload"></i> Upload<input type="file" accept=".png,.jpg,.jpeg" hidden></label>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-clear>Remove</button>
                </div>
              </div>
              @endforeach

              <div class="ct-sec">Signatures <span class="text-muted fw-normal text-lowercase">(up to 3)</span></div>
              <div id="ct-sigs"></div>
              <button type="button" class="btn btn-sm btn-outline-primary" id="ct-add-sig"><i class="bi bi-plus-lg"></i> Add signature</button>

              <div class="ct-sec">Details</div>
              <div class="row g-2 align-items-end">
                <div class="col-6"><label class="form-label small mb-1">Certificate number prefix</label><input class="form-control" name="number_prefix" maxlength="20"><div class="form-text">e.g. BICS2026 → BICS2026-000001</div></div>
                <div class="col-6">
                  @foreach(['show_number' => 'Show number', 'show_date' => 'Show issue date', 'show_qr' => 'QR code (verify)', 'show_seal' => 'Seal'] as $k => $l)
                  <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="d-{{ $k }}" name="{{ $k }}"><label class="form-check-label small" for="d-{{ $k }}">{{ $l }}</label></div>
                  @endforeach
                </div>
              </div>
            </div>
            <div class="card-footer d-flex flex-wrap gap-2">
              <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Save design</button>
              <button class="btn btn-outline-secondary" type="button" id="ct-reset"><i class="bi bi-arrow-counterclockwise"></i> Default design</button>
            </div>
          </form>
        </div>
        <div class="col-xl-7">
          <div class="ct-preview">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="fw-semibold"><i class="bi bi-eye"></i> Live preview <span class="text-muted fw-normal small" id="ct-prev-state"></span></span>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" type="button" id="ct-refresh"><i class="bi bi-arrow-repeat"></i> Refresh</button>
                <button class="btn btn-sm btn-outline-primary" type="button" id="ct-sample"><i class="bi bi-download"></i> Sample PDF</button>
              </div>
            </div>
            <iframe id="ct-frame" title="Certificate preview"></iframe>
            <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> The preview uses a sample person (Dr Sarah Rahman). Each participant gets their own name, number and QR code.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- ================= EMAIL ================= --}}
    <div class="tab-pane fade" id="tab-email">
      <form class="card" id="ct-email" novalidate style="max-width:900px">
        <div class="card-body pt-3">
          <div class="row g-4">
            <div class="col-md-6">
              <div class="ct-sec mt-0">Sending</div>
              @foreach($modes as $k => $label)<label class="ct-rule"><input class="form-check-input mt-1" type="radio" name="mode" value="{{ $k }}"><span><b>{{ $label }}</b></span></label>@endforeach
            </div>
            <div class="col-md-6">
              <div class="ct-sec mt-0">Who gets a certificate</div>
              @foreach($eligible as $k => $label)<label class="ct-rule"><input class="form-check-input mt-1" type="radio" name="eligible" value="{{ $k }}"><span><b>{{ $label }}</b><br><small class="text-muted">{{ $k === 'checked_in' ? 'Checked in at the registration desk (verify page or on-site registration).' : 'Also people who registered but were not checked in.' }}</small></span></label>@endforeach
            </div>
          </div>
          <div class="ct-sec">Email</div>
          <div class="mb-2"><label class="form-label small mb-1">Subject</label><input class="form-control" name="subject" maxlength="200"></div>
          <div class="mb-2"><label class="form-label small mb-1">Message</label><textarea class="form-control" name="body" rows="5" maxlength="3000"></textarea></div>
          <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="e-attach" name="attach_pdf"><label class="form-check-label" for="e-attach">Attach the certificate PDF (the email always has a download / verify button)</label></div>
          <details class="small text-muted"><summary>Placeholders</summary><div class="mt-1">@foreach($placeholders as $ph => $desc)<code>{{ $ph }}</code> {{ $desc }}<br>@endforeach</div></details>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2">
          <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Save email settings</button>
          <button class="btn btn-outline-secondary" type="button" id="ct-test"><i class="bi bi-send"></i> Send test to {{ Auth::user()->email }}</button>
        </div>
      </form>
    </div>

    {{-- ================= PARTICIPANTS ================= --}}
    <div class="tab-pane fade" id="tab-people">
      <div class="card mb-3">
        <div class="card-body pt-3">
          <div class="fw-bold mb-2"><i class="bi bi-person-check"></i> Send to one participant</div>
          <div class="row g-2 align-items-end">
            <div class="col-lg-5 position-relative">
              <label class="form-label small mb-1" for="one-search">Find the participant</label>
              <input class="form-control" id="one-search" autocomplete="off" placeholder="Name, registration number or email…">
              <div class="list-group position-absolute w-100 shadow-sm d-none" id="one-results" style="z-index:20;max-height:260px;overflow-y:auto"></div>
            </div>
            <div class="col-lg-4">
              <label class="form-label small mb-1" for="one-to">Send to <span class="text-muted">(optional other address)</span></label>
              <input type="email" class="form-control" id="one-to" placeholder="Their registered email" maxlength="190">
            </div>
            <div class="col-lg-3 d-flex gap-2">
              <button class="btn btn-primary flex-grow-1" type="button" id="one-send" disabled><i class="bi bi-send"></i> Send certificate</button>
              <a class="btn btn-outline-secondary" id="one-pdf" href="#" title="Download PDF" style="pointer-events:none;opacity:.5"><i class="bi bi-download"></i></a>
            </div>
          </div>
          <div class="small mt-2" id="one-picked"><span class="text-muted">Pick someone from the list: works even if they were not checked in or emails are off (you will be asked to confirm).</span></div>
        </div>
      </div>
      <div class="row g-2 mb-3" id="ct-kpis"></div>
      <div class="card">
        <div class="card-body pt-3">
          <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
            <div class="d-flex flex-wrap gap-2">
              <input type="search" class="form-control form-control-sm" id="p-search" placeholder="Search name, email, number…" style="width:240px">
              <select class="form-select form-select-sm" id="p-filter" style="width:auto">
                <option value="eligible">Gets a certificate</option><option value="">Everyone registered</option><option value="checked">Checked in</option>
                <option value="pending">Not emailed yet</option><option value="sent">Emailed</option><option value="failed">Email failed</option>
                <option value="assigned">On participant dashboard</option><option value="unassigned">Not on dashboard</option>
              </select>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <div class="btn-group btn-group-sm">
                <button class="btn btn-primary" id="p-assign-selected" disabled title="Show the certificate on the participant's dashboard (My events)"><i class="bi bi-person-check"></i> Assign to dashboard (<span>0</span>)</button>
                <button class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" id="p-assign-more" disabled></button>
                <ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item text-danger" id="p-unassign-selected"><i class="bi bi-person-dash"></i> Remove from dashboard</button></li></ul>
              </div>
              <button class="btn btn-sm btn-outline-primary" id="p-send-selected" disabled><i class="bi bi-send"></i> Email selected (<span>0</span>)</button>
              <button class="btn btn-sm btn-success" id="p-send-all"><i class="bi bi-send-check"></i> Send to all not emailed yet</button>
            </div>
          </div>
          <div class="progress mb-2 d-none" id="p-progress" style="height:22px"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%</div></div>
          <div class="table-responsive" style="max-height:620px;overflow-y:auto">
            <table class="table table-sm align-middle mb-0">
              <thead class="sticky-top bg-body"><tr><th style="width:30px"><input type="checkbox" class="form-check-input" id="p-all"></th><th>Participant</th><th>Attendance</th><th>Certificate</th><th>Dashboard</th><th>Email</th><th class="text-end">Action</th></tr></thead>
              <tbody id="p-body"><tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';
  var EVENT_ID = @json($event->id);
  var design = @json($design), defaults = @json($defaults), email = @json($email);
  var base = @json(url('/certificates'));
  var ASSET = @json(rtrim(asset('/'), '/'));
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2500 });
  function q(u) { return u + (u.indexOf('?') < 0 ? '?' : '&') + 'event=' + EVENT_ID; }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  function post(url, body, isForm) {
    return fetch(q(url), { method: 'POST', headers: Object.assign({ 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, isForm ? {} : { 'Content-Type': 'application/json' }), body: isForm ? body : JSON.stringify(body || {}) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); });
  }
  function fail(res, title) { var e = res.d.errors || {}; var k = Object.keys(e)[0]; Swal.fire(title || 'Not saved', k ? e[k][0] : (res.d.message || 'Please try again.'), 'warning'); }

  /* ---------------- Design ---------------- */
  var form = document.getElementById('ct-design');
  var sigs = [];
  function fillDesign(d) {
    form.querySelectorAll('input[name=template]').forEach(function (r) { r.checked = r.value === d.template; });
    ['primary', 'accent', 'gold', 'paper', 'title', 'subtitle', 'presented', 'body', 'footer_note', 'name_style', 'number_prefix'].forEach(function (k) { form.elements[k].value = d[k] || ''; });
    ['include_title', 'show_number', 'show_date', 'show_qr', 'show_seal'].forEach(function (k) { form.elements[k].checked = d[k] === '1'; });
    ['logo', 'logo2', 'background'].forEach(function (k) { setImg(form.querySelector('[data-img="' + k + '"]'), d[k]); });
    sigs = (d.signatures || []).map(function (s) { return { name: s.name, role: s.role, image: s.image }; });
    renderSigs();
  }
  function setImg(box, path) {
    box.querySelector('input[type=hidden]').value = path || '';
    var img = box.querySelector('img');
    img.style.display = path ? '' : 'none';
    if (path) img.src = ASSET + '/' + path;
  }
  function upload(file, done) {
    var fd = new FormData(); fd.append('image', file);
    post(base + '/upload', fd, true).then(function (res) { if (!res.ok) return fail(res, 'Not uploaded'); done(res.d.path); });
  }
  form.querySelectorAll('[data-img]').forEach(function (box) {
    box.querySelector('input[type=file]').addEventListener('change', function () {
      var f = this.files[0]; if (!f) return;
      upload(f, function (path) { setImg(box, path); preview(); });
      this.value = '';
    });
    box.querySelector('[data-clear]').addEventListener('click', function () { setImg(box, ''); preview(); });
  });
  function renderSigs() {
    var wrap = document.getElementById('ct-sigs');
    wrap.innerHTML = sigs.map(function (s, i) {
      return '<div class="ct-sig" data-i="' + i + '"><div class="row g-2 align-items-end">' +
        '<div class="col-6"><label class="form-label small mb-1">Name</label><input class="form-control form-control-sm" data-k="name" value="' + esc(s.name) + '" maxlength="80" placeholder="e.g. Prof. Dr. A. Rahman"></div>' +
        '<div class="col-6"><label class="form-label small mb-1">Role</label><input class="form-control form-control-sm" data-k="role" value="' + esc(s.role) + '" maxlength="100"></div>' +
        '<div class="col-12 d-flex align-items-center gap-2 mt-1">' + (s.image ? '<img src="' + ASSET + '/' + esc(s.image) + '" style="max-height:34px;border:1px solid rgba(0,0,0,.1);border-radius:6px;padding:2px;background:#fff">' : '<span class="small text-muted">No signature image</span>') +
        '<label class="btn btn-sm btn-outline-primary mb-0"><i class="bi bi-pen"></i> ' + (s.image ? 'Change' : 'Upload signature') + '<input type="file" accept=".png,.jpg,.jpeg" hidden data-up></label>' +
        (s.image ? '<button type="button" class="btn btn-sm btn-link p-0" data-noimg>Remove image</button>' : '') +
        '<button type="button" class="btn btn-sm btn-link text-danger ms-auto p-0" data-del>Delete</button></div></div></div>';
    }).join('') || '<div class="small text-muted mb-2">No signatures.</div>';
    wrap.querySelectorAll('.ct-sig').forEach(function (el) {
      var i = parseInt(el.dataset.i, 10);
      el.querySelectorAll('[data-k]').forEach(function (inp) { inp.addEventListener('input', function () { sigs[i][inp.dataset.k] = inp.value; preview(); }); });
      el.querySelector('[data-up]').addEventListener('change', function () { var f = this.files[0]; if (f) upload(f, function (p) { sigs[i].image = p; renderSigs(); preview(); }); });
      var noimg = el.querySelector('[data-noimg]'); if (noimg) noimg.addEventListener('click', function () { sigs[i].image = ''; renderSigs(); preview(); });
      el.querySelector('[data-del]').addEventListener('click', function () { sigs.splice(i, 1); renderSigs(); preview(); });
    });
    document.getElementById('ct-add-sig').disabled = sigs.length >= 3;
  }
  document.getElementById('ct-add-sig').addEventListener('click', function () { if (sigs.length < 3) { sigs.push({ name: '', role: '', image: '' }); renderSigs(); } });
  function values() {
    var v = {};
    var t = form.querySelector('input[name=template]:checked'); v.template = t ? t.value : 'classic';
    ['primary', 'accent', 'gold', 'paper', 'title', 'subtitle', 'presented', 'body', 'footer_note', 'name_style', 'number_prefix', 'logo', 'logo2', 'background'].forEach(function (k) { v[k] = form.elements[k].value; });
    ['include_title', 'show_number', 'show_date', 'show_qr', 'show_seal'].forEach(function (k) { v[k] = form.elements[k].checked ? '1' : '0'; });
    v.signatures = sigs;
    return v;
  }
  var timer = null, lastUrl = null;
  function preview() {
    clearTimeout(timer);
    document.getElementById('ct-prev-state').textContent = '· updating…';
    timer = setTimeout(function () {
      fetch(q(base + '/preview'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }, body: JSON.stringify({ design: values() }) })
        .then(function (r) { if (!r.ok) throw r.status; return r.blob(); })
        .then(function (blob) {
          if (lastUrl) URL.revokeObjectURL(lastUrl);
          lastUrl = URL.createObjectURL(blob);
          document.getElementById('ct-frame').src = lastUrl + '#toolbar=0&navpanes=0&view=Fit';
          document.getElementById('ct-prev-state').textContent = '';
        }).catch(function (s) { document.getElementById('ct-prev-state').textContent = s === 419 ? '· session expired, reload the page' : '· could not update'; });
    }, 700);
  }
  form.addEventListener('input', function (e) { if (!e.target.closest('.ct-sig')) preview(); });
  form.addEventListener('change', function (e) { if (e.target.type === 'radio' || e.target.type === 'checkbox' || e.target.tagName === 'SELECT') preview(); });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    post(base + '/design', { design: values() }).then(function (res) {
      if (!res.ok) return fail(res);
      design = res.d.design; toast.fire({ icon: 'success', title: res.d.message });
    });
  });
  document.getElementById('ct-reset').addEventListener('click', function () {
    Swal.fire({ icon: 'question', title: 'Go back to the default design?', text: 'Your colours, texts, logos and signatures for this event will be replaced.', showCancelButton: true, confirmButtonText: 'Use default' })
      .then(function (r) { if (r.isConfirmed) post(base + '/design/reset').then(function (res) { if (res.ok) { design = res.d.design; fillDesign(design); preview(); toast.fire({ icon: 'success', title: res.d.message }); } }); });
  });
  document.getElementById('ct-refresh').addEventListener('click', preview);
  document.getElementById('ct-sample').addEventListener('click', function () { if (lastUrl) { var a = document.createElement('a'); a.href = lastUrl; a.download = 'certificate-sample.pdf'; a.click(); } });

  /* ---------------- Email ---------------- */
  var emailForm = document.getElementById('ct-email');
  function fillEmail(e) {
    emailForm.querySelectorAll('input[name=mode]').forEach(function (r) { r.checked = r.value === e.mode; });
    emailForm.querySelectorAll('input[name=eligible]').forEach(function (r) { r.checked = r.value === e.eligible; });
    var f = emailForm.elements; f.subject.value = e.subject; f.body.value = e.body; f.attach_pdf.checked = e.attach_pdf === '1';
  }
  function emailValues() {
    var m = emailForm.querySelector('input[name=mode]:checked'), el = emailForm.querySelector('input[name=eligible]:checked');
    return { mode: m ? m.value : 'manual', eligible: el ? el.value : 'checked_in', subject: emailForm.elements.subject.value, body: emailForm.elements.body.value, attach_pdf: emailForm.elements.attach_pdf.checked };
  }
  emailForm.addEventListener('submit', function (e) {
    e.preventDefault();
    post(base + '/email', emailValues()).then(function (res) {
      if (!res.ok) return fail(res);
      email = res.d.email; toast.fire({ icon: 'success', title: res.d.message }); peopleLoaded = false;
    });
  });
  document.getElementById('ct-test').addEventListener('click', function () {
    var btn = this; btn.disabled = true;
    post(base + '/email/test', emailValues()).then(function (res) { btn.disabled = false; if (res.ok) toast.fire({ icon: 'success', title: res.d.message }); else fail(res, 'Not sent'); });
  });

  /* ---------------- Participants ---------------- */
  var people = [], peopleLoaded = false, selected = {};
  function loadPeople() {
    peopleLoaded = true;
    fetch(q(base + '/participants'), { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); })
      .then(function (d) { people = d.participants; email = d.email; renderPeople(); });
  }
  document.getElementById('tab-people-btn').addEventListener('shown.bs.tab', function () { if (!peopleLoaded) loadPeople(); });
  function kpis() {
    var el = people.filter(function (p) { return p.eligible; });
    var items = [
      ['Registered', people.length, ''], ['Checked in', people.filter(function (p) { return p.checked_in; }).length, 'text-info'],
      ['Get a certificate', el.length, 'text-primary'], ['Emailed', people.filter(function (p) { return p.emailed_at; }).length, 'text-success'],
      ['On dashboards', people.filter(function (p) { return p.assigned_at; }).length, 'text-primary'], ['Failed', people.filter(function (p) { return p.error; }).length, 'text-danger']
    ];
    document.getElementById('ct-kpis').innerHTML = items.map(function (k) { return '<div class="col-6 col-md-2"><div class="ct-kpi"><div class="n ' + k[2] + '">' + k[1] + '</div><div class="l">' + k[0] + '</div></div></div>'; }).join('');
  }
  function visible() {
    var s = document.getElementById('p-search').value.trim().toLowerCase(), f = document.getElementById('p-filter').value;
    return people.filter(function (p) {
      if (s && [p.name, p.email, p.pin, p.number, p.country].join(' ').toLowerCase().indexOf(s) < 0) return false;
      if (f === 'eligible') return p.eligible; if (f === 'checked') return p.checked_in; if (f === 'pending') return p.eligible && !p.emailed_at;
      if (f === 'sent') return !!p.emailed_at; if (f === 'failed') return !!p.error;
      if (f === 'assigned') return !!p.assigned_at; if (f === 'unassigned') return !p.assigned_at;
      return true;
    });
  }
  function renderPeople() {
    kpis();
    var off = email.mode === 'off';
    var rows = visible();
    document.getElementById('p-body').innerHTML = rows.slice(0, 1500).map(function (p) {
      var mail = p.emailed_at ? '<span class="text-success small"><i class="bi bi-envelope-check"></i> ' + esc(p.emailed_at) + '</span>'
        : p.error ? '<span class="text-danger small" title="' + esc(p.error) + '"><i class="bi bi-envelope-exclamation"></i> Failed</span>'
        : !p.valid_email ? '<span class="text-muted small"><i class="bi bi-envelope-dash"></i> No valid email</span>' : '<span class="text-muted small">Not emailed</span>';
      return '<tr><td><input type="checkbox" class="form-check-input" data-sel="' + p.id + '" ' + (selected[p.id] ? 'checked' : '') + '></td>' +
        '<td><div class="fw-semibold">' + esc(p.name) + '</div><div class="small text-muted">#' + esc(p.pin) + (p.country ? ' · ' + esc(p.country) : '') + (p.email ? ' · ' + esc(p.email) : '') + '</div></td>' +
        '<td>' + (p.checked_in ? '<span class="badge bg-success">Checked in</span>' : '<span class="badge bg-light text-dark border">Not checked in</span>') + (!p.eligible ? '<div class="small text-muted mt-1">Not attended — no automatic certificate (you can still assign one)</div>' : '') + '</td>' +
        '<td>' + (p.number ? '<span class="fw-semibold small">' + esc(p.number) + '</span>' + (p.downloads ? '<div class="small text-muted">' + p.downloads + ' download' + (p.downloads > 1 ? 's' : '') + '</div>' : '') : '<span class="small text-muted">Not issued yet</span>') + '</td>' +
        '<td>' + (p.assigned_at ? '<span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-person-check"></i> Assigned</span><div class="small text-muted">' + esc(p.assigned_at) + '</div>'
          : '<span class="small text-muted">Not assigned</span>') + '</td>' +
        '<td>' + mail + '</td>' +
        '<td class="text-end text-nowrap">' +
          '<a class="btn btn-sm btn-outline-secondary" href="' + q(base + '/' + p.id + '/pdf') + '" title="Download PDF"><i class="bi bi-download"></i></a> ' +
          (p.link ? '<a class="btn btn-sm btn-outline-secondary" href="' + esc(p.link) + '" target="_blank" title="Public verification page"><i class="bi bi-box-arrow-up-right"></i></a> ' : '') +
          '<button class="btn btn-sm ' + (p.assigned_at ? 'btn-outline-danger' : 'btn-outline-primary') + '" data-assign="' + p.id + '" data-on="' + (p.assigned_at ? 0 : 1) + '" title="' + (p.assigned_at ? 'Remove from participant dashboard' : 'Assign: show on participant dashboard') + '"><i class="bi bi-' + (p.assigned_at ? 'person-dash' : 'person-check') + '"></i></button> ' +
          '<button class="btn btn-sm ' + (p.emailed_at ? 'btn-outline-secondary' : 'btn-outline-success') + '" data-send="' + p.id + '" ' + (!p.valid_email ? 'disabled' : '') +
            ' title="' + (!p.valid_email ? 'No valid email address' : p.emailed_at ? 'Send again' : 'Send certificate') + '"><i class="bi bi-' + (p.emailed_at ? 'arrow-repeat' : 'send') + '"></i></button></td></tr>';
    }).join('') || '<tr><td colspan="7" class="text-center text-muted py-4">Nobody here.</td></tr>';
    document.querySelectorAll('[data-assign]').forEach(function (b) { b.addEventListener('click', function () { assignIds([+b.dataset.assign], b.dataset.on === '1', true); }); });
    document.querySelectorAll('[data-sel]').forEach(function (cb) { cb.addEventListener('change', function () { if (cb.checked) selected[cb.dataset.sel] = true; else delete selected[cb.dataset.sel]; selCount(); }); });
    document.querySelectorAll('[data-send]').forEach(function (b) { b.addEventListener('click', function () { sendOne(people.filter(function (x) { return String(x.id) === b.dataset.send; })[0], null); }); });
    document.getElementById('p-send-all').disabled = off;
    selCount();
  }
  function selCount() {
    var ids = Object.keys(selected), n = ids.length, b = document.getElementById('p-send-selected');
    var withEmail = people.filter(function (p) { return selected[p.id] && p.valid_email; }).length;
    b.querySelector('span').textContent = withEmail; b.disabled = !withEmail || email.mode === 'off';
    var a = document.getElementById('p-assign-selected'); a.querySelector('span').textContent = n; a.disabled = !n;
    document.getElementById('p-assign-more').disabled = !n;
  }
  document.getElementById('p-all').addEventListener('change', function () {
    var on = this.checked;
    visible().forEach(function (p) { if (on) selected[p.id] = true; else delete selected[p.id]; });
    renderPeople();
  });
  document.getElementById('p-search').addEventListener('input', renderPeople);
  document.getElementById('p-filter').addEventListener('change', renderPeople);

  function sendIds(ids, confirmText) {
    if (!ids.length) return Swal.fire('Nobody to send to', 'Everyone eligible already has their certificate email.', 'info');
    Swal.fire({ icon: 'question', title: 'Email ' + ids.length + ' certificate' + (ids.length > 1 ? 's' : '') + '?', text: confirmText || '', showCancelButton: true, confirmButtonText: 'Send' })
      .then(function (r) {
        if (!r.isConfirmed) return;
        var bar = document.getElementById('p-progress'), fill = bar.querySelector('.progress-bar');
        bar.classList.remove('d-none');
        var done = 0, sent = 0, failed = 0, chunks = [];
        for (var i = 0; i < ids.length; i += 10) chunks.push(ids.slice(i, i + 10));
        (function next() {
          if (!chunks.length) {
            bar.classList.add('d-none'); selected = {}; loadPeople();
            return Swal.fire({ icon: failed ? 'warning' : 'success', title: sent + ' sent' + (failed ? ', ' + failed + ' failed' : ''), text: failed ? 'Use the "Email failed" filter to see why.' : '' });
          }
          var chunk = chunks.shift();
          post(base + '/send', { ids: chunk }).then(function (res) {
            done += chunk.length;
            if (res.ok) { sent += res.d.sent; failed += Object.keys(res.d.failed || {}).length; } else failed += chunk.length;
            var pct = Math.round(done / ids.length * 100); fill.style.width = pct + '%'; fill.textContent = done + ' / ' + ids.length;
            next();
          }).catch(function () { failed += chunk.length; done += chunk.length; next(); });
        })();
      });
  }
  // ---- One specific participant ----
  var picked = null;
  var oneSearch = document.getElementById('one-search'), oneResults = document.getElementById('one-results');
  function pick(p) {
    picked = p;
    oneSearch.value = p.name + ' (#' + p.pin + ')';
    oneResults.classList.add('d-none');
    document.getElementById('one-to').placeholder = p.email || 'No registered email: type an address';
    document.getElementById('one-send').disabled = false;
    var pdf = document.getElementById('one-pdf'); pdf.href = q(base + '/' + p.id + '/pdf'); pdf.style.pointerEvents = ''; pdf.style.opacity = '';
    document.getElementById('one-picked').innerHTML = '<b>' + esc(p.name) + '</b> · ' + (p.checked_in ? '<span class="text-success">checked in</span>' : '<span class="text-warning-emphasis">not checked in</span>') +
      (p.number ? ' · certificate ' + esc(p.number) : '') + (p.emailed_at ? ' · <span class="text-success">emailed ' + esc(p.emailed_at) + '</span>' : '') + (p.error ? ' · <span class="text-danger">last email failed</span>' : '');
  }
  oneSearch.addEventListener('focus', function () { if (!peopleLoaded) loadPeople(); });
  oneSearch.addEventListener('input', function () {
    picked = null; document.getElementById('one-send').disabled = true;
    var s = this.value.trim().toLowerCase();
    if (s.length < 2) { oneResults.classList.add('d-none'); return; }
    var hits = people.filter(function (p) { return [p.name, p.pin, p.email].join(' ').toLowerCase().indexOf(s) > -1; }).slice(0, 12);
    oneResults.innerHTML = hits.map(function (p) {
      return '<button type="button" class="list-group-item list-group-item-action" data-pick="' + p.id + '"><div class="fw-semibold">' + esc(p.name) + '</div><div class="small text-muted">#' + esc(p.pin) + (p.email ? ' · ' + esc(p.email) : '') + (p.checked_in ? ' · checked in' : '') + '</div></button>';
    }).join('') || '<div class="list-group-item small text-muted">No participant found for this event.</div>';
    oneResults.classList.remove('d-none');
    oneResults.querySelectorAll('[data-pick]').forEach(function (b) { b.addEventListener('click', function () { pick(people.filter(function (p) { return String(p.id) === b.dataset.pick; })[0]); }); });
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('#one-results') && e.target !== oneSearch) oneResults.classList.add('d-none'); });
  document.getElementById('one-send').addEventListener('click', function () { if (picked) sendOne(picked, document.getElementById('one-to').value.trim() || null); });

  function sendOne(p, to) {
    if (!p) return;
    var address = to || p.email;
    var notes = [];
    if (!p.checked_in) notes.push('They were <b>not checked in</b> (did not attend).');
    if (email.mode === 'off') notes.push('Certificate emails are <b>off</b> for this event; this sends anyway.');
    if (p.emailed_at && !to) notes.push('They already received it on ' + esc(p.emailed_at) + '.');
    Swal.fire({ icon: notes.length ? 'warning' : 'question', title: 'Send the certificate to ' + esc(p.name) + '?',
      html: 'To <b>' + esc(address || '—') + '</b>' + (notes.length ? '<br><br>' + notes.join('<br>') : ''), showCancelButton: true, confirmButtonText: 'Send' })
      .then(function (r) {
        if (!r.isConfirmed) return;
        post(base + '/send-one', { registration_id: p.id, to: to }).then(function (res) {
          if (!res.ok) return fail(res, 'Not sent');
          toast.fire({ icon: 'success', title: res.d.message });
          loadPeople();
          if (picked && picked.id === p.id) { document.getElementById('one-to').value = ''; }
        });
      });
  }

  document.getElementById('p-send-selected').addEventListener('click', function () { sendIds(people.filter(function (p) { return selected[p.id] && p.valid_email; }).map(function (p) { return p.id; })); });

  // assign / remove: the certificate shows on (or leaves) the participant's dashboard, under this event
  function assignIds(ids, on, quiet) {
    function go() {
      post(base + '/assign?event=' + EVENT_ID, { ids: ids, assign: on }).then(function (res) {
        if (!res.ok) return fail(res, 'Not done');
        toast.fire({ icon: 'success', title: res.d.message });
        if (!quiet) selected = {};
        loadPeople();
      });
    }
    if (quiet) return go();
    var notIn = people.filter(function (p) { return ids.indexOf(p.id) >= 0 && on && !p.checked_in; }).length;
    Swal.fire({ icon: 'question', title: (on ? 'Assign ' : 'Remove ') + ids.length + ' certificate' + (ids.length > 1 ? 's' : '') + (on ? '?' : ' from dashboards?'),
      html: on ? 'They will appear in each participant\'s dashboard under <b>' + esc(@json($event->event_short)) + '</b>. No email is sent.' + (notIn ? '<div class="small text-warning mt-2">' + notIn + ' of them did not check in.</div>' : '')
        : 'The certificates stay valid (numbers and emailed links keep working) but no longer show on the dashboards.',
      showCancelButton: true, confirmButtonText: on ? 'Assign' : 'Remove', confirmButtonColor: on ? undefined : '#d33' }).then(function (r) { if (r.isConfirmed) go(); });
  }
  document.getElementById('p-assign-selected').addEventListener('click', function () { assignIds(Object.keys(selected).map(Number), true); });
  document.getElementById('p-unassign-selected').addEventListener('click', function () { assignIds(Object.keys(selected).map(Number), false); });
  document.getElementById('p-send-all').addEventListener('click', function () {
    sendIds(people.filter(function (p) { return p.eligible && !p.emailed_at && p.valid_email; }).map(function (p) { return p.id; }), 'Everyone who gets a certificate and has not been emailed yet.');
  });

  fillDesign(design);
  fillEmail(email);
  preview();
})();
</script>
@endpush
