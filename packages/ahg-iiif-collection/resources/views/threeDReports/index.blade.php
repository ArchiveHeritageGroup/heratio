{{-- 3D Reports Dashboard — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', '3D Object Reports Dashboard')
@section('body-class', 'admin three-d-reports index')
@section('sidebar')
<div class="sidebar-content">
  <h4>3D Reports</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('iiif.three-d-reports.models') }}"><i class="fas fa-cube me-2"></i>3D Models</a></li>
    <li><a href="{{ route('iiif.three-d-reports.hotspots') }}"><i class="fas fa-map-pin me-2"></i>Hotspots</a></li>
    <li><a href="{{ route('iiif.three-d-reports.thumbnails') }}"><i class="fas fa-image me-2"></i>Thumbnails</a></li>
    <li><a href="{{ route('iiif.three-d-reports.digital-objects') }}"><i class="fas fa-file me-2"></i>3D Files</a></li>
    <li><a href="{{ route('iiif.three-d-reports.settings') }}"><i class="fas fa-cog me-2"></i>Viewer Settings</a></li>
  </ul>
</div>
@endsection
@section('title-block')<h1><i class="fas fa-cube me-2"></i>3D Object Reports Dashboard</h1>@endsection
@php
if (!function_exists('threeDFmtBytes')) { function threeDFmtBytes($b,$p=2){$u=['B','KB','MB','GB','TB'];$b=max($b,0);$w=floor(($b?log($b):0)/log(1024));$w=min($w,count($u)-1);return round($b/pow(1024,$w),$p).' '.$u[$w];} }
@endphp
@section('content')
<div class="row mb-4">
  <div class="col-md-3"><div class="card text-center bg-primary text-white"><div class="card-body"><h2>{{ number_format($stats['totalModels'] ?? 0) }}</h2><p class="mb-0">3D Models</p></div></div></div>
  <div class="col-md-3"><div class="card text-center bg-success text-white"><div class="card-body"><h2>{{ number_format($stats['digitalObjects3D'] ?? 0) }}</h2><p class="mb-0">3D Files</p></div></div></div>
  <div class="col-md-3"><div class="card text-center bg-info text-white"><div class="card-body"><h2>{{ number_format($stats['totalHotspots'] ?? 0) }}</h2><p class="mb-0">Hotspots</p></div></div></div>
  <div class="col-md-3"><div class="card text-center bg-warning text-dark"><div class="card-body"><h2>{{ threeDFmtBytes($stats['totalSize'] ?? 0) }}</h2><p class="mb-0">Total Size</p></div></div></div>
</div>
<div class="row mb-4">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>By Format</h5></div>
      <ul class="list-group list-group-flush">
        @forelse($stats['byFormat'] ?? [] as $f)
        <li class="list-group-item d-flex justify-content-between"><code>.{{ strtoupper($f->format ?? '') }}</code> <span class="badge bg-primary">{{ $f->count ?? 0 }}</span></li>
        @empty
        <li class="list-group-item text-muted">No models yet</li>
        @endforelse
      </ul>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Quick Links</h5></div>
      <div class="list-group list-group-flush">
        <a href="{{ route('iiif.three-d-reports.models') }}" class="list-group-item list-group-item-action"><i class="fas fa-cube me-2"></i>Browse All Models</a>
        <a href="{{ route('iiif.three-d-reports.hotspots') }}" class="list-group-item list-group-item-action"><i class="fas fa-map-pin me-2"></i>All Hotspots</a>
        <a href="{{ route('iiif.three-d-reports.digital-objects') }}" class="list-group-item list-group-item-action"><i class="fas fa-file me-2"></i>3D File Inventory</a>
      </div>
    </div>
  </div>
</div>
@endsection
