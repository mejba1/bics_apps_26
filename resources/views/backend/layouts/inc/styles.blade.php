{{-- Light / dark theme: applied before anything is drawn (no flash). Saved choice, else the device setting. --}}
<script>
  (function () {
    var t = null;
    try { t = localStorage.getItem('bics-admin-theme'); } catch (e) {}
    if (t !== 'light' && t !== 'dark') t = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', t);
  })();
</script>
<link href="{{ asset('assets/backend') }}/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="{{ asset('assets/backend') }}/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<!-- newer icon set (adds icons the bundled 1.x font lacks; same class names) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="{{ asset('assets/backend') }}/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
<link href="{{ asset('assets/backend') }}/vendor/quill/quill.snow.css" rel="stylesheet">
<link href="{{ asset('assets/backend') }}/vendor/quill/quill.bubble.css" rel="stylesheet">
<link href="{{ asset('assets/backend') }}/vendor/remixicon/remixicon.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/fixedheader/3.3.2/css/fixedHeader.bootstrap.min.css" rel="stylesheet">

<!-- Template Main CSS File -->
<link href="{{ asset('assets/backend') }}/css/style.css" rel="stylesheet">
<!-- BICS admin design system (refines every admin page) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="{{ asset('assets/backend/css/admin-theme.css') }}?v={{ filemtime(base_path('assets/backend/css/admin-theme.css')) }}" rel="stylesheet">