{{-- Section heading: eyebrow, title and intro. Pass $c and optionally $center (default true). --}}
@if(!empty($c['eyebrow']) || !empty($c['heading']) || !empty($c['intro']))
<div class="bx-head bx-reveal {{ ($center ?? true) ? 'is-center' : '' }}">
   @if(!empty($c['eyebrow']))<div class="bx-eyebrow">{{ $c['eyebrow'] }}</div>@endif
   @if(!empty($c['heading']))<h2 class="bx-title">{{ $c['heading'] }}</h2>@endif
   @if(!empty($c['intro']))<p class="bx-lead">{!! nl2br(e($c['intro'])) !!}</p>@endif
</div>
@endif
