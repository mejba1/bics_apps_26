@extends('backend.layouts.master')
@section('title', 'Notifications')
@push('styles')
<style>
  .nt-card { border: 1px solid var(--ad-line); border-radius: 18px; background: var(--ad-surface); }
  .nt-h { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 16px 18px 6px; }
  .nt-h h3 { font-size: 1rem; font-weight: 800; margin: 0; color: var(--ad-ink); } .nt-h small { color: var(--ad-muted); }
  .nt-b { padding: 6px 18px 16px; }
  .nt-item { display: flex; gap: 12px; align-items: flex-start; padding: 12px 0; border-top: 1px solid var(--ad-line-soft); text-decoration: none; color: inherit; }
  .nt-item:first-child { border-top: 0; }
  .nt-item:hover .nt-t b { color: var(--ad-brand); }
  .nt-ic { width: 40px; height: 40px; border-radius: 12px; flex: none; display: inline-flex; align-items: center; justify-content: center; font-size: 1.05rem; }
  .tone-brand { background: var(--ad-brand-50); color: var(--ad-brand); } .tone-green { background: var(--ad-green-50); color: var(--ad-green); }
  .tone-magenta { background: rgba(178,46,107,.12); color: #b22e6b; } .tone-red { background: var(--ad-red-50); color: var(--ad-red); } .tone-amber { background: var(--ad-amber-50); color: var(--ad-amber); }
  .nt-t { flex: 1; min-width: 0; } .nt-t b { color: var(--ad-ink); display: block; } .nt-t span { font-size: 13px; color: var(--ad-muted); }
  .nt-at { font-size: 12px; color: var(--ad-muted); white-space: nowrap; }
  .nt-msg { border-top: 1px solid var(--ad-line-soft); padding: 14px 0; scroll-margin-top: 90px; }
  .nt-msg:first-child { border-top: 0; }
  .nt-msg:target { background: var(--ad-brand-50); border-radius: 12px; padding: 14px; }
  .nt-msg .who { display: flex; flex-wrap: wrap; gap: 6px 14px; align-items: baseline; }
  .nt-msg .who b { color: var(--ad-ink); } .nt-msg .who a, .nt-msg .who span { font-size: 13px; }
  .nt-msg p { margin: 8px 0 0; white-space: pre-wrap; color: var(--ad-text); font-size: 14px; }
  .nt-subj { display: inline-block; font-size: 12px; font-weight: 700; background: var(--ad-chip); border-radius: 7px; padding: 2px 8px; color: var(--ad-text); }
  .nt-empty { text-align: center; color: var(--ad-muted); padding: 34px 10px; } .nt-empty .big { font-size: 2.4rem; }
</style>
@endpush
@section('content')
<div class="pagetitle">
  <h1>Notifications</h1>
  <nav><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ url('/home') }}">Home</a></li><li class="breadcrumb-item active">Notifications</li></ol></nav>
</div>
<section class="section">
  <div class="row g-3">
    <div class="col-xl-{{ $messages->count() ? 6 : 12 }}">
      <div class="nt-card">
        <div class="nt-h"><div><h3>Recent activity</h3><small>Last {{ \App\Support\AdminNotifications::DAYS }} days · {{ optional(\App\Support\PinnedEvent::event())->event_short }}</small></div></div>
        <div class="nt-b">
          @forelse($items as $n)
            <a class="nt-item" href="{{ $n['url'] }}">
              <span class="nt-ic tone-{{ $n['tone'] }}"><i class="bi {{ $n['icon'] }}"></i></span>
              <span class="nt-t"><b>{{ $n['title'] }}</b><span>{{ $n['text'] }}</span></span>
              <span class="nt-at">{{ \App\Support\AdminNotifications::ago($n['at']) }}</span>
            </a>
          @empty
            <div class="nt-empty"><div class="big">🔔</div><b style="color:var(--ad-ink)">All quiet</b><br>New registrations, check-ins and messages appear here.</div>
          @endforelse
        </div>
      </div>
    </div>
    @if($messages->count())
    <div class="col-xl-6">
      <div class="nt-card">
        <div class="nt-h"><div><h3><i class="bi bi-envelope-paper"></i> Website messages</h3><small>Sent with the contact form · latest 50</small></div></div>
        <div class="nt-b">
          @foreach($messages as $m)
            <div class="nt-msg" id="msg-{{ $m->id }}">
              <div class="who"><b>{{ $m->name }}</b><a href="mailto:{{ $m->email }}?subject={{ rawurlencode('Re: ' . ($m->subject ?: 'Your message to BICS')) }}">{{ $m->email }}</a>
                @if($m->phone)<span class="text-muted"><i class="bi bi-telephone"></i> {{ $m->phone }}</span>@endif
                @if($m->country)<span class="text-muted"><i class="bi bi-geo-alt"></i> {{ $m->country }}</span>@endif
                <span class="text-muted ms-auto">{{ \Carbon\Carbon::parse($m->created_at)->format('j M Y, H:i') }}</span></div>
              @if($m->subject)<span class="nt-subj">{{ $m->subject }}</span>@endif
              <p>{{ $m->message }}</p>
            </div>
          @endforeach
        </div>
      </div>
    </div>
    @endif
  </div>
</section>
@endsection
