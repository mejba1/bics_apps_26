@php use App\Support\Cms; @endphp
<section {!! Cms::sectionAttrs($c) !!}>
   <div class="container">
      <div class="bx-cta bx-reveal">
         <div class="row g-4 align-items-center">
            <div class="col-lg-8">
               @if(!empty($c['eyebrow']))<div class="bx-eyebrow" style="color:var(--bx-gold)">{{ $c['eyebrow'] }}</div>@endif
               @if(!empty($c['title']))<h2>{{ $c['title'] }}</h2>@endif
               @if(!empty($c['text']))<p>{!! nl2br(e($c['text'])) !!}</p>@endif
            </div>
            @if(!empty($c['button_text']))
            <div class="col-lg-4 bx-cta-actions justify-content-lg-end">
               <a href="{{ Cms::link($c['button_url'] ?? '') }}" class="bx-btn bx-btn-light">{{ $c['button_text'] }} <i class="bi bi-arrow-right"></i></a>
            </div>
            @endif
         </div>
      </div>
   </div>
</section>
