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

@section('title', 'Form Preview')

@section('content')
@php
    $fields = $fields ?? collect();
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('forms.index') }}">Form Templates</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('forms.builder', ['id' => $template->id ?? 0]) }}">Builder</a></li>
                    <li class="breadcrumb-item active">Preview</li>
                </ol>
            </nav>
            <h1><i class="fas fa-eye me-2"></i>Preview: {{ $template->name ?? '' }}</h1>
        </div>
        <div class="col-auto">
            <a href="{{ route('forms.builder', ['id' => $template->id ?? 0]) }}" class="btn btn-outline-primary">
                <i class="fas fa-edit me-1"></i> Back to Builder
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Form Preview') }}</h5>
                </div>
                <div class="card-body">
                    @if($fields->isEmpty())
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This template has no fields yet. Add fields in the builder.
                        </div>
                    @else
                        <form>
                            @foreach($fields as $field)
                                <div class="mb-3">
                                    @if($field->field_type === 'heading')
                                        <h5 class="border-bottom pb-2 mt-4">{{ $field->label }}</h5>
                                    @elseif($field->field_type === 'divider')
                                        <hr class="my-4">
                                    @else
                                        <label class="form-label">
                                            {{ $field->label }}
                                            @if(!empty($field->is_required))
                                                <span class="text-danger">*</span>
                                            @endif
                                        </label>

                                        @if($field->field_type === 'text')
                                            <input type="text" class="form-control"
                                                   placeholder="{{ $field->placeholder ?? '' }}"
                                                   value="{{ $field->default_value ?? '' }}"
                                                   {{ !empty($field->is_readonly) ? 'readonly' : '' }}>

                                        @elseif($field->field_type === 'textarea' || $field->field_type === 'richtext')
                                            <textarea class="form-control" rows="4"
                                                      placeholder="{{ $field->placeholder ?? '' }}"
                                                      {{ !empty($field->is_readonly) ? 'readonly' : '' }}>{{ $field->default_value ?? '' }}</textarea>

                                        @elseif($field->field_type === 'select' || $field->field_type === 'multiselect' || $field->field_type === 'radio')
                                            <select class="form-select" {{ !empty($field->is_readonly) ? 'disabled' : '' }}>
                                                <option value="">-- Select --</option>
                                                <option>{{ __('Option 1') }}</option>
                                                <option>{{ __('Option 2') }}</option>
                                                <option>{{ __('Option 3') }}</option>
                                            </select>

                                        @elseif($field->field_type === 'date')
                                            <input type="date" class="form-control"
                                                   value="{{ $field->default_value ?? '' }}"
                                                   {{ !empty($field->is_readonly) ? 'readonly' : '' }}>

                                        @elseif($field->field_type === 'daterange' || $field->field_type === 'date_range')
                                            <div class="row">
                                                <div class="col-6">
                                                    <input type="date" class="form-control" placeholder="{{ __('Start date') }}">
                                                </div>
                                                <div class="col-6">
                                                    <input type="date" class="form-control" placeholder="{{ __('End date') }}">
                                                </div>
                                            </div>

                                        @elseif($field->field_type === 'checkbox')
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="field_{{ $field->id }}">
                                                <label class="form-check-label" for="field_{{ $field->id }}">
                                                    {{ $field->help_text ?? 'Check this option' }}
                                                </label>
                                            </div>

                                        @elseif($field->field_type === 'file')
                                            <input type="file" class="form-control">

                                        @elseif($field->field_type === 'autocomplete')
                                            <input type="text" class="form-control"
                                                   placeholder="{{ __('Start typing to search...') }}">

                                        @elseif($field->field_type === 'hidden')
                                            <input type="hidden" value="{{ $field->default_value ?? '' }}">
                                            <span class="text-muted small">(hidden field)</span>

                                        @else
                                            <input type="text" class="form-control"
                                                   placeholder="{{ $field->placeholder ?? '' }}">
                                        @endif

                                        @if(!empty($field->help_text) && $field->field_type !== 'checkbox')
                                            <div class="form-text">{{ $field->help_text }}</div>
                                        @endif
                                    @endif
                                </div>
                            @endforeach

                            <div class="mt-4">
                                <button type="button" class="btn btn-primary" disabled>
                                    <i class="fas fa-save me-1"></i> Save (Preview Only)
                                </button>
                                <button type="button" class="btn btn-outline-secondary" disabled>
                                    {{ __('Cancel') }}
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Template Info</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> {{ $template->name ?? '' }}</p>
                    <p><strong>Type:</strong> <span class="badge bg-info">{{ $template->form_type ?? '' }}</span></p>
                    <p><strong>Fields:</strong> {{ $fields->count() }}</p>
                    <p><strong>Status:</strong>
                        @if(!empty($template->is_active))
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-warning">Inactive</span>
                        @endif
                    </p>
                    @if(!empty($template->description))
                        <p><strong>Description:</strong><br>{{ $template->description }}</p>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Field Summary</h5>
                </div>
                @php
                    $requiredCount = 0;
                    foreach ($fields as $f) {
                        if (!empty($f->is_required)) { ++$requiredCount; }
                    }
                @endphp
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        Total Fields
                        <span class="badge bg-primary">{{ $fields->count() }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        Required Fields
                        <span class="badge bg-danger">{{ $requiredCount }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        Optional Fields
                        <span class="badge bg-secondary">{{ $fields->count() - $requiredCount }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
