@extends('my.layout')
@section('title', 'Overview')
@section('heading', 'Welcome back, ' . ($p->first_name ?: 'friend'))
@section('sub', $totals['since'] ? 'With ' . (\App\Support\EventSettings::get('event_short') ?: 'BICS') . ' since ' . $totals['since'] . ' · Participant ID ' . $p->code : 'Participant ID ' . $p->code)
@section('actions')
  <a class="btn btn-soft d-none d-md-inline-flex align-items-center gap-1" href="{{ $p->url() }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Public profile</a>
@endsection
@php
  $photo = $p->photo && is_file(base_path($p->photo)) ? \App\Support\Thumb::url($p->photo, 480) : null;
  $pct = $complete['percent'];
  $circ = 2 * M_PI * 30;
  $nfc = !$card ? ['gray', 'No NFC card yet'] : match ($card->status) {
      'active' => ['green', 'Active · ' . $card->card_no], 'assigned' => ['blue', 'Being prepared · ' . $card->card_no],
      default => ['red', 'Switched off · ' . $card->card_no],
  };
  $tones = ['blue' => '#0e4ca1', 'green' => '#2f8a4c', 'gold' => '#c98a12', 'magenta' => '#b22e6b', 'teal' => '#1f8fb3', 'gray' => '#8a93a6'];
  $tiles = [
      ['bi-door-open', $totals['attended'], 'Events attended', $totals['registered'] ? 'of ' . $totals['registered'] . ' registered' : 'none yet', 'blue'],
      ['bi-calendar2-heart', $totals['with_us'] ?: '—', 'Years with us', $totals['since'] ? 'since ' . $totals['since'] : '', 'teal'],
      ['bi-person', $totals['age'] ?? '—', 'Age', $totals['age'] ? 'years old' : ($canEdit ? 'add your date of birth' : ''), 'gray'],
      ['bi-award', $totals['certificates'], 'Certificates', $totals['certificates'] ? 'ready to download' : 'after you attend', 'gold'],
      ['bi-trophy', $totals['prizes'], 'Prizes won', $totals['prizes'] ? 'congratulations!' : 'raffle draws', 'magenta'],
      ['bi-eye', number_format($views), 'Profile views', 'link, QR & NFC', 'green'],
  ];
@endphp
@push('styles')
<style>
  .hero { position: relative; overflow: hidden; border-radius: 22px; padding: 22px; color: #fff; display: flex; gap: 20px; align-items: center;
    background: radial-gradient(520px 260px at 100% 0%, rgba(178,46,107,.55), transparent 60%), radial-gradient(420px 260px at 0% 120%, rgba(31,143,179,.45), transparent 60%), linear-gradient(135deg, #0a2350, #0e4ca1); box-shadow: 0 20px 40px -24px rgba(10,35,80,.7); margin-bottom: 18px; }
  .hero .av { width: 78px; height: 78px; border-radius: 50%; border: 3px solid rgba(255,255,255,.85); object-fit: cover; background: #e9eff9; color: var(--blue); font-weight: 800; font-size: 1.6rem; display: flex; align-items: center; justify-content: center; flex: none; }
  .hero h2 { color: #fff; font-size: 1.25rem; font-weight: 800; margin: 0 0 2px; }
  .hero .role { opacity: .85; font-size: 14px; }
  .hero .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
  .hero .chips span { background: rgba(255,255,255,.14); border-radius: 30px; padding: 4px 10px; font-size: 12px; font-weight: 700; }
  .ring { margin-left: auto; display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,.1); border-radius: 16px; padding: 10px 14px; text-decoration: none; color: #fff; }
  .ring:hover { background: rgba(255,255,255,.16); color: #fff; }
  .ring b { font-size: 14px; display: block; } .ring small { opacity: .8; font-size: 12px; }
  .tiles { display: grid; grid-template-columns: repeat(6, 1fr); gap: 14px; margin-bottom: 18px; }
  .tile { background: #fff; border-radius: 18px; padding: 16px; box-shadow: var(--shadow); border: 1px solid rgba(231,235,243,.7); }
  .tile .ic { width: 38px; height: 38px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; margin-bottom: 10px; }
  .tile b { display: block; font-size: 1.6rem; font-weight: 800; color: var(--ink); line-height: 1.05; }
  .tile .l { font-weight: 700; color: var(--ink); font-size: 13.5px; margin-top: 2px; }
  .tile .s { font-size: 12px; color: var(--muted); }
  .next { border-radius: 18px; background: linear-gradient(135deg, #fff, #f3f7ff); border: 1px solid #dce6f7; padding: 18px; display: flex; gap: 18px; align-items: center; }
  .date-badge { width: 74px; flex: none; text-align: center; border-radius: 16px; background: var(--blue); color: #fff; padding: 10px 6px; box-shadow: 0 10px 20px -12px rgba(14,76,161,.8); }
  .date-badge .m { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; opacity: .85; }
  .date-badge .d { font-size: 1.9rem; font-weight: 800; line-height: 1; }
  .count { font-size: 13px; font-weight: 800; color: var(--magenta); }
  .ev-row { display: flex; gap: 12px; align-items: center; padding: 12px 0; border-top: 1px solid var(--line); }
  .ev-row:first-child { border-top: 0; padding-top: 0; }
  .ev-row .yr { width: 52px; height: 52px; border-radius: 14px; background: var(--soft); color: var(--ink); font-weight: 800; display: flex; align-items: center; justify-content: center; flex: none; }
  .kv { display: flex; justify-content: space-between; gap: 10px; padding: 9px 0; border-top: 1px solid var(--line); font-size: 14px; }
  .kv:first-child { border-top: 0; padding-top: 0; }
  .kv span { color: var(--muted); } .kv b { color: var(--ink); text-align: right; font-weight: 700; }
  .tl { list-style: none; padding: 0; margin: 0; position: relative; }
  .tl li { display: flex; gap: 12px; padding-bottom: 14px; position: relative; }
  .tl li:not(:last-child)::before { content: ''; position: absolute; left: 15px; top: 32px; bottom: 0; width: 2px; background: var(--line); }
  .tl .dot { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; flex: none; font-size: 15px; }
  .tl b { font-size: 14px; color: var(--ink); font-weight: 700; display: block; }
  .tl small { color: var(--muted); }
  .badges { display: flex; flex-wrap: wrap; gap: 8px; }
  .badges span { display: inline-flex; align-items: center; gap: 6px; background: var(--soft); border-radius: 30px; padding: 6px 12px; font-size: 13px; font-weight: 700; color: var(--ink); }
  .badges i { color: var(--gold); }
  @media (max-width: 1199px) { .tiles { grid-template-columns: repeat(3, 1fr); } }
  @media (max-width: 767px) {
    .hero { flex-wrap: wrap; } .ring { margin-left: 0; width: 100%; }
    .tiles { grid-template-columns: repeat(2, 1fr); gap: 10px; } .tile { padding: 14px; }
    .next { flex-direction: column; align-items: flex-start; }
  }
</style>

<style>
  .bday { position: relative; overflow: hidden; display: flex; gap: 22px; align-items: center; border-radius: 22px; padding: 22px; margin-bottom: 18px; color: #fff;
    background: radial-gradient(420px 220px at 100% 0%, rgba(255,210,122,.35), transparent 70%), linear-gradient(135deg, #0a1f4d, #6a1d55 70%, #b22e6b); box-shadow: 0 18px 40px -24px rgba(106,29,85,.8); }
  .bday-text { flex: 1; min-width: 0; }
  .bday-eyebrow { font-size: 12px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #ffd27a; margin-bottom: 6px; }
  .bday h2 { color: #fff; font-weight: 800; font-size: 1.6rem; margin: 0 0 6px; letter-spacing: -.01em; }
  .bday p { color: rgba(255,255,255,.85); margin: 0 0 14px; max-width: 460px; }
  .bday-card { width: 46%; max-width: 420px; border-radius: 14px; box-shadow: 0 16px 30px -14px rgba(0,0,0,.6); transform: rotate(-1.5deg); }
  @media (max-width: 767.98px) { .bday { flex-direction: column; align-items: stretch; } .bday-card { width: 100%; max-width: none; transform: none; order: -1; } }
</style>
@endpush
@section('content')
@if($birthday)
<div class="bday" role="region" aria-label="Happy birthday">
  <div class="bday-text">
    <div class="bday-eyebrow">🎉 Today is your day</div>
    <h2>Happy birthday, {{ $p->first_name }}!</h2>
    <p>Everyone at the Beacon International Cancer Summit wishes you a wonderful year ahead. Here is a card made just for you.</p>
    <a class="btn btn-light btn-sm fw-bold" href="{{ route('my.birthday-card', ['download' => 1]) }}"><i class="bi bi-download"></i> Save my card</a>
  </div>
  <img class="bday-card" src="{{ route('my.birthday-card') }}" alt="Your birthday card" loading="lazy">
</div>
@endif
<div class="hero">
  @if($photo)<img class="av" src="{{ $photo }}" alt="">@else<span class="av">{{ $p->initials }}</span>@endif
  <div class="min-w-0">
    <h2>{{ $p->name }}</h2>
    <div class="role">{{ collect([$p->designation, $p->organization])->filter()->implode(' · ') ?: 'Add your designation and organization' }}</div>
    <div class="chips"><span><i class="bi bi-person-vcard"></i> {{ $p->code }}</span>@if($p->category)<span>{{ $p->category }}</span>@endif @if($p->country)<span><i class="bi bi-geo-alt"></i> {{ $p->country }}</span>@endif</div>
  </div>
  <a class="ring" href="{{ $canEdit ? route('my.edit') : route('my.card') }}">
    <svg width="72" height="72" viewBox="0 0 72 72" aria-hidden="true"><circle cx="36" cy="36" r="30" fill="none" stroke="rgba(255,255,255,.2)" stroke-width="7"/>
      <circle cx="36" cy="36" r="30" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round" stroke-dasharray="{{ round($circ * $pct / 100, 1) }} {{ round($circ, 1) }}" transform="rotate(-90 36 36)"/>
      <text x="36" y="41" text-anchor="middle" font-size="15" font-weight="800" fill="#fff" font-family="Plus Jakarta Sans, sans-serif">{{ $pct }}%</text></svg>
    <div><b>{{ $pct === 100 ? 'Profile complete' : 'Profile ' . $pct . '% complete' }}</b><small>{{ $pct === 100 ? 'Looking great!' : 'Add ' . strtolower(implode(', ', array_slice($complete['missing'], 0, 2))) . (count($complete['missing']) > 2 ? '…' : '') }}</small></div>
  </a>
</div>

<div class="tiles">
  @foreach($tiles as [$icon, $value, $label, $sub, $tone])
  <div class="tile"><span class="ic" style="background:{{ $tones[$tone] }}1a;color:{{ $tones[$tone] }}"><i class="bi {{ $icon }}"></i></span><b>{{ $value }}</b><div class="l">{{ $label }}</div><div class="s">{{ $sub }}</div></div>
  @endforeach
</div>

<div class="row g-3">
  <div class="col-xl-8">
    @if($next)
    <div class="panel">
      <div class="panel-h"><span class="ico"><i class="bi {{ $next->live ? 'bi-broadcast' : 'bi-stars' }}"></i></span><h2>{{ $next->live ? 'Happening now' : 'Your next event' }}</h2>
        @if($next->live)<span class="pill ms-auto" style="background:#b22e6b;color:#fff">● Live</span>@endif</div>
      <div class="next">
        <div class="date-badge"><div class="m">{{ $next->start->format('M') }}</div><div class="d">{{ $next->start->format('j') }}</div><div class="m">{{ $next->start->format('Y') }}</div></div>
        <div class="flex-grow-1 min-w-0">
          @php $days = (int) ($next->days_to ?? 0); /* counted in the event's time zone */ @endphp
          <div class="count">{{ $next->live ? 'Happening now' : ($days > 1 ? 'In ' . $days . ' days' : ($days === 1 ? 'Tomorrow' : 'Today')) }}</div>
          <h3 class="h5 fw-bold mb-1">{{ $next->name }}</h3>
          <div class="muted small">{{ $next->dates }}{{ $next->venue ? ' · ' . $next->venue : '' }}</div>
          <div class="d-flex flex-wrap gap-1 mt-2">
            <span class="pill gray"># {{ $next->registration_no }}</span>
            <span class="pill {{ $next->approved ? 'green' : ($next->status === 'Rejected' ? 'red' : 'amber') }}">{{ $next->approved ? 'Registration approved' : 'Registration ' . strtolower($next->status) }}</span>
          </div>
        </div>
        <div class="d-flex flex-column gap-2">
          @if($next->id_card)<a class="btn btn-primary" href="{{ route('my.idcard', $next->id) }}" target="_blank"><i class="bi bi-person-badge"></i> ID card</a>@endif
          <a class="btn btn-soft" href="{{ route('my.events') }}">Details</a>
        </div>
      </div>
    </div>
    @endif

    <div class="panel">
      <div class="panel-h"><span class="ico"><i class="bi bi-calendar-event"></i></span><h2>My events</h2><a class="more" href="{{ route('my.events') }}">See all <i class="bi bi-arrow-right"></i></a></div>
      @forelse($recent as $e)
        <div class="ev-row">
          <div class="yr">{{ $e->year }}</div>
          <div class="flex-grow-1 min-w-0"><div class="fw-bold ink text-truncate">{{ $e->name }}</div>
            <div class="d-flex flex-wrap gap-1 mt-1">
              @if($e->live)<span class="pill" style="background:#b22e6b;color:#fff">● Live now</span>@elseif($e->upcoming)<span class="pill blue">Upcoming</span>@else<span class="pill gray"><i class="bi bi-flag-fill"></i> Ended</span>@endif
              @if($e->checked_in)<span class="pill green"><i class="bi bi-check2-circle"></i> Attended</span>@endif
              @if($e->certificate)<span class="pill gold"><i class="bi bi-award"></i> Certificate</span>@endif
              @foreach($e->prizes as $w)<span class="pill magenta"><i class="bi bi-trophy"></i> {{ $w->prize }}</span>@endforeach
            </div></div>
          @if($e->certificate)<a class="btn btn-sm btn-outline-primary" href="{{ route('certificate.show', $e->certificate->token) }}" target="_blank">Certificate</a>
          @elseif($e->id_card)<a class="btn btn-sm btn-outline-primary" href="{{ route('my.idcard', $e->id) }}" target="_blank">ID card</a>@endif
        </div>
      @empty
        <div class="muted">You have not registered for an event yet.</div>
      @endforelse
    </div>

    @if($milestones)
    <div class="panel">
      <div class="panel-h"><span class="ico"><i class="bi bi-patch-check"></i></span><h2>Milestones</h2></div>
      <div class="badges">@foreach($milestones as [$i, $t])<span><i class="bi {{ $i }}"></i> {{ $t }}</span>@endforeach</div>
    </div>
    @endif
  </div>

  <div class="col-xl-4">
    <div class="panel">
      <div class="panel-h"><span class="ico"><i class="bi bi-person-badge"></i></span><h2>My account</h2></div>
      <div class="kv"><span>Participant ID</span><b>{{ $p->code }}</b></div>
      @if($p->birth_date)<div class="kv"><span>Date of birth</span><b>{{ $p->birth_date->format('j M Y') }} <span class="muted fw-normal">({{ $totals['age'] }})</span></b></div>@endif
      <div class="kv"><span>NFC card</span><b><span class="pill {{ $nfc[0] }}">{{ $nfc[1] }}</span></b></div>
      <div class="kv"><span>Previous sign-in</span><b>{{ $previousLogin ? \Carbon\Carbon::parse($previousLogin->created_at)->diffForHumans() : 'First time — welcome!' }}</b></div>
      <div class="kv"><span>Password</span><b>{{ $p->password ? 'Changed ' . $p->password_changed_at?->diffForHumans() : 'Not set (code sign-in)' }}</b></div>
      <div class="kv"><span>Profile created</span><b>{{ $p->created_at?->format('j M Y') }}</b></div>
      <a class="btn btn-soft w-100 mt-2" href="{{ route('my.security') }}"><i class="bi bi-shield-lock"></i> Security settings</a>
    </div>

    <div class="panel">
      <div class="panel-h"><span class="ico"><i class="bi bi-clock-history"></i></span><h2>Recent activity</h2></div>
      <ul class="tl">
        @forelse($timeline as $t)
          <li><span class="dot" style="background:{{ $tones[$t['tone']] }}1a;color:{{ $tones[$t['tone']] }}"><i class="bi {{ $t['icon'] }}"></i></span>
            <div><b>{{ $t['text'] }}</b><small>{{ $t['at']->format('j M Y') }} · {{ $t['at']->diffForHumans() }}</small></div></li>
        @empty
          <li class="muted">Nothing yet.</li>
        @endforelse
      </ul>
    </div>
  </div>
</div>
@endsection
