{{-- A document from Website › Documents, at its own address (e.g. /Itenary-2026). $d = documents row, $file = PDF URL. --}}
@php
  $size = $d->size >= 1048576 ? number_format($d->size / 1048576, 1) . ' MB' : max(1, round($d->size / 1024)) . ' KB';
  $updated = $d->file_updated_at ? \Carbon\Carbon::parse($d->file_updated_at)->format('j F Y') : null;
  $dl = url('/' . $d->slug . '/file') . '?download=1';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>{{ $d->title }} · BICS</title>
  <meta name="description" content="{{ $d->description ?: $d->title }}">
  <meta property="og:title" content="{{ $d->title }}">
  <meta property="og:description" content="{{ $d->description ?: 'Beacon International Cancer Summit' }}">
  <meta property="og:image" content="{{ asset('assets/frontend/images/logo.png') }}">
  <meta name="theme-color" content="#0a2350">
  <link rel="icon" href="{{ asset('assets/frontend/images/logo.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root { --navy: #0a2350; --blue: #0e4ca1; --magenta: #b22e6b; --ink: #0f1a2e; --muted: #6b7489; --line: #e3e8f2; --soft: #f3f6fb; }
    * { box-sizing: border-box; }
    html, body { margin: 0; height: 100%; }
    body { font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--soft); display: flex; flex-direction: column; min-height: 100vh; min-height: 100dvh; -webkit-font-smoothing: antialiased; }
    .dc-top { color: #fff; background: radial-gradient(520px 240px at 100% 0%, rgba(178,46,107,.55), transparent 70%), linear-gradient(120deg, #071a3d, var(--blue)); padding: 14px 20px; }
    .dc-in { max-width: 1200px; margin: 0 auto; display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
    .dc-logo { background: #fff; border-radius: 12px; padding: 6px 10px; display: inline-flex; flex: none; } .dc-logo img { height: 30px; display: block; }
    .dc-t { flex: 1; min-width: 200px; }
    .dc-t h1 { margin: 0; font-size: clamp(1.1rem, 2.4vw, 1.45rem); font-weight: 800; letter-spacing: -.01em; }
    .dc-t p { margin: 2px 0 0; font-size: 13.5px; color: rgba(255,255,255,.8); }
    .dc-acts { display: flex; gap: 8px; flex-wrap: wrap; }
    .dc-btn { display: inline-flex; align-items: center; gap: 7px; border-radius: 11px; padding: 9px 14px; font-weight: 700; font-size: 14px; text-decoration: none; border: 1px solid rgba(255,255,255,.35); color: #fff; background: rgba(255,255,255,.12); white-space: nowrap; font-family: inherit; cursor: pointer; }
    .dc-btn:hover { background: rgba(255,255,255,.22); }
    .dc-btn.solid { background: #fff; color: var(--blue); border-color: #fff; }
    .dc-btn.solid:hover { background: #eef3fb; }
    .dc-view { flex: 1; display: flex; padding: 16px; }
    .dc-frame { flex: 1; max-width: 1200px; margin: 0 auto; width: 100%; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 50px -30px rgba(10,30,70,.5); border: 1px solid var(--line); display: flex; }
    .dc-frame iframe { flex: 1; width: 100%; min-height: 72vh; border: 0; display: block; }
    /* phones: most mobile browsers cannot show a PDF inside a page, so give a clear card instead */
    .dc-mobile { display: none; flex: 1; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 32px 20px; gap: 6px; }
    .dc-mobile .ic { width: 78px; height: 78px; border-radius: 22px; background: #fdecee; color: #c0392b; display: inline-flex; align-items: center; justify-content: center; font-size: 2.3rem; margin-bottom: 8px; }
    .dc-mobile h2 { font-size: 1.2rem; margin: 0; font-weight: 800; } .dc-mobile p { color: var(--muted); margin: 0 0 14px; font-size: 14px; }
    .dc-mobile .dc-btn { color: #fff; background: var(--blue); border-color: var(--blue); justify-content: center; width: 100%; max-width: 320px; padding: 13px; font-size: 15px; }
    .dc-mobile .dc-btn.ghost { color: var(--blue); background: #fff; border-color: var(--line); }
    .dc-foot { text-align: center; font-size: 12.5px; color: var(--muted); padding: 0 16px 16px; }
    .dc-foot a { color: inherit; }
    @media (max-width: 767.98px) {
      .dc-top { padding: 12px 16px; } .dc-acts .hide-sm { display: none; }
      .dc-view { padding: 12px; }
      .dc-frame iframe { display: none; } .dc-mobile { display: flex; }
    }
  </style>
</head>
<body>
  <header class="dc-top">
    <div class="dc-in">
      <a class="dc-logo" href="{{ url('/') }}" title="Beacon International Cancer Summit"><img src="{{ asset('assets/frontend/images/logo.png') }}" alt="BICS"></a>
      <div class="dc-t">
        <h1>{{ $d->title }}</h1>
        <p>@if($d->description){{ $d->description }} · @endif PDF · {{ $size }}@if($updated) · updated {{ $updated }}@endif</p>
      </div>
      <div class="dc-acts">
        <a class="dc-btn hide-sm" href="{{ $file }}" target="_blank" rel="noopener"><i class="bi bi-arrows-fullscreen"></i> Full screen</a>
        <button type="button" class="dc-btn hide-sm" id="dc-share"><i class="bi bi-share"></i> Share</button>
        @if($d->allow_download)<a class="dc-btn solid" href="{{ $dl }}"><i class="bi bi-download"></i> Download</a>@endif
      </div>
    </div>
  </header>

  <main class="dc-view">
    <div class="dc-frame">
      <iframe src="{{ $file }}#view=FitH" title="{{ $d->title }}"></iframe>
      <div class="dc-mobile">
        <span class="ic"><i class="bi bi-file-earmark-pdf-fill"></i></span>
        <h2>{{ $d->title }}</h2>
        <p>PDF · {{ $size }}@if($updated) · updated {{ $updated }}@endif</p>
        <a class="dc-btn" href="{{ $file }}" target="_blank" rel="noopener"><i class="bi bi-eye"></i> Open the PDF</a>
        @if($d->allow_download)<a class="dc-btn ghost" href="{{ $dl }}"><i class="bi bi-download"></i> Download</a>@endif
        <button type="button" class="dc-btn ghost" id="dc-share-m"><i class="bi bi-share"></i> Share link</button>
      </div>
    </div>
  </main>
  <div class="dc-foot"><a href="{{ url('/') }}">Beacon International Cancer Summit</a></div>

<script>
  (function () {
    var url = @json(url('/' . $d->slug)), title = @json($d->title);
    function share() {
      if (navigator.share) { navigator.share({ title: title, url: url }).catch(function () {}); return; }
      (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () { alert('Link copied: ' + url); }, function () { prompt('Copy this link:', url); });
    }
    ['dc-share', 'dc-share-m'].forEach(function (id) { var b = document.getElementById(id); if (b) b.addEventListener('click', share); });
  })();
</script>
</body>
</html>
