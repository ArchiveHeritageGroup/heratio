{{--
  Background Jobs Settings — cloned from AtoM section.blade.php @case('jobs')
  Copyright (C) 2026 Johan Pieterse — Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Background Jobs')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1><i class="fas fa-tasks me-2"></i>Background Jobs</h1>
  <p class="text-muted small mb-0">Job queue and scheduling settings</p>
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="post" action="{{ route('settings.ahg.jobs') }}">
    @csrf

    {{-- Background Job Settings --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Background Job Settings</h5></div>
      <div class="card-body">
        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Enable Jobs') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="jobs_enabled"
                     name="settings[jobs_enabled]" value="true"
                     {{ ($settings['jobs_enabled'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="jobs_enabled">{{ __('Enable background job processing') }}</label>
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="jobs_max_concurrent">{{ __('Max Concurrent Jobs') }}</label>
          <div class="col-sm-9">
            <input type="number" class="form-control" id="jobs_max_concurrent"
                   name="settings[jobs_max_concurrent]"
                   value="{{ $settings['jobs_max_concurrent'] ?? 2 }}" min="1" max="10">
            <div class="form-text">Maximum number of jobs to run simultaneously</div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="jobs_timeout">{{ __('Job Timeout') }}</label>
          <div class="col-sm-9">
            <div class="input-group">
              <input type="number" class="form-control" id="jobs_timeout"
                     name="settings[jobs_timeout]"
                     value="{{ $settings['jobs_timeout'] ?? 3600 }}" min="60" max="86400">
              <span class="input-group-text">seconds</span>
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="jobs_retry_attempts">{{ __('Retry Attempts') }}</label>
          <div class="col-sm-9">
            <input type="number" class="form-control" id="jobs_retry_attempts"
                   name="settings[jobs_retry_attempts]"
                   value="{{ $settings['jobs_retry_attempts'] ?? 3 }}" min="0" max="10">
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="jobs_cleanup_days">{{ __('Cleanup After') }}</label>
          <div class="col-sm-9">
            <div class="input-group">
              <input type="number" class="form-control" id="jobs_cleanup_days"
                     name="settings[jobs_cleanup_days]"
                     value="{{ $settings['jobs_cleanup_days'] ?? 30 }}" min="1" max="365">
              <span class="input-group-text">days</span>
            </div>
            <div class="form-text">Delete completed jobs after this many days</div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Notify on Failure') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="jobs_notify_on_failure"
                     name="settings[jobs_notify_on_failure]" value="true"
                     {{ ($settings['jobs_notify_on_failure'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="jobs_notify_on_failure">{{ __('Send email when jobs fail') }}</label>
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label" for="jobs_notify_email">{{ __('Notification Email') }}</label>
          <div class="col-sm-9">
            <input type="email" class="form-control" id="jobs_notify_email"
                   name="settings[jobs_notify_email]"
                   value="{{ e($settings['jobs_notify_email'] ?? '') }}" placeholder="{{ __('admin@example.com') }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Job Queue Status --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-list-alt me-2"></i>Job Queue Status</h5></div>
      <div class="card-body">
        <div class="alert alert-info mb-0">
          <i class="fas fa-info-circle me-1"></i>
          @if(\Route::has('job.browse'))
            <a href="{{ route('job.browse') }}">View all jobs in Job Manager</a>
          @else
            <a href="{{ url('/jobs/browse') }}">View all jobs in Job Manager</a>
          @endif
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between">
      <a href="{{ route('settings.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save Settings') }}</button>
    </div>
  </form>
@endsection
