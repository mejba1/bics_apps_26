@php use App\Support\Cms; $embed = Cms::youtubeEmbed($c['youtube_url'] ?? ''); @endphp
@if($embed)
<section {!! Cms::sectionAttrs($c) !!}>
   <div class="container" style="max-width:1080px">
      @include('frontend.sections.partials.head')
      <div class="bx-video bx-reveal">
         <iframe src="{{ $embed }}" title="{{ ($c['heading'] ?? '') ?: 'BICS video' }}" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
      </div>
   </div>
</section>
@endif
