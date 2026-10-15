<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Your registration code</title></head>
<body style="margin:0;background:#f4f7fc;font-family:Segoe UI,Arial,sans-serif;color:#101a2e">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fc;padding:24px 12px"><tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;overflow:hidden">
    <tr><td style="background:#0a2350;padding:18px 24px;color:#ffffff;font-weight:700;font-size:16px">{{ $eventName }} · Registration</td></tr>
    <tr><td style="padding:24px">
      <p style="margin:0 0 12px;font-size:16px">Hello {{ $participant->first_name }},</p>
      <p style="margin:0 0 20px;line-height:1.5">Welcome back! Enter this code on the registration page to register for <b>{{ $eventName }}</b> with the details you used before.</p>
      <p style="margin:0 0 22px;text-align:center;font-size:34px;font-weight:800;letter-spacing:10px;font-family:Consolas,monospace;color:#0e4ca1">{{ $code }}</p>
      <p style="margin:0;color:#6b7489;font-size:13px;line-height:1.5">The code works once and expires in {{ $minutes }} minutes. If you did not ask for it, you can ignore this email — nothing changes without it.</p>
    </td></tr>
    <tr><td style="padding:14px 24px;background:#f4f7fc;color:#6b7489;font-size:12px">Participant ID {{ $participant->code }}</td></tr>
  </table>
</td></tr></table>
</body></html>
