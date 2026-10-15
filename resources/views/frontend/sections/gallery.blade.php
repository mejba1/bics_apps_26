@php
   use App\Support\Thumb;
   // Photos come from Admin > Gallery (albums); older content stored in the section is the fallback
   $albums = \App\Models\GalleryAlbum::forDisplay();
   if ($albums->isNotEmpty()) {
      $groups = $albums->mapWithKeys(fn ($a) => ['album-' . $a->id => $a->title]);
      $images = $albums->flatMap(fn ($a) => $a->photos->map(fn ($p) => [
         'image' => $p->image, 'group' => 'album-' . $a->id, 'alt' => $p->caption ?: '',
         'size' => $p->width && $p->height ? [$p->width, $p->height] : null,
      ]))->values()->all();
   } else {
      $images = array_values(array_filter($c['images'] ?? [], fn ($i) => !empty($i['image'])));
      $groups = collect($c['filters'] ?? [])->filter(fn ($f) => !empty($f['key']))
         ->mapWithKeys(fn ($f) => [$f['key'] => $f['label'] ?: $f['key']]);
   }
   $counts = collect($images)->countBy(fn ($i) => $i['group'] ?? '');
   $layout = ($c['layout'] ?? 'masonry') === 'grid' ? 'grid' : 'masonry';
   $gid = 'gal-' . $section->id;
@endphp
@once
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/frontend/css/gallery-v2.css') }}?v={{ filemtime(base_path('assets/frontend/css/gallery-v2.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('assets/frontend/js/gallery-v2.js') }}?v={{ filemtime(base_path('assets/frontend/js/gallery-v2.js')) }}"></script>
@endpush
@endonce

<section class="gal" id="{{ $gid }}" data-gallery>
   <div class="container">
      <header class="gal-head">
         <span class="gal-eyebrow"><i class="bi bi-camera"></i> {{ count($images) }} photos</span>
         @if(!empty($c['title']))<h1 class="gal-title">{{ $c['title'] }}</h1>@endif
         @if(!empty($c['subtitle']))<p class="gal-sub">{!! nl2br(e($c['subtitle'])) !!}</p>@endif
      </header>

      @if($groups->count() > 1 || ($groups->count() === 1 && $counts->count() > 1))
      <div class="gal-filters" role="toolbar" aria-label="Filter photos">
         <button type="button" class="gal-filter is-active" data-filter="all" aria-pressed="true">All <span>{{ count($images) }}</span></button>
         @foreach($groups as $key => $label)
            @if($counts->get($key))
            <button type="button" class="gal-filter" data-filter="{{ $key }}" aria-pressed="false">{{ $label }} <span>{{ $counts->get($key) }}</span></button>
            @endif
         @endforeach
      </div>
      @endif

      <div class="gal-grid gal-{{ $layout }}">
         @foreach($images as $i => $img)
            @php
               $size = $img['size'] ?? Thumb::size($img['image']);
               $group = $img['group'] ?? '';
               $caption = $img['alt'] ?? '';
               $groupLabel = $groups->get($group, '');
            @endphp
            <figure class="gal-item" data-group="{{ $group }}">
               <a href="{{ Thumb::url($img['image'], 1600) }}" class="gal-link" data-index="{{ $i }}"
                  data-caption="{{ $caption }}" data-group-label="{{ $groupLabel }}"
                  aria-label="Open photo {{ $i + 1 }}{{ $caption ? ': ' . $caption : '' }}">
                  @php $ph = Thumb::placeholder($img['image']); @endphp
                  {{-- Blurred preview shown at once; the real photo fades in over it when loaded --}}
                  @if($ph)<span class="gal-ph" style="background-image:url('{{ $ph }}')" aria-hidden="true"></span>@endif
                  <img class="gal-img" src="{{ Thumb::url($img['image'], 800) }}" srcset="{{ Thumb::srcset($img['image'], [480, 800]) }}"
                       sizes="(max-width: 575px) 100vw, (max-width: 991px) 50vw, 33vw"
                       @if($size) width="{{ $size[0] }}" height="{{ $size[1] }}" @endif
                       alt="{{ $caption ?: ($groupLabel ?: 'BICS photo') }}"
                       loading="{{ $i < 9 ? 'eager' : 'lazy' }}" @if($i < 3) fetchpriority="high" @endif decoding="async">
                  <span class="gal-overlay">
                     @if($groupLabel)<span class="gal-chip">{{ $groupLabel }}</span>@endif
                     <i class="bi bi-arrows-fullscreen gal-zoom" aria-hidden="true"></i>
                  </span>
               </a>
            </figure>
         @endforeach
      </div>

      @if(!count($images))
      <p class="gal-empty"><i class="bi bi-images"></i><br>Photos coming soon.</p>
      @endif
   </div>
</section>
