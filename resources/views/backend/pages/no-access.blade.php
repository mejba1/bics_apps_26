@extends('backend.layouts.master')
@section('title')
Welcome
@endsection
@section('content')
<section class="section">
  <div class="card text-center p-4 p-md-5 mx-auto" style="max-width:560px">
    <i class="bi bi-shield-lock text-primary" style="font-size:3rem"></i>
    <h3 class="fw-bold mt-2">Welcome, {{ Auth::user()->name }}</h3>
    <p class="text-muted mb-1">Your role <b>{{ Auth::user()->role_name }}</b> does not have access to any admin pages yet.</p>
    <p class="text-muted">Please ask a Super Admin to give your role the permissions you need.</p>
    <div><a href="{{ route('account') }}" class="btn btn-outline-primary"><i class="bi bi-person"></i> My account</a></div>
  </div>
</section>
@endsection
