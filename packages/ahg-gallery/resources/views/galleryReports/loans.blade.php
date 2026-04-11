{{--
  Loans Report — cloned from AtoM galleryReports/loansSuccess.php
  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Loans Report')
@section('body-class', 'gallery-reports loans')

@section('sidebar')
<div class="sidebar-content">
  <a href="{{ route('gallery-reports.index') }}" class="btn btn-outline-primary btn-sm w-100 mb-3"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
</div>
@endsection

@section('title-block')
<h1><i class="fas fa-exchange-alt me-2"></i>Loans Report</h1>
@endsection

@section('content')
<div class="alert alert-info"><strong>{{ count($items) }}</strong> loans found</div>

<div class="table-responsive">
  <table class="table table-striped table-hover">
    <thead class="table-dark">
      <tr>
        <th>Loan #</th>
        <th>Type</th>
        <th>Institution</th>
        <th>Status</th>
        <th>Dates</th>
        <th>Objects</th>
        <th>Insurance</th>
        <th>Days</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $l)
      <tr>
        <td><strong>{{ $l->loan_number ?? $l->id }}</strong></td>
        <td>{{ ucfirst($l->loan_type ?? $l->direction ?? '-') }}</td>
        <td>{{ e($l->institution_name ?? $l->borrower ?? $l->lender ?? '-') }}</td>
        <td><span class="badge bg-{{ ($l->status ?? '') === 'active' ? 'success' : (($l->status ?? '') === 'overdue' ? 'danger' : 'secondary') }}">{{ ucfirst($l->status ?? '-') }}</span></td>
        <td>{{ $l->start_date ? date('d M Y', strtotime($l->start_date)) : '-' }}@if(!empty($l->end_date)) - {{ date('d M Y', strtotime($l->end_date)) }}@endif</td>
        <td class="text-center">{{ $l->object_count ?? 0 }}</td>
        <td class="text-end">{{ $l->insurance_value ? 'R ' . number_format($l->insurance_value, 2) : '-' }}</td>
        <td class="text-center">{{ $l->start_date && $l->end_date ? \Carbon\Carbon::parse($l->start_date)->diffInDays($l->end_date) : '-' }}</td>
      </tr>
      @empty
      <tr><td colspan="8" class="text-center text-muted py-4">No loans found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
