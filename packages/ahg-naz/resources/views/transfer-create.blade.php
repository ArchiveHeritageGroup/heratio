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

@section('title', 'Create Records Transfer')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.index') }}">NAZ</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.transfers') }}">Transfers</a></li>
                    <li class="breadcrumb-item active">New Transfer</li>
                </ol>
            </nav>
            <h1><i class="fas fa-truck me-2"></i>Create Records Transfer</h1>
        </div>
    </div>

    <form method="post" class="row g-4">
        @csrf
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Transferring Agency</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Agency Name <span class="text-danger">*</span></label>
                            <input type="text" name="transferring_agency" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="agency_contact" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="agency_email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="agency_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transfer Type</label>
                            <select name="transfer_type" class="form-select">
                                <option value="scheduled">Scheduled Transfer</option>
                                <option value="voluntary">Voluntary Transfer</option>
                                <option value="rescue">Rescue Transfer</option>
                                <option value="donation">Donation</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Records Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" required placeholder="Describe the records being transferred"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date Range Start</label>
                            <input type="date" name="date_range_start" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date Range End</label>
                            <input type="date" name="date_range_end" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Linear Metres</label>
                            <input type="number" name="quantity_linear_metres" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Number of Boxes</label>
                            <input type="number" name="quantity_boxes" class="form-control" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Number of Items</label>
                            <input type="number" name="quantity_items" class="form-control" min="0">
                        </div>
                    </div>
                </div>
            </div>

            @if(!empty($schedules) && (is_countable($schedules) && count($schedules) > 0))
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Retention Schedule</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Linked Schedule</label>
                            <select name="schedule_id" class="form-select">
                                <option value="">None</option>
                                @foreach($schedules as $s)
                                    <option value="{{ $s->id }}">{{ $s->agency_name ?? '' }} — {{ $s->record_series ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="card">
                <div class="card-header"><h5 class="mb-0">Restrictions</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="contains_restricted" id="contains_restricted">
                                <label class="form-check-label" for="contains_restricted">
                                    Contains restricted records
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Restriction Details</label>
                            <textarea name="restriction_details" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Proposed Transfer Date</label>
                            <input type="date" name="proposed_date" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Create Transfer
                    </button>
                    <a href="{{ route('ahgnaz.transfers') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
