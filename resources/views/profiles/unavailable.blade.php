<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
   <title>Profile not available</title>
   <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700;800&display=swap">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
   <style>
      body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; font-family: "Plus Jakarta Sans", system-ui, sans-serif; background: #f4f7fc; color: #101a2e; }
      .card { max-width: 420px; text-align: center; background: #fff; border-radius: 24px; padding: 34px 28px; box-shadow: 0 30px 60px -30px rgba(10,35,80,.35); }
      .ic { font-size: 44px; color: #b22e6b; }
      h1 { font-size: 1.35rem; margin: 10px 0 8px; } p { color: #6b7489; line-height: 1.6; margin: 0 0 18px; }
      a { display: inline-block; background: #0e4ca1; color: #fff; text-decoration: none; font-weight: 700; padding: 12px 20px; border-radius: 12px; }
   </style>
</head>
<body>
   <div class="card">
      <div class="ic"><i class="bi bi-person-slash"></i></div>
      <h1>This profile is not available</h1>
      <p>The profile you are looking for is currently not public. Please contact the event organiser if you need help.</p>
      <a href="{{ url('/') }}">Go to the BICS website</a>
   </div>
</body>
</html>
