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
    @if(auth()->user()->is_admin && ($page->source_culture ?? null))
      <section class="border-bottom mb-3" id="adminArea">
        <h2 class="h5 mb-0 atom-section-header">
          <div class="d-flex p-3 border-bottom text-primary">
            Administration area
          </div>
        </h2>
        <div>
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Source language') }}</h3>
            <div class="col-9 p-2">
              @php
                $displayLang = function_exists('locale_get_display_language')
                  ? locale_get_display_language($page->source_culture, app()->getLocale())
                  : $page->source_culture;
              @endphp
              {{ $displayLang }}
            </div>
          </div>
        </div>
      </section>
    @endif
  @endauth

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
