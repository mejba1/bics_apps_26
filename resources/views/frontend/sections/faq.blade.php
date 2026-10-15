@php
   use App\Support\Cms;
   $items = array_values(array_filter($c['items'] ?? [], fn ($q) => trim($q['question'] ?? '') !== ''));
   $id = 'faq' . ($section->id ?? 0);
@endphp
@if($items)
<section {!! Cms::sectionAttrs($c) !!}>
   <div class="container">
      <div class="row g-5">
         <div class="col-lg-4">
            @include('frontend.sections.partials.head', ['center' => false])
            @if(!empty($c['help_title']))
            <div class="bx-help-card bx-reveal">
               <span class="bx-icon"><i class="bi bi-headset"></i></span>
               <h3>{{ $c['help_title'] }}</h3>
               @if(!empty($c['help_text']))<p class="text-muted">{!! nl2br(e($c['help_text'])) !!}</p>@endif
               @if(!empty($c['button_text']))<a class="bx-link" href="{{ Cms::link($c['button_url'] ?? '') }}">{{ $c['button_text'] }} <i class="bi bi-arrow-right"></i></a>@endif
            </div>
            @endif
         </div>
         <div class="col-lg-8">
            <div class="accordion bx-faq" id="{{ $id }}">
               @foreach($items as $i => $q)
               <div class="accordion-item bx-reveal">
                  <h3 class="accordion-header" id="{{ $id }}-h{{ $i }}">
                     <button class="accordion-button {{ $i ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $id }}-c{{ $i }}" aria-expanded="{{ $i ? 'false' : 'true' }}" aria-controls="{{ $id }}-c{{ $i }}">{{ $q['question'] }}</button>
                  </h3>
                  <div id="{{ $id }}-c{{ $i }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" aria-labelledby="{{ $id }}-h{{ $i }}" data-bs-parent="#{{ $id }}">
                     <div class="accordion-body">{!! nl2br(e($q['answer'] ?? '')) !!}</div>
                  </div>
               </div>
               @endforeach
            </div>
         </div>
      </div>
   </div>
</section>
@endif
