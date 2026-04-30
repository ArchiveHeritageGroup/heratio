{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Manage Cultural Notices')

@section('content')
<div class="container-xxl">
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/'.$object->slug) }}">{{ $object->title ?? 'Record' }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.object-icip', ['slug' => $object->slug]) }}">ICIP</a></li>
      <li class="breadcrumb-item active">Cultural Notices</li>
    </ol>
  </nav>

  <h1 class="mb-4"><i class="bi bi-bell me-2"></i>Manage Cultural Notices</h1>

  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="row">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Active Notices') }}</h5></div>
        <div class="card-body">
          @if($notices->isEmpty())
            <p class="text-muted">No cultural notices applied to this record.</p>
          @else
            @foreach($notices as $notice)
              @php
                $severityIcon = match($notice->severity) {
                  'critical' => 'bi-exclamation-triangle-fill text-danger',
                  'warning' => 'bi-exclamation-circle text-warning',
                  default => 'bi-info-circle text-info',
                };
              @endphp
              <div class="icip-notice icip-notice-{{ $notice->severity }} mb-3 p-3 rounded d-flex justify-content-between align-items-start">
                <div class="d-flex">
                  <i class="bi {{ $severityIcon }} fs-4 me-3"></i>
                  <div>
                    <strong>{{ $notice->notice_name }}</strong>
                    @if(!empty($notice->requires_acknowledgement))
                      <span class="badge bg-warning text-dark ms-2">Requires Acknowledgement</span>
                    @endif
                    @if(!empty($notice->blocks_access))
                      <span class="badge bg-danger ms-2">Blocks Access</span>
                    @endif
                    <p class="mb-1 mt-1">{{ $notice->custom_text ?? $notice->default_text ?? '' }}</p>
                    @if(!empty($notice->community_name))
                      <small class="text-muted">Community: {{ $notice->community_name }}</small>
                    @endif
                  </div>
                </div>
                <form method="post" class="d-inline" onsubmit="return confirm('Remove this notice?');">
                  @csrf
                  <input type="hidden" name="form_action" value="remove">
                  <input type="hidden" name="notice_id" value="{{ $notice->id }}">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button>
                </form>
              </div>
            @endforeach
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ __('Add Cultural Notice') }}</h5></div>
        <div class="card-body">
          <form method="post">
            @csrf
            <input type="hidden" name="form_action" value="add">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Notice Type <span class="text-danger">*</span></label>
                <select name="notice_type_id" class="form-select" required>
                  <option value="">{{ __('Select notice type') }}</option>
                  @foreach($noticeTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }} ({{ ucfirst($type->severity) }})</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Community') }}</label>
                <select name="community_id" class="form-select">
                  <option value="">{{ __('Not specified') }}</option>
                  @foreach($communities as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ __('Custom Text (optional)') }}</label>
              <textarea name="custom_text" class="form-control" rows="3" placeholder="{{ __('Override the default notice text...') }}"></textarea>
              <div class="form-text">Leave blank to use the default text for this notice type</div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('Start Date') }}</label>
                <input type="date" name="start_date" class="form-control">
                <div class="form-text">For seasonal notices</div>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('End Date') }}</label>
                <input type="date" name="end_date" class="form-control">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">&nbsp;</label>
                <div class="form-check mt-2">
                  <input type="checkbox" name="applies_to_descendants" value="1" class="form-check-input" id="applyDescendants" checked>
                  <label class="form-check-label" for="applyDescendants">{{ __('Apply to child records') }}</label>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ __('Notes') }}</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
              <i class="bi bi-plus-circle me-1"></i> Add Notice
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ __('Notice Types') }}</h5></div>
        <div class="card-body small">
          @foreach($noticeTypes as $type)
            @php
              $severityIcon = match($type->severity) {
                'critical' => 'bi-exclamation-triangle-fill text-danger',
                'warning' => 'bi-exclamation-circle text-warning',
                default => 'bi-info-circle text-info',
              };
            @endphp
            <div class="mb-2 pb-2 border-bottom">
              <i class="bi {{ $severityIcon }} me-1"></i>
              <strong>{{ $type->name }}</strong>
              <br>
              <small class="text-muted">{{ $type->description ?? '' }}</small>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.icip-notice-critical { background-color: #f8d7da; border-left: 4px solid #dc3545; }
.icip-notice-warning { background-color: #fff3cd; border-left: 4px solid #ffc107; }
.icip-notice-info { background-color: #cff4fc; border-left: 4px solid #0dcaf0; }
</style>
@endsection
