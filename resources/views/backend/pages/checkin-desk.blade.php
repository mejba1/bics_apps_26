@extends('backend.layouts.master')
@section('title')
Self Check-in
@endsection
@php
  $qrSvg = (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(260)->margin(0)->errorCorrection('H')->generate($url);
  $dt = fn ($v) => $v ? str_replace(' ', 'T', $v) : '';
@endphp
@push('styles')
<style>
  :root { --ck-pink: #b22e6b; }
  .ck-hero { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; border: 1px solid var(--ad-line); border-radius: 20px; padding: 20px 22px; margin-bottom: 18px;
    background: radial-gradient(520px 240px at 100% 0%, rgba(31,157,85,.12), transparent 70%), var(--ad-surface); }
  .ck-hero.closed { background: radial-gradient(520px 240px at 100% 0%, rgba(178,46,107,.10), transparent 70%), var(--ad-surface); }
  .ck-ic { width: 60px; height: 60px; border-radius: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.7rem; color: #fff; flex: none;
    background: linear-gradient(135deg, #0e4ca1, #1f9d55); box-shadow: 0 14px 28px -14px rgba(31,157,85,.9); }
  .ck-hero.closed .ck-ic { background: linear-gradient(135deg, #0e4ca1, var(--ck-pink)); }
  .ck-hero h2 { font-size: 1.3rem; font-weight: 800; margin: 0 0 3px; color: var(--ad-ink); display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
  .ck-hero p { margin: 0; color: var(--ad-muted); }
  .ck-pill { font-size: 12px; font-weight: 800; padding: 4px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px; }
  .ck-pill.on { background: var(--ad-green-50); color: var(--ad-green); } .ck-pill.off { background: var(--ad-chip); color: var(--ad-muted); }
  .ck-pill .dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
  .ck-pill.on .dot { animation: ckPulse 1.8s infinite; }
  @keyframes ckPulse { 0% { box-shadow: 0 0 0 0 rgba(31,157,85,.5); } 70% { box-shadow: 0 0 0 7px rgba(31,157,85,0); } 100% { box-shadow: 0 0 0 0 rgba(31,157,85,0); } }
  .ck-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--ad-line); margin-bottom: 18px; overflow-x: auto; }
  .ck-tabs button { border: 0; background: none; padding: 10px 14px; font-weight: 600; color: var(--ad-muted); border-bottom: 2.5px solid transparent; margin-bottom: -1px; white-space: nowrap; display: inline-flex; gap: 7px; align-items: center; }
  .ck-tabs button.on { color: var(--ad-ink); border-bottom-color: var(--ck-pink); }
  .ck-pane { display: none; } .ck-pane.on { display: block; }
  .ck-card { border: 1px solid var(--ad-line); border-radius: 18px; background: var(--ad-surface); height: 100%; }
  .ck-card-h { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 16px 18px 6px; flex-wrap: wrap; }
  .ck-card-h h3 { font-size: 1rem; font-weight: 800; margin: 0; color: var(--ad-ink); } .ck-card-h small { color: var(--ad-muted); }
  .ck-card-b { padding: 8px 18px 18px; }
  .ck-kpi { border: 1px solid var(--ad-line); border-radius: 16px; padding: 14px 16px; background: var(--ad-surface); height: 100%; }
  .ck-kpi .n { font-size: 1.7rem; font-weight: 800; line-height: 1; color: var(--ad-ink); } .ck-kpi .l { font-size: 12.5px; color: var(--ad-muted); margin-top: 5px; }
  .ck-progress { height: 10px; border-radius: 999px; background: var(--ad-chip); overflow: hidden; margin-top: 12px; }
  .ck-progress i { display: block; height: 100%; background: linear-gradient(90deg, #1f9d55, #3fc27a); border-radius: 999px; transition: width .6s ease; }
  .ck-row { display: flex; gap: 12px; align-items: center; padding: 10px 0; border-top: 1px solid var(--ad-line-soft); }
  .ck-row:first-child { border-top: 0; }
  .ck-row.fresh { animation: ckFresh 2.4s ease; border-radius: 12px; }
  @keyframes ckFresh { 0% { background: rgba(31,157,85,.22); } 100% { background: transparent; } }
  .ck-av { width: 46px; height: 46px; border-radius: 50%; object-fit: cover; flex: none; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; background: var(--ad-brand-50); color: var(--ad-brand); }
  .ck-who { flex: 1; min-width: 0; } .ck-who b { color: var(--ad-ink); display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .ck-who span { font-size: 12.5px; color: var(--ad-muted); }
  .ck-time { text-align: right; font-size: 12.5px; color: var(--ad-muted); white-space: nowrap; } .ck-time b { display: block; color: var(--ad-ink); font-size: 15px; }
  .ck-m { font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: 6px; text-transform: uppercase; letter-spacing: .03em; }
  .ck-m.self { background: var(--ad-green-50); color: var(--ad-green); } .ck-m.kiosk { background: var(--ad-teal-50); color: var(--ad-teal); } .ck-m.desk { background: var(--ad-brand-50); color: var(--ad-brand); }
  .ck-warn { font-size: 11.5px; font-weight: 700; color: var(--ad-amber); }
  .ck-undo { border: 0; background: none; color: var(--ad-muted); width: 32px; height: 32px; border-radius: 8px; }
  .ck-undo:hover { background: var(--ad-red-50); color: var(--ad-red); }
  .ck-feed { max-height: 640px; overflow: auto; }
  .ck-empty { text-align: center; color: var(--ad-muted); padding: 40px 10px; } .ck-empty .big { font-size: 2.6rem; margin-bottom: 8px; } .ck-empty b { color: var(--ad-ink); display: block; }
  .ck-res { border: 1px solid var(--ad-line); border-radius: 14px; padding: 10px 12px; display: flex; gap: 10px; align-items: center; margin-top: 8px; }
  .ck-res b { color: var(--ad-ink); } .ck-res small { color: var(--ad-muted); display: block; }
  .ck-qr { width: 220px; height: 220px; padding: 12px; background: #fff; border-radius: 16px; border: 1px solid var(--ad-line); } .ck-qr svg { width: 100%; height: 100%; display: block; }
  .ck-link { display: flex; gap: 8px; align-items: center; border: 1px solid var(--ad-line); border-radius: 12px; padding: 8px 8px 8px 12px; background: var(--ad-bg); }
  .ck-link code { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--ad-ink); font-size: 13px; background: none; }
  .ck-print { border: 1px solid var(--ad-line); border-radius: 16px; padding: 14px; text-align: center; background: var(--ad-surface); height: 100%; display: flex; flex-direction: column; gap: 8px; align-items: center; }
  .ck-print .ic { font-size: 1.8rem; color: var(--ck-pink); } .ck-print b { color: var(--ad-ink); } .ck-print small { color: var(--ad-muted); }
  .ck-sec { font-size: 12px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: var(--ck-pink); margin: 6px 0 12px; display: flex; gap: 8px; align-items: center; }
  .ck-sec::after { content: ""; flex: 1; border-top: 1px solid var(--ad-line); }
  .ck-label { font-size: 13px; font-weight: 700; color: var(--ad-ink); margin-bottom: 5px; display: block; }
  .ck-opt { border: 1.5px solid var(--ad-line); border-radius: 14px; padding: 12px 14px; display: flex; gap: 10px; align-items: flex-start; cursor: pointer; height: 100%; }
  .ck-opt:has(input:checked) { border-color: var(--ad-brand); background: var(--ad-brand-50); }
  .ck-opt b { color: var(--ad-ink); display: block; font-size: 14px; } .ck-opt small { color: var(--ad-muted); }
  .ck-big-switch .form-check-input { width: 3.2em; height: 1.7em; cursor: pointer; }
  .ck-qrprev { width: 100%; max-width: 230px; aspect-ratio: 1; border-radius: 14px; border: 1px solid var(--ad-line); display: flex; align-items: center; justify-content: center; padding: 10px;
    background: repeating-conic-gradient(var(--ad-chip) 0% 25%, var(--ad-surface) 0% 50%) 50% / 16px 16px; }
  .ck-qrprev img { width: 100%; height: 100%; object-fit: contain; display: block; }
  .ck-fmt { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
  .ck-fmt a { border: 1.5px solid var(--ad-line); border-radius: 12px; padding: 10px 6px; text-align: center; text-decoration: none; color: var(--ad-ink); background: var(--ad-surface); transition: border-color .15s, transform .15s; }
  .ck-fmt a:hover { border-color: var(--ck-pink); transform: translateY(-1px); }
  .ck-fmt a b { display: block; font-size: 15px; } .ck-fmt a small { color: var(--ad-muted); font-size: 11.5px; }
</style>
@endpush
@section('content')
<div class="pagetitle d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h1>Self Check-in</h1>
    <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item">Attendee Activities</li><li class="breadcrumb-item active">Self Check-in</li></ol></nav>
  </div>
  <select class="form-select" style="width:auto" onchange="location.href='{{ route('checkin.desk') }}?event=' + this.value">
    @foreach($events as $ev)<option value="{{ $ev->id }}" @selected($ev->id === $event->id)>{{ $ev->event_short }}{{ $ev->is_current ? ' (current)' : '' }}</option>@endforeach
  </select>
</div>

@php $ckRo = \App\Support\EventLock::isLocked($event) || !\App\Support\EventLock::isCurrentOrUnlocked($event); @endphp
<section class="section {{ $ckRo ? 'is-readonly' : '' }}">
  @include('partials.event-lock', ['event' => $event])
  <div class="ck-hero {{ $open ? '' : 'closed' }}" id="ck-hero">
    <span class="ck-ic"><i class="bi bi-qr-code-scan"></i></span>
    <div style="flex:1;min-width:240px">
      <h2>{{ $event->event_short }} check-in <span class="ck-pill {{ $open ? 'on' : 'off' }}" id="ck-pill"><span class="dot"></span><span>{{ $open ? 'Open' : 'Closed' }}</span></span></h2>
      <p id="ck-hero-t">{{ $open ? 'People scan the poster QR, type their registration number and are checked in — once.' : $closed }}</p>
    </div>
    @if($canSettings)
    <div class="form-check form-switch ck-big-switch m-0 d-flex align-items-center gap-2" data-lockable>
      <input class="form-check-input" type="checkbox" role="switch" id="ck-quick" @checked($s['enabled']) aria-label="Check-in on or off">
      <label class="form-check-label fw-semibold" for="ck-quick">{{ $s['enabled'] ? 'On' : 'Off' }}</label>
    </div>
    @endif
  </div>

  <div class="ck-tabs">
    <button type="button" data-tab="live"><i class="bi bi-broadcast"></i> Live arrivals</button>
    <button type="button" data-tab="poster"><i class="bi bi-printer"></i> QR &amp; posters</button>
    @if($canSettings)<button type="button" data-tab="settings"><i class="bi bi-sliders"></i> Options</button>@endif
  </div>

  {{-- ============ Live ============ --}}
  <div class="ck-pane" data-pane="live">
    <div class="row g-3 mb-3">
      <div class="col-md-6 col-xl-4"><div class="ck-kpi"><div class="d-flex justify-content-between align-items-end"><div><div class="n"><span data-k="checked">{{ $stats['checked'] }}</span> <small style="font-size:1rem;color:var(--ad-muted)">/ <span data-k="registered">{{ $stats['registered'] }}</span></small></div><div class="l">Checked in</div></div><b style="font-size:1.4rem;color:var(--ad-green)"><span data-k="percent">{{ $stats['percent'] }}</span>%</b></div><div class="ck-progress"><i id="ck-bar" style="width:{{ $stats['percent'] }}%"></i></div></div></div>
      <div class="col-6 col-md-3 col-xl-2"><div class="ck-kpi"><div class="n" data-k="waiting">{{ $stats['waiting'] }}</div><div class="l">Not arrived yet</div></div></div>
      <div class="col-6 col-md-3 col-xl-2"><div class="ck-kpi"><div class="n" data-k="today">{{ $stats['today'] }}</div><div class="l">Today</div></div></div>
      <div class="col-12 col-xl-4"><div class="ck-kpi d-flex gap-3 justify-content-around text-center">
        <div><div class="n" data-k="self">{{ $stats['self'] }}</div><div class="l"><span class="ck-m self">Own phone</span></div></div>
        <div><div class="n" data-k="kiosk">{{ $stats['kiosk'] }}</div><div class="l"><span class="ck-m kiosk">Kiosk</span></div></div>
        <div><div class="n" data-k="desk">{{ $stats['desk'] }}</div><div class="l"><span class="ck-m desk">Help desk</span></div></div>
      </div></div>
    </div>
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="ck-card">
          <div class="ck-card-h"><div><h3>Arrivals</h3><small>Updates by itself every few seconds</small></div>
            <div class="d-flex align-items-center gap-3">
              <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="ck-snd"><label class="form-check-label small" for="ck-snd">Sound</label></div>
              <div class="btn-group">
                <a class="btn btn-sm btn-success" href="{{ route('checkin.export', ['event' => $event->id]) }}"><i class="bi bi-file-earmark-excel"></i> Export to Excel</a>
                @if($days->count() > 1)
                <button type="button" class="btn btn-sm btn-success dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-label="Export one day"></button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><h6 class="dropdown-header">One day only</h6></li>
                  @foreach($days as $d)<li><a class="dropdown-item" href="{{ route('checkin.export', ['event' => $event->id, 'day' => $d]) }}">{{ \Carbon\Carbon::parse($d)->format('l j M Y') }}</a></li>@endforeach
                </ul>
                @endif
              </div>
            </div></div>
          <div class="ck-card-b ck-feed" id="ck-feed"></div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="ck-card">
          <div class="ck-card-h"><div><h3><i class="bi bi-life-preserver"></i> Help desk</h3><small>Forgot the number, no phone? Find them here.</small></div></div>
          <div class="ck-card-b">
            <input type="search" class="form-control" id="ck-q" placeholder="Name, registration number, email or phone…" autocomplete="off">
            <div id="ck-results"><div class="ck-empty" style="padding:24px 10px"><div class="big">🔎</div>Type at least 2 letters or digits.</div></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ============ QR & posters ============ --}}
  <div class="ck-pane" data-pane="poster">
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="ck-card"><div class="ck-card-b pt-3 text-center">
          <div class="ck-qr mx-auto mb-3">{!! $qrSvg !!}</div>
          <b style="color:var(--ad-ink)">{{ $event->event_short }} check-in QR</b>
          <p class="small text-muted mb-3">The same QR for everyone. Print it and put 5–10 copies at the entrance, registration desks and doors.</p>
          <div class="ck-link mb-2 text-start"><i class="bi bi-link-45deg"></i><code id="ck-url">{{ $url }}</code>
            <button class="btn btn-sm btn-outline-secondary" data-copy="ck-url"><i class="bi bi-copy"></i></button>
            <a class="btn btn-sm btn-outline-secondary" href="{{ $url }}" target="_blank" rel="noopener" title="Open"><i class="bi bi-box-arrow-up-right"></i></a></div>
          <div class="ck-link text-start"><i class="bi bi-tablet"></i><code id="ck-kurl">{{ $kioskUrl }}</code>
            <button class="btn btn-sm btn-outline-secondary" data-copy="ck-kurl"><i class="bi bi-copy"></i></button>
            <a class="btn btn-sm btn-outline-secondary" href="{{ $kioskUrl }}" target="_blank" rel="noopener" title="Open kiosk"><i class="bi bi-box-arrow-up-right"></i></a></div>
          <p class="small text-muted mt-2 mb-0 text-start"><i class="bi bi-info-circle"></i> <b>Kiosk link</b>: open it on a tablet at the entrance for people without a smartphone — after each welcome it clears for the next person.</p>
          @if($canSettings)
            <button class="btn btn-sm btn-outline-danger mt-3" id="ck-token" data-lockable><i class="bi bi-arrow-repeat"></i> Make a new QR (old posters stop working)</button>
          @endif
        </div></div>
      </div>
      <div class="col-lg-7">
        <div class="ck-card"><div class="ck-card-h"><div><h3>Print posters</h3><small>Opens a print-ready page — print from your browser</small></div>
          <div class="d-flex align-items-center gap-2"><label class="small text-muted" for="ck-copies">Copies</label>
            <select class="form-select form-select-sm" id="ck-copies" style="width:auto">@foreach([1, 2, 3, 5, 8, 10] as $n)<option @selected($n === 5)>{{ $n }}</option>@endforeach</select></div></div>
          <div class="ck-card-b">
            <div class="row g-3">
              @foreach(['a4' => ['bi-file-earmark', 'A4 poster', 'Walls, doors, entrance'], 'a5' => ['bi-file-earmark-richtext', 'A5 poster', 'Desks and stands'], 'tent' => ['bi-easel', 'Table tent', 'A4 folded in half, stands on tables']] as $k => [$ic, $t, $d])
                <div class="col-sm-4"><div class="ck-print"><i class="bi {{ $ic }} ic"></i><b>{{ $t }}</b><small>{{ $d }}</small>
                  <a class="btn btn-sm btn-primary mt-auto" data-poster="{{ $k }}" href="{{ route('checkin.poster', ['event' => $event->id, 'size' => $k, 'copies' => 5]) }}" target="_blank"><i class="bi bi-printer"></i> Open &amp; print</a></div></div>
              @endforeach
            </div>
            <div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-lightbulb"></i> Each poster shows the event name, the QR and three steps: <b>Scan → type your registration number → done</b>. Test one with your phone before printing them all.</div>
          </div>
        </div>
      </div>
      <div class="col-12">
        <div class="ck-card"><div class="ck-card-h"><div><h3><i class="bi bi-qr-code"></i> QR code only — for your own design</h3><small>Download just the check-in QR and place it on banners, standees, screens or slides your designer makes</small></div></div>
          <div class="ck-card-b">
            <div class="row g-4 align-items-start">
              <div class="col-md-3 text-center"><div class="ck-qrprev mx-auto"><img id="qd-prev" alt="QR preview"></div><small class="text-muted d-block mt-2" id="qd-info"></small></div>
              <div class="col-md-5">
                <div class="row g-2">
                  <div class="col-6"><label class="ck-label" for="qd-size">Size (PNG / JPG)</label>
                    <select class="form-select" id="qd-size"><option value="600">600 px — screens</option><option value="1200" selected>1200 px — A5 / A4</option><option value="2400">2400 px — posters</option><option value="4000">4000 px — banners</option></select></div>
                  <div class="col-6"><label class="ck-label" for="qd-margin">White border</label>
                    <select class="form-select" id="qd-margin"><option value="0">None</option><option value="1">Thin</option><option value="2" selected>Normal</option><option value="4">Wide (recommended by the QR standard)</option></select></div>
                  <div class="col-6"><label class="ck-label" for="qd-fg">QR colour</label><input type="color" class="form-control form-control-color w-100" id="qd-fg" value="#000000"></div>
                  <div class="col-6"><label class="ck-label" for="qd-bg">Background</label><input type="color" class="form-control form-control-color w-100" id="qd-bg" value="#ffffff"></div>
                  <div class="col-12 d-flex flex-wrap gap-3 mt-2">
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="qd-tr"><label class="form-check-label small" for="qd-tr">Transparent background (PNG &amp; SVG)</label></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="qd-kiosk"><label class="form-check-label small" for="qd-kiosk">Kiosk version</label></div>
                  </div>
                </div>
                <small class="text-muted d-block mt-2"><i class="bi bi-info-circle"></i> Keep strong contrast (dark QR on a light background) so every phone can read it. The code has extra error correction, so a small logo in the middle is fine.</small>
              </div>
              <div class="col-md-4">
                <span class="ck-label">Download</span>
                <div class="ck-fmt">
                  <a data-fmt="png" href="#"><b>PNG</b><small>web, slides</small></a>
                  <a data-fmt="jpg" href="#"><b>JPG</b><small>photos, print</small></a>
                  <a data-fmt="svg" href="#"><b>SVG</b><small>vector</small></a>
                  <a data-fmt="eps" href="#"><b>EPS</b><small>print, CMYK</small></a>
                </div>
                <small class="text-muted d-block mt-2">SVG and EPS are vector — sharp at any size (Illustrator, CorelDRAW, InDesign). EPS uses CMYK colours for the printer.</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ============ Options ============ --}}
  @if($canSettings)
  <div class="ck-pane" data-pane="settings">
    <form id="ck-form" class="ck-card" novalidate><fieldset class="ck-card-b pt-3" @disabled(\App\Support\EventLock::isLocked($event))>
      <div class="row g-4">
        <div class="col-xl-6">
          <div class="ck-sec"><i class="bi bi-power"></i> Check-in</div>
          <div class="form-check form-switch ck-big-switch mb-4">
            <input class="form-check-input" type="checkbox" role="switch" id="o-enabled" name="enabled" @checked($s['enabled'])>
            <label class="form-check-label fw-semibold ms-2" for="o-enabled">Check-in for {{ $event->event_short }} is on</label>
          </div>

          <div class="ck-sec"><i class="bi bi-clock"></i> When it works</div>
          <div class="row g-2 mb-2">
            <div class="col-sm-6"><label class="ck-opt"><input class="form-check-input mt-1" type="radio" name="hours" value="always" @checked($s['hours'] === 'always')><span><b>Always open</b><small>While check-in is switched on</small></span></label></div>
            <div class="col-sm-6"><label class="ck-opt"><input class="form-check-input mt-1" type="radio" name="hours" value="window" @checked($s['hours'] === 'window')><span><b>Between set times</b><small>Opens and closes by itself</small></span></label></div>
          </div>
          <div class="row g-2 mb-4" id="o-window">
            <div class="col-sm-6"><label class="ck-label" for="o-opens">Opens</label><input type="datetime-local" class="form-control" id="o-opens" name="opens" value="{{ $dt($s['opens']) }}"></div>
            <div class="col-sm-6"><label class="ck-label" for="o-closes">Closes</label><input type="datetime-local" class="form-control" id="o-closes" name="closes" value="{{ $dt($s['closes']) }}"></div>
            <div class="col-12"><small class="text-muted">Times in the event's time zone ({{ $tz }}).</small></div>
          </div>

          <div class="ck-sec"><i class="bi bi-input-cursor-text"></i> What people type</div>
          <p class="small text-muted mb-2">The <b>registration number</b> is always asked. You can ask one more detail to stop people checking in for others.</p>
          <select class="form-select mb-4" name="extra" id="o-extra">
            @foreach(\App\Support\CheckIn::EXTRA as $k => $l)<option value="{{ $k }}" @selected($s['extra'] === $k)>{{ $k === 'none' ? $l . ' (simplest)' : '+ ' . $l }}</option>@endforeach
          </select>

          <div class="ck-sec"><i class="bi bi-shield-check"></i> Safety</div>
          <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="o-approved" name="approved_only" @checked($s['approved_only'])><label class="form-check-label" for="o-approved">Only <b>approved</b> registrations can check in</label></div>
          <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="o-forgot" name="forgot" @checked($s['forgot'])><label class="form-check-label" for="o-forgot">Show <b>"Forgot your number?"</b> — emails the number to the registered address</label></div>
          <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="o-location" name="location" @checked($s['location'])><label class="form-check-label" for="o-location">Only at the venue — <b>check the phone's location</b></label></div>
          <div class="row g-2 mb-2" id="o-loc">
            <div class="col-4"><label class="ck-label" for="o-lat">Latitude</label><input class="form-control" id="o-lat" name="lat" value="{{ $s['lat'] }}" placeholder="39.4919"></div>
            <div class="col-4"><label class="ck-label" for="o-lng">Longitude</label><input class="form-control" id="o-lng" name="lng" value="{{ $s['lng'] }}" placeholder="-0.4205"></div>
            <div class="col-4"><label class="ck-label" for="o-radius">Within (m)</label><input class="form-control" type="number" id="o-radius" name="radius" min="50" max="20000" step="50" value="{{ $s['radius'] }}"></div>
            <div class="col-12"><button type="button" class="btn btn-sm btn-outline-secondary" id="o-here"><i class="bi bi-crosshair"></i> Use my current location (when you are at the venue)</button>
              <small class="text-muted d-block mt-1">Or copy the numbers from Google Maps (right-click the venue). People who block location access cannot check in themselves — the help desk still can.</small></div>
          </div>
        </div>
        <div class="col-xl-6">
          <div class="ck-sec"><i class="bi bi-emoji-smile"></i> Welcome screen</div>
          <label class="ck-label" for="o-welcome">Welcome text</label>
          <input class="form-control mb-1" id="o-welcome" name="welcome" maxlength="120" value="{{ $s['welcome'] }}">
          <small class="text-muted d-block mb-3">Placeholders: <code>{first_name}</code> <code>{last_name}</code> <code>{name}</code> <code>{event_short}</code> <code>{event_name}</code></small>
          <label class="ck-label" for="o-message">Message under it</label>
          <input class="form-control mb-3" id="o-message" name="message" maxlength="240" value="{{ $s['message'] }}">
          <label class="ck-label" for="o-seconds">Show it for <span id="o-seconds-v">{{ $s['seconds'] }}</span> seconds</label>
          <input type="range" class="form-range mb-3" id="o-seconds" name="seconds" min="3" max="30" value="{{ $s['seconds'] }}">
          <span class="ck-label">Show on the welcome screen</span>
          <div class="d-flex flex-wrap gap-3 mb-2">
            @foreach(['show_photo' => 'Photo', 'show_profession' => 'Profession', 'show_country' => 'Country', 'show_organization' => 'Institution', 'sound' => 'Sound'] as $k => $l)
              <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="o-{{ $k }}" name="{{ $k }}" @checked($s[$k])><label class="form-check-label" for="o-{{ $k }}">{{ $l }}</label></div>
            @endforeach
          </div>
          <small class="text-muted d-block mb-4">The name is always shown.</small>

          <div class="ck-sec"><i class="bi bi-award"></i> Certificates</div>
          <div class="row g-2">
            <div class="col-sm-6"><label class="ck-opt"><input class="form-check-input mt-1" type="radio" name="certificate" value="event" @checked($s['certificate'] === 'event')><span><b>As set in Certificates</b><small>Sent on check-in when Certificates says "automatic"</small></span></label></div>
            <div class="col-sm-6"><label class="ck-opt"><input class="form-check-input mt-1" type="radio" name="certificate" value="never" @checked($s['certificate'] === 'never')><span><b>Not on self check-in</b><small>Send them yourself later</small></span></label></div>
          </div>
        </div>
      </div>
      <div class="d-flex gap-2 mt-4 flex-wrap"><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Save options</button>
        <a class="btn btn-outline-secondary" href="{{ $url }}" target="_blank" rel="noopener"><i class="bi bi-phone"></i> Try the check-in page</a></div>
    </fieldset></form>
  </div>
  @endif
</section>
@endsection
@push('scripts')
@php
  $boot = ['recent' => $recent, 'tab' => $tab, 'event' => $event->id, 'locked' => \App\Support\EventLock::isLocked($event) || !\App\Support\EventLock::isCurrentOrUnlocked($event),
    'r' => ['feed' => route('checkin.feed'), 'search' => route('checkin.search'), 'in' => route('checkin.manual'), 'undo' => route('checkin.undo'),
            'settings' => route('checkin.settings'), 'token' => route('checkin.token'), 'desk' => route('checkin.desk'), 'poster' => route('checkin.poster'),
            'qr' => str_replace('png', 'FORMAT', route('checkin.qr', 'png'))]];
@endphp
<script>
(function () {
  'use strict';
  var B = @json($boot);
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  function $(id) { return document.getElementById(id); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function toast(m, i) { Swal.fire({ toast: true, position: 'top-end', icon: i || 'success', title: m, showConfirmButton: false, timer: 3000 }); }
  function post(url, body) {
    return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({ event: B.event }, body || {})) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) { if (!res.ok) { var e = res.d.errors || {}, k = Object.keys(e)[0]; Swal.fire('Not done', res.d.message || (k ? e[k][0] : 'Please try again.'), 'warning'); return null; } return res.d; });
  }

  /* tabs */
  function tab(n) {
    document.querySelectorAll('.ck-tabs [data-tab]').forEach(function (b) { b.classList.toggle('on', b.dataset.tab === n); });
    document.querySelectorAll('.ck-pane').forEach(function (p) { p.classList.toggle('on', p.dataset.pane === n); });
    history.replaceState(null, '', B.r.desk + '?event=' + B.event + (n === 'live' ? '' : '&tab=' + n));
  }
  document.querySelectorAll('.ck-tabs [data-tab]').forEach(function (b) { b.addEventListener('click', function () { tab(b.dataset.tab); }); });

  /* live feed */
  var lastId = 0, feed = $('ck-feed');
  function rowHtml(c, fresh) {
    var pic = c.photo ? '<img class="ck-av" src="' + esc(c.photo) + '" alt="">' : '<span class="ck-av">' + esc(c.initials) + '</span>';
    var label = { self: 'Own phone', kiosk: 'Kiosk', desk: 'Help desk' }[c.method] || c.method;
    var warn = c.shared ? '<div class="ck-warn"><i class="bi bi-exclamation-triangle-fill"></i> Same phone checked in ' + c.shared + ' people</div>' : '';
    return '<div class="ck-row' + (fresh ? ' fresh' : '') + '" data-rid="' + c.rid + '">' + pic +
      '<div class="ck-who"><b>' + esc(c.name) + '</b><span>' + esc([c.number, c.profession, c.country].filter(Boolean).join(' · ')) + '</span>' + warn + '</div>' +
      '<div class="ck-time"><b>' + esc(c.time) + '</b><span class="ck-m ' + esc(c.method) + '">' + esc(label) + '</span>' + (c.by ? '<br><small>by ' + esc(c.by) + '</small>' : '') + '</div>' +
      (B.locked ? '' : '<button class="ck-undo" data-undo="' + c.rid + '" title="Undo this check-in" aria-label="Undo check-in of ' + esc(c.name) + '"><i class="bi bi-arrow-counterclockwise"></i></button>') + '</div>';
  }
  function empty() { return '<div class="ck-empty"><div class="big">🚪</div><b>No arrivals yet</b>They appear here the moment someone checks in.</div>'; }
  function renderAll(rows) { feed.innerHTML = rows.length ? rows.map(function (c) { return rowHtml(c, false); }).join('') : empty(); if (rows.length) lastId = rows[0].id; }
  renderAll(B.recent);
  function chime() {
    if (!$('ck-snd').checked) return;
    try { var a = new (window.AudioContext || window.webkitAudioContext)(), o = a.createOscillator(), g = a.createGain(); o.frequency.value = 880; o.connect(g); g.connect(a.destination); g.gain.setValueAtTime(.2, a.currentTime); g.gain.exponentialRampToValueAtTime(.0001, a.currentTime + .3); o.start(); o.stop(a.currentTime + .3); } catch (e) {}
  }
  function setStats(s) {
    document.querySelectorAll('[data-k]').forEach(function (el) { if (s[el.dataset.k] != null) el.textContent = s[el.dataset.k]; });
    $('ck-bar').style.width = s.percent + '%';
  }
  function poll() {
    fetch(B.r.feed + '?event=' + B.event + '&after=' + lastId, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
      setStats(d.stats);
      if (d.rows.length) {
        if (!lastId) feed.innerHTML = '';
        var html = d.rows.map(function (c) { return rowHtml(c, true); }).join('');
        feed.insertAdjacentHTML('afterbegin', html);
        lastId = d.rows[0].id; chime();
      }
    }).catch(function () {});
  }
  setInterval(function () { if (!document.hidden) poll(); }, 5000);
  feed.addEventListener('click', function (e) {
    var b = e.target.closest('[data-undo]'); if (!b) return;
    Swal.fire({ title: 'Undo this check-in?', text: 'The person will show as not arrived and can check in again.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Undo check-in', confirmButtonColor: '#d33' })
      .then(function (r) { if (!r.isConfirmed) return; post(B.r.undo, { registration_id: +b.dataset.undo }).then(function (d) { if (!d) return; toast(d.message, 'info'); var row = b.closest('.ck-row'); row.remove(); if (!feed.children.length) feed.innerHTML = empty(); poll(); }); });
  });

  /* help desk */
  var seq = 0, qt;
  $('ck-q').addEventListener('input', function () {
    clearTimeout(qt); var q = this.value.trim(), my = ++seq;
    if (q.length < 2) { $('ck-results').innerHTML = '<div class="ck-empty" style="padding:24px 10px"><div class="big">🔎</div>Type at least 2 letters or digits.</div>'; return; }
    qt = setTimeout(function () {
      fetch(B.r.search + '?event=' + B.event + '&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
        if (my !== seq) return;
        $('ck-results').innerHTML = d.data.length ? d.data.map(function (r) {
          return '<div class="ck-res"><div style="flex:1;min-width:0"><b>' + esc(r.name) + '</b><small>No. ' + esc(r.number) + ' · ' + esc([r.profession, r.country].filter(Boolean).join(' · ')) + '</small><small>' + esc(r.email || '') + '</small></div>' +
            (r.in ? '<span class="ck-m self" title="Checked in ' + esc(r.in) + '"><i class="bi bi-check2"></i> ' + esc(r.in) + '</span>'
                  : (B.locked ? '<span class="small text-muted">Did not check in</span>' : '<button class="btn btn-sm btn-success" data-in="' + r.id + '"><i class="bi bi-box-arrow-in-right"></i> Check in</button>')) + '</div>';
        }).join('') : '<div class="ck-empty" style="padding:24px 10px">Nobody registered for this event matches.</div>';
      });
    }, 250);
  });
  $('ck-results').addEventListener('click', function (e) {
    var b = e.target.closest('[data-in]'); if (!b) return;
    b.disabled = true;
    post(B.r.in, { registration_id: +b.dataset.in }).then(function (d) { if (!d) { b.disabled = false; return; } toast(d.message); b.outerHTML = '<span class="ck-m self"><i class="bi bi-check2"></i> Now</span>'; poll(); });
  });

  /* copy links / posters */
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () { var t = $(b.dataset.copy).textContent; (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { toast('Link copied'); }, function () { prompt('Copy this link:', t); }); });
  });
  var copies = $('ck-copies');
  if (copies) copies.addEventListener('change', function () {
    document.querySelectorAll('[data-poster]').forEach(function (a) { a.href = B.r.poster + '?event=' + B.event + '&size=' + a.dataset.poster + '&copies=' + copies.value; });
  });
  var tk = $('ck-token');
  if (tk) tk.addEventListener('click', function () {
    Swal.fire({ title: 'Make a new QR?', html: 'Use this only if a poster photo was shared outside the venue.<br><b>All printed posters stop working</b> — you must print new ones.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Make new QR', confirmButtonColor: '#d33' })
      .then(function (r) { if (r.isConfirmed) post(B.r.token).then(function (d) { if (d) Swal.fire('Done', d.message, 'success').then(function () { location.href = B.r.desk + '?event=' + B.event + '&tab=poster'; }); }); });
  });

  /* options */
  var f = $('ck-form');
  function fmt(v) { return v ? v.replace('T', ' ').slice(0, 16) : ''; }
  function data() {
    var el = f.elements, o = { hours: f.querySelector('[name=hours]:checked').value, certificate: f.querySelector('[name=certificate]:checked').value,
      opens: fmt(el.opens.value), closes: fmt(el.closes.value), extra: el.extra.value, lat: el.lat.value.trim(), lng: el.lng.value.trim(), radius: +el.radius.value,
      welcome: el.welcome.value, message: el.message.value, seconds: +el.seconds.value };
    ['enabled', 'location', 'approved_only', 'forgot', 'show_photo', 'show_profession', 'show_country', 'show_organization', 'sound'].forEach(function (k) { o[k] = el[k].checked; });
    if (o.hours !== 'window') { o.opens = ''; o.closes = ''; }
    return o;
  }
  function sync() {
    if (!f) return;
    $('o-window').style.display = f.querySelector('[name=hours]:checked').value === 'window' ? '' : 'none';
    $('o-loc').style.display = f.elements.location.checked ? '' : 'none';
    $('o-seconds-v').textContent = f.elements.seconds.value;
  }
  function applyState(d, enabled) {
    var hero = $('ck-hero'), pill = $('ck-pill');
    hero.classList.toggle('closed', !d.open); pill.className = 'ck-pill ' + (d.open ? 'on' : 'off'); pill.lastElementChild.textContent = d.open ? 'Open' : 'Closed';
    $('ck-hero-t').textContent = d.open ? 'People scan the poster QR, type their registration number and are checked in — once.' : d.message.replace(/^Saved\. /, '');
    var q = $('ck-quick'); if (q) { q.checked = enabled; q.nextElementSibling.textContent = enabled ? 'On' : 'Off'; }
    if (f) f.elements.enabled.checked = enabled;
  }
  if (f) {
    f.addEventListener('input', sync); f.addEventListener('change', sync); sync();
    f.addEventListener('submit', function (e) { e.preventDefault(); var o = data(); post(B.r.settings, o).then(function (d) { if (d) { toast(d.message); applyState(d, o.enabled); } }); });
    $('o-here').addEventListener('click', function () {
      if (!navigator.geolocation) return Swal.fire('Not available', 'This browser cannot share its location.', 'info');
      navigator.geolocation.getCurrentPosition(function (p) { f.elements.lat.value = p.coords.latitude.toFixed(6); f.elements.lng.value = p.coords.longitude.toFixed(6); toast('Location filled in — remember to save.'); },
        function () { Swal.fire('No location', 'Allow location access in the browser, or type the numbers.', 'info'); }, { enableHighAccuracy: true, timeout: 10000 });
    });
  }
  var quick = $('ck-quick');
  if (quick) quick.addEventListener('change', function () {
    var on = quick.checked, o = f ? data() : {}; o.enabled = on;
    post(B.r.settings, o).then(function (d) { if (!d) { quick.checked = !on; return; } toast(on ? 'Check-in switched on' : 'Check-in switched off', on ? 'success' : 'info'); applyState(d, on); });
  });

  /* QR code only */
  var qd = { prev: $('qd-prev') };
  function qdParams(fmt) {
    var p = { event: B.event, size: $('qd-size').value, margin: $('qd-margin').value, fg: $('qd-fg').value, bg: $('qd-bg').value };
    if ($('qd-tr').checked && (fmt === 'png' || fmt === 'svg')) p.transparent = 1;
    if ($('qd-kiosk').checked) p.kiosk = 1;
    return p;
  }
  function qs(o) { return Object.keys(o).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(o[k]); }).join('&'); }
  function qdRefresh() {
    if (!qd.prev) return;
    var p = qdParams('png'); p.size = 600;
    qd.prev.src = B.r.qr.replace('FORMAT', 'png') + '?' + qs(p);
    var fg = $('qd-fg').value, bg = $('qd-tr').checked ? '#ffffff' : $('qd-bg').value;
    function lum(h) { var c = [1, 3, 5].map(function (i) { var v = parseInt(h.substr(i, 2), 16) / 255; return v <= .03928 ? v / 12.92 : Math.pow((v + .055) / 1.055, 2.4); }); return .2126 * c[0] + .7152 * c[1] + .0722 * c[2]; }
    var a = lum(fg), b = lum(bg), ratio = (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
    $('qd-info').innerHTML = ratio < 4 ? '<span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Low contrast — phones may not read it</span>' : (a > b ? '<span class="text-warning"><i class="bi bi-exclamation-triangle-fill"></i> Light QR on dark: some phones fail</span>' : '<i class="bi bi-check-circle text-success"></i> Good contrast');
  }
  ['qd-size', 'qd-margin', 'qd-fg', 'qd-bg', 'qd-tr', 'qd-kiosk'].forEach(function (id) { var el = $(id); if (el) el.addEventListener('change', qdRefresh); if (el) el.addEventListener('input', qdRefresh); });
  document.querySelectorAll('[data-fmt]').forEach(function (a) {
    a.addEventListener('click', function (e) { e.preventDefault(); var p = qdParams(a.dataset.fmt); p.download = 1; location.href = B.r.qr.replace('FORMAT', a.dataset.fmt) + '?' + qs(p); });
  });
  qdRefresh();

  tab(B.tab);
})();
</script>
@endpush
