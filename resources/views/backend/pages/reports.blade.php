@extends('backend.layouts.master')
@section('title')
Reports
@endsection
@push('styles')
<style>
  .rp-pin { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px 24px; background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 16px; padding: 18px 20px; margin-bottom: 22px; }
  .rp-pin .lbl { font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ad-muted); }
  .rp-pin .ev { font-size: 22px; font-weight: 800; color: var(--ad-ink); display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .rp-pin .ev .bi { color: var(--ad-accent); }
  .rp-pin .tag { font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 999px; background: var(--ad-green-50); color: var(--ad-green); }
  .rp-pin .tag.muted { background: var(--ad-chip); color: var(--ad-muted); }
  .rp-pin .meta { color: var(--ad-muted); font-size: 13.5px; margin-top: 2px; }
  .rp-pin form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  .rp-pin select { min-width: 220px; }
  .rp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 18px; }
  .rp-card { background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 16px; padding: 18px; display: flex; flex-direction: column; gap: 10px; }
  .rp-card .ic { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-size: 20px; background: var(--ad-brand-50); color: var(--ad-brand); }
  .rp-card .ic.green { background: var(--ad-green-50); color: var(--ad-green); }
  .rp-card .ic.amber { background: var(--ad-amber-50); color: var(--ad-amber); }
  .rp-card .ic.teal { background: var(--ad-teal-50); color: var(--ad-teal); }
  .rp-card .ic.accent { background: var(--ad-accent-50); color: var(--ad-accent); }
  .rp-card h3 { font-size: 16px; font-weight: 800; margin: 0; color: var(--ad-ink); }
  .rp-card p { font-size: 13.5px; color: var(--ad-muted); margin: 0; flex: 1; }
  .rp-card .n { font-size: 13px; font-weight: 700; color: var(--ad-ink); }
  .rp-card .n span { color: var(--ad-muted); font-weight: 500; }
  .rp-card .btn { align-self: flex-start; }
  .rp-card.disabled { opacity: .55; }
  .rp-note { font-size: 13px; color: var(--ad-muted); margin-top: 18px; }
  .rp-h2 { font-size: 17px; font-weight: 800; color: var(--ad-ink); margin: 30px 0 14px; display: flex; align-items: center; gap: 8px; }
  .rp-h2 .bi { color: var(--ad-brand); }
  .rp-quick { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; }
  .rp-q { background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 14px; padding: 14px; display: flex; flex-direction: column; gap: 12px; }
  .rp-q-head { display: flex; gap: 12px; align-items: center; }
  .rp-q-head > .bi { width: 38px; height: 38px; flex: 0 0 38px; border-radius: 10px; display: grid; place-items: center; background: var(--ad-brand-50); color: var(--ad-brand); font-size: 17px; }
  .rp-q-head b { display: block; color: var(--ad-ink); font-size: 14.5px; }
  .rp-q-head small { color: var(--ad-muted); font-size: 12.5px; }
  .rp-q-actions { display: flex; gap: 6px; flex-wrap: wrap; }
  .rp-q-actions .btn-light { background: var(--ad-chip); border-color: transparent; color: var(--ad-ink); }
  .rp-builder { background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 16px; padding: 18px 20px; }
  .rp-builder .form-label { font-weight: 600; font-size: 13px; margin-bottom: 4px; }
  .rp-builder-foot { display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: center; border-top: 1px solid var(--ad-line); margin-top: 18px; padding-top: 16px; }
  .rp-result { background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 16px; margin-top: 14px; overflow: hidden; }
  .rp-result-head { padding: 14px 18px; border-bottom: 1px solid var(--ad-line); }
  .rp-scope { font-weight: 700; color: var(--ad-brand); font-size: 13.5px; }
  .rp-kpis { display: flex; flex-wrap: wrap; gap: 6px 18px; margin-top: 6px; font-size: 13px; color: var(--ad-muted); }
  .rp-kpis b { color: var(--ad-ink); font-size: 15px; }
  .rp-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--ad-muted); background: var(--ad-subtle, transparent); white-space: nowrap; }
  .rp-table td { vertical-align: middle; }
  .rp-table tfoot td { font-weight: 800; border-top: 2px solid var(--ad-line); }
  .rp-share { height: 8px; border-radius: 4px; background: var(--ad-chip); overflow: hidden; }
  .rp-share span { display: block; height: 100%; background: var(--ad-brand); border-radius: 4px; }
  .rp-result.loading { opacity: .55; }
  .rp-empty { text-align: center; color: var(--ad-muted); padding: 28px !important; }
</style>
@endpush
@section('content')
@php
  $dl = fn (array $q) => route('reports.download', $q);
  $notIn = $counts['total'] - $counts['checked_in'];
@endphp
<div class="pagetitle">
  <h1>Reports</h1>
  <nav>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li>
      <li class="breadcrumb-item active">Reports</li>
    </ol>
  </nav>
</div>

<section class="section">
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="status"><i class="bi bi-check-circle"></i> {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
  @endif
  {{-- Pinned event --}}
  <div class="rp-pin">
    <div>
      <div class="lbl">Reports are for your pinned event</div>
      <div class="ev"><i class="bi bi-pin-angle-fill"></i> {{ $pinned }}
        @if($pinned === $current)<span class="tag">Current event</span>@else<span class="tag muted">Past event</span>@endif
      </div>
      <div class="meta">{{ number_format($counts['total']) }} registrations · {{ number_format($counts['checked_in']) }} checked in · {{ $countries->count() }} {{ $countries->count() === 1 ? 'country' : 'countries' }}</div>
    </div>
    <form method="POST" action="{{ route('events.pin') }}">
      @csrf
      <label class="visually-hidden" for="rp-venue">Pin another event</label>
      <select class="form-select" id="rp-venue" name="venue">
        @foreach($venues as $v)
          <option value="{{ $v->venue }}" @selected($v->venue === $pinned)>{{ $v->venue }} ({{ $v->total }}){{ $v->venue === $current ? ' · current' : '' }}</option>
        @endforeach
      </select>
      <button class="btn btn-outline-primary"><i class="bi bi-pin-angle"></i> Pin event</button>
    </form>
  </div>

  <div class="rp-grid">
    <div class="rp-card {{ $counts['total'] ? '' : 'disabled' }}">
      <div class="ic"><i class="bi bi-people-fill"></i></div>
      <h3>All registrations</h3>
      <p>Everyone registered for {{ $pinned }}, with a summary of gender, countries, professions and check-in.</p>
      <div class="n">{{ number_format($counts['total']) }} <span>people</span></div>
      <a class="btn btn-primary" href="{{ $dl(['venue' => 'pinned']) }}"><i class="bi bi-download"></i> Download PDF</a>
    </div>

    <div class="rp-card {{ $counts['checked_in'] ? '' : 'disabled' }}">
      <div class="ic green"><i class="bi bi-person-check-fill"></i></div>
      <h3>Checked in</h3>
      <p>Attendees verified at the registration desk.</p>
      <div class="n">{{ number_format($counts['checked_in']) }} <span>people</span></div>
      <a class="btn btn-outline-primary" href="{{ $dl(['venue' => 'pinned', 'status' => 'checked_in']) }}"><i class="bi bi-download"></i> Download PDF</a>
    </div>

    <div class="rp-card {{ $notIn ? '' : 'disabled' }}">
      <div class="ic amber"><i class="bi bi-hourglass-split"></i></div>
      <h3>Not checked in yet</h3>
      <p>Registered but not seen at the desk: useful for reminders and no-shows.</p>
      <div class="n">{{ number_format($notIn) }} <span>people</span></div>
      <a class="btn btn-outline-primary" href="{{ $dl(['venue' => 'pinned', 'status' => 'not_checked_in']) }}"><i class="bi bi-download"></i> Download PDF</a>
    </div>

    <div class="rp-card {{ $countries->isEmpty() ? 'disabled' : '' }}">
      <div class="ic teal"><i class="bi bi-globe2"></i></div>
      <h3>By country</h3>
      <p>Registrations from one country.</p>
      <form method="GET" action="{{ route('reports.download') }}" class="d-flex flex-wrap gap-2">
        <input type="hidden" name="venue" value="pinned">
        <label class="visually-hidden" for="rp-country">Country</label>
        <select class="form-select" id="rp-country" name="country" style="flex:1;min-width:150px" @disabled($countries->isEmpty())>
          @foreach($countries as $c)<option value="{{ $c->country }}">{{ $c->country }} ({{ $c->n }})</option>@endforeach
        </select>
        <button class="btn btn-outline-primary" @disabled($countries->isEmpty())><i class="bi bi-download"></i> PDF</button>
      </form>
    </div>

    <div class="rp-card">
      <div class="ic accent"><i class="bi bi-collection-fill"></i></div>
      <h3>Every event</h3>
      <p>All registrations from every event, in one report.</p>
      <div class="n">{{ number_format($counts['all']) }} <span>people · {{ $venues->where('total', '>', 0)->count() }} events</span></div>
      <a class="btn btn-outline-secondary" href="{{ $dl(['venue' => 'all']) }}"><i class="bi bi-download"></i> Download PDF</a>
    </div>
  </div>

  {{-- One-click summaries --}}
  <h2 class="rp-h2"><i class="bi bi-bar-chart-line"></i> Summary reports</h2>
  <div class="rp-quick">
    @foreach([
      ['date', 'Date-wise', 'bi-calendar3', 'pinned', 'Registrations per day'],
      ['month', 'Month-wise', 'bi-calendar-month', 'pinned', 'Registrations per month'],
      ['year', 'Year-wise', 'bi-calendar-range', 'all', 'All events, per year'],
      ['venue', 'Event-wise', 'bi-geo-alt', 'all', 'All events side by side'],
      ['gender', 'Male / Female', 'bi-gender-ambiguous', 'pinned', 'Gender split'],
      ['country', 'Country-wise', 'bi-globe2', 'pinned', 'Registrations per country'],
      ['profession', 'Profession-wise', 'bi-briefcase', 'pinned', 'Doctors, nurses, pharmacists…'],
      ['status', 'Check-in', 'bi-person-check', 'pinned', 'Checked in vs not yet'],
    ] as [$by, $name, $icon, $v, $desc])
      <div class="rp-q">
        <div class="rp-q-head"><i class="bi {{ $icon }}"></i><div><b>{{ $name }}</b><small>{{ $desc }}{{ $v === 'pinned' ? ' · ' . $pinned : '' }}</small></div></div>
        <div class="rp-q-actions">
          <button type="button" class="btn btn-sm btn-light rp-show" data-by="{{ $by }}" data-venue="{{ $v }}" title="Show in the report builder"><i class="bi bi-eye"></i> View</button>
          <a class="btn btn-sm btn-outline-primary" href="{{ route('reports.download', ['type' => 'summary', 'by' => $by, 'venue' => $v]) }}"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
          <a class="btn btn-sm btn-outline-success" href="{{ route('reports.download', ['type' => 'summary', 'by' => $by, 'venue' => $v, 'format' => 'csv']) }}"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Report builder --}}
  <h2 class="rp-h2" id="builder"><i class="bi bi-sliders"></i> Report builder</h2>
  <form class="rp-builder" id="rpForm" novalidate>
    <div class="row g-3">
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="rb-venue">Event</label>
        <select class="form-select" id="rb-venue" name="venue">
          <option value="pinned">Pinned: {{ $pinned }}</option>
          <option value="all">All events</option>
          @foreach($venues as $v)<option value="{{ $v->venue }}">{{ $v->venue }} ({{ $v->total }})</option>@endforeach
        </select>
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="rb-from">Registered from</label>
        <input type="date" class="form-control" id="rb-from" name="from">
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="rb-to">Registered until</label>
        <input type="date" class="form-control" id="rb-to" name="to">
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="rb-year">Year</label>
        <select class="form-select" id="rb-year" name="year"><option value="">Any year</option>@foreach($options['years'] as $y)<option>{{ $y }}</option>@endforeach</select>
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="rb-country">Country</label>
        <select class="form-select" id="rb-country" name="country"><option value="">All countries</option>@foreach($options['countries'] as $c)<option>{{ $c }}</option>@endforeach</select>
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="rb-gender">Gender</label>
        <select class="form-select" id="rb-gender" name="gender"><option value="">Male &amp; female</option>@foreach($options['genders'] as $g)<option>{{ $g }}</option>@endforeach</select>
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="rb-profession">Profession</label>
        <select class="form-select" id="rb-profession" name="profession"><option value="">All professions</option>@foreach($options['professions'] as $p)<option>{{ $p }}</option>@endforeach</select>
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="rb-status">Check-in</label>
        <select class="form-select" id="rb-status" name="status"><option value="">Everyone</option><option value="checked_in">Checked in</option><option value="not_checked_in">Not checked in</option></select>
      </div>
    </div>

    <div class="rp-builder-foot">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <label class="form-label mb-0 fw-bold" for="rb-by">Group by</label>
        <select class="form-select" id="rb-by" name="by" style="width:auto">
          @foreach($groups as $k => $label)<option value="{{ $k }}" @selected($k === 'country')>{{ $label }}</option>@endforeach
        </select>
        <button type="button" class="btn btn-link btn-sm" id="rbClear">Clear filters</button>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <div class="btn-group">
          <button type="button" class="btn btn-primary" data-dl="summary" data-format="pdf"><i class="bi bi-file-earmark-pdf"></i> Summary PDF</button>
          <button type="button" class="btn btn-outline-success" data-dl="summary" data-format="csv" title="Summary for Excel"><i class="bi bi-file-earmark-spreadsheet"></i></button>
        </div>
        <div class="btn-group">
          <button type="button" class="btn btn-outline-primary" data-dl="list" data-format="pdf"><i class="bi bi-people"></i> Attendee list PDF</button>
          <button type="button" class="btn btn-outline-success" data-dl="list" data-format="csv" title="Attendee list for Excel (all fields)"><i class="bi bi-file-earmark-spreadsheet"></i></button>
        </div>
      </div>
    </div>
  </form>

  <div class="rp-result" id="rpResult" aria-live="polite">
    <div class="rp-result-head">
      <div><div class="rp-scope" id="rpScope"></div><div class="rp-kpis" id="rpKpis"></div></div>
    </div>
    <div class="table-responsive">
      <table class="table rp-table mb-0">
        <thead><tr><th id="rpByHead">Country</th><th class="text-end">Registrations</th><th style="width:28%">Share</th><th class="text-end">Male</th><th class="text-end">Female</th><th class="text-end">Checked in</th></tr></thead>
        <tbody id="rpBody"></tbody>
        <tfoot id="rpFoot"></tfoot>
      </table>
    </div>
  </div>

  <p class="rp-note"><i class="bi bi-info-circle"></i> One attendee's record (photo, details, QR code): use the <i class="bi bi-file-earmark-person"></i> button in the dashboard's registration list. Pinning only changes what you see; new registrations always go to the current event (Admin &gt; Events).</p>
</section>
@endsection

@push('scripts')
<script>
  // Report builder: live preview (AJAX) + PDF / Excel downloads with the same filters
  (function () {
    var form = document.getElementById('rpForm');
    var result = document.getElementById('rpResult');
    var previewUrl = @json(route('reports.preview')), downloadUrl = @json(route('reports.download'));
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; };
    var num = function (n) { return Number(n).toLocaleString(); };
    var seq = 0, timer = null;

    function params(extra) {
      var p = new URLSearchParams();
      new FormData(form).forEach(function (v, k) { if (v !== '') p.append(k, v); });
      Object.keys(extra || {}).forEach(function (k) { p.set(k, extra[k]); });
      return p;
    }
    function clearFilters() {
      form.querySelectorAll('input, select').forEach(function (el) { if (el.name !== 'by') el.value = el.tagName === 'SELECT' ? el.options[0].value : ''; });
    }

    function load() {
      var my = ++seq;
      result.classList.add('loading');
      fetch(previewUrl + '?' + params(), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
          if (my !== seq) return;
          result.classList.remove('loading');
          var body = document.getElementById('rpBody'), foot = document.getElementById('rpFoot');
          if (!res.ok) {
            var errs = res.d.errors || {};
            body.innerHTML = '<tr><td colspan="6" class="rp-empty text-danger">' + esc(Object.keys(errs).map(function (k) { return errs[k][0]; }).join(' ') || res.d.message) + '</td></tr>';
            foot.innerHTML = ''; return;
          }
          var d = res.d, t = d.totals, max = Math.max.apply(null, d.groups.map(function (g) { return g.total; }).concat([1]));
          var pct = function (n) { return t.total ? Math.round(n / t.total * 1000) / 10 : 0; };
          document.getElementById('rpScope').textContent = d.scope;
          document.getElementById('rpByHead').textContent = d.by;
          document.getElementById('rpKpis').innerHTML = '<span><b>' + num(t.total) + '</b> registrations</span><span><b>' + num(t.male) + '</b> male (' + pct(t.male) + '%)</span>'
            + '<span><b>' + num(t.female) + '</b> female (' + pct(t.female) + '%)</span><span><b>' + num(t.checked_in) + '</b> checked in (' + pct(t.checked_in) + '%)</span>'
            + '<span><b>' + d.groups.length + '</b> ' + esc(d.by.toLowerCase()) + ' groups</span>';
          body.innerHTML = d.groups.length ? d.groups.map(function (g) {
            return '<tr><td class="fw-semibold">' + esc(g.label) + '</td><td class="text-end fw-bold">' + num(g.total) + '</td>'
              + '<td><div class="d-flex align-items-center gap-2"><div class="rp-share flex-grow-1"><span style="width:' + Math.max(2, Math.round(g.total / max * 100)) + '%"></span></div><small class="text-muted" style="width:44px;text-align:right">' + pct(g.total) + '%</small></div></td>'
              + '<td class="text-end">' + num(g.male) + '</td><td class="text-end">' + num(g.female) + '</td><td class="text-end">' + num(g.checked_in) + '</td></tr>';
          }).join('') : '<tr><td colspan="6" class="rp-empty">No registrations match these filters.</td></tr>';
          foot.innerHTML = d.groups.length ? '<tr><td>Total</td><td class="text-end">' + num(t.total) + '</td><td></td><td class="text-end">' + num(t.male) + '</td><td class="text-end">' + num(t.female) + '</td><td class="text-end">' + num(t.checked_in) + '</td></tr>' : '';
        })
        .catch(function () { if (my === seq) result.classList.remove('loading'); });
    }
    function schedule() { clearTimeout(timer); timer = setTimeout(load, 250); }

    form.addEventListener('change', schedule);
    form.addEventListener('submit', function (e) { e.preventDefault(); load(); });
    document.getElementById('rbClear').addEventListener('click', function () { clearFilters(); load(); });

    // Downloads use the same filters
    form.querySelectorAll('[data-dl]').forEach(function (b) {
      b.addEventListener('click', function () {
        var from = form.elements.from.value, to = form.elements.to.value;
        if (from && to && to < from) { Swal.fire({ icon: 'warning', title: 'Check the dates', text: 'The end date must be on or after the start date.' }); return; }
        location.href = downloadUrl + '?' + params({ type: b.dataset.dl, format: b.dataset.format });
      });
    });

    // "View" on a summary card: show it in the builder
    document.querySelectorAll('.rp-show').forEach(function (b) {
      b.addEventListener('click', function () {
        clearFilters();
        form.elements.by.value = b.dataset.by; form.elements.venue.value = b.dataset.venue;
        load();
        document.getElementById('builder').scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    load();
  })();
</script>
@endpush
