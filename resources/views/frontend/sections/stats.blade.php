@php use App\Support\Cms; use App\Support\SiteStats; @endphp
<section {!! Cms::sectionAttrs($c, 'dark') !!}>
   <div class="container">
      @include('frontend.sections.partials.head')
      <div class="bx-stats">
         @foreach($c['items'] ?? [] as $item)
            @php $v = SiteStats::value($item['value'] ?? ''); @endphp
            <div class="bx-stat bx-reveal">
               @if(!empty($item['icon']))<i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>@endif
               <span class="bx-stat-num" @if($v['number'] !== null) data-count="{{ $v['number'] }}" data-suffix="{{ $v['suffix'] }}" @endif>{{ $v['text'] }}</span>
               <span class="bx-stat-label">{{ $item['label'] ?? '' }}</span>
            </div>
         @endforeach
      </div>
   </div>
</section>
