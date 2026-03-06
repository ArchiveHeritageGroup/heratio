@extends('theme::layouts.1col')

@section('title', 'Taxonomies')
@section('body-class', 'browse taxonomy')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-sitemap me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Taxonomies</h1>
      <span class="small text-muted">{{ $taxonomies->count() }} taxonomies</span>
    </div>
  </div>

  <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
    @foreach($taxonomies as $taxonomy)
      <div class="col">
        <a href="{{ route('term.browse', ['taxonomy' => $taxonomy->id]) }}" class="text-decoration-none">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title mb-0">
                <i class="fas fa-tags me-2 text-muted" aria-hidden="true"></i>
                {{ $taxonomy->name }}
              </h5>
            </div>
          </div>
        </a>
      </div>
    @endforeach
  </div>
@endsection
