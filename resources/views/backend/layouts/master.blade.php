<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title') - BICS</title>
  <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
  <!-- Vendor CSS Files -->
 @include('backend.layouts.inc.styles')
 @stack('styles')
</head>
<body>
@include('backend.layouts.inc.header')
  <!-- ======= Sidebar ======= -->
  @include('backend.layouts.inc.sidemenu')


  <main id="main" class="main">
    @yield('content')
  </main><!-- End #main -->

 @include('backend.layouts.inc.footer')

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
  <!-- Vendor JS Files -->
  @include('backend.layouts.inc.script')
  @stack('scripts')
</body>
</html>