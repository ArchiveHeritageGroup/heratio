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

@section('title', __('Cultural Notices'))

@section('content')
@php
  $noticeTypes = collect($noticeTypes ?? []);
  $appliedNotices = collect($appliedNotices ?? []);
  $severityIcon = fn ($severity) => match ($severity) {
    'critical' => 'bi-exclamation-triangle-fill text-danger',
    'warning' => 'bi-exclamation-circle text-warning',
    default => 'bi-info-circle text-info',
  };
  $severityClass = fn ($severity) => match ($severity) {
    'critical' => 'bg-danger',
    'warning' => 'bg-warning text-dark',
    default => 'bg-info',
  };
  $severityLabels = [
    'info' => __('Info'),
    'warning' => __('Warning'),
    'critical' => __('Critical'),
  ];
@endphp
<div class="container-xxl">
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.dashboard') }}">ICIP</a></li>
      <li class="breadcrumb-item active">{{ __('Cultural Notices') }}</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-bell me-2"></i>{{ __('Cultural Notices') }}</h1>
    <a href="{{ route('ahgicip.notice-types') }}" class="btn btn-outline-secondary">
      <i class="bi bi-gear me-1"></i> {{ __('Manage Notice Types') }}
    </a>
  </div>

  @if(!($tablesExist ?? true))
    <div class="alert alert-warning">{{ __('ICIP tables have not been provisioned for this installation.') }}</div>
  @endif

  <div class="row">
    <div class="col-lg-8">
      {{-- Applied notices --}}
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">{{ __('Applied Cultural Notices') }}</h5>
        </div>
        <div class="card-body p-0">
          @if($appliedNotices->isEmpty())
            <div class="p-4 text-center text-muted">
              <i class="bi bi-bell fs-1"></i>
              <p class="mb-0 mt-2">{{ __('No notices applied yet') }}</p>
              <p class="small">{{ __('Notices can be applied from individual record ICIP pages') }}</p>
            </div>
          @else
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>{{ __('Notice Type') }}</th>
                    <th>{{ __('Record') }}</th>
                    <th>{{ __('Community') }}</th>
                    <th>{{ __('Severity') }}</th>
                    <th>{{ __('Applied') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($appliedNotices as $notice)
                    <tr>
                      <td>
                        <i class="bi {{ $severityIcon($notice->severity) }} me-1"></i>
                        {{ $notice->notice_name }}
                      </td>
                      <td>
                        @if(!empty($notice->slug))
                          <a href="{{ route('ahgicip.object-icip', ['slug' => $notice->slug]) }}">{{ $notice->object_title ?? __('Untitled') }}</a>
                        @else
                          {{ $notice->object_title ?? __('Untitled') }}
                        @endif
                      </td>
                      <td>{{ $notice->community_name ?? '-' }}</td>
                      <td>
                        <span class="badge {{ $severityClass($notice->severity) }}">
                          {{ $severityLabels[$notice->severity] ?? ucfirst((string) $notice->severity) }}
                        </span>
                      </td>
                      <td>{{ !empty($notice->created_at) ? \Carbon\Carbon::parse($notice->created_at)->format('j M Y') : '-' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      {{-- Notice types reference --}}
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">{{ __('Notice Types') }}</h5>
        </div>
        <div class="card-body">
          @foreach($noticeTypes as $type)
            <div class="mb-3 pb-3 border-bottom">
              <div class="d-flex align-items-start">
                <i class="bi {{ $severityIcon($type->severity) }} fs-5 me-2 mt-1"></i>
                <div>
                  <strong>{{ $type->name }}</strong>
                  @if(!$type->is_active)
                    <span class="badge bg-secondary ms-1">{{ __('Inactive') }}</span>
                  @endif
                  <br>
                  <small class="text-muted">{{ $type->description ?? '' }}</small>
                  <div class="mt-1">
                    @if($type->requires_acknowledgement)
                      <span class="badge bg-warning text-dark">{{ __('Requires Acknowledgement') }}</span>
                    @endif
                    @if($type->blocks_access)
                      <span class="badge bg-danger">{{ __('Blocks Access') }}</span>
                    @endif
                  </div>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
