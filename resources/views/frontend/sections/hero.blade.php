@php
   use App\Support\Cms;
   use App\Support\EventSettings as Ev;
   $start = Ev::date('event_start');
   $reg = Ev::registration();
   $title = trim($c['title'] ?? '') ?: Ev::get('event_name');
   // Colour the chosen words of the headline
   $titleHtml = nl2br(e($title));
   if (!empty($c['highlight']) && str_contains($title, $c['highlight'])) {
      $titleHtml = str_replace(e($c['highlight']), '<span class="bx-grad">' . e($c['highlight']) . '</span>', $titleHtml);
   }
   $regText = [
      'open' => 'Registration open' . ($reg['closes'] ? ' until ' . $reg['closes']->format('j M Y') : ''),
      'upcoming' => 'Registration opens ' . optional($reg['opens'])->format('j M Y'),
      'closed' => 'Registration closed',
   ][$reg['state']];
   $btn1 = trim($c['button1_text'] ?? '') ?: ($reg['state'] === 'open' ? 'Register now' : 'Registration details');
   $bg = $c['image'] ?? '';
@endphp
<section class="bx-hero" @if(!empty($c['anchor'])) id="{{ \Illuminate\Support\Str::slug($c['anchor']) }}" @endif>
   @if($bg)
   <div class="bx-hero-bg" aria-hidden="true">
      <img src="{{ \App\Support\Thumb::url($bg, 1600) }}" srcset="{{ \App\Support\Thumb::srcset($bg, [800, 1600]) }}" sizes="100vw" alt="" fetchpriority="high">
   </div>
   @endif
   <div class="container">
      <div class="row g-5 align-items-center">
         <div class="col-lg-7 col-xl-8">
            <div class="bx-hero-kicker">
               @if(!empty($c['kicker']))<span>{{ $c['kicker'] }}</span>@endif
               <span class="bx-pill {{ $reg['state'] }}"><span class="dot"></span>{{ $regText }}</span>
            </div>
            <h1>{!! $titleHtml !!}</h1>
            @if(!empty($c['theme']))<p class="bx-hero-theme">{!! nl2br(e($c['theme'])) !!}</p>@endif
            <div class="bx-hero-meta">
               @if(Ev::datesLabel())<span><i class="bi bi-calendar-event"></i>{{ Ev::datesLabel() }}</span>@endif
               @if(Ev::get('event_venue'))<span><i class="bi bi-geo-alt"></i>{{ Ev::get('event_venue') }}</span>@endif
            </div>
            <div class="bx-hero-actions">
               <a class="bx-btn bx-btn-accent" href="{{ Cms::link(($c['button1_url'] ?? '') ?: 'registration') }}">{{ $btn1 }} <i class="bi bi-arrow-right"></i></a>
               @if(!empty($c['button2_text']))<a class="bx-btn bx-btn-ghost" href="{{ Cms::link($c['button2_url'] ?? '') }}">{{ $c['button2_text'] }}</a>@endif
            </div>
         </div>
         @if(!empty($c['show_countdown']) && $start)
         <div class="col-lg-5 col-xl-4">
            <div class="bx-hero-card">
               <div class="bx-hero-card-title">{{ $c['countdown_title'] ?? 'The summit starts in' }}</div>
               @if($start->isFuture())
               <div class="bx-countdown" data-countdown="{{ $start->toIso8601String() }}" data-done="{{ $c['finished_text'] ?? '' }}" role="timer" aria-label="Time until the summit starts">
                  <div><b data-u="d">0</b><small>Days</small></div>
                  <div><b data-u="h">00</b><small>Hours</small></div>
                  <div><b data-u="m">00</b><small>Min</small></div>
                  <div><b data-u="s">00</b><small>Sec</small></div>
               </div>
               @else
               <div class="bx-countdown-done">{{ $c['finished_text'] ?? '' }}</div>
               @endif
               <div class="small mt-3" style="color:rgba(255,255,255,.7)"><i class="bi bi-clock"></i> {{ $start->format('l, j F Y · H:i') }} ({{ $start->format('T') }})</div>
            </div>
         </div>
         @endif
      </div>
   </div>
</section>
