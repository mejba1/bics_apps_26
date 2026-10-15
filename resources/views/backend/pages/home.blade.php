@extends('backend.layouts.master')
@section('title')
   Dashboard
@endsection
@push('styles')
<style>
  .db-hero { position: relative; overflow: hidden; border-radius: 18px; padding: 24px 26px; color: #fff; margin-bottom: 22px;
    background: radial-gradient(circle at 92% 0%, rgba(255,255,255,.16), transparent 42%), linear-gradient(120deg, #0a3a7c, #0e4ca1 55%, #b22e6b);
    box-shadow: 0 20px 44px -24px rgba(14, 40, 90, .75); }
  .db-hero h1 { color: #fff; font-size: 24px; font-weight: 800; margin: 2px 0 6px; }
  .db-hero p { margin: 0; opacity: .9; }
  .db-hero-meta { display: flex; flex-wrap: wrap; gap: 8px 18px; margin-top: 12px; font-size: 13.5px; opacity: .95; }
  .db-hero-meta i { color: #ffd28a; margin-right: 5px; }
  .db-countdown { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25); border-radius: 14px; padding: 12px 18px; text-align: center; min-width: 120px; }
  .db-countdown b { display: block; font-size: 30px; line-height: 1; font-weight: 800; }
  .db-countdown span { font-size: 11.5px; letter-spacing: .1em; text-transform: uppercase; opacity: .85; }
  .db-actions .btn { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.3); color: #fff; box-shadow: none; }
  .db-actions .btn:hover { background: var(--ad-surface); color: var(--ad-brand); }
  .db-venues { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
  .db-venue { display: inline-flex; align-items: center; gap: 8px; padding: 7px 14px; border-radius: 999px; border: 1.5px solid var(--ad-line); background: var(--ad-surface);
    color: var(--ad-text); font-weight: 600; font-size: 13.5px; text-decoration: none; transition: all .15s; }
  .db-venue span { font-size: 12px; color: var(--ad-muted); background: var(--ad-chip); border-radius: 999px; padding: 1px 8px; }
  .db-venue:hover { border-color: var(--ad-brand); color: var(--ad-brand); }
  .db-venue.is-active { background: var(--ad-brand); border-color: var(--ad-brand); color: #fff; }
  .db-venue.is-active span { background: rgba(255,255,255,.2); color: #fff; }
  .db-venues-row { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 10px; }
  .db-venues-row .db-venues { margin-bottom: 6px; }
  .db-venue .db-pin { color: var(--ad-accent); font-size: 13px; }
  .db-venue.is-active .db-pin { color: #fff; }
  .db-pin-btn, .db-pinned { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; font-weight: 600; font-size: 14px; white-space: nowrap; }
  .db-pin-btn { border: 1.5px dashed var(--ad-accent); background: transparent; color: var(--ad-accent); }
  .db-pin-btn:hover { background: var(--ad-accent-50); }
  .db-pinned { background: var(--ad-accent-50); color: var(--ad-accent); }
  .db-legend { display: flex; flex-wrap: wrap; gap: 4px 18px; font-size: 12.5px; color: var(--ad-muted); margin-bottom: 18px; }
  .db-legend span { display: inline-flex; align-items: center; gap: 6px; }
  .db-legend .bi { color: var(--ad-accent); }
  .db-venue .db-now { width: 7px; height: 7px; border-radius: 50%; background: #2fbf71; box-shadow: 0 0 0 3px rgba(47,191,113,.2); }
  .db-kpi { height: 100%; margin-bottom: 0; }
  .db-kpi .card-body { display: flex; gap: 14px; align-items: flex-start; padding: 18px; }
  .db-kpi-label { font-size: 13px; color: var(--ad-muted); font-weight: 600; }
  .db-kpi-value { font-size: 28px; font-weight: 800; color: var(--ad-ink); line-height: 1.15; letter-spacing: -.01em; }
  .db-kpi-sub { font-size: 12.5px; color: var(--ad-muted); margin-top: 2px; }
  .db-kpi-sub .up { color: var(--ad-green); font-weight: 700; }
  .db-bar { height: 6px; background: var(--ad-line-soft); border-radius: 6px; overflow: hidden; margin-top: 10px; }
  .db-bar > i { display: block; height: 100%; border-radius: 6px; background: linear-gradient(90deg, var(--ad-brand), var(--ad-accent)); }
  .db-rank li { display: grid; grid-template-columns: 1fr auto; gap: 4px 10px; padding: 9px 0; border-bottom: 1px solid var(--ad-line-soft); }
  .db-rank li:last-child { border-bottom: 0; }
  .db-rank .name { font-weight: 600; color: var(--ad-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .db-rank .count { font-weight: 700; color: var(--ad-ink); font-variant-numeric: tabular-nums; }
  .db-rank .count a { color: inherit; }
  .db-rank .db-bar { grid-column: 1 / -1; margin-top: 0; height: 5px; }
  .db-scroll { max-height: 330px; overflow-y: auto; padding-right: 4px; }
  .db-recent li { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--ad-line-soft); }
  .db-recent li:last-child { border-bottom: 0; }
  .db-avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--ad-brand-50); color: var(--ad-brand); font-weight: 800; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .db-person { font-weight: 600; color: var(--ad-ink); line-height: 1.25; }
  .db-person small { display: block; color: var(--ad-muted); font-weight: 400; font-size: 12.5px; }
  .db-table td .db-person { white-space: nowrap; }
  .db-section-title { display: flex; align-items: baseline; gap: 10px; margin: 4px 0 12px; }
  .db-section-title span { font-size: 17px; font-weight: 800; color: var(--ad-ink); }
  .db-section-title small { color: var(--ad-muted); }
  .db-gender { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .db-gender-item { display: flex; gap: 12px; align-items: center; padding: 12px; border-radius: 12px; background: var(--ad-subtle); }
  .db-split { display: flex; height: 10px; border-radius: 10px; overflow: hidden; background: var(--ad-line-soft); }
  .db-split .m { background: var(--ad-brand); } .db-split .f { background: var(--ad-accent); }
  .db-prof .card-body { display: flex; gap: 12px; align-items: center; padding: 14px 16px; }
  .db-prof-value { font-size: 22px; font-weight: 800; color: var(--ad-ink); line-height: 1.2; }
  .db-prof-value small { font-size: 12.5px; font-weight: 600; color: var(--ad-muted); margin-left: 4px; }
  .db-card-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 6px; }
  .db-card-head h5 { margin: 0; font-size: 16px; font-weight: 700; }
  .db-card-head small { color: var(--ad-muted); }
</style>
@endpush
@section('content')
@php
   use App\Support\EventSettings as Ev;
   $s = $stats;
   $start = Ev::date('event_start');
   $daysLeft = $start && $start->isFuture() ? (int) now(Ev::timezone())->startOfDay()->diffInDays($start->copy()->startOfDay()) : null;
   $isCurrent = $venue === $currentVenue;
   $label = $venue === 'all' ? 'All events' : $venue;
   $initials = fn ($a, $b) => mb_strtoupper(mb_substr(trim((string) $a), 0, 1) . mb_substr(trim((string) $b), 0, 1));
@endphp

{{-- ===== Welcome ===== --}}
<div class="db-hero">
  <div class="row g-3 align-items-center">
    <div class="col-lg">
      <div class="small text-uppercase fw-bold" style="letter-spacing:.12em;opacity:.8" id="db-greeting">Welcome back</div>
      <h1>{{ Auth::user()->name }}</h1>
      <p>{{ $isCurrent ? 'Here is how registration for ' . Ev::get('event_name') . ' is going.' : 'Showing ' . $label . '.' }}</p>
      <div class="db-hero-meta">
        <span><i class="bi bi-calendar-event-fill"></i>{{ Ev::datesLabel() }}</span>
        <span><i class="bi bi-geo-alt-fill"></i>{{ Ev::get('event_venue') }}</span>
        @php $reg = Ev::registration(); @endphp
        <span><i class="bi bi-door-open-fill"></i>Registration {{ ['open' => 'open', 'upcoming' => 'opens ' . optional($reg['opens'])->format('j M, H:i'), 'closed' => 'closed'][$reg['state']] }}</span>
      </div>
    </div>
    @if($daysLeft !== null)
    <div class="col-auto"><div class="db-countdown"><b>{{ $daysLeft }}</b><span>{{ $daysLeft === 1 ? 'day' : 'days' }} to go</span></div></div>
    @endif
    <div class="col-12 col-xl-auto db-actions d-flex flex-wrap gap-2">
      @can('attendees.verify')<a href="{{ url('/verify-user') }}" class="btn"><i class="bi bi-person-check"></i> Verify attendee</a>@endcan
      @can('registrations.create')
        <a href="{{ route('registrations.create') }}" class="btn"><i class="bi bi-person-plus"></i> New registration</a>
      @else
        <a href="{{ url('/registration') }}" target="_blank" class="btn"><i class="bi bi-person-plus"></i> Public form</a>
      @endcan
      @can('registrations.export')<a href="{{ route('reports.index') }}" class="btn"><i class="bi bi-file-earmark-pdf"></i> Reports</a>@endcan
    </div>
  </div>
</div>

{{-- ===== Event switcher (pin = your default event for the dashboard and reports) ===== --}}
<div class="db-venues-row">
  <div class="db-venues" role="tablist" aria-label="Choose event">
    @foreach($venues as $v)
      <a href="{{ url('/home?venue=' . urlencode($v->venue)) }}" class="db-venue {{ $venue === $v->venue ? 'is-active' : '' }}" role="tab" aria-selected="{{ $venue === $v->venue ? 'true' : 'false' }}">
        @if($v->venue === $pinnedVenue)<i class="bi bi-pin-angle-fill db-pin" title="Your pinned event"></i>@endif
        @if($v->venue === $currentVenue)<i class="db-now" title="Current event (new registrations)"></i>@endif
        {{ $v->venue }} <span>{{ $v->total }}</span>
      </a>
    @endforeach
    <a href="{{ url('/home?venue=all') }}" class="db-venue {{ $venue === 'all' ? 'is-active' : '' }}" role="tab">All events <span>{{ $venues->sum('total') }}</span></a>
  </div>
  @if($venue !== 'all')
    @if($venue === $pinnedVenue)
      <span class="db-pinned" title="The dashboard and reports open on this event"><i class="bi bi-pin-angle-fill"></i> Pinned</span>
    @else
      <button type="button" class="db-pin-btn" id="dbPin" data-venue="{{ $venue }}"><i class="bi bi-pin-angle"></i> Pin {{ $venue }}</button>
    @endif
  @endif
</div>
<div class="db-legend"><span><i class="db-now"></i> Current event: new registrations are saved here</span><span><i class="bi bi-pin-angle-fill"></i> Pinned: your dashboard and reports open on it</span></div>
@php $dbEvent = $venue !== 'all' ? \App\Models\Event::forVenue($venue) : null; @endphp
@include('partials.event-lock', ['event' => $dbEvent])
<div class="{{ \App\Support\EventLock::isLocked($dbEvent) ? 'is-readonly' : '' }}">

{{-- ===== KPIs ===== --}}
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card db-kpi"><div class="card-body">
      <span class="ad-icon-tile ad-tone-brand"><i class="bi bi-people-fill"></i></span>
      <div class="flex-grow-1">
        <div class="db-kpi-label">Registrations</div>
        <div class="db-kpi-value">{{ number_format($s['total']) }}</div>
        <div class="db-kpi-sub">@if($s['today'])<span class="up">+{{ $s['today'] }}</span> today · @endif{{ $s['week'] }} in the last 7 days</div>
      </div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card db-kpi"><div class="card-body">
      <span class="ad-icon-tile ad-tone-teal"><i class="bi bi-globe2"></i></span>
      <div class="flex-grow-1">
        <div class="db-kpi-label">Countries</div>
        <div class="db-kpi-value">{{ $s['countries'] }}</div>
        <div class="db-kpi-sub">{{ $countries->first() ? 'Most from ' . $countries->first()->country : 'No registrations yet' }}</div>
      </div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card db-kpi"><div class="card-body">
      <span class="ad-icon-tile ad-tone-green"><i class="bi bi-person-check-fill"></i></span>
      <div class="flex-grow-1">
        <div class="db-kpi-label">Checked in</div>
        <div class="db-kpi-value">{{ $s['verified'] }} <small class="fs-6 text-muted fw-semibold">/ {{ $s['total'] }}</small></div>
        <div class="db-bar" title="{{ $s['verified_pct'] }}%"><i style="width:{{ $s['verified_pct'] }}%"></i></div>
      </div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card db-kpi"><div class="card-body">
      <span class="ad-icon-tile ad-tone-accent"><i class="bi bi-envelope-check-fill"></i></span>
      <div class="flex-grow-1">
        <div class="db-kpi-label">Confirmation emails</div>
        <div class="db-kpi-value">{{ $s['emailed_pct'] }}%</div>
        <div class="db-kpi-sub">{{ $s['emailed'] }} of {{ $s['total'] }} sent</div>
      </div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-4">
    <div class="card db-kpi"><div class="card-body">
      <span class="ad-icon-tile ad-tone-amber"><i class="bi bi-gift-fill"></i></span>
      <div class="flex-grow-1">
        <div class="db-kpi-label">Gifts handed out</div>
        <div class="db-kpi-value">{{ $s['gifts'] }} <small class="fs-6 text-muted fw-semibold">/ {{ $s['total'] }}</small></div>
        <div class="db-bar"><i style="width:{{ $s['gifts_pct'] }}%"></i></div>
      </div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-4">
    <div class="card db-kpi"><div class="card-body">
      <span class="ad-icon-tile ad-tone-brand"><i class="bi bi-award-fill"></i></span>
      <div class="flex-grow-1">
        <div class="db-kpi-label">Raffle prizes</div>
        <div class="db-kpi-value">{{ $s['prizes_given'] }} <small class="fs-6 text-muted fw-semibold">/ {{ $s['prizes'] }} drawn</small></div>
        <div class="db-bar"><i style="width:{{ $s['prizes'] ? round($s['prizes_given'] / $s['prizes'] * 100) : 0 }}%"></i></div>
      </div>
    </div></div>
  </div>
  <div class="col-sm-12 col-xl-4">
    <div class="card db-kpi"><div class="card-body">
      <span class="ad-icon-tile ad-tone-red"><i class="bi bi-trophy-fill"></i></span>
      <div class="flex-grow-1">
        <div class="db-kpi-label">Raffle winners</div>
        <div class="db-kpi-value">{{ $s['winners'] }}</div>
        <div class="db-kpi-sub">@can('raffle.prizes')<a href="{{ url('/prize-winner') }}">See winners <i class="bi bi-arrow-right"></i></a>@else&nbsp;@endcan</div>
      </div>
    </div></div>
  </div>
</div>

{{-- ===== All registrations (highlighted, right after the numbers) ===== --}}
@can('registrations.list')
@include('backend.pages.partials.dashboard-registrations')
@endcan

{{-- ===== Who's attending: gender + professions ===== --}}
@php
  $male = (int) ($genders['Male'] ?? 0); $female = (int) ($genders['Female'] ?? 0);
  $other = max(0, $s['total'] - $male - $female);
  $profIcon = function ($name) {
      $n = mb_strtolower($name);
      foreach (['doctor' => 'bi-heart-fill', 'physician' => 'bi-heart-fill', 'pharmac' => 'bi-file-medical-fill', 'patholog' => 'bi-eyedropper',
                'nurs' => 'bi-bandaid-fill', 'government' => 'bi-bank2', 'procurement' => 'bi-box-seam', 'research' => 'bi-search', 'student' => 'bi-mortarboard-fill'] as $k => $icon) {
          if (str_contains($n, $k)) return $icon;
      }
      return 'bi-person-badge-fill';
  };
  $tones = ['ad-tone-brand', 'ad-tone-accent', 'ad-tone-teal', 'ad-tone-green', 'ad-tone-amber', 'ad-tone-red'];
@endphp
<div class="db-section-title"><span>Who's attending</span><small>{{ $label }}</small></div>
<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="card h-100 mb-0"><div class="card-body">
      <div class="db-card-head mb-3"><h5>Gender</h5><small>{{ $s['total'] }} people</small></div>
      <div class="db-gender">
        <div class="db-gender-item">
          <span class="ad-icon-tile ad-tone-brand"><i class="bi bi-gender-male"></i></span>
          <div><div class="db-kpi-value">{{ $male }}</div><div class="db-kpi-label">Male · {{ $s['total'] ? round($male / $s['total'] * 100) : 0 }}%</div></div>
        </div>
        <div class="db-gender-item">
          <span class="ad-icon-tile ad-tone-accent"><i class="bi bi-gender-female"></i></span>
          <div><div class="db-kpi-value">{{ $female }}</div><div class="db-kpi-label">Female · {{ $s['total'] ? round($female / $s['total'] * 100) : 0 }}%</div></div>
        </div>
      </div>
      <div class="db-split mt-3" role="img" aria-label="{{ $male }} male, {{ $female }} female">
        <i class="m" style="width:{{ $s['total'] ? $male / $s['total'] * 100 : 0 }}%"></i><i class="f" style="width:{{ $s['total'] ? $female / $s['total'] * 100 : 0 }}%"></i>
      </div>
      @if($other)<div class="small text-muted mt-2">{{ $other }} not specified</div>@endif
    </div></div>
  </div>
  <div class="col-lg-8">
    <div class="row g-3">
      @forelse($professionCards as $i => $p)
      <div class="col-sm-6 col-md-4">
        <div class="card db-prof h-100 mb-0"><div class="card-body">
          <span class="ad-icon-tile {{ $tones[$i % count($tones)] }}"><i class="bi {{ $profIcon($p['name']) }}"></i></span>
          <div class="flex-grow-1" style="min-width:0">
            <div class="db-kpi-label text-truncate" title="{{ $p['name'] }}">{{ $p['name'] }}</div>
            <div class="db-prof-value">{{ $p['n'] }} <small>{{ $s['total'] ? round($p['n'] / $s['total'] * 100) : 0 }}%</small></div>
          </div>
        </div></div>
      </div>
      @empty
      <div class="col-12"><div class="ad-empty"><i class="bi bi-briefcase"></i>No professions yet.</div></div>
      @endforelse
    </div>
  </div>
</div>

{{-- ===== Trend + countries ===== --}}
<div class="row g-3">
  <div class="col-xl-8">
    <div class="card h-100 mb-0"><div class="card-body">
      <div class="db-card-head"><h5>Registrations per day</h5><small>{{ $seriesRange }}</small></div>
      <div id="db-trend" style="min-height:300px"></div>
    </div></div>
  </div>
  <div class="col-xl-4">
    <div class="card h-100 mb-0"><div class="card-body">
      <div class="db-card-head"><h5>Top countries</h5><small>{{ $countries->count() }} total</small></div>
      @if($countries->isEmpty())
        <div class="ad-empty"><i class="bi bi-globe"></i>No registrations yet.</div>
      @else
      <ul class="list-unstyled db-rank db-scroll mb-0">
        @php $max = max(1, $countries->max('n')); @endphp
        @foreach($countries as $c)
        <li>
          <span class="name">{{ $c->country }}</span>
          <span class="count">@can('registrations.export')<a href="{{ url('/country-wise-report/' . $c->country . ($venue !== 'all' ? '/' . $venue : '')) }}" title="Download this country's report">{{ $c->n }}</a>@else{{ $c->n }}@endcan</span>
          <div class="db-bar"><i style="width:{{ round($c->n / $max * 100) }}%"></i></div>
        </li>
        @endforeach
      </ul>
      @endif
    </div></div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-xl-4 col-lg-5">
    <div class="card h-100 mb-0"><div class="card-body">
      <div class="db-card-head"><h5>Professions</h5><small>top {{ $professions->count() }}</small></div>
      @if($professions->isEmpty())
        <div class="ad-empty"><i class="bi bi-briefcase"></i>No data yet.</div>
      @else
        <div id="db-professions" style="min-height:260px"></div>
      @endif
    </div></div>
  </div>
  <div class="col-xl-8 col-lg-7">
    <div class="card h-100 mb-0"><div class="card-body">
      <div class="db-card-head"><h5>Latest registrations</h5>@can('registrations.list')<a href="#all-registrations" class="small fw-semibold">View all <i class="bi bi-arrow-down"></i></a>@endcan</div>
      @can('registrations.list')
        @if($recent->isEmpty())
          <div class="ad-empty"><i class="bi bi-inbox"></i>No registrations yet for {{ $label }}.</div>
        @else
        <ul class="list-unstyled db-recent mb-0">
          @foreach($recent as $r)
          <li>
            <span class="db-avatar">{{ $initials($r->firstname, $r->lastname) }}</span>
            <div class="db-person flex-grow-1" style="min-width:0">{{ trim($r->title . ' ' . $r->firstname . ' ' . $r->lastname) }}<small class="text-truncate">{{ $r->profession }}{{ $r->country ? ' · ' . $r->country : '' }}</small></div>
            <span class="badge {{ $r->verify_status === 'Yes' ? 'bg-success' : 'bg-light' }}">{{ $r->verify_status === 'Yes' ? 'Checked in' : 'Registered' }}</span>
            <small class="text-muted text-nowrap d-none d-sm-inline">{{ $r->created_at ? \Carbon\Carbon::parse($r->created_at)->diffForHumans() : '' }}</small>
          </li>
          @endforeach
        </ul>
        @endif
      @else
        <div class="ad-empty"><i class="bi bi-lock"></i>Your role cannot see attendee details.</div>
      @endcan
    </div></div>
  </div>
</div>


</div>{{-- /read-only wrapper --}}
@endsection

@push('scripts')
<script>
  // Pin the event being viewed: the dashboard and reports open on it from now on
  (function () {
    var btn = document.getElementById('dbPin');
    if (!btn) return;
    btn.addEventListener('click', function () {
      btn.disabled = true;
      var fd = new FormData(); fd.append('venue', btn.dataset.venue);
      fetch(@json(route('events.pin')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }, body: fd })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
          if (!res.ok) { btn.disabled = false; Swal.fire('Not pinned', res.d.message || 'Please try again.', 'error'); return; }
          Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.d.message, showConfirmButton: false, timer: 2200 });
          setTimeout(function () { location.href = @json(url('/home')); }, 700);
        })
        .catch(function () { btn.disabled = false; Swal.fire('Error', 'Could not pin. Please try again.', 'error'); });
    });
  })();

  // Send / re-send the confirmation email (one attendee, or everyone not sent yet)
  (function () {
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; };
    var post = function (url, data) {
      var fd = new FormData(); Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
      return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd })
        .then(function (r) { return r.json().catch(function () { return { message: 'Server error (' + r.status + ').' }; }).then(function (d) { return { ok: r.ok, d: d }; }); });
    };
    var markSent = function (id, email) {
      if (window.DbRegs) window.DbRegs.markSent(id, email);
      document.querySelectorAll('[data-email-status="' + id + '"]').forEach(function (td) { td.innerHTML = '<span class="badge bg-success">Sent</span>'; });
      if (email) document.querySelectorAll('[data-email-cell="' + id + '"]').forEach(function (td) { td.textContent = email; });
      document.querySelectorAll('.sendEmail[data-id="' + id + '"]').forEach(function (b) { b.dataset.sent = '1'; if (email) b.dataset.email = email; });
    };
    var sendUrl = @json(url('/registrations')) + '/';

    document.addEventListener('click', function (e) {
      var b = e.target.closest('.sendEmail'); if (!b) return;
      var original = b.dataset.email || '';
      Swal.fire({
        title: b.dataset.sent === '1' ? 'Send the email again?' : 'Send the confirmation email',
        html: '<div class="text-start">'
          + '<p class="mb-2">To <b>' + esc(b.dataset.name) + '</b>, with their ID card and the attachments set in Email Template.'
          + (b.dataset.sent === '1' ? ' <span class="text-muted">It was sent before; use this if they did not receive it.</span>' : '') + '</p>'
          + '<label class="form-label fw-semibold mb-1" for="seTo">Email address</label>'
          + '<input type="email" id="seTo" class="form-control" value="' + esc(original) + '" autocomplete="off">'
          + '<div class="form-check mt-2" id="seSaveWrap" style="display:none"><input class="form-check-input" type="checkbox" id="seSave" checked>'
          + '<label class="form-check-label" for="seSave">Also correct the attendee\'s email address to this one</label></div>'
          + '<div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Not arriving? Ask them to check spam / promotions, or fix a typo in the address above.</div></div>',
        showCancelButton: true, confirmButtonText: '<i class="bi bi-send"></i> Send now', focusConfirm: false, showLoaderOnConfirm: true,
        didOpen: function () {
          var to = document.getElementById('seTo'), wrap = document.getElementById('seSaveWrap');
          to.addEventListener('input', function () { wrap.style.display = to.value.trim().toLowerCase() !== original.toLowerCase() ? '' : 'none'; });
        },
        preConfirm: function () {
          var to = document.getElementById('seTo').value.trim();
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) { Swal.showValidationMessage('Enter a valid email address.'); return false; }
          return post(sendUrl + b.dataset.id + '/send-email', { email: to, save_email: document.getElementById('seSave').checked && to.toLowerCase() !== original.toLowerCase() ? 1 : 0 })
            .then(function (res) {
              if (!res.ok) { var er = res.d.errors && res.d.errors.email ? res.d.errors.email[0] : res.d.message; Swal.showValidationMessage(er || 'Could not send.'); return false; }
              return res.d;
            });
        },
        allowOutsideClick: function () { return !Swal.isLoading(); }
      }).then(function (r) {
        if (!r.isConfirmed || !r.value) return;
        markSent(b.dataset.id, r.value.email);
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: r.value.message, showConfirmButton: false, timer: 2600 });
      });
    });

    var bulk = document.getElementById('sendPending');
    if (!bulk) return;
    bulk.addEventListener('click', function () {
      var total = +bulk.dataset.count;
      Swal.fire({
        icon: 'question', title: 'Email ' + total + ' attendee' + (total === 1 ? '' : 's') + '?',
        text: 'Everyone of ' + bulk.dataset.venue + ' who has not received the confirmation email yet gets it now, with their ID card.',
        showCancelButton: true, confirmButtonText: 'Send ' + total + ' emails'
      }).then(function (r) {
        if (!r.isConfirmed) return;
        var sent = 0, failed = [], after = 0;
        Swal.fire({ title: 'Sending…', html: '<div id="spProg">0 of ' + total + ' sent</div><div class="progress mt-3" style="height:8px"><div class="progress-bar" id="spBar" style="width:0%"></div></div>', allowOutsideClick: false, showConfirmButton: false });
        (function next() {
          post(@json(route('registrations.send-pending')), { venue: bulk.dataset.venue, after_id: after }).then(function (res) {
            if (!res.ok) { Swal.fire('Stopped', res.d.message || 'Could not continue.', 'error'); return; }
            sent += res.d.sent; failed = failed.concat(res.d.failed); after = res.d.after_id;
            var done = sent + failed.length;
            document.getElementById('spProg').textContent = sent + ' of ' + total + ' sent' + (failed.length ? ' · ' + failed.length + ' failed' : '');
            document.getElementById('spBar').style.width = Math.min(100, Math.round(done / Math.max(total, 1) * 100)) + '%';
            if (res.d.remaining > 0) return next();
            Swal.fire({
              icon: failed.length ? 'warning' : 'success', title: sent + ' email' + (sent === 1 ? '' : 's') + ' sent',
              html: failed.length ? '<div class="text-start small">Not sent:<ul class="mb-0">' + failed.slice(0, 8).map(function (f) { return '<li><b>' + esc(f.name) + '</b> (' + esc(f.email) + '): ' + esc(f.error) + '</li>'; }).join('') + '</ul></div>' : '',
              confirmButtonText: 'OK'
            }).then(function () { location.reload(); });
          }).catch(function () { Swal.fire('Stopped', 'Connection lost. Emails sent so far are marked as sent; press the button again to continue.', 'error'); });
        })();
      });
    });
  })();

  // Greeting by the viewer's local time
  (function () {
    var h = new Date().getHours();
    document.getElementById('db-greeting').textContent = h < 12 ? 'Good morning' : (h < 17 ? 'Good afternoon' : 'Good evening');
  })();

  document.addEventListener('DOMContentLoaded', function () {
    if (!window.ApexCharts) return;
    var font = getComputedStyle(document.body).fontFamily;
    var charts = [];

    // Colours from the current light / dark theme
    function palette() {
      var css = getComputedStyle(document.documentElement), v = function (n) { return css.getPropertyValue(n).trim(); };
      var dark = document.documentElement.getAttribute('data-theme') === 'dark';
      return { dark: dark, brand: v('--ad-brand'), muted: v('--ad-muted'), ink: v('--ad-ink'), grid: v('--ad-line-soft'), surface: v('--ad-surface') };
    }
    function themed(p) {
      return {
        theme: { mode: p.dark ? 'dark' : 'light' },
        chart: { background: 'transparent', foreColor: p.muted },
        grid: { borderColor: p.grid },
        xaxis: { labels: { style: { colors: p.muted } } },
        yaxis: { labels: { style: { colors: p.muted }, formatter: function (v) { return Math.round(v); } } },
        legend: { labels: { colors: p.ink } },
        tooltip: { theme: p.dark ? 'dark' : 'light' }
      };
    }

    var p = palette();
    var trend = new ApexCharts(document.querySelector('#db-trend'), {
      chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: font, zoom: { enabled: false }, background: 'transparent', foreColor: p.muted },
      theme: { mode: p.dark ? 'dark' : 'light' },
      series: [{ name: 'Registrations', data: @json($series) }],
      colors: [p.brand],
      stroke: { curve: 'smooth', width: 3 },
      fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .02, stops: [0, 95] } },
      dataLabels: { enabled: false },
      grid: { borderColor: p.grid, strokeDashArray: 4 },
      xaxis: { type: 'datetime', labels: { style: { colors: p.muted }, datetimeUTC: false }, axisBorder: { show: false }, axisTicks: { show: false } },
      yaxis: { min: 0, forceNiceScale: true, labels: { style: { colors: p.muted }, formatter: function (v) { return Math.round(v); } } },
      tooltip: { x: { format: 'd MMM yyyy' }, theme: p.dark ? 'dark' : 'light' },
      markers: { size: 0, hover: { size: 5 } }
    });
    trend.render();
    charts.push({ chart: trend, extra: function (p) { return { colors: [p.brand] }; } });

    @if($professions->isNotEmpty())
    var donut = new ApexCharts(document.querySelector('#db-professions'), {
      chart: { type: 'donut', height: 270, fontFamily: font, background: 'transparent', foreColor: p.muted },
      theme: { mode: p.dark ? 'dark' : 'light' },
      series: @json($professions->pluck('n')->map(fn ($n) => (int) $n)),
      labels: @json($professions->pluck('profession')),
      colors: ['#3d7be0', '#d4467f', '#1aa6bb', '#3fa964', '#f0b429', '#8b6cff'],
      legend: { position: 'bottom', fontSize: '12.5px', markers: { radius: 12 }, labels: { colors: p.ink } },
      dataLabels: { enabled: false },
      stroke: { width: 2, colors: [p.surface] },
      tooltip: { theme: p.dark ? 'dark' : 'light' },
      plotOptions: { pie: { donut: { size: '68%', labels: { show: true, value: { color: p.ink }, total: { show: true, label: 'Total', fontWeight: 600, color: p.muted } } } } }
    });
    donut.render();
    charts.push({ chart: donut, extra: function (p) {
      return { stroke: { width: 2, colors: [p.surface] }, plotOptions: { pie: { donut: { labels: { value: { color: p.ink }, total: { color: p.muted } } } } } };
    } });
    @endif

    // Switch chart colours with the theme toggle
    document.addEventListener('bics:theme', function () {
      var p = palette();
      charts.forEach(function (c) {
        var opts = themed(p), extra = c.extra(p);
        Object.keys(extra).forEach(function (k) { opts[k] = extra[k]; });
        c.chart.updateOptions(opts, false, false);
      });
    });
  });
</script>
@endpush
