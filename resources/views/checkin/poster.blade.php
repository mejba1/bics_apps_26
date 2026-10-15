{{-- Check-in poster for the venue (Admin › Self Check-in › Poster). Printed from the browser; $copies pages. --}}
@php
  $start = $event->event_start ? \Carbon\Carbon::parse($event->event_start, $tz) : null;
  $short = preg_replace('#^https?://(www\.)?#', '', $url);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Check-in poster · {{ $event->event_short }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&display=swap">
  <style>
    @page { size: {{ $size === 'a5' ? 'A5' : 'A4' }} {{ $size === 'tent' ? 'landscape' : 'portrait' }}; margin: 0; }
    * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body { margin: 0; font-family: "Plus Jakarta Sans", system-ui, sans-serif; color: #0f1a2e; background: #e9edf4; }
    .bar { position: sticky; top: 0; display: flex; gap: 10px; align-items: center; justify-content: center; padding: 12px; background: #0f1a2e; color: #fff; font-size: 14px; z-index: 2; flex-wrap: wrap; }
    .bar button, .bar a { border: 0; border-radius: 10px; padding: 9px 16px; font-weight: 700; font-family: inherit; cursor: pointer; text-decoration: none; background: #fff; color: #0f1a2e; font-size: 14px; }
    .bar button.primary { background: #b22e6b; color: #fff; }
    .bar a.on { background: #3b4a66; color: #fff; }
    .sheet { width: 210mm; height: 297mm; margin: 14px auto; background: #fff; box-shadow: 0 10px 30px rgba(0,0,0,.15); overflow: hidden; position: relative; page-break-after: always; break-after: page;
      display: flex; flex-direction: column; align-items: center; text-align: center; }
    .a5 .sheet { width: 148mm; height: 210mm; }
    .tent .sheet { width: 297mm; height: 210mm; }
    .top { width: 100%; padding: 16mm 14mm 12mm; color: #fff; background: radial-gradient(120mm 80mm at 100% 0%, rgba(178,46,107,.7), transparent 60%), linear-gradient(150deg, #071a3d, #0e4ca1); }
    .logo { background: #fff; border-radius: 4mm; padding: 2.5mm 4mm; display: inline-flex; } .logo img { height: 12mm; display: block; }
    .ev { font-size: 7mm; font-weight: 800; margin: 6mm 0 2mm; line-height: 1.2; }
    .when { font-size: 4.2mm; opacity: .85; }
    .title { font-size: 15mm; font-weight: 800; letter-spacing: -.5mm; margin: 12mm 0 2mm; line-height: 1; color: #0f1a2e; }
    .title span { color: #b22e6b; }
    .lead { font-size: 5mm; color: #4a5368; margin: 0 0 8mm; }
    .qr { width: 110mm; height: 110mm; padding: 5mm; border: 1.2mm solid #0f1a2e; border-radius: 6mm; background: #fff; }
    .qr svg { width: 100%; height: 100%; display: block; }
    .steps { display: flex; gap: 6mm; margin: 10mm 0 0; padding: 0 14mm; }
    .step { flex: 1; text-align: center; font-size: 4mm; color: #4a5368; line-height: 1.35; }
    .step b { display: inline-flex; width: 9mm; height: 9mm; border-radius: 50%; background: #0e4ca1; color: #fff; align-items: center; justify-content: center; font-size: 4.6mm; margin-bottom: 2mm; }
    .step strong { display: block; color: #0f1a2e; font-size: 4.4mm; }
    .url { margin-top: auto; padding: 0 0 10mm; font-size: 3.4mm; color: #8a93a6; }
    /* A5 & table tent: smaller */
    .a5 .top { padding: 9mm 9mm 7mm; } .a5 .logo img { height: 8mm; } .a5 .ev { font-size: 5mm; margin-top: 4mm; } .a5 .when { font-size: 3.2mm; }
    .a5 .title { font-size: 10mm; margin-top: 7mm; } .a5 .lead { font-size: 3.6mm; margin-bottom: 5mm; } .a5 .qr { width: 78mm; height: 78mm; }
    .a5 .steps { margin-top: 6mm; padding: 0 8mm; gap: 3mm; } .a5 .step { font-size: 2.9mm; } .a5 .step strong { font-size: 3.2mm; } .a5 .step b { width: 6.5mm; height: 6.5mm; font-size: 3.4mm; } .a5 .url { font-size: 2.6mm; padding-bottom: 6mm; }
    .tent .sheet { flex-direction: row; text-align: left; }
    .tent .half { width: 50%; height: 100%; display: flex; flex-direction: column; align-items: center; text-align: center; border-right: 0.3mm dashed #c9d2e3; }
    .tent .half:first-child { transform: rotate(180deg); }
    .tent .top { padding: 8mm; } .tent .logo img { height: 8mm; } .tent .ev { font-size: 4.6mm; margin-top: 3mm; } .tent .when { font-size: 3mm; }
    .tent .title { font-size: 9mm; margin-top: 6mm; } .tent .lead { font-size: 3.4mm; margin-bottom: 4mm; } .tent .qr { width: 70mm; height: 70mm; padding: 3mm; }
    .tent .url { font-size: 2.6mm; padding-bottom: 5mm; }
    @media print { body { background: #fff; } .bar { display: none; } .sheet { margin: 0; box-shadow: none; } }
  </style>
</head>
<body class="{{ $size }}">
  <div class="bar">
    <span>{{ $copies }} {{ $copies === 1 ? 'page' : 'pages' }} ·</span>
    @foreach(['a4' => 'A4 poster', 'a5' => 'A5 poster', 'tent' => 'Table tent (A4, folds)'] as $k => $l)
      <a class="{{ $size === $k ? 'on' : '' }}" href="?{{ http_build_query(array_merge(request()->query(), ['size' => $k])) }}">{{ $l }}</a>
    @endforeach
    <button class="primary" onclick="window.print()">🖨 Print</button>
  </div>
  @for($i = 0; $i < $copies; $i++)
    <div class="sheet">
      @foreach($size === 'tent' ? [0, 1] : [0] as $half)
      <div class="{{ $size === 'tent' ? 'half' : '' }}" style="{{ $size === 'tent' ? '' : 'display:contents' }}">
        <div class="top">
          <span class="logo"><img src="{{ asset('assets/frontend/images/logo.png') }}" alt="BICS"></span>
          <div class="ev">{{ $event->event_name }}</div>
          <div class="when">@if($start){{ $start->format('j F Y') }}@endif @if($event->event_venue) · {{ $event->event_venue }}@endif</div>
        </div>
        <div class="title">Check in <span>here</span></div>
        <p class="lead">Scan with your phone camera</p>
        <div class="qr">{!! $qr !!}</div>
        @if($size !== 'tent')
        <div class="steps">
          <div class="step"><b>1</b><strong>Scan</strong>the QR code with your phone camera</div>
          <div class="step"><b>2</b><strong>Type</strong>your registration number</div>
          <div class="step"><b>3</b><strong>Done!</strong>you are checked in — once is enough</div>
        </div>
        @endif
        <div class="url">{{ $short }}</div>
      </div>
      @endforeach
    </div>
  @endfor
</body>
</html>
