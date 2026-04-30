@extends('theme::layouts.1col')
@section('title', 'Trending Now')
@section('body-class', 'heritage')

@section('content')
<div class="heritage-trending py-4">
  <div class="container">
    <div class="row mb-4">
      <div class="col-12">
        <h1 class="display-5 fw-bold mb-3"><i class="fas fa-chart-line me-2"></i>{{ __('Trending Now') }}</h1>
        <p class="lead text-muted">Popular items being viewed this week</p>
      </div>
    </div>

    @if(!empty($items))
    <div class="row g-3">
      @foreach($items as $index => $item)
      <div class="col-6 col-md-4 col-lg-3">
        <a href="{{ route('informationobject.show', $item['slug'] ?? '#') }}" class="card h-100 text-decoration-none">
          <span class="position-absolute top-0 start-0 m-2 badge {{ $index < 3 ? 'bg-warning text-dark' : 'bg-secondary' }}">#{{ $index + 1 }}</span>
          @if(!empty($item['thumbnail']))
          <img src="{{ $item['thumbnail'] }}" class="card-img-top" alt="{{ $item['title'] }}" style="height:180px;object-fit:cover">
          @else
          <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:180px"><i class="fas fa-file-alt text-muted" style="font-size:3rem"></i></div>
          @endif
          <div class="card-body">
            <h5 class="card-title h6">{{ substr($item['title'],0,60) }}</h5>
            @if(isset($item['view_count']))<small class="text-muted"><i class="fas fa-eye me-1"></i>{{ number_format($item['view_count']) }} views</small>@endif
          </div>
        </a>
      </div>
      @endforeach
    </div>
    @else
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>{{ __('No trending data available yet. Browse some items to get started!') }}</div>
    @endif
  </div>
</div>
@endsection
