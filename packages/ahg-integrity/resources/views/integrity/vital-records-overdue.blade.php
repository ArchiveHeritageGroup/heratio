@extends('theme::layouts.1col')
@section('title', 'Integrity - Overdue Vital Record Reviews')
@section('body-class', 'admin integrity vital-records-overdue')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-exclamation-triangle text-danger me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Overdue Vital Record Reviews') }}</h1><span class="small text-muted">{{ __('Records past their scheduled review date') }}</span></div>
  </div>
@endsection
@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">Overdue Reviews <span class="badge bg-danger ms-2">{{ count($records) }}</span></h5>
  </div>
  <div class="card-body p-0">
    @if(count($records) > 0)
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <th>{{ __('ID') }}</th><th>{{ __('IO ID') }}</th><th>{{ __('IO Title') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Review Cycle') }}</th><th>{{ __('Due Date') }}</th><th>{{ __('Days Overdue') }}</th><th>{{ __('Last Reviewed') }}</th><th>{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($records as $rec)
        @php
          $daysOverdue = \Carbon\Carbon::parse($rec->next_review_date)->diffInDays(now());
        @endphp
        <tr>
          <td>{{ $rec->id }}</td>
          <td><a href="{{ url('/informationobject/show/' . $rec->information_object_id) }}">#{{ $rec->information_object_id }}</a></td>
          <td>{{ $rec->io_title ?? '-' }}</td>
          <td>{{ \Illuminate\Support\Str::limit($rec->reason, 50) }}</td>
          <td>{{ $rec->review_cycle_days }} days</td>
          <td><span class="badge bg-danger">{{ $rec->next_review_date }}</span></td>
          <td><strong class="text-danger">{{ $daysOverdue }} days</strong></td>
          <td>{{ $rec->last_reviewed_at ?? 'Never' }}</td>
          <td>
            <form method="POST" action="{{ route('integrity.vital-records.review', $rec->id) }}">
              @csrf
              <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>{{ __('Mark Reviewed') }}</button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
    <div class="text-center py-4 text-muted">No overdue reviews. All vital records are up to date.</div>
    @endif
  </div>
</div>

<div class="mt-3">
  <a href="{{ route('integrity.vital-records') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Vital Records') }}</a>
  <a href="{{ route('integrity.index') }}" class="btn atom-btn-white ms-2"><i class="fas fa-shield-alt me-1"></i>{{ __('Dashboard') }}</a>
</div>
@endsection
