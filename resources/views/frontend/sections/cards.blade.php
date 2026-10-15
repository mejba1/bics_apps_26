@php use App\Support\Cms; $col = ['2' => 'col-md-6', '3' => 'col-md-6 col-lg-4', '4' => 'col-sm-6 col-lg-3'][$c['columns'] ?? '3'] ?? 'col-md-6 col-lg-4'; @endphp
<section {!! Cms::sectionAttrs($c) !!}>
   <div class="container">
      @include('frontend.sections.partials.head')
      <div class="row g-4 bx-grid-cards">
         @foreach($c['items'] ?? [] as $item)
         <div class="{{ $col }}">
            <div class="bx-card bx-reveal">
               @if(!empty($item['image']))<img class="bx-card-img" src="{{ \App\Support\Thumb::url($item['image'], 800) }}" alt="" loading="lazy">@endif
               @if(!empty($item['icon']))<span class="bx-icon"><i class="bi {{ $item['icon'] }}" aria-hidden="true"></i></span>@endif
               @if(!empty($item['title']))<h3>{{ $item['title'] }}</h3>@endif
               @if(!empty($item['text']))<p>{!! nl2br(e($item['text'])) !!}</p>@endif
               @if(!empty($item['link']))<a href="{{ Cms::link($item['link']) }}" class="bx-link mt-3">Learn more <i class="bi bi-arrow-right"></i></a>@endif
            </div>
         </div>
         @endforeach
      </div>
   </div>
</section>
