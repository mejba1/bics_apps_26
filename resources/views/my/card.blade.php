@extends('my.layout')
@section('title', 'Card & QR')
@section('heading', 'Card & QR code')
@section('sub', 'Share your profile — the link and QR code never change')
@php
  $views = ($stats['view_link'] ?? 0) + ($stats['view_qr'] ?? 0) + ($stats['view_nfc'] ?? 0);
  $clicks = collect($stats)->filter(fn ($n, $k) => str_starts_with($k, 'click_'))->sum();
  $max = max(1, collect($series)->max('n'));
  $state = !$card ? ['none', 'bi-info-circle', 'No NFC card yet', 'The organizers give out NFC cards at the event — your QR code works already.']
      : match ($card->status) {
          'active' => ['active', 'bi-broadcast', 'NFC card active', 'Card ' . $card->card_no . ' — tap it on a phone to open your profile.'],
          'assigned' => ['prep', 'bi-hourglass-split', 'NFC card being prepared', 'Card ' . $card->card_no . ' is reserved for you and will be activated soon.'],
          default => ['off', 'bi-x-octagon', 'NFC card switched off', 'Card ' . $card->card_no . ' no longer opens your profile. Ask the organizers for a new one.'],
      };
@endphp
@push('styles')
<style>
  .qrbox { display: flex; gap: 20px; align-items: center; }
  .qrbox img { width: 180px; height: 180px; border-radius: 16px; border: 1px solid var(--line); padding: 8px; background: #fff; flex: none; }
  .url { font-family: Consolas, monospace; font-size: 13px; background: var(--soft); border-radius: 12px; padding: 10px 12px; word-break: break-all; }
  .cards { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; background: var(--soft); border-radius: 16px; padding: 18px; }
  .cards svg { filter: drop-shadow(0 8px 18px rgba(16,30,60,.25)); max-width: 100%; height: auto; }
  .state { border-radius: 14px; padding: 12px 14px; display: flex; gap: 12px; align-items: flex-start; font-size: 14px; margin-bottom: 14px; }
  .state i { font-size: 20px; }
  .state.active { background: #e5f4ea; color: #1d6b39; } .state.prep { background: #e8f0fc; color: var(--blue); } .state.off { background: #fde8eb; color: #a3243a; } .state.none { background: var(--soft); color: var(--muted); }
  .kpis { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; }
  .kpis div { background: var(--soft); border-radius: 14px; padding: 12px; text-align: center; }
  .kpis b { display: block; font-size: 1.4rem; font-weight: 800; color: var(--ink); } .kpis span { font-size: 12px; color: var(--muted); font-weight: 600; }
  .bars { display: flex; align-items: flex-end; gap: 4px; height: 90px; margin-top: 16px; }
  .bars i { flex: 1; background: linear-gradient(var(--blue-2), #6aa6ff); border-radius: 4px 4px 0 0; min-height: 3px; }
  @media (max-width: 767px) { .qrbox { flex-direction: column; } .kpis { grid-template-columns: repeat(3, 1fr); } }
</style>
@endpush
@section('content')
<div class="row g-3">
  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="panel-h"><span class="ico"><i class="bi bi-qr-code"></i></span><h2>My QR code &amp; link</h2></div>
      <div class="qrbox">
        <img src="{{ route('profile.qr', [$p->code, 'svg']) }}" alt="QR code for {{ $p->name }}">
        <div class="w-100 min-w-0">
          <div class="small fw-bold mb-1 ink">Profile link</div>
          <div class="url mb-2" id="my-url">{{ $p->shareUrl() }}</div>
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-primary" id="my-copy"><i class="bi bi-clipboard"></i> Copy</button>
            <button class="btn btn-sm btn-soft d-none" id="my-share"><i class="bi bi-share"></i> Share</button>
            <a class="btn btn-sm btn-soft" href="{{ $p->url() }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open</a>
          </div>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <a class="btn btn-sm btn-outline-primary" href="{{ route('profile.qr', [$p->code, 'png']) }}" download="{{ $p->code }}-qr.png"><i class="bi bi-download"></i> QR (PNG)</a>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('profile.qr', [$p->code, 'svg']) }}" download="{{ $p->code }}-qr.svg"><i class="bi bi-download"></i> QR (SVG, for print)</a>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('profile.vcard', $p->code) }}"><i class="bi bi-person-lines-fill"></i> Contact card (.vcf)</a>
      </div>
      <div class="small muted mt-2">Put the QR on slides, posters or your email signature. The contact card has only the details you made public.</div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="panel h-100">
      <div class="panel-h"><span class="ico"><i class="bi bi-credit-card-2-front"></i></span><h2>My participant card</h2></div>
      <div class="state {{ $state[0] }}"><i class="bi {{ $state[1] }}"></i><div><b>{{ $state[2] }}</b><div>{{ $state[3] }}</div></div></div>
      @if($template)
        <div class="cards" id="my-cards"><span class="small muted">Loading preview…</span></div>
        <div class="small muted mt-2">Preview — the printed card may differ slightly. Wrong details? Update your profile or tell the organizers.</div>
      @else
        <div class="small muted">Your card design is not ready yet.</div>
      @endif
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-h"><span class="ico"><i class="bi bi-graph-up"></i></span><h2>Visits to my profile</h2><span class="more muted fw-normal">all time</span></div>
  <div class="kpis">
    <div><b>{{ number_format($views) }}</b><span>Views</span></div>
    <div><b>{{ number_format($stats['view_qr'] ?? 0) }}</b><span>QR scans</span></div>
    <div><b>{{ number_format($stats['view_nfc'] ?? 0) }}</b><span>NFC taps</span></div>
    <div><b>{{ number_format($stats['view_link'] ?? 0) }}</b><span>Link visits</span></div>
    <div><b>{{ number_format($stats['vcard'] ?? 0) }}</b><span>Contacts saved</span></div>
    <div><b>{{ number_format($clicks) }}</b><span>Calls, emails, links</span></div>
  </div>
  <div class="bars" title="Profile views per day">@foreach($series as $d)<i style="height:{{ max(3, round($d['n'] / $max * 100)) }}%" title="{{ \Carbon\Carbon::parse($d['day'])->format('j M') }}: {{ $d['n'] }}"></i>@endforeach</div>
  <div class="d-flex justify-content-between small muted mt-1"><span>{{ \Carbon\Carbon::parse($series[0]['day'])->format('j M') }}</span><span>Views per day, last 30 days</span><span>Today</span></div>
  <div class="small muted mt-2"><i class="bi bi-shield-check"></i> Only counts are kept — we never record who visited. Your own visits are not counted.</div>
</div>
@endsection

@push('scripts')
@if($template)
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Inter:wght@300..800&family=Montserrat:ital,wght@0,300..800;1,300..800&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400;1,700&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Oswald:wght@300..700&family=Playfair+Display:ital,wght@0,400..800;1,400..800&family=Lora:ital,wght@0,400..700;1,400..700&family=Merriweather:ital,wght@0,300;0,400;0,700;1,400&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script src="{{ asset('assets/backend/card-studio/card-renderer.js') }}?v={{ @filemtime(base_path('assets/backend/card-studio/card-renderer.js')) }}"></script>
<script>
(function () {
  var design = @json($template['design']), data = @json($cardData), icons = @json($icons), base = @json(url('/') . '/');
  CardRenderer.ready(design).then(function () {
    var box = document.getElementById('my-cards'); box.innerHTML = '';
    var h = design.height > design.width ? 250 : 160;
    (design.double_sided ? ['front', 'back'] : ['front']).forEach(function (s) {
      box.appendChild(CardRenderer.render(design, s, data, { icons: icons, base: base, trim: true, widthPx: Math.round(h * design.width / design.height) }));
    });
  });
})();
</script>
@endif
<script>
(function () {
  var url = document.getElementById('my-url').textContent.trim();
  document.getElementById('my-copy').addEventListener('click', function () {
    (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () { myToast('Link copied'); }, function () { prompt('Copy your link:', url); });
  });
  var share = document.getElementById('my-share');
  if (navigator.share) { share.classList.remove('d-none'); share.addEventListener('click', function () { navigator.share({ title: @json($p->name), url: url }).catch(function () {}); }); }
})();
</script>
@endpush
