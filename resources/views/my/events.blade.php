@extends('my.layout')
@section('title', 'My events')
@section('heading', 'My events')
@section('sub', $totals['registered'] ? $totals['attended'] . ' attended · ' . $totals['registered'] . ' registered' . ($totals['years'] > 1 ? ' · across ' . $totals['years'] . ' years' : '') : 'Your registrations will appear here')
@php $gift = ['received' => ['green', 'bi-gift', 'Gift received'], 'pending' => ['gray', 'bi-gift', 'Gift not collected yet'], 'none' => ['gray', 'bi-gift', 'No gift']]; @endphp
@push('styles')
<style>
  .filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
  .filters button { border: 1px solid var(--line); background: #fff; border-radius: 30px; padding: 6px 14px; font-weight: 700; font-size: 13.5px; color: var(--text); }
  .filters button.on { background: var(--ink); color: #fff; border-color: var(--ink); }
  .ev { display: grid; grid-template-columns: 92px 1fr; gap: 0; padding: 0; overflow: hidden; }
  .ev-date { background: linear-gradient(180deg, #0a2350, #0e4ca1); color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 14px 6px; }
  .ev-date .m { font-size: 12px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; opacity: .8; }
  .ev-date .d { font-size: 2rem; font-weight: 800; line-height: 1; }
  .ev-date .y { font-size: 13px; font-weight: 700; opacity: .85; }
  .ev.past .ev-date { background: linear-gradient(180deg, #46506a, #67718a); }
  .ev.live .ev-date { background: linear-gradient(180deg, #8a1d52, #b22e6b); }
  .ev-state { display: flex; gap: 10px; align-items: center; border-radius: 12px; padding: 10px 12px; margin-top: 12px; font-size: 14px; }
  .ev-state i { font-size: 1.15rem; }
  .ev-state.live { background: #fbeaf2; color: #8a1d52; } .ev-state.upcoming { background: #eaf2fd; color: #0b3f86; } .ev-state.ended { background: var(--soft); color: var(--muted); }
  .ev-state b { color: inherit; }
  .pill.live { background: #b22e6b; color: #fff; }
  .live-dot { width: 8px; height: 8px; border-radius: 50%; background: #fff; display: inline-block; animation: liveDot 1.6s infinite; }
  @keyframes liveDot { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
  @media (prefers-reduced-motion: reduce) { .live-dot { animation: none; } }
  .ev-body { padding: 18px 20px; min-width: 0; }
  .ev-body h3 { font-size: 1.1rem; font-weight: 800; margin: 0 0 2px; }
  .facts { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 14px 0; }
  .fact { background: var(--soft); border-radius: 12px; padding: 10px 12px; min-width: 0; }
  .fact span { display: block; font-size: 11.5px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
  .fact b { color: var(--ink); font-size: 14px; font-weight: 700; word-break: break-word; }
  .prize { display: flex; gap: 12px; align-items: center; border-radius: 14px; padding: 12px 14px; margin-bottom: 14px; background: linear-gradient(135deg, #fff6df, #fde9f1); border: 1px solid #f6dcb0; }
  .prize i { font-size: 1.6rem; color: var(--gold); }
  .actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
  .locked-act { display: inline-flex; gap: 6px; align-items: center; font-size: 13px; color: var(--muted); background: var(--soft); border-radius: 11px; padding: 7px 12px; }
  @media (max-width: 767px) { .ev { grid-template-columns: 1fr; } .ev-date { flex-direction: row; gap: 8px; padding: 10px; justify-content: flex-start; } .ev-date .d { font-size: 1.3rem; } .facts { grid-template-columns: 1fr 1fr; } .ev-body { padding: 16px; } }
</style>
@endpush
@section('content')
@if($events->count())
<div class="filters" role="tablist">
  <button class="on" data-f="all">All ({{ $events->count() }})</button>
  <button data-f="upcoming">Upcoming ({{ $events->where('upcoming', true)->count() }})</button>
  <button data-f="ended">Ended ({{ $events->where('ended', true)->count() }})</button>
  <button data-f="attended">Attended ({{ $events->where('checked_in', true)->count() }})</button>
  <button data-f="certificate">With certificate ({{ $events->filter(fn ($e) => $e->certificate)->count() }})</button>
</div>
@endif

@forelse($events as $e)
<article class="panel ev {{ $e->live ? 'live' : ($e->upcoming ? '' : 'past') }}" data-upcoming="{{ $e->upcoming ? 1 : 0 }}" data-ended="{{ $e->ended ? 1 : 0 }}" data-attended="{{ $e->checked_in ? 1 : 0 }}" data-certificate="{{ $e->certificate ? 1 : 0 }}">
  <div class="ev-date">
    @if($e->start)<div class="m">{{ $e->start->format('M') }}</div><div class="d">{{ $e->start->format('j') }}</div><div class="y">{{ $e->start->format('Y') }}</div>
    @else<div class="m">Year</div><div class="d">{{ $e->year }}</div>@endif
  </div>
  <div class="ev-body">
    <div class="d-flex flex-wrap gap-2 align-items-start justify-content-between">
      <div class="min-w-0"><h3>{{ $e->name }}</h3><div class="muted small">{{ $e->dates ?: $e->short }}{{ $e->venue ? ' · ' . $e->venue : '' }}</div></div>
      <div class="d-flex flex-wrap gap-1">
        @if($e->live)<span class="pill live"><span class="live-dot"></span> Live now</span>
        @elseif($e->upcoming)<span class="pill blue"><i class="bi bi-hourglass-split"></i> Upcoming</span>
        @else<span class="pill gray"><i class="bi bi-flag-fill"></i> Event ended</span>@endif
        @if($e->checked_in)<span class="pill green"><i class="bi bi-check2-circle"></i> Attended</span>@elseif($e->ended)<span class="pill gray">Not checked in</span>@endif
        <span class="pill {{ $e->approved ? 'green' : ($e->status === 'Rejected' ? 'red' : 'amber') }}">{{ $e->approved ? 'Approved' : $e->status }}</span>
      </div>
    </div>

    <div class="facts">
      <div class="fact"><span>Registration no.</span><b>{{ $e->registration_no ?: '—' }}</b></div>
      <div class="fact"><span>Registered</span><b>{{ $e->registered_at?->format('j M Y') ?? '—' }}</b></div>
      <div class="fact"><span>Check-in</span><b>{{ $e->checked_in ? ($e->checked_in_at ? $e->checked_in_at->format('j M, g:i a') : 'Yes') : ($e->upcoming ? 'At the venue' : 'No') }}</b></div>
      <div class="fact"><span>Welcome gift</span><b>{{ $gift[$e->gift][2] ?? 'Not collected yet' }}</b></div>
    </div>

    @if($e->live)
      <div class="ev-state live"><i class="bi bi-broadcast"></i><div>
        @if($e->checked_in)<b>Happening now — you're checked in.</b> Enjoy the summit!
        @else<b>Happening now.</b> Check in at the venue: scan the check-in poster and type your registration number <b>{{ $e->registration_no }}</b>.@endif
      </div></div>
    @elseif($e->upcoming)
      <div class="ev-state upcoming"><i class="bi bi-calendar-event"></i><div>
        @if($e->days_to === null)<b>Coming up.</b> Dates will be announced.
        @elseif($e->days_to === 0)<b>Starts today!</b>
        @elseif($e->days_to === 1)<b>Starts tomorrow.</b>
        @else<b>Starts in {{ $e->days_to }} days.</b>@endif
        Bring your registration number <b>{{ $e->registration_no }}</b> to check in.
      </div></div>
    @else
      <div class="ev-state ended"><i class="bi bi-flag"></i><div>
        <b>This event has ended{{ $e->ended_on ? ' (' . $e->ended_on . ')' : '' }}.</b>
        {{ $e->checked_in ? 'Thank you for joining us!' : 'We missed you this time — we hope to see you at the next summit.' }}
      </div></div>
    @endif

    @foreach($e->prizes as $w)
    <div class="prize"><i class="bi bi-trophy-fill"></i><div><div class="fw-bold ink">You won {{ $w->prize ?: 'a prize' }}!</div><div class="small muted">{{ $w->type ? $w->type . ' · ' : '' }}Raffle draw{{ $w->at ? ' on ' . $w->at->format('j M Y') : '' }}. Please collect it from the organizers' desk.</div></div></div>
    @endforeach

    <div class="actions">
      @if($e->id_card)
        <a class="btn btn-primary" href="{{ route('my.idcard', $e->id) }}" target="_blank"><i class="bi bi-person-badge"></i> ID card (PDF)</a>
        @if($e->id_card_at)<span class="small muted me-2">issued {{ $e->id_card_at->format('j M Y') }}</span>@endif
      @else
        <span class="locked-act"><i class="bi bi-hourglass"></i> Your ID card will appear here when the organizers issue it</span>
      @endif
      @if($e->certificate)
        <a class="btn btn-outline-primary" href="{{ route('certificate.show', $e->certificate->token) }}" target="_blank"><i class="bi bi-award"></i> Certificate</a>
        <a class="btn btn-soft" href="{{ route('certificate.show', $e->certificate->token) }}/pdf"><i class="bi bi-download"></i> PDF</a>
        <span class="small muted">No. {{ $e->certificate->number }}{{ $e->certificate->issued_at ? ' · issued ' . $e->certificate->issued_at->format('j M Y') : '' }}</span>
      @elseif($e->checked_in)
        <span class="locked-act"><i class="bi bi-award"></i> Certificate will appear here when the organizers issue it</span>
      @elseif(!$e->upcoming)
        <span class="locked-act"><i class="bi bi-award"></i> Certificates are for checked-in participants</span>
      @endif
    </div>
  </div>
</article>
@empty
<div class="panel text-center py-5">
  <div style="font-size:2.6rem;color:var(--blue)"><i class="bi bi-calendar-plus"></i></div>
  <h3 class="h5 fw-bold mt-2">No events yet</h3>
  <p class="muted mb-3">When you register for an event with {{ $p->email }}, it shows up here with your ID card and certificate.</p>
  <a class="btn btn-primary" href="{{ url('/') }}">See upcoming events</a>
</div>
@endforelse
<div class="panel text-center muted small d-none" id="ev-none">No events in this list.</div>
@endsection
@push('scripts')
<script>
  document.querySelectorAll('[data-f]').forEach(function (b) { b.addEventListener('click', function () {
    document.querySelectorAll('[data-f]').forEach(function (x) { x.classList.toggle('on', x === b); });
    var shown = 0;
    document.querySelectorAll('article.ev').forEach(function (a) { var ok = b.dataset.f === 'all' || a.dataset[b.dataset.f] === '1'; a.classList.toggle('d-none', !ok); if (ok) shown++; });
    document.getElementById('ev-none').classList.toggle('d-none', shown > 0);
  }); });
</script>
@endpush
