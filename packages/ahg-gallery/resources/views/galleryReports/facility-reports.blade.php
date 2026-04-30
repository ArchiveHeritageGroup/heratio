{{--
  Facility Reports — cloned from AtoM galleryReports/facilityReportsSuccess.php
  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Facility Reports')
@section('body-class', 'gallery-reports facility-reports')

@section('sidebar')
<div class="sidebar-content">
  <a href="{{ route('gallery-reports.index') }}" class="btn btn-outline-primary btn-sm w-100 mb-3"><i class="fas fa-arrow-left me-2"></i>{{ __('Back to Dashboard') }}</a>
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-building me-2"></i>{{ __('Facility Reports') }}</h1>
@endsection

@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> facility reports found</div>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark">
      <tr>
        <th>{{ __('Institution') }}</th>
        <th>{{ __('Loan') }}</th>
        <th>{{ __('Type') }}</th>
        <th>{{ __('Fire') }}</th>
        <th>{{ __('Climate') }}</th>
        <th>{{ __('Security') }}</th>
        <th>{{ __('Handlers') }}</th>
        <th>{{ __('Approved') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $r)
      <tr>
        <td><strong>{{ e($r->institution_name ?? $r->venue_name ?? '-') }}</strong></td>
        <td>{{ $r->loan_number ?? $r->loan_id ?? '-' }}</td>
        <td>{{ ucfirst($r->report_type ?? $r->type ?? '-') }}</td>
        <td class="text-center">
          @if($r->fire_safety ?? false)<i class="fas fa-check text-success"></i>@else<i class="fas fa-times text-danger"></i>@endif
        </td>
        <td class="text-center">
          @if($r->climate_control ?? false)<i class="fas fa-check text-success"></i>@else<i class="fas fa-times text-danger"></i>@endif
        </td>
        <td class="text-center">
          @if($r->security ?? false)<i class="fas fa-check text-success"></i>@else<i class="fas fa-times text-danger"></i>@endif
        </td>
        <td class="text-center">
          @if($r->trained_handlers ?? false)<i class="fas fa-check text-success"></i>@else<i class="fas fa-times text-danger"></i>@endif
        </td>
        <td class="text-center">
          @if($r->approved ?? $r->is_approved ?? false)
            <span class="badge bg-success">{{ __('Yes') }}</span>
          @else
            <span class="badge bg-warning">{{ __('Pending') }}</span>
          @endif
        </td>
      </tr>
      @empty
      <tr><td colspan="8" class="text-center text-muted py-4">No facility reports found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
