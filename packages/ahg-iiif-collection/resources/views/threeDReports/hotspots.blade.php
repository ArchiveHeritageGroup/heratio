{{-- Hotspots Report — cloned from AtoM. @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', 'Hotspots Report')
@section('body-class', 'admin three-d-reports hotspots')
@section('sidebar')<div class="sidebar-content"><a href="{{ route('iiif.three-d-reports.index') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>Back</a></div>@endsection
@section('title-block')<h1><i class="fas fa-map-pin me-2"></i>Hotspots Report</h1>@endsection
@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> hotspots found</div>
<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark"><tr><th>Title</th><th>Type</th><th>Model</th><th>Object</th><th>Position</th><th>Visible</th></tr></thead>
    <tbody>
      @forelse($items as $h)
      <tr>
        <td><strong>{{ e($h->title ?? '-') }}</strong></td>
        <td><span class="badge bg-secondary">{{ $h->type ?? '-' }}</span></td>
        <td>{{ e($h->model_name ?? '-') }}</td>
        <td>{{ e($h->object_title ?? '-') }}</td>
        <td><code>{{ $h->position_x ?? '?' }}, {{ $h->position_y ?? '?' }}, {{ $h->position_z ?? '?' }}</code></td>
        <td class="text-center">@if($h->is_visible ?? true)<i class="fas fa-check text-success"></i>@else<i class="fas fa-times text-muted"></i>@endif</td>
      </tr>
      @empty
      <tr><td colspan="6" class="text-muted text-center py-4">No hotspots found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
