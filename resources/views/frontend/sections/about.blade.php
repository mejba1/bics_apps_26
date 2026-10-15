@php use App\Support\Cms; @endphp
<section {!! Cms::sectionAttrs($c) !!}>
   <div class="container">
      <div class="row g-5 align-items-center">
         <div class="col-lg-6 bx-reveal">
            @if(!empty($c['eyebrow']))<div class="bx-eyebrow">{{ $c['eyebrow'] }}</div>@endif
            @if(!empty($c['left_title']))<h2 class="bx-title">{{ $c['left_title'] }}</h2>@endif
            <div class="bx-about-body">{!! $c['left_body'] ?? '' !!}</div>
            @if(!empty($c['button_text']) && ($c['button_url'] ?? '#') !== '#')
            <a href="{{ Cms::link($c['button_url'] ?? '') }}" class="bx-btn bx-btn-primary mt-3">{{ $c['button_text'] }} <i class="bi bi-arrow-right"></i></a>
            @endif
         </div>
         @if(!empty($c['right_items']))
         <div class="col-lg-6 bx-reveal">
            <div class="bx-objectives">
               @if(!empty($c['right_title']))<h3>{{ $c['right_title'] }}</h3>@endif
               <ul>
                  @foreach($c['right_items'] as $item)
                     @if(!empty($item['text']))<li><i class="bi bi-check2"></i><span>{{ $item['text'] }}</span></li>@endif
                  @endforeach
               </ul>
            </div>
         </div>
         @endif
      </div>
   </div>
</section>
