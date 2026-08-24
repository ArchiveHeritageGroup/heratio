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

@section('title', 'Register Researcher')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.index') }}">NAZ</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.researchers') }}">Researchers</a></li>
                    <li class="breadcrumb-item active">{{ $researcher ?? null ? 'Edit' : 'Register' }}</li>
                </ol>
            </nav>
            {{--
              #1478 This form serves both registering and editing. researcherUpdate()
              existed complete - validation, PII encryption, audit - with no GET
              form and no route to reach it, and the researcher view's Edit button
              pointed at a route name that was never defined, so that page threw
              for every researcher.
            --}}
            <h1><i class="fas fa-user-plus me-2"></i>{{ $researcher ?? null ? __('Edit Researcher') : __('Register Researcher') }}</h1>
        </div>
    </div>

    @php $r = $researcher ?? null; @endphp

    <form method="post" class="row g-4"
          action="{{ $r ? route('ahgnaz.researcher-update', ['id' => $r->id]) : route('ahgnaz.researcher-store') }}">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Personal Information') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">{{ __('Title') }}</label>
                            @php $selTitle = old('title', $r->title ?? ''); @endphp
                            <select name="title" class="form-select">
                                <option value="">-</option>
                                @foreach(['Mr','Mrs','Ms','Dr','Prof'] as $t)
                                    <option value="{{ $t }}" @selected($selTitle === $t)>{{ __($t) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $r->first_name ?? '') }}" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $r->last_name ?? '') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $r->email ?? '') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Phone') }}</label>
                            <input type="tel" name="phone" class="form-control" value="{{ old('phone', $r->phone ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Researcher Type <span class="text-danger">*</span></label>
                            @php $selType = old('researcher_type', $r->researcher_type ?? 'local'); @endphp
                            <select name="researcher_type" class="form-select" required>
                                @foreach(['local' => 'Local', 'foreign' => 'Foreign', 'institutional' => 'Institutional'] as $v => $label)
                                    <option value="{{ $v }}" @selected($selType === $v)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Nationality') }}</label>
                            <input type="text" name="nationality" class="form-control" value="{{ old('nationality', $r->nationality ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('National ID / Passport') }}</label>
                            <input type="text" name="national_id" class="form-control" value="{{ old('national_id', $r->national_id ?? '') }}">
                        </div>
                        {{--
                          #1478 registration_date is validated as REQUIRED by both
                          researcherStore() and researcherUpdate() and was collected
                          by no field, so registering a researcher failed validation
                          every single time. The column is NOT NULL, so it cannot
                          simply be dropped from the rules.
                        --}}
                        <div class="col-md-4">
                            <label class="form-label">Registration Date <span class="text-danger">*</span></label>
                            <input type="date" name="registration_date" class="form-control" required
                                   value="{{ old('registration_date', isset($r->registration_date) ? substr((string) $r->registration_date, 0, 10) : date('Y-m-d')) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Status') }}</label>
                            @php $selStatus = old('status', $r->status ?? 'active'); @endphp
                            <select name="status" class="form-select">
                                @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended', 'blacklisted' => 'Blacklisted'] as $v => $label)
                                    <option value="{{ $v }}" @selected($selStatus === $v)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Affiliation') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">{{ __('Institution') }}</label>
                            <input type="text" name="institution" class="form-control" value="{{ old('institution', $r->institution ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Position') }}</label>
                            <input type="text" name="position" class="form-control" value="{{ old('position', $r->position ?? '') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Research Interests') }}</label>
                            <textarea name="research_interests" class="form-control" rows="3">{{ old('research_interests', $r->research_interests ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle"></i> {{ __('Permit Fees') }}</h6>
                <p class="small mb-0">Foreign researchers: US$200<br>Local researchers: Free</p>
            </div>
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>{{ $r ? __('Save Changes') : __('Register') }}</button>
                    <a href="{{ route('ahgnaz.researchers') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
