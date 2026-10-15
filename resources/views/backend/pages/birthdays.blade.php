@extends('backend.layouts.master')
@section('title')
Birthdays
@endsection
@php
  use App\Http\Controllers\BirthdayController as BC;
  $on = $s['enabled'] === '1';
  $default = $cards->firstWhere('is_default', true) ?? $cards->first();
  $timePassed = $now->format('H:i') >= $s['send_time'];
  $rows = $people->map(function ($p) { $r = BC::row($p); $r['in_days'] = $p->in_days; $r['turns'] = $p->turns; $r['next'] = $p->next->format('D j M'); $r['month'] = $p->next->month; $r['last'] = $p->last_wished ? \Carbon\Carbon::parse($p->last_wished)->format('j M Y') : null; return $r; })->values();
  $statusOf = function ($p) use ($status, $on, $timePassed, $s) {
      $g = $status[$p->id] ?? null;
      if ($g && $g->status === 'sent') return ['ok', 'Sent ' . \Carbon\Carbon::parse($g->sent_at)->timezone($s['timezone'])->format('H:i'), 'bi-check-circle-fill'];
      if ($g && $g->status === 'failed') return ['bad', 'Failed', 'bi-exclamation-triangle-fill', $g->error];
      if (!$p->birthday_wishes) return ['mute', 'Switched off', 'bi-slash-circle'];
      if (!filter_var($p->email, FILTER_VALIDATE_EMAIL)) return ['mute', 'No email', 'bi-envelope-x'];
      if (!$on) return ['mute', 'Wishes off', 'bi-pause-circle'];
      return $timePassed ? ['warn', 'Sending soon', 'bi-hourglass-split'] : ['info', 'Scheduled ' . $s['send_time'], 'bi-clock'];
  };
  $avatar = function ($p, $size = 44) {
      $r = BC::row($p);
      $hue = ($p->id * 47) % 360;
      return $r['photo']
          ? '<img class="bd-av" style="width:' . $size . 'px;height:' . $size . 'px" src="' . e($r['photo']) . '" alt="" loading="lazy">'
          : '<span class="bd-av bd-av-i" style="width:' . $size . 'px;height:' . $size . 'px;background:hsl(' . $hue . ' 55% 92%);color:hsl(' . $hue . ' 45% 35%)">' . e($r['initials']) . '</span>';
  };
  $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  $maxMonth = max(1, max($byMonth));
  $later = $upcoming->where('in_days', '>', 0)->groupBy(fn ($p) => $p->in_days);
@endphp
@push('styles')
<style>
  :root { --bd-pink: #b22e6b; --bd-pink-50: #fbeef4; --bd-gold: #f2b84b; }
  html[data-theme="dark"] { --bd-pink-50: rgba(178,46,107,.16); }
  .bd-hero { position: relative; overflow: hidden; border-radius: 20px; padding: 24px 26px; margin-bottom: 18px; border: 1px solid var(--ad-line);
    background: radial-gradient(520px 260px at 100% 0%, rgba(178,46,107,.16), transparent 70%), radial-gradient(420px 260px at 0% 100%, rgba(14,76,161,.10), transparent 70%), var(--ad-surface); }
  .bd-hero-in { display: flex; gap: 22px; align-items: center; flex-wrap: wrap; }
  .bd-hero-ic { width: 66px; height: 66px; flex: none; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; color: #fff;
    background: linear-gradient(135deg, #0e4ca1, var(--bd-pink)); box-shadow: 0 16px 30px -16px rgba(178,46,107,.9); }
  .bd-hero h2 { font-size: 1.35rem; font-weight: 800; margin: 0 0 4px; color: var(--ad-ink); display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .bd-hero p { margin: 0; color: var(--ad-muted); max-width: 640px; }
  .bd-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; }
  .bd-pill.on { background: var(--ad-green-50); color: var(--ad-green); } .bd-pill.off { background: var(--ad-chip); color: var(--ad-muted); }
  .bd-pill .dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
  .bd-pill.on .dot { animation: bdPulse 1.8s infinite; }
  @keyframes bdPulse { 0% { box-shadow: 0 0 0 0 rgba(47,138,76,.5); } 70% { box-shadow: 0 0 0 7px rgba(47,138,76,0); } 100% { box-shadow: 0 0 0 0 rgba(47,138,76,0); } }
  .bd-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
  .bd-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; padding: 5px 10px; border-radius: 9px; border: 1px solid var(--ad-line); background: var(--ad-surface); color: var(--ad-text); }
  .bd-chip b { color: var(--ad-ink); } .bd-chip i { color: var(--bd-pink); }
  .bd-switch { margin-left: auto; display: flex; flex-direction: column; align-items: center; gap: 6px; }
  .bd-toggle { position: relative; width: 84px; height: 44px; border-radius: 999px; border: 0; background: var(--ad-line); transition: background .25s; cursor: pointer; padding: 0; }
  .bd-toggle::after { content: ""; position: absolute; top: 5px; left: 5px; width: 34px; height: 34px; border-radius: 50%; background: #fff; box-shadow: 0 3px 10px rgba(0,0,0,.25); transition: transform .25s cubic-bezier(.3,.7,.3,1); }
  .bd-toggle[aria-checked="true"] { background: linear-gradient(120deg, #2f8a4c, #3fb565); } .bd-toggle[aria-checked="true"]::after { transform: translateX(40px); }
  .bd-toggle:focus-visible { outline: 3px solid rgba(14,76,161,.35); outline-offset: 3px; }
  .bd-switch small { font-weight: 700; color: var(--ad-muted); font-size: 12px; }

  .bd-tabs { display: flex; gap: 4px; flex-wrap: wrap; border-bottom: 1px solid var(--ad-line); margin-bottom: 18px; }
  .bd-tabs button { border: 0; background: none; padding: 10px 14px; font-weight: 600; color: var(--ad-muted); border-bottom: 2.5px solid transparent; margin-bottom: -1px; display: inline-flex; align-items: center; gap: 7px; }
  .bd-tabs button.on { color: var(--ad-ink); border-bottom-color: var(--bd-pink); }
  .bd-tabs .n { font-size: 11.5px; background: var(--ad-chip); color: var(--ad-muted); border-radius: 999px; padding: 1px 7px; }
  .bd-pane { display: none; } .bd-pane.on { display: block; animation: bdIn .2s ease; }
  @keyframes bdIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

  .bd-kpi { border: 1px solid var(--ad-line); border-radius: 16px; padding: 14px 16px; height: 100%; background: var(--ad-surface); display: flex; gap: 12px; align-items: center; }
  .bd-kpi .ic { width: 42px; height: 42px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; flex: none; }
  .bd-kpi .n { font-size: 1.55rem; font-weight: 800; line-height: 1; color: var(--ad-ink); }
  .bd-kpi .l { font-size: 12.5px; color: var(--ad-muted); margin-top: 4px; }
  .bd-card { border: 1px solid var(--ad-line); border-radius: 18px; background: var(--ad-surface); height: 100%; }
  .bd-card-h { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 16px 18px 6px; }
  .bd-card-h h3 { font-size: 1rem; font-weight: 800; margin: 0; color: var(--ad-ink); }
  .bd-card-h small { color: var(--ad-muted); }
  .bd-card-b { padding: 6px 18px 16px; }

  .bd-av { border-radius: 50%; object-fit: cover; flex: none; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; }
  .bd-person { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-top: 1px solid var(--ad-line-soft); }
  .bd-person:first-child { border-top: 0; }
  .bd-person .who { min-width: 0; flex: 1; }
  .bd-person .who b { color: var(--ad-ink); display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .bd-person .who span { font-size: 12.5px; color: var(--ad-muted); }
  .bd-cake { position: relative; } .bd-cake::after { content: "🎂"; position: absolute; right: -4px; bottom: -4px; font-size: 16px; }
  .bd-st { font-size: 12px; font-weight: 700; padding: 4px 9px; border-radius: 8px; white-space: nowrap; display: inline-flex; gap: 5px; align-items: center; }
  .bd-st.ok { background: var(--ad-green-50); color: var(--ad-green); } .bd-st.bad { background: var(--ad-red-50); color: var(--ad-red); }
  .bd-st.warn { background: var(--ad-amber-50); color: var(--ad-amber); } .bd-st.info { background: var(--ad-brand-50); color: var(--ad-brand); } .bd-st.mute { background: var(--ad-chip); color: var(--ad-muted); }
  .bd-day { font-size: 11.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--ad-muted); margin: 14px 0 2px; }
  .bd-day:first-child { margin-top: 2px; }
  .bd-scroll { max-height: 520px; overflow: auto; }
  .bd-empty { text-align: center; padding: 34px 10px; color: var(--ad-muted); }
  .bd-card.flexy { display: flex; flex-direction: column; } .bd-card.flexy .bd-card-b { flex: 1; display: flex; flex-direction: column; } .bd-card.flexy .bd-empty { margin: auto 0; }
  .bd-empty .big { font-size: 2.6rem; line-height: 1; margin-bottom: 10px; }
  .bd-empty b { color: var(--ad-ink); display: block; font-size: 1.05rem; margin-bottom: 4px; }
  .bd-iconbtn { width: 34px; height: 34px; border-radius: 10px; border: 1px solid var(--ad-line); background: var(--ad-surface); color: var(--ad-text); display: inline-flex; align-items: center; justify-content: center; flex: none; }
  .bd-iconbtn:hover { border-color: var(--bd-pink); color: var(--bd-pink); }

  .bd-bars { display: grid; grid-template-columns: repeat(12, 1fr); gap: 8px; padding-top: 10px; }
  .bd-bar { display: flex; flex-direction: column; align-items: center; gap: 6px; }
  .bd-bar .track { height: 110px; width: 100%; display: flex; align-items: flex-end; justify-content: center; }
  .bd-bar i { display: block; width: 100%; max-width: 34px; border-radius: 8px 8px 3px 3px; background: linear-gradient(180deg, #7aa6e6, #b9cff2); min-height: 4px; }
  html[data-theme="dark"] .bd-bar i { background: linear-gradient(180deg, #3f6fb8, #284a7e); }
  .bd-bar.now i { background: linear-gradient(180deg, var(--bd-pink), #d85a92); }
  .bd-bar em { font-style: normal; font-size: 11.5px; color: var(--ad-muted); font-weight: 600; }
  .bd-bar b { font-size: 11.5px; color: var(--ad-ink); }

  .bd-table td, .bd-table th { font-size: 13.5px; vertical-align: middle; }
  .bd-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--ad-muted); font-weight: 700; }
  .bd-name { display: flex; align-items: center; gap: 10px; } .bd-name b { color: var(--ad-ink); } .bd-name small { color: var(--ad-muted); display: block; }

  .bd-design { border: 1px solid var(--ad-line); border-radius: 18px; overflow: hidden; background: var(--ad-surface); height: 100%; display: flex; flex-direction: column; transition: box-shadow .2s, transform .2s; }
  .bd-design:hover { box-shadow: var(--ad-shadow-lg); transform: translateY(-2px); }
  .bd-design.is-default { border-color: var(--bd-pink); box-shadow: 0 0 0 3px rgba(178,46,107,.12); }
  .bd-design .pic { aspect-ratio: 3 / 2; background: var(--ad-chip); position: relative; overflow: hidden; }
  .bd-design .pic img { width: 100%; height: 100%; object-fit: contain; display: block; background: #1b2235; }
  .bd-design .tag { position: absolute; top: 10px; left: 10px; font-size: 11.5px; font-weight: 800; padding: 4px 9px; border-radius: 8px; background: var(--bd-pink); color: #fff; box-shadow: 0 6px 14px -6px rgba(0,0,0,.4); }
  .bd-design .body { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 10px; flex: 1; }
  .bd-design .body b { color: var(--ad-ink); } .bd-design .body small { color: var(--ad-muted); }
  .bd-drop { border: 2px dashed var(--ad-line); border-radius: 18px; height: 100%; min-height: 260px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 22px; color: var(--ad-muted); cursor: pointer; transition: border-color .2s, background .2s; background: var(--ad-surface); }
  .bd-drop:hover, .bd-drop.over { border-color: var(--bd-pink); background: var(--bd-pink-50); }
  .bd-drop .ic { width: 56px; height: 56px; border-radius: 16px; background: var(--bd-pink-50); color: var(--bd-pink); display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 12px; }
  .bd-drop b { color: var(--ad-ink); font-size: 1.02rem; }

  .bd-preview { position: relative; border-radius: 16px; overflow: hidden; border: 1px solid var(--ad-line); display: flex; align-items: center; justify-content: center; min-height: 160px;
    background: repeating-conic-gradient(var(--ad-chip) 0% 25%, var(--ad-surface) 0% 50%) 50% / 18px 18px; }
  .bd-preview img { max-width: 100%; max-height: 62vh; width: auto; height: auto; display: block; margin: 0 auto; transition: opacity .2s; }
  /* the preview stays in view while the settings on the right (or below, on phones) are changed */
  .bd-stick { position: sticky; top: 0; z-index: 6; }
  .bd-stick .bd-mailframe { height: min(640px, 66vh) !important; }
  @media (max-width: 991.98px) {
    .bd-stick { top: -16px; margin: -16px -12px 0; padding: 16px 24px 12px !important; background: var(--ad-surface); box-shadow: 0 10px 18px -16px rgba(0,0,0,.45); }
    .bd-stick .bd-preview img { max-height: 30vh; }
    .bd-stick .bd-preview { min-height: 0; }
    .bd-stick .bd-mailframe { height: 46vh !important; }
    /* stacked columns: let the preview stay pinned over the whole form, not just its own column */
    .bd-stick-col { display: contents; }
    .bd-stick { width: calc(100% + 24px); flex: 0 0 calc(100% + 24px); max-width: none; }
  }
  .bd-preview.loading img { opacity: .55; }
  .bd-preview .spin { position: absolute; top: 12px; right: 12px; display: none; }
  .bd-preview.loading .spin { display: block; }
  .bd-label { font-size: 12.5px; font-weight: 700; color: var(--ad-ink); margin-bottom: 5px; display: block; }
  .bd-vars { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
  .bd-vars button { border: 1px solid var(--ad-line); background: var(--ad-surface); border-radius: 8px; font-size: 12px; padding: 3px 8px; font-family: Consolas, monospace; color: var(--ad-text); }
  .bd-vars button:hover { border-color: var(--bd-pink); color: var(--bd-pink); }
  .bd-section { font-size: 12px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: var(--bd-pink); margin: 4px 0 12px; display: flex; align-items: center; gap: 8px; }
  .bd-section::after { content: ""; flex: 1; border-top: 1px solid var(--ad-line); }
  .bd-mailframe { width: 100%; height: 760px; border: 1px solid var(--ad-line); border-radius: 16px; background: #f6eff4; }
  .bd-sticky { position: sticky; top: 80px; }
  .bd-pick { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
  .bd-pick label { position: relative; border-radius: 10px; overflow: hidden; border: 2px solid transparent; cursor: pointer; aspect-ratio: 3 / 2; background: var(--ad-chip); }
  .bd-pick input { position: absolute; opacity: 0; }
  .bd-pick img { width: 100%; height: 100%; object-fit: contain; display: block; background: #1b2235; }
  .bd-pick input:checked + img { outline: 0; } .bd-pick label:has(input:checked) { border-color: var(--bd-pink); box-shadow: 0 0 0 3px rgba(178,46,107,.15); }
  .bd-pick label:has(input:focus-visible) { outline: 2px solid var(--ad-brand); }
  .bd-pick .bd-pick-up { border: 2px dashed var(--ad-line); background: var(--ad-surface); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
    color: var(--ad-muted); font-size: 12px; font-weight: 700; transition: border-color .15s, color .15s, background .15s; }
  .bd-pick .bd-pick-up i { font-size: 1.3rem; color: var(--bd-pink); }
  .bd-pick .bd-pick-up:hover, .bd-pick .bd-pick-up.over { border-color: var(--bd-pink); color: var(--bd-pink); background: var(--bd-pink-50); }
  .bd-pick .bd-pick-up.busy { pointer-events: none; opacity: .7; }
  .bd-pick label .mine { position: absolute; left: 4px; bottom: 4px; font-size: 10px; font-weight: 800; background: rgba(0,0,0,.6); color: #fff; border-radius: 5px; padding: 1px 5px; }
  .bd-place { border: 1px solid var(--ad-line); border-radius: 14px; padding: 12px 14px; margin: 4px 0 14px; background: var(--ad-bg); }
  .bd-place .row-l { display: flex; justify-content: space-between; align-items: baseline; }
  .bd-who { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid var(--ad-line); border-radius: 14px; background: var(--ad-bg); }
  .bd-who b { color: var(--ad-ink); } .bd-who small { color: var(--ad-muted); display: block; }
  .bd-results { position: absolute; z-index: 20; left: 0; right: 0; top: 100%; margin-top: 4px; background: var(--ad-surface); border: 1px solid var(--ad-line); border-radius: 12px; box-shadow: var(--ad-shadow-lg); max-height: 300px; overflow: auto; }
  .bd-results button { display: flex; gap: 10px; align-items: center; width: 100%; border: 0; background: none; padding: 8px 12px; text-align: left; color: var(--ad-text); }
  .bd-results button:hover, .bd-results button:focus { background: var(--ad-hover); outline: 0; }
  .bd-results b { color: var(--ad-ink); font-size: 14px; } .bd-results small { color: var(--ad-muted); display: block; font-size: 12px; }
  .bd-seg { display: inline-flex; background: var(--ad-chip); border-radius: 10px; padding: 3px; }
  .bd-seg button { border: 0; background: none; padding: 5px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; color: var(--ad-muted); }
  .bd-seg button.on { background: var(--ad-surface); color: var(--ad-ink); box-shadow: 0 1px 4px rgba(0,0,0,.12); }
  .bd-modal .modal-content { border-radius: 20px; border: 0; background: var(--ad-surface); overflow: hidden; }
  .bd-modal .modal-header { background: none !important; color: var(--ad-ink) !important; padding: 20px 24px 6px; }
  .bd-modal .modal-header .modal-title { color: var(--ad-ink) !important; }
  .bd-modal .modal-header .btn-close { filter: none !important; }
  html[data-theme="dark"] .bd-modal .modal-header .btn-close { filter: invert(1) !important; }
  .bd-modal .modal-body { padding: 16px 24px; } .bd-modal .modal-footer { padding: 6px 24px 20px; }
  @media (max-width: 767.98px) {
    .bd-hero { padding: 18px; } .bd-switch { margin-left: 0; flex-direction: row; } .bd-hero-ic { width: 52px; height: 52px; font-size: 1.5rem; }
    .bd-tabs { flex-wrap: nowrap; overflow-x: auto; } .bd-tabs button { white-space: nowrap; }
    .bd-mailframe { height: 600px; }
  }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Birthdays</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item">Engagement</li><li class="breadcrumb-item active">Birthdays</li></ol></nav>
  </div>
  <button class="btn btn-primary" data-compose><i class="bi bi-magic"></i> Send a custom card</button>
</div>

<section class="section">
  {{-- master switch --}}
  <div class="bd-hero">
    <div class="bd-hero-in">
      <span class="bd-hero-ic"><i class="bi bi-cake2"></i></span>
      <div style="flex:1;min-width:260px">
        <h2>Automatic birthday wishes <span class="bd-pill {{ $on ? 'on' : 'off' }}" id="bd-pill"><span class="dot"></span><span>{{ $on ? 'On' : 'Off' }}</span></span></h2>
        <p id="bd-hero-text">{{ $on
          ? 'Every day at ' . $s['send_time'] . ' (' . $s['timezone'] . '), everyone celebrating gets your birthday card by email — once a year, automatically.'
          : 'Switched off — nobody gets an automatic card. You can still send custom cards to anyone, any time.' }}</p>
        <div class="bd-meta">
          <span class="bd-chip"><i class="bi bi-clock"></i> Sends at <b>{{ $s['send_time'] }}</b></span>
          <span class="bd-chip"><i class="bi bi-globe2"></i> {{ $s['timezone'] }} · now {{ $now->format('H:i') }}</span>
          <span class="bd-chip"><i class="bi bi-image"></i> Design <b>{{ $default->name ?? '—' }}</b></span>
          <span class="bd-chip"><i class="bi bi-people"></i> <b>{{ number_format($kpi['reach']) }}</b> of {{ number_format($kpi['total'] + $kpi['noDob']) }} reachable</span>
        </div>
      </div>
      <div class="bd-switch">
        <button type="button" class="bd-toggle" id="bd-toggle" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}" aria-label="Automatic birthday wishes"></button>
        <small id="bd-toggle-l">{{ $on ? 'Switched on' : 'Switched off' }}</small>
      </div>
    </div>
  </div>

  <div class="bd-tabs" role="tablist">
    <button type="button" data-tab="overview"><i class="bi bi-grid-1x2"></i> Overview</button>
    <button type="button" data-tab="people"><i class="bi bi-people"></i> People <span class="n">{{ $people->count() }}</span></button>
    <button type="button" data-tab="designs"><i class="bi bi-images"></i> Card designs <span class="n">{{ $cards->count() }}</span></button>
    <button type="button" data-tab="message"><i class="bi bi-envelope-paper-heart"></i> Message &amp; schedule</button>
    <button type="button" data-tab="history"><i class="bi bi-clock-history"></i> History <span class="n">{{ $history->count() }}</span></button>
  </div>

  {{-- ============ Overview ============ --}}
  <div class="bd-pane" data-pane="overview">
    <div class="row g-3 mb-3">
      <div class="col-6 col-xl-3"><div class="bd-kpi"><span class="ic" style="background:var(--bd-pink-50);color:var(--bd-pink)"><i class="bi bi-cake2"></i></span><div><div class="n">{{ $kpi['today'] }}</div><div class="l">Today</div></div></div></div>
      <div class="col-6 col-xl-3"><div class="bd-kpi"><span class="ic" style="background:var(--ad-brand-50);color:var(--ad-brand)"><i class="bi bi-calendar-week"></i></span><div><div class="n">{{ $kpi['week'] }}</div><div class="l">Next 7 days</div></div></div></div>
      <div class="col-6 col-xl-3"><div class="bd-kpi"><span class="ic" style="background:var(--ad-teal-50);color:var(--ad-teal)"><i class="bi bi-calendar3"></i></span><div><div class="n">{{ $kpi['month'] }}</div><div class="l">Still to come in {{ $now->format('F') }}</div></div></div></div>
      <div class="col-6 col-xl-3"><div class="bd-kpi"><span class="ic" style="background:var(--ad-green-50);color:var(--ad-green)"><i class="bi bi-send-check"></i></span><div><div class="n">{{ $kpi['sent'] }}</div><div class="l">Cards sent in {{ $now->year }}@if($kpi['failed']) · <span class="text-danger">{{ $kpi['failed'] }} failed</span>@endif</div></div></div></div>
    </div>

    <div class="row g-3">
      <div class="col-lg-7">
        <div class="bd-card flexy">
          <div class="bd-card-h">
            <div><h3>🎉 Today · {{ $now->format('l, j F') }}</h3><small>{{ $today->count() ? $today->count() . ' ' . \Illuminate\Support\Str::plural('birthday', $today->count()) : 'No birthdays' }}</small></div>
            @if($today->where('birthday_wishes', true)->count())
              <button class="btn btn-sm btn-outline-primary" id="bd-run"><i class="bi bi-send"></i> Send today's cards now</button>
            @endif
          </div>
          <div class="bd-card-b">
            @forelse($today as $p)
              @php $st = $statusOf($p); @endphp
              <div class="bd-person">
                <span class="bd-cake">{!! $avatar($p, 48) !!}</span>
                <div class="who"><b>{{ trim(($p->title ? $p->title . ' ' : '') . $p->first_name . ' ' . $p->last_name) }}</b>
                  <span>Turns {{ $p->turns }} · {{ $p->code }}{{ $p->organization ? ' · ' . \Illuminate\Support\Str::limit($p->organization, 30) : '' }}</span></div>
                <span class="bd-st {{ $st[0] }}" @if(!empty($st[3])) title="{{ $st[3] }}" @endif><i class="bi {{ $st[2] }}"></i> {{ $st[1] }}</span>
                <button class="bd-iconbtn" data-compose="{{ $p->id }}" title="Send a custom card" aria-label="Send a custom card to {{ $p->first_name }}"><i class="bi bi-magic"></i></button>
              </div>
            @empty
              @php $next = $upcoming->firstWhere('in_days', '>', 0); @endphp
              <div class="bd-empty"><div class="big">🎈</div><b>No birthdays today</b>
                @if($next) Next up: {{ $next->first_name }} {{ $next->last_name }} on {{ $next->next->format('l, j M') }}{{ $next->in_days === 1 ? ' (tomorrow)' : '' }}. @else Nobody in the next 30 days. @endif</div>
            @endforelse
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="bd-card">
          <div class="bd-card-h"><div><h3>Coming up</h3><small>Next 30 days</small></div><button class="btn btn-sm btn-link text-decoration-none" data-goto="people">All people <i class="bi bi-arrow-right"></i></button></div>
          <div class="bd-card-b bd-scroll">
            @forelse($later as $days => $group)
              <div class="bd-day">{{ $days == 1 ? 'Tomorrow' : $group->first()->next->format('l, j M') }} <span style="font-weight:600;text-transform:none;letter-spacing:0">· in {{ $days }} {{ $days == 1 ? 'day' : 'days' }}</span></div>
              @foreach($group as $p)
                <div class="bd-person" style="padding:8px 0">
                  {!! $avatar($p, 36) !!}
                  <div class="who"><b style="font-size:14px">{{ $p->first_name }} {{ $p->last_name }}</b><span>Turns {{ $p->turns }}@if(!$p->birthday_wishes) · <i class="bi bi-slash-circle"></i> wishes off @elseif(!filter_var($p->email, FILTER_VALIDATE_EMAIL)) · no email @endif</span></div>
                  <button class="bd-iconbtn" data-compose="{{ $p->id }}" title="Prepare a custom card"><i class="bi bi-magic"></i></button>
                </div>
              @endforeach
            @empty
              <div class="bd-empty"><div class="big">📅</div>No birthdays in the next 30 days.</div>
            @endforelse
          </div>
        </div>
      </div>
      <div class="col-12">
        <div class="bd-card">
          <div class="bd-card-h"><div><h3>Birthdays through the year</h3><small>{{ number_format($kpi['total']) }} participants with a date of birth @if($kpi['noDob'])· {{ number_format($kpi['noDob']) }} without one (they can add it in their dashboard) @endif</small></div></div>
          <div class="bd-card-b">
            <div class="bd-bars">
              @foreach($byMonth as $m => $n)
                <div class="bd-bar {{ $m === $now->month ? 'now' : '' }}" title="{{ $months[$m - 1] }}: {{ $n }}"><b>{{ $n }}</b><span class="track"><i style="height:{{ max(4, round($n / $maxMonth * 100)) }}%"></i></span><em>{{ $months[$m - 1] }}</em></div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ============ People ============ --}}
  <div class="bd-pane" data-pane="people">
    <div class="bd-card">
      <div class="bd-card-h flex-wrap">
        <div class="d-flex flex-wrap gap-2">
          <input type="search" class="form-control form-control-sm" id="bd-q" placeholder="Search name, email, ID…" style="width:240px">
          <select class="form-select form-select-sm" id="bd-f" style="width:auto">
            <option value="">Everyone ({{ $people->count() }})</option><option value="soon">Next 30 days</option><option value="month">{{ $now->format('F') }}</option>
            <option value="on">Wishes on</option><option value="off">Wishes switched off ({{ $kpi['off'] }})</option><option value="noemail">No email ({{ $kpi['noEmail'] }})</option>
          </select>
        </div>
        <small id="bd-count"></small>
      </div>
      <div class="bd-card-b">
        <div class="table-responsive"><table class="table bd-table align-middle mb-0">
          <thead><tr><th>Participant</th><th>Birthday</th><th>Next</th><th class="d-none d-md-table-cell">Email</th><th class="d-none d-lg-table-cell">Last wished</th><th class="text-center">Wishes</th><th></th></tr></thead>
          <tbody id="bd-people"></tbody>
        </table></div>
        <div class="bd-empty d-none" id="bd-people-empty"><div class="big">🔍</div>Nobody matches.</div>
      </div>
    </div>
  </div>

  {{-- ============ Card designs ============ --}}
  <div class="bd-pane" data-pane="designs">
    <p class="text-muted small mb-3"><i class="bi bi-info-circle"></i> The <b>default</b> design goes out with the automatic wishes. Every card is made for the person — their name (and photo on the built-in designs) is written on it. Previews use a sample participant.</p>
    <div class="row g-3">
      <div class="col-md-6 col-xl-4">
        <label class="bd-drop" id="bd-drop" for="bd-file">
          <span class="ic"><i class="bi bi-cloud-arrow-up"></i></span>
          <b>Upload your own design</b>
          <span class="small mt-1">Made in Canva, Photoshop…? Upload a JPG or PNG (best 1200 × 800 px, max 6 MB).<br>We write each person's name on it.</span>
          <span class="btn btn-sm btn-outline-primary mt-3"><i class="bi bi-upload"></i> Choose picture</span>
          <input type="file" id="bd-file" accept="image/jpeg,image/png,image/webp" hidden>
        </label>
      </div>
      @foreach($cards as $c)
        <div class="col-md-6 col-xl-4">
          <div class="bd-design {{ $c->is_default ? 'is-default' : '' }}">
            <div class="pic"><img src="{{ route('birthdays.thumb', $c->id) }}?v={{ strtotime($c->updated_at) }}" alt="{{ $c->name }}" loading="lazy">@if($c->is_default)<span class="tag"><i class="bi bi-star-fill"></i> Default</span>@endif</div>
            <div class="body">
              <div><b>{{ $c->name }}</b><br><small>{{ $c->kind === 'upload' ? 'Your design' : (\App\Support\BirthdayCard::PRESETS[$c->preset][1] ?? 'Built-in') }}</small></div>
              <div class="d-flex gap-2 mt-auto flex-wrap">
                @unless($c->is_default)<button class="btn btn-sm btn-outline-primary" data-default="{{ $c->id }}"><i class="bi bi-star"></i> Make default</button>@endunless
                <button class="btn btn-sm btn-outline-secondary" data-edit="{{ $c->id }}"><i class="bi bi-sliders"></i> Customize</button>
                <button class="btn btn-sm btn-outline-secondary" data-compose data-card="{{ $c->id }}" title="Send with this design"><i class="bi bi-send"></i></button>
                @if($c->kind === 'upload' && !$c->is_default)<button class="btn btn-sm btn-outline-danger ms-auto" data-delete="{{ $c->id }}" title="Delete"><i class="bi bi-trash3"></i></button>@endif
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- ============ Message & schedule ============ --}}
  <div class="bd-pane" data-pane="message">
    <div class="row g-4">
      <div class="col-xl-5">
        <form id="bd-settings" class="bd-card" novalidate><div class="bd-card-b pt-3">
          <div class="bd-section"><i class="bi bi-clock"></i> Schedule</div>
          <div class="row g-2 mb-3">
            <div class="col-5"><label class="bd-label" for="bd-time">Send at</label><input type="time" class="form-control" id="bd-time" name="send_time" value="{{ $s['send_time'] }}" step="900"></div>
            <div class="col-7"><label class="bd-label" for="bd-tz">Time zone</label>
              <select class="form-select" id="bd-tz" name="timezone">@foreach($timezones as $tz)<option @selected($tz === $s['timezone'])>{{ $tz }}</option>@endforeach</select></div>
          </div>
          <div class="form-check form-switch mb-4">
            <input class="form-check-input" type="checkbox" role="switch" id="bd-dash" name="dashboard" @checked($s['dashboard'] === '1')>
            <label class="form-check-label" for="bd-dash">Also show a birthday greeting in the participant's dashboard on their day</label>
          </div>

          <div class="bd-section"><i class="bi bi-image"></i> On the card</div>
          <label class="bd-label" for="bd-headline">Headline</label>
          <input class="form-control mb-3" id="bd-headline" name="headline" maxlength="40" value="{{ $s['headline'] }}">
          <label class="bd-label" for="bd-cardmsg">Short message <small class="text-muted fw-normal">(one or two lines)</small></label>
          <input class="form-control mb-3" id="bd-cardmsg" name="card_message" maxlength="160" value="{{ $s['card_message'] }}">
          <label class="bd-label" for="bd-sign">Signed by</label>
          <input class="form-control mb-4" id="bd-sign" name="signature" maxlength="60" value="{{ $s['signature'] }}">

          <div class="bd-section"><i class="bi bi-envelope"></i> Email</div>
          <label class="bd-label" for="bd-subject">Subject</label>
          <input class="form-control mb-3" id="bd-subject" name="subject" maxlength="150" value="{{ $s['subject'] }}">
          <label class="bd-label" for="bd-body">Message</label>
          <textarea class="form-control" id="bd-body" name="email_body" rows="8" maxlength="3000">{{ $s['email_body'] }}</textarea>
          <div class="bd-vars" data-for="bd-body">@foreach(\App\Support\Birthdays::VARS as $k => $label)<button type="button" data-var="{{ $k }}" title="{{ $label }}">{{ $k }}</button>@endforeach</div>
          <div class="form-text">Click a placeholder to insert it. Use **double stars** for bold.</div>
          <div class="d-flex gap-2 mt-4 flex-wrap">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Save</button>
            <button class="btn btn-outline-secondary" type="button" data-compose><i class="bi bi-send"></i> Try it — send a test</button>
          </div>
        </div></form>
      </div>
      <div class="col-xl-7">
        <div class="bd-sticky">
          <div class="d-flex justify-content-between align-items-center mb-2"><b style="color:var(--ad-ink)"><i class="bi bi-eye"></i> Email preview</b><small class="text-muted">Updates as you type · sample participant</small></div>
          <iframe class="bd-mailframe" id="bd-mailframe" title="Email preview"></iframe>
        </div>
      </div>
    </div>
  </div>

  {{-- ============ History ============ --}}
  <div class="bd-pane" data-pane="history">
    <div class="bd-card">
      <div class="bd-card-h flex-wrap">
        <div class="d-flex gap-2 flex-wrap">
          <select class="form-select form-select-sm" id="bd-hs" style="width:auto"><option value="">All results</option><option value="sent">Sent</option><option value="failed">Failed</option></select>
          <select class="form-select form-select-sm" id="bd-hm" style="width:auto"><option value="">Automatic &amp; custom</option><option value="auto">Automatic</option><option value="manual">Custom</option></select>
        </div>
        <small class="text-muted">Latest 300</small>
      </div>
      <div class="bd-card-b">
        @if($history->isEmpty())
          <div class="bd-empty"><div class="big">💌</div><b>No cards sent yet</b>Switch the wishes on, or send a custom card.</div>
        @else
        <div class="table-responsive"><table class="table bd-table align-middle mb-0">
          <thead><tr><th>When</th><th>Participant</th><th>Type</th><th class="d-none d-md-table-cell">Design</th><th class="d-none d-lg-table-cell">Sent to</th><th>Result</th><th></th></tr></thead>
          <tbody>
            @foreach($history as $g)
              <tr data-hs="{{ $g->status }}" data-hm="{{ $g->mode }}">
                <td class="text-nowrap">{{ \Carbon\Carbon::parse($g->sent_at ?? $g->updated_at)->timezone($s['timezone'])->format('j M Y, H:i') }}</td>
                <td><b style="color:var(--ad-ink)">{{ trim(($g->title ? $g->title . ' ' : '') . $g->first_name . ' ' . $g->last_name) ?: 'Deleted participant' }}</b><br><small class="text-muted">{{ $g->code }}</small></td>
                <td>@if($g->mode === 'auto')<span class="bd-st info"><i class="bi bi-robot"></i> Automatic</span>@else<span class="bd-st" style="background:var(--bd-pink-50);color:var(--bd-pink)"><i class="bi bi-magic"></i> Custom</span>@endif
                  @if($g->by_name)<br><small class="text-muted">by {{ $g->by_name }}</small>@endif</td>
                <td class="d-none d-md-table-cell">{{ $g->card_name ?? '—' }}</td>
                <td class="d-none d-lg-table-cell">{{ $g->email }}</td>
                <td>@if($g->status === 'sent')<span class="bd-st ok"><i class="bi bi-check-circle-fill"></i> Sent</span>@else<span class="bd-st bad" title="{{ $g->error }}"><i class="bi bi-exclamation-triangle-fill"></i> Failed</span><br><small class="text-danger">{{ \Illuminate\Support\Str::limit($g->error, 60) }}</small>@endif</td>
                <td class="text-end">@if($g->status !== 'sent')<button class="btn btn-sm btn-outline-primary" data-resend="{{ $g->id }}"><i class="bi bi-arrow-repeat"></i> Retry</button>@endif</td>
              </tr>
            @endforeach
          </tbody>
        </table></div>
        @endif
      </div>
    </div>
  </div>
</section>

{{-- ============ Composer ============ --}}
<div class="modal fade bd-modal" id="bd-compose" tabindex="-1" aria-labelledby="bd-compose-t" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <div><h5 class="modal-title fw-bold" id="bd-compose-t"><i class="bi bi-magic" style="color:var(--bd-pink)"></i> Custom birthday card</h5><small class="text-muted">Make it personal — change the design and words just for this person.</small></div>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
      <div class="row g-4">
        <div class="col-lg-7 bd-stick-col"><div class="bd-stick">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="bd-seg" role="tablist"><button type="button" class="on" data-view="card">Card</button><button type="button" data-view="email">Email</button></div>
            <a class="btn btn-sm btn-link text-decoration-none" id="bd-dl" href="#" download><i class="bi bi-download"></i> Download JPG</a>
          </div>
          <div class="bd-preview" id="bd-cprev"><img id="bd-cimg" alt="Card preview"><span class="spinner-border spinner-border-sm text-light spin"></span></div>
          <iframe class="bd-mailframe d-none" id="bd-cmail" title="Email preview"></iframe>
        </div></div>
        <div class="col-lg-5">
          <span class="bd-label">To</span>
          <div class="position-relative mb-3" id="bd-whobox">
            <div class="bd-who d-none" id="bd-who"></div>
            <input type="search" class="form-control" id="bd-find" placeholder="Search a participant by name, email or ID…" autocomplete="off">
            <div class="bd-results d-none" id="bd-results"></div>
          </div>
          <span class="bd-label">Design</span>
          <div class="bd-pick mb-3" id="bd-pick">
            @foreach($cards as $c)<label title="{{ $c->name }}"><input type="radio" name="bd-card" value="{{ $c->id }}" @checked($c->is_default)><img src="{{ route('birthdays.thumb', $c->id) }}?v={{ strtotime($c->updated_at) }}" alt="{{ $c->name }}" loading="lazy">@if($c->kind === 'upload')<span class="mine">Yours</span>@endif</label>@endforeach
            <label class="bd-pick-up" id="c-up" title="Upload your own design for this card (JPG / PNG, best 1200 × 800 px)"><input type="file" id="c-file" accept="image/jpeg,image/png,image/webp" hidden><i class="bi bi-cloud-arrow-up"></i><span>Upload design</span></label>
          </div>
          <form id="bd-cform" novalidate>
            <div data-preset-only><label class="bd-label" for="c-headline">Headline</label>
            <input class="form-control mb-2" id="c-headline" name="headline" maxlength="40" placeholder="{{ $s['headline'] }}"></div>
            <label class="bd-label" for="c-message">Message on the card</label>
            <textarea class="form-control mb-2" id="c-message" name="message" rows="2" maxlength="160" placeholder="{{ $s['card_message'] }}"></textarea>
            <div data-preset-only><label class="bd-label" for="c-signature">Signed by</label>
            <input class="form-control mb-2" id="c-signature" name="signature" maxlength="60" placeholder="{{ $s['signature'] }}"></div>
            <div class="bd-place" id="c-place" hidden>
              <div class="bd-section mb-2"><i class="bi bi-fonts"></i> Name on your design</div>
              <div class="row-l"><label class="bd-label" for="c-y">Height</label><small class="text-muted" id="c-y-v"></small></div>
              <input type="range" class="form-range mb-1" id="c-y" name="name_y" min="5" max="95" step="1">
              <div class="row-l"><label class="bd-label" for="c-size">Size</label><small class="text-muted" id="c-size-v"></small></div>
              <input type="range" class="form-range mb-2" id="c-size" name="name_size" min="24" max="140" step="2">
              <div class="d-flex gap-3 align-items-end flex-wrap mb-2">
                <div><label class="bd-label" for="c-color">Colour</label><input type="color" class="form-control form-control-color" id="c-color" name="name_color"></div>
                <div><span class="bd-label">Align</span><div class="bd-seg" id="c-align"><button type="button" data-a="left" aria-label="Left"><i class="bi bi-text-left"></i></button><button type="button" data-a="center" aria-label="Centre"><i class="bi bi-text-center"></i></button><button type="button" data-a="right" aria-label="Right"><i class="bi bi-text-right"></i></button></div></div>
              </div>
              <div class="d-flex gap-3 flex-wrap">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="c-msg" name="show_message"><label class="form-check-label small" for="c-msg">Message under the name</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="c-shadow" name="shadow"><label class="form-check-label small" for="c-shadow">Soft shadow</label></div>
              </div>
              <button type="button" class="btn btn-sm btn-link px-0 mt-1" id="c-keep"><i class="bi bi-bookmark-check"></i> Keep this placement for the design</button>
            </div>
            <div class="d-flex gap-3 flex-wrap mb-3 mt-2" id="c-toggles">
              <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="c-photo" name="show_photo" checked><label class="form-check-label small" for="c-photo">Photo</label></div>
              <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="c-age" name="show_age"><label class="form-check-label small" for="c-age">Age (“45th birthday”)</label></div>
            </div>
            <details class="mb-1">
              <summary class="bd-label" style="cursor:pointer">Email text <small class="text-muted fw-normal">(optional — default from Message &amp; schedule)</small></summary>
              <input class="form-control mt-2 mb-2" id="c-subject" name="subject" maxlength="150" placeholder="{{ $s['subject'] }}">
              <textarea class="form-control" id="c-body" name="email_body" rows="6" maxlength="3000" placeholder="{{ $s['email_body'] }}"></textarea>
            </details>
          </form>
        </div>
      </div>
    </div>
    <div class="modal-footer border-0 pt-0">
      <small class="text-muted me-auto" id="bd-cnote">Choose a participant to see their card.</small>
      <button class="btn btn-outline-secondary" id="bd-ctest" disabled><i class="bi bi-envelope"></i> Send test to me</button>
      <button class="btn btn-primary" id="bd-csend" disabled><i class="bi bi-send-fill"></i> <span>Send card</span></button>
    </div>
  </div></div>
</div>

{{-- ============ Design editor ============ --}}
<div class="modal fade bd-modal" id="bd-editor" tabindex="-1" aria-labelledby="bd-editor-t" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold" id="bd-editor-t"><i class="bi bi-sliders" style="color:var(--bd-pink)"></i> Customize design</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <div class="row g-4">
        <div class="col-lg-7 bd-stick-col"><div class="bd-stick"><div class="bd-preview" id="bd-eprev"><img id="bd-eimg" alt="Design preview"><span class="spinner-border spinner-border-sm text-light spin"></span></div>
          <small class="text-muted d-block mt-2"><i class="bi bi-person"></i> Shown with a sample participant.</small></div></div>
        <div class="col-lg-5">
          <form id="bd-eform" novalidate>
            <label class="bd-label" for="e-name">Design name</label>
            <input class="form-control mb-3" id="e-name" name="name" maxlength="120">
            <div data-only="upload">
              <div class="bd-section"><i class="bi bi-fonts"></i> Name on the picture</div>
              <label class="bd-label" for="e-y">Height <small class="text-muted fw-normal" id="e-y-v"></small></label>
              <input type="range" class="form-range mb-2" id="e-y" name="name_y" min="5" max="95" step="1">
              <label class="bd-label" for="e-size">Size <small class="text-muted fw-normal" id="e-size-v"></small></label>
              <input type="range" class="form-range mb-2" id="e-size" name="name_size" min="24" max="140" step="2">
              <div class="d-flex gap-3 align-items-end mb-3 flex-wrap">
                <div><label class="bd-label" for="e-color">Colour</label><input type="color" class="form-control form-control-color" id="e-color" name="name_color"></div>
                <div><span class="bd-label">Align</span><div class="bd-seg" id="e-align"><button type="button" data-a="left"><i class="bi bi-text-left"></i></button><button type="button" data-a="center"><i class="bi bi-text-center"></i></button><button type="button" data-a="right"><i class="bi bi-text-right"></i></button></div></div>
              </div>
              <div class="d-flex gap-3 flex-wrap mb-3">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="e-msg" name="show_message"><label class="form-check-label small" for="e-msg">Message under the name</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="e-shadow" name="shadow"><label class="form-check-label small" for="e-shadow">Soft shadow</label></div>
              </div>
            </div>
            <div data-only="preset">
              <div class="bd-section"><i class="bi bi-toggles"></i> Show</div>
              <div class="d-flex gap-3 flex-wrap mb-3">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="e-photo" name="show_photo"><label class="form-check-label small" for="e-photo">Photo</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="e-logo" name="show_logo"><label class="form-check-label small" for="e-logo">BICS logo</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="e-age" name="show_age"><label class="form-check-label small" for="e-age">Age</label></div>
              </div>
            </div>
            <div class="bd-section"><i class="bi bi-chat-heart"></i> Words <small class="text-muted fw-normal text-transform-none" style="text-transform:none;letter-spacing:0">(empty = the general texts)</small></div>
            <input class="form-control mb-2" id="e-headline" name="headline" maxlength="40" placeholder="Headline: {{ $s['headline'] }}">
            <input class="form-control mb-2" id="e-message" name="message" maxlength="160" placeholder="Message: {{ $s['card_message'] }}">
            <input class="form-control mb-2" id="e-signature" name="signature" maxlength="60" placeholder="Signed by: {{ $s['signature'] }}">
          </form>
        </div>
      </div>
    </div>
    <div class="modal-footer border-0 pt-0"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="bd-esave"><i class="bi bi-check2-circle"></i> Save design</button></div>
  </div></div>
</div>
@endsection
@push('scripts')
@php
  $boot = [
    'people' => $rows, 'cards' => $cards->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'kind' => $c->kind, 'default' => (bool) $c->is_default, 'options' => json_decode($c->options ?: '[]', true) ?: []])->values(),
    'defaults' => \App\Support\BirthdayCard::DEFAULTS, 'tab' => $tab,
    'r' => ['toggle' => route('birthdays.toggle'), 'settings' => route('birthdays.settings'), 'search' => route('birthdays.search'), 'preview' => route('birthdays.preview'),
            'mail' => route('birthdays.preview-email'), 'send' => route('birthdays.send'), 'run' => route('birthdays.run'), 'upload' => route('birthdays.cards.upload'),
            'card' => url('birthdays/cards'), 'wishes' => url('birthdays/people'), 'resend' => url('birthdays/greetings'), 'index' => route('birthdays.index')],
  ];
@endphp
<script>
(function () {
  'use strict';
  var B = @json($boot);
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  function $(id) { return document.getElementById(id); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function toast(msg, icon) { Swal.fire({ toast: true, position: 'top-end', icon: icon || 'success', title: msg, showConfirmButton: false, timer: 3200 }); }
  function req(url, body, method) {
    var isForm = body instanceof FormData;
    return fetch(url, { method: method || 'POST', headers: Object.assign({ 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, isForm ? {} : { 'Content-Type': 'application/json' }), body: isForm ? body : JSON.stringify(body || {}) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) { if (!res.ok) { var e = res.d.errors || {}, k = Object.keys(e)[0]; Swal.fire('Not done', res.d.message || (k ? e[k][0] : 'Please try again.'), 'warning'); return null; } return res.d; });
  }
  function debounce(fn, ms) { var t; return function () { var a = arguments; clearTimeout(t); t = setTimeout(function () { fn.apply(null, a); }, ms); }; }
  function qs(o) { return Object.keys(o).filter(function (k) { return o[k] !== '' && o[k] != null; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(o[k]); }).join('&'); }
  function avatar(p, size) {
    size = size || 36;
    var hue = (p.id * 47) % 360;
    return p.photo ? '<img class="bd-av" style="width:' + size + 'px;height:' + size + 'px" src="' + esc(p.photo) + '" alt="">'
      : '<span class="bd-av" style="width:' + size + 'px;height:' + size + 'px;background:hsl(' + hue + ' 55% 92%);color:hsl(' + hue + ' 45% 35%)">' + esc(p.initials) + '</span>';
  }

  /* ---------- tabs (remembered in the address) ---------- */
  var mailLoaded = false;
  function tab(name) {
    document.querySelectorAll('.bd-tabs [data-tab]').forEach(function (b) { b.classList.toggle('on', b.dataset.tab === name); });
    document.querySelectorAll('.bd-pane').forEach(function (p) { p.classList.toggle('on', p.dataset.pane === name); });
    history.replaceState(null, '', B.r.index + (name === 'overview' ? '' : '?tab=' + name));
    if (name === 'message' && !mailLoaded) { mailLoaded = true; refreshMail(); }
    if (name === 'people') renderPeople();
  }
  document.querySelectorAll('.bd-tabs [data-tab]').forEach(function (b) { b.addEventListener('click', function () { tab(b.dataset.tab); }); });
  document.querySelectorAll('[data-goto]').forEach(function (b) { b.addEventListener('click', function () { tab(b.dataset.goto); }); });

  /* ---------- master switch ---------- */
  var tg = $('bd-toggle');
  tg.addEventListener('click', function () {
    var turnOn = tg.getAttribute('aria-checked') !== 'true';
    var go = function () {
      tg.disabled = true;
      req(B.r.toggle, { enabled: turnOn }).then(function (d) {
        tg.disabled = false;
        if (!d) return;
        tg.setAttribute('aria-checked', d.enabled ? 'true' : 'false');
        $('bd-toggle-l').textContent = d.enabled ? 'Switched on' : 'Switched off';
        var pill = $('bd-pill'); pill.className = 'bd-pill ' + (d.enabled ? 'on' : 'off'); pill.lastElementChild.textContent = d.enabled ? 'On' : 'Off';
        $('bd-hero-text').textContent = d.message;
        toast(d.enabled ? 'Birthday wishes switched on 🎉' : 'Birthday wishes switched off', d.enabled ? 'success' : 'info');
      });
    };
    if (turnOn) Swal.fire({ title: 'Switch on birthday wishes?', html: 'From now on everyone gets your default card by email on their birthday.', icon: 'question', showCancelButton: true, confirmButtonText: 'Switch on' }).then(function (r) { if (r.isConfirmed) go(); });
    else go();
  });

  var run = $('bd-run');
  if (run) run.addEventListener('click', function () {
    Swal.fire({ title: "Send today's cards now?", text: 'Everyone with a birthday today who has not had a card yet gets one now.', icon: 'question', showCancelButton: true, confirmButtonText: 'Send now' })
      .then(function (r) { if (!r.isConfirmed) return; run.disabled = true; run.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending…';
        req(B.r.run).then(function (d) { if (d) Swal.fire('Done', d.message, 'success').then(function () { location.reload(); }); else { run.disabled = false; run.innerHTML = '<i class="bi bi-send"></i> Send today\'s cards now'; } }); });
  });

  /* ---------- people ---------- */
  var peopleRendered = false;
  function renderPeople() {
    var q = $('bd-q').value.trim().toLowerCase(), f = $('bd-f').value, now = new Date().getMonth() + 1;
    var list = B.people.filter(function (p) {
      if (q && (p.name + ' ' + (p.email || '') + ' ' + p.code).toLowerCase().indexOf(q) < 0) return false;
      if (f === 'soon') return p.in_days <= 30;
      if (f === 'month') return p.month === now && p.in_days <= 31;
      if (f === 'on') return p.wishes; if (f === 'off') return !p.wishes;
      if (f === 'noemail') return !p.email;
      return true;
    });
    $('bd-count').textContent = list.length + ' of ' + B.people.length;
    $('bd-people-empty').classList.toggle('d-none', list.length > 0);
    $('bd-people').innerHTML = list.slice(0, 400).map(function (p) {
      var next = p.in_days === 0 ? '<span class="bd-st" style="background:var(--bd-pink-50);color:var(--bd-pink)">🎂 Today</span>' : (p.in_days === 1 ? 'Tomorrow' : 'in ' + p.in_days + ' days') + '<br><small class="text-muted">' + esc(p.next) + '</small>';
      return '<tr><td><div class="bd-name">' + avatar(p) + '<div><b>' + esc(p.name) + '</b><small>' + esc(p.code) + '</small></div></div></td>' +
        '<td>' + esc(p.birthday) + '<br><small class="text-muted">turns ' + p.turns + '</small></td><td>' + next + '</td>' +
        '<td class="d-none d-md-table-cell">' + (p.email ? esc(p.email) : '<span class="bd-st mute"><i class="bi bi-envelope-x"></i> No email</span>') + '</td>' +
        '<td class="d-none d-lg-table-cell">' + (p.last ? esc(p.last) : '<span class="text-muted">—</span>') + '</td>' +
        '<td class="text-center"><div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" role="switch" data-wish="' + p.id + '"' + (p.wishes ? ' checked' : '') + ' aria-label="Birthday wishes for ' + esc(p.name) + '"></div></td>' +
        '<td class="text-end"><button class="bd-iconbtn" data-compose="' + p.id + '" title="Custom card"><i class="bi bi-magic"></i></button></td></tr>';
    }).join('');
    peopleRendered = true;
  }
  $('bd-q').addEventListener('input', debounce(renderPeople, 150));
  $('bd-f').addEventListener('change', renderPeople);
  $('bd-people').addEventListener('change', function (e) {
    var t = e.target; if (!t.dataset.wish) return;
    var id = +t.dataset.wish, on = t.checked;
    req(B.r.wishes + '/' + id + '/wishes', { on: on }).then(function (d) {
      if (!d) { t.checked = !on; return; }
      B.people.forEach(function (p) { if (p.id === id) p.wishes = on; });
      toast(d.message, on ? 'success' : 'info');
    });
  });

  /* ---------- message & schedule ---------- */
  var sf = $('bd-settings');
  function settingsData() {
    var el = sf.elements;
    return { send_time: el.send_time.value, timezone: el.timezone.value, subject: el.subject.value, headline: el.headline.value, email_body: el.email_body.value,
      card_message: el.card_message.value, signature: el.signature.value, dashboard: el.dashboard.checked };
  }
  function loadMail(frame, body) {
    fetch(B.r.mail, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      .then(function (r) { return r.text(); }).then(function (html) { frame.srcdoc = html; });
  }
  var refreshMail = debounce(function () {
    var d = settingsData();
    loadMail($('bd-mailframe'), { subject: d.subject, email_body: d.email_body, headline: d.headline, message: d.card_message, signature: d.signature });
  }, 500);
  sf.addEventListener('input', refreshMail);
  sf.addEventListener('submit', function (e) {
    e.preventDefault();
    req(B.r.settings, settingsData()).then(function (d) { if (d) toast(d.message); });
  });
  document.querySelectorAll('.bd-vars [data-var]').forEach(function (b) {
    b.addEventListener('click', function () {
      var ta = $(b.parentNode.dataset.for), v = b.dataset.var, s = ta.selectionStart || ta.value.length;
      ta.value = ta.value.slice(0, s) + v + ta.value.slice(ta.selectionEnd || s); ta.focus(); ta.selectionStart = ta.selectionEnd = s + v.length;
      ta.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });

  /* ---------- history ---------- */
  function filterHistory() {
    var s = $('bd-hs').value, m = $('bd-hm').value;
    document.querySelectorAll('[data-hs]').forEach(function (tr) { tr.hidden = (s && tr.dataset.hs !== s) || (m && tr.dataset.hm !== m); });
  }
  $('bd-hs').addEventListener('change', filterHistory); $('bd-hm').addEventListener('change', filterHistory);
  document.querySelectorAll('[data-resend]').forEach(function (b) {
    b.addEventListener('click', function () { b.disabled = true; req(B.r.resend + '/' + b.dataset.resend + '/resend').then(function (d) { b.disabled = false; if (d) Swal.fire('Sent', d.message, 'success').then(function () { location.reload(); }); }); });
  });

  /* ---------- composer ---------- */
  var cm = new bootstrap.Modal($('bd-compose')), who = null, cform = $('bd-cform');
  function cardId() { var r = document.querySelector('input[name="bd-card"]:checked'); return r ? r.value : ''; }
  function cardOf(id) { return B.cards.filter(function (c) { return String(c.id) === String(id); })[0]; }
  function isUpload() { var c = cardOf(cardId()); return c && c.kind === 'upload'; }
  function cOpts() {
    var el = cform.elements;
    var o = { headline: el.headline.value, message: el.message.value, signature: el.signature.value };
    if (isUpload()) Object.assign(o, { name_y: el.name_y.value, name_size: el.name_size.value, name_color: el.name_color.value, align: cform.dataset.align || 'center', show_message: el.show_message.checked ? 1 : 0, shadow: el.shadow.checked ? 1 : 0 });
    else Object.assign(o, { show_photo: el.show_photo.checked ? 1 : 0, show_age: el.show_age.checked ? 1 : 0 });
    return o;
  }
  function cAlign(a) { cform.dataset.align = a; document.querySelectorAll('#c-align [data-a]').forEach(function (b) { b.classList.toggle('on', b.dataset.a === a); }); }
  document.querySelectorAll('#c-align [data-a]').forEach(function (b) { b.addEventListener('click', function () { cAlign(b.dataset.a); cRefresh(); }); });
  /* picking a design: uploaded artwork gets the name-placement controls, built-in ones the photo / age switches */
  function designChanged() {
    var c = cardOf(cardId()), up = c && c.kind === 'upload';
    $('c-place').hidden = !up;
    $('c-toggles').classList.toggle('d-none', up);   // (d-flex would beat the hidden attribute)
    document.querySelectorAll('#bd-cform [data-preset-only]').forEach(function (x) { x.hidden = up; });
    if (up) {
      var o = Object.assign({}, B.defaults, c.options || {}), el = cform.elements;
      el.name_y.value = o.name_y; el.name_size.value = o.name_size; el.name_color.value = o.name_color || '#ffffff';
      el.show_message.checked = !!o.show_message; el.shadow.checked = !!o.shadow; cAlign(o.align || 'center');
    }
    placeLabels();
  }
  function placeLabels() { $('c-y-v').textContent = cform.elements.name_y.value + '% from the top'; $('c-size-v').textContent = cform.elements.name_size.value + ' px'; }
  cform.addEventListener('input', placeLabels);
  $('c-keep').addEventListener('click', function () {
    var c = cardOf(cardId()); if (!c) return;
    var o = cOpts(), keep = { name: c.name };
    ['name_y', 'name_size', 'name_color', 'align', 'show_message', 'shadow'].forEach(function (k) { keep[k] = o[k]; });
    ['headline', 'message', 'signature'].forEach(function (k) { keep[k] = (c.options || {})[k] || ' '; });
    req(B.r.card + '/' + c.id, keep).then(function (d) { if (!d) return; c.options = Object.assign({}, c.options, keep); toast('Placement saved for “' + c.name + '”.'); });
  });

  /* upload a design right here, for this card */
  function composerUpload(file) {
    if (!file) return;
    var tile = $('c-up'), label = tile.querySelector('span');
    var fd = new FormData(); fd.append('image', file);
    fd.append('name', (who ? 'For ' + who.name : file.name.replace(/\.[^.]+$/, '')).slice(0, 100));
    tile.classList.add('busy'); label.textContent = 'Uploading…';
    req(B.r.upload, fd).then(function (d) {
      tile.classList.remove('busy'); label.textContent = 'Upload design';
      if (!d) return;
      var c = { id: d.card.id, name: d.card.name, kind: 'upload', default: false, options: JSON.parse(d.card.options || '{}') };
      B.cards.push(c);
      var l = document.createElement('label'); l.title = c.name;
      l.innerHTML = '<input type="radio" name="bd-card" value="' + c.id + '"><img src="' + B.r.card + '/' + c.id + '/thumb.jpg?v=' + Date.now() + '" alt=""><span class="mine">Yours</span>';
      tile.parentNode.insertBefore(l, tile);
      l.querySelector('input').checked = true;
      designChanged(); cRefresh();
      toast('Design uploaded — move the name to where it fits.');
    });
  }
  $('c-file').addEventListener('change', function () { composerUpload(this.files[0]); this.value = ''; });
  ['dragenter', 'dragover'].forEach(function (t) { $('c-up').addEventListener(t, function (e) { e.preventDefault(); $('c-up').classList.add('over'); }); });
  ['dragleave', 'drop'].forEach(function (t) { $('c-up').addEventListener(t, function (e) { e.preventDefault(); $('c-up').classList.remove('over'); }); });
  $('c-up').addEventListener('drop', function (e) { composerUpload(e.dataTransfer.files[0]); });
  var cview = 'card';
  var cRefresh = debounce(function () {
    var o = Object.assign({ card_id: cardId(), participant_id: who ? who.id : '' }, cOpts());
    var url = B.r.preview + '?' + qs(o);
    var box = $('bd-cprev'); box.classList.add('loading');
    var img = $('bd-cimg'); img.onload = img.onerror = function () { box.classList.remove('loading'); }; img.src = url;
    $('bd-dl').href = url + '&download=1';
    if (cview === 'email') loadMail($('bd-cmail'), Object.assign({ subject: cform.elements.subject.value || undefined, email_body: cform.elements.email_body.value || undefined }, o));
  }, 350);
  document.querySelectorAll('#bd-compose [data-view]').forEach(function (b) {
    b.addEventListener('click', function () {
      cview = b.dataset.view;
      document.querySelectorAll('#bd-compose [data-view]').forEach(function (x) { x.classList.toggle('on', x === b); });
      $('bd-cprev').classList.toggle('d-none', cview !== 'card'); $('bd-cmail').classList.toggle('d-none', cview !== 'email');
      cRefresh();
    });
  });
  cform.addEventListener('input', cRefresh); cform.addEventListener('change', cRefresh);
  $('bd-pick').addEventListener('change', function (e) { if (e.target.name === 'bd-card') { designChanged(); cRefresh(); } });
  function setWho(p) {
    who = p;
    var box = $('bd-who');
    if (p) {
      box.innerHTML = avatar(p, 40) + '<div style="min-width:0;flex:1"><b>' + esc(p.name) + '</b><small>' + esc(p.email || 'No email address') + ' · birthday ' + esc(p.birthday) + '</small></div><button type="button" class="btn btn-sm btn-link" id="bd-change">Change</button>';
      box.classList.remove('d-none'); $('bd-find').classList.add('d-none');
      $('bd-change').addEventListener('click', function () { setWho(null); $('bd-find').focus(); });
      var first = (p.name.split(' ').filter(function (w) { return !/^(dr|prof|mr|mrs|ms|miss)\.?$/i.test(w); })[0]) || p.name;
      $('bd-csend').querySelector('span').textContent = 'Send to ' + first;
    } else {
      box.classList.add('d-none'); $('bd-find').classList.remove('d-none'); $('bd-find').value = '';
      $('bd-csend').querySelector('span').textContent = 'Send card';
    }
    var can = p && p.email;
    $('bd-csend').disabled = !can; $('bd-ctest').disabled = !p;
    $('bd-cnote').textContent = !p ? 'Choose a participant to see their card.' : (!p.email ? 'This person has no email address — you can still download the card.' : 'Goes to ' + p.email + '.');
    cRefresh();
  }
  var findSeq = 0;
  $('bd-find').addEventListener('input', debounce(function () {
    var q = $('bd-find').value.trim(), res = $('bd-results'), my = ++findSeq;
    if (q.length < 2) { res.classList.add('d-none'); return; }
    fetch(B.r.search + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      if (my !== findSeq) return;
      res.innerHTML = d.data.length ? d.data.map(function (p, i) { return '<button type="button" data-i="' + i + '">' + avatar(p, 32) + '<span><b>' + esc(p.name) + '</b><small>' + esc(p.email || 'no email') + ' · ' + esc(p.birthday) + '</small></span></button>'; }).join('')
        : '<div class="p-3 small text-muted">No participant with a date of birth matches.</div>';
      res.classList.remove('d-none');
      res.querySelectorAll('[data-i]').forEach(function (b) { b.addEventListener('click', function () { res.classList.add('d-none'); setWho(d.data[+b.dataset.i]); }); });
    });
  }, 250));
  document.addEventListener('click', function (e) { if (!$('bd-whobox').contains(e.target)) $('bd-results').classList.add('d-none'); });

  function openComposer(pid, card) {
    cform.reset(); cform.elements.show_photo.checked = true;
    var radios = document.querySelectorAll('input[name="bd-card"]');
    var def = B.cards.filter(function (c) { return c.default; })[0];
    radios.forEach(function (r) { r.checked = String(r.value) === String(card || (def && def.id)); });
    designChanged();
    var p = pid ? B.people.filter(function (x) { return x.id === +pid; })[0] : null;
    setWho(p || null);
    cm.show();
    if (!p) setTimeout(function () { $('bd-find').focus(); }, 300);
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-compose]'); if (!b) return;
    e.preventDefault(); openComposer(b.dataset.compose, b.dataset.card);
  });
  function sendCard(test) {
    if (!who) return;
    var btn = test ? $('bd-ctest') : $('bd-csend');
    var body = Object.assign({ participant_id: who.id, card_id: +cardId(), test: test, subject: cform.elements.subject.value, email_body: cform.elements.email_body.value }, cOpts());
    ['headline', 'message', 'signature'].forEach(function (k) { if (!body[k]) delete body[k]; });   // empty = what the design says
    var go = function () {
      btn.disabled = true; var old = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending…';
      req(B.r.send, body).then(function (d) {
        btn.disabled = false; btn.innerHTML = old;
        if (!d) return;
        if (test) toast(d.message);
        else { cm.hide(); Swal.fire({ icon: 'success', title: 'Card sent 🎉', text: d.message }).then(function () { location.reload(); }); }
      });
    };
    if (test) go();
    else Swal.fire({ title: 'Send this card?', html: 'To <b>' + esc(who.name) + '</b> at ' + esc(who.email) + '.', icon: 'question', showCancelButton: true, confirmButtonText: 'Send' }).then(function (r) { if (r.isConfirmed) go(); });
  }
  $('bd-csend').addEventListener('click', function () { sendCard(false); });
  $('bd-ctest').addEventListener('click', function () { sendCard(true); });

  /* ---------- designs ---------- */
  document.querySelectorAll('[data-default]').forEach(function (b) {
    b.addEventListener('click', function () { req(B.r.card + '/' + b.dataset.default + '/default').then(function (d) { if (d) { toast(d.message); setTimeout(function () { location.href = B.r.index + '?tab=designs'; }, 700); } }); });
  });
  document.querySelectorAll('[data-delete]').forEach(function (b) {
    b.addEventListener('click', function () {
      Swal.fire({ title: 'Delete this design?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#d33' }).then(function (r) {
        if (r.isConfirmed) req(B.r.card + '/' + b.dataset.delete, {}, 'DELETE').then(function (d) { if (d) location.href = B.r.index + '?tab=designs'; });
      });
    });
  });
  function upload(file) {
    if (!file) return;
    var fd = new FormData(); fd.append('image', file); fd.append('name', file.name.replace(/\.[^.]+$/, '').slice(0, 100));
    Swal.fire({ title: 'Uploading…', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
    req(B.r.upload, fd).then(function (d) {
      if (!d) return;
      Swal.close();
      B.cards.push({ id: d.card.id, name: d.card.name, kind: 'upload', default: false, options: JSON.parse(d.card.options || '{}') });
      openEditor(d.card.id, true);
    });
  }
  $('bd-file').addEventListener('change', function () { upload(this.files[0]); this.value = ''; });
  var drop = $('bd-drop');
  ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
  ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('over'); }); });
  drop.addEventListener('drop', function (e) { upload(e.dataTransfer.files[0]); });

  var em = new bootstrap.Modal($('bd-editor')), ef = $('bd-eform'), editing = null, reloadAfter = false;
  function eOpts() {
    var el = ef.elements, o = { headline: el.headline.value || ' ', message: el.message.value || ' ', signature: el.signature.value || ' ' };
    if (editing.kind === 'upload') Object.assign(o, { name_y: el.name_y.value, name_size: el.name_size.value, name_color: el.name_color.value, align: ef.dataset.align || 'center', show_message: el.show_message.checked ? 1 : 0, shadow: el.shadow.checked ? 1 : 0 });
    else Object.assign(o, { show_photo: el.show_photo.checked ? 1 : 0, show_logo: el.show_logo.checked ? 1 : 0, show_age: el.show_age.checked ? 1 : 0 });
    return o;
  }
  var eRefresh = debounce(function () {
    if (!editing) return;
    $('bd-e' + 'prev').classList.add('loading');
    var img = $('bd-eimg'); img.onload = img.onerror = function () { $('bd-eprev').classList.remove('loading'); };
    // preview with the edited options on top of the saved design
    img.src = B.r.preview + '?' + qs(Object.assign({ card_id: editing.id }, eOpts()));
    $('e-y-v').textContent = ef.elements.name_y.value + '% from the top'; $('e-size-v').textContent = ef.elements.name_size.value + ' px';
  }, 300);
  function openEditor(id, fresh) {
    editing = B.cards.filter(function (c) { return c.id === +id; })[0]; if (!editing) return;
    reloadAfter = !!fresh;
    var o = Object.assign({}, B.defaults, editing.options || {}), el = ef.elements;
    el.name.value = editing.name;
    document.querySelectorAll('#bd-eform [data-only]').forEach(function (x) { x.hidden = x.dataset.only !== editing.kind; });
    el.headline.value = o.headline || ''; el.message.value = o.message || ''; el.signature.value = o.signature || '';
    el.name_y.value = o.name_y; el.name_size.value = o.name_size; el.name_color.value = o.name_color || '#ffffff';
    el.show_message.checked = !!o.show_message; el.shadow.checked = !!o.shadow;
    el.show_photo.checked = !!o.show_photo; el.show_logo.checked = !!o.show_logo; el.show_age.checked = !!o.show_age;
    setAlign(o.align || 'center');
    $('bd-editor-t').innerHTML = '<i class="bi bi-sliders" style="color:var(--bd-pink)"></i> ' + (fresh ? 'Place the name on your design' : 'Customize “' + esc(editing.name) + '”');
    em.show(); eRefresh();
  }
  function setAlign(a) { ef.dataset.align = a; document.querySelectorAll('#e-align [data-a]').forEach(function (b) { b.classList.toggle('on', b.dataset.a === a); }); }
  document.querySelectorAll('#e-align [data-a]').forEach(function (b) { b.addEventListener('click', function () { setAlign(b.dataset.a); eRefresh(); }); });
  ef.addEventListener('input', eRefresh); ef.addEventListener('change', eRefresh);
  document.querySelectorAll('[data-edit]').forEach(function (b) { b.addEventListener('click', function () { openEditor(b.dataset.edit); }); });
  $('bd-esave').addEventListener('click', function () {
    var body = Object.assign({ name: ef.elements.name.value.trim() || editing.name }, eOpts());
    req(B.r.card + '/' + editing.id, body).then(function (d) { if (d) { em.hide(); toast(d.message); setTimeout(function () { location.href = B.r.index + '?tab=designs'; }, 600); } });
  });
  $('bd-editor').addEventListener('hidden.bs.modal', function () { if (reloadAfter) location.href = B.r.index + '?tab=designs'; });

  tab(B.tab);
})();
</script>
@endpush
