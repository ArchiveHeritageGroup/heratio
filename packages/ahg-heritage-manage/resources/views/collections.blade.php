@extends('theme::layouts.1col')
@section('title', 'Featured Collections')
@section('body-class', 'heritage')

@section('content')
<div class="heritage-collections py-4">
  <div class="container">
    <div class="row mb-4">
      <div class="col-12">
        <h1 class="display-5 fw-bold mb-3"><i class="fas fa-layer-group me-2"></i>Featured Collections</h1>
        <p class="lead text-muted">Curated collections highlighting our most significant holdings</p>
      </div>
    </div>

    @if(!empty($collections))
    <div class="row g-4">
      @foreach($collections as $collection)
      <div class="col-md-6 {{ ($collection['is_featured'] ?? false) ? 'col-lg-6' : 'col-lg-4' }}">
        <div class="card h-100" @if($collection['background_color'] ?? false) style="border-left:4px solid {{ $collection['background_color'] }}" @endif>
          @if($collection['cover_image'] ?? false)
          <div class="card-img-top position-relative" style="height:{{ ($collection['is_featured'] ?? false) ? '250px' : '180px' }};background:url('{{ $collection['cover_image'] }}') center/cover;">
            @if($collection['is_featured'] ?? false)<span class="position-absolute top-0 start-0 badge bg-warning text-dark m-2"><i class="fas fa-star me-1"></i>{{ __('Featured') }}</span>@endif
          </div>
          @endif
          <div class="card-body">
            <h3 class="card-title h5">{{ $collection['title'] }}</h3>
            @if($collection['subtitle'] ?? false)<p class="card-subtitle text-muted mb-2">{{ $collection['subtitle'] }}</p>@endif
            @if($collection['description'] ?? false)<p class="card-text">{{ substr($collection['description'], 0, 200) }}...</p>@endif
            @if($collection['curator_note'] ?? false)
            <div class="border-start border-3 ps-3 mt-3 bg-light p-2 rounded" style="border-color:var(--ahg-primary)!important"><small class="text-muted"><i class="fas fa-quote-left me-1"></i>{{ substr($collection['curator_note'], 0, 100) }}...</small></div>
            @endif
          </div>
          <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
            <div>
              @if(($collection['item_count'] ?? 0) > 0)<span class="badge bg-secondary me-2">{{ number_format($collection['item_count']) }} items</span>@endif
              @if(($collection['image_count'] ?? 0) > 0)<span class="badge bg-info">{{ number_format($collection['image_count']) }} images</span>@endif
            </div>
            @if(($collection['link_type'] ?? '') === 'search' && ($collection['link_reference'] ?? ''))
            <a href="{{ route('heritage.search', ['q' => $collection['link_reference']]) }}" class="btn btn-outline-primary btn-sm">Explore <i class="fas fa-arrow-right"></i></a>
            @elseif(($collection['link_type'] ?? '') === 'collection' && ($collection['link_reference'] ?? ''))
            <a href="{{ $collection['link_reference'] }}" class="btn btn-outline-primary btn-sm">View Collection <i class="fas fa-arrow-right"></i></a>
            @endif
          </div>
        </div>
      </div>
      @endforeach
    </div>
    @else
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No featured collections available yet. Check back soon!</div>
    @endif
  </div>
</div>
@endsection
