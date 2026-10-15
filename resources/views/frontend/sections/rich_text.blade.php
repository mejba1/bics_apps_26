@php use App\Support\Cms; @endphp
<section {!! Cms::sectionAttrs($c) !!}>
   <div class="container" style="max-width:980px">
      @if(!empty($c['boxed']))
      <div class="bx-prose-card bx-reveal">
         @if(!empty($c['eyebrow']))<div class="bx-eyebrow">{{ $c['eyebrow'] }}</div>@endif
         @if(!empty($c['heading']))<h2 class="bx-title">{{ $c['heading'] }}</h2>@endif
         <div class="bx-prose cms-content">{!! $c['body'] ?? '' !!}</div>
      </div>
      @else
      @if(!empty($c['eyebrow']))<div class="bx-eyebrow">{{ $c['eyebrow'] }}</div>@endif
      @if(!empty($c['heading']))<h2 class="bx-title">{{ $c['heading'] }}</h2>@endif
      <div class="bx-prose cms-content">{!! $c['body'] ?? '' !!}</div>
      @endif
   </div>
</section>
