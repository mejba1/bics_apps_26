@php
   use App\Support\Cms;
   use App\Support\EventSettings as Ev;
   // Editable in Admin > Website Pages > Site-wide > Footer
   $f = \App\Models\Page::globalContent('footer') ?? \App\Support\PageSeed::footer();
   $reg = Ev::registration();
   $social = ['facebook' => 'bi-facebook', 'linkedin' => 'bi-linkedin', 'twitter' => 'bi-twitter-x', 'youtube' => 'bi-youtube'];
   $menu = \App\Models\MenuItem::tree();
   // The page already ends with a register banner: no second one in the footer
   $hasCta = isset($page) && $page->activeSections->contains('type', 'cta');
@endphp
<footer class="bx-footer">
   @if($reg['state'] !== 'closed' && !$hasCta && !request()->is('registration'))
   <div class="bx-footer-cta">
      <div class="container">
         <div>
            <h3>Join us at {{ Ev::get('event_short') }}</h3>
            <p>{{ Ev::datesLabel() }}@if(Ev::get('event_venue')) · {{ Ev::get('event_venue') }}@endif</p>
         </div>
         <a class="bx-btn bx-btn-accent" href="{{ route('registration') }}">{{ $reg['state'] === 'open' ? 'Register now' : 'See registration details' }} <i class="bi bi-arrow-right"></i></a>
      </div>
   </div>
   @endif
   <div class="bx-footer-main">
      <div class="container">
         <div class="row g-5">
            <div class="col-lg-4">
               <a class="bx-footer-logo" href="{{ route('home') }}"><img src="{{ asset('assets/frontend/images/logo.png') }}" alt="BICS — Stronger Together"></a>
               <p>{{ $f['about'] ?? '' }}</p>
               <div class="bx-social">
                  @foreach($social as $network => $icon)
                     @if(!empty($f[$network]) && $f[$network] !== '#')
                     <a href="{{ Cms::link($f[$network]) }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}"><i class="bi {{ $icon }}"></i></a>
                     @endif
                  @endforeach
               </div>
            </div>
            <div class="col-6 col-lg-2">
               <h4>Explore</h4>
               <ul>
                  @foreach($menu as $item)
                  <li><a href="{{ $item->href }}">{{ $item->title }}</a></li>
                  @endforeach
               </ul>
            </div>
            <div class="col-6 col-lg-2">
               <h4>Quick links</h4>
               <ul>
                  <li><a href="{{ route('registration') }}">Register for {{ Ev::get('event_short') }}</a></li>
                  <li><a href="{{ url('upcomming-bics#programme') }}">Programme</a></li>
                  <li><a href="{{ url('upcomming-bics#faq') }}">FAQ</a></li>
                  @foreach($f['quick_links'] ?? [] as $link)
                     @if(($link['url'] ?? '#') !== '#')
                     <li><a href="{{ Cms::link($link['url'] ?? '') }}">{{ $link['label'] ?? '' }}</a></li>
                     @endif
                  @endforeach
               </ul>
            </div>
            <div class="col-lg-4">
               <h4>Contact</h4>
               <ul class="bx-footer-contact">
                  @if(Cms::lines($f['phones'] ?? ''))
                  <li><i class="bi bi-telephone"></i><div>@foreach(Cms::lines($f['phones'] ?? '') as $phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a><br>@endforeach</div></li>
                  @endif
                  @if(Cms::lines($f['emails'] ?? ''))
                  <li><i class="bi bi-envelope"></i><div>@foreach(Cms::lines($f['emails'] ?? '') as $email)<a href="mailto:{{ $email }}">{{ $email }}</a><br>@endforeach</div></li>
                  @endif
                  @if(!empty($f['address']))
                  <li><i class="bi bi-geo-alt"></i><div>{!! nl2br(e($f['address'])) !!}</div></li>
                  @endif
               </ul>
            </div>
         </div>
      </div>
   </div>
   <div class="bx-footer-bottom">
      <div class="container">
         <span>{{ str_replace('{year}', date('Y'), $f['copyright'] ?? '') }}</span>
         <span>Beacon International Cancer Summit · Stronger Together</span>
      </div>
   </div>
</footer>
<button class="bx-totop" type="button" aria-label="Back to top"><i class="bi bi-arrow-up"></i></button>
