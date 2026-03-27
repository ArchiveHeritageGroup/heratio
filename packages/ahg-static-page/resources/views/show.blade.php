@php
  $hasMenu = view()->exists('ahg-menu-manage::_static-pages-menu');
@endphp

@extends($hasMenu ? 'theme::layouts.2col' : 'theme::layouts.1col')

@section('title', $page->title ?: __('Page'))
@section('body-class', 'show static-page')

@if($hasMenu)
  @section('sidebar')
    @include('ahg-menu-manage::_static-pages-menu')
  @endsection
@endif

@section('content')

  <h1>{{ $page->title ?: __('Untitled page') }}</h1>

  <div class="page p-3">
    <div>
      @if($page->content)
        {!! $page->content !!}
      @endif
    </div>
  </div>

  @auth
    @php $isAdmin = auth()->user()->is_admin; @endphp
    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        {{-- Edit: any authenticated user --}}
        <li><a class="btn atom-btn-outline-light" href="{{ route('staticpage.edit', $page->slug) }}">{{ __('Edit') }}</a></li>
        @php
          $protectedSlugs = ['home', 'about', 'contact'];
        @endphp
        {{-- Delete: admin only, and not for protected pages --}}
        @if($isAdmin && !in_array($page->slug, $protectedSlugs))
          <li><a class="btn atom-btn-outline-danger" href="{{ route('staticpage.delete', $page->slug) }}">{{ __('Delete') }}</a></li>
        @endif
      </ul>
    </section>
  @endauth

@endsection
