@extends('frontend.layouts.content')
@section('title')
   Media
@endsection
@section('content')
{{-- <div class="breadcrumb-area">
    <div class="container">
       <div class="row align-items-end">
          <div class="col-lg-12">
             <div class="breadcrumb-content">
                <!-- <h2 class="page-title">Upcoming BICS</h2>-->
                <ul class="page-switcher">
                   <li><a href="index.php">Home <i class="bi bi-caret-right"></i></a></li>
                   <li>Media</li>
                </ul>
             </div>
          </div>
       </div>
    </div>
 </div> --}}
 <div class="blog-sidebar-wrapper mt-3 mb-5">
    <div class="container-fluid position-relative pt-10">
       <div class="row justify-content-center">
          <div class="col-md-10">
             <div class="card shadow-lg rounded-10">
                <span class="shape"></span>
               <!-- <div class="card-header text-bics"><i class="fas fa-info-circle fa-lg"></i> &nbsp;REGISTRATION FORM</div>-->
                <div class="card-body">
                   <img src="{{ asset('assets/frontend') }}/images/gellary.jpg" width="1000" class="img-fluid" alt="bics Gallery">
                </div>
                <!--<div class="card-footer">Footer</div>-->
             </div>
          </div>
       </div>
    </div>
 </div>
    
@endsection