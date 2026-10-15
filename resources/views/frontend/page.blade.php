@extends('frontend.layouts.content')

@section('title'){{ $page->meta_title ?: $page->title }}@endsection

@if($page->meta_description)
@section('meta')
      <meta name="description" content="{{ $page->meta_description }}">
@endsection
@endif

@section('content')
@foreach($page->activeSections as $section)
   @includeIf('frontend.sections.' . $section->type, ['c' => $section->content ?? [], 'section' => $section])
@endforeach
@endsection
