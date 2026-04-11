{{--
  Spaces Report — cloned from AtoM galleryReports/spacesSuccess.php
  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Spaces Report')
@section('body-class', 'gallery-reports spaces')

@section('sidebar')
<div class="sidebar-content">
  <a href="{{ route('gallery-reports.index') }}" class="btn btn-outline-primary btn-sm w-100 mb-3"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-th-large me-2"></i>Spaces Report</h1>
@endsection

@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> spaces found</div>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark">
      <tr>
        <th>Space</th>
        <th>Venue</th>
        <th>Area (m&sup2;)</th>
        <th>Wall Length (m)</th>
        <th>Height (m)</th>
        <th>Climate</th>
        <th>Max Weight (kg)</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $s)
      <tr>
        <td><strong>{{ e($s->name ?? '-') }}</strong></td>
        <td>{{ e($s->venue_name ?? '-') }}</td>
        <td class="text-end">{{ $s->area_sqm ?? $s->floor_area ?? '-' }}</td>
        <td class="text-end">{{ $s->wall_length ?? '-' }}</td>
        <td class="text-end">{{ $s->ceiling_height ?? $s->height ?? '-' }}</td>
        <td class="text-center">
          @if($s->climate_controlled ?? false)<i class="fas fa-check text-success"></i>@else<i class="fas fa-times text-muted"></i>@endif
        </td>
        <td class="text-end">{{ $s->max_weight_kg ?? $s->max_weight ?? '-' }}</td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center text-muted py-4">No spaces found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
