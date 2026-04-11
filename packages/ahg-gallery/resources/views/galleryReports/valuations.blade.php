{{--
  Valuations Report — cloned from AtoM galleryReports/valuationsSuccess.php
  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Valuations Report')
@section('body-class', 'gallery-reports valuations')

@section('sidebar')
<div class="sidebar-content">
  <a href="{{ route('gallery-reports.index') }}" class="btn btn-outline-primary btn-sm w-100 mb-3"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-coins me-2"></i>Valuations Report</h1>
@endsection

@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> valuations found</div>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark">
      <tr>
        <th>Object</th>
        <th>Type</th>
        <th>Value</th>
        <th>Date</th>
        <th>Valid Until</th>
        <th>Appraiser</th>
        <th>Current</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $v)
      <tr>
        <td><strong>{{ e($v->object_name ?? $v->title ?? '-') }}</strong></td>
        <td>{{ ucfirst($v->valuation_type ?? $v->type ?? '-') }}</td>
        <td class="text-end">R {{ number_format($v->value ?? 0, 2) }}</td>
        <td>{{ $v->valuation_date ? date('d M Y', strtotime($v->valuation_date)) : ($v->created_at ? date('d M Y', strtotime($v->created_at)) : '-') }}</td>
        <td>{{ !empty($v->expiry_date) ? date('d M Y', strtotime($v->expiry_date)) : '-' }}</td>
        <td>{{ e($v->appraiser ?? $v->appraiser_name ?? '-') }}</td>
        <td class="text-center">
          @if($v->is_current ?? false)
            <span class="badge bg-success">Yes</span>
          @else
            <span class="badge bg-secondary">No</span>
          @endif
        </td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-center text-muted py-4">No valuations found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
