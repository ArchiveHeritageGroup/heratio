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

@section('title', 'Browse Form Templates')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-wpforms me-2"></i>Browse Form Templates</h1>
            <p class="text-muted">View and search available form templates</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('forms.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Form Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        @foreach(($formTypes ?? []) as $value => $label)
                            <option value="{{ $value }}" {{ ($type ?? '') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Search by name or description..."
                           value="{{ $search ?? '' }}">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                    <a href="{{ route('forms.browse') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Results --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                {{ count($templates ?? []) }} Template{{ count($templates ?? []) !== 1 ? 's' : '' }} Found
            </h5>
        </div>
        <div class="card-body p-0">
            @if(count($templates ?? []) === 0)
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No form templates match your criteria.</p>
                    @if(!empty($type) || !empty($search))
                        <a href="{{ route('forms.browse') }}" class="btn btn-outline-primary">
                            Clear Filters
                        </a>
                    @endif
                </div>
            @else
                <div class="row g-4 p-4">
                    @foreach($templates as $template)
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">
                                        {{ $template->name }}
                                        @if(!empty($template->is_default))
                                            <span class="badge bg-primary ms-1">Default</span>
                                        @endif
                                    </h6>
                                </div>
                                <div class="card-body">
                                    @if(!empty($template->description))
                                        <p class="text-muted small mb-3">{{ $template->description }}</p>
                                    @endif

                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="badge bg-info">
                                            <i class="fas fa-tag me-1"></i>
                                            {{ $formTypes[$template->form_type] ?? $template->form_type }}
                                        </span>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-th-list me-1"></i>
                                            {{ $template->field_count ?? 0 }} field{{ ($template->field_count ?? 0) !== 1 ? 's' : '' }}
                                        </span>
                                        @if(!empty($template->is_active))
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>Active
                                            </span>
                                        @else
                                            <span class="badge bg-warning">
                                                <i class="fas fa-pause me-1"></i>Inactive
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="card-footer bg-white">
                                    <div class="btn-group btn-group-sm w-100">
                                        <a href="{{ route('forms.preview', ['id' => $template->id]) }}"
                                           class="btn btn-outline-primary" title="Preview">
                                            <i class="fas fa-eye me-1"></i> Preview
                                        </a>
                                        <a href="{{ route('forms.builder', ['id' => $template->id]) }}"
                                           class="btn btn-outline-secondary" title="Edit">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
