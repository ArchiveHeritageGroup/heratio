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

@section('title', 'Manage Access Restrictions')

@section('content')
<div class="container-xxl">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/'.$object->slug) }}">{{ $object->title ?? 'Record' }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.object-icip', ['slug' => $object->slug]) }}">ICIP</a></li>
      <li class="breadcrumb-item active">Restrictions</li>
    </ol>
  </nav>

  <h1 class="mb-4"><i class="bi bi-lock me-2"></i>Manage Access Restrictions</h1>

  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="row">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Active Restrictions</h5></div>
        <div class="card-body">
          @if($restrictions->isEmpty())
            <p class="text-muted">No access restrictions applied to this record.</p>
          @else
            @foreach($restrictions as $restriction)
              <div class="alert alert-danger d-flex justify-content-between align-items-start">
                <div>
                  <i class="bi bi-lock-fill me-2"></i>
                  <strong>{{ $restrictionTypes[$restriction->restriction_type] ?? ucwords(str_replace('_', ' ', $restriction->restriction_type)) }}</strong>
                  @if(!empty($restriction->override_security_clearance))
                    <span class="badge bg-dark ms-2">Overrides Security Clearance</span>
                  @endif

                  @if($restriction->restriction_type === 'custom' && !empty($restriction->custom_restriction_text))
                    <p class="mb-1 mt-1">{{ $restriction->custom_restriction_text }}</p>
                  @endif

                  <div class="mt-1">
                    @if(!empty($restriction->community_name))
                      <small class="text-muted">Community: {{ $restriction->community_name }}</small>
                    @endif
                    @if(!empty($restriction->start_date) || !empty($restriction->end_date))
                      <br>
                      <small class="text-muted">
                        Period: {{ !empty($restriction->start_date) ? \Carbon\Carbon::parse($restriction->start_date)->format('j M Y') : 'Start' }}
                        &ndash;
                        {{ !empty($restriction->end_date) ? \Carbon\Carbon::parse($restriction->end_date)->format('j M Y') : 'Indefinite' }}
                      </small>
                    @endif
                  </div>
                </div>
                <form method="post" class="d-inline" onsubmit="return confirm('Remove this restriction?');">
                  @csrf
                  <input type="hidden" name="form_action" value="remove">
                  <input type="hidden" name="restriction_id" value="{{ $restriction->id }}">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove"><i class="bi bi-x-lg"></i></button>
                </form>
              </div>
            @endforeach
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">Add Restriction</h5></div>
        <div class="card-body">
          <form method="post">
            @csrf
            <input type="hidden" name="form_action" value="add">

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Restriction Type <span class="text-danger">*</span></label>
                <select name="restriction_type" class="form-select" required id="restrictionType">
                  @foreach($restrictionTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Community</label>
                <select name="community_id" class="form-select">
                  <option value="">Not specified</option>
                  @foreach($communities as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="mb-3" id="customTextGroup" style="display: none;">
              <label class="form-label">Custom Restriction Text</label>
              <textarea name="custom_restriction_text" class="form-control" rows="2"></textarea>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control">
                <div class="form-text">Leave blank for indefinite</div>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">&nbsp;</label>
                <div class="form-check mt-2">
                  <input type="checkbox" name="applies_to_descendants" value="1" class="form-check-input" id="applyDescendants" checked>
                  <label class="form-check-label" for="applyDescendants">Apply to child records</label>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <div class="form-check">
                <input type="checkbox" name="override_security_clearance" value="1" class="form-check-input" id="overrideSecurity" checked>
                <label class="form-check-label" for="overrideSecurity">
                  <strong>Override Security Clearance</strong>
                  <br><small class="text-muted">ICIP restriction takes precedence over standard access controls</small>
                </label>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>

            <button type="submit" class="btn btn-danger">
              <i class="bi bi-lock me-1"></i> Add Restriction
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Restriction Types</h5></div>
        <div class="card-body small">
          <dl class="mb-0">
            <dt><i class="bi bi-people text-danger me-1"></i> Community Permission Required</dt>
            <dd class="text-muted">Written permission from the community is required</dd>
            <dt><i class="bi bi-shield-lock text-danger me-1"></i> Initiated Only</dt>
            <dd class="text-muted">Restricted to initiated community members</dd>
            <dt><i class="bi bi-calendar-event text-danger me-1"></i> Seasonal</dt>
            <dd class="text-muted">Time-based restrictions</dd>
            <dt><i class="bi bi-heart text-danger me-1"></i> Mourning Period</dt>
            <dd class="text-muted">Temporary restriction during mourning</dd>
            <dt><i class="bi bi-box-arrow-left text-danger me-1"></i> Repatriation Pending</dt>
            <dd class="text-muted">Material awaiting return to community</dd>
          </dl>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('restrictionType').addEventListener('change', function() {
  document.getElementById('customTextGroup').style.display = this.value === 'custom' ? 'block' : 'none';
});
</script>
@endsection
