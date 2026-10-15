@php $event = \App\Support\EventSettings::get('event_short') ?: 'BICS'; @endphp
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in</title></head>
<body style="margin:0;background:#f4f7fc;font-family:Segoe UI,Arial,sans-serif;color:#101a2e">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fc;padding:24px 12px"><tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden">
    <tr><td style="background:#0a2350;padding:18px 24px;color:#ffffff;font-weight:700;font-size:16px">{{ $event }} · My profile</td></tr>
    <tr><td style="padding:24px">
      <p style="margin:0 0 12px;font-size:16px">Hello {{ $participant->first_name }},</p>
      <p style="margin:0 0 20px;line-height:1.5">Use the button to sign in to your participant profile. You can update your details, choose what is public, download your QR code and see your card.</p>
      <p style="margin:0 0 22px;text-align:center"><a href="{{ $link }}" style="display:inline-block;background:#0e4ca1;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 26px;border-radius:10px">Sign in to my profile</a></p>
      <p style="margin:0 0 6px;text-align:center;color:#6b7489;font-size:13px">Or enter this code on the sign-in page:</p>
      <p style="margin:0 0 22px;text-align:center;font-size:30px;font-weight:800;letter-spacing:8px;font-family:Consolas,monospace">{{ $code }}</p>
      <p style="margin:0;color:#6b7489;font-size:13px;line-height:1.5">The link and code work once and expire in {{ $minutes }} minutes. If you did not ask to sign in, you can ignore this email — nobody can sign in without it.</p>
    </td></tr>
    <tr><td style="padding:14px 24px;background:#f4f7fc;color:#6b7489;font-size:12px">Participant ID {{ $participant->code }}</td></tr>
  </table>
</td></tr></table>
</body></html>
