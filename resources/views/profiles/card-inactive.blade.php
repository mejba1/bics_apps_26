@php
   $lost = in_array($card->status, ['lost', 'damaged', 'deactivated'], true);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
   <title>{{ $lost ? 'Card not active' : 'Card not activated yet' }}</title>
   <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&display=swap">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
   <style>
      body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; font-family: "Plus Jakarta Sans", system-ui, sans-serif; background: #f4f7fc; color: #101a2e; }
      .card { max-width: 420px; width: 100%; text-align: center; background: #fff; border-radius: 24px; padding: 34px 28px; box-shadow: 0 30px 60px -30px rgba(10,35,80,.35); }
      .ic { font-size: 46px; color: {{ $lost ? '#b22e6b' : '#0e4ca1' }}; }
      h1 { font-size: 1.35rem; margin: 10px 0 8px; } p { color: #6b7489; line-height: 1.6; margin: 0 0 18px; }
      a.btn { display: inline-block; background: #0e4ca1; color: #fff; text-decoration: none; font-weight: 700; padding: 12px 20px; border-radius: 12px; margin: 4px; }
      a.alt { background: #fff; color: #0e4ca1; border: 2px solid #dbe5f5; }
      .staff { margin-top: 18px; padding-top: 16px; border-top: 1px dashed #dbe2ee; font-size: 14px; }
      .staff b { font-family: monospace; }
   </style>
</head>
<body>
   <div class="card">
      <div class="ic"><i class="bi {{ $lost ? 'bi-credit-card-2-front' : 'bi-hourglass-split' }}"></i></div>
      @if($lost)
      <h1>This card is no longer active</h1>
      <p>This NFC card has been switched off. If it is yours, please contact the event organiser for a replacement.</p>
      @else
      <h1>This card is not activated yet</h1>
      <p>The card works, but it has not been activated. It will open its owner's profile once the organiser activates it.</p>
      @endif
      <a class="btn alt" href="{{ url('/') }}">BICS website</a>
      @if($staff)
      <div class="staff"><i class="bi bi-person-badge"></i> Staff: this is card <b>{{ $card->card_no }}</b> ({{ \App\Models\NfcCard::STATUS[$card->status][0] }}).<br>
         <a class="btn" href="{{ route('nfc.show', $card->id) }}">Open card in admin</a></div>
      @endif
   </div>
</body>
</html>
