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

  <div class="static-page-content mb-4">
    @if($page->content)
      {!! $page->content !!}
    @else
      <p class="text-muted mb-0">No content available.</p>
    @endif
  </div>

  @auth
    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        <li><a class="btn atom-btn-outline-light" href="{{ route('staticpage.edit', $page->slug) }}">Edit</a></li>
      </ul>
    </section>
  @endauth
@endsection
