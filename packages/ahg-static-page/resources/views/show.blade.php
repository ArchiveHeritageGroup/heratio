@extends('theme::layouts.1col')

@section('title', $page->title ?: 'Page')
@section('body-class', 'show static-page')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-file-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ $page->title ?: 'Untitled page' }}</h1>
      <span class="small text-muted">Static page</span>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      @if($page->content)
        {!! $page->content !!}
      @else
        <p class="text-muted mb-0">No content available.</p>
      @endif
    </div>
  </div>
@endsection
