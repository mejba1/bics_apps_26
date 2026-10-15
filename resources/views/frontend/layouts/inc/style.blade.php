<link rel="icon" href="{{ asset('assets/frontend') }}/images/logo.png" type="image/gif" sizes="20x20">
<link rel="stylesheet" href="{{ asset('assets/frontend') }}/css/jquery.fancybox.min.css">
<link rel="stylesheet" href="{{ asset('assets/frontend') }}/css/all.min.css">
<link rel="stylesheet" href="{{ asset('assets/frontend') }}/css/jquery-ui.css">
<link rel="stylesheet" href="{{ asset('assets/frontend') }}/css/bootstrap-icons.css">
<link rel="stylesheet" href="{{ asset('assets/frontend') }}/css/bootstrap.min.css">
<link rel="stylesheet" href="{{ asset('assets/frontend') }}/css/daterangepicker.css">
<link rel="stylesheet" href="{{ asset('assets/frontend') }}/css/style.css">
<link rel="stylesheet" href="{{ asset('assets/frontend') }}/css/responsive.css">
<link rel="stylesheet" href="{{ asset('assets/frontend') }}/css/custom.css">
<link rel="stylesheet" href="{{ asset('assets/frontend') }}/css/lobibox.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="{{ asset('assets/frontend') }}/css/bics-theme.css?v={{ @filemtime(base_path('assets/frontend/css/bics-theme.css')) }}">
<meta name="theme-color" content="#0a2350">
<script src="{{ asset('assets/frontend') }}/js/jquery-3.6.0.min.js"></script>
<!--===Date Picker===-->
<script src="{{ asset('assets/frontend') }}/js/moment.min.js" ></script>
<script src="{{ asset('assets/frontend') }}/js/daterangepicker.js" ></script>
<script src="{{ asset('assets/frontend') }}/js/global.js" ></script>
<meta name="csrf-token" content="{{ csrf_token() }}" />