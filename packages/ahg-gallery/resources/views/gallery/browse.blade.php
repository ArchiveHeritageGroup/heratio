@extends('theme::layouts.1col')

@section('title', 'Gallery artworks')
@section('body-class', 'browse gallery')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-palette me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">Gallery artworks</span>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search gallery artworks',
        'landmarkLabel' => 'Gallery artwork',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @if($repositories->isNotEmpty())
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
            {{ $selectedRepository ? ($repositoryNames[$selectedRepository] ?? 'Repository') : 'Repository' }}
          </button>
          <ul class="dropdown-menu">
            <li>
              <a class="dropdown-item {{ !$selectedRepository ? 'active' : '' }}"
                 href="{{ route('gallery.browse', array_merge(request()->except('repository', 'page'), [])) }}">
                All repositories
              </a>
            </li>
            @foreach($repositories as $repo)
              <li>
                <a class="dropdown-item {{ $selectedRepository == $repo->id ? 'active' : '' }}"
                   href="{{ route('gallery.browse', array_merge(request()->except('page'), ['repository' => $repo->id])) }}">
                  {{ $repo->name }}
                </a>
              </li>
            @endforeach
          </ul>
        </div>
      @endif

      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'alphabetic',
      ])

      @auth
        <a href="{{ route('gallery.create') }}" class="btn btn-sm btn-success">
          <i class="fas fa-plus me-1"></i> Add new
        </a>
      @endauth
    </div>
  </div>

  @if($pager->getNbResults())
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3 mb-3">
      @foreach($pager->getResults() as $doc)
        <div class="col">
          <div class="card h-100 shadow-sm">
            @if(!empty($doc['thumbnail']))
              <a href="{{ route('gallery.show', $doc['slug']) }}">
                <img src="/uploads/{{ $doc['thumbnail']->path }}/{{ $doc['thumbnail']->name }}"
                     class="card-img-top" alt="{{ $doc['name'] ?: 'Artwork' }}"
                     style="height: 200px; object-fit: cover;">
              </a>
            @else
              <a href="{{ route('gallery.show', $doc['slug']) }}" class="text-decoration-none">
                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                  <i class="fas fa-image fa-3x text-muted"></i>
                </div>
              </a>
            @endif
            <div class="card-body p-2">
              <h6 class="card-title mb-1">
                <a href="{{ route('gallery.show', $doc['slug']) }}" class="text-decoration-none">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
              </h6>
              @if(!empty($doc['creator_identity']))
                <p class="card-text text-muted small mb-1">
                  <i class="fas fa-user me-1"></i>{{ $doc['creator_identity'] }}
                </p>
              @endif
              @if(!empty($doc['work_type']) || !empty($doc['materials']))
                <p class="card-text text-muted small mb-1">
                  @if(!empty($doc['work_type']))
                    <span class="badge bg-secondary me-1">{{ $doc['work_type'] }}</span>
                  @endif
                  @if(!empty($doc['materials']))
                    {{ $doc['materials'] }}
                  @endif
                </p>
              @endif
              @if(!empty($doc['creation_date_display']))
                <p class="card-text text-muted small mb-0">
                  <i class="fas fa-calendar me-1"></i>{{ $doc['creation_date_display'] }}
                </p>
              @endif
            </div>
            @if(!empty($doc['identifier']))
              <div class="card-footer bg-transparent p-2">
                <small class="text-muted">{{ $doc['identifier'] }}</small>
              </div>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])
@endsection
