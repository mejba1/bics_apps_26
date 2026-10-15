{{-- Brand panel of the sign-in pages (participant /my/login and admin /login): the whole BICS platform —
     every summit, not just the current one. Usage: @include('partials.auth-brand', ['audience' => 'participant'|'admin'])
     Styles: assets/frontend/css/auth-brand.css --}}
@php
  $stats = \App\Support\PlatformStats::summary();
  $isAdmin = ($audience ?? 'participant') === 'admin';
  $features = $isAdmin
      ? [['bi-people', 'Registrations & check-in', 'for every summit and venue'], ['bi-person-badge', 'Participant profiles', 'one lifelong ID, QR & NFC cards'],
         ['bi-award', 'Certificates & ID cards', 'design, assign and print'], ['bi-graph-up-arrow', 'Reports & analytics', 'across all events']]
      : [['bi-calendar2-check', 'All your summits', 'past, current and upcoming'], ['bi-person-badge', 'ID cards & certificates', 'when the organizers assign them'],
         ['bi-qr-code', 'Digital profile', 'QR code and NFC card'], ['bi-shield-lock', 'Your privacy', 'you decide what is public']];
@endphp
<div class="ab">
  <div class="ab-top">
    <a class="ab-logo" href="{{ url('/') }}" title="Beacon International Cancer Summit — website"><img src="{{ asset('assets/frontend/images/logo.png') }}" alt="BICS"></a>
    <span class="ab-tag"><i class="bi {{ $isAdmin ? 'bi-shield-lock-fill' : 'bi-person-circle' }}"></i> {{ $isAdmin ? 'Admin console' : 'Participant portal' }}</span>
  </div>

  <div class="ab-main">
    <div class="ab-eyebrow">Beacon International Cancer Summit</div>
    <h2 class="ab-title">{!! $isAdmin ? 'Run every BICS summit <br>from one place.' : 'One account for <br>every BICS summit.' !!}</h2>
    <p class="ab-lead">{{ $isAdmin
        ? 'Registrations, check-in, participant profiles, certificates, the raffle and the website — for all events, past and upcoming.'
        : 'Your profile, ID cards, certificates and event history stay with you from one summit to the next.' }}</p>

    @if($stats['events'] || $stats['participants'])
    <div class="ab-stats">
      <div><b>{{ $stats['events'] }}</b><span>{{ \Illuminate\Support\Str::plural('Summit', $stats['events']) }}</span></div>
      <div><b>{{ \App\Support\PlatformStats::short($stats['participants']) }}</b><span>Participants</span></div>
      <div><b>{{ $stats['countries'] }}</b><span>Countries</span></div>
    </div>
    @endif

    @if(count($stats['timeline']) > 1)
    <div class="ab-journey" aria-label="BICS summits">
      @foreach(array_slice($stats['timeline'], -5) as $e)
        <div class="ab-stop {{ $e['current'] ? 'is-now' : '' }}">
          <span class="ab-dot">@if($e['current'])<i class="bi bi-broadcast"></i>@endif</span>
          <b>{{ $e['short'] }}</b>
          <small>{{ $e['current'] ? 'Current' : ($e['upcoming'] ? 'Upcoming' : 'Past') }}</small>
        </div>
      @endforeach
    </div>
    @endif

    <ul class="ab-features">
      @foreach($features as [$icon, $title, $sub])
        <li><i class="bi {{ $icon }}"></i><div><b>{{ $title }}</b><span>{{ $sub }}</span></div></li>
      @endforeach
    </ul>
  </div>

  <div class="ab-foot">© {{ date('Y') }} BICS · Organized by Beacon Pharma Ltd.</div>
</div>
