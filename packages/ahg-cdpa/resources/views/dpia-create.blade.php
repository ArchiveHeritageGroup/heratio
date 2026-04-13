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

@section('title', 'New DPIA')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.index') }}">CDPA</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.dpia') }}">DPIA</a></li>
                    <li class="breadcrumb-item active">New DPIA</li>
                </ol>
            </nav>
            <h1><i class="fas fa-clipboard-check me-2"></i>New Data Protection Impact Assessment</h1>
        </div>
    </div>

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Assessment Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">DPIA Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Related Processing Activity</label>
                            <select name="processing_activity_id" class="form-select">
                                <option value="">None / New Activity</option>
                                @foreach(($activities ?? []) as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assessor Name <span class="text-danger">*</span></label>
                            <input type="text" name="assessor_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assessment Date <span class="text-danger">*</span></label>
                            <input type="date" name="assessment_date" class="form-control" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" required placeholder="Describe the processing activity and why a DPIA is needed..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Necessity & Proportionality</h5></div>
                <div class="card-body">
                    <label class="form-label">Assessment of necessity and proportionality <span class="text-danger">*</span></label>
                    <textarea name="necessity_assessment" class="form-control" rows="4" required placeholder="Why is this processing necessary? Is it proportionate to the purpose?"></textarea>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">Risk Assessment</h5></div>
                <div class="card-body">
                    <label class="form-label">Overall Risk Level <span class="text-danger">*</span></label>
                    <select name="risk_level" class="form-select" required>
                        <option value="">Select...</option>
                        <option value="low">Low - Minimal impact on data subjects</option>
                        <option value="medium">Medium - Moderate impact, manageable with controls</option>
                        <option value="high">High - Significant impact, requires strong controls</option>
                        <option value="critical">Critical - Severe impact, may require regulator consultation</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle me-1"></i> When is DPIA Required?</h6>
                <ul class="small mb-0">
                    <li>Large-scale processing</li>
                    <li>Systematic monitoring</li>
                    <li>Automated decisions with legal effects</li>
                    <li>Special categories of data</li>
                    <li>Cross-border transfers</li>
                    <li>Innovative technologies</li>
                </ul>
            </div>

            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Create DPIA
                    </button>
                    <a href="{{ route('ahgcdpa.dpia') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
