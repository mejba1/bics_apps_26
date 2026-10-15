{{--
  Attendee ID card (A6 portrait, 297.64 × 419.53 pt). Rendered by App\Support\IdCard.
  dompdf: absolute positioning in pt, DejaVu Sans, images embedded as data URIs (nothing is fetched).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $eventShort }} · {{ $name }} · {{ $data->qr_code }}</title>
<style>
  @page { margin: 0; }
  * { font-family: 'DejaVu Sans', sans-serif; margin: 0; padding: 0; }
  body { width: 297.64pt; height: 419.53pt; position: relative; color: #1d2433; background: #ffffff; }
  .abs { position: absolute; }
  .header { left: 0; top: 0; width: 297.64pt; height: 134.4pt; }
  .logo-plate { left: 14pt; top: 13pt; background: #ffffff; border-radius: 7pt; padding: 4pt 8pt 3pt; }
  .logo-plate img { height: 22pt; }
  .ev-short { right: 14pt; top: 15pt; width: 150pt; text-align: right; color: #ffffff; font-size: 13pt; font-weight: bold; letter-spacing: .5pt; }
  .ev-kind { right: 14pt; top: 33pt; width: 150pt; text-align: right; color: #ffffff; font-size: 6.2pt; letter-spacing: 1.6pt; opacity: .85; }
  .ev-name { left: 16pt; top: 50pt; width: 175pt; color: #ffffff; font-size: 7pt; line-height: 1.35; opacity: .95; }
  .photo-ring { left: 98.82pt; top: 84pt; width: 100pt; height: 100pt; border-radius: 50pt; background: #ffffff; }
  .photo { left: 102.82pt; top: 88pt; width: 92pt; height: 92pt; border-radius: 46pt; }
  .initials { left: 102.82pt; top: 88pt; width: 92pt; height: 60pt; padding-top: 32pt; border-radius: 46pt; text-align: center; line-height: 1; color: #ffffff; font-size: 26pt; font-weight: bold; letter-spacing: 1pt; }
  .who { left: 14pt; top: 191pt; width: 269.64pt; text-align: center; }
  .name { font-weight: bold; line-height: 1.15; color: #1d2433; overflow-wrap: anywhere; }
  .prof { margin-top: 4pt; font-size: 8.6pt; font-weight: bold; }
  .spec { font-size: 7.6pt; color: #4a5368; margin-top: 1pt; }
  .inst { font-size: 7.2pt; color: #6b7385; margin-top: 3pt; line-height: 1.3; overflow-wrap: anywhere; }
  .panel { left: 14pt; top: 278pt; width: 269.64pt; height: 96pt; border: 0.8pt solid #e3e8f0; border-radius: 9pt; background: #f7f9fc; }
  .qr-box { left: 22pt; top: 285pt; width: 72pt; height: 72pt; background: #ffffff; border-radius: 6pt; }
  .qr-box img { margin: 4pt; width: 64pt; height: 64pt; }
  .info { left: 104pt; top: 288pt; width: 172pt; }
  .lbl { font-size: 5.6pt; letter-spacing: 1.2pt; color: #8a93a6; font-weight: bold; }
  .code { font-size: 19pt; font-weight: bold; letter-spacing: 3pt; line-height: 1.1; margin: 1pt 0 5pt; }
  .meta { font-size: 6.6pt; color: #4a5368; line-height: 1.45; overflow-wrap: anywhere; }
  .meta b { color: #1d2433; }
  .scan { left: 22pt; top: 362pt; width: 72pt; text-align: center; font-size: 5.2pt; color: #8a93a6; letter-spacing: .4pt; }
  .org { left: 104pt; top: 362pt; width: 171pt; text-align: right; font-size: 5.6pt; color: #8a93a6; }
  .ribbon { left: 0; top: 384.53pt; width: 297.64pt; height: 35pt; }
  .ribbon-text { left: 0; top: 396pt; width: 297.64pt; line-height: 1; text-align: center; color: #ffffff; font-size: 10.5pt; font-weight: bold; letter-spacing: 2.4pt; }
</style>
</head>
<body>
  {{-- Header artwork + event --}}
  <img class="abs header" src="{{ $header }}" alt="">
  <div class="abs logo-plate">@if(is_file($logo))<img src="{{ $logo }}" alt="BICS">@else<b>BICS</b>@endif</div>
  <div class="abs ev-short">{{ $eventShort }}</div>
  <div class="abs ev-kind">ATTENDEE ID CARD</div>
  <div class="abs ev-name">{{ \Illuminate\Support\Str::limit($eventName, 70) }}</div>

  {{-- Photo --}}
  <div class="abs photo-ring"></div>
  @if($photo)
    <img class="abs photo" src="{{ $photo }}" alt="">
  @else
    <div class="abs initials" style="background:{{ $brand }}">{{ $initials ?: '•' }}</div>
  @endif

  {{-- Name and role --}}
  <div class="abs who">
    <div class="name" style="font-size:{{ $nameSize }}pt">{{ $name }}</div>
    @if($data->profession)<div class="prof" style="color:{{ $brand }}">{{ $data->profession }}</div>@endif
    @if($nameLines < 3 && $data->specialty && $data->specialty !== $data->profession)<div class="spec">{{ $data->specialty }}</div>@endif
    @if($data->institute || $data->country)
      <div class="inst">{{ \Illuminate\Support\Str::limit(trim($data->institute), $nameLines > 1 ? 34 : 70) }}{{ $data->institute && $data->country ? ' · ' : '' }}{{ $data->country }}</div>
    @endif
  </div>

  {{-- QR code + registration number + event details --}}
  <div class="abs panel"></div>
  <div class="abs qr-box"><img src="{{ $qr }}" alt="QR code"></div>
  <div class="abs info">
    <div class="lbl">REGISTRATION NO.</div>
    <div class="code" style="color:{{ $brand }}">{{ $data->qr_code }}</div>
    @if($dates)<div class="meta"><b>Date</b>&nbsp; {{ $dates }}</div>@endif
    @if($venue)<div class="meta"><b>Venue</b>&nbsp; {{ \Illuminate\Support\Str::limit($venue, 60) }}</div>@endif
  </div>
  <div class="abs scan">SCAN TO VERIFY</div>
  @if($organizer)<div class="abs org">{{ $organizer }}</div>@endif

  {{-- Role ribbon --}}
  <div class="abs ribbon" style="background:{{ $role['color'] }}"></div>
  <div class="abs ribbon-text">{{ \Illuminate\Support\Str::limit($role['label'], 28) }}</div>
</body>
</html>
