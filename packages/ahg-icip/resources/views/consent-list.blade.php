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

@section('title', 'Consent Records')

@section('content')
<div class="container-xxl">
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.dashboard') }}">ICIP</a></li>
      <li class="breadcrumb-item active">Consent Records</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-file-earmark-check me-2"></i>{{ __('Consent Records') }}</h1>
    <a href="{{ route('ahgicip.consent-edit') }}" class="btn btn-primary">
      <i class="bi bi-plus-circle me-1"></i> {{ __('Add Consent Record') }}
    </a>
  </div>

  @if(!($tablesExist ?? true))
    <div class="alert alert-warning">ICIP tables have not been provisioned for this installation.</div>
  @endif

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">{{ __('Status') }}</label>
          <select name="status" class="form-select">
            <option value="">{{ __('All Statuses') }}</option>
            @foreach($statusOptions as $value => $label)
              <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Community') }}</label>
          <select name="community_id" class="form-select">
            <option value="">{{ __('All Communities') }}</option>
            @foreach($communities as $c)
              <option value="{{ $c->id }}" @selected(($filters['community_id'] ?? '') == $c->id)>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-outline-primary me-2"><i class="bi bi-search"></i> {{ __('Filter') }}</button>
          <a href="{{ route('ahgicip.consent-list') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><strong>{{ $consents->count() }}</strong> consent records found</div>
    <div class="card-body p-0">
      @if($consents->isEmpty())
        <div class="p-4 text-center text-muted">
          <i class="bi bi-file-earmark-check fs-1"></i>
          <p class="mb-0 mt-2">No consent records found</p>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Record') }}</th>
                <th>{{ __('Community') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Consent Date') }}</th>
                <th>{{ __('Expiry') }}</th>
                <th width="100">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($consents as $consent)
                @php
                  $statusClass = match($consent->consent_status) {
                    'full_consent' => 'bg-success',
                    'conditional_consent', 'restricted_consent' => 'bg-info',
                    'pending_consultation', 'consultation_in_progress' => 'bg-warning text-dark',
                    'denied' => 'bg-danger',
                    'not_required' => 'bg-light text-dark',
                    default => 'bg-secondary',
                  };
                  $isExpired = false; $isExpiringSoon = false;
                  if (!empty($consent->consent_expiry_date)) {
                    $exp = \Carbon\Carbon::parse($consent->consent_expiry_date);
                    $isExpired = $exp->isPast();
                    $isExpiringSoon = !$isExpired && $exp->diffInDays(now()) <= 90;
                  }
                @endphp
                <tr>
                  <td>
                    @if(!empty($consent->slug))
                      <a href="{{ url('/'.$consent->slug) }}">{{ $consent->object_title ?? 'Untitled' }}</a>
                    @else
                      {{ $consent->object_title ?? 'Untitled' }}
                    @endif
                  </td>
                  <td>{{ $consent->community_name ?? '-' }}</td>
                  <td>
                    <span class="badge {{ $statusClass }}">
                      {{ $statusOptions[$consent->consent_status] ?? ucwords(str_replace('_', ' ', $consent->consent_status)) }}
                    </span>
                  </td>
                  <td>{{ !empty($consent->consent_date) ? \Carbon\Carbon::parse($consent->consent_date)->format('j M Y') : '-' }}</td>
                  <td>
                    @if(!empty($consent->consent_expiry_date))
                      <span class="{{ $isExpired ? 'text-danger' : ($isExpiringSoon ? 'text-warning' : '') }}">
                        {{ \Carbon\Carbon::parse($consent->consent_expiry_date)->format('j M Y') }}
                        @if($isExpired)<i class="bi bi-exclamation-circle" title="{{ __('Expired') }}"></i>
                        @elseif($isExpiringSoon)<i class="bi bi-clock" title="{{ __('Expiring soon') }}"></i>@endif
                      </span>
                    @else
                      -
                    @endif
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a href="{{ route('ahgicip.consent-view', ['id' => $consent->id]) }}" class="btn btn-outline-primary" title="{{ __('View') }}"><i class="bi bi-eye"></i></a>
                      <a href="{{ route('ahgicip.consent-edit', ['id' => $consent->id]) }}" class="btn btn-outline-secondary" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                    </div>
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
@endsection
