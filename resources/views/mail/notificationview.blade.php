@php
   /*
    | Registration confirmation email. Texts, colours and banner: Admin > Email Template (App\Support\EmailTemplate).
    | Variables: $title, $name (last name), $firstName, $pin, $profession, $qrcode (relative path of the verification card PDF),
    |            $attachments (titles of the attached files), optional $tpl (unsaved texts, for the live preview).
    | Event details come from Admin > Events (current event), contact details from the site footer.
    | Images and links use the public website address (config bics.public_url), never localhost.
    */
   use App\Support\EventSettings as Ev;
   use App\Support\EmailTemplate as T;
   $t = array_merge(T::all(), $tpl ?? []);
   $on = fn ($k) => !empty($t[$k]) && $t[$k] !== '0';
   $site = config('bics.public_url');
   $eventName = Ev::get('event_name');
   $venue = Ev::get('event_venue');
   $dates = Ev::datesLabel();
   $start = Ev::date('event_start');
   $cardUrl = !empty($qrcode) ? $site . '/' . ltrim($qrcode, '/') : null;
   $vars = T::vars(['title' => $title ?? '', 'first_name' => $firstName ?? '', 'last_name' => $name ?? '', 'pin' => $pin ?? '', 'profession' => $profession ?? ''], $attachments ?? []);
   $steps = T::steps($t['steps'], $vars);
   $brand = preg_match('/^#[0-9a-f]{6}$/i', $t['brand_color']) ? $t['brand_color'] : '#0e4ca1';
   $accent = preg_match('/^#[0-9a-f]{6}$/i', $t['accent_color']) ? $t['accent_color'] : '#b22e6b';
   $brandDark = T::shade($brand, -0.3);
   $brandTint = T::shade($brand, 0.9);
   $banner = $t['banner'] ? (preg_match('#^https?://#', $t['banner']) ? $t['banner'] : $site . '/' . ltrim($t['banner'], '/')) : null;
   $footer = \App\Models\Page::globalContent('footer') ?? \App\Support\PageSeed::footer();
   $emails = \App\Support\Cms::lines($footer['emails'] ?? '');
   $phones = \App\Support\Cms::lines($footer['phones'] ?? '');
   $font = "font-family:'Segoe UI',Helvetica,Arial,sans-serif;";
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="color-scheme" content="light">
  <title>{{ T::text($t['subject'], $vars) }}</title>
  <!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
  <style>
    @media (max-width: 620px) {
      .wrap { width: 100% !important; }
      .px { padding-left: 22px !important; padding-right: 22px !important; }
      .pin { font-size: 34px !important; letter-spacing: 6px !important; }
      .col { display: block !important; width: 100% !important; border-left: 0 !important; }
      .h1 { font-size: 24px !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background:#eef2f8;">
  {{-- Inbox preview text --}}
  @if(trim($t['preheader']) !== '')<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">{{ T::text($t['preheader'], $vars) }}</div>@endif

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2f8;">
    <tr>
      <td align="center" style="padding:28px 12px;">
        <table role="presentation" class="wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(14,40,90,.12);">

          {{-- Brand stripe + logo --}}
          <tr><td style="height:6px;line-height:6px;font-size:0;background:{{ $brand }};background-image:linear-gradient(90deg,{{ $brand }},{{ $accent }},#4f8a2e,#519cbb);">&nbsp;</td></tr>
          <tr>
            <td class="px" align="center" style="padding:26px 40px 10px;">
              <img src="{{ $site }}/assets/frontend/images/logo.png" alt="BICS - Beacon International Cancer Summit" width="170" style="display:block;width:170px;height:auto;border:0;">
            </td>
          </tr>

          {{-- Confirmation heading --}}
          <tr>
            <td class="px" align="center" style="padding:14px 40px 6px;{{ $font }}">
              @if(trim($t['badge']) !== '')
              <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                <td style="background:#e8f6e4;color:#2f6b1c;border-radius:999px;padding:7px 16px;font-size:13px;font-weight:700;{{ $font }}">&#10003;&nbsp; {{ T::text($t['badge'], $vars) }}</td>
              </tr></table>
              @endif
              @if(trim($t['heading']) !== '')<h1 class="h1" style="margin:18px 0 8px;font-size:28px;line-height:1.25;color:#1d2433;font-weight:800;{{ $font }}">{{ T::text($t['heading'], $vars) }}</h1>@endif
              @if(trim($t['intro']) !== '')<p style="margin:0;font-size:16px;line-height:1.6;color:#4a5368;{{ $font }}">{!! T::html($t['intro'], $vars) !!}</p>@endif
            </td>
          </tr>

          {{-- Registration number "ticket" --}}
          <tr>
            <td class="px" style="padding:24px 40px 8px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{{ $brand }};background-image:linear-gradient(120deg,{{ $brandDark }},{{ $brand }} 55%,{{ $accent }});border-radius:14px;">
                <tr>
                  <td align="center" style="padding:22px 16px;{{ $font }}">
                    <div style="font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#e6eeff;font-weight:700;{{ $font }}">{{ T::text($t['pin_label'], $vars) }}</div>
                    <div class="pin" style="font-size:42px;letter-spacing:10px;font-weight:800;color:#ffffff;margin:6px 0 4px;padding-left:10px;{{ $font }}">{{ $pin }}</div>
                    @if(trim($t['pin_note']) !== '')<div style="font-size:13px;color:#f0f4ff;{{ $font }}">{{ T::text($t['pin_note'], $vars) }}</div>@endif
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          {{-- Event details --}}
          @if($on('show_event'))
          <tr>
            <td class="px" style="padding:18px 40px 4px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e3e8f0;border-radius:12px;">
                <tr>
                  <td class="col" width="50%" valign="top" style="padding:16px 18px;{{ $font }}">
                    <div style="font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#8a93a6;font-weight:700;">&#128197; Date</div>
                    <div style="font-size:15px;color:#1d2433;font-weight:700;margin-top:4px;">{{ $dates }}</div>
                    @if($start)<div style="font-size:13px;color:#6b7385;margin-top:2px;">Starts {{ $start->format('H:i') }} ({{ $start->format('T') }})</div>@endif
                  </td>
                  <td class="col" width="50%" valign="top" style="padding:16px 18px;border-left:1px solid #e3e8f0;{{ $font }}">
                    <div style="font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#8a93a6;font-weight:700;">&#128205; Venue</div>
                    <div style="font-size:15px;color:#1d2433;font-weight:700;margin-top:4px;">{{ $venue }}</div>
                  </td>
                </tr>
                @if($on('show_profession') && !empty($profession))
                <tr>
                  <td colspan="2" style="padding:12px 18px;border-top:1px solid #e3e8f0;font-size:14px;color:#4a5368;{{ $font }}">
                    Registered as: <strong style="color:#1d2433;">{{ $profession }}</strong>
                  </td>
                </tr>
                @endif
              </table>
            </td>
          </tr>
          @endif

          {{-- Call to action --}}
          @if($cardUrl && $on('show_button') && trim($t['button_text']) !== '')
          @php $btn = T::text($t['button_text'], $vars); @endphp
          <tr>
            <td class="px" align="center" style="padding:24px 40px 6px;">
              <!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{{ $cardUrl }}" style="height:48px;v-text-anchor:middle;width:300px;" arcsize="25%" stroke="f" fillcolor="{{ $brand }}"><center style="color:#ffffff;font-family:Arial;font-size:16px;font-weight:bold;">{{ $btn }}</center></v:roundrect><![endif]-->
              <!--[if !mso]><!-->
              <a href="{{ $cardUrl }}" target="_blank" style="display:inline-block;background:{{ $brand }};color:#ffffff;text-decoration:none;font-weight:700;font-size:16px;padding:14px 28px;border-radius:12px;{{ $font }}">&#11015;&nbsp; {{ $btn }}</a>
              <!--<![endif]-->
            </td>
          </tr>
          @endif

          {{-- What's next --}}
          @if($steps)
          <tr>
            <td class="px" style="padding:22px 40px 8px;{{ $font }}">
              @if(trim($t['steps_title']) !== '')<div style="font-size:16px;font-weight:800;color:#1d2433;margin-bottom:10px;">{{ T::text($t['steps_title'], $vars) }}</div>@endif
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                @foreach($steps as $text)
                <tr>
                  <td width="34" valign="top" style="padding:4px 0;">
                    <div style="width:24px;height:24px;line-height:24px;border-radius:12px;background:{{ $brandTint }};color:{{ $brand }};font-weight:800;font-size:13px;text-align:center;{{ $font }}">{{ $loop->iteration }}</div>
                  </td>
                  <td valign="top" style="padding:5px 0 8px;font-size:14.5px;line-height:1.5;color:#4a5368;{{ $font }}">{!! $text !!}</td>
                </tr>
                @endforeach
              </table>
            </td>
          </tr>
          @endif

          {{-- Closing paragraph --}}
          @if(trim($t['closing']) !== '')
          <tr>
            <td class="px" style="padding:14px 40px 4px;font-size:15px;line-height:1.6;color:#4a5368;{{ $font }}">{!! T::html($t['closing'], $vars) !!}</td>
          </tr>
          @endif

          {{-- Event banner --}}
          @if($on('show_banner') && $banner)
          <tr>
            <td style="padding:18px 0 0;">
              <img src="{{ $banner }}" alt="{{ $eventName }}" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;">
            </td>
          </tr>
          @else
          <tr><td style="height:22px;line-height:22px;font-size:0;">&nbsp;</td></tr>
          @endif

          {{-- Footer --}}
          <tr>
            <td class="px" style="background:{{ $brandDark }};padding:24px 40px;{{ $font }}">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="font-size:13px;line-height:1.7;color:#dbe4f3;{{ $font }}">
                    @if(trim($t['footer_title']) !== '')<strong style="color:#ffffff;font-size:14px;">{{ T::text($t['footer_title'], $vars) }}</strong><br>@endif
                    @foreach($emails as $e)<a href="mailto:{{ $e }}" style="color:#ffffff;text-decoration:underline;">{{ $e }}</a>@if(!$loop->last) &nbsp;·&nbsp; @endif @endforeach
                    @if($phones)<br>{{ implode('  ·  ', $phones) }}@endif
                  </td>
                </tr>
                <tr>
                  <td style="padding-top:14px;font-size:12px;color:#c3d0e6;border-top:1px solid rgba(255,255,255,.18);{{ $font }}">
                    <a href="{{ $site }}" style="color:#ffffff;text-decoration:none;font-weight:700;">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($site, '/')) }}</a>
                    @if(!empty($footer['facebook']) && $footer['facebook'] !== '#') &nbsp;·&nbsp; <a href="{{ $footer['facebook'] }}" style="color:#ffffff;text-decoration:none;">Facebook</a>@endif
                    @if(trim($t['footer_note']) !== '') &nbsp;·&nbsp; {{ T::text($t['footer_note'], $vars) }}@endif
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        @if(trim($t['bottom_note']) !== '')<p style="margin:16px 0 0;font-size:12px;color:#8a93a6;{{ $font }}">{{ T::text($t['bottom_note'], $vars) }}</p>@endif
      </td>
    </tr>
  </table>
</body>
</html>
