@php use App\Support\Cms; @endphp
@if(!empty($c['image']))
@php
   $img = '<img src="' . e(Cms::image($c['image'])) . '" class="img-fluid" alt="' . e($c['alt'] ?? '') . '">';
   if (!empty($c['link'])) $img = '<a href="' . e(Cms::link($c['link'])) . '">' . $img . '</a>';
@endphp
@if(($c['layout'] ?? 'boxed') === 'full')
<div class="container-fluid">
    <div class="row justify-content-center">
       <div class="col-md-12">
          <div class="card shadow-lg rounded-10">
             <span class="shape"></span>
             <div class="card-body">
                {!! $img !!}
             </div>
          </div>
       </div>
    </div>
 </div>
@else
<div class="blog-sidebar-wrapper mt-3 mb-5">
    <div class="container-fluid position-relative pt-10">
       <div class="row justify-content-center">
          <div class="col-md-10">
             <div class="card shadow-lg rounded-10">
                <span class="shape"></span>
                <div class="card-body">
                   {!! $img !!}
                </div>
             </div>
          </div>
       </div>
    </div>
 </div>
@endif
@endif
