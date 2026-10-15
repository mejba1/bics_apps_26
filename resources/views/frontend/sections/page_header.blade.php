@php $title = ($c['title'] ?? '') ?: ($page->title ?? ''); @endphp
<section class="bx-pagehead">
   @if(!empty($c['image']))<img class="bx-pagehead-bg" src="{{ \App\Support\Thumb::url($c['image'], 1600) }}" alt="" aria-hidden="true">@endif
   <div class="container">
      <ol class="bx-crumbs" aria-label="Breadcrumb">
         <li><a href="{{ route('home') }}">Home</a></li>
         <li aria-current="page">{{ $page->title ?? $title }}</li>
      </ol>
      @if(!empty($c['eyebrow']))<div class="bx-eyebrow mt-3" style="color:var(--bx-gold)">{{ $c['eyebrow'] }}</div>@endif
      <h1>{{ $title }}</h1>
      @if(!empty($c['subtitle']))<p>{!! nl2br(e($c['subtitle'])) !!}</p>@endif
   </div>
</section>
