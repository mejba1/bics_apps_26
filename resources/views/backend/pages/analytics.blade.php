@extends('backend.layouts.master')
@section('title')
Participant Analytics
@endsection
@php
  $q = fn ($extra = []) => array_filter(array_merge(['days' => $a->days, 'event' => $a->eventId ?? 'all'], $extra), fn ($v) => $v !== null);
  $pct = fn ($n, $of) => $of ? round($n / $of * 100) : 0;
  $k = $kpis;
@endphp
@push('styles')
<style>
  .an-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
  .an-pills { display: inline-flex; background: var(--ad-chip); border-radius: 12px; padding: 3px; }
  .an-pills a { padding: 5px 11px; border-radius: 9px; font-size: 13px; font-weight: 600; color: var(--ad-muted); text-decoration: none; white-space: nowrap; }
  .an-pills a.on { background: var(--ad-surface); color: var(--ad-ink); box-shadow: var(--ad-shadow); }
  .an-kpis { display: grid; grid-template-columns: repeat(6, 1fr); gap: 14px; margin-bottom: 18px; }
  .an-kpi { background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 16px; padding: 16px; box-shadow: var(--ad-shadow); }
  .an-kpi .ic { width: 36px; height: 36px; border-radius: 11px; display: inline-flex; align-items: center; justify-content: center; font-size: 17px; margin-bottom: 8px; }
  .an-kpi b { display: block; font-size: 1.6rem; font-weight: 800; color: var(--ad-ink); line-height: 1.1; }
  .an-kpi .l { font-weight: 700; color: var(--ad-ink); font-size: 13.5px; }
  .an-kpi .s { font-size: 12px; color: var(--ad-muted); }
  .an-card { background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 16px; box-shadow: var(--ad-shadow); padding: 18px; height: 100%; }
  .an-card h5 { font-size: 15px; font-weight: 800; color: var(--ad-ink); margin: 0; }
  .an-head { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; }
  .an-head .sub { font-size: 12.5px; color: var(--ad-muted); }
  .an-head .csv { margin-left: auto; font-size: 12.5px; font-weight: 700; text-decoration: none; }
  .an-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 8px 0; border-top: 1px solid var(--ad-line-soft); font-size: 13.5px; }
  .an-row:first-child { border-top: 0; }
  .an-row span { color: var(--ad-text); } .an-row b { color: var(--ad-ink); }
  .an-meter { height: 6px; border-radius: 6px; background: var(--ad-line-soft); overflow: hidden; margin-top: 3px; } .an-meter i { display: block; height: 100%; border-radius: 6px; background: var(--ad-brand); }
  .an-hbar { display: grid; grid-template-columns: minmax(0, 1fr) 46px; gap: 4px 10px; align-items: center; font-size: 13px; padding: 4px 0; }
  .an-hbar .t { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--ad-text); }
  .an-hbar .n { text-align: right; font-weight: 700; color: var(--ad-ink); }
  .an-hbar .bar { grid-column: 1 / -1; height: 6px; border-radius: 6px; background: var(--ad-line-soft); overflow: hidden; } .an-hbar .bar i { display: block; height: 100%; border-radius: 6px; }
  .an-top td { vertical-align: middle; font-size: 13.5px; }
  .an-av { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; background: var(--ad-chip); color: var(--ad-brand); font-weight: 800; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; flex: none; }
  .an-src { display: inline-flex; gap: 4px; } .an-src span { font-size: 11px; font-weight: 700; padding: 1px 6px; border-radius: 6px; background: var(--ad-chip); color: var(--ad-muted); }
  .an-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 12.5px; color: var(--ad-muted); } .an-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 5px; vertical-align: -1px; }
  @media (max-width: 1399px) { .an-kpis { grid-template-columns: repeat(3, 1fr); } }
  @media (max-width: 575px) { .an-kpis { grid-template-columns: repeat(2, 1fr); } }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Participant Analytics</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Participant Analytics</li></ol></nav>
  </div>
  <div class="an-filters">
    <div class="an-pills">@foreach($periods as $d => $label)<a href="{{ route('analytics.index', $q(['days' => $d])) }}" class="{{ $a->days === $d ? 'on' : '' }}">{{ $label }}</a>@endforeach</div>
    <select class="form-select form-select-sm" style="width:auto" onchange="location.href=this.value">
      <option value="{{ route('analytics.index', $q(['event' => 'all'])) }}" @selected(!$a->eventId)>All participants</option>
      @foreach($eventList as $e)<option value="{{ route('analytics.index', $q(['event' => $e->id])) }}" @selected($a->eventId === $e->id)>{{ $e->event_short }}{{ $e->is_current ? ' (current)' : '' }}</option>@endforeach
    </select>
    <div class="dropdown"><button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-filetype-csv"></i> Export</button>
      <ul class="dropdown-menu dropdown-menu-end">
        @foreach(['daily' => 'Visits over time', 'top' => 'Top profiles', 'events' => 'Events', 'organizations' => 'Organizations', 'categories' => 'Categories', 'countries' => 'Countries'] as $kind => $label)
        <li><a class="dropdown-item" href="{{ route('analytics.export', array_merge(['kind' => $kind], $q())) }}">{{ $label }} (CSV)</a></li>
        @endforeach
      </ul></div>
  </div>
</div>

<section class="section">
  <div class="an-kpis">
    @foreach([
      ['bi-eye', $k['views'], 'Profile views', $periods[$a->days], '#1861c5'],
      ['bi-qr-code-scan', $k['view_qr'], 'QR scans', $pct($k['view_qr'], $k['views']) . '% of views', '#7a3fc0'],
      ['bi-broadcast', $k['view_nfc'], 'NFC taps', $pct($k['view_nfc'], $k['views']) . '% of views', '#0f8fa3'],
      ['bi-person-plus', $k['vcard'], 'Contacts saved', 'vCard downloads', '#2f8a4c'],
      ['bi-hand-index', $k['clicks'], 'Calls, emails & links', 'button presses', '#b7791f'],
      ['bi-person-workspace', $k['dashboard'], 'Dashboard users', $k['dashboard_recent'] . ' in the last 30 days', '#b22e6b'],
    ] as [$icon, $n, $label, $sub, $color])
    <div class="an-kpi"><span class="ic" style="background:{{ $color }}1f;color:{{ $color }}"><i class="bi {{ $icon }}"></i></span><b>{{ number_format($n) }}</b><div class="l">{{ $label }}</div><div class="s">{{ $sub }}</div></div>
    @endforeach
  </div>

  <div class="row g-3 mb-3">
    <div class="col-xl-8">
      <div class="an-card">
        <div class="an-head"><h5>Profile visits by source</h5><span class="sub">{{ $series['monthly'] ? 'per month' : 'per day' }}</span>
          <div class="an-legend ms-auto"><span><i style="background:#1861c5"></i>Link</span><span><i style="background:#7a3fc0"></i>QR</span><span><i style="background:#0f8fa3"></i>NFC</span><span><i style="background:#2f8a4c"></i>Contacts saved</span><span><i style="background:#b22e6b"></i>Dashboard sign-ins</span></div></div>
        <div style="height:300px"><canvas id="an-series"></canvas></div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="an-card">
        <div class="an-head"><h5>Where visits come from</h5></div>
        <div style="height:180px"><canvas id="an-sources"></canvas></div>
        <div class="mt-3">
          @foreach(['view_link' => ['Shared link', '#1861c5'], 'view_qr' => ['QR code scan', '#7a3fc0'], 'view_nfc' => ['NFC card tap', '#0f8fa3']] as $m => [$label, $c])
          <div class="an-row"><span><i class="bi bi-circle-fill" style="color:{{ $c }};font-size:9px"></i> {{ $label }}</span><b>{{ number_format($k[$m]) }} <span class="text-muted fw-normal small">({{ $pct($k[$m], $k['views']) }}%)</span></b></div>
          @endforeach
          @if($k['clicks_by'])<div class="small text-muted mt-2">Button presses: {{ collect($k['clicks_by'])->map(fn ($n, $w) => ucfirst($w) . ' ' . $n)->implode(' · ') }}</div>@endif
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-xl-7">
      <div class="an-card">
        <div class="an-head"><h5>Most viewed profiles</h5><span class="sub">{{ $periods[$a->days] }}</span><a class="csv" href="{{ route('analytics.export', array_merge(['kind' => 'top'], $q())) }}">CSV</a></div>
        <div class="table-responsive"><table class="table table-sm an-top mb-0">
          <thead><tr><th>#</th><th>Participant</th><th class="text-end">Views</th><th class="d-none d-md-table-cell">Source</th><th class="text-end">Saved</th></tr></thead>
          <tbody>
          @forelse($top as $i => $t)
            <tr><td class="text-muted">{{ $i + 1 }}</td>
              <td><div class="d-flex align-items-center gap-2">@if($t['photo'])<img class="an-av" src="{{ $t['photo'] }}" alt="">@else<span class="an-av">{{ mb_substr($t['name'], 0, 1) }}</span>@endif
                <div class="min-w-0"><a class="fw-semibold" href="{{ route('participants.edit', $t['id']) }}">{{ $t['name'] }}</a><div class="small text-muted text-truncate" style="max-width:260px">{{ $t['code'] }}{{ $t['organization'] ? ' · ' . $t['organization'] : '' }}</div></div></div></td>
              <td class="text-end fw-bold">{{ number_format($t['views']) }}</td>
              <td class="d-none d-md-table-cell"><span class="an-src"><span title="Link">L {{ $t['link'] }}</span><span title="QR">QR {{ $t['qr'] }}</span><span title="NFC">NFC {{ $t['nfc'] }}</span></span></td>
              <td class="text-end">{{ $t['vcard'] }}</td></tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No profile visits in this period yet.</td></tr>
          @endforelse
          </tbody></table></div>
      </div>
    </div>
    <div class="col-xl-5">
      <div class="an-card">
        <div class="an-head"><h5>Participants</h5><span class="sub">{{ $a->eventId ? 'of this event' : 'all profiles' }}</span></div>
        @foreach([
          ['Profiles', $k['participants'], null], ['Active (public)', $k['active'], $k['participants']], ['Suspended', $k['suspended'], $k['participants']],
          ['With photo', $k['photo'], $k['participants']], ['With email (can sign in)', $k['email'], $k['participants']],
          ['Used their dashboard', $k['dashboard'], $k['participants']], ['Set a password', $k['password'], $k['participants']],
          ['Profile edits by participants', $k['edits'], null],
        ] as [$label, $n, $of])
          <div class="an-row"><span>{{ $label }}</span><b>{{ number_format($n) }}@if($of) <span class="text-muted fw-normal small">({{ $pct($n, $of) }}%)</span>@endif</b></div>
        @endforeach
        <div class="an-head mt-3 mb-2"><h5>NFC cards &amp; printing</h5></div>
        <div class="an-row"><span><i class="bi bi-circle-fill text-success" style="font-size:9px"></i> Active cards</span><b>{{ $k['cards_active'] }}</b></div>
        <div class="an-row"><span><i class="bi bi-circle-fill text-info" style="font-size:9px"></i> Being prepared</span><b>{{ $k['cards_assigned'] }}</b></div>
        <div class="an-row"><span><i class="bi bi-circle-fill text-danger" style="font-size:9px"></i> Lost / damaged / switched off</span><b>{{ $k['cards_off'] }}</b></div>
        @unless($a->eventId)<div class="an-row"><span><i class="bi bi-circle-fill text-secondary" style="font-size:9px"></i> Blank cards in stock</span><b>{{ $k['cards_stock'] }}</b></div>@endunless
        <div class="an-row"><span><i class="bi bi-printer"></i> Participants with a printed card</span><b>{{ $k['printed'] }}</b></div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    @foreach([['organizations', 'Organizations', $orgs, '#1861c5'], ['countries', 'Countries', $countries, '#0f8fa3'], ['categories', 'Categories', $cats, '#b22e6b']] as [$kind, $title, $data, $color])
    <div class="col-xl-4">
      <div class="an-card">
        <div class="an-head"><h5>{{ $title }}</h5><span class="sub">{{ $data['distinct'] }} different</span><a class="csv" href="{{ route('analytics.export', array_merge(['kind' => $kind], $q())) }}">CSV</a></div>
        @php $max = max(1, collect($data['rows'])->max(1)); @endphp
        @forelse($data['rows'] as [$v, $n])
          <div class="an-hbar"><span class="t" title="{{ $v }}">{{ $v }}</span><span class="n">{{ $n }}</span><span class="bar"><i style="width:{{ round($n / $max * 100) }}%;background:{{ $color }}"></i></span></div>
        @empty
          <div class="text-muted small">No data.</div>
        @endforelse
        @if($kind !== 'countries')<a class="small d-inline-block mt-2" href="{{ route('participants.values', ['field' => $kind === 'organizations' ? 'organization' : 'category']) }}"><i class="bi bi-magic"></i> Clean up spelling variants</a>@endif
      </div>
    </div>
    @endforeach
  </div>

  <div class="an-card mb-3">
    <div class="an-head"><h5>Events</h5><span class="sub">all time</span><a class="csv" href="{{ route('analytics.export', array_merge(['kind' => 'events'], $q())) }}">CSV</a></div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead><tr><th>Event</th><th class="text-end">Registrations</th><th class="text-end">Participants</th><th style="min-width:180px">Checked in</th><th class="text-end">Certificates</th><th class="text-end">Prizes</th></tr></thead>
      <tbody>
      @forelse($events as $e)
        <tr><td><b>{{ $e['event'] }}</b><div class="small text-muted">{{ $e['name'] }}</div></td><td class="text-end">{{ number_format($e['registrations']) }}</td><td class="text-end">{{ number_format($e['participants']) }}</td>
          <td><div class="d-flex justify-content-between small"><span>{{ number_format($e['checked_in']) }}</span><span class="text-muted">{{ $pct($e['checked_in'], $e['registrations']) }}%</span></div><div class="an-meter"><i style="width:{{ $pct($e['checked_in'], $e['registrations']) }}%"></i></div></td>
          <td class="text-end">{{ $e['certificates'] }}</td><td class="text-end">{{ $e['prizes'] }}</td></tr>
      @empty
        <tr><td colspan="6" class="text-center text-muted py-3">No registrations yet.</td></tr>
      @endforelse
      </tbody></table></div>
  </div>
  <div class="small text-muted"><i class="bi bi-shield-check"></i> Visits are daily counts only — no visitor is identified or tracked. Participants' visits to their own profile are not counted.</div>
</section>
@endsection

@push('scripts')
<script>
(function () {
  if (!window.Chart) return;
  var s = @json($series), k = @json($kpis);
  var css = getComputedStyle(document.documentElement), grid = css.getPropertyValue('--ad-line').trim() || '#e6eaf1', text = css.getPropertyValue('--ad-muted').trim() || '#6b7385';
  Chart.defaults.color = text; Chart.defaults.font.family = 'Inter, "Segoe UI", sans-serif';
  new Chart(document.getElementById('an-series'), {
    data: { labels: s.labels, datasets: [
      { type: 'bar', label: 'Link', data: s.link, backgroundColor: '#1861c5', stack: 'v', borderRadius: 3 },
      { type: 'bar', label: 'QR', data: s.qr, backgroundColor: '#7a3fc0', stack: 'v', borderRadius: 3 },
      { type: 'bar', label: 'NFC', data: s.nfc, backgroundColor: '#0f8fa3', stack: 'v', borderRadius: 3 },
      { type: 'line', label: 'Contacts saved', data: s.vcard, borderColor: '#2f8a4c', backgroundColor: '#2f8a4c', tension: .3, pointRadius: 0, borderWidth: 2 },
      { type: 'line', label: 'Dashboard sign-ins', data: s.logins, borderColor: '#b22e6b', backgroundColor: '#b22e6b', borderDash: [4, 4], tension: .3, pointRadius: 0, borderWidth: 2 }
    ] },
    options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { display: false } },
      scales: { x: { stacked: true, grid: { display: false }, ticks: { maxTicksLimit: 12 } }, y: { stacked: true, beginAtZero: true, grid: { color: grid }, ticks: { precision: 0 } } } }
  });
  var total = k.view_link + k.view_qr + k.view_nfc;
  new Chart(document.getElementById('an-sources'), {
    type: 'doughnut',
    data: { labels: ['Link', 'QR', 'NFC'], datasets: [{ data: total ? [k.view_link, k.view_qr, k.view_nfc] : [1], backgroundColor: total ? ['#1861c5', '#7a3fc0', '#0f8fa3'] : [grid], borderWidth: 0 }] },
    options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false }, tooltip: { enabled: !!total } } }
  });
})();
</script>
@endpush
