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

@section('title', __('ICIP Management'))

@section('content')
@php
  $statusOptions = $statusOptions ?? [];
  $consultationTypes = $consultationTypes ?? [];
  $consultationStatuses = $consultationStatuses ?? [];
  $pendingConsultations = collect($pendingConsultations ?? []);
  $expiringConsents = collect($expiringConsents ?? []);
  $recentConsultations = collect($recentConsultations ?? []);
@endphp
<div class="container-xxl">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-shield-check me-2"></i>{{ __('ICIP Management') }}</h1>
    <div>
      <a href="{{ route('ahgicip.communities') }}" class="btn btn-outline-primary">
        <i class="bi bi-people"></i> {{ __('Communities') }}
      </a>
      <a href="{{ route('ahgicip.reports') }}" class="btn btn-outline-secondary">
        <i class="bi bi-graph-up"></i> {{ __('Reports') }}
      </a>
    </div>
  </div>

  @if(!($tablesExist ?? true))
    <div class="alert alert-warning">{{ __('ICIP tables have not been provisioned for this installation.') }}</div>
  @endif

  {{-- Stats cards --}}
  <div class="row g-4 mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <h6 class="card-subtitle mb-2 text-white-50">{{ __('ICIP Records') }}</h6>
              <h2 class="card-title mb-0">{{ number_format($stats['total_icip_objects'] ?? 0) }}</h2>
            </div>
            <i class="bi bi-archive fs-1 opacity-50"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card bg-success text-white h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <h6 class="card-subtitle mb-2 text-white-50">{{ __('Communities') }}</h6>
              <h2 class="card-title mb-0">{{ number_format($stats['total_communities'] ?? 0) }}</h2>
            </div>
            <i class="bi bi-people fs-1 opacity-50"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card bg-warning text-dark h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <h6 class="card-subtitle mb-2">{{ __('Pending Consultation') }}</h6>
              <h2 class="card-title mb-0">{{ number_format($stats['pending_consultations'] ?? 0) }}</h2>
            </div>
            <i class="bi bi-clock-history fs-1 opacity-50"></i>
          </div>
        </div>
        @if(($stats['pending_consultations'] ?? 0) > 0)
          <div class="card-footer bg-transparent border-0">
            <a href="{{ route('ahgicip.report-pending') }}" class="text-dark small">{{ __('View list') }} &rarr;</a>
          </div>
        @endif
      </div>
    </div>

    <div class="col-md-3">
      <div class="card bg-danger text-white h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div>
              <h6 class="card-subtitle mb-2 text-white-50">{{ __('Expiring Consents') }}</h6>
              <h2 class="card-title mb-0">{{ number_format($stats['expiring_consents'] ?? 0) }}</h2>
            </div>
            <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
          </div>
        </div>
        @if(($stats['expiring_consents'] ?? 0) > 0)
          <div class="card-footer bg-transparent border-0">
            <a href="{{ route('ahgicip.report-expiry') }}" class="text-white small">{{ __('View list') }} &rarr;</a>
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- Secondary stats --}}
  <div class="row g-4 mb-4">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="text-muted mb-1">{{ __('TK Labels Applied') }}</h6>
              <h4 class="mb-0">{{ number_format($stats['tk_labels_applied'] ?? 0) }}</h4>
            </div>
            <span class="badge bg-info fs-5 p-2"><i class="bi bi-tag"></i></span>
          </div>
        </div>
        <div class="card-footer bg-transparent">
          <a href="{{ route('ahgicip.tk-labels') }}">{{ __('Manage TK Labels') }} &rarr;</a>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="text-muted mb-1">{{ __('Active Restrictions') }}</h6>
              <h4 class="mb-0">{{ number_format($stats['active_restrictions'] ?? 0) }}</h4>
            </div>
            <span class="badge bg-secondary fs-5 p-2"><i class="bi bi-lock"></i></span>
          </div>
        </div>
        <div class="card-footer bg-transparent">
          <a href="{{ route('ahgicip.restrictions') }}">{{ __('View Restrictions') }} &rarr;</a>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h6 class="text-muted mb-1">{{ __('Follow-ups Due') }}</h6>
              <h4 class="mb-0">{{ number_format($stats['follow_ups_due'] ?? 0) }}</h4>
            </div>
            <span class="badge bg-warning text-dark fs-5 p-2"><i class="bi bi-calendar-check"></i></span>
          </div>
        </div>
        <div class="card-footer bg-transparent">
          <a href="{{ route('ahgicip.consultations', ['status' => 'follow_up_required']) }}">{{ __('View Follow-ups') }} &rarr;</a>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    {{-- Pending consultations --}}
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="bi bi-clock text-warning me-2"></i>{{ __('Pending Consultation') }}</h5>
          <a href="{{ route('ahgicip.report-pending') }}" class="btn btn-sm btn-outline-primary">{{ __('View All') }}</a>
        </div>
        <div class="card-body p-0">
          @if($pendingConsultations->isEmpty())
            <div class="p-4 text-center text-muted">
              <i class="bi bi-check-circle fs-1 text-success"></i>
              <p class="mb-0 mt-2">{{ __('No pending consultations') }}</p>
            </div>
          @else
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>{{ __('Record') }}</th>
                    <th>{{ __('Community') }}</th>
                    <th>{{ __('Status') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($pendingConsultations as $record)
                    @php $recordTitle = $record->object_title ?? $record->identifier ?? __('Untitled'); @endphp
                    <tr>
                      <td>
                        @if(!empty($record->slug))
                          <a href="{{ route('ahgicip.object-icip', ['slug' => $record->slug]) }}">{{ $recordTitle }}</a>
                        @else
                          {{ $recordTitle }}
                        @endif
                      </td>
                      <td>
                        @if(!empty($record->community_name))
                          {{ $record->community_name }}
                        @else
                          <span class="text-muted">{{ __('Not specified') }}</span>
                        @endif
                      </td>
                      <td>
                        <span class="badge bg-warning text-dark">
                          {{ $statusOptions[$record->consent_status] ?? ucwords(str_replace('_', ' ', (string) $record->consent_status)) }}
                        </span>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Expiring consents --}}
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="bi bi-exclamation-triangle text-danger me-2"></i>{{ __('Expiring Consents (90 days)') }}</h5>
          <a href="{{ route('ahgicip.report-expiry') }}" class="btn btn-sm btn-outline-primary">{{ __('View All') }}</a>
        </div>
        <div class="card-body p-0">
          @if($expiringConsents->isEmpty())
            <div class="p-4 text-center text-muted">
              <i class="bi bi-check-circle fs-1 text-success"></i>
              <p class="mb-0 mt-2">{{ __('No consents expiring soon') }}</p>
            </div>
          @else
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>{{ __('Record') }}</th>
                    <th>{{ __('Expires') }}</th>
                    <th>{{ __('Days Left') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($expiringConsents as $record)
                    @php
                      $expiry = \Carbon\Carbon::parse($record->consent_expiry_date)->startOfDay();
                      $daysLeft = (int) abs(now()->startOfDay()->diffInDays($expiry));
                    @endphp
                    <tr>
                      <td>
                        @if(!empty($record->slug))
                          <a href="{{ route('ahgicip.object-icip', ['slug' => $record->slug]) }}">{{ $record->object_title ?? __('Untitled') }}</a>
                        @else
                          {{ $record->object_title ?? __('Untitled') }}
                        @endif
                      </td>
                      <td>{{ $expiry->format('j M Y') }}</td>
                      <td>
                        <span class="badge {{ $daysLeft < 30 ? 'bg-danger' : 'bg-warning text-dark' }}">
                          {{ trans_choice(':count day|:count days', $daysLeft, ['count' => $daysLeft]) }}
                        </span>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Recent consultations --}}
  <div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="bi bi-chat-dots me-2"></i>{{ __('Recent Consultations') }}</h5>
      <a href="{{ route('ahgicip.consultations') }}" class="btn btn-sm btn-outline-primary">{{ __('View All') }}</a>
    </div>
    <div class="card-body p-0">
      @if($recentConsultations->isEmpty())
        <div class="p-4 text-center text-muted">
          <p class="mb-0">{{ __('No consultations recorded yet') }}</p>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Community') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Summary') }}</th>
                <th>{{ __('Status') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentConsultations as $consultation)
                @php
                  $statusClass = match ($consultation->status) {
                    'completed' => 'bg-success',
                    'scheduled' => 'bg-info',
                    'cancelled' => 'bg-secondary',
                    'follow_up_required' => 'bg-warning text-dark',
                    default => 'bg-secondary',
                  };
                  $summary = (string) ($consultation->summary ?? '');
                @endphp
                <tr>
                  <td>{{ !empty($consultation->consultation_date) ? \Carbon\Carbon::parse($consultation->consultation_date)->format('j M Y') : '-' }}</td>
                  <td>
                    <a href="{{ route('ahgicip.consultation-view', ['id' => $consultation->id]) }}">{{ $consultation->community_name }}</a>
                  </td>
                  <td>{{ $consultationTypes[$consultation->consultation_type] ?? ucwords(str_replace('_', ' ', (string) $consultation->consultation_type)) }}</td>
                  <td>{{ \Illuminate\Support\Str::limit($summary, 60, '...') }}</td>
                  <td>
                    <span class="badge {{ $statusClass }}">
                      {{ $consultationStatuses[$consultation->status] ?? ucwords(str_replace('_', ' ', (string) $consultation->status)) }}
                    </span>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  {{-- Quick actions --}}
  <div class="card mt-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>{{ __('Quick Actions') }}</h5>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <a href="{{ route('ahgicip.community-edit') }}" class="btn btn-outline-primary w-100">
            <i class="bi bi-plus-circle me-2"></i>{{ __('Add Community') }}
          </a>
        </div>
        <div class="col-md-3">
          <a href="{{ route('ahgicip.consent-edit') }}" class="btn btn-outline-primary w-100">
            <i class="bi bi-file-earmark-check me-2"></i>{{ __('Record Consent') }}
          </a>
        </div>
        <div class="col-md-3">
          <a href="{{ route('ahgicip.consultation-edit') }}" class="btn btn-outline-primary w-100">
            <i class="bi bi-chat-dots me-2"></i>{{ __('Log Consultation') }}
          </a>
        </div>
        <div class="col-md-3">
          <a href="{{ route('ahgicip.notices') }}" class="btn btn-outline-primary w-100">
            <i class="bi bi-bell me-2"></i>{{ __('Cultural Notices') }}
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
