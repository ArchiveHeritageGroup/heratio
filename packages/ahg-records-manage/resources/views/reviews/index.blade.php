{{--
  Records Management — Review Queue (P2.4)

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Review Queue')
@section('body-class', 'admin records reviews')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-eye me-2"></i> Review Queue</h1>
  <a href="{{ route('records.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Records dashboard') }}</a>
</div>

<p class="text-muted small">
  Records flagged for periodic review before final disposal. A reviewer accepts each record's next step:
  retain longer, schedule another review, transfer to archives, or trigger destruction.
</p>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row g-2 mb-3">
  <div class="col"><div class="card border-warning"><div class="card-body py-2">
    <small class="text-muted">{{ __('Pending') }}</small>
    <h4 class="mb-0">{{ $counts['pending'] }}</h4>
  </div></div></div>
  <div class="col"><div class="card border-danger"><div class="card-body py-2">
    <small class="text-muted">{{ __('Overdue') }}</small>
    <h4 class="mb-0 text-danger">{{ $counts['overdue'] }}</h4>
  </div></div></div>
  <div class="col"><div class="card border-info"><div class="card-body py-2">
    <small class="text-muted">{{ __('Due in 30 days') }}</small>
    <h4 class="mb-0">{{ $counts['due_30d'] }}</h4>
  </div></div></div>
  <div class="col"><div class="card border-success"><div class="card-body py-2">
    <small class="text-muted">{{ __('Completed') }}</small>
    <h4 class="mb-0 text-success">{{ $counts['completed'] }}</h4>
  </div></div></div>
</div>

<form method="GET" class="row g-2 align-items-end mb-3">
  <div class="col-md-3">
    <label class="form-label small mb-0">{{ __('Status') }}</label>
    <select name="status" class="form-select form-select-sm">
      <option value="">{{ __('All') }}</option>
      <option value="pending" @selected($filters['status']==='pending')>{{ __('Pending') }}</option>
      <option value="completed" @selected($filters['status']==='completed')>{{ __('Completed') }}</option>
      <option value="cancelled" @selected($filters['status']==='cancelled')>{{ __('Cancelled') }}</option>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label small mb-0">{{ __('Due before') }}</label>
    <input type="date" name="due_before" value="{{ $filters['due_before'] }}" class="form-control form-control-sm">
  </div>
  <div class="col-md-3">
    <label class="form-label small mb-0">{{ __('Search title') }}</label>
    <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="...">
  </div>
  <div class="col-md-3">
    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>{{ __('Filter') }}</button>
    <a href="{{ route('records.reviews.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
  </div>
</form>

<div class="card">
  <table class="table table-hover table-sm mb-0">
    <thead class="table-light">
      <tr>
        <th>{{ __('Record') }}</th>
        <th>{{ __('Disposal class') }}</th>
        <th>{{ __('Type') }}</th>
        <th>{{ __('Due') }}</th>
        <th>{{ __('Status') }}</th>
        <th>{{ __('Decision') }}</th>
        <th class="text-end">{{ __('Action') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        @php
          $today = now()->toDateString();
          $overdue = $r->status === 'pending' && $r->review_due_date <= $today;
        @endphp
        <tr class="{{ $overdue ? 'table-danger' : '' }}">
          <td>
            @if($r->record_slug)
              <a href="{{ url('/' . $r->record_slug) }}">{{ $r->record_title ?: '[Untitled]' }}</a>
            @else
              {{ $r->record_title ?: ('IO #' . $r->information_object_id) }}
            @endif
          </td>
          <td><small>{{ $r->disposal_class_ref ?? '—' }}<br>{{ $r->disposal_class_title ?? '' }}</small></td>
          <td><small>{{ $r->review_type }}</small></td>
          <td><small>{{ $r->review_due_date }}</small></td>
          <td><span class="badge bg-{{ $r->status === 'completed' ? 'success' : ($overdue ? 'danger' : 'warning text-dark') }}">{{ $r->status }}</span></td>
          <td><small>{{ $r->decision ?? '—' }}</small></td>
          <td class="text-end">
            <a href="{{ route('records.reviews.show', $r->id) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-arrow-right"></i></a>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-center text-muted py-4">
          No reviews scheduled. Reviews are auto-spawned when a record is assigned to a disposal class with <em>review_required = 1</em>.
        </td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="text-muted small mt-2">{{ $total }} review(s) total. Showing {{ count($rows) }}.</div>
@endsection
