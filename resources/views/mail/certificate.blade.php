@php
   /*
    | Certificate of participation email. Texts: Admin > Certificates > Email (App\Support\Certificate).
    | Colours / footer note: Admin > Email Template. Variables: $t (settings), $vars, $link, $event.
    */
   use App\Support\EmailTemplate as T;
   use App\Support\Certificate as C;
   $site = config('bics.public_url');
   $tpl = T::all();
   $brand = preg_match('/^#[0-9a-f]{6}$/i', $tpl['brand_color']) ? $tpl['brand_color'] : '#0e4ca1';
   $accent = preg_match('/^#[0-9a-f]{6}$/i', $tpl['accent_color']) ? $tpl['accent_color'] : '#b22e6b';
   $brandDark = T::shade($brand, -0.3);
   $footer = \App\Models\Page::globalContent('footer') ?? \App\Support\PageSeed::footer();
   $emails = \App\Support\Cms::lines($footer['emails'] ?? '');
   $font = "font-family:'Segoe UI',Helvetica,Arial,sans-serif;";
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>{{ T::text($t['subject'], $vars) }}</title>
  <style>@media (max-width: 620px) { .wrap { width: 100% !important; } .px { padding-left: 22px !important; padding-right: 22px !important; } }</style>
</head>
<body style="margin:0;padding:0;background:#eef2f8;">
  <div style="display:none;max-height:0;overflow:hidden;">{{ T::text('Your certificate of participation for {event_short} (No. {certificate_no}).', $vars) }}</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2f8;">
    <tr>
      <td align="center" style="padding:28px 12px;">
        <table role="presentation" class="wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(14,40,90,.12);">
          <tr><td style="height:6px;line-height:6px;font-size:0;background:{{ $brand }};background-image:linear-gradient(90deg,{{ $brand }},{{ $accent }},#4f8a2e,#519cbb);">&nbsp;</td></tr>
          <tr><td class="px" align="center" style="padding:26px 40px 6px;"><img src="{{ $site }}/assets/frontend/images/logo.png" alt="BICS" width="160" style="display:block;width:160px;height:auto;border:0;"></td></tr>
          <tr>
            <td class="px" align="center" style="padding:16px 40px 4px;{{ $font }}">
              <div style="font-size:46px;line-height:1;">&#127891;</div>
              <h1 style="margin:16px 0 6px;font-size:26px;line-height:1.25;color:#1d2433;font-weight:800;{{ $font }}">Certificate of participation</h1>
              <div style="font-size:14px;color:#6b7385;{{ $font }}">{{ $vars['{event_name}'] }}</div>
            </td>
          </tr>
          <tr><td class="px" style="padding:20px 40px 6px;font-size:15.5px;line-height:1.65;color:#4a5368;{{ $font }}">{!! C::html($t['body'], $vars, '#1d2433') !!}</td></tr>
          <tr>
            <td class="px" style="padding:18px 40px 4px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e3e8f0;border-radius:12px;">
                <tr>
                  <td style="padding:14px 18px;{{ $font }}">
                    <div style="font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#8a93a6;font-weight:700;">Certificate number</div>
                    <div style="font-size:17px;color:#1d2433;font-weight:800;margin-top:3px;letter-spacing:1px;">{{ $vars['{certificate_no}'] }}</div>
                  </td>
                  <td style="padding:14px 18px;border-left:1px solid #e3e8f0;{{ $font }}">
                    <div style="font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#8a93a6;font-weight:700;">Issued</div>
                    <div style="font-size:15px;color:#1d2433;font-weight:700;margin-top:3px;">{{ $vars['{issue_date}'] }}</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td class="px" align="center" style="padding:24px 40px 28px;">
              <a href="{{ $link }}" target="_blank" style="display:inline-block;background:{{ $brand }};color:#ffffff;text-decoration:none;font-weight:700;font-size:16px;padding:14px 28px;border-radius:12px;{{ $font }}">&#11015;&nbsp; Download / verify your certificate</a>
            </td>
          </tr>
          <tr>
            <td class="px" style="background:{{ $brandDark }};padding:22px 40px;font-size:13px;line-height:1.7;color:#dbe4f3;{{ $font }}">
              <strong style="color:#ffffff;">Questions?</strong>
              @foreach($emails as $e) <a href="mailto:{{ $e }}" style="color:#ffffff;">{{ $e }}</a>@if(!$loop->last) · @endif @endforeach
              <br><span style="font-size:12px;color:#c3d0e6;">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($site, '/')) }}@if(trim($tpl['footer_note']) !== '') · {{ T::text($tpl['footer_note'], $vars) }}@endif</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
