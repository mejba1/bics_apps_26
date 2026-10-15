@php use App\Support\EventSettings as Ev; @endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
  @page { margin: 26px 28px 40px 28px; }
  * { font-family: 'DejaVu Sans', sans-serif; }
  body { color: #1d2433; font-size: 9pt; margin: 0; }
  .brand-bar { height: 5px; background: #0e4ca1; margin-bottom: 14px; }
  .brand-bar span { display: inline-block; height: 5px; width: 25%; }
  table.head { width: 100%; border-collapse: collapse; margin-bottom: 12px; table-layout: fixed; }
  table.head td { vertical-align: middle; }
  .logo { height: 46px; }
  .event { overflow-wrap: anywhere; }
  .title { font-size: 17pt; font-weight: bold; color: #0e4ca1; margin: 0; }
  .event { font-size: 9.5pt; color: #4a5368; margin-top: 3px; }
  .meta { text-align: right; font-size: 8pt; color: #6b7385; line-height: 1.6; }
  .scope { display: inline-block; background: #eef4fd; color: #0e4ca1; font-weight: bold; padding: 3px 8px; border-radius: 10px; font-size: 8pt; }
  table.summary { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 14px; }
  table.summary td { background: #f5f8fc; border: 1px solid #e3e8f0; border-radius: 6px; padding: 8px 10px; width: 16.6%; vertical-align: top; }
  .num { font-size: 15pt; font-weight: bold; color: #1d2433; }
  .lbl { font-size: 7.5pt; color: #6b7385; text-transform: uppercase; letter-spacing: .5px; }
  .small { font-size: 7.5pt; color: #6b7385; }
  /* Fixed column widths that add up to the page width; long text (emails, links) wraps inside its cell */
  table.data { width: 100%; border-collapse: collapse; table-layout: fixed; }
  table.data thead { display: table-header-group; }
  table.data th { background: #0e4ca1; color: #fff; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .4px; text-align: left; padding: 6px 6px; }
  table.data td { padding: 5px 5px; border-bottom: 1px solid #e6eaf1; vertical-align: top; font-size: 8pt; line-height: 1.3; overflow-wrap: anywhere; word-wrap: break-word; }
  table.data th { white-space: nowrap; letter-spacing: .2px; padding: 6px 5px; }
  table.data tr:nth-child(even) td { background: #f7f9fc; }
  table.data tr { page-break-inside: avoid; }
  .code { font-weight: bold; color: #0e4ca1; }
  .name { font-weight: bold; }
  .muted { color: #6b7385; font-size: 7.5pt; }
  .pill { font-size: 6.8pt; font-weight: bold; padding: 1px 5px; border-radius: 8px; white-space: nowrap; }
  .yes { background: #e3f4e8; color: #2f6b1c; }
  .no { background: #eef0f4; color: #5f6878; }
  .footer { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 7.5pt; color: #8a93a6; border-top: 1px solid #e3e8f0; padding-top: 5px; }
  .empty { text-align: center; color: #6b7385; padding: 30px; }
</style>
</head>
<body>
  {{-- footer (event / venue · confidential · generated · page) is drawn on every page by App\Support\PdfFooter --}}

  <div class="brand-bar"><span style="background:#0e4ca1"></span><span style="background:#b22e6b"></span><span style="background:#4f8a2e"></span><span style="background:#519cbb"></span></div>

  <table class="head">
    <tr>
      <td style="width:150px">@if(is_file($logo))<img class="logo" src="{{ $logo }}" alt="BICS">@endif</td>
      <td>
        <p class="title">{{ $title }}</p>
        <div class="event">{{ $eventLine }}</div>
      </td>
      <td class="meta" style="width:250px">
        <span class="scope">{{ $scope }}</span><br>
        Generated {{ $generatedAt->format('j F Y, H:i T') }}@if($generatedBy)<br>by {{ $generatedBy }}@endif
      </td>
    </tr>
  </table>

  <table class="summary">
    <tr>
      <td><div class="lbl">Registrations</div><div class="num">{{ $summary['total'] }}</div></td>
      <td><div class="lbl">Male / Female</div><div class="num">{{ $summary['male'] }} / {{ $summary['female'] }}</div></td>
      <td><div class="lbl">Countries</div><div class="num">{{ $summary['countries'] }}</div></td>
      <td><div class="lbl">Checked in</div><div class="num">{{ $summary['checked_in'] }}</div>
        <div class="small">{{ $summary['total'] ? round($summary['checked_in'] / $summary['total'] * 100) : 0 }}% of registrations</div></td>
      <td colspan="2"><div class="lbl">Top professions</div>
        <div class="small" style="color:#1d2433;font-size:8pt;line-height:1.5">
          @forelse($summary['professions'] as $name => $n){{ $name ?: '—' }}: <b>{{ $n }}</b>@if(!$loop->last) &nbsp;·&nbsp; @endif @empty — @endforelse
        </div></td>
    </tr>
  </table>

  @if($rows->isEmpty())
    <div class="empty">No registrations match this report.</div>
  @else
  <table class="data">
    <thead>
      <tr>
        <th style="width:3%">#</th>
        <th style="width:6.5%">Reg. no</th>
        <th style="width:14.5%">Name</th>
        <th style="width:12%">Profession</th>
        <th style="width:17%">Institution</th>
        <th style="width:7.5%">Country</th>
        <th style="width:17%">Contact</th>
        <th style="width:6%">Gender</th>
        <th style="width:8.5%">Registered</th>
        <th style="width:8%">Status</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $r)
      <tr>
        <td class="muted">{{ $loop->iteration }}</td>
        <td class="code">{{ $r->qr_code }}</td>
        <td><span class="name">{{ trim($r->title . ' ' . $r->firstname . ' ' . $r->lastname) }}</span>@if($r->designation)<br><span class="muted">{{ $r->designation }}</span>@endif</td>
        <td>{{ $r->profession }}@if($r->specialty)<br><span class="muted">{{ $r->specialty }}</span>@endif</td>
        <td>{{ $r->institute }}</td>
        <td>{{ $r->country }}</td>
        <td>{{ $r->email }}<br><span class="muted">{{ $r->phone }}</span></td>
        <td>{{ $r->gender }}</td>
        <td>{{ $r->created_at ? \Carbon\Carbon::parse($r->created_at)->format('j M Y') : '—' }}</td>
        <td><span class="pill {{ $r->verify_status === 'Yes' ? 'yes' : 'no' }}">{{ $r->verify_status === 'Yes' ? 'Checked in' : 'Registered' }}</span></td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif
</body>
</html>
