{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Donor Agreements')

@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
          <li class="breadcrumb-item"><a href="{{ route('donor.browse') }}">{{ __('Donor Dashboard') }}</a></li>
          <li class="breadcrumb-item active">{{ __('Agreements') }}</li>
        </ol>
      </nav>
      <h1 class="h3 mb-0">
        <i class="fas fa-file-contract text-primary me-2"></i>
        {{ __('Donor Agreements') }}
        <span class="badge bg-secondary ms-2">{{ number_format($total ?? 0) }}</span>
      </h1>
    </div>
    <a href="{{ route('donor.agreement.add') }}" class="btn btn-primary">
      <i class="fas fa-plus me-1"></i> {{ __('New Agreement') }}
    </a>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" action="{{ route('donor.agreements') }}">
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label">{{ __('Search') }}</label>
            <input type="text" name="q" class="form-control" placeholder="{{ __('Agreement #, title...') }}" value="{{ $filters['search'] ?? '' }}">
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ __('Status') }}</label>
            <select name="status" class="form-select">
              <option value="">{{ __('All') }}</option>
              @foreach(($statuses ?? []) as $key => $label)
                <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ __('Type') }}</label>
            <select name="type" class="form-select">
              <option value="">{{ __('All') }}</option>
              @foreach(($types ?? []) as $type)
                <option value="{{ $type->id }}" @selected(($filters['type'] ?? '') == $type->id)>{{ $type->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">{{ __('Expiring') }}</label>
            <select name="expiring" class="form-select">
              <option value="">{{ __('Any') }}</option>
              <option value="7" @selected(($filters['expiring'] ?? '') == '7')>{{ __('Within 7 days') }}</option>
              <option value="30" @selected(($filters['expiring'] ?? '') == '30')>{{ __('Within 30 days') }}</option>
              <option value="90" @selected(($filters['expiring'] ?? '') == '90')>{{ __('Within 90 days') }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> {{ __('Filter') }}</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ __('Agreement #') }}</th>
              <th>{{ __('Title') }}</th>
              <th>{{ __('Donor') }}</th>
              <th>{{ __('Type') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Agreement Date') }}</th>
              <th>{{ __('Expiry') }}</th>
              <th class="text-end">{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @if(!empty($agreements))
              @foreach($agreements as $agreement)
                @php
                  $statusColors = ['draft' => 'secondary', 'active' => 'success', 'expired' => 'danger', 'terminated' => 'dark', 'pending_approval' => 'warning'];
                  $color = $statusColors[$agreement->status] ?? 'secondary';
                  $daysLeft = !empty($agreement->expiry_date) ? (strtotime($agreement->expiry_date) - time()) / 86400 : null;
                  $textClass = $daysLeft === null ? '' : ($daysLeft < 30 ? 'text-danger fw-bold' : ($daysLeft < 90 ? 'text-warning' : ''));
                @endphp
                <tr>
                  <td>
                    <a href="{{ route('donor.agreement.show', ['id' => $agreement->id]) }}" class="fw-bold">
                      {{ $agreement->agreement_number }}
                    </a>
                  </td>
                  <td>{{ $agreement->title ?? '—' }}</td>
                  <td>
                    @if(!empty($agreement->donor_name))
                      @if(!empty($agreement->donor_slug))
                        <a href="{{ url('/'.$agreement->donor_slug) }}">{{ $agreement->donor_name }}</a>
                      @else
                        {{ $agreement->donor_name }}
                      @endif
                    @else
                      —
                    @endif
                  </td>
                  <td><small>{{ $agreement->type_name ?? '—' }}</small></td>
                  <td><span class="badge bg-{{ $color }}">{{ ucfirst(str_replace('_', ' ', $agreement->status ?? '')) }}</span></td>
                  <td>{{ !empty($agreement->agreement_date) ? \Carbon\Carbon::parse($agreement->agreement_date)->format('j M Y') : '—' }}</td>
                  <td>
                    @if(!empty($agreement->expiry_date))
                      <span class="{{ $textClass }}">{{ \Carbon\Carbon::parse($agreement->expiry_date)->format('j M Y') }}</span>
                    @else
                      —
                    @endif
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <a href="{{ route('donor.agreement.show', ['id' => $agreement->id]) }}" class="btn btn-outline-primary"><i class="fas fa-eye"></i></a>
                      <a href="{{ route('donor.agreement.edit', ['id' => $agreement->id]) }}" class="btn btn-outline-secondary"><i class="fas fa-edit"></i></a>
                    </div>
                  </td>
                </tr>
              @endforeach
            @else
              <tr>
                <td colspan="8" class="text-center py-5">
                  <i class="fas fa-file-contract fa-3x text-muted mb-3"></i>
                  <p class="text-muted mb-0">{{ __('No agreements found') }}</p>
                </td>
              </tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
