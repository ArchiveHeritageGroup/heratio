@extends('theme::layouts.1col')
@section('title', 'Creators & People')
@section('body-class', 'heritage')

@section('content')
<div class="heritage-creators py-4">
  <div class="container">
    <div class="row mb-4">
      <div class="col-12">
        <h1 class="display-5 fw-bold mb-3"><i class="fas fa-users me-2"></i>{{ __('Creators & People') }}</h1>
        <p class="lead text-muted">Discover collections by the people who created them</p>
      </div>
    </div>

    <div class="row mb-4">
      <div class="col-12 col-md-8 col-lg-6">
        <form method="get" action="{{ route('heritage.creators') }}">
          <div class="input-group input-group-lg">
            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
            <input type="text" class="form-control border-start-0" name="q" value="{{ $searchQuery ?? '' }}" placeholder="{{ __('Search creators by name...') }}" autocomplete="off">
            @if(!empty($searchQuery))<a href="{{ route('heritage.creators') }}" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>@endif
            <button type="submit" class="btn atom-btn-secondary">{{ __('Search') }}</button>
          </div>
        </form>
      </div>
      <div class="col-12 col-md-4 col-lg-6 d-flex align-items-center mt-3 mt-md-0">
        @if(isset($totalItems))
        <span class="text-muted">@if(!empty($searchQuery))<i class="fas fa-filter me-1"></i>{{ number_format($totalItems) }} results for "{{ $searchQuery }}"@else{{ number_format($totalItems) }} creators@endif</span>
        @endif
      </div>
    </div>

    @if(!empty($creators))
    <div class="row g-4">
      @foreach($creators as $creator)
      <div class="col-md-6 col-lg-4">
        <a href="{{ route('actor.show', $creator['slug'] ?? $creator['id'] ?? '#') }}" class="card h-100 text-decoration-none">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="me-3"><i class="fas fa-user-circle" style="font-size:2.5rem;color:var(--ahg-primary)"></i></div>
              <div><h5 class="card-title mb-1">{{ $creator['name'] }}</h5>@if(isset($creator['count']))<span class="badge bg-secondary">{{ number_format($creator['count']) }} items</span>@endif</div>
            </div>
          </div>
          <div class="card-footer bg-transparent border-top-0"><small style="color:var(--ahg-primary)">View creator profile <i class="fas fa-arrow-right"></i></small></div>
        </a>
      </div>
      @endforeach
    </div>

    @if(isset($totalPages) && $totalPages > 1)
    <nav class="mt-4"><ul class="pagination justify-content-center">
      @if(($page ?? 1) > 1)<li class="page-item"><a class="page-link" href="?page={{ $page - 1 }}{{ !empty($searchQuery) ? '&q='.urlencode($searchQuery) : '' }}">Previous</a></li>@endif
      @for($i = max(1, ($page ?? 1) - 2); $i <= min($totalPages, ($page ?? 1) + 2); $i++)<li class="page-item {{ $i === ($page ?? 1) ? 'active' : '' }}"><a class="page-link" href="?page={{ $i }}{{ !empty($searchQuery) ? '&q='.urlencode($searchQuery) : '' }}">{{ $i }}</a></li>@endfor
      @if(($page ?? 1) < $totalPages)<li class="page-item"><a class="page-link" href="?page={{ $page + 1 }}{{ !empty($searchQuery) ? '&q='.urlencode($searchQuery) : '' }}">Next</a></li>@endif
    </ul></nav>
    @endif
    @else
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>{{ __('No creators found.') }}</div>
    @endif
  </div>
</div>
@endsection
