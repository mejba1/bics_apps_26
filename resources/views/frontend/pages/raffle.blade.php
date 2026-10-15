<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <meta name="csrf-token" content="{{ csrf_token() }}">
   <meta name="robots" content="noindex">
   <title>Raffle Draw · {{ $event->event_short ?? 'BICS' }}</title>
   <link rel="icon" href="{{ asset('assets/frontend/images/logo.png') }}">
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
   <style>
      :root {
         --blue: #0e4ca1; --navy: #071a3d; --navy-2: #0a2350; --magenta: #b22e6b; --green: #5d9b3a; --teal: #3f97bf; --gold: #f2b544;
         --ink: #0f1b33; --glass: rgba(255,255,255,.07); --line: rgba(255,255,255,.14); --muted: rgba(255,255,255,.66);
      }
      * { box-sizing: border-box; }
      html, body { height: 100%; margin: 0; }
      body { font-family: "Plus Jakarta Sans", system-ui, sans-serif; color: #fff; background: var(--navy); overflow: hidden; -webkit-font-smoothing: antialiased; }
      button { font-family: inherit; }

      /* Background: slow moving brand-colour glows */
      .bg { position: fixed; inset: 0; z-index: -1; overflow: hidden; background: radial-gradient(1200px 700px at 50% 120%, #0e3a85 0%, transparent 60%), linear-gradient(180deg, #06142f, var(--navy) 40%, #0a2350); }
      .bg span { position: absolute; width: 46vmax; height: 46vmax; border-radius: 50%; filter: blur(90px); opacity: .32; animation: float 22s ease-in-out infinite; }
      .bg span:nth-child(1) { background: var(--blue); left: -12vmax; top: -16vmax; }
      .bg span:nth-child(2) { background: var(--magenta); right: -16vmax; top: 10vmax; animation-delay: -7s; }
      .bg span:nth-child(3) { background: var(--teal); left: 30vmax; bottom: -26vmax; animation-delay: -14s; opacity: .22; }
      @keyframes float { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(4vmax, 3vmax) scale(1.08); } }
      .brandbar { display: flex; height: 5px; position: fixed; top: 0; left: 0; right: 0; z-index: 5; }
      .brandbar i { flex: 1; } .brandbar i:nth-child(1) { background: var(--blue); } .brandbar i:nth-child(2) { background: var(--magenta); } .brandbar i:nth-child(3) { background: var(--green); } .brandbar i:nth-child(4) { background: var(--teal); }

      .app { height: 100vh; display: grid; grid-template-rows: auto 1fr auto; padding: 22px clamp(16px, 3vw, 44px) 16px; gap: 18px; }

      /* Top bar */
      .top { display: flex; align-items: center; gap: 18px; }
      .logo { background: #fff; border-radius: 14px; padding: 8px 12px; display: flex; }
      .logo img { height: 44px; display: block; }
      .title small { display: block; font-size: 12px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--gold); }
      .title h1 { margin: 2px 0 0; font-size: clamp(1.2rem, 2vw, 1.6rem); font-weight: 800; letter-spacing: -.01em; }
      .title p { margin: 2px 0 0; font-size: 14px; color: var(--muted); }
      .tools { margin-left: auto; display: flex; align-items: center; gap: 10px; }
      .chip { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border-radius: 999px; background: var(--glass); border: 1px solid var(--line); font-size: 14px; font-weight: 600; }
      .chip b { font-variant-numeric: tabular-nums; }
      .icon-btn { width: 44px; height: 44px; border-radius: 12px; border: 1px solid var(--line); background: var(--glass); color: #fff; font-size: 18px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none; transition: background .2s; }
      .icon-btn:hover { background: rgba(255,255,255,.16); }

      /* Main grid */
      .main { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 22px; min-height: 0; }
      .stage { position: relative; border-radius: 28px; background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.03)); border: 1px solid var(--line); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: clamp(20px, 3vw, 44px); overflow: hidden; }
      .now { font-size: 13px; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; color: var(--gold); display: inline-flex; align-items: center; gap: 10px; }
      .now::before, .now::after { content: ""; width: 34px; height: 2px; background: currentColor; border-radius: 2px; opacity: .7; }
      .prize-type { font-size: clamp(2.2rem, 5.4vw, 4.6rem); font-weight: 800; line-height: 1.05; margin: 10px 0 6px; letter-spacing: -.02em; background: linear-gradient(90deg, #ffffff, #ffd9a1 40%, #ffb3d4); -webkit-background-clip: text; background-clip: text; color: transparent; }
      .prize-name { font-size: clamp(1.1rem, 2vw, 1.6rem); font-weight: 600; color: rgba(255,255,255,.88); }

      /* Reels */
      .reels { display: flex; gap: clamp(8px, 1.2vw, 16px); margin: clamp(22px, 4vh, 44px) 0 clamp(18px, 3vh, 30px); }
      .reel { width: clamp(64px, 8.4vw, 124px); height: clamp(88px, 12vw, 168px); border-radius: 20px; background: linear-gradient(180deg, #0b2554, #071a3d); border: 1px solid rgba(255,255,255,.18); box-shadow: inset 0 10px 24px rgba(0,0,0,.45), 0 18px 40px -20px rgba(0,0,0,.8); display: flex; align-items: center; justify-content: center; font-size: clamp(3rem, 7vw, 6.4rem); font-weight: 800; font-variant-numeric: tabular-nums; position: relative; overflow: hidden; }
      .reel::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(255,255,255,.12), transparent 30%, transparent 70%, rgba(0,0,0,.3)); pointer-events: none; }
      .reel.spin span { animation: blur .12s linear infinite; }
      .reel.locked { border-color: var(--gold); box-shadow: inset 0 10px 24px rgba(0,0,0,.4), 0 0 0 3px rgba(242,181,68,.25), 0 0 40px -6px rgba(242,181,68,.7); animation: lock .35s ease-out; }
      .reel.locked span { color: #ffe2a8; }
      @keyframes blur { 0% { transform: translateY(-6%); filter: blur(1.5px); } 100% { transform: translateY(6%); filter: blur(1.5px); } }
      @keyframes lock { 0% { transform: scale(1.12); } 100% { transform: scale(1); } }

      .main.no-prizes { grid-template-columns: minmax(0, 1fr); }
      .main.no-prizes .ladder { display: none; }
      .cd { display: none; align-items: center; justify-content: center; gap: 18px; margin: 0 0 22px; }
      .cd.show { display: flex; }
      .cd small { font-size: 13px; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; color: var(--gold); }
      .cd-ring { position: relative; width: 112px; height: 112px; }
      .cd-ring svg { position: absolute; inset: 0; transform: rotate(-90deg); }
      .cd-ring circle { fill: none; stroke-width: 8; }
      .cd-ring .track { stroke: rgba(255,255,255,.12); }
      .cd-ring .bar { stroke: url(#cdgrad); stroke-linecap: round; transition: stroke-dashoffset 1s linear; }
      .cd-ring b { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 800; font-variant-numeric: tabular-nums; }
      .cd-ring b.tick { animation: tick .5s ease-out; }
      @keyframes tick { 0% { transform: scale(1.35); opacity: .4; } 100% { transform: scale(1); opacity: 1; } }
      .hint { color: var(--muted); font-size: 15px; margin: 0 0 22px; min-height: 22px; }
      .hint kbd { background: rgba(255,255,255,.14); border: 1px solid var(--line); border-bottom-width: 3px; border-radius: 7px; padding: 2px 8px; font-family: inherit; font-weight: 700; font-size: 13px; color: #fff; }
      .draw-btn { position: relative; border: 0; cursor: pointer; color: #fff; font-weight: 800; font-size: clamp(1.2rem, 2vw, 1.6rem); letter-spacing: .08em; padding: 22px 64px; border-radius: 999px; background: linear-gradient(135deg, var(--magenta), #e0548f); box-shadow: 0 20px 44px -16px rgba(178,46,107,.9), inset 0 -4px 0 rgba(0,0,0,.18); transition: transform .15s, box-shadow .2s, opacity .2s; display: inline-flex; align-items: center; gap: 12px; }
      .draw-btn::before { content: ""; position: absolute; inset: -8px; border-radius: inherit; border: 2px solid rgba(224,84,143,.5); animation: ring 2s ease-out infinite; }
      .draw-btn:hover { transform: translateY(-2px) scale(1.02); }
      .draw-btn:disabled { opacity: .45; cursor: not-allowed; transform: none; }
      .draw-btn:disabled::before { display: none; }
      @keyframes ring { 0% { transform: scale(.96); opacity: 1; } 100% { transform: scale(1.18); opacity: 0; } }

      /* Winner reveal */
      .winner { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding: 24px; background: radial-gradient(800px 500px at 50% 40%, rgba(178,46,107,.35), transparent 70%), rgba(5, 16, 40, .86); backdrop-filter: blur(6px); opacity: 0; visibility: hidden; transition: opacity .45s, visibility .45s; }
      .winner.show { opacity: 1; visibility: visible; }
      .winner-card { max-width: 760px; width: 100%; transform: translateY(24px) scale(.96); transition: transform .55s cubic-bezier(.2, .9, .25, 1.2); }
      .winner.show .winner-card { transform: none; }
      .congrats { font-size: 13px; font-weight: 800; letter-spacing: .22em; text-transform: uppercase; color: var(--gold); }
      .photo { width: clamp(120px, 16vw, 190px); height: clamp(120px, 16vw, 190px); border-radius: 50%; margin: 18px auto 16px; padding: 5px; background: conic-gradient(var(--blue), var(--magenta), var(--green), var(--teal), var(--blue)); box-shadow: 0 0 60px -10px rgba(242,181,68,.7); }
      .photo img, .photo span { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 5px solid var(--navy); display: flex; align-items: center; justify-content: center; background: #0e3a85; font-size: clamp(2.4rem, 4vw, 3.6rem); font-weight: 800; }
      .w-name { font-size: clamp(2rem, 4.6vw, 3.8rem); font-weight: 800; line-height: 1.08; margin: 0; letter-spacing: -.02em; }
      .w-meta { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin: 16px 0 6px; }
      .w-meta span { display: inline-flex; align-items: center; gap: 8px; padding: 9px 15px; border-radius: 12px; background: var(--glass); border: 1px solid var(--line); font-weight: 600; }
      .w-meta i { color: var(--gold); }
      .w-prize { margin-top: 14px; font-size: 1.15rem; color: rgba(255,255,255,.85); }
      .w-prize b { color: #ffd9a1; }
      .w-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin-top: 28px; }
      .btn { border: 0; cursor: pointer; border-radius: 999px; padding: 16px 28px; font-weight: 800; font-size: 16px; display: inline-flex; align-items: center; gap: 10px; transition: transform .15s, background .2s; color: #fff; }
      .btn:hover { transform: translateY(-2px); }
      .btn:disabled { opacity: .5; cursor: wait; transform: none; }
      .btn-ok { background: linear-gradient(135deg, #2f8f3a, #5d9b3a); box-shadow: 0 16px 30px -14px rgba(93,155,58,.9); }
      .btn-ghost { background: rgba(255,255,255,.08); border: 1px solid var(--line); }
      .btn-light { background: #fff; color: var(--blue); }

      /* Finished */
      .done { display: none; }
      .done.show { display: block; }
      .done i.big { font-size: 64px; color: var(--gold); }
      .done h2 { font-size: clamp(2rem, 4vw, 3.2rem); margin: 10px 0 8px; font-weight: 800; }
      .done p { color: var(--muted); font-size: 1.1rem; }

      /* Prize ladder */
      .ladder { border-radius: 28px; background: rgba(255,255,255,.05); border: 1px solid var(--line); padding: 20px; display: flex; flex-direction: column; min-height: 0; }
      .ladder h2 { font-size: 13px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); margin: 4px 6px 14px; display: flex; justify-content: space-between; }
      .ladder ol { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; overflow-y: auto; }
      .rung { display: grid; grid-template-columns: 38px 1fr; gap: 12px; align-items: center; padding: 12px 14px; border-radius: 16px; background: rgba(255,255,255,.04); border: 1px solid transparent; transition: background .3s, border-color .3s; }
      .rung .n { width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; background: rgba(255,255,255,.08); }
      .rung .t { font-weight: 800; font-size: 15px; }
      .rung .p { font-size: 13.5px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .rung .w { font-size: 13px; color: #b9f0a6; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .rung.is-next { background: rgba(178,46,107,.18); border-color: rgba(224,84,143,.6); }
      .rung.is-next .n { background: linear-gradient(135deg, var(--magenta), #e0548f); }
      .rung.is-won .n { background: rgba(93,155,58,.35); color: #b9f0a6; }
      .rung.is-won .t { opacity: .75; }
      .empty { color: var(--muted); padding: 10px 6px; font-size: 14px; }

      .foot { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; color: rgba(255,255,255,.5); }
      .toast { position: fixed; left: 50%; bottom: 28px; transform: translate(-50%, 30px); background: #fff; color: var(--ink); font-weight: 700; padding: 13px 20px; border-radius: 14px; box-shadow: 0 20px 40px -18px rgba(0,0,0,.7); opacity: 0; transition: opacity .25s, transform .25s; z-index: 50; }
      .toast.show { opacity: 1; transform: translate(-50%, 0); }
      .toast.err { background: #ffe3e8; color: #8d1730; }

      @media (max-width: 1100px) {
         body { overflow: auto; }
         .app { height: auto; min-height: 100vh; }
         .main { grid-template-columns: 1fr; }
         .stage { min-height: 70vh; }
      }
      @media (max-width: 640px) {
         .title p, .chip.pool span { display: none; }
         .draw-btn { padding: 18px 40px; }
      }
      @media (prefers-reduced-motion: reduce) { .bg span, .draw-btn::before { animation: none; } }
   </style>
</head>
<body>
<div class="bg" aria-hidden="true"><span></span><span></span><span></span></div>
<div class="brandbar" aria-hidden="true"><i></i><i></i><i></i><i></i></div>

<div class="app">
   <header class="top">
      <div class="logo"><img src="{{ asset('assets/frontend/images/logo.png') }}" alt="BICS"></div>
      <div class="title">
         <small>Raffle draw</small>
         <h1>{{ $event->event_name ?? 'BICS' }}</h1>
         <p>{{ collect([\App\Support\EventSettings::datesLabel(), $event->event_venue ?? null])->filter()->implode(' · ') }}</p>
      </div>
      <div class="tools">
         <span class="chip pool" title="People who can still win"><i class="bi bi-people-fill"></i><b id="pool">{{ $state['pool'] }}</b> <span>in the draw</span></span>
         <button class="icon-btn" id="sound" type="button" title="Sound on/off" aria-pressed="true"><i class="bi bi-volume-up-fill"></i></button>
         <button class="icon-btn" id="fullscreen" type="button" title="Full screen (F)"><i class="bi bi-arrows-fullscreen"></i></button>
         <a class="icon-btn" href="{{ route('addPrize') }}" title="Back to Raffle setup"><i class="bi bi-x-lg"></i></a>
      </div>
   </header>

   <main class="main {{ $state['screen']['show_prizes'] ? '' : 'no-prizes' }}" id="main">
      <section class="stage" aria-live="polite">
         <div id="drawing">
            <div class="now">Now drawing</div>
            <div class="prize-type" id="prize-type">—</div>
            <div class="prize-name" id="prize-name"></div>
            <div class="reels" id="reels" aria-hidden="true">
               @for($i = 0; $i < 6; $i++)<div class="reel"><span>0</span></div>@endfor
            </div>
            <div class="cd" id="cd" aria-live="assertive">
               <small>Revealing in</small>
               <div class="cd-ring">
                  <svg viewBox="0 0 112 112" aria-hidden="true"><defs><linearGradient id="cdgrad" x1="0" x2="1"><stop offset="0" stop-color="#f2b544"/><stop offset="1" stop-color="#e0548f"/></linearGradient></defs>
                     <circle class="track" cx="56" cy="56" r="50"/><circle class="bar" id="cd-bar" cx="56" cy="56" r="50" stroke-dasharray="314.16" stroke-dashoffset="0"/></svg>
                  <b id="cd-num">10</b>
               </div>
               <small>seconds</small>
            </div>
            <p class="hint" id="hint">Press <kbd>Space</kbd> or the button to draw the winner</p>
            <button class="draw-btn" id="draw" type="button"><i class="bi bi-stars"></i> DRAW</button>
         </div>

         <div class="done" id="done">
            <i class="bi bi-trophy-fill big"></i>
            <h2>All prizes awarded</h2>
            <p>Congratulations to every winner. Thank you for joining {{ $event->event_short ?? 'BICS' }}!</p>
         </div>

         <div class="winner" id="winner" role="dialog" aria-modal="true" aria-labelledby="w-name">
            <div class="winner-card">
               <div class="congrats"><i class="bi bi-stars"></i> Congratulations <i class="bi bi-stars"></i></div>
               <div class="photo" id="w-photo"></div>
               <h2 class="w-name" id="w-name"></h2>
               <div class="w-meta" id="w-meta"></div>
               <div class="w-prize">wins the <b id="w-prize"></b></div>
               <div class="w-actions">
                  <button class="btn btn-ok" id="accept" type="button"><i class="bi bi-check2-circle"></i> Confirm winner</button>
                  <button class="btn btn-ghost" id="redraw" type="button"><i class="bi bi-arrow-repeat"></i> Not present — draw again</button>
               </div>
            </div>
         </div>
      </section>

      <aside class="ladder" aria-label="Prizes">
         <h2><span>Prizes</span><span id="progress"></span></h2>
         <ol id="ladder"></ol>
      </aside>
   </main>

   <footer class="foot">
      <span><i class="bi bi-person-badge"></i> Host: {{ $state['host'] }} · Who can win: <span id="elig">{{ $state['eligibility'] }}</span></span>
      <span>Winners are drawn at random on the server · <kbd style="all:unset">F</kbd> full screen</span>
   </footer>
</div>

<div class="toast" id="toast" role="status"></div>
{{-- Sounds: Raffle setup > Draw screen > Sounds --}}
<audio id="a-spin" preload="auto"></audio>
<audio id="a-win" preload="auto"></audio>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
<script>
(function () {
   'use strict';
   var state = @json($state);
   var urls = { state: @json(route('raffle.state')), draw: @json(route('raffle.draw')), accept: @json(route('raffle.accept')), redraw: @json(route('raffle.redraw')) };
   var csrf = document.querySelector('meta[name="csrf-token"]').content;
   var $ = function (id) { return document.getElementById(id); };
   var reels = Array.prototype.slice.call(document.querySelectorAll('.reel'));
   var busy = false, current = null, soundOn = true;
   var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

   function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
   function toast(msg, err) { var t = $('toast'); t.textContent = msg; t.className = 'toast show' + (err ? ' err' : ''); clearTimeout(t._h); t._h = setTimeout(function () { t.className = 'toast'; }, 3200); }
   // ---- Sounds (chosen per event in Raffle setup) ----
   function applySounds() {
      var sc = state.screen, spinA = $('a-spin'), winA = $('a-win');
      if (sc.draw_url && spinA.getAttribute('src') !== sc.draw_url) spinA.src = sc.draw_url;
      if (!sc.draw_url) spinA.removeAttribute('src');
      if (sc.win_url && winA.getAttribute('src') !== sc.win_url) winA.src = sc.win_url;
      if (!sc.win_url) winA.removeAttribute('src');
      spinA.volume = Math.max(0, Math.min(1, (sc.draw_volume || 0) / 100));
      spinA.loop = !!sc.draw_loop;
      winA.volume = Math.max(0, Math.min(1, (sc.win_volume || 0) / 100));
   }
   function play(id) { if (!soundOn) return; var a = $(id); if (!a.getAttribute('src')) return; try { a.currentTime = 0; var p = a.play(); if (p && p.catch) p.catch(function () {}); } catch (e) {} }
   function stop(id) { var a = $(id); try { a.pause(); } catch (e) {} }
   var audioCtx = null;
   function tickSound(final) {
      if (!soundOn || !state.screen.tick) return;
      try {
         audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
         var o = audioCtx.createOscillator(), g = audioCtx.createGain(), t = audioCtx.currentTime;
         var vol = Math.max(0, Math.min(1, (state.screen.tick_volume || 0) / 100)) * 0.5;
         o.type = 'sine'; o.frequency.value = final ? 1320 : 880;
         g.gain.setValueAtTime(vol, t); g.gain.exponentialRampToValueAtTime(0.0001, t + (final ? 0.22 : 0.12));
         o.connect(g); g.connect(audioCtx.destination); o.start(t); o.stop(t + 0.25);
      } catch (e) {}
   }
   function post(url, body) {
      return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) })
         .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); });
   }

   function render() {
      $('main').classList.toggle('no-prizes', !state.screen.show_prizes);
      applySounds();
      var next = state.prizes.filter(function (p) { return p.id === state.next; })[0];
      var won = state.prizes.filter(function (p) { return p.won; }).length;
      $('pool').textContent = state.pool;
      $('progress').textContent = state.prizes.length ? won + ' / ' + state.prizes.length : '';
      $('ladder').innerHTML = state.prizes.length ? state.prizes.map(function (p, i) {
         var cls = p.won ? 'is-won' : (p.id === state.next ? 'is-next' : '');
         return '<li class="rung ' + cls + '"><span class="n">' + (p.won ? '<i class="bi bi-check-lg"></i>' : i + 1) + '</span><div><div class="t">' + esc(p.type) + '</div><div class="p">' + esc(p.name) + '</div>' +
            (p.winner ? '<div class="w"><i class="bi bi-trophy"></i> ' + esc(p.winner.name) + (p.winner.country ? ' · ' + esc(p.winner.country) : '') + '</div>' : '') + '</div></li>';
      }).join('') : '<li class="empty">No prizes yet. Add them in Raffle setup.</li>';

      var finished = state.prizes.length && !next;
      $('drawing').style.display = finished ? 'none' : '';
      $('done').classList.toggle('show', !!finished);
      if (next) { $('prize-type').textContent = next.type; $('prize-name').textContent = next.name; }
      else if (!state.prizes.length) { $('prize-type').textContent = 'No prizes'; $('prize-name').textContent = 'Add prizes in Raffle setup first.'; }
      $('draw').disabled = busy || !next || !state.pool;
      $('draw').style.visibility = busy ? 'hidden' : '';
      if (next && !state.pool) $('hint').textContent = 'Nobody is left to draw. Check "Who can win" in Raffle setup.';
   }

   // Reels: spin random digits, then stop one by one on the drawn number
   var spinTimer = null, shuffle = [];
   function tickReels() {
      // Participants' numbers flash through the reels (or random digits)
      clearInterval(spinTimer);
      spinTimer = setInterval(function () {
         // Few people left: mix real numbers with random digits so the reels keep moving
         var useList = shuffle.length && (shuffle.length >= 6 || Math.random() < .35);
         var n = useList ? String(shuffle[Math.floor(Math.random() * shuffle.length)]).padStart(reels.length, '0').slice(-reels.length) : null;
         reels.forEach(function (r, i) { if (r.classList.contains('spin')) r.firstChild.textContent = n ? n[i] : Math.floor(Math.random() * 10); });
      }, shuffle.length ? 90 : 55);
   }
   function spin() {
      shuffle = [];
      reels.forEach(function (r) { r.classList.remove('locked'); r.classList.add('spin'); });
      tickReels();
   }
   function setShuffle(list) { shuffle = list || []; if (shuffle.length) tickReels(); }

   // Big countdown before the reveal
   function countdown(seconds) {
      return new Promise(function (resolve) {
         if (!seconds) return resolve();
         var left = seconds, bar = $('cd-bar'), num = $('cd-num'), full = 314.16;
         $('cd').classList.add('show'); $('hint').style.display = 'none';
         function show() {
            tickSound(left <= 3);
            num.textContent = left; num.classList.remove('tick'); void num.offsetWidth; num.classList.add('tick');
            bar.style.strokeDashoffset = String(full * (1 - left / seconds));
         }
         bar.style.transition = 'none'; bar.style.strokeDashoffset = '0'; void bar.offsetWidth; bar.style.transition = '';
         show();
         var h = setInterval(function () {
            left--;
            if (left <= 0) { clearInterval(h); bar.style.strokeDashoffset = String(full); setTimeout(function () { $('cd').classList.remove('show'); $('hint').style.display = ''; resolve(); }, 350); return; }
            show();
         }, 1000);
      });
   }
   function land(number) {
      var digits = String(number).padStart(reels.length, '0').slice(-reels.length).split('');
      return new Promise(function (resolve) {
         reels.forEach(function (r, i) {
            setTimeout(function () {
               r.classList.remove('spin'); r.classList.add('locked'); r.firstChild.textContent = digits[i];
               if (i === reels.length - 1) { clearInterval(spinTimer); setTimeout(resolve, 700); }
            }, reduce ? 0 : 650 * i);
         });
      });
   }
   function resetReels() { clearInterval(spinTimer); reels.forEach(function (r) { r.classList.remove('spin', 'locked'); r.firstChild.textContent = '0'; }); }

   function celebrate() {
      if (reduce || typeof confetti !== 'function') return;
      var colors = ['#0e4ca1', '#b22e6b', '#5d9b3a', '#3f97bf', '#f2b544', '#ffffff'];
      confetti({ particleCount: 160, spread: 90, origin: { y: .6 }, colors: colors });
      var end = Date.now() + 1800;
      (function frame() {
         confetti({ particleCount: 4, angle: 60, spread: 60, origin: { x: 0 }, colors: colors });
         confetti({ particleCount: 4, angle: 120, spread: 60, origin: { x: 1 }, colors: colors });
         if (Date.now() < end) requestAnimationFrame(frame);
      })();
   }

   function showWinner(d) {
      var p = d.person;
      $('w-photo').innerHTML = p.photo ? '<img src="' + esc(p.photo) + '" alt="">' : '<span>' + esc(p.initials || '?') + '</span>';
      $('w-name').textContent = p.name;
      $('w-meta').innerHTML = [
         ['bi-hash', p.number], ['bi-briefcase', p.profession + (p.specialty ? ' · ' + p.specialty : '')], ['bi-geo-alt', p.country], ['bi-building', p.institute]
      ].filter(function (m) { return m[1]; }).map(function (m) { return '<span><i class="bi ' + m[0] + '"></i>' + esc(m[1]) + '</span>'; }).join('');
      $('w-prize').textContent = d.prize.type + ' — ' + d.prize.name;
      $('winner').classList.add('show');
      $('accept').disabled = $('redraw').disabled = false;
      setTimeout(function () { $('accept').focus(); }, 400);
      stop('a-spin'); play('a-win'); celebrate();
   }

   function draw() {
      if (busy || $('draw').disabled) return;
      busy = true; render();
      $('hint').textContent = 'Drawing…';
      spin(); play('a-spin');
      var started = Date.now();
      post(urls.draw).then(function (res) {
         if (!res.ok) throw new Error(res.d.message || 'The draw failed. Please try again.');
         current = res.d;
         setShuffle(res.d.shuffle);
         var seconds = state.screen.countdown || 0;
         var wait = seconds ? 0 : Math.max(0, (reduce ? 300 : 3200) - (Date.now() - started));
         $('hint').textContent = 'Drawing…';
         setTimeout(function () {
            countdown(seconds).then(function () { return land(res.d.person.number); }).then(function () { showWinner(res.d); });
         }, wait);
      }).catch(function (e) {
         busy = false; resetReels(); stop('a-spin'); render();
         $('hint').innerHTML = 'Press <kbd>Space</kbd> or the button to draw the winner';
         toast(e.message, true);
      });
   }

   function finish(url, okMsg) {
      if (!current) return;
      $('accept').disabled = $('redraw').disabled = true;
      post(url, { token: current.token }).then(function (res) {
         if (!res.ok) { toast(res.d.message || 'Please try again.', true); $('accept').disabled = $('redraw').disabled = false; return; }
         state = res.d.state;
         current = null; busy = false;
         $('winner').classList.remove('show');
         resetReels(); render();
         $('hint').innerHTML = 'Press <kbd>Space</kbd> or the button to draw the winner';
         toast(okMsg);
      }).catch(function () { toast('Connection problem. Please try again.', true); $('accept').disabled = $('redraw').disabled = false; });
   }

   $('draw').addEventListener('click', draw);
   $('accept').addEventListener('click', function () { finish(urls.accept, 'Winner confirmed and saved.'); });
   $('redraw').addEventListener('click', function () { finish(urls.redraw, 'Left out of this draw. Ready to draw again.'); });
   $('sound').addEventListener('click', function () {
      soundOn = !soundOn; this.setAttribute('aria-pressed', soundOn); this.innerHTML = '<i class="bi ' + (soundOn ? 'bi-volume-up-fill' : 'bi-volume-mute-fill') + '"></i>';
      if (!soundOn) { stop('a-spin'); stop('a-win'); }
   });
   function toggleFull() { if (!document.fullscreenElement) document.documentElement.requestFullscreen && document.documentElement.requestFullscreen(); else document.exitFullscreen && document.exitFullscreen(); }
   $('fullscreen').addEventListener('click', toggleFull);
   document.addEventListener('keydown', function (e) {
      if (e.target.closest && e.target.closest('button, a, input')) { if (e.code === 'Space') return; }
      if (e.code === 'Space' && !$('winner').classList.contains('show')) { e.preventDefault(); draw(); }
      if (e.key === 'f' || e.key === 'F') toggleFull();
   });

   // Pick up changes from Raffle setup (prize list on/off, countdown, prizes) while waiting
   setInterval(function () {
      if (busy || $('winner').classList.contains('show') || document.hidden) return;
      fetch(urls.state, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; })
         .then(function (d) { if (d && !busy) { state = d; render(); } }).catch(function () {});
   }, 8000);

   render();
})();
</script>
</body>
</html>
