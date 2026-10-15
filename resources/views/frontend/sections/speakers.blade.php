@php
   use App\Support\Cms;
   $items = array_values(array_filter($c['items'] ?? [], fn ($s) => trim($s['name'] ?? '') !== ''));
   $initials = fn ($n) => mb_strtoupper(collect(preg_split('/\s+/', preg_replace('/^(dr|prof|mr|mrs|ms)\.?\s+/i', '', trim($n))))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
@endphp
<section {!! Cms::sectionAttrs($c) !!}>
   <div class="container">
      @include('frontend.sections.partials.head')
      @if($items)
      <div class="row g-4 justify-content-center">
         @foreach($items as $s)
         <div class="col-sm-6 col-lg-4 col-xl-3">
            <article class="bx-card bx-speaker bx-reveal">
               <div class="bx-speaker-photo">
                  @if(!empty($s['photo']))<img src="{{ \App\Support\Thumb::url($s['photo'], 480) }}" alt="{{ $s['name'] }}" loading="lazy">
                  @else<span aria-hidden="true">{{ $initials($s['name']) }}</span>@endif
               </div>
               <h3>{{ $s['name'] }}</h3>
               @if(!empty($s['role']))<div class="role">{{ $s['role'] }}</div>@endif
               @if(!empty($s['organisation']))<div class="org">{{ $s['organisation'] }}</div>@endif
               @if(!empty($s['country']))<span class="bx-flag"><i class="bi bi-geo-alt"></i> {{ $s['country'] }}</span>@endif
               @if(!empty($s['topic']))<div class="topic">“{{ $s['topic'] }}”</div>@endif
            </article>
         </div>
         @endforeach
      </div>
      @else
      <div class="bx-soon bx-reveal">
         <i class="bi bi-mic" aria-hidden="true"></i>
         <h3>{{ ($c['empty_title'] ?? '') ?: 'Speakers to be announced' }}</h3>
         @if(!empty($c['empty_text']))<p>{!! nl2br(e($c['empty_text'])) !!}</p>@endif
         <a class="bx-btn bx-btn-primary bx-btn-sm" href="{{ route('registration') }}">Reserve your seat <i class="bi bi-arrow-right"></i></a>
      </div>
      @endif
   </div>
</section>
