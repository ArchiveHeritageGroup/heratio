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

@section('title', 'Create Form Template')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-plus me-2"></i>Create Form Template</h1>
            <p class="text-muted">Create a new form template</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('forms.templates') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="post">
                @csrf
                <div class="mb-3">
                    <label class="form-label">{{ __('Template Name *') }}</label>
                    <input type="text" name="name" class="form-control" required placeholder="{{ __('e.g., ISAD-G Standard Form') }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('Description') }}</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="{{ __('Describe the purpose of this form template...') }}"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('Form Type *') }}</label>
                    <select name="form_type" class="form-select" required>
                        <option value="">{{ __('Select type...') }}</option>
                        <option value="information_object">{{ __('Information Object (Archival Description)') }}</option>
                        <option value="actor">{{ __('Authority Record (Actor)') }}</option>
                        <option value="repository">{{ __('Repository') }}</option>
                        <option value="accession">{{ __('Accession') }}</option>
                        <option value="deaccession">{{ __('Deaccession') }}</option>
                        <option value="rights">{{ __('Rights') }}</option>
                    </select>
                    <small class="text-muted">The type of record this form will be used for</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('Layout') }}</label>
                    <select name="layout" class="form-select">
                        <option value="single">{{ __('Single Column') }}</option>
                        <option value="two-column">{{ __('Two Columns') }}</option>
                        <option value="tabs">{{ __('Tabbed Sections') }}</option>
                    </select>
                </div>

                <hr>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('forms.templates') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Create Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
