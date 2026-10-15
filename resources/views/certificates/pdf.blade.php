@php
   /*
    | Participation certificate, A4 landscape (842 × 595 pt), rendered by DomPDF.
    | Design: Admin > Certificates (App\Support\Certificate). DomPDF has no flexbox / gradients:
    | everything is absolutely positioned boxes and tables.
    */
   use App\Support\Certificate as C;
   $t = $d['template'];
   $own = (bool) $background;                       // own artwork: no template decoration
   $name = $vars['{name}'];
   $nameSize = mb_strlen($name) > 40 ? 22 : (mb_strlen($name) > 28 ? 28 : 34);
   $nameFont = $d['name_style'] === 'sans' ? "font-family:'DejaVu Sans';font-weight:bold;" : "font-family:'DejaVu Serif';" . ($d['name_style'] === 'serif_italic' ? 'font-style:italic;' : '') . 'font-weight:bold;';
   // Content area per template (left, right, top, bottom in pt) and alignment
   $box = ['classic' => [70, 70, 58, 46], 'modern' => [262, 54, 52, 44], 'elegant' => [86, 86, 60, 48]][$t];
   $qrRight = $t === 'elegant' && !$own ? 128 : $box[1];   // keep the QR clear of the elegant corner shape
   $align = $t === 'modern' ? 'left' : 'center';
   $muted = '#5b6475';
   $year = optional($event->dateOf('event_start'))->format('Y') ?: date('Y');
   $sigCount = count($signatures);
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
   @page { margin: 0; }
   * { margin: 0; padding: 0; }
   body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #1d2433; background: {{ $own ? '#ffffff' : $d['paper'] }}; }
   .abs { position: absolute; }
   .title { font-family: 'DejaVu Serif'; font-weight: bold; color: {{ $d['primary'] }}; letter-spacing: 6pt; }
   .sub { color: {{ $d['accent'] }}; font-weight: bold; letter-spacing: 4pt; font-size: 13pt; }
   .muted { color: {{ $muted }}; }
   table { border-collapse: collapse; }
</style>
</head>
<body>
@if($own)
   <img class="abs" src="{{ $background }}" style="left:0;top:0;width:842pt;height:595pt;">
@elseif($t === 'classic')
   {{-- Classic: double frame with gold corners --}}
   <div class="abs" style="left:16pt;top:16pt;width:798pt;height:551pt;border:7pt solid {{ $d['primary'] }};"></div>
   <div class="abs" style="left:34pt;top:34pt;width:770pt;height:523pt;border:1.4pt solid {{ $d['gold'] }};"></div>
   @foreach([[29, 29], [801, 29], [29, 552], [801, 552]] as [$x, $y])
   <div class="abs" style="left:{{ $x }}pt;top:{{ $y }}pt;width:12pt;height:12pt;background:{{ $d['gold'] }};"></div>
   @endforeach
   <div class="abs" style="left:371pt;top:34pt;width:100pt;height:5pt;background:{{ $d['accent'] }};"></div>
@elseif($t === 'modern')
   {{-- Modern: colour band on the left --}}
   <div class="abs" style="left:0;top:0;width:214pt;height:595pt;background:{{ $d['primary'] }};"></div>
   <div class="abs" style="left:214pt;top:0;width:9pt;height:595pt;background:{{ $d['accent'] }};"></div>
   <div class="abs" style="left:223pt;top:0;width:3pt;height:595pt;background:{{ $d['gold'] }};"></div>
   <div class="abs" style="left:30pt;top:46pt;width:154pt;text-align:center;">
      @if($logo)<div style="background:#ffffff;border-radius:10pt;padding:10pt 8pt;"><img src="{{ $logo }}" style="max-width:130pt;max-height:60pt;"></div>@endif
   </div>
   <div class="abs" style="left:26pt;top:250pt;width:162pt;text-align:center;color:#ffffff;">
      <div style="font-size:11pt;letter-spacing:3pt;color:#dfe8f8;">PARTICIPANT</div>
      <div style="font-family:'DejaVu Serif';font-weight:bold;font-size:24pt;margin-top:6pt;line-height:1.15;">{{ $vars['{event_short}'] }}</div>
      <div style="width:40pt;height:3pt;background:{{ $d['gold'] }};margin:12pt auto;"></div>
      <div style="font-size:10pt;color:#dfe8f8;line-height:1.5;">{{ $vars['{event_dates}'] }}</div>
   </div>
   @if($d['show_seal'] === '1')
   <div class="abs" style="left:62pt;top:452pt;width:84pt;height:84pt;border:3pt solid {{ $d['gold'] }};border-radius:45pt;text-align:center;color:#ffffff;">
      <div style="padding-top:22pt;font-family:'DejaVu Serif';font-weight:bold;font-size:15pt;letter-spacing:2pt;">{{ $d['number_prefix'] === '' ? 'BICS' : mb_substr(preg_replace('/\d+$/', '', $d['number_prefix']) ?: 'BICS', 0, 6) }}</div>
      <div style="font-size:9pt;letter-spacing:2pt;color:#f3dfa0;">{{ $year }}</div>
   </div>
   @endif
@else
   {{-- Elegant: corner triangles + fine frame --}}
   <div class="abs" style="left:0;top:0;width:0;height:0;border-top:190pt solid {{ $d['primary'] }};border-right:190pt solid transparent;"></div>
   <div class="abs" style="left:0;top:0;width:0;height:0;border-top:120pt solid {{ $d['accent'] }};border-right:120pt solid transparent;"></div>
   <div class="abs" style="left:692pt;top:445pt;width:0;height:0;border-bottom:150pt solid {{ $d['primary'] }};border-left:150pt solid transparent;"></div>
   <div class="abs" style="left:747pt;top:500pt;width:0;height:0;border-bottom:95pt solid {{ $d['gold'] }};border-left:95pt solid transparent;"></div>
   <div class="abs" style="left:28pt;top:28pt;width:784pt;height:537pt;border:1pt solid {{ $d['gold'] }};"></div>
@endif

{{-- Logos (classic / elegant: top centre; modern: in the band) --}}
@if($t !== 'modern' || $own)
<div class="abs" style="left:{{ $box[0] }}pt;width:{{ 842 - $box[0] - $box[1] }}pt;top:{{ $box[2] }}pt;text-align:center;">
   @if($logo)<img src="{{ $logo }}" style="max-height:52pt;max-width:180pt;">@endif
   @if($logo2)<img src="{{ $logo2 }}" style="max-height:52pt;max-width:160pt;margin-left:22pt;">@endif
</div>
@elseif($logo2)
<div class="abs" style="left:{{ 842 - $box[1] - 150 }}pt;width:150pt;text-align:right;top:{{ $box[2] - 8 }}pt;"><img src="{{ $logo2 }}" style="max-height:44pt;max-width:150pt;"></div>
@endif

{{-- Main text --}}
<div class="abs" style="left:{{ $box[0] }}pt;width:{{ 842 - $box[0] - $box[1] }}pt;top:{{ $t === 'modern' && !$own ? $box[2] + 18 : $box[2] + 66 }}pt;text-align:{{ $align }};">
   <div class="title" style="font-size:{{ $t === 'modern' ? 40 : 38 }}pt;">{{ mb_strtoupper($d['title']) }}</div>
   @if($d['subtitle'] !== '')<div class="sub" style="margin-top:4pt;">{{ mb_strtoupper($d['subtitle']) }}</div>@endif
   @if($t === 'modern')<div style="width:70pt;height:4pt;background:{{ $d['gold'] }};margin-top:14pt;"></div>@endif
   @if($d['presented'] !== '')<div class="muted" style="font-size:12pt;margin-top:{{ $t === 'modern' ? 22 : 18 }}pt;">{{ strtr($d['presented'], $vars) }}</div>@endif
   <div style="{{ $nameFont }}font-size:{{ $nameSize }}pt;color:#1d2433;margin-top:10pt;line-height:1.15;">{{ $name }}</div>
   <div style="width:{{ $t === 'modern' ? 320 : 360 }}pt;height:1.2pt;background:{{ $d['gold'] }};margin:8pt {{ $align === 'center' ? 'auto' : '0' }} 0;"></div>
   <div style="font-size:12.5pt;line-height:1.55;color:#3b4559;margin-top:14pt;">{!! C::html($d['body'], $vars, $d['primary']) !!}</div>
   @if($d['footer_note'] !== '')<div class="muted" style="font-size:9.5pt;margin-top:8pt;">{!! C::html($d['footer_note'], $vars) !!}</div>@endif
</div>

{{-- Signatures --}}
@if($sigCount)
@php $sigWidth = $t === 'modern' ? 842 - $box[0] - $box[1] - 120 : 842 - $box[0] - $qrRight - 220; $sigLeft = $t === 'modern' ? $box[0] : $box[0] + 110; @endphp
<table class="abs" style="left:{{ $sigLeft }}pt;width:{{ $sigWidth }}pt;top:{{ 595 - $box[3] - 92 }}pt;">
   <tr>
      @foreach($signatures as $s)
      <td style="width:{{ floor(100 / $sigCount) }}%;text-align:center;vertical-align:bottom;padding:0 10pt;">
         <div style="height:40pt;">@if($s['src'])<img src="{{ $s['src'] }}" style="max-height:40pt;max-width:130pt;">@endif</div>
         <div style="border-top:1pt solid #9aa3b5;margin:2pt 6pt 0;"></div>
         @if($s['name'] !== '')<div style="font-size:10.5pt;font-weight:bold;margin-top:4pt;">{{ strtr($s['name'], $vars) }}</div>@endif
         @if($s['role'] !== '')<div class="muted" style="font-size:8.5pt;margin-top:2pt;">{{ strtr($s['role'], $vars) }}</div>@endif
      </td>
      @endforeach
   </tr>
</table>
@endif

{{-- Seal (classic / elegant) --}}
@if($d['show_seal'] === '1' && $t !== 'modern' && !$own)
<div class="abs" style="left:{{ $box[0] - 6 }}pt;top:{{ 595 - $box[3] - 86 }}pt;width:78pt;height:78pt;border:3pt solid {{ $d['gold'] }};border-radius:42pt;background:{{ $d['primary'] }};text-align:center;color:#ffffff;">
   <div style="padding-top:20pt;font-family:'DejaVu Serif';font-weight:bold;font-size:13pt;letter-spacing:1.5pt;">{{ mb_substr(preg_replace('/\d+$/', '', $d['number_prefix']) ?: 'BICS', 0, 6) }}</div>
   <div style="font-size:8.5pt;letter-spacing:2pt;color:#f3dfa0;">{{ $year }}</div>
</div>
@endif

{{-- QR + number + date --}}
@if($d['show_qr'] === '1' || $d['show_number'] === '1' || $d['show_date'] === '1')
<div class="abs" style="left:{{ 842 - $qrRight - 102 }}pt;top:{{ 595 - $box[3] - 98 }}pt;width:110pt;text-align:center;">
   @if($qr)<img src="{{ $qr }}" style="width:62pt;height:62pt;">@endif
   @if($d['show_number'] === '1')<div style="font-size:7.5pt;color:{{ $muted }};margin-top:3pt;">No. <b style="color:#1d2433">{{ $vars['{certificate_no}'] }}</b></div>@endif
   @if($d['show_date'] === '1')<div style="font-size:7.5pt;color:{{ $muted }};margin-top:1pt;">Issued {{ $vars['{issue_date}'] }}</div>@endif
   @if($qr)<div style="font-size:6.5pt;color:{{ $muted }};margin-top:1pt;">Scan to verify</div>@endif
</div>
@endif
</body>
</html>
