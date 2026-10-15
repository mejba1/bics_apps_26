<!doctype html>
<html lang="en">
   <head>
      <title>@yield('title') | Beacon International Cancer Summit</title>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      @yield('meta')
      @include('frontend.layouts.inc.style')
      @stack('styles')
   </head>
   <body>
      <!-- BICS - PreLoader -->
      @include('frontend.layouts.inc.preloader')
      <!-- BICS - Event Sidebar -->
      @include('frontend.layouts.inc.event')
      <!-- Header Start -->
      @include('frontend.layouts.inc.header')
      
      <main id="main">
            @yield('content')
      </main>

      <!-- Start Footer -->
      @include('frontend.layouts.inc.footer')
      <!--<div class="cursor"></div>
         <div class="cursor2"></div>-->
      @include('frontend.layouts.inc.script')
      @stack('scripts')
   </body>
</html>