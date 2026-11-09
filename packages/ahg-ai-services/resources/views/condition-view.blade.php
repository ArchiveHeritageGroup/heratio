@extends('theme::layouts.1col')

{{-- One AI condition assessment, for a reviewer to confirm or dismiss (heratio#1558).
     Assessments sent through the API (an assistant's condition flag) land here as pending. --}}

@section('title', __('Condition assessment').' #'.$a->id)
@section('body-class', 'browse ai')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-robot me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Condition assessment') }} #{{ $a->id }}</h1>
      <span class="small text-muted">
        @if($a->object_slug)
          <a href="{{ url('/'.$a->object_slug) }}">{{ $a->object_title ?: __('[Untitled]') }}</a>
        @else
          {{ $a->object_title ?: __('[Untitled]') }}
        @endif
      </span>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
  @endif

  @php
    $badge = ['pending' => 'bg-warning text-dark', 'confirmed' => 'bg-success', 'dismissed' => 'bg-secondary'][$status] ?? 'bg-light text-dark';
    $statusLabel = ['pending' => __('Pending'), 'confirmed' => __('Confirmed'), 'dismissed' => __('Dismissed')][$status] ?? $status;
  @endphp

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header fw-bold">{{ __('Assessment') }}</div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-4">{{ __('Status') }}</dt>
            <dd class="col-sm-8"><span class="badge {{ $badge }}">{{ $statusLabel }}</span></dd>
            <dt class="col-sm-4">{{ __('Condition grade') }}</dt>
            <dd class="col-sm-8">{{ $a->condition_grade ? ($gradeLabels[$a->condition_grade] ?? $a->condition_grade) : '-' }}</dd>
            @if($a->overall_score !== null)
              <dt class="col-sm-4">{{ __('Score') }}</dt>
              <dd class="col-sm-8">{{ $a->overall_score }}</dd>
            @endif
            <dt class="col-sm-4">{{ __('Source') }}</dt>
            <dd class="col-sm-8">{{ $a->source === 'api' ? __('Sent through the API (an assistant\'s condition flag)') : ucfirst((string) $a->source) }}</dd>
            @if($a->model_version)
              <dt class="col-sm-4">{{ __('Model') }}</dt>
              <dd class="col-sm-8">{{ $a->model_version }}</dd>
            @endif
            <dt class="col-sm-4">{{ __('Proposed by') }}</dt>
            <dd class="col-sm-8">{{ $a->proposed_by ?: '-' }}, {{ $a->created_at }}</dd>
            @if($status !== 'pending')
              <dt class="col-sm-4">{{ __('Decided by') }}</dt>
              <dd class="col-sm-8">{{ $a->decided_by ?: '-' }}, {{ $a->confirmed_at }}</dd>
            @endif
          </dl>
          @if($a->recommendations)
            <h2 class="h6 mt-3">{{ __('Note') }}</h2>
            <p class="mb-0" style="white-space: pre-line">{{ $a->recommendations }}</p>
          @endif
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header fw-bold">{{ __('Damage') }} ({{ count($damages) }})</div>
        @if(count($damages))
          <div class="table-responsive">
            <table class="table table-bordered mb-0">
              <thead>
                <tr><th>{{ __('Type') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Location') }}</th><th>{{ __('Confidence') }}</th><th>{{ __('Description') }}</th></tr>
              </thead>
              <tbody>
                @foreach($damages as $d)
                  <tr>
                    <td>{{ $typeLabels[$d->damage_type] ?? $d->damage_type }}</td>
                    <td>{{ $d->severity ? ($severityLabels[$d->severity] ?? $d->severity) : '-' }}</td>
                    <td>{{ $d->location_zone ?: '-' }}</td>
                    <td>{{ $d->confidence !== null ? round((float) $d->confidence * 100).'%' : '-' }}</td>
                    <td>{{ $d->description ?: '-' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <div class="card-body text-muted">{{ __('No damage recorded.') }}</div>
        @endif
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header fw-bold">{{ __('Decision') }}</div>
        <div class="card-body">
          @if($status === 'pending')
            <p class="small text-muted">{{ __('Confirm when the assessment is right. Dismiss when it is not; it stays on record as dismissed.') }}</p>
          @endif
          <div class="d-flex gap-2 flex-wrap">
            @if($status !== 'confirmed')
              <form method="post" action="{{ route('admin.ai.condition.decision', $a->id) }}">
                @csrf
                <input type="hidden" name="decision" value="confirmed">
                <button type="submit" class="btn btn-success"><i class="fas fa-check me-1" aria-hidden="true"></i>{{ __('Confirm') }}</button>
              </form>
            @endif
            @if($status !== 'dismissed')
              <form method="post" action="{{ route('admin.ai.condition.decision', $a->id) }}">
                @csrf
                <input type="hidden" name="decision" value="dismissed">
                <button type="submit" class="btn btn-outline-secondary"><i class="fas fa-times me-1" aria-hidden="true"></i>{{ __('Dismiss') }}</button>
              </form>
            @endif
          </div>
        </div>
      </div>
      <a href="{{ route('admin.ai.condition.browse') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>{{ __('Back to condition assessments') }}</a>
    </div>
  </div>
@endsection
