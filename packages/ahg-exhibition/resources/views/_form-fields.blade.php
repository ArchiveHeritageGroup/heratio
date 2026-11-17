{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Exhibition add/edit fields. Every name is a real exhibition column; types,
  statuses and currencies come from the Dropdown Manager.
  Expects: $data (object), $types, $statuses, $currencies, $venues.
--}}
@php
  $v = fn (string $field, $default = '') => old($field, $data->{$field} ?? $default);
  $types = $types ?? [];
  $statuses = $statuses ?? [];
  $currencies = $currencies ?? [];
  $venues = $venues ?? collect();
@endphp

@if($errors->any())
  <div class="alert alert-danger" role="alert">
    <ul class="mb-0">
      @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
  </div>
@endif

<div class="card mb-4">
  <div class="card-header"><h5 class="mb-0">{{ __('Basic Information') }}</h5></div>
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label" for="ex-title">{{ __('Title') }} <span class="text-danger">*</span></label>
      <input type="text" id="ex-title" name="title" class="form-control" autocomplete="off" required maxlength="500" value="{{ $v('title') }}">
    </div>
    <div class="mb-3">
      <label class="form-label" for="ex-subtitle">{{ __('Subtitle') }}</label>
      <input type="text" id="ex-subtitle" name="subtitle" class="form-control" maxlength="500" value="{{ $v('subtitle') }}">
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label" for="ex-type">{{ __('Exhibition Type') }}</label>
        <select id="ex-type" name="exhibition_type" class="form-select">
          @foreach($types as $key => $label)
            <option value="{{ $key }}" @selected($v('exhibition_type', 'temporary') == $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="ex-code">{{ __('Project Code') }}</label>
        <input type="text" id="ex-code" name="project_code" class="form-control" maxlength="50" value="{{ $v('project_code') }}">
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="ex-theme">{{ __('Theme') }}</label>
      <input type="text" id="ex-theme" name="theme" class="form-control" value="{{ $v('theme') }}"
             placeholder="{{ __('e.g., African Art, Modern Sculpture, Industrial Heritage') }}">
    </div>
    <div class="mb-3">
      <label class="form-label" for="ex-description">{{ __('Description') }}</label>
      <textarea id="ex-description" name="description" class="form-control" rows="4">{{ $v('description') }}</textarea>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header"><h5 class="mb-0">{{ __('Dates') }}</h5></div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label" for="ex-opening">{{ __('Opening Date') }}</label>
        <input type="date" id="ex-opening" name="opening_date" class="form-control" value="{{ $v('opening_date') }}">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="ex-closing">{{ __('Closing Date') }}</label>
        <input type="date" id="ex-closing" name="closing_date" class="form-control" value="{{ $v('closing_date') }}">
      </div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header"><h5 class="mb-0">{{ __('Venue & Team') }}</h5></div>
  <div class="card-body">
    <div class="row">
      @if($venues->isNotEmpty())
        <div class="col-md-6 mb-3">
          <label class="form-label" for="ex-venue">{{ __('Venue') }}</label>
          <select id="ex-venue" name="venue_id" class="form-select">
            <option value="">{{ __('-- Select venue --') }}</option>
            @foreach($venues as $venue)
              <option value="{{ $venue->id }}" @selected($v('venue_id') == $venue->id)>{{ $venue->name }}</option>
            @endforeach
          </select>
        </div>
      @endif
      <div class="col-md-6 mb-3">
        <label class="form-label" for="ex-venue-name">{{ __('Venue Name') }}</label>
        <input type="text" id="ex-venue-name" name="venue_name" class="form-control" maxlength="255" value="{{ $v('venue_name') }}">
      </div>
    </div>
    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label" for="ex-curator">{{ __('Curator') }}</label>
        <input type="text" id="ex-curator" name="curator_name" class="form-control" maxlength="255" value="{{ $v('curator_name') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label" for="ex-designer">{{ __('Designer') }}</label>
        <input type="text" id="ex-designer" name="designer_name" class="form-control" maxlength="255" value="{{ $v('designer_name') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label" for="ex-organized">{{ __('Organized By') }}</label>
        <input type="text" id="ex-organized" name="organized_by" class="form-control" maxlength="255" value="{{ $v('organized_by') }}">
      </div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header"><h5 class="mb-0">{{ __('Status & Budget') }}</h5></div>
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label" for="ex-status">{{ __('Status') }}</label>
      <select id="ex-status" name="status" class="form-select">
        @foreach($statuses as $key => $label)
          <option value="{{ $key }}" @selected($v('status', 'concept') == $key)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="row">
      <div class="col-md-8 mb-3">
        <label class="form-label" for="ex-budget">{{ __('Budget Amount') }}</label>
        <input type="number" id="ex-budget" name="budget_amount" class="form-control" step="0.01" min="0" value="{{ $v('budget_amount') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label" for="ex-currency">{{ __('Currency') }}</label>
        <select id="ex-currency" name="budget_currency" class="form-select">
          @foreach($currencies as $code => $label)
            <option value="{{ $code }}" @selected($v('budget_currency', 'ZAR') == $code)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="row">
      <div class="col-md-4 mb-3">
        <label class="form-label" for="ex-visitors">{{ __('Expected Visitors') }}</label>
        <input type="number" id="ex-visitors" name="expected_visitors" class="form-control" min="0" value="{{ $v('expected_visitors') }}">
      </div>
      <div class="col-md-4 mb-3">
        <label class="form-label" for="ex-fee">{{ __('Admission Fee') }}</label>
        <input type="number" id="ex-fee" name="admission_fee" class="form-control" step="0.01" min="0" value="{{ $v('admission_fee') }}">
      </div>
      <div class="col-md-4 mb-3 d-flex align-items-end">
        <div class="form-check">
          <input type="checkbox" id="ex-free" name="is_free_admission" class="form-check-input" value="1" @checked($v('is_free_admission'))>
          <label class="form-check-label" for="ex-free">{{ __('Free Admission') }}</label>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header"><h5 class="mb-0">{{ __('Notes') }}</h5></div>
  <div class="card-body">
    <label class="form-label" for="ex-notes">{{ __('Internal Notes') }}</label>
    <textarea id="ex-notes" name="notes" class="form-control" rows="3">{{ $v('notes') }}</textarea>
  </div>
</div>
