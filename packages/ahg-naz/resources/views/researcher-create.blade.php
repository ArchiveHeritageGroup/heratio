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
                    <li class="breadcrumb-item active">Register</li>
                </ol>
            </nav>
            <h1><i class="fas fa-user-plus me-2"></i>Register Researcher</h1>
        </div>
    </div>

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Personal Information') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">{{ __('Title') }}</label>
                            <select name="title" class="form-select">
                                <option value="">-</option>
                                <option value="Mr">{{ __('Mr') }}</option>
                                <option value="Mrs">{{ __('Mrs') }}</option>
                                <option value="Ms">{{ __('Ms') }}</option>
                                <option value="Dr">{{ __('Dr') }}</option>
                                <option value="Prof">{{ __('Prof') }}</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Phone') }}</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Researcher Type <span class="text-danger">*</span></label>
                            <select name="researcher_type" class="form-select" required>
                                <option value="local">{{ __('Local') }}</option>
                                <option value="foreign">{{ __('Foreign') }}</option>
                                <option value="institutional">{{ __('Institutional') }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Nationality') }}</label>
                            <input type="text" name="nationality" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('National ID / Passport') }}</label>
                            <input type="text" name="national_id" class="form-control">
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
                            <input type="text" name="institution" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Position') }}</label>
                            <input type="text" name="position" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Research Interests') }}</label>
                            <textarea name="research_interests" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle"></i> Permit Fees</h6>
                <p class="small mb-0">Foreign researchers: US$200<br>Local researchers: Free</p>
            </div>
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Register</button>
                    <a href="{{ route('ahgnaz.researchers') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
