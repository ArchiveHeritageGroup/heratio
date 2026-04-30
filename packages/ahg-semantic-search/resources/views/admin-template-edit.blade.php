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

@section('title', ($isNew ?? true) ? 'New Search Template' : 'Edit Search Template')

@section('content')
@php
    $isNew = $isNew ?? true;
    $template = $template ?? null;
@endphp

<h1><i class="fa fa-edit me-2"></i>{{ $isNew ? 'New Search Template' : 'Edit Search Template' }}</h1>

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/admin/settings') }}">Settings</a></li>
    <li class="breadcrumb-item"><a href="{{ route('semantic-search.admin.templates') }}">Search Templates</a></li>
    <li class="breadcrumb-item active">{{ $isNew ? 'New' : 'Edit' }}</li>
  </ol>
</nav>

<form method="post" class="needs-validation" novalidate>
  @csrf
  <div class="row">
    <div class="col-md-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Template Details') }}</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">{{ __('Name *') }}</label>
              <input type="text" name="name" class="form-control" required
                     value="{{ $template->name ?? '' }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Slug') }}</label>
              <input type="text" name="slug" class="form-control"
                     value="{{ $template->slug ?? '' }}"
                     placeholder="{{ __('auto-generated') }}">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Description') }}</label>
              <textarea name="description" class="form-control" rows="2">{{ $template->description ?? '' }}</textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Category') }}</label>
              <input type="text" name="category" class="form-control"
                     value="{{ $template->category ?? '' }}"
                     placeholder="{{ __('e.g., Quick Searches, By Format') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Entity Type') }}</label>
              <select name="entity_type" class="form-select">
                <option value="informationobject" {{ ($template->entity_type ?? '') === 'informationobject' ? 'selected' : '' }}>Information Objects</option>
                <option value="actor" {{ ($template->entity_type ?? '') === 'actor' ? 'selected' : '' }}>Authority Records</option>
                <option value="repository" {{ ($template->entity_type ?? '') === 'repository' ? 'selected' : '' }}>Repositories</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Search Parameters') }}</h5></div>
        <div class="card-body">
          <label class="form-label">{{ __('JSON Parameters') }}</label>
          <textarea name="search_params" class="form-control font-monospace" rows="6" required>{{ $template->search_params ?? '{}' }}</textarea>
          <div class="form-text">
            Example: <code>{"sq0":"photographs","sf0":"title","onlyMedia":"1"}</code>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Appearance') }}</h5></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Icon') }}</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fa {{ $template->icon ?? 'fa-search' }}"></i></span>
              <input type="text" name="icon" class="form-control"
                     value="{{ $template->icon ?? 'fa-search' }}">
            </div>
            <div class="form-text">FontAwesome class</div>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Color') }}</label>
            <select name="color" class="form-select">
              @foreach(['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark'] as $color)
              <option value="{{ $color }}" {{ ($template->color ?? 'primary') === $color ? 'selected' : '' }}>
                {{ ucfirst($color) }}
              </option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Sort Order') }}</label>
            <input type="number" name="sort_order" class="form-control"
                   value="{{ (int) ($template->sort_order ?? 0) }}">
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">{{ __('Visibility') }}</h5></div>
        <div class="card-body">
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                   {{ ($template->is_active ?? 1) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="is_featured"
                   {{ ($template->is_featured ?? 0) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_featured">{{ __('Featured') }}</label>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="show_on_homepage" value="1" id="show_on_homepage"
                   {{ ($template->show_on_homepage ?? 0) ? 'checked' : '' }}>
            <label class="form-check-label" for="show_on_homepage">{{ __('Show on Homepage') }}</label>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-between">
    <a href="{{ route('semantic-search.admin.templates') }}" class="btn btn-secondary">
      <i class="fa fa-arrow-left me-1"></i>Cancel
    </a>
    <button type="submit" class="btn btn-primary">
      <i class="fa fa-save me-1"></i>Save Template
    </button>
  </div>
</form>
@endsection
