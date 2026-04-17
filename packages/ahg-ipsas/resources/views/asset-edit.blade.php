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

@section('title', 'Edit Asset')

@section('content')
@php
    $asset = $asset ?? (object) [];
    $categories = $categories ?? collect();
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.index') }}">IPSAS</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.assets') }}">Assets</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.asset.view', ['id' => $asset->id ?? 0]) }}">{{ $asset->asset_number ?? 'Asset' }}</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </nav>
            <h1><i class="fas fa-edit me-2"></i>Edit Asset</h1>
        </div>
    </div>

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Asset Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required value="{{ $asset->title ?? '' }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ $asset->description ?? '' }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" value="{{ $asset->location ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" {{ 'active' === ($asset->status ?? '') ? 'selected' : '' }}>Active</option>
                                <option value="on_loan" {{ 'on_loan' === ($asset->status ?? '') ? 'selected' : '' }}>On Loan</option>
                                <option value="in_storage" {{ 'in_storage' === ($asset->status ?? '') ? 'selected' : '' }}>In Storage</option>
                                <option value="under_conservation" {{ 'under_conservation' === ($asset->status ?? '') ? 'selected' : '' }}>Under Conservation</option>
                                <option value="disposed" {{ 'disposed' === ($asset->status ?? '') ? 'selected' : '' }}>Disposed</option>
                                <option value="lost" {{ 'lost' === ($asset->status ?? '') ? 'selected' : '' }}>Lost</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Condition &amp; Risk</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Condition Rating</label>
                            <select name="condition_rating" class="form-select">
                                <option value="excellent" {{ 'excellent' === ($asset->condition_rating ?? '') ? 'selected' : '' }}>Excellent</option>
                                <option value="good" {{ 'good' === ($asset->condition_rating ?? '') ? 'selected' : '' }}>Good</option>
                                <option value="fair" {{ 'fair' === ($asset->condition_rating ?? '') ? 'selected' : '' }}>Fair</option>
                                <option value="poor" {{ 'poor' === ($asset->condition_rating ?? '') ? 'selected' : '' }}>Poor</option>
                                <option value="critical" {{ 'critical' === ($asset->condition_rating ?? '') ? 'selected' : '' }}>Critical</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Risk Level</label>
                            <select name="risk_level" class="form-select">
                                <option value="">Not Assessed</option>
                                <option value="low" {{ 'low' === ($asset->risk_level ?? '') ? 'selected' : '' }}>Low</option>
                                <option value="medium" {{ 'medium' === ($asset->risk_level ?? '') ? 'selected' : '' }}>Medium</option>
                                <option value="high" {{ 'high' === ($asset->risk_level ?? '') ? 'selected' : '' }}>High</option>
                                <option value="critical" {{ 'critical' === ($asset->risk_level ?? '') ? 'selected' : '' }}>Critical</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Risk Notes</label>
                            <textarea name="risk_notes" class="form-control" rows="2">{{ $asset->risk_notes ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Save Changes</button>
                    <a href="{{ route('ipsas.asset.view', ['id' => $asset->id ?? 0]) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
