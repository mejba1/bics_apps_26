@php
   use App\Support\Cms;
   use App\Support\EventSettings as Ev;
   $start = Ev::date('event_start');
   $reg = Ev::registration();
   $id = 'evc-' . $section->id;
   $fmt = fn ($d) => $d ? $d->format('j F Y, H:i') : '';
@endphp
@if($start)
@once
@push('styles')
<style>
   .evc { margin: 34px auto; }
   .evc-card { position: relative; overflow: hidden; border-radius: 22px; color: #fff; padding: 30px 22px;
      background: radial-gradient(circle at 90% 10%, rgba(255,255,255,.14), transparent 40%), linear-gradient(120deg, #0a3a7c, #0e4ca1 50%, #b22e6b);
      box-shadow: 0 22px 50px -24px rgba(14, 40, 90, .7); }
   .evc-eyebrow { font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; opacity: .85; }
   .evc-title { color: #fff; font-weight: 800; font-size: clamp(1.35rem, 3vw, 2rem); margin: 6px 0 10px; }
   .evc-meta { display: flex; flex-wrap: wrap; gap: 8px 20px; font-size: 15px; opacity: .95; }
   .evc-meta i { color: #ffd28a; margin-right: 6px; }
   .evc-clock { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; max-width: 460px; }
   .evc-unit { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); border-radius: 14px; padding: 12px 6px; text-align: center; backdrop-filter: blur(3px); }
   .evc-num { display: block; font-size: clamp(1.6rem, 4vw, 2.4rem); font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
   .evc-lbl { display: block; font-size: 11.5px; text-transform: uppercase; letter-spacing: .08em; opacity: .85; margin-top: 6px; }
   .evc-done { font-size: 1.25rem; font-weight: 800; }
   .evc-reg { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 18px; }
   .evc-badge { display: inline-flex; align-items: center; gap: 7px; border-radius: 999px; padding: 7px 14px; font-size: 13.5px; font-weight: 700; }
   .evc-badge.open { background: #e7f6e2; color: #2f6b1c; }
   .evc-badge.upcoming { background: #fff4d6; color: #7a5410; }
   .evc-badge.closed { background: rgba(255,255,255,.18); color: #fff; }
   .evc-btn { background: #fff; color: #0e4ca1 !important; font-weight: 800; border-radius: 12px; padding: 11px 22px; text-decoration: none; box-shadow: 0 10px 22px -12px rgba(0,0,0,.5); }
   .evc-btn:hover { background: #f1f6ff; }
   @media (min-width: 992px) { .evc-card { padding: 38px 44px; } }
</style>
@endpush
@push('scripts')
<script>
   // Live countdowns for every .evc-clock on the page (target = data-target, ISO date with timezone)
   (function () {
      var clocks = document.querySelectorAll('[data-evc-target]');
      function pad(n) { return (n < 10 ? '0' : '') + n; }
      function tick() {
         clocks.forEach(function (c) {
            var left = Math.max(0, Math.floor((new Date(c.dataset.evcTarget) - new Date()) / 1000));
            if (!left && c.dataset.evcDone) { var done = document.createElement('div'); done.className = 'evc-done'; done.textContent = c.dataset.evcDone; c.replaceWith(done); return; }
            c.querySelector('[data-u=d]').textContent = Math.floor(left / 86400);
            c.querySelector('[data-u=h]').textContent = pad(Math.floor(left % 86400 / 3600));
            c.querySelector('[data-u=m]').textContent = pad(Math.floor(left % 3600 / 60));
            c.querySelector('[data-u=s]').textContent = pad(left % 60);
         });
      }
      tick(); setInterval(function () { clocks = document.querySelectorAll('[data-evc-target]'); tick(); }, 1000);
   })();
</script>
@endpush
@endonce
<div class="container evc" id="{{ $id }}">
   <div class="evc-card">
      <div class="row g-4 align-items-center">
         <div class="col-lg-6">
            @if(!empty($c['subheading']))<div class="evc-eyebrow">{{ $c['subheading'] }}</div>@endif
            <h2 class="evc-title">{{ ($c['heading'] ?? '') ?: Ev::get('event_name') }}</h2>
            <div class="evc-meta">
               <span><i class="bi bi-calendar-event-fill"></i>{{ Ev::datesLabel() }}</span>
               @if(!empty($c['show_venue']) && Ev::get('event_venue'))<span><i class="bi bi-geo-alt-fill"></i>{{ Ev::get('event_venue') }}</span>@endif
            </div>
            @if(!empty($c['show_registration']))
            <div class="evc-reg">
               @if($reg['state'] === 'open')
                  <span class="evc-badge open"><i class="bi bi-circle-fill" style="font-size:8px"></i> Registration open{{ $reg['closes'] ? ' until ' . $fmt($reg['closes']) : '' }}</span>
                  @if(!empty($c['button_text']))<a class="evc-btn" href="{{ Cms::link($c['button_url'] ?? 'registration') }}">{{ $c['button_text'] }} <i class="bi bi-arrow-right"></i></a>@endif
               @elseif($reg['state'] === 'upcoming')
                  <span class="evc-badge upcoming"><i class="bi bi-hourglass-split"></i> Registration opens {{ $fmt($reg['opens']) }}</span>
               @else
                  <span class="evc-badge closed"><i class="bi bi-lock-fill"></i> Registration closed</span>
               @endif
            </div>
            @endif
         </div>
         <div class="col-lg-6 d-flex justify-content-lg-end">
            @if($start->isFuture())
            <div class="evc-clock w-100" data-evc-target="{{ $start->toIso8601String() }}" data-evc-done="{{ $c['finished_text'] ?? '' }}" aria-label="Time until the event">
               <div class="evc-unit"><span class="evc-num" data-u="d">0</span><span class="evc-lbl">Days</span></div>
               <div class="evc-unit"><span class="evc-num" data-u="h">00</span><span class="evc-lbl">Hours</span></div>
               <div class="evc-unit"><span class="evc-num" data-u="m">00</span><span class="evc-lbl">Minutes</span></div>
               <div class="evc-unit"><span class="evc-num" data-u="s">00</span><span class="evc-lbl">Seconds</span></div>
            </div>
            @else
            <div class="evc-done">{{ $c['finished_text'] ?? '' }}</div>
            @endif
         </div>
      </div>
   </div>
</div>
@endif
