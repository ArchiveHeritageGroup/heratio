@extends('theme::layouts.1col')
@section('title', isset($currentCategory) ? $currentCategory['name'].' - Explore' : 'Explore Our Collections')
@section('body-class', 'heritage')

@section('content')
<div class="heritage-explore py-4">
  <div class="container">
    @if(!isset($currentCategory))
    <div class="row mb-4"><div class="col-12"><h1 class="display-5 fw-bold mb-3">Explore Our Collections</h1><p class="lead text-muted">Discover archives through different perspectives</p></div></div>

    <div class="row g-4">
      @foreach($categories ?? [] as $cat)
      <div class="col-md-6 col-lg-4">
        <a href="{{ route('heritage.explore', ['category'=>$cat['code']]) }}" class="card h-100 text-decoration-none" @if($cat['background_color'] ?? false) style="background-color:{{ $cat['background_color'] }};color:{{ $cat['text_color'] ?? '#fff' }}" @endif>
          @if($cat['cover_image'] ?? false)<div class="card-img-top" style="height:150px;background:url('{{ $cat['cover_image'] }}') center/cover"></div>@endif
          <div class="card-body"><div class="d-flex align-items-center mb-2"><i class="{{ $cat['icon'] ?? 'fas fa-compass' }} fs-3 me-2"></i><h3 class="card-title h4 mb-0">{{ $cat['name'] }}</h3></div>@if($cat['tagline'] ?? false)<p class="card-text opacity-75">{{ $cat['tagline'] }}</p>@endif</div>
        </a>
      </div>
      @endforeach
    </div>

    @else
    <nav aria-label="breadcrumb" class="mb-4"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('heritage.landing') }}">Heritage</a></li><li class="breadcrumb-item"><a href="{{ route('heritage.explore') }}">Explore</a></li><li class="breadcrumb-item active">{{ $currentCategory['name'] }}</li></ol></nav>

    <div class="row mb-4"><div class="col-12">
      <h1 class="display-5 fw-bold mb-2"><i class="{{ $currentCategory['icon'] ?? 'fas fa-compass' }} me-2"></i>{{ $currentCategory['name'] }}</h1>
      @if($currentCategory['description'] ?? false)<p class="lead text-muted">{{ $currentCategory['description'] }}</p>@endif
      @if(isset($totalItems))<p class="text-muted">{{ number_format($totalItems) }} items found</p>@endif
    </div></div>

    @if(!empty($items))
      @if(($currentCategory['display_style'] ?? 'grid') === 'list')
      <div class="list-group">
        @foreach($items as $item)
        <a href="{{ route('heritage.search', [$currentCategory['source_reference'] ?? 'q' => $item['name']]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">{{ $item['name'] }}@if(isset($item['count']))<span class="badge bg-primary rounded-pill">{{ number_format($item['count']) }}</span>@endif</a>
        @endforeach
      </div>
      @else
      <div class="row g-3">
        @foreach($items as $item)
        <div class="col-6 col-md-4 col-lg-3">
          <a href="{{ route('heritage.search', [$currentCategory['source_reference'] ?? 'q' => $item['name']]) }}" class="card h-100 text-decoration-none"><div class="card-body text-center"><h5 class="card-title">{{ $item['name'] }}</h5>@if(isset($item['count']))<span class="badge bg-secondary">{{ number_format($item['count']) }} items</span>@endif</div></a>
        </div>
        @endforeach
      </div>
      @endif

      @if(isset($totalPages) && $totalPages > 1)
      <nav class="mt-4"><ul class="pagination justify-content-center">
        @if(($page ?? 1) > 1)<li class="page-item"><a class="page-link" href="?category={{ $currentCategory['code'] }}&page={{ $page - 1 }}">Previous</a></li>@endif
        @for($i=max(1,($page ?? 1)-2);$i<=min($totalPages,($page ?? 1)+2);$i++)<li class="page-item {{ $i===($page ?? 1)?'active':'' }}"><a class="page-link" href="?category={{ $currentCategory['code'] }}&page={{ $i }}">{{ $i }}</a></li>@endfor
        @if(($page ?? 1)<$totalPages)<li class="page-item"><a class="page-link" href="?category={{ $currentCategory['code'] }}&page={{ $page+1 }}">Next</a></li>@endif
      </ul></nav>
      @endif
    @else
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No items found in this category.</div>
    @endif
    @endif
  </div>
</div>
@endsection
