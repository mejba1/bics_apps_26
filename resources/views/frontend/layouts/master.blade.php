<!doctype html>
<html lang="en">
   <head>
      <title>BICS | Beacon International Cancer Summit</title>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      @include('frontend.layouts.inc.style')
   </head>
   <body>
      <!-- BICS - PreLoader -->
     @include('frontend.layouts.inc.preloader')
      <!-- BICS - Event Sidebar -->
      @include('frontend.layouts.inc.event')
      <!-- Header Start -->
      @include('frontend.layouts.inc.header')
      
      <!--- Content Section -->

      <!--- end-Content Section -->

      <!-- Start Slider Section -->
      @include('frontend.layouts.inc.slider')
      <!-- BICS - About Section -->
     @include('frontend.layouts.inc.about-section')

     <!--- Bics Gallery--->
      @include('frontend.layouts.inc.bics-gellary')
      <!-- BICS - Info Section -->
      <!--<div class="container mt-4" id="menu-2">
         <div class="row">
            <div class="col-md-12">
               <div class="card bics-info-content-2 shadow p-3 mb-5 bg-body rounded">
                  <div class="card-body">
                     <div class="d-flex bd-highlight">
                        <div class="p-2 flex-fill bd-highlight"><img src="assets/images/backgrounds/achievement-bg2.png" width="120" height="120"></div>
                        <div class="p-2 flex-fill bd-highlight">
                           <h5 class="card-title fw-blod">BICS-MCBS</h5>
                           <p class="card-text"><span class="text-danger fw-bold">Beacon International Cancer Summit (BICS) </span>is a global Summit organized by Beacon Pharma Ltd to unite cancer care professionals to improve quality of life of the cancer patients through exchanging latest information and innovative ideas in cancer treatments. </p>
                           <div class="text-end">
                              <a href="#" class="bics-btn">Read more <i class="bi bi-arrow-right"></i></a>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>-->
      <!-- Videos Section -->
      @include('frontend.layouts.inc.video-section')
      <!-- Start Footer -->
      @include('frontend.layouts.inc.footer')
      <!--<div class="cursor"></div>
         <div class="cursor2"></div>-->
      @include('frontend.layouts.inc.script')
   </body>
</html>