@php
   /* Public participant profile (/p/{code}). Only fields marked public are shown: App\Models\Participant::shown(). */
   $designation = $p->shown('designation');
   $org = $p->shown('organization');
   $dept = $p->shown('department');
   if ($dept && $designation && strcasecmp($dept, $designation) === 0) $dept = null;   // no repeats
   $category = $p->shown('category');
   if ($category && $designation && strcasecmp($category, $designation) === 0) $category = null;
   $country = $p->shown('country');
   $email = $p->shown('email');
   $mobile = $p->shown('mobile');
   $phone = $p->shown('phone');
   $wa = $p->shown('whatsapp');
   $waNumber = $wa ? preg_replace('/\D/', '', $wa) : null;
   $address = $p->shown('address');
   $map = $p->shown('map_url') ?: ($address ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address . ($country ? ', ' . $country : '')) : null);
   $website = $p->shown('website');
   $socials = $p->publicSocials();
   $bio = $p->shown('bio');
   $expertise = $p->shown('expertise') ? array_values(array_filter(array_map('trim', preg_split('/[\n,;]+/', $p->shown('expertise'))))) : [];
   $custom = $p->publicCustomFields();
   $logo = $p->shown('logo');
   $logo = $logo && is_file(base_path($logo)) ? asset($logo) : null;
   $photo = $p->photo && is_file(base_path($p->photo)) ? \App\Support\Thumb::url($p->photo, 800) : null;
   $url = $p->shareUrl();
   $tel = fn ($n) => 'tel:' . preg_replace('/[^0-9+]/', '', $n);
   $subtitle = collect([$designation, $org])->filter()->implode(' · ');
   $eventShort = \App\Support\EventSettings::get('event_short') ?: 'BICS';
   $eventName = \App\Support\EventSettings::get('event_name') ?: 'Beacon International Cancer Summit';
   // "Attending BICS 2026" when the participant is registered for an event that has not ended yet
   $upcoming = $events->first(function ($e) { $d = $e->dateOf('event_end') ?: $e->dateOf('event_start'); return $d && $d->endOfDay()->isFuture(); });
   $actions = array_values(array_filter([
      ($mobile || $phone) ? ['call', $tel($mobile ?: $phone), 'bi-telephone-fill', 'Call', false] : null,
      $waNumber ? ['whatsapp', 'https://wa.me/' . $waNumber, 'bi-whatsapp', 'WhatsApp', true] : null,
      $email ? ['email', 'mailto:' . $email, 'bi-envelope-fill', 'Email', false] : null,
      $website ? ['website', $website, 'bi-globe2', 'Website', true] : null,
      $map ? ['map', $map, 'bi-geo-alt-fill', 'Directions', true] : null,
   ]));
   $hasContact = $email || $mobile || $phone || $wa || $address || $website || $custom;
   // location card: address text, Google Maps links and a map that loads only when the visitor asks for it
   $place = $address ? trim($address . ($country && stripos($address, $country) === false ? ', ' . $country : '')) : null;
   $mapUrl = $p->shown('map_url');
   $coords = $mapUrl && preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $mapUrl, $mm) ? $mm[1] . ',' . $mm[2] : null;
   $mapQuery = $place ?: $coords;
   $mapOpen = $mapUrl ?: ($mapQuery ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mapQuery) : null);
   $directions = $mapQuery ? 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($mapQuery) : $mapUrl;
   $mapEmbed = $mapQuery ? 'https://www.google.com/maps?q=' . urlencode($mapQuery) . '&z=15&output=embed' : null;
   $hasLocation = $place || $mapUrl;
   $tiles = array_values(array_filter([
      $email ? ['email', 'mailto:' . $email, 'bi-envelope-fill', 'Email', $email, 'Send an email', $email, false] : null,
      $mobile ? ['call', $tel($mobile), 'bi-phone-fill', 'Mobile', $mobile, 'Call now', $mobile, false] : null,
      $phone ? ['call', $tel($phone), 'bi-telephone-fill', 'Office phone', $phone, 'Call now', $phone, false] : null,
      $waNumber ? ['whatsapp', 'https://wa.me/' . $waNumber, 'bi-whatsapp', 'WhatsApp', $wa, 'Start a chat', $wa, true] : null,
      $website ? ['website', $website, 'bi-globe2', 'Website', preg_replace('#^https?://(www\.)?#', '', rtrim($website, '/')), 'Visit website', $website, true] : null,
   ]));
   $bioLong = $bio && mb_strlen($bio) > 380;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
   <meta name="robots" content="noindex, nofollow">
   <meta name="theme-color" content="#0a2350">
   <title>{{ $p->name }}{{ $subtitle ? ' — ' . $subtitle : '' }}</title>
   <meta name="description" content="{{ $subtitle ?: $p->name }} · {{ $eventShort }} participant">
   <meta property="og:type" content="profile">
   <meta property="og:title" content="{{ $p->name }}">
   <meta property="og:description" content="{{ $subtitle ?: $eventShort . ' participant' }}">
   <meta property="og:url" content="{{ $url }}">
   @if($photo)<meta property="og:image" content="{{ $photo }}">@endif
   <meta name="twitter:card" content="summary">
   <link rel="icon" href="{{ asset('assets/frontend/images/logo.png') }}">
   <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
   <style>
      :root {
         --blue: #0e4ca1; --blue-2: #2563d0; --navy: #071a3d; --magenta: #b22e6b; --green: #2f8a4c; --teal: #1f8fb3; --gold: #e8a317;
         --ink: #0f1a2e; --text: #3a4458; --muted: #6b7489; --line: #e6eaf2; --soft: #f3f6fb; --card: #ffffff; --chip: #eef3fb;
         --shadow: 0 1px 2px rgba(10,26,61,.05), 0 18px 40px -24px rgba(10,26,61,.35);
         --radius: 22px;
      }
      @media (prefers-color-scheme: dark) {
         :root { --ink: #eef2fa; --text: #c3cbdb; --muted: #8d97ab; --line: #233049; --soft: #0a1222; --card: #111b2f; --chip: #1a2640;
            --shadow: 0 1px 2px rgba(0,0,0,.3), 0 18px 40px -24px rgba(0,0,0,.8); }
      }
      * { box-sizing: border-box; }
      html, body { margin: 0; }
      body { font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", sans-serif; background: var(--soft); color: var(--text); -webkit-font-smoothing: antialiased; line-height: 1.5; }
      a { color: inherit; text-decoration: none; }
      button { font: inherit; }
      :focus-visible { outline: 3px solid #7fb0ff; outline-offset: 2px; border-radius: 10px; }
      .min-w-0 { min-width: 0; }
      .sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

      /* hero */
      .hero { position: relative; height: 230px; overflow: hidden; color: #fff;
         background: radial-gradient(600px 300px at 88% -10%, rgba(178,46,107,.75), transparent 60%), radial-gradient(520px 320px at -10% 110%, rgba(31,143,179,.55), transparent 60%), linear-gradient(135deg, #071a3d 0%, #0e4ca1 70%, #1d63c9 100%); }
      .hero::before { content: ''; position: absolute; inset: 0; background-image: radial-gradient(rgba(255,255,255,.13) 1px, transparent 1.2px); background-size: 18px 18px; mask-image: linear-gradient(180deg, #000, transparent 85%); -webkit-mask-image: linear-gradient(180deg, #000, transparent 85%); }
      .hero::after { content: ''; position: absolute; width: 420px; height: 420px; right: -140px; top: -210px; border-radius: 50%; border: 1px solid rgba(255,255,255,.14); box-shadow: 0 0 0 40px rgba(255,255,255,.03), 0 0 0 80px rgba(255,255,255,.025); }
      .topbar { position: relative; z-index: 2; max-width: 1120px; margin: 0 auto; display: flex; align-items: center; gap: 10px; padding: 16px 18px; padding-top: max(16px, env(safe-area-inset-top)); }
      .brand { background: #fff; border-radius: 12px; padding: 6px 10px; display: inline-flex; box-shadow: 0 8px 20px -10px rgba(0,0,0,.4); }
      .brand img { height: 24px; display: block; }
      .glass { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); color: #fff; border-radius: 12px; height: 38px; padding: 0 12px; display: inline-flex; align-items: center; gap: 7px; font-weight: 700; font-size: 13px; cursor: pointer; }
      .glass:hover { background: rgba(255,255,255,.22); }
      .glass.icon { width: 38px; padding: 0; justify-content: center; font-size: 15px; }
      .stripe { position: absolute; left: 0; right: 0; bottom: 0; display: flex; height: 4px; z-index: 2; } .stripe i { flex: 1; }
      .stripe i:nth-child(1) { background: #3b7be0; } .stripe i:nth-child(2) { background: var(--magenta); } .stripe i:nth-child(3) { background: #5d9b3a; } .stripe i:nth-child(4) { background: #3f97bf; }

      /* layout */
      .page { max-width: 1120px; margin: -120px auto 0; padding: 0 16px 40px; position: relative; z-index: 3; display: grid; grid-template-columns: 1fr; gap: 16px; }
      .card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid color-mix(in srgb, var(--line) 70%, transparent); }

      /* identity */
      .id { padding: 0 22px 22px; text-align: center; }
      .photo { width: 148px; height: 148px; margin: -2px auto 0; transform: translateY(-40px); margin-bottom: -26px; border-radius: 40px; padding: 4px; background: conic-gradient(from 210deg, #3b7be0, var(--magenta), #5d9b3a, #3f97bf, #3b7be0); box-shadow: 0 22px 40px -20px rgba(10,26,61,.7); }
      .photo img, .photo span { width: 100%; height: 100%; border-radius: 36px; border: 4px solid var(--card); object-fit: cover; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #e9eff9, #dbe6f7); color: var(--blue); font-size: 3rem; font-weight: 800; }
      h1 { font-size: 1.75rem; line-height: 1.15; margin: 0; font-weight: 800; color: var(--ink); letter-spacing: -.02em; }
      .verified { display: inline-flex; align-items: center; justify-content: center; vertical-align: 3px; margin-left: 4px; color: var(--blue-2); font-size: 1.05rem; }
      .role { color: var(--blue-2); font-weight: 700; margin-top: 6px; font-size: 1.02rem; }
      @media (prefers-color-scheme: dark) { .role, .verified { color: #8ab4ff; } }
      .org { margin-top: 10px; display: inline-flex; align-items: center; gap: 10px; max-width: 100%; padding: 8px 12px 8px 8px; border-radius: 14px; background: var(--soft); border: 1px solid var(--line); text-align: left; }
      .org .lg { width: 40px; height: 40px; border-radius: 10px; background: #fff; display: flex; align-items: center; justify-content: center; flex: none; overflow: hidden; border: 1px solid var(--line); }
      .org .lg img { max-width: 34px; max-height: 34px; object-fit: contain; }
      .org .lg i { color: var(--blue); font-size: 1.1rem; }
      .org b { color: var(--ink); font-size: 14px; display: block; line-height: 1.25; }
      .org small { color: var(--muted); font-size: 12.5px; display: block; line-height: 1.4; margin-top: 2px; }
      .chips { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin-top: 14px; }
      .chip { font-size: 12.5px; font-weight: 700; padding: 5px 11px; border-radius: 999px; background: var(--chip); color: var(--text); display: inline-flex; align-items: center; gap: 5px; }
      .chip.event { background: linear-gradient(135deg, #fde7f1, #fff1dd); color: #9a2459; }
      @media (prefers-color-scheme: dark) { .chip.event { background: rgba(178,46,107,.2); color: #ff9cc6; } }
      .chip.code { font-family: ui-monospace, Consolas, monospace; letter-spacing: .02em; }

      .save { margin-top: 18px; display: grid; grid-template-columns: 1fr auto; gap: 8px; }
      .btn { border: 0; cursor: pointer; font-weight: 800; border-radius: 16px; padding: 15px 18px; display: inline-flex; align-items: center; justify-content: center; gap: 9px; transition: transform .12s, box-shadow .2s, background .2s; }
      .btn:active { transform: scale(.97); }
      .btn-main { background: linear-gradient(135deg, var(--magenta), #d7458a); color: #fff; box-shadow: 0 16px 28px -14px rgba(178,46,107,.95); font-size: 1rem; }
      .btn-main:hover { box-shadow: 0 18px 32px -12px rgba(178,46,107,1); }
      .btn-ghost { background: var(--chip); color: var(--ink); width: 54px; font-size: 1.1rem; }
      .actions { display: grid; grid-template-columns: repeat(var(--n), 1fr); gap: 8px; margin-top: 10px; }
      .act { display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 12px 4px 10px; border-radius: 16px; background: var(--soft); border: 1px solid var(--line); font-size: 12px; font-weight: 700; color: var(--text); transition: transform .12s, background .2s, border-color .2s; }
      .act:hover { border-color: #b9cff1; background: color-mix(in srgb, var(--soft) 70%, #dbe7fb); }
      .act:active { transform: scale(.96); }
      .act i { width: 38px; height: 38px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #fff; background: var(--blue); }
      .act { min-width: 0; }
      .act .al { display: block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; line-height: 1.3; }
      /* five buttons: 3 + 2 so labels stay readable */
      .actions[data-n="5"] { grid-template-columns: repeat(6, 1fr); } .actions[data-n="5"] .act { grid-column: span 2; } .actions[data-n="5"] .act:nth-child(n+4) { grid-column: span 3; }
      .act.a-whatsapp i { background: #1fa855; } .act.a-email i { background: #d14a3a; } .act.a-website i { background: var(--teal); } .act.a-map i { background: #e08a12; }

      /* sections */
      .sec { padding: 20px 22px; }
      .sec h2 { display: flex; align-items: center; gap: 9px; font-size: 1rem; font-weight: 800; color: var(--ink); margin: 0 0 14px; }
      .sec h2 i { width: 32px; height: 32px; border-radius: 10px; background: var(--chip); color: var(--blue); display: inline-flex; align-items: center; justify-content: center; font-size: .95rem; }
      @media (prefers-color-scheme: dark) { .sec h2 i { color: #8ab4ff; } }
      .bio { margin: 0; color: var(--text); line-height: 1.75; white-space: pre-line; }
      .bio.clamp { display: -webkit-box; -webkit-line-clamp: 6; -webkit-box-orient: vertical; overflow: hidden; }
      .more { margin-top: 8px; background: none; border: 0; padding: 0; color: var(--blue-2); font-weight: 700; cursor: pointer; }
      .tags { display: flex; flex-wrap: wrap; gap: 8px; }
      .tags span { background: var(--chip); color: var(--ink); border-radius: 12px; padding: 8px 13px; font-weight: 600; font-size: 13.5px; }
      .rows { display: grid; }
      .row { display: flex; gap: 14px; align-items: center; padding: 12px 0; border-top: 1px solid var(--line); }
      .row:first-child { border-top: 0; padding-top: 0; } .row:last-child { padding-bottom: 0; }
      .row .ic { width: 42px; height: 42px; border-radius: 13px; display: flex; align-items: center; justify-content: center; background: var(--chip); color: var(--blue); flex: none; font-size: 1.1rem; }
      @media (prefers-color-scheme: dark) { .row .ic { color: #8ab4ff; } }
      .row .tx { min-width: 0; flex: 1; }
      .row .k { font-size: 12px; color: var(--muted); font-weight: 600; display: block; }
      .row .v { font-weight: 700; color: var(--ink); word-break: break-word; }
      .row a.v:hover { color: var(--blue-2); }
      .mini { width: 36px; height: 36px; border-radius: 11px; border: 1px solid var(--line); background: var(--card); color: var(--muted); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex: none; }
      .mini:hover { color: var(--blue-2); border-color: #b9cff1; }
      /* connect with me */
      .connect { position: relative; overflow: hidden; }
      .connect::before { content: ''; position: absolute; right: -80px; top: -80px; width: 220px; height: 220px; border-radius: 50%; background: radial-gradient(circle, rgba(178,46,107,.12), transparent 70%); pointer-events: none; }
      .conn-head { display: flex; gap: 12px; align-items: center; margin-bottom: 16px; position: relative; }
      .conn-head h2 { margin: 0; font-size: 1.15rem; }
      .conn-head p { margin: 2px 0 0; color: var(--muted); font-size: 13.5px; }
      .conn-badge { width: 46px; height: 46px; border-radius: 15px; flex: none; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.25rem; background: linear-gradient(135deg, var(--magenta), #e0578f); box-shadow: 0 12px 22px -12px rgba(178,46,107,.9); }
      .tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 10px; }
      .tile { position: relative; display: flex; align-items: stretch; border-radius: 18px; background: var(--soft); border: 1px solid var(--line); transition: transform .15s, box-shadow .2s, border-color .2s; }
      .tile:hover { transform: translateY(-2px); box-shadow: 0 14px 28px -20px rgba(10,26,61,.6); border-color: var(--tc-line, #b9cff1); }
      .tile-main { flex: 1; min-width: 0; display: flex; gap: 12px; align-items: center; padding: 14px; }
      .ti { width: 46px; height: 46px; border-radius: 14px; flex: none; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #fff; background: var(--tc, var(--blue)); box-shadow: 0 10px 18px -12px var(--tc, var(--blue)); }
      .tt { min-width: 0; display: flex; flex-direction: column; }
      .tt small { font-size: 11.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
      .tt b { color: var(--ink); font-size: 14.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .tt em { font-style: normal; font-size: 12.5px; font-weight: 700; color: var(--tc, var(--blue)); margin-top: 1px; }
      @media (prefers-color-scheme: dark) { .tt em { filter: brightness(1.5); } }
      .tile-copy { border: 0; background: none; color: var(--muted); width: 44px; flex: none; border-left: 1px solid var(--line); border-radius: 0 18px 18px 0; cursor: pointer; }
      .tile-copy:hover { color: var(--ink); background: color-mix(in srgb, var(--line) 50%, transparent); }
      .t-email { --tc: #d14a3a; --tc-line: #f1c2bb; } .t-call { --tc: #0e4ca1; } .t-whatsapp { --tc: #1fa855; --tc-line: #b4e6c8; } .t-website { --tc: #1f8fb3; --tc-line: #b3e0ee; }

      .loc { margin-top: 12px; border-radius: 20px; overflow: hidden; border: 1px solid var(--line); background: var(--card); display: grid; grid-template-columns: 1fr; }
      .map { position: relative; height: 190px; background: #e8eef6; overflow: hidden; }
      .map iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
      .map-art { position: absolute; inset: 0; background-color: #e6edf5; background-image: linear-gradient(rgba(255,255,255,.7) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.7) 1px, transparent 1px); background-size: 26px 26px; }
      @media (prefers-color-scheme: dark) { .map-art { background-color: #16233b; background-image: linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px); } .map { background: #16233b; } }
      .road { position: absolute; background: #fff; box-shadow: 0 0 0 1px rgba(10,26,61,.05); }
      @media (prefers-color-scheme: dark) { .road { background: #24324d; } }
      .road.r1 { left: -10%; right: -10%; top: 58%; height: 14px; transform: rotate(-8deg); }
      .road.r2 { top: -10%; bottom: -10%; left: 34%; width: 12px; transform: rotate(14deg); }
      .road.r3 { left: 55%; right: -10%; top: 22%; height: 9px; transform: rotate(22deg); }
      .park { position: absolute; left: 8%; top: 12%; width: 22%; height: 30%; border-radius: 14px; background: #cfe8d3; }
      @media (prefers-color-scheme: dark) { .park { background: #1c3a2a; } }
      .pin { position: absolute; left: 50%; top: 46%; transform: translate(-50%, -100%); font-size: 2.6rem; color: var(--magenta); filter: drop-shadow(0 8px 10px rgba(178,46,107,.45)); z-index: 2; line-height: 1; }
      .pulse { position: absolute; left: 50%; top: 46%; width: 18px; height: 8px; margin: -4px 0 0 -9px; border-radius: 50%; background: rgba(178,46,107,.35); }
      @media (prefers-reduced-motion: no-preference) { .pulse { animation: pulse 1.8s ease-out infinite; } @keyframes pulse { from { transform: scale(.6); opacity: 1; } to { transform: scale(3.2); opacity: 0; } } }
      .map-load { position: absolute; right: 12px; bottom: 12px; z-index: 3; border: 0; border-radius: 12px; padding: 9px 13px; font-weight: 800; font-size: 13px; background: var(--card); color: var(--ink); box-shadow: 0 10px 22px -12px rgba(10,26,61,.7); cursor: pointer; display: inline-flex; gap: 6px; align-items: center; }
      .map-load:hover { color: var(--blue-2); }
      .map.loaded .map-art, .map.loaded .map-load { display: none; }
      .loc-info { padding: 16px; }
      .loc-info small { font-size: 11.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); display: flex; align-items: center; gap: 6px; }
      .loc-info b { display: block; color: var(--ink); font-size: 15px; margin: 4px 0 12px; line-height: 1.4; }
      .loc-btns { display: flex; flex-wrap: wrap; gap: 8px; }
      .lb { display: inline-flex; align-items: center; gap: 7px; border-radius: 12px; padding: 10px 14px; font-weight: 700; font-size: 13.5px; background: var(--chip); color: var(--ink); border: 0; cursor: pointer; }
      .lb:hover { color: var(--blue-2); }
      .lb.primary { background: linear-gradient(135deg, #0e4ca1, #2563d0); color: #fff; box-shadow: 0 12px 22px -14px rgba(14,76,161,.9); }
      .lb.primary:hover { color: #fff; filter: brightness(1.08); }
      .lb.icon { padding: 10px 12px; }
      .extra { margin-top: 12px; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; }
      .extra div { background: var(--soft); border: 1px solid var(--line); border-radius: 14px; padding: 10px 12px; }
      .extra small { display: block; font-size: 11.5px; font-weight: 700; color: var(--muted); }
      .extra b { color: var(--ink); font-size: 14px; word-break: break-word; }
      .conn-save { margin-top: 12px; display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 18px; border: 1.5px dashed color-mix(in srgb, var(--magenta) 45%, var(--line)); color: var(--ink); transition: background .2s; }
      .conn-save:hover { background: color-mix(in srgb, var(--magenta) 6%, transparent); }
      .conn-save > span { width: 40px; height: 40px; border-radius: 12px; background: #fde7f1; color: var(--magenta); display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; flex: none; }
      @media (prefers-color-scheme: dark) { .conn-save > span { background: rgba(178,46,107,.2); color: #ff9cc6; } }
      .conn-save b { display: block; font-size: 14.5px; } .conn-save small { color: var(--muted); font-size: 12.5px; }
      .conn-save > i { margin-left: auto; color: var(--magenta); font-size: 1.1rem; }
      @media (max-width: 519px) { .tile-main { padding: 11px 12px; gap: 11px; } .ti { width: 42px; height: 42px; border-radius: 13px; font-size: 1.1rem; } .tiles { gap: 8px; } .map { height: 170px; } }
      @media (min-width: 640px) { .loc { grid-template-columns: 1.15fr 1fr; } .map { height: auto; min-height: 220px; } .loc-info { display: flex; flex-direction: column; justify-content: center; padding: 20px; } }
      .socials { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 8px; }
      .social { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 14px; border: 1px solid var(--line); font-weight: 700; font-size: 14px; color: var(--ink); transition: transform .12s, border-color .2s; }
      .social:hover { border-color: #b9cff1; transform: translateY(-1px); }
      .social > i:first-child { width: 36px; height: 36px; border-radius: 11px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; flex: none; background: var(--sc, #475569); }
      .social > i.s-img { overflow: hidden; padding: 0; } .social > i.s-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
      .s-instagram > i:first-child { background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fd5949 45%, #d6249f 60%, #285aeb 90%); }
      .s-x > i:first-child, .s-tiktok > i:first-child, .s-threads > i:first-child, .s-medium > i:first-child, .s-github > i:first-child { box-shadow: inset 0 0 0 1px rgba(255,255,255,.15); }
      .social .sn { min-width: 0; display: flex; flex-direction: column; line-height: 1.25; }
      .social .sn b { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .social .sn small { color: var(--muted); font-weight: 600; font-size: 11.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .social .go { margin-left: auto; color: var(--muted); opacity: 0; transition: opacity .2s, transform .2s; }
      .social:hover .go { opacity: 1; transform: translate(1px, -1px); }
      .s-youtube i { background: #ff0000; } .s-researchgate i { background: #00a99d; } .s-other i { background: #475569; }
      .events { display: grid; gap: 10px; }
      .ev { display: flex; gap: 12px; align-items: center; padding: 12px; border-radius: 16px; background: var(--soft); border: 1px solid var(--line); }
      .ev .d { width: 54px; height: 58px; border-radius: 14px; background: linear-gradient(160deg, var(--navy), var(--blue)); color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; flex: none; line-height: 1.05; }
      .ev .d small { font-size: 10.5px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; opacity: .8; }
      .ev .d b { font-size: 1.25rem; font-weight: 800; }
      .ev .t b { color: var(--ink); display: block; font-size: 14.5px; }
      .ev .t span { color: var(--muted); font-size: 13px; }
      .ev .soon { margin-left: auto; font-size: 11.5px; font-weight: 800; color: #9a2459; background: #fde7f1; border-radius: 999px; padding: 4px 9px; white-space: nowrap; }
      @media (prefers-color-scheme: dark) { .ev .soon { background: rgba(178,46,107,.2); color: #ff9cc6; } }

      .share { display: flex; gap: 16px; align-items: center; }
      .qr-btn { border: 0; padding: 8px; border-radius: 18px; background: #fff; box-shadow: 0 10px 24px -14px rgba(10,26,61,.5); cursor: zoom-in; flex: none; position: relative; }
      .qr-btn img { width: 116px; height: 116px; display: block; }
      .qr-btn span { position: absolute; right: -6px; bottom: -6px; width: 30px; height: 30px; border-radius: 10px; background: var(--ink); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; }
      @media (prefers-color-scheme: dark) { .qr-btn span { background: var(--blue-2); } }
      .share .t { font-weight: 800; color: var(--ink); font-size: 15px; }
      .share .d { color: var(--muted); font-size: 13.5px; margin: 3px 0 12px; }
      .pills { display: flex; flex-wrap: wrap; gap: 6px; }
      .pill { font-size: 13px; font-weight: 700; padding: 8px 12px; border-radius: 11px; background: var(--chip); color: var(--ink); display: inline-flex; align-items: center; gap: 6px; border: 0; cursor: pointer; }
      .pill:hover { color: var(--blue-2); }

      .foot { text-align: center; color: var(--muted); font-size: 12.5px; padding: 6px 20px calc(96px + env(safe-area-inset-bottom)); }
      .foot .ev-name { font-weight: 700; color: var(--text); }
      .foot a { text-decoration: underline; text-underline-offset: 3px; }
      .foot .pw { display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; opacity: .85; }
      .foot .pw img { height: 18px; background: #fff; border-radius: 5px; padding: 2px 4px; }

      /* sticky save bar (phones) */
      .dock { position: fixed; left: 12px; right: 12px; bottom: max(12px, env(safe-area-inset-bottom)); z-index: 20; display: flex; gap: 8px; align-items: center; padding: 8px; border-radius: 20px;
         background: color-mix(in srgb, var(--card) 88%, transparent); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border: 1px solid var(--line); box-shadow: 0 18px 40px -16px rgba(10,26,61,.5);
         transform: translateY(140%); transition: transform .3s cubic-bezier(.2,.8,.2,1); }
      .dock.on { transform: none; }
      .dock .who { display: flex; align-items: center; gap: 9px; min-width: 0; flex: 1; padding-left: 4px; }
      .dock .who img, .dock .who span { width: 36px; height: 36px; border-radius: 11px; object-fit: cover; background: var(--chip); color: var(--blue); font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 13px; flex: none; }
      .dock .who b { font-size: 13.5px; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .dock .btn { padding: 11px 14px; border-radius: 13px; font-size: 14px; }

      /* full-screen QR */
      .qr-full { position: fixed; inset: 0; z-index: 50; background: rgba(4,12,30,.86); backdrop-filter: blur(6px); display: none; align-items: center; justify-content: center; padding: 20px; }
      .qr-full.on { display: flex; }
      .qr-full .box { background: #fff; color: #0f1a2e; border-radius: 28px; padding: 26px 22px 20px; text-align: center; width: 100%; max-width: 380px; box-shadow: 0 30px 80px -20px rgba(0,0,0,.6); }
      .qr-full img { width: 100%; max-width: 300px; aspect-ratio: 1; display: block; margin: 0 auto 12px; }
      .qr-full b { display: block; font-size: 1.15rem; }
      .qr-full small { color: #6b7489; }
      .qr-full button { margin-top: 16px; width: 100%; background: #0f1a2e; color: #fff; }

      .toast { position: fixed; left: 50%; bottom: calc(92px + env(safe-area-inset-bottom)); transform: translate(-50%, 20px); background: #0f1a2e; color: #fff; padding: 11px 18px; border-radius: 13px; font-weight: 700; font-size: 14px; opacity: 0; transition: .25s; pointer-events: none; z-index: 60; box-shadow: 0 14px 30px -12px rgba(0,0,0,.5); }
      .toast.show { opacity: 1; transform: translate(-50%, 0); }

      /* entrance */
      @media (prefers-reduced-motion: no-preference) {
         .rise { animation: rise .55s cubic-bezier(.2,.8,.2,1) both; }
         .rise:nth-child(2) { animation-delay: .06s; } .rise:nth-child(3) { animation-delay: .12s; } .rise:nth-child(4) { animation-delay: .18s; } .rise:nth-child(5) { animation-delay: .24s; }
         @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
      }

      /* desktop: identity card on the left, details on the right */
      @media (min-width: 920px) {
         .hero { height: 260px; }
         .page { grid-template-columns: 370px 1fr; align-items: start; margin-top: -150px; gap: 20px; padding-bottom: 30px; }
         .side { position: sticky; top: 20px; }
         .main { display: grid; gap: 16px; padding-top: 110px; }
         .dock { display: none; }
         .foot { padding-bottom: 36px; }
         .toast { bottom: 30px; }
      }
      @media (max-width: 919px) { .main { display: grid; gap: 16px; } }
      @media (max-width: 380px) { .actions[data-n="4"] { grid-template-columns: repeat(2, 1fr); } h1 { font-size: 1.5rem; } }
   </style>
</head>
<body>
<header class="hero">
   <div class="topbar">
      <a class="brand" href="{{ url('/') }}" aria-label="{{ $eventShort }} home"><img src="{{ asset('assets/frontend/images/logo.png') }}" alt="{{ $eventShort }}"></a>
      <span style="flex:1"></span>
      <button class="glass icon" type="button" data-qr aria-label="Show QR code"><i class="bi bi-qr-code"></i></button>
      <button class="glass" type="button" data-share><i class="bi bi-share-fill"></i> Share</button>
   </div>
   <div class="stripe"><i></i><i></i><i></i><i></i></div>
</header>

<div class="page">
   {{-- identity --}}
   <aside class="side">
      <section class="card id rise" aria-label="Profile">
         <div class="photo">@if($photo)<img src="{{ $photo }}" alt="{{ $p->name }}">@else<span aria-hidden="true">{{ $p->initials }}</span>@endif</div>
         <h1>{{ $p->name }}<span class="verified" title="Registered {{ $eventShort }} participant"><i class="bi bi-patch-check-fill" aria-hidden="true"></i><span class="sr">Registered {{ $eventShort }} participant</span></span></h1>
         @if($designation)<div class="role">{{ $designation }}</div>@endif
         @if($org || $dept)
            <div><div class="org"><span class="lg">@if($logo)<img src="{{ $logo }}" alt="">@else<i class="bi bi-building"></i>@endif</span>
               <span class="min-w-0">@if($org)<b>{{ $org }}</b>@endif @if($dept)<small>{{ $dept }}</small>@endif</span></div></div>
         @endif
         <div class="chips">
            @if($upcoming)<span class="chip event"><i class="bi bi-calendar-heart"></i> Attending {{ $upcoming->event_short }}</span>@endif
            @if($category)<span class="chip"><i class="bi bi-person-badge"></i> {{ $category }}</span>@endif
            @if($country)<span class="chip"><i class="bi bi-geo-alt"></i> {{ $country }}</span>@endif
            <span class="chip code" title="Participant ID">{{ $p->code }}</span>
         </div>

         <div class="save">
            <a class="btn btn-main" href="{{ route('profile.vcard', $p->code) }}" data-save><i class="bi bi-person-plus-fill"></i> Save contact</a>
            <button class="btn btn-ghost" type="button" data-share aria-label="Share profile"><i class="bi bi-share-fill"></i></button>
         </div>
         @if($actions)
         <nav class="actions" style="--n: {{ count($actions) }}" data-n="{{ count($actions) }}" aria-label="Contact">
            @foreach($actions as [$track, $href, $icon, $label, $blank])
               <a class="act a-{{ $track }}" href="{{ $href }}" @if($blank) target="_blank" rel="noopener" @endif data-track="{{ $track }}"><i class="bi {{ $icon }}" aria-hidden="true"></i><span class="al">{{ $label }}</span></a>
            @endforeach
         </nav>
         @endif
      </section>
   </aside>

   {{-- details --}}
   <main class="main">
      @if($bio || $expertise)
      <section class="card sec rise">
         @if($bio)
            <h2><i class="bi bi-person-lines-fill"></i> About</h2>
            <p class="bio {{ $bioLong ? 'clamp' : '' }}" id="bio">{{ $bio }}</p>
            @if($bioLong)<button class="more" type="button" id="bio-more">Read more</button>@endif
         @endif
         @if($expertise)
            <h2 style="{{ $bio ? 'margin-top:20px' : '' }}"><i class="bi bi-stars"></i> Areas of expertise</h2>
            <div class="tags">@foreach($expertise as $x)<span>{{ $x }}</span>@endforeach</div>
         @endif
      </section>
      @endif

      @if($tiles || $hasLocation || $custom)
      <section class="card sec connect rise" id="connect" aria-labelledby="connect-title">
         <div class="conn-head">
            <span class="conn-badge"><i class="bi bi-chat-heart-fill"></i></span>
            <div><h2 id="connect-title">Connect with me</h2><p>Pick the easiest way to get in touch.</p></div>
         </div>

         @if($tiles)
         <div class="tiles">
            @foreach($tiles as [$track, $href, $icon, $label, $value, $cta, $copy, $blank])
            <div class="tile t-{{ $track }}">
               <a class="tile-main" href="{{ $href }}" @if($blank) target="_blank" rel="noopener" @endif data-track="{{ $track }}">
                  <span class="ti"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
                  <span class="tt"><small>{{ $label }}</small><b>{{ $value }}</b><em>{{ $cta }} <i class="bi bi-arrow-right-short"></i></em></span>
               </a>
               <button class="tile-copy" type="button" data-copy="{{ $copy }}" aria-label="Copy {{ strtolower($label) }}" title="Copy"><i class="bi bi-copy"></i></button>
            </div>
            @endforeach
         </div>
         @endif

         @if($hasLocation)
         <div class="loc">
            <div class="map" id="map" @if($mapEmbed) data-embed="{{ $mapEmbed }}" @endif>
               <div class="map-art" aria-hidden="true"><span class="road r1"></span><span class="road r2"></span><span class="road r3"></span><span class="park"></span><span class="pin"><i class="bi bi-geo-alt-fill"></i></span><span class="pulse"></span></div>
               @if($mapEmbed)<button class="map-load" type="button" id="map-load"><i class="bi bi-map"></i> Show map</button>@endif
            </div>
            <div class="loc-info">
               <small><i class="bi bi-building"></i> {{ $place ? 'Office location' : 'Location' }}</small>
               <b>{{ $place ?: 'See the location on Google Maps' }}</b>
               <div class="loc-btns">
                  @if($directions)<a class="lb primary" href="{{ $directions }}" target="_blank" rel="noopener" data-track="map"><i class="bi bi-sign-turn-right-fill"></i> Get directions</a>@endif
                  @if($mapOpen)<a class="lb" href="{{ $mapOpen }}" target="_blank" rel="noopener" data-track="map"><i class="bi bi-google"></i> Open in Google Maps</a>@endif
                  @if($place)<button class="lb icon" type="button" data-copy="{{ $place }}" aria-label="Copy address" title="Copy address"><i class="bi bi-copy"></i></button>@endif
               </div>
            </div>
         </div>
         @endif

         @if($custom)
         <div class="extra">
            @foreach($custom as $f)<div><small>{{ $f['label'] ?? '' }}</small><b>{{ $f['value'] ?? '' }}</b></div>@endforeach
         </div>
         @endif

         <a class="conn-save" href="{{ route('profile.vcard', $p->code) }}"><span><i class="bi bi-person-vcard-fill"></i></span><div><b>Save all my details</b><small>Adds me to your phone's contacts in one tap</small></div><i class="bi bi-download"></i></a>
      </section>
      @endif

      @if($socials)
      <section class="card sec rise">
         <h2><i class="bi bi-share"></i> Find me online</h2>
         <div class="socials">
            @foreach($socials as $l)
               @php $sTitle = \App\Support\SocialLinks::title($l); $sHost = preg_replace('/^www\./', '', (string) parse_url($l['url'], PHP_URL_HOST)); $sLook = \App\Support\SocialLinks::look($l); @endphp
               <a class="social s-{{ $l['type'] }}" href="{{ $l['url'] }}" target="_blank" rel="noopener me" data-track="social" style="--sc: {{ $sLook['color'] }}">
                  @if($sLook['image'])<i class="s-img" aria-hidden="true"><img src="{{ $sLook['image'] }}" alt="" loading="lazy"></i>@else<i class="bi {{ $sLook['icon'] }}" aria-hidden="true"></i>@endif
                  <span class="sn"><b>{{ $sTitle }}</b>@if($sTitle !== $sHost)<small>{{ $sHost }}</small>@endif</span>
                  <i class="bi bi-arrow-up-right go" aria-hidden="true"></i>
               </a>
            @endforeach
         </div>
      </section>
      @endif

      @if($events->isNotEmpty())
      <section class="card sec rise">
         <h2><i class="bi bi-calendar-event"></i> {{ $eventShort === 'BICS' ? 'BICS events' : 'Events' }}</h2>
         <div class="events">
            @foreach($events as $ev)
               @php $start = $ev->dateOf('event_start'); @endphp
               <div class="ev">
                  <div class="d">@if($start)<small>{{ $start->format('M') }}</small><b>{{ $start->format('Y') }}</b>@else<small>Event</small><b><i class="bi bi-calendar2"></i></b>@endif</div>
                  <div class="t"><b>{{ $ev->event_name ?: $ev->event_short }}</b><span>{{ collect([$ev->datesLabel(), $ev->event_venue])->filter()->implode(' · ') ?: $ev->event_short }}</span></div>
                  @if($upcoming && $upcoming->id === $ev->id)<span class="soon">See you there</span>@endif
               </div>
            @endforeach
         </div>
      </section>
      @endif

      <section class="card sec rise">
         <h2><i class="bi bi-qr-code"></i> Share this profile</h2>
         <div class="share">
            <button class="qr-btn" type="button" data-qr aria-label="Show QR code full screen"><img src="{{ route('profile.qr', [$p->code, 'svg']) }}" alt="QR code of this profile"><span><i class="bi bi-arrows-fullscreen"></i></span></button>
            <div class="min-w-0">
               <div class="t">Scan to connect</div>
               <div class="d">Show the code to someone nearby, or send the link.</div>
               <div class="pills">
                  <button class="pill" type="button" data-copy-link><i class="bi bi-link-45deg"></i> Copy link</button>
                  <a class="pill" href="https://wa.me/?text={{ urlencode($p->name . ' — ' . $url) }}" target="_blank" rel="noopener" data-track="share"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                  <a class="pill" href="{{ route('profile.qr', [$p->code, 'png']) }}?download=1&size=1000" download><i class="bi bi-download"></i> QR image</a>
               </div>
            </div>
         </div>
      </section>

      <footer class="foot">
         Digital profile · <span class="ev-name">{{ $eventName }}</span>
         @if(\App\Support\ParticipantAccess::settings()['portal'])<div style="margin-top:6px">Is this you? <a href="{{ route('my.login') }}">Sign in to update your profile</a></div>@endif
         <div><span class="pw"><img src="{{ asset('assets/frontend/images/logo.png') }}" alt=""> {{ $eventShort }}</span></div>
      </footer>
   </main>
</div>

{{-- phones: save / share always at hand once the identity card scrolls away --}}
<div class="dock" id="dock" aria-hidden="true">
   <div class="who">@if($photo)<img src="{{ $photo }}" alt="">@else<span>{{ $p->initials }}</span>@endif<b>{{ $p->name }}</b></div>
   <button class="btn btn-ghost" type="button" data-share aria-label="Share profile" style="width:44px"><i class="bi bi-share-fill"></i></button>
   <a class="btn btn-main" href="{{ route('profile.vcard', $p->code) }}"><i class="bi bi-person-plus-fill"></i> Save</a>
</div>

<div class="qr-full" id="qr-full" role="dialog" aria-modal="true" aria-label="QR code">
   <div class="box">
      <img src="{{ route('profile.qr', [$p->code, 'svg']) }}" alt="QR code of {{ $p->name }}">
      <b>{{ $p->name }}</b>
      <small>{{ $subtitle ?: $eventShort . ' participant' }}</small>
      <button class="btn" type="button" data-close>Close</button>
   </div>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>
(function () {
   var url = @json($url), name = @json($p->name), trackUrl = @json(route('profile.track', $p->code)), csrf = @json(csrf_token());
   function toast(t) { var el = document.getElementById('toast'); el.textContent = t; el.classList.add('show'); clearTimeout(el._t); el._t = setTimeout(function () { el.classList.remove('show'); }, 2000); }
   function track(a) { try { navigator.sendBeacon(trackUrl, new Blob([JSON.stringify({ action: a, _token: csrf })], { type: 'application/json' })); } catch (e) {} }
   function copyText(text, msg) {
      (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { toast(msg); })
         .catch(function () { var i = document.createElement('input'); i.value = text; document.body.appendChild(i); i.select(); document.execCommand('copy'); i.remove(); toast(msg); });
   }
   document.querySelectorAll('[data-track]').forEach(function (a) { a.addEventListener('click', function () { track(a.dataset.track); }); });
   document.querySelectorAll('[data-copy]').forEach(function (b) { b.addEventListener('click', function () { copyText(b.dataset.copy, 'Copied'); track('copy'); }); });
   document.querySelectorAll('[data-copy-link]').forEach(function (b) { b.addEventListener('click', function () { copyText(url, 'Link copied'); track('copy'); }); });
   document.querySelectorAll('[data-share]').forEach(function (b) { b.addEventListener('click', function () {
      track('share');
      if (navigator.share) navigator.share({ title: name, text: name, url: url }).catch(function () {}); else copyText(url, 'Link copied');
   }); });

   // full-screen QR (for scanning in person)
   var full = document.getElementById('qr-full');
   document.querySelectorAll('[data-qr]').forEach(function (b) { b.addEventListener('click', function () { full.classList.add('on'); full.querySelector('[data-close]').focus(); }); });
   full.addEventListener('click', function (e) { if (e.target === full || e.target.closest('[data-close]')) full.classList.remove('on'); });
   document.addEventListener('keydown', function (e) { if (e.key === 'Escape') full.classList.remove('on'); });

   // map: loads Google Maps only when the visitor asks for it (no third-party request on every visit)
   var mapLoad = document.getElementById('map-load');
   if (mapLoad) mapLoad.addEventListener('click', function () {
      var box = document.getElementById('map'), f = document.createElement('iframe');
      f.src = box.dataset.embed; f.title = 'Map'; f.loading = 'lazy'; f.referrerPolicy = 'no-referrer-when-downgrade'; f.allowFullscreen = true;
      box.appendChild(f); box.classList.add('loaded'); track('map');
   });

   // "read more"
   var more = document.getElementById('bio-more');
   if (more) more.addEventListener('click', function () { var b = document.getElementById('bio'); var open = b.classList.toggle('clamp'); more.textContent = open ? 'Read more' : 'Show less'; });

   // sticky save bar appears when the main "Save contact" button leaves the screen
   var dock = document.getElementById('dock'), save = document.querySelector('[data-save]');
   if ('IntersectionObserver' in window && save) {
      new IntersectionObserver(function (en) { var on = !en[0].isIntersecting && en[0].boundingClientRect.top < 0; dock.classList.toggle('on', on); dock.setAttribute('aria-hidden', on ? 'false' : 'true'); }).observe(save);
   }
})();
</script>
</body>
</html>
