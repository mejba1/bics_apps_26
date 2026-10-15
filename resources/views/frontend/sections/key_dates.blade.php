@php
   use App\Support\Cms;
   use App\Support\EventSettings as Ev;
   use Carbon\Carbon;
   $tz = Ev::timezone();
   $dates = [];
   foreach ($c['items'] ?? [] as $d) {
      if (trim($d['title'] ?? '') === '') continue;
      $when = null;
      try { $when = !empty($d['date']) ? Carbon::parse(str_replace('T', ' ', $d['date']), $tz) : null; } catch (\Throwable $e) {}
      $dates[] = ['when' => $when, 'title' => $d['title'], 'text' => $d['text'] ?? ''];
   }
   // Dates from the current event (Admin > Events)
   if (!empty($c['auto'])) {
      $reg = Ev::registration();
      if ($reg['opens']) $dates[] = ['when' => $reg['opens'], 'title' => 'Registration opens', 'text' => 'Online registration for ' . Ev::get('event_short') . ' opens.'];
      if ($reg['closes']) $dates[] = ['when' => $reg['closes'], 'title' => 'Registration closes', 'text' => 'Last day to register online.'];
      if ($s = Ev::date('event_start')) $dates[] = ['when' => $s, 'title' => Ev::get('event_short') . ' begins', 'text' => Ev::get('event_venue') ?: ''];
      if (($e = Ev::date('event_end')) && !$e->isSameDay(Ev::date('event_start'))) $dates[] = ['when' => $e, 'title' => 'Closing day', 'text' => 'Final sessions and closing ceremony.'];
   }
   usort($dates, fn ($a, $b) => ($a['when']?->timestamp ?? PHP_INT_MAX) <=> ($b['when']?->timestamp ?? PHP_INT_MAX));
   $now = Carbon::now($tz);
@endphp
@if($dates)
<section {!! Cms::sectionAttrs($c) !!}>
   <div class="container">
      @include('frontend.sections.partials.head')
      <div class="bx-dates">
         @foreach($dates as $d)
         @php $past = $d['when'] && $d['when']->lt($now) && !$d['when']->isSameDay($now); @endphp
         <div class="bx-date bx-reveal {{ $past ? 'is-past' : '' }}">
            @if($past)<span class="bx-done"><i class="bi bi-check2-circle"></i> Passed</span>@endif
            <div class="bx-date-when">{{ $d['when'] ? $d['when']->format('j M Y') : 'To be confirmed' }}</div>
            <h3>{{ $d['title'] }}</h3>
            @if($d['text'])<p>{!! nl2br(e($d['text'])) !!}</p>@endif
         </div>
         @endforeach
      </div>
   </div>
</section>
@endif
