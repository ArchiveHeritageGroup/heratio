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

@section('title', 'Manage Consent')

@section('content')
<div class="container-xxl">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/'.$object->slug) }}">{{ $object->title ?? 'Record' }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.object-icip', ['slug' => $object->slug]) }}">ICIP</a></li>
      <li class="breadcrumb-item active">Consent</li>
    </ol>
  </nav>

  <h1 class="mb-4"><i class="bi bi-file-earmark-check me-2"></i>Manage Consent</h1>

  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="row">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Consent Records</h5></div>
        <div class="card-body p-0">
          @if($consents->isEmpty())
            <div class="p-4 text-center text-muted">No consent records yet</div>
          @else
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Status</th>
                    <th>Community</th>
                    <th>Date</th>
                    <th>Expiry</th>
                    <th>Actions</th>
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
                        default => 'bg-secondary',
                      };
                    @endphp
                    <tr>
                      <td>
                        <span class="badge {{ $statusClass }}">
                          {{ $statusOptions[$consent->consent_status] ?? ucwords(str_replace('_', ' ', $consent->consent_status)) }}
                        </span>
                      </td>
                      <td>{{ $consent->community_name ?? '-' }}</td>
                      <td>{{ !empty($consent->consent_date) ? \Carbon\Carbon::parse($consent->consent_date)->format('j M Y') : '-' }}</td>
                      <td>{{ !empty($consent->consent_expiry_date) ? \Carbon\Carbon::parse($consent->consent_expiry_date)->format('j M Y') : '-' }}</td>
                      <td>
                        <a href="{{ route('ahgicip.consent-edit', ['id' => $consent->id]) }}" class="btn btn-sm btn-outline-primary">
                          <i class="bi bi-pencil"></i>
                        </a>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">Add Consent Record</h5></div>
        <div class="card-body">
          <form method="post">
            @csrf
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Community</label>
                <select name="community_id" class="form-select">
                  <option value="">Not specified / Multiple</option>
                  @foreach($communities as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Consent Status <span class="text-danger">*</span></label>
                <select name="consent_status" class="form-select" required>
                  @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Consent Scope</label>
              <div class="row">
                @foreach($scopeOptions as $value => $label)
                  <div class="col-md-4">
                    <div class="form-check">
                      <input type="checkbox" name="consent_scope[]" value="{{ $value }}" class="form-check-input" id="ocs_{{ $value }}">
                      <label class="form-check-label" for="ocs_{{ $value }}">{{ $label }}</label>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Consent Date</label>
                <input type="date" name="consent_date" class="form-control">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Expiry Date</label>
                <input type="date" name="consent_expiry_date" class="form-control">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Granted By</label>
                <input type="text" name="consent_granted_by" class="form-control">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Conditions</label>
              <textarea name="conditions" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
              <i class="bi bi-plus-circle me-1"></i> Add Consent Record
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Status Guide</h5></div>
        <div class="card-body small">
          <dl class="mb-0">
            <dt class="text-muted">Not Required</dt>
            <dd>No consent needed for this material</dd>
            <dt class="text-warning">Pending Consultation</dt>
            <dd>Awaiting initial community contact</dd>
            <dt class="text-info">In Progress</dt>
            <dd>Consultation underway</dd>
            <dt class="text-success">Full Consent</dt>
            <dd>Unrestricted consent granted</dd>
            <dt class="text-primary">Conditional/Restricted</dt>
            <dd>Consent with specific limitations</dd>
            <dt class="text-danger">Denied</dt>
            <dd>Consent refused by community</dd>
          </dl>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
