<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <meta name="robots" content="noindex">
   <title>Raffle draw · Not assigned</title>
   <link rel="icon" href="{{ asset('assets/frontend/images/logo.png') }}">
   <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
   <style>
      body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; font-family: "Plus Jakarta Sans", system-ui, sans-serif; color: #fff;
         background: radial-gradient(900px 500px at 80% 0%, rgba(178,46,107,.4), transparent 60%), linear-gradient(160deg, #06142f, #0a2350); }
      .card { max-width: 520px; width: 100%; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.15); border-radius: 24px; padding: 36px 32px; text-align: center; }
      .icon { width: 72px; height: 72px; border-radius: 22px; margin: 0 auto 18px; display: flex; align-items: center; justify-content: center; font-size: 32px; background: linear-gradient(135deg, #b22e6b, #e0548f); }
      h1 { font-size: 1.6rem; margin: 0 0 10px; }
      p { color: rgba(255,255,255,.75); line-height: 1.6; margin: 0 0 14px; }
      .hosts { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin: 6px 0 22px; }
      .hosts span { background: rgba(255,255,255,.1); border-radius: 999px; padding: 6px 12px; font-weight: 600; font-size: 14px; }
      a { display: inline-flex; align-items: center; gap: 8px; color: #0e4ca1; background: #fff; text-decoration: none; font-weight: 800; padding: 13px 22px; border-radius: 999px; }
   </style>
</head>
<body>
   <div class="card">
      <div class="icon"><i class="bi bi-shield-lock"></i></div>
      @if(!empty($locked))
      <h1>The draw is closed</h1>
         <p>{{ \App\Support\EventLock::reason($event) }} Its raffle is read-only now — winners can still be seen and emailed in <b>Prize Winners</b>.</p>
      @else
      <h1>Only assigned hosts can run the draw</h1>
      @endif
      @if(!empty($locked))
      @elseif(!$event)
         <p>There is no current event yet. Create one in Admin › Events.</p>
      @elseif($hosts->isEmpty())
         <p>Nobody is assigned to host the {{ $event->event_short }} raffle yet. A Super Admin can assign hosts in <b>Raffle setup</b>.</p>
      @else
         <p>The {{ $event->event_short }} raffle can be started by:</p>
         <div class="hosts">@foreach($hosts as $h)<span><i class="bi bi-person-badge"></i> {{ $h->name }}</span>@endforeach</div>
         <p>Ask a Super Admin if you should be added.</p>
      @endif
      <a href="{{ url('/home') }}"><i class="bi bi-arrow-left"></i> Back to the admin panel</a>
   </div>
</body>
</html>
