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

@section('title', ($id ? 'Edit' : 'Add') . ' Community')

@section('content')
<div class="container-xxl">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.dashboard') }}">ICIP</a></li>
      <li class="breadcrumb-item"><a href="{{ route('ahgicip.communities') }}">Communities</a></li>
      <li class="breadcrumb-item active">{{ $id ? 'Edit' : 'Add' }} Community</li>
    </ol>
  </nav>

  <h1 class="mb-4">
    <i class="bi bi-{{ $id ? 'pencil' : 'plus-circle' }} me-2"></i>
    {{ $id ? 'Edit Community' : 'Add Community' }}
  </h1>

  <form method="post" class="needs-validation" novalidate>
    @csrf
    @if($id)<input type="hidden" name="id" value="{{ $id }}">@endif
    <div class="row">
      <div class="col-lg-8">
        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">Basic Information</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Community Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required value="{{ $community->name ?? '' }}">
              <div class="form-text">Official name of the community</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Alternate Names</label>
              <input type="text" name="alternate_names" class="form-control" value="{{ $community && !empty($community->alternate_names) ? implode(', ', json_decode($community->alternate_names, true) ?? []) : '' }}">
              <div class="form-text">Separate multiple names with commas</div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Language Group</label>
                <input type="text" name="language_group" class="form-control" value="{{ $community->language_group ?? '' }}">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Region</label>
                <input type="text" name="region" class="form-control" value="{{ $community->region ?? '' }}">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">State/Territory <span class="text-danger">*</span></label>
              <select name="state_territory" class="form-select" required>
                <option value="">Select state/territory</option>
                @foreach($states as $code => $name)
                  <option value="{{ $code }}" @selected(($community->state_territory ?? '') === $code)>{{ $name }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">Contact Information</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Primary Contact Name</label>
              <input type="text" name="contact_name" class="form-control" value="{{ $community->contact_name ?? '' }}">
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Contact Email</label>
                <input type="email" name="contact_email" class="form-control" value="{{ $community->contact_email ?? '' }}">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Contact Phone</label>
                <input type="tel" name="contact_phone" class="form-control" value="{{ $community->contact_phone ?? '' }}">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Contact Address</label>
              <textarea name="contact_address" class="form-control" rows="2">{{ $community->contact_address ?? '' }}</textarea>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">Native Title Information</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Native Title Reference</label>
              <input type="text" name="native_title_reference" class="form-control" value="{{ $community->native_title_reference ?? '' }}">
              <div class="form-text">Reference number for Native Title determination (if applicable)</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Prescribed Body Corporate (PBC)</label>
              <input type="text" name="prescribed_body_corporate" class="form-control" value="{{ $community->prescribed_body_corporate ?? '' }}">
              <div class="form-text">Name of the PBC holding Native Title rights</div>
            </div>
            <div class="mb-3">
              <label class="form-label">PBC Contact Email</label>
              <input type="email" name="pbc_contact_email" class="form-control" value="{{ $community->pbc_contact_email ?? '' }}">
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">Notes</h5></div>
          <div class="card-body">
            <textarea name="notes" class="form-control" rows="4">{{ $community->notes ?? '' }}</textarea>
            <div class="form-text">Internal notes about this community (not displayed publicly)</div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">Status</h5></div>
          <div class="card-body">
            <div class="form-check form-switch">
              <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" @checked(($community->is_active ?? 1))>
              <label class="form-check-label" for="isActive">Active</label>
            </div>
            <div class="form-text">Inactive communities are hidden from selection lists but retain historical records</div>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <button type="submit" class="btn btn-primary w-100 mb-2">
              <i class="bi bi-check-circle me-1"></i>
              {{ $id ? 'Save Changes' : 'Create Community' }}
            </button>
            <a href="{{ route('ahgicip.communities') }}" class="btn btn-outline-secondary w-100">Cancel</a>
          </div>
        </div>

        @if($id)
          <div class="card mt-4">
            <div class="card-header"><h5 class="mb-0">Linked Records</h5></div>
            <div class="card-body">
              <p class="small text-muted mb-2">This community may be linked to consent records, consultations, and cultural notices.</p>
              <a href="{{ route('ahgicip.community-view', ['id' => $id]) }}" class="btn btn-outline-primary btn-sm w-100">
                <i class="bi bi-eye me-1"></i> View Details
              </a>
            </div>
          </div>
        @endif
      </div>
    </div>
  </form>
</div>
@endsection
