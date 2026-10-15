@php
   use App\Support\Cms;
   $slides = array_values(array_filter($c['slides'] ?? [], fn ($s) => !empty($s['image'])));
   $carouselId = 'carousel-' . $section->id;
   $countdownShown = false;
@endphp
@if(count($slides))
<div class="hero-style-two position-relative">
    <div id="{{ $carouselId }}" class="carousel slide" data-bs-ride="carousel">
       <div class="carousel-indicators">
          @foreach($slides as $i => $slide)
          <button type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide-to="{{ $i }}" @if($i === 0) class="active" aria-current="true" @endif aria-label="Slide {{ $i + 1 }}"></button>
          @endforeach
       </div>
       <div class="carousel-inner swiper hero-two-slider">
          @foreach($slides as $i => $slide)
          <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
             <img src="{{ Cms::image($slide['image']) }}" class="d-block w-100" alt="{{ $slide['subtitle'] ?? '' ?: 'BICS' }}">
             <div class="carousel-caption d-md-block">
                @if(($slide['style'] ?? 'none') === 'event')
                <div class="container">
                   <div class="hero-bics-layer">
                      @if(!empty($slide['subtitle']))<h5>{{ $slide['subtitle'] }}</h5>@endif
                      @if(!empty($slide['title']))<h3>{!! nl2br(e($slide['title'])) !!}</h3>@endif
                      @if(!empty($slide['show_countdown']) && !$countdownShown)
                      @php $countdownShown = true; @endphp
                      <div class="hero-featured-countdown" id="timer_h2">
                         <div class="hero-countdown-box"><h3 id="days_h2">00</h3><p>Days</p></div>
                         <div class="hero-countdown-box"><h3 id="hours_h2">00</h3><p>Hour</p></div>
                         <div class="hero-countdown-box"><h3 id="minutes_h2">00</h3><p>Miniute</p></div>
                         <div class="hero-countdown-box"><h3 id="seconds_h2">00</h3><p>Secoend</p></div>
                      </div>
                      @endif
                      @if(!empty($slide['button1_text']) || !empty($slide['button2_text']))
                      <div class="hero-btns">
                         @if(!empty($slide['button1_text']))<a href="{{ Cms::link($slide['button1_url'] ?? '') }}" class="primary-btn-fill-s2">{{ $slide['button1_text'] }}</a>@endif
                         @if(!empty($slide['button2_text']))<a href="{{ Cms::link($slide['button2_url'] ?? '') }}" class="primary-btn-outline-s2">{{ $slide['button2_text'] }}</a>@endif
                      </div>
                      @endif
                   </div>
                </div>
                @elseif(($slide['style'] ?? 'none') === 'headline' && !empty($slide['title']))
                <div class="hero-bics-layer heading2-3rd">
                   @foreach(Cms::lines($slide['title']) as $line)
                   <h2>{{ $line }}</h2>
                   @endforeach
                </div>
                @endif
             </div>
          </div>
          @endforeach
       </div>
       @if(count($slides) > 1)
       <button class="carousel-control-prev" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="prev">
       <span class="carousel-control-prev-icon" aria-hidden="true"></span>
       <span class="visually-hidden">Previous</span>
       </button>
       <button class="carousel-control-next" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="next">
       <span class="carousel-control-next-icon" aria-hidden="true"></span>
       <span class="visually-hidden">Next</span>
       </button>
       @endif
    </div>
 </div>
@endif
