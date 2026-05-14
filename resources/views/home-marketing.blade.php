@extends('theme::layouts.1col')

@section('title-block')
  @if($page)
    <h1>{{ $page->title }}</h1>
  @endif
@endsection

@push('css')
  <meta name="description" content="Heratio is the world's first fully integrated open-source platform for galleries, libraries, archives, and museums. Built by archives professionals, AGPL-licensed, available in 18 languages.">
@endpush

@section('content')
  @php
    $homeParts = $page ? preg_split('/<!--\s*HERATIO_MIDPAGE\s*-->/', (string) ($page->content ?? ''), 2) : [''];
    $heroPart = $homeParts[0] ?? '';
  @endphp

  @if($page)
    <div class="page p-3">
      @auth
        @if(auth()->user()->is_admin)
          <div class="mb-2 text-end">
            <a href="{{ route('staticpage.edit', 'home') }}" class="btn btn-sm atom-btn-white">
              <i class="fas fa-pencil-alt me-1" aria-hidden="true"></i>{{ __('Edit') }}
            </a>
          </div>
        @endif
      @endauth
      {!! $heroPart !!}
    </div>
  @endif
@endsection
