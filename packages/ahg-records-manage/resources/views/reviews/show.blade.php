{{--
  Records Management — Review detail + decision form (P2.4)

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Review #' . $review->id)
@section('body-class', 'admin records reviews show')

@section('content')
@php
  $today    = now()->toDateString();
  $isOpen   = $review->status !== 'completed' && $review->status !== 'cancelled';
  $overdue  = $isOpen && $review->review_due_date <= $today;
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0">
    <i class="fas fa-eye me-2"></i> Review #{{ $review->id }}
    <span class="badge bg-{{ $review->status === 'completed' ? 'success' : ($overdue ? 'danger' : 'warning text-dark') }} ms-2">
      {{ $review->status }}{{ $overdue ? ' (overdue)' : '' }}
    </span>
  </h1>
  <div>
    <a href="{{ route('records.reviews.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-list me-1"></i>Queue</a>
    <a href="{{ route('records.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Records dashboard</a>
  </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row">
  <div class="col-md-7">
    <div class="card mb-3">
      <div class="card-header bg-light">Record under review</div>
      <table class="table table-sm mb-0">
        <tr>
          <th class="text-muted" style="width:40%">{{ __('Record') }}</th>
          <td>
            @if($review->record_slug)
              <a href="{{ url('/' . $review->record_slug) }}">{{ $review->record_title ?: '[Untitled]' }}</a>
            @else
              {{ $review->record_title ?: ('IO #' . $review->information_object_id) }}
            @endif
          </td>
        </tr>
        <tr>
          <th class="text-muted">{{ __('Disposal class') }}</th>
          <td>
            @if($review->disposal_class_ref)
              <code>{{ $review->disposal_class_ref }}</code> — {{ $review->disposal_class_title }}
              <br><small class="text-muted">Action on disposal: {{ $review->disposal_class_action ?? '—' }} after {{ $review->disposal_class_years ? $review->disposal_class_years . ' year(s)' : 'n/a' }}</small>
            @else
              <em class="text-muted">none</em>
            @endif
          </td>
        </tr>
        <tr><th class="text-muted">{{ __('Review type') }}</th><td>{{ $review->review_type }}</td></tr>
        <tr><th class="text-muted">{{ __('Due date') }}</th><td>{{ $review->review_due_date }}{{ $overdue ? ' (overdue)' : '' }}</td></tr>
        @if($review->review_completed_date)
          <tr><th class="text-muted">{{ __('Completed') }}</th><td>{{ $review->review_completed_date }}</td></tr>
        @endif
        @if($review->next_review_due_date)
          <tr><th class="text-muted">{{ __('Next review due') }}</th><td>{{ $review->next_review_due_date }}</td></tr>
        @endif
        @if($review->decision)
          <tr><th class="text-muted">{{ __('Decision') }}</th><td><strong>{{ $review->decision }}</strong></td></tr>
        @endif
        @if($review->decision_notes)
          <tr><th class="text-muted">{{ __('Decision notes') }}</th><td>{!! nl2br(e($review->decision_notes)) !!}</td></tr>
        @endif
        @if($review->triggered_disposal_action_id)
          <tr><th class="text-muted">{{ __('Triggered disposal') }}</th>
              <td><a href="{{ url('/admin/records/disposal/' . $review->triggered_disposal_action_id) }}">action #{{ $review->triggered_disposal_action_id }}</a></td>
          </tr>
        @endif
      </table>
    </div>
  </div>

  <div class="col-md-5">
    @if($isOpen)
      <div class="card border-primary mb-3">
        <div class="card-header bg-primary text-white"><i class="fas fa-check-circle me-1"></i> Complete review</div>
        <div class="card-body">
          <form method="POST" action="{{ route('records.reviews.complete', $review->id) }}">
            @csrf
            <div class="mb-3">
              <label class="form-label">{{ __('Decision') }}</label>
              <select name="decision" class="form-select" required>
                <option value="">— pick a decision —</option>
                @foreach($decisions as $d)
                  <option value="{{ $d->code }}">{{ $d->label }}</option>
                @endforeach
              </select>
              <div class="form-text small">
                <strong>retain_extend / retain_review / no_change</strong> — record stays retained.<br>
                <strong>dispose</strong> — creates a destruction action in the disposal queue.<br>
                <strong>transfer</strong> — creates a transfer-to-archives action in the disposal queue.
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Notes (rationale)') }}</label>
              <textarea name="decision_notes" rows="4" class="form-control" placeholder="{{ __('Why this decision? Cite policy, legislation, business need.') }}"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Next review due') }}</label>
              <input type="date" name="next_review_due_date" class="form-control">
              <div class="form-text small">Optional. If retaining, schedule the next look at this record.</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i>Submit decision</button>
          </form>
        </div>
      </div>
    @else
      <div class="alert alert-secondary">
        <i class="fas fa-lock me-1"></i> Review closed — completed on {{ $review->review_completed_date }}.
      </div>
    @endif
  </div>
</div>
@endsection
