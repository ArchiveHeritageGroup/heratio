{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Export Permit ' . ($permit->permit_number ?? 'EXP-' . $permit->id))

@section('content')
@php
  $statusColors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'expired' => 'secondary'];
  $color = $statusColors[$permit->status] ?? 'secondary';
@endphp
<div class="container-fluid">
  <div class="row mb-4">
    <div class="col">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('nmmz.index') }}">NMMZ</a></li>
          <li class="breadcrumb-item"><a href="{{ route('nmmz.permits') }}">Export Permits</a></li>
          <li class="breadcrumb-item active">{{ $permit->permit_number ?? 'EXP-'.$permit->id }}</li>
        </ol>
      </nav>
      <h1><i class="fas fa-file-export me-2"></i>Export Permit {{ $permit->permit_number ?? 'EXP-'.$permit->id }}</h1>
    </div>
    <div class="col-auto">
      <a href="{{ route('nmmz.permits') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Permits
      </a>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Applicant Information</h5></div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-4">Applicant Name</dt>
            <dd class="col-sm-8">{{ $permit->applicant_name ?? '-' }}</dd>

            <dt class="col-sm-4">Applicant Type</dt>
            <dd class="col-sm-8">{{ ucfirst($permit->applicant_type ?? '-') }}</dd>

            <dt class="col-sm-4">Address</dt>
            <dd class="col-sm-8">{!! nl2br(e($permit->applicant_address ?? '-')) !!}</dd>

            <dt class="col-sm-4">Email</dt>
            <dd class="col-sm-8">{{ $permit->applicant_email ?? '-' }}</dd>

            <dt class="col-sm-4">Phone</dt>
            <dd class="col-sm-8">{{ $permit->applicant_phone ?? '-' }}</dd>
          </dl>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Object Details</h5></div>
        <div class="card-body">
          <dl class="row mb-0">
            @if($permit->antiquity_id)
              <dt class="col-sm-4">Linked Antiquity</dt>
              <dd class="col-sm-8">
                <a href="{{ route('nmmz.antiquity.view', $permit->antiquity_id) }}">ANT-{{ $permit->antiquity_id }}</a>
              </dd>
            @endif

            <dt class="col-sm-4">Description</dt>
            <dd class="col-sm-8">{!! nl2br(e($permit->object_description ?? '-')) !!}</dd>

            <dt class="col-sm-4">Quantity</dt>
            <dd class="col-sm-8">{{ $permit->quantity ?? 1 }}</dd>

            <dt class="col-sm-4">Estimated Value</dt>
            <dd class="col-sm-8">{{ $permit->estimated_value ? '$'.number_format($permit->estimated_value, 2) : '-' }}</dd>
          </dl>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Export Details</h5></div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-4">Export Purpose</dt>
            <dd class="col-sm-8">{{ ucfirst($permit->export_purpose ?? '-') }}</dd>

            <dt class="col-sm-4">Purpose Details</dt>
            <dd class="col-sm-8">{!! nl2br(e($permit->purpose_details ?? '-')) !!}</dd>

            <dt class="col-sm-4">Destination Country</dt>
            <dd class="col-sm-8">{{ $permit->destination_country ?? '-' }}</dd>

            <dt class="col-sm-4">Destination Institution</dt>
            <dd class="col-sm-8">{{ $permit->destination_institution ?? '-' }}</dd>

            <dt class="col-sm-4">Proposed Export Date</dt>
            <dd class="col-sm-8">{{ $permit->export_date_proposed ? \Carbon\Carbon::parse($permit->export_date_proposed)->format('j F Y') : '-' }}</dd>

            <dt class="col-sm-4">Return Date</dt>
            <dd class="col-sm-8">{{ $permit->return_date ? \Carbon\Carbon::parse($permit->return_date)->format('j F Y') : 'Not specified (permanent)' }}</dd>
          </dl>
        </div>
      </div>

      @if($permit->status === 'approved' && !empty($permit->approval_conditions))
        <div class="card mb-4">
          <div class="card-header bg-success text-white"><h5 class="mb-0">Approval Conditions</h5></div>
          <div class="card-body">{!! nl2br(e($permit->approval_conditions)) !!}</div>
        </div>
      @endif

      @if($permit->status === 'rejected' && !empty($permit->rejection_reason))
        <div class="card mb-4">
          <div class="card-header bg-danger text-white"><h5 class="mb-0">Rejection Reason</h5></div>
          <div class="card-body">{!! nl2br(e($permit->rejection_reason)) !!}</div>
        </div>
      @endif
    </div>

    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Status</h5></div>
        <div class="card-body text-center">
          <span class="badge bg-{{ $color }} fs-5 px-4 py-2">{{ ucfirst($permit->status ?? 'Pending') }}</span>
        </div>
      </div>

      @if($permit->status === 'pending')
        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">Review Actions</h5></div>
          <div class="card-body">
            <form method="post">
              @csrf
              <div class="mb-3">
                <label class="form-label">Conditions (if approving)</label>
                <textarea name="conditions" class="form-control" rows="3"></textarea>
              </div>
              <button type="submit" name="action_type" value="approve" class="btn btn-success w-100 mb-2">
                <i class="fas fa-check me-1"></i> Approve
              </button>

              <hr>

              <div class="mb-3">
                <label class="form-label">Rejection Reason</label>
                <textarea name="rejection_reason" class="form-control" rows="3"></textarea>
              </div>
              <button type="submit" name="action_type" value="reject" class="btn btn-danger w-100">
                <i class="fas fa-times me-1"></i> Reject
              </button>
            </form>
          </div>
        </div>
      @endif

      <div class="card">
        <div class="card-header"><h5 class="mb-0">Record Info</h5></div>
        <div class="card-body">
          <small class="text-muted">
            <p class="mb-1"><strong>Applied:</strong> {{ $permit->created_at ? \Carbon\Carbon::parse($permit->created_at)->format('j M Y H:i') : '-' }}</p>
            @if(!empty($permit->review_date))
              <p class="mb-1"><strong>Reviewed:</strong> {{ \Carbon\Carbon::parse($permit->review_date)->format('j M Y') }}</p>
            @endif
            <p class="mb-0"><strong>Updated:</strong> {{ $permit->updated_at ? \Carbon\Carbon::parse($permit->updated_at)->format('j M Y H:i') : '-' }}</p>
          </small>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
