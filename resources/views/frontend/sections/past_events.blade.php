@php use App\Support\Cms; @endphp
@if(!empty($c['items']))
<section {!! Cms::sectionAttrs($c, 'soft') !!}>
   <div class="container">
      @include('frontend.sections.partials.head')
      <div class="bx-past">
         @foreach($c['items'] as $p)
         @php $tag = !empty($p['link']) ? 'a' : 'div'; @endphp
         <{{ $tag }} class="bx-past-card bx-reveal {{ !empty($p['upcoming']) ? 'is-next' : (empty($p['image']) ? 'is-plain' : '') }}" @if($tag === 'a') href="{{ Cms::link($p['link']) }}" @endif>
            @if(!empty($p['image']))<img src="{{ \App\Support\Thumb::url($p['image'], 800) }}" alt="" loading="lazy">@endif
            <div class="bx-past-body">
               @if(!empty($p['edition']))<span class="bx-past-edition">{{ $p['edition'] }}</span>@endif
               <div class="bx-past-year">{{ $p['year'] ?? '' }}</div>
               @if(!empty($p['city']))<div class="bx-past-city"><i class="bi bi-geo-alt"></i> {{ $p['city'] }}</div>@endif
               @if(!empty($p['theme']))<div class="bx-past-theme">{{ $p['theme'] }}</div>@endif
               @if($tag === 'a')<div class="bx-link mt-3" style="color:#fff">{{ !empty($p['upcoming']) ? 'Register now' : 'View photos' }} <i class="bi bi-arrow-right"></i></div>@endif
            </div>
         </{{ $tag }}>
         @endforeach
      </div>
   </div>
</section>
@endif
