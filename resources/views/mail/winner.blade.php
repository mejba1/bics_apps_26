@php
   /*
    | Raffle winner email. Texts: Raffle setup > Winner email (App\Support\WinnerEmail).
    | Colours, footer note: Admin > Email Template. Contact details: site footer.
    | Variables: $t (settings), $vars (placeholders), $prize, $prizeTitle.
    */
   use App\Support\EmailTemplate as T;
   $site = config('bics.public_url');
   $tpl = T::all();
   $brand = preg_match('/^#[0-9a-f]{6}$/i', $tpl['brand_color']) ? $tpl['brand_color'] : '#0e4ca1';
   $accent = preg_match('/^#[0-9a-f]{6}$/i', $tpl['accent_color']) ? $tpl['accent_color'] : '#b22e6b';
   $brandDark = T::shade($brand, -0.3);
   $brandTint = T::shade($brand, 0.9);
   $footer = \App\Models\Page::globalContent('footer') ?? \App\Support\PageSeed::footer();
   $emails = \App\Support\Cms::lines($footer['emails'] ?? '');
   $phones = \App\Support\Cms::lines($footer['phones'] ?? '');
   $collect = T::steps($t['collect'] ?? '', $vars);
   $font = "font-family:'Segoe UI',Helvetica,Arial,sans-serif;";
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="color-scheme" content="light">
  <title>{{ T::text($t['subject'], $vars) }}</title>
  <style>
    @media (max-width: 620px) {
      .wrap { width: 100% !important; }
      .px { padding-left: 22px !important; padding-right: 22px !important; }
      .h1 { font-size: 26px !important; }
      .prize { font-size: 24px !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background:#eef2f8;">
  <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">{{ T::text('You won the {prize_title} ({prize}) at {event_short}.', $vars) }}</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2f8;">
    <tr>
      <td align="center" style="padding:28px 12px;">
        <table role="presentation" class="wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(14,40,90,.12);">
          <tr><td style="height:6px;line-height:6px;font-size:0;background:{{ $brand }};background-image:linear-gradient(90deg,{{ $brand }},{{ $accent }},#4f8a2e,#519cbb);">&nbsp;</td></tr>
          <tr>
            <td class="px" align="center" style="padding:26px 40px 6px;">
              <img src="{{ $site }}/assets/frontend/images/logo.png" alt="BICS - Beacon International Cancer Summit" width="160" style="display:block;width:160px;height:auto;border:0;">
            </td>
          </tr>

          {{-- Trophy + heading --}}
          <tr>
            <td class="px" align="center" style="padding:14px 40px 4px;{{ $font }}">
              <div style="font-size:52px;line-height:1;">&#127942;</div>
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:14px;"><tr>
                <td style="background:#fff4d6;color:#8a5a07;border-radius:999px;padding:7px 16px;font-size:13px;font-weight:700;{{ $font }}">{{ T::text('{event_short} raffle draw', $vars) }}</td>
              </tr></table>
              @if(trim($t['heading']) !== '')<h1 class="h1" style="margin:16px 0 10px;font-size:30px;line-height:1.2;color:#1d2433;font-weight:800;{{ $font }}">{{ T::text($t['heading'], $vars) }}</h1>@endif
              @if(trim($t['intro']) !== '')<p style="margin:0;font-size:16px;line-height:1.65;color:#4a5368;{{ $font }}">{!! T::html($t['intro'], $vars) !!}</p>@endif
            </td>
          </tr>

          {{-- Prize "ticket" --}}
          <tr>
            <td class="px" style="padding:24px 40px 8px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{{ $brand }};background-image:linear-gradient(120deg,{{ $brandDark }},{{ $brand }} 50%,{{ $accent }});border-radius:14px;">
                <tr>
                  <td align="center" style="padding:24px 16px;{{ $font }}">
                    <div style="font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#ffe2a8;font-weight:800;{{ $font }}">{{ $prizeTitle }}</div>
                    <div class="prize" style="font-size:30px;line-height:1.25;font-weight:800;color:#ffffff;margin:8px 0 6px;{{ $font }}">{{ $prize }}</div>
                    <div style="font-size:13px;color:#e6eeff;{{ $font }}">Winning number <strong style="color:#ffffff;letter-spacing:2px;">{{ $vars['{pin}'] ?? '' }}</strong></div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          {{-- How to collect --}}
          @if($collect)
          <tr>
            <td class="px" style="padding:22px 40px 6px;{{ $font }}">
              <div style="font-size:16px;font-weight:800;color:#1d2433;margin-bottom:10px;">How to collect your prize</div>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                @foreach($collect as $text)
                <tr>
                  <td width="34" valign="top" style="padding:4px 0;">
                    <div style="width:24px;height:24px;line-height:24px;border-radius:12px;background:{{ $brandTint }};color:{{ $brand }};font-weight:800;font-size:13px;text-align:center;{{ $font }}">{{ $loop->iteration }}</div>
                  </td>
                  <td valign="top" style="padding:5px 0 8px;font-size:14.5px;line-height:1.55;color:#4a5368;{{ $font }}">{!! $text !!}</td>
                </tr>
                @endforeach
              </table>
            </td>
          </tr>
          @endif

          @if(trim($t['closing'] ?? '') !== '')
          <tr><td class="px" style="padding:14px 40px 24px;font-size:15px;line-height:1.6;color:#4a5368;{{ $font }}">{!! T::html($t['closing'], $vars) !!}</td></tr>
          @else
          <tr><td style="height:20px;line-height:20px;font-size:0;">&nbsp;</td></tr>
          @endif

          {{-- Footer --}}
          <tr>
            <td class="px" style="background:{{ $brandDark }};padding:24px 40px;{{ $font }}">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="font-size:13px;line-height:1.7;color:#dbe4f3;{{ $font }}">
                    <strong style="color:#ffffff;font-size:14px;">Questions about your prize?</strong><br>
                    @foreach($emails as $e)<a href="mailto:{{ $e }}" style="color:#ffffff;text-decoration:underline;">{{ $e }}</a>@if(!$loop->last) &nbsp;·&nbsp; @endif @endforeach
                    @if($phones)<br>{{ implode('  ·  ', $phones) }}@endif
                  </td>
                </tr>
                <tr>
                  <td style="padding-top:14px;font-size:12px;color:#c3d0e6;border-top:1px solid rgba(255,255,255,.18);{{ $font }}">
                    <a href="{{ $site }}" style="color:#ffffff;text-decoration:none;font-weight:700;">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($site, '/')) }}</a>
                    @if(trim($tpl['footer_note']) !== '') &nbsp;·&nbsp; {{ T::text($tpl['footer_note'], $vars) }}@endif
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
        <p style="margin:16px 0 0;font-size:12px;color:#8a93a6;{{ $font }}">{{ T::text('You received this email because your registration number was drawn in the {event_short} raffle.', $vars) }}</p>
      </td>
    </tr>
  </table>
</body>
</html>
