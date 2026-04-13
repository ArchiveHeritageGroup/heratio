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

@section('title', 'Create Records Schedule')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.index') }}">NAZ</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.schedules') }}">Schedules</a></li>
                    <li class="breadcrumb-item active">New Schedule</li>
                </ol>
            </nav>
            <h1><i class="fas fa-calendar-alt me-2"></i>Create Records Schedule</h1>
        </div>
    </div>

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Agency Information</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Agency Name <span class="text-danger">*</span></label>
                            <input type="text" name="agency_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Agency Code</label>
                            <input type="text" name="agency_code" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Record Series <span class="text-danger">*</span></label>
                            <input type="text" name="record_series" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Retention & Disposal</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Active Retention (years) <span class="text-danger">*</span></label>
                            <input type="number" name="retention_period_active" class="form-control" required min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Semi-active (years)</label>
                            <input type="number" name="retention_period_semi" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Disposal Action <span class="text-danger">*</span></label>
                            <select name="disposal_action" class="form-select" required>
                                <option value="destroy">Destroy</option>
                                <option value="transfer">Transfer to NAZ</option>
                                <option value="review">Review</option>
                                <option value="permanent">Permanent Retention</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Legal Authority</label>
                            <textarea name="legal_authority" class="form-control" rows="2" placeholder="Legal basis for retention period"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">Classification & Access</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Classification</label>
                            <select name="classification" class="form-select">
                                <option value="vital">Vital</option>
                                <option value="important">Important</option>
                                <option value="useful" selected>Useful</option>
                                <option value="non-essential">Non-essential</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Access Restriction</label>
                            <select name="access_restriction" class="form-select">
                                <option value="open" selected>Open</option>
                                <option value="restricted">Restricted</option>
                                <option value="confidential">Confidential</option>
                                <option value="secret">Secret</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Create Schedule
                    </button>
                    <a href="{{ route('ahgnaz.schedules') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
