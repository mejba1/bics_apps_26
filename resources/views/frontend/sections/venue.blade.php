@php
   use App\Support\Cms;
   use App\Support\EventSettings as Ev;
   $address = ($c['address'] ?? '') ?: (string) Ev::get('event_venue');
   $name = ($c['venue_name'] ?? '') ?: (string) \Illuminate\Support\Str::before(Ev::get('event_venue', ''), ',');
   $showMap = ($c['media'] ?? 'map') === 'map' && $address !== '';
@endphp
<section {!! Cms::sectionAttrs($c) !!}>
   <div class="container">
      <div class="row g-5 align-items-stretch">
         <div class="col-lg-5">
            @include('frontend.sections.partials.head', ['center' => false])
            @if($name)<h3 class="bx-venue-name">{{ $name }}</h3>@endif
            @if($address)<div class="bx-venue-addr"><i class="bi bi-geo-alt-fill"></i><span>{{ $address }}</span></div>@endif
            @if($address)
            <a class="bx-btn bx-btn-outline bx-btn-sm" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($address) }}" target="_blank" rel="noopener"><i class="bi bi-sign-turn-right"></i> Get directions</a>
            @endif
            @if(!empty($c['items']))
            <div class="bx-info-list bx-grid-cards">
               @foreach($c['items'] as $item)
               <div class="bx-info bx-reveal">
                  <span class="bx-icon"><i class="bi {{ ($item['icon'] ?? '') ?: 'bi-info-circle' }}"></i></span>
                  <div>
                     <h4>{{ $item['title'] ?? '' }}</h4>
                     @if(!empty($item['text']))<p>{!! nl2br(e($item['text'])) !!}</p>@endif
                  </div>
               </div>
               @endforeach
            </div>
            @endif
         </div>
         <div class="col-lg-7">
            <div class="bx-venue-media bx-reveal">
               @if($showMap)
               <iframe title="Map: {{ $address }}" src="https://maps.google.com/maps?q={{ urlencode($address) }}&z=14&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
               @elseif(!empty($c['image']))
               <img src="{{ \App\Support\Thumb::url($c['image'], 1600) }}" alt="{{ $name }}" loading="lazy">
               @endif
            </div>
         </div>
      </div>
   </div>
</section>
