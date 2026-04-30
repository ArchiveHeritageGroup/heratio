@extends('theme::layouts.1col')
@section('title', isset($currentPeriod) ? ($currentPeriod->name ?? '').' - Timeline' : 'Historical Timeline')
@section('body-class', 'heritage')

@section('content')
<div class="heritage-timeline py-4">
  <div class="container">
    @if(!isset($currentPeriod))
    <div class="row mb-4"><div class="col-12"><h1 class="display-5 fw-bold mb-3"><i class="fas fa-history me-2"></i>{{ __('Journey Through Time') }}</h1><p class="lead text-muted">Explore our collections by historical period</p></div></div>

    @if(!empty($periods ?? []))
    <div class="row g-4">
      @foreach($periods as $period)
      <div class="col-md-6 col-lg-4">
        <a href="{{ route('heritage.timeline', ['period_id' => $period->id]) }}" class="card h-100 text-decoration-none" @if($period->background_color ?? null) style="border-left:4px solid {{ $period->background_color }}" @endif>
          @if($period->cover_image ?? false)<div class="card-img-top" style="height:120px;background:url('{{ $period->cover_image }}') center/cover"></div>@endif
          <div class="card-body">
            <h3 class="card-title h5">{{ $period->name }}</h3>
            <p class="card-subtitle mb-2" style="color:var(--ahg-primary)">{{ $period->year_label ?? '' }}</p>
            @if($period->description ?? false)<p class="card-text small text-muted">{{ substr($period->description,0,150) }}...</p>@endif
            @if(($period->item_count ?? 0) > 0)<span class="badge bg-secondary">{{ number_format($period->item_count) }} items</span>@endif
          </div>
        </a>
      </div>
      @endforeach
    </div>
    @endif

    @else
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-4"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('heritage.landing') }}">Heritage</a></li><li class="breadcrumb-item"><a href="{{ route('heritage.timeline') }}">Timeline</a></li><li class="breadcrumb-item active">{{ $currentPeriod->name ?? '' }}</li></ol></nav>

    <div class="row mb-4"><div class="col-12">
      <h1 class="display-5 fw-bold mb-2">{{ $currentPeriod->name ?? '' }}</h1>
      <p class="h4 mb-3" style="color:var(--ahg-primary)">{{ $currentPeriod->year_label ?? '' }}</p>
      @if($currentPeriod->description ?? false)<p class="lead text-muted">{{ $currentPeriod->description }}</p>@endif
      @if(isset($totalItems))<p class="text-muted">{{ number_format($totalItems) }} items from this period</p>@endif
    </div></div>

    @if(!empty($periods ?? []))
    <div class="d-flex flex-wrap gap-2 mb-4">
      @foreach($periods as $p)
      <a href="{{ route('heritage.timeline', ['period_id'=>$p->id]) }}" class="btn btn-sm {{ $p->id==($currentPeriod->id ?? null) ? 'atom-btn-secondary' : 'atom-btn-white' }}">{{ $p->short_name ?? $p->name }}</a>
      @endforeach
    </div>
    @endif

    @if(!empty($items))
    <div class="row g-3">
      @foreach($items as $item)
      <div class="col-6 col-md-4 col-lg-3">
        <a href="{{ route('informationobject.show', $item->slug ?? '#') }}" class="card h-100 text-decoration-none">
          @if(!empty($item->thumbnail))<img src="{{ $item->thumbnail }}" class="card-img-top" alt="{{ $item->title }}" style="height:150px;object-fit:cover">
          @else<div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:150px"><i class="fas fa-file text-muted" style="font-size:3rem"></i></div>@endif
          <div class="card-body"><h5 class="card-title h6">{{ substr($item->title ?? '',0,60) }}</h5></div>
        </a>
      </div>
      @endforeach
    </div>

    @if(isset($totalPages) && $totalPages > 1)
    <nav class="mt-4"><ul class="pagination justify-content-center">
      @if(($page ?? 1) > 1)<li class="page-item"><a class="page-link" href="?period_id={{ $currentPeriod->id }}&page={{ $page - 1 }}">Previous</a></li>@endif
      @for($i = max(1, ($page ?? 1) - 2); $i <= min($totalPages, ($page ?? 1) + 2); $i++)<li class="page-item {{ $i===($page ?? 1)?'active':'' }}"><a class="page-link" href="?period_id={{ $currentPeriod->id }}&page={{ $i }}">{{ $i }}</a></li>@endfor
      @if(($page ?? 1) < $totalPages)<li class="page-item"><a class="page-link" href="?period_id={{ $currentPeriod->id }}&page={{ $page + 1 }}">Next</a></li>@endif
    </ul></nav>
    @endif
    @else
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>{{ __('No items found for this period.') }}</div>
    @endif
    @endif
  </div>
</div>
@endsection
