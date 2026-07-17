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

@section('title', ($id ? 'Edit' : 'Add') . ' Community')

@section('content')
<div class="container-xxl">
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
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

  <form method="post" class="needs-validation" novalidate autocomplete="off">
    @csrf
    @if($id)<input type="hidden" name="id" value="{{ $id }}">@endif
    <div class="row">
      <div class="col-lg-8">
        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">{{ __('Basic Information') }}</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Community Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required autocomplete="off" value="{{ $community->name ?? '' }}">
              <div class="form-text">Official name of the community</div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ __('Self-Identified Term') }}</label>
              <input type="text" name="self_identified_term" class="form-control" value="{{ $community->self_identified_term ?? '' }}">
              <div class="form-text">{{ __("The term this community uses for itself. This is rendered in preference to any imposed label - never assume \"Indigenous\" (communities self-identify).") }}</div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ __('Alternate Names') }}</label>
              <input type="text" name="alternate_names" class="form-control" value="{{ $community && !empty($community->alternate_names) ? implode(', ', json_decode($community->alternate_names, true) ?? []) : '' }}">
              <div class="form-text">Separate multiple names with commas</div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Language Group') }}</label>
                <input type="text" name="language_group" class="form-control" value="{{ $community->language_group ?? '' }}">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Region') }}</label>
                <input type="text" name="region" class="form-control" value="{{ $community->region ?? '' }}">
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Region Pack') }}</label>
                @if(isset($regions) && $regions->isNotEmpty())
                  <select name="region_module" class="form-select">
                    <option value="">{{ __('- none -') }}</option>
                    @foreach($regions as $rg)
                      <option value="{{ $rg->code }}" @selected(($community->region_module ?? '') === $rg->code)>{{ $rg->name }}</option>
                    @endforeach
                  </select>
                @else
                  <input type="text" name="region_module" class="form-control" value="{{ $community->region_module ?? '' }}" placeholder="e.g. za, sadc, international">
                @endif
                <div class="form-text">{{ __('Pluggable per-region pack this community belongs to (optional).') }}</div>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Persistent Identifier (PID)') }}</label>
                <input type="text" name="pid" class="form-control" value="{{ $community->pid ?? '' }}">
                <div class="form-text">{{ __('Sovereign PID / DOCiD, if minted.') }}</div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ __('CARE Statement') }}</label>
              <textarea name="care_statement" class="form-control" rows="2" placeholder="{{ __('Collective benefit, Authority to control, Responsibility, Ethics') }}">{{ $community->care_statement ?? '' }}</textarea>
              <div class="form-text">{{ __('The community\'s data-governance statement under the CARE Principles.') }}</div>
            </div>

            <div class="mb-3">
              <label class="form-label">{{ __('State/Territory') }} <span class="text-muted small">({{ __('Australia') }})</span></label>
              <select name="state_territory" class="form-select">
                <option value="">{{ __('Not applicable') }}</option>
                @foreach($states as $code => $name)
                  <option value="{{ $code }}" @selected(($community->state_territory ?? '') === $code)>{{ $name }}</option>
                @endforeach
              </select>
              <div class="form-text">{{ __('AU-specific. Leave as "Not applicable" outside Australia.') }}</div>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">{{ __('Contact Information') }}</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">{{ __('Primary Contact Name') }}</label>
              <input type="text" name="contact_name" class="form-control" value="{{ $community->contact_name ?? '' }}">
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Contact Email') }}</label>
                <input type="email" name="contact_email" class="form-control" value="{{ $community->contact_email ?? '' }}">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Contact Phone') }}</label>
                <input type="tel" name="contact_phone" class="form-control" value="{{ $community->contact_phone ?? '' }}">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Contact Address') }}</label>
              <textarea name="contact_address" class="form-control" rows="2">{{ $community->contact_address ?? '' }}</textarea>
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">{{ __('Native Title Information') }} <span class="text-muted small fw-normal">({{ __('Australia') }})</span></h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">{{ __('Native Title Reference') }}</label>
              <input type="text" name="native_title_reference" class="form-control" value="{{ $community->native_title_reference ?? '' }}">
              <div class="form-text">Reference number for Native Title determination (if applicable)</div>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Prescribed Body Corporate (PBC)') }}</label>
              <input type="text" name="prescribed_body_corporate" class="form-control" value="{{ $community->prescribed_body_corporate ?? '' }}">
              <div class="form-text">Name of the PBC holding Native Title rights</div>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('PBC Contact Email') }}</label>
              <input type="email" name="pbc_contact_email" class="form-control" value="{{ $community->pbc_contact_email ?? '' }}">
            </div>
          </div>
        </div>

        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">{{ __('Notes') }}</h5></div>
          <div class="card-body">
            <textarea name="notes" class="form-control" rows="4">{{ $community->notes ?? '' }}</textarea>
            <div class="form-text">Internal notes about this community (not displayed publicly)</div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">{{ __('Status') }}</h5></div>
          <div class="card-body">
            <div class="form-check form-switch">
              <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" @checked(($community->is_active ?? 1))>
              <label class="form-check-label" for="isActive">{{ __('Active') }}</label>
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
            <div class="card-header"><h5 class="mb-0">{{ __('Linked Records') }}</h5></div>
            <div class="card-body">
              <p class="small text-muted mb-2">This community may be linked to consent records, consultations, and cultural notices.</p>
              <a href="{{ route('ahgicip.community-view', ['id' => $id]) }}" class="btn btn-outline-primary btn-sm w-100">
                <i class="bi bi-eye me-1"></i> {{ __('View Details') }}
              </a>
            </div>
          </div>
        @endif
      </div>
    </div>
  </form>

  @if($id)
    {{-- #1406 P2c - Community Stewards. Only a steward (or an administrator) may
         apply/withdraw this community's labels with applied_by='community'. While
         a community has no stewards, staff ICIP-write governs (no workflow break). --}}
    <div class="card mt-2 mb-4">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">{{ __('Community Stewards') }}</h5>
        <span class="badge bg-secondary ms-2">{{ $stewards->count() }}</span>
      </div>
      <div class="card-body">
        <p class="small text-muted">
          {{ __('Stewards are the actors this community authorises to apply or withdraw its own (applied_by = community) labels. Until a steward is named, staff with ICIP-write permission manage the labels.') }}
        </p>

        @if($stewards->isNotEmpty())
          <ul class="list-group mb-3">
            @foreach($stewards as $s)
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-badge me-1"></i>{{ $s->username ?? ('user #'.$s->user_id) }}
                  @if($s->email)<span class="text-muted small">- {{ $s->email }}</span>@endif
                </span>
                <form method="post" action="{{ route('ahgicip.community-steward-remove') }}" class="m-0">
                  @csrf
                  <input type="hidden" name="community_id" value="{{ $id }}">
                  <input type="hidden" name="steward_id" value="{{ $s->id }}">
                  <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Remove') }}</button>
                </form>
              </li>
            @endforeach
          </ul>
        @else
          <p class="text-muted"><em>{{ __('No stewards named yet.') }}</em></p>
        @endif

        <form method="post" action="{{ route('ahgicip.community-steward-add') }}" class="row g-2 align-items-end">
          @csrf
          <input type="hidden" name="community_id" value="{{ $id }}">
          <div class="col-md-8">
            <label class="form-label">{{ __('Add steward') }}</label>
            <input type="text" name="user_ident" class="form-control" placeholder="{{ __('username or email') }}" autocomplete="off">
          </div>
          <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-circle me-1"></i>{{ __('Add Steward') }}</button>
          </div>
        </form>
      </div>
    </div>
  @endif
</div>
@endsection
