{{-- 3D Models Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', '3D Models Report')
@section('body-class', 'admin three-d-reports models')
@section('sidebar')
<div class="sidebar-content">
  <h4>{{ __('3D Reports') }}</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('iiif.three-d-reports.models') }}"><i class="fas fa-cube me-2"></i>3D Models</a></li>
    <li><a href="{{ route('iiif.three-d-reports.hotspots') }}"><i class="fas fa-map-pin me-2"></i>{{ __('Hotspots') }}</a></li>
    <li><a href="{{ route('iiif.three-d-reports.thumbnails') }}"><i class="fas fa-image me-2"></i>{{ __('Thumbnails') }}</a></li>
    <li><a href="{{ route('iiif.three-d-reports.digital-objects') }}"><i class="fas fa-file me-2"></i>3D Files</a></li>
  </ul>
  <hr>
  <a href="{{ route('iiif.three-d-reports.index') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back to Dashboard') }}</a>
</div>
@endsection
@section('title-block')<h1><i class="fas fa-cube me-2"></i>3D Models Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> models found</div>
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark"><tr><th>{{ __('Model') }}</th><th>{{ __('Object') }}</th><th>{{ __('Format') }}</th><th>{{ __('Size') }}</th><th>{{ __('Thumb') }}</th><th>{{ __('AR') }}</th><th>{{ __('Public') }}</th></tr></thead>
    <tbody>
      @forelse($items as $m)
      <tr>
        <td><strong>{{ e($m->name ?? '-') }}</strong></td>
        <td>{{ e($m->object_title ?? '-') }}</td>
        <td><code>{{ strtoupper($m->format ?? '-') }}</code></td>
        <td class="text-end">@php $b=$m->file_size??0;$u=['B','KB','MB','GB'];$p=floor(($b?log($b):0)/log(1024));echo round($b/pow(1024,min($p,3)),1).' '.$u[min($p,3)]; @endphp</td>
        <td class="text-center">@if($m->thumbnail_path)<i class="fas fa-check text-success"></i>@else<i class="fas fa-times text-muted"></i>@endif</td>
        <td class="text-center">@if($m->ar_enabled ?? false)<i class="fas fa-check text-success"></i>@else<i class="fas fa-times text-muted"></i>@endif</td>
        <td class="text-center">@if($m->is_public ?? true)<i class="fas fa-check text-success"></i>@else<i class="fas fa-times text-muted"></i>@endif</td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-muted text-center py-4">No 3D models found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
