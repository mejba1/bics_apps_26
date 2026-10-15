@php
  // Attendee record (Admin > download card). Everything is embedded: photo, logo and QR code are made locally.
  use App\Support\EventSettings as Ev;
  $photoPath = base_path('upload/images/' . $data->image);
  $photo = is_file($photoPath) ? 'data:' . mime_content_type($photoPath) . ';base64,' . base64_encode(file_get_contents($photoPath)) : null;
  $logo = base_path('assets/frontend/images/logo.png');
  $verifyUrl = $data->qr_url ?: ('https://bics.org/verify/' . $data->qr_code);
  $qr = 'data:image/svg+xml;base64,' . base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(140)->margin(0)->generate($verifyUrl));
  $checkedIn = $data->verify_status === 'Yes';
  // Header: the attendee's own event (Admin > Events)
  $event = \App\Models\Event::forVenue($data->venue);
  $eventTitle = $event->event_name ?? 'Beacon International Cancer Summit';
  $eventSub = $event ? implode(' · ', array_filter([$event->datesLabel(), $event->event_venue])) ?: 'Event: ' . $data->venue : 'Event: ' . $data->venue;
  $row = fn ($label, $value) => '<tr><td class="k">' . e($label) . '</td><td class="v">' . (trim((string) $value) !== '' ? e($value) : '<span class="muted">—</span>') . '</td></tr>';
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $data->firstname }} {{ $data->lastname }} — {{ $data->qr_code }}</title>
<style>
  @page { margin: 30px 34px; }
  * { font-family: 'DejaVu Sans', sans-serif; }
  body { color: #1d2433; font-size: 9.5pt; margin: 0; }
  .bar span { display: inline-block; height: 5px; width: 25%; }
  table { border-collapse: collapse; }
  .head { width: 100%; margin: 14px 0 18px; table-layout: fixed; }
  .head td { vertical-align: middle; }
  .doc-title { font-size: 9pt; color: #6b7385; text-transform: uppercase; letter-spacing: 1px; text-align: right; }
  .doc-event { font-size: 10pt; font-weight: bold; color: #0e4ca1; text-align: right; }
  .hero { width: 100%; background: #0e4ca1; color: #fff; border-radius: 10px; table-layout: fixed; }
  .hero td { padding: 16px 18px; vertical-align: middle; color: #fff; }
  .photo { width: 104px; height: 104px; border-radius: 52px; border: 3px solid #fff; }
  .nophoto { width: 104px; height: 104px; border-radius: 52px; background: #3d6fb8; color: #fff; text-align: center; line-height: 104px; font-size: 30pt; font-weight: bold; }
  .name { font-size: 15pt; font-weight: bold; line-height: 1.2; overflow-wrap: anywhere; }
  .hero .sub { overflow-wrap: anywhere; }
  .sub { font-size: 9.5pt; opacity: .9; margin-top: 3px; }
  .code-lbl { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 1px; opacity: .8; }
  .code { font-size: 21pt; font-weight: bold; letter-spacing: 3px; }
  .qr { background: #fff; padding: 8px; border-radius: 8px; }
  .status { display: inline-block; margin-top: 8px; font-size: 8pt; font-weight: bold; padding: 3px 10px; border-radius: 10px; }
  .status.yes { background: #e3f4e8; color: #2f6b1c; }
  .status.no { background: #fff4d6; color: #7a5410; }
  .cols { width: 100%; margin-top: 18px; }
  .cols > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; }
  .cols > tbody > tr > td:first-child { padding-right: 10px; }
  .cols > tbody > tr > td:last-child { padding-left: 10px; }
  .box { border: 1px solid #e3e8f0; border-radius: 8px; margin-bottom: 14px; }
  .box h3 { margin: 0; font-size: 8pt; text-transform: uppercase; letter-spacing: 1px; color: #0e4ca1; background: #f5f8fc; padding: 7px 12px; border-bottom: 1px solid #e3e8f0; }
  .kv { width: 100%; table-layout: fixed; }
  .kv td { padding: 6px 12px; border-bottom: 1px solid #eef1f6; vertical-align: top; overflow-wrap: anywhere; }
  .kv tr:last-child td { border-bottom: 0; }
  .k { width: 38%; color: #6b7385; font-size: 8.5pt; }
  .v { font-weight: bold; }
  .muted { color: #a3abbb; font-weight: normal; }
  .foot { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 7.5pt; color: #8a93a6; border-top: 1px solid #e3e8f0; padding-top: 6px; }
</style>
</head>
<body>
  {{-- footer (event / venue · confidential · generated · page) is drawn by App\Support\PdfFooter --}}
  <div class="bar"><span style="background:#0e4ca1"></span><span style="background:#b22e6b"></span><span style="background:#4f8a2e"></span><span style="background:#519cbb"></span></div>

  <table class="head">
    <tr>
      <td>@if(is_file($logo))<img src="{{ $logo }}" style="height:46px" alt="BICS">@endif</td>
      <td style="width:62%"><div class="doc-title">Attendee record</div><div class="doc-event">{{ $eventTitle }}</div><div class="doc-title" style="text-transform:none;letter-spacing:0">{{ $eventSub }}</div></td>
    </tr>
  </table>

  <table class="hero">
    <tr>
      <td style="width:19%;padding-right:6px">
        @if($photo)<img class="photo" src="{{ $photo }}" alt="">@else<div class="nophoto">{{ mb_strtoupper(mb_substr($data->firstname, 0, 1) . mb_substr($data->lastname, 0, 1)) }}</div>@endif
      </td>
      <td style="width:41%">
        <div class="name">{{ trim($data->title . ' ' . $data->firstname . ' ' . $data->lastname) }}</div>
        <div class="sub">{{ $data->profession }}{{ $data->specialty ? ' · ' . $data->specialty : '' }}</div>
        <div class="sub">{{ $data->institute }}{{ $data->country ? ', ' . $data->country : '' }}</div>
        <span class="status {{ $checkedIn ? 'yes' : 'no' }}">{{ $checkedIn ? 'Checked in' . ($data->verify_at ? ' · ' . $data->verify_at : '') : 'Registered — not checked in yet' }}</span>
      </td>
      <td style="width:21%;padding-left:6px;padding-right:6px;text-align:center">
        <div class="code-lbl">Registration no.</div>
        <div class="code">{{ $data->qr_code }}</div>
      </td>
      <td style="width:19%;padding-left:6px;text-align:right"><img class="qr" src="{{ $qr }}" style="width:88px;height:88px" alt="QR"></td>
    </tr>
  </table>

  <table class="cols">
    <tr>
      <td>
        <div class="box"><h3>Personal</h3>
          <table class="kv">
            {!! $row('Full name', trim($data->title . ' ' . $data->firstname . ' ' . $data->lastname)) !!}
            {!! $row('Gender', $data->gender) !!}
            {!! $row('Date of birth', $data->birthday) !!}
            {!! $row('Country', $data->country) !!}
            {!! $row('Email', $data->email) !!}
            {!! $row('Phone', $data->phone) !!}
          </table>
        </div>
        <div class="box"><h3>Identification</h3>
          <table class="kv">
            {!! $row('ID type', $data->identification_type) !!}
            {!! $row('ID number', $data->identification_no) !!}
          </table>
        </div>
      </td>
      <td>
        <div class="box"><h3>Professional</h3>
          <table class="kv">
            {!! $row('Profession', $data->profession) !!}
            {!! $row('Speciality', $data->specialty) !!}
            {!! $row('Designation', $data->designation) !!}
            {!! $row('Institution', $data->institute) !!}
            {!! $row('Institution address', $data->institute_address) !!}
          </table>
        </div>
        <div class="box"><h3>Registration</h3>
          <table class="kv">
            {!! $row('Event', $data->venue) !!}
            {!! $row('Registered on', $data->created_at ? \Carbon\Carbon::parse($data->created_at)->format('j F Y, H:i') : '') !!}
            {!! $row('Confirmation email', $data->email_notification === 'Yes' ? 'Sent' : 'Not sent') !!}
            {!! $row('Gift', $data->gift) !!}
            {!! $row('Verification link', $verifyUrl) !!}
          </table>
        </div>
      </td>
    </tr>
  </table>
</body>
</html>
