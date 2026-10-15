@extends('my.layout')
@section('title', 'Sign in')
@section('content')
  <div class="mb-2" style="font-size:2.4rem;color:var(--blue)"><i class="bi bi-shield-check"></i></div>
  <h1 style="font-size:1.6rem;font-weight:800">One more step</h1>
  <p class="muted">Press the button to finish signing in on this device.</p>
  <form method="post" action="{{ route('my.login.use') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <button class="btn btn-primary btn-lg w-100" autofocus>Sign in to my profile</button>
  </form>
  <p class="small muted mt-3">The link works once and only for {{ \App\Support\ParticipantAccess::LINK_MINUTES }} minutes. Didn't ask to sign in? Just close this page.</p>
@endsection
