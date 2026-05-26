{{--
  AI Services - Face detection driver status (Issue #667 Phase 1).

  Stubbed driver view: shows which FaceDetectorInterface implementation is
  bound, whether the feature is enabled in ahg_ai_settings, and a quick
  health probe via $detector->health().

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Face Detection')
@section('body-class', 'admin ai-services face-detect')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="bi bi-person-bounding-box"></i> {{ __('Face Detection') }}</h1>
  <a href="{{ route('admin.ai.index') }}" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>{{ __('Back to AI Services') }}
  </a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">{{ __('Driver') }}</dt>
      <dd class="col-sm-9"><code>{{ $driver }}</code></dd>

      <dt class="col-sm-3">{{ __('Implementation') }}</dt>
      <dd class="col-sm-9"><code>{{ $class }}</code></dd>

      <dt class="col-sm-3">{{ __('Enabled') }}</dt>
      <dd class="col-sm-9">
        @if($enabled)
          <span class="badge bg-success">{{ __('yes') }}</span>
        @else
          <span class="badge bg-secondary">{{ __('no') }}</span>
        @endif
      </dd>

      <dt class="col-sm-3">{{ __('Health probe') }}</dt>
      <dd class="col-sm-9">
        @if($health)
          <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>{{ __('healthy') }}</span>
        @else
          <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('not responding') }}</span>
        @endif
      </dd>
    </dl>
  </div>
</div>

<p class="text-muted mt-3 small">{{ __('Driver and credentials are configured under') }} <code>ahg_ai_settings</code> (<code>feature = face_detect</code>). {{ __('The null driver is a placeholder that satisfies the interface for tests; production use requires a real driver.') }}</p>
@endsection
