@php
   use App\Support\Cms;
   $tiers = collect($c['items'] ?? [])->filter(fn ($p) => trim(($p['name'] ?? '') . ($p['logo'] ?? '')) !== '')->groupBy(fn ($p) => trim($p['tier'] ?? ''));
@endphp
@if($tiers->isNotEmpty())
<section {!! Cms::sectionAttrs($c) !!}>
   <div class="container">
      @include('frontend.sections.partials.head')
      @foreach($tiers as $tier => $logos)
      <div class="bx-tier bx-reveal">
         @if($tier !== '')<div class="bx-tier-name">{{ $tier }}</div>@endif
         <div class="bx-logos">
            @foreach($logos as $p)
               @php $tag = !empty($p['link']) ? 'a' : 'div'; @endphp
               <{{ $tag }} class="bx-logo-tile" @if($tag === 'a') href="{{ Cms::link($p['link']) }}" target="_blank" rel="noopener" @endif title="{{ $p['name'] ?? '' }}">
                  @if(!empty($p['logo']))<img src="{{ Cms::image($p['logo']) }}" alt="{{ $p['name'] ?? '' }}" loading="lazy">@else<span>{{ $p['name'] }}</span>@endif
               </{{ $tag }}>
            @endforeach
         </div>
      </div>
      @endforeach
   </div>
</section>
@endif
