@php
   use App\Support\Cms;
   $items = array_values(array_filter($c['items'] ?? [], fn ($s) => trim($s['title'] ?? '') !== ''));
   $days = collect($items)->groupBy(fn ($s) => trim($s['day'] ?? '') ?: 'Programme');
   $labels = ['session' => 'Session', 'keynote' => 'Keynote', 'panel' => 'Panel', 'break' => 'Break', 'ceremony' => 'Ceremony'];
   $sid = 'ag' . ($section->id ?? 0);
@endphp
<section {!! Cms::sectionAttrs($c, 'soft') !!}>
   <div class="container">
      @include('frontend.sections.partials.head')
      @if($items)
      <div data-bx-tabs>
         @if($days->count() > 1)
         <div class="bx-tabs is-center" role="tablist">
            @foreach($days->keys() as $i => $day)
            <button class="bx-tab {{ $i === 0 ? 'is-active' : '' }}" type="button" role="tab" data-tab="{{ $sid }}-{{ $i }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}">{{ $day }}</button>
            @endforeach
         </div>
         @endif
         @foreach($days->values() as $i => $slots)
         <div class="bx-agenda mx-auto" style="max-width:980px" data-panel="{{ $sid }}-{{ $i }}" role="tabpanel" @if($i > 0) hidden @endif>
            @foreach($slots as $s)
               @php $type = $s['type'] ?? 'session'; @endphp
               <div class="bx-slot t-{{ $type }} bx-reveal">
                  <div class="bx-slot-time">{{ $s['time'] ?? '' }}</div>
                  <div>
                     <h3 class="bx-slot-title">{{ $s['title'] }}</h3>
                     @if(!empty($s['speaker']) || !empty($s['room']))
                     <div class="bx-slot-sub">
                        @if(!empty($s['speaker']))<span><i class="bi bi-person"></i>{{ $s['speaker'] }}</span>@endif
                        @if(!empty($s['room']))<span><i class="bi bi-door-open"></i>{{ $s['room'] }}</span>@endif
                     </div>
                     @endif
                  </div>
                  <span class="bx-tag">{{ $labels[$type] ?? 'Session' }}</span>
               </div>
            @endforeach
         </div>
         @endforeach
      </div>
      @if(!empty($c['note']))<p class="text-center text-muted small mt-4 mb-0"><i class="bi bi-info-circle"></i> {{ $c['note'] }}</p>@endif
      @else
      <div class="bx-soon bx-reveal">
         <i class="bi bi-calendar3-week" aria-hidden="true"></i>
         <h3>Programme coming soon</h3>
         @if(!empty($c['empty_text']))<p>{!! nl2br(e($c['empty_text'])) !!}</p>@endif
      </div>
      @endif
   </div>
</section>
