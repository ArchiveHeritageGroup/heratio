{{--
  Heratio - Term related-authorities sidebar / page.

  Migrated from PSIS TermRelatedAuthoritiesAction (#743).

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  Licensed under AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', $term->name ?? 'Term')
@section('body-class', 'view term related-authorities')

@section('content')
  <nav aria-label="{{ __('breadcrumb') }}" class="small mb-2">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item">
        <a href="{{ route('term.browse', ['taxonomy' => $term->taxonomy_id]) }}">{{ $taxonomyName }}</a>
      </li>
      <li class="breadcrumb-item">
        <a href="{{ route('term.show', $term->slug) }}">{{ $term->name }}</a>
      </li>
      <li class="breadcrumb-item active" aria-current="page">{{ __('Related authority records') }}</li>
    </ol>
  </nav>

  <h1>{{ $term->name }}</h1>

  <ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
      <a class="nav-link" href="{{ route('term.show', $term->slug) }}">{{ __('Details') }}</a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link active" aria-current="page" href="#">
        {{ __('Related authority records') }}
        <span class="badge atom-badge-secondary ms-1">{{ $total }}</span>
      </a>
    </li>
  </ul>

  <div class="d-flex flex-wrap gap-2 mb-3">
    @if($onlyDirect)
      @php $clearParams = request()->query(); unset($clearParams['onlyDirect'], $clearParams['page']); @endphp
      <a class="btn btn-sm atom-btn-white filter-tag"
         href="{{ route('term.relatedAuthorities', array_merge(['slug' => $term->slug], $clearParams)) }}">
        <span class="visually-hidden">{{ __('Remove filter:') }}</span>
        {{ __('Only results directly related') }}
        <i class="fas fa-times ms-2" aria-hidden="true"></i>
      </a>
    @else
      <a class="btn btn-sm atom-btn-white"
         href="{{ route('term.relatedAuthorities', ['slug' => $term->slug, 'onlyDirect' => 1]) }}">
        {{ __('Only results directly related') }}
      </a>
    @endif

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @foreach(['lastUpdated' => __('Date modified'), 'alphabetic' => __('Name'), 'identifier' => __('Identifier')] as $key => $label)
        @php $isActive = $sort === $key; @endphp
        <a class="btn btn-sm {{ $isActive ? 'btn-primary' : 'atom-btn-white' }}"
           href="{{ route('term.relatedAuthorities', array_merge(['slug' => $term->slug], request()->query(), ['sort' => $key])) }}">
          {{ $label }}
        </a>
      @endforeach
    </div>
  </div>

  @if($total === 0)
    <div class="p-3">
      {{ __('No actors or repositories are linked to this term.') }}
    </div>
  @else
    @if($actors->count())
      <h2 class="h5 mt-3">{{ __('Actors') }} <small class="text-muted">({{ $totalActors }})</small></h2>
      <ul class="list-group mb-4">
        @foreach($actors as $a)
          <li class="list-group-item">
            <a href="{{ route('actor.show', $a->slug) }}" class="fw-semibold">
              {{ $a->authorized_form_of_name ?? '(untitled)' }}
            </a>
            @if($a->description_identifier)
              <span class="badge atom-badge-secondary ms-2">{{ $a->description_identifier }}</span>
            @endif
            @if($a->dates_of_existence)
              <div class="small text-muted">{{ $a->dates_of_existence }}</div>
            @endif
          </li>
        @endforeach
      </ul>
    @endif

    @if($repositories->count())
      <h2 class="h5 mt-3">{{ __('Repositories') }} <small class="text-muted">({{ $totalRepositories }})</small></h2>
      <ul class="list-group">
        @foreach($repositories as $r)
          <li class="list-group-item">
            <a href="{{ route('repository.show', $r->slug) }}" class="fw-semibold">
              {{ $r->authorized_form_of_name ?? '(untitled)' }}
            </a>
            @if($r->description_identifier)
              <span class="badge atom-badge-secondary ms-2">{{ $r->description_identifier }}</span>
            @endif
          </li>
        @endforeach
      </ul>
    @endif

    @if($lastPage > 1)
      <nav class="mt-3" aria-label="{{ __('Pagination') }}">
        <ul class="pagination">
          @for($p = 1; $p <= $lastPage; $p++)
            <li class="page-item {{ $p === $page ? 'active' : '' }}">
              <a class="page-link"
                 href="{{ route('term.relatedAuthorities', array_merge(['slug' => $term->slug], request()->query(), ['page' => $p])) }}">
                {{ $p }}
              </a>
            </li>
          @endfor
        </ul>
      </nav>
    @endif
  @endif
@endsection
