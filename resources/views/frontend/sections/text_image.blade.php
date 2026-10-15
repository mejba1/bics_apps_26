@php use App\Support\Cms; $imageLeft = ($c['image_position'] ?? 'right') === 'left'; @endphp
<section {!! Cms::sectionAttrs($c) !!}>
   <div class="container">
      <div class="row align-items-center g-5">
         <div class="{{ empty($c['image']) ? 'col-12' : 'col-lg-6' }} {{ $imageLeft ? 'order-lg-2' : '' }} bx-reveal">
            @if(!empty($c['eyebrow']))<div class="bx-eyebrow">{{ $c['eyebrow'] }}</div>@endif
            @if(!empty($c['heading']))<h2 class="bx-title">{{ $c['heading'] }}</h2>@endif
            <div class="bx-prose cms-content">{!! $c['body'] ?? '' !!}</div>
            @if(!empty($c['button_text']))
            <a href="{{ Cms::link($c['button_url'] ?? '') }}" class="bx-btn bx-btn-primary mt-3">{{ $c['button_text'] }} <i class="bi bi-arrow-right"></i></a>
            @endif
         </div>
         @if(!empty($c['image']))
         <div class="col-lg-6 {{ $imageLeft ? 'order-lg-1' : '' }} bx-reveal">
            <img src="{{ \App\Support\Thumb::url($c['image'], 1600) }}" class="bx-media-img" alt="{{ $c['heading'] ?? '' }}" loading="lazy">
         </div>
         @endif
      </div>
   </div>
</section>
