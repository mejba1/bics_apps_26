@php use App\Support\EventSettings as Ev; $max = max(1, $groups->max('total') ?? 1); $pct = fn ($n) => $totals['total'] ? round($n / $totals['total'] * 100, 1) : 0; @endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
  @page { margin: 28px 30px 42px 30px; }
  * { font-family: 'DejaVu Sans', sans-serif; }
  body { color: #1d2433; font-size: 9pt; margin: 0; }
  .brand-bar span { display: inline-block; height: 5px; width: 25%; }
  table { border-collapse: collapse; }
  table.head { width: 100%; margin: 14px 0 12px; table-layout: fixed; }
  table.head td { vertical-align: middle; }
  .title { font-size: 16pt; font-weight: bold; color: #0e4ca1; margin: 0; }
  .event { font-size: 9pt; color: #4a5368; margin-top: 3px; overflow-wrap: anywhere; }
  .meta { text-align: right; font-size: 8pt; color: #6b7385; line-height: 1.6; }
  .scope { background: #eef4fd; color: #0e4ca1; font-weight: bold; padding: 6px 10px; border-radius: 6px; font-size: 8.5pt; margin-bottom: 12px; }
  table.kpi { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 14px; table-layout: fixed; }
  table.kpi td { background: #f5f8fc; border: 1px solid #e3e8f0; border-radius: 6px; padding: 8px 10px; }
  .num { font-size: 15pt; font-weight: bold; }
  .lbl { font-size: 7.5pt; color: #6b7385; text-transform: uppercase; letter-spacing: .5px; }
  .small { font-size: 7.5pt; color: #6b7385; }
  table.data { width: 100%; table-layout: fixed; }
  table.data thead { display: table-header-group; }
  table.data th { background: #0e4ca1; color: #fff; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .3px; text-align: left; padding: 6px; white-space: nowrap; }
  table.data th.r, table.data td.r { text-align: right; }
  table.data td { padding: 5px 6px; border-bottom: 1px solid #e6eaf1; vertical-align: middle; font-size: 8.5pt; overflow-wrap: anywhere; }
  table.data tr:nth-child(even) td { background: #f7f9fc; }
  table.data tr { page-break-inside: avoid; }
  table.data tfoot td { font-weight: bold; background: #eef4fd !important; border-top: 1.5px solid #0e4ca1; }
  .bar { height: 7px; background: #e6eaf1; border-radius: 4px; }
  .bar span { display: block; height: 7px; background: #0e4ca1; border-radius: 4px; }
  .footer { position: fixed; bottom: -28px; left: 0; right: 0; font-size: 7.5pt; color: #8a93a6; border-top: 1px solid #e3e8f0; padding-top: 5px; }
  .empty { text-align: center; color: #6b7385; padding: 30px; }
</style>
</head>
<body>
  {{-- footer (event / venue · generated · page) is drawn on every page by App\Support\PdfFooter --}}
  <div class="brand-bar"><span style="background:#0e4ca1"></span><span style="background:#b22e6b"></span><span style="background:#4f8a2e"></span><span style="background:#519cbb"></span></div>

  <table class="head">
    <tr>
      <td style="width:17%">@if(is_file($logo))<img src="{{ $logo }}" style="height:40px" alt="BICS">@endif</td>
      <td style="width:56%"><p class="title">{{ $title }}</p><div class="event">{{ $eventLine }}</div></td>
      <td class="meta" style="width:27%">Generated {{ $generatedAt->format('j F Y, H:i T') }}@if($generatedBy)<br>by {{ $generatedBy }}@endif</td>
    </tr>
  </table>
  <div class="scope">{{ $scope }}</div>

  <table class="kpi">
    <tr>
      <td><div class="lbl">Registrations</div><div class="num">{{ number_format($totals['total']) }}</div></td>
      <td><div class="lbl">Male</div><div class="num">{{ number_format($totals['male']) }}</div><div class="small">{{ $pct($totals['male']) }}%</div></td>
      <td><div class="lbl">Female</div><div class="num">{{ number_format($totals['female']) }}</div><div class="small">{{ $pct($totals['female']) }}%</div></td>
      <td><div class="lbl">Checked in</div><div class="num">{{ number_format($totals['checked_in']) }}</div><div class="small">{{ $pct($totals['checked_in']) }}%</div></td>
      <td><div class="lbl">{{ $groupLabel }} groups</div><div class="num">{{ $groups->count() }}</div></td>
    </tr>
  </table>

  @if($groups->isEmpty())
    <div class="empty">No registrations match this report.</div>
  @else
  <table class="data">
    <thead>
      <tr>
        <th style="width:5%">#</th>
        <th style="width:27%">{{ $groupLabel }}</th>
        <th class="r" style="width:12%">Registrations</th>
        <th style="width:16%">Share</th>
        <th class="r" style="width:9%">Male</th>
        <th class="r" style="width:10%">Female</th>
        <th class="r" style="width:13%">Checked in</th>
        <th class="r" style="width:8%">%</th>
      </tr>
    </thead>
    <tbody>
      @foreach($groups as $g)
      <tr>
        <td class="small">{{ $loop->iteration }}</td>
        <td><b>{{ $g->label }}</b></td>
        <td class="r"><b>{{ number_format($g->total) }}</b></td>
        <td><div class="bar"><span style="width:{{ max(2, round($g->total / $max * 100)) }}%"></span></div></td>
        <td class="r">{{ number_format($g->male) }}</td>
        <td class="r">{{ number_format($g->female) }}</td>
        <td class="r">{{ number_format($g->checked_in) }}</td>
        <td class="r">{{ $pct($g->total) }}%</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td></td><td>Total</td><td class="r">{{ number_format($totals['total']) }}</td><td></td>
        <td class="r">{{ number_format($totals['male']) }}</td><td class="r">{{ number_format($totals['female']) }}</td>
        <td class="r">{{ number_format($totals['checked_in']) }}</td><td class="r">100%</td>
      </tr>
    </tfoot>
  </table>
  @endif
</body>
</html>
