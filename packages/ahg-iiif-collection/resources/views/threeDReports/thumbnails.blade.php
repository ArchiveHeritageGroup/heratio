{{-- 3D Thumbnails Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', '3D Thumbnails Report')
@section('body-class', 'admin three-d-reports thumbnails')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('iiif.three-d-reports.index') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>Back</a></div>@endsection
@section('title-block')<h1><i class="fas fa-image me-2"></i>3D Thumbnails Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> models with thumbnails</div>
<div class="row">
  @forelse($items as $m)
  <div class="col-md-3 mb-3">
    <div class="card h-100">
      @if($m->thumbnail_path)
      <img src="{{ asset($m->thumbnail_path) }}" class="card-img-top" alt="{{ e($m->name ?? '') }}" style="height:150px;object-fit:cover">
      @else
      <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:150px"><i class="fas fa-cube fa-3x text-muted"></i></div>
      @endif
      <div class="card-body py-2">
        <h6 class="card-title mb-1">{{ e($m->name ?? '-') }}</h6>
        <small class="text-muted">{{ strtoupper($m->format ?? '') }}</small>
      </div>
    </div>
  </div>
  @empty
  <div class="col-12"><div class="alert alert-warning">No thumbnails found.</div></div>
  @endforelse
</div>
@endsection
