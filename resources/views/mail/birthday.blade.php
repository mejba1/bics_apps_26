@php
   /*
    | Birthday wish email (App\Support\Birthdays). Texts: Admin › Birthdays › Message. Colours / footer note: Admin › Email Template.
    | $body (with {placeholders} and **bold**), $vars, $card (JPEG bytes) — or $cardSrc (URL) for the admin preview.
    */
   use App\Support\EmailTemplate as T;
   $site = config('bics.public_url');
   $tpl = T::all();
   $brand = preg_match('/^#[0-9a-f]{6}$/i', $tpl['brand_color']) ? $tpl['brand_color'] : '#0e4ca1';
   $accent = preg_match('/^#[0-9a-f]{6}$/i', $tpl['accent_color']) ? $tpl['accent_color'] : '#b22e6b';
   $brandDark = T::shade($brand, -0.3);
   $font = "font-family:'Segoe UI',Helvetica,Arial,sans-serif;";
   $src = $cardSrc ?? (isset($message) ? $message->embedData($card, 'birthday-card.jpg', 'image/jpeg') : 'data:image/jpeg;base64,' . base64_encode($card));
   $html = nl2br(preg_replace('/\*\*(.+?)\*\*/s', '<b style="color:#1d2433">$1</b>', e(strtr($body, $vars))), false);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>{{ $subjectLine ?? 'Happy birthday' }}</title>
  <style>@media (max-width: 620px) { .wrap { width: 100% !important; } .px { padding-left: 22px !important; padding-right: 22px !important; } }</style>
</head>
<body style="margin:0;padding:0;background:#f6eff4;">
  <div style="display:none;max-height:0;overflow:hidden;">{{ strtr('Warm birthday wishes from everyone at BICS, {first_name}!', $vars) }}</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f6eff4;">
    <tr>
      <td align="center" style="padding:28px 12px;">
        <table role="presentation" class="wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 10px 34px rgba(90,20,60,.12);">
          <tr><td style="padding:0;"><img src="{{ $src }}" alt="Happy birthday, {{ $vars['{first_name}'] }}!" width="600" style="display:block;width:100%;max-width:600px;height:auto;border:0;"></td></tr>
          <tr><td class="px" style="padding:30px 40px 8px;font-size:16px;line-height:1.7;color:#4a5368;{{ $font }}">{!! $html !!}</td></tr>
          <tr>
            <td class="px" style="padding:14px 40px 30px;{{ $font }}">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                <td style="font-size:26px;line-height:1;padding-right:12px;">&#127874;</td>
                <td style="font-size:14px;color:#6b7385;line-height:1.5;">With warm wishes,<br><b style="color:#1d2433;">{{ \App\Support\Birthdays::settings()['signature'] }}</b></td>
              </tr></table>
            </td>
          </tr>
          <tr><td style="height:5px;line-height:5px;font-size:0;background:{{ $accent }};background-image:linear-gradient(90deg,{{ $brand }},{{ $accent }},#f2b84b);">&nbsp;</td></tr>
          <tr>
            <td class="px" style="background:{{ $brandDark }};padding:18px 40px;font-size:12.5px;line-height:1.7;color:#c3d0e6;{{ $font }}">
              <b style="color:#ffffff;">Beacon International Cancer Summit</b> · <a href="{{ $site }}" style="color:#ffffff;">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($site, '/')) }}</a>
              @if(trim($tpl['footer_note']) !== '')<br>{{ T::text($tpl['footer_note'], $vars) }}@endif
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
