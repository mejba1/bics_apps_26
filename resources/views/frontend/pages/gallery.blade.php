@extends('frontend.layouts.content')
<link rel="stylesheet" href="{{ asset('assets/frontend') }}/css/gallery.css" />
<script src="//code.jquery.com/jquery-1.11.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bs5-lightbox@1.8.3/dist/index.bundle.min.js"></script>
@section('title') Gallery @endsection @section('content')
<div class="blog-sidebar-wrapper mt-3 mb-5">
<div class="container-fluid position-relative">
   <div class="row">
      <div class="gallery col-lg-12 col-md-12 col-sm-12 col-xs-12">
         <h1 class="gallery-title">Gallery</h1>
      </div>
      <div class="text-center mb-2">
         <button class="btn btn-default filter-button" data-filter="all">All</button>
         <button class="btn btn-default filter-button" data-filter="Bics2021"> Tanzania 2021</button>
         <button class="btn btn-default filter-button" data-filter="Bics2023"> Algeria 2023</button>
      </div>
      <hr/>
       <!--====Bics-Algeria-Start========-->
		 
		 <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/Bics2023-1.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2023"
                  alt="Tanzania bics"
                  />
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/Bics2023-2.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2023"
                  alt="Tanzania bics"
                  />
            </div>
            <div class="col-lg-4 mb-4 mb-lg-0">
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/Bics2023-4.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2023"
                  alt="Tanzania bics"
                  />
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/Bics2023-3.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2023"
                  alt="Tanzania bics"
                  />
            </div>
            <div class="col-lg-4 mb-4 mb-lg-0">
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/Bics2023-5.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2023"
                  alt="Tanzania bics"
                  />
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/Bics2023-6.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2023"
                  alt="Tanzania bics"
                  />
                 
            </div>
              <div class="col-lg-4 mb-4 mb-lg-0">
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/Bics2023-7.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2023"
                  alt="Tanzania bics"
                  />
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/Bics2023-8.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2023"
                  alt="Algeria bics"
                  />
                 
            </div>
            <div class="col-lg-4 mb-4 mb-lg-0">
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/Bics2023-9.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2023"
                  alt="Tanzania bics"
                  />
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/Bics2023-10.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2023"
                  alt="Algeria bics"
                  />
                  
            </div>
            <div class="col-lg-4 mb-4 mb-lg-0">
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/Bics2023-11.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2023"
                  alt="Tanzania bics"
                  />
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/Bics2023-12.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2023"
                  alt="Algeria bics"
                  />
                  <!--<p class="w-100 shadow-1-strong rounded mb-4 filter Bics2023 text-center">Not Available</p>-->
            </div>
            
            
         </div>
      <div class="row">
         <div class="col-lg-4 col-md-12 mb-4 mb-lg-0">
            <img
               src="{{ asset('assets/frontend') }}/images/gallery/bics-tanzania-4.jpg"
               class="w-100 shadow-1-strong rounded mb-4 filter Bics2021"
               alt="Tanzania"
               data-toggle="lightbox" data-gallery="example-gallery"
               />
            <img
               src="{{ asset('assets/frontend') }}/images/gallery/bics-tanzania-8.jpg"
               class="w-100 shadow-1-strong rounded mb-4 filter Bics2021"
               alt="Tanzania bics"
               />
         </div>
         <div class="col-lg-4 mb-4 mb-lg-0">
            <img
               src="{{ asset('assets/frontend') }}/images/gallery/bics-tanzania-13.jpg"
               class="w-100 shadow-1-strong rounded mb-4 filter Bics2021"
               alt="Tanzania bics"
               />
            <img
               src="{{ asset('assets/frontend') }}/images/gallery/bics-tanzania-11.jpg"
               class="w-100 shadow-1-strong rounded mb-4 filter Bics2021"
               alt="Tanzania bics"
               />
         </div>
         <div class="col-lg-4 mb-4 mb-lg-0">
            <img
               src="{{ asset('assets/frontend') }}/images/gallery/bics-tanzania-19.jpg"
               class="w-100 shadow-1-strong rounded mb-4 filter Bics2021"
               alt="Tanzania bics"
               />
            <img
               src="{{ asset('assets/frontend') }}/images/gallery/bics-tanzania-17.jpg"
               class="w-100 shadow-1-strong rounded mb-4 filter Bics2021"
               alt="Tanzania bics"
               />
         </div>
         <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/bics-tanzania-18.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2021"
                  alt="Tanzania bics"
                  />
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/bics-tanzania-20.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2021"
                  alt="Tanzania bics"
                  />
            </div>
            <div class="col-lg-4 mb-4 mb-lg-0">
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/bics-tanzania-6.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2021"
                  alt="Tanzania bics"
                  />
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/bics-tanzania-16.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2021"
                  alt="Tanzania bics"
                  />
            </div>
            <div class="col-lg-4 mb-4 mb-lg-0">
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/bics-tanzania-12.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2021"
                  alt="Tanzania bics"
                  />
               <img
                  src="{{ asset('assets/frontend') }}/images/gallery/bics-tanzania-10.jpg"
                  class="w-100 shadow-1-strong rounded mb-4 filter Bics2021"
                  alt="Tanzania bics"
                  />
            </div>
         </div>
         <!-- Gallery -->
		 
		 
		
      </div>
   </div>
</div>
</div>
<script src="{{ asset('assets/frontend') }}/js/gallery.js"></script>
@endsection