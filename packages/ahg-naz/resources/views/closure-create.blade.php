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

@section('title', 'Create Closure Period')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.index') }}">NAZ</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.closures') }}">Closures</a></li>
                    <li class="breadcrumb-item active">New Closure</li>
                </ol>
            </nav>
            <h1><i class="fas fa-lock me-2"></i>Create Closure Period</h1>
        </div>
    </div>

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Record Information') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Information Object ID <span class="text-danger">*</span></label>
                            <input type="number" name="information_object_id" class="form-control" required>
                            <small class="text-muted">Enter the archival description record ID</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Closure Details') }}</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Closure Type <span class="text-danger">*</span></label>
                            <select name="closure_type" class="form-select" required>
                                <option value="standard">{{ __('Standard (25 years)') }}</option>
                                <option value="extended">{{ __('Extended') }}</option>
                                <option value="indefinite">{{ __('Indefinite') }}</option>
                                <option value="ministerial">{{ __('Ministerial Order') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Closure Period (years)') }}</label>
                            <input type="number" name="years" class="form-control" value="25" min="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Review Date') }}</label>
                            <input type="date" name="review_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Closure Reason <span class="text-danger">*</span></label>
                            <textarea name="closure_reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ __('Authority Reference') }}</label>
                            <input type="text" name="authority_reference" class="form-control" placeholder="{{ __('Ministerial order or legal reference') }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle"></i> Section 10</h6>
                <p class="small mb-0">Records are closed for 25 years from date of creation under the NAZ Act.</p>
            </div>
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Create Closure</button>
                    <a href="{{ route('ahgnaz.closures') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
