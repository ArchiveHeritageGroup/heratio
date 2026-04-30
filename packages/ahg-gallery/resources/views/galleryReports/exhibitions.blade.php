{{--
  Exhibitions Report — cloned from AtoM galleryReports/exhibitionsSuccess.php
  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Exhibitions Report')
@section('body-class', 'gallery-reports exhibitions')

@section('sidebar')
<div class="sidebar-content">
  <h4>{{ __('Filter Options') }}</h4>
  <form method="get" action="{{ route('gallery-reports.exhibitions') }}">
    <div class="mb-3">
      <label class="form-label">{{ __('Status') }}</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">{{ __('All') }}</option>
        @foreach(['planning','confirmed','installing','open','closing','closed','cancelled'] as $s)
          <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
        @endforeach
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('Apply Filters') }}</button>
    <a href="{{ route('gallery-reports.exhibitions') }}" class="btn btn-outline-secondary btn-sm w-100 mt-2">Clear</a>
  </form>
  <hr>
  <a href="{{ route('gallery-reports.index') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-images me-2"></i>Exhibitions Report</h1>
@endsection

@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> exhibitions found</div>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark">
      <tr>
        <th>{{ __('Title') }}</th>
        <th>{{ __('Type') }}</th>
        <th>{{ __('Status') }}</th>
        <th>{{ __('Venue') }}</th>
        <th>{{ __('Dates') }}</th>
        <th>{{ __('Objects') }}</th>
        <th>{{ __('Visitors') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $e)
      @php
        $statusColors = ['planning' => 'warning', 'confirmed' => 'info', 'installing' => 'primary', 'open' => 'success', 'closing' => 'warning', 'closed' => 'secondary', 'cancelled' => 'danger'];
        $color = $statusColors[$e->status ?? ''] ?? 'secondary';
      @endphp
      <tr>
        <td><strong>{{ e($e->title ?? $e->name ?? '') }}</strong>@if(!empty($e->subtitle))<br><small class="text-muted">{{ e($e->subtitle) }}</small>@endif</td>
        <td><span class="badge bg-secondary">{{ ucfirst($e->exhibition_type ?? $e->type ?? '-') }}</span></td>
        <td><span class="badge bg-{{ $color }}">{{ ucfirst($e->status ?? '-') }}</span></td>
        <td>{{ e($e->venue_name ?? '-') }}</td>
        <td>{{ $e->opening_date ? date('d M Y', strtotime($e->opening_date)) : ($e->start_date ? date('d M Y', strtotime($e->start_date)) : '-') }}@if(!empty($e->closing_date ?? $e->end_date)) - {{ date('d M Y', strtotime($e->closing_date ?? $e->end_date)) }}@endif</td>
        <td class="text-center">{{ $e->object_count ?? 0 }}</td>
        <td class="text-end">{{ number_format($e->actual_visitors ?? 0) }}</td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center text-muted py-4">No exhibitions found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
