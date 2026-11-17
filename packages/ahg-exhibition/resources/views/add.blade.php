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

@section('title', 'New Exhibition')

@section('content')
@php
  $types = $types ?? [];
  $statuses = $statuses ?? [];
  $data = (object) ($formData ?? []);
@endphp

<div class="row">
  <div class="col-md-8">
    <h1>{{ __('New Exhibition') }}</h1>

    <form method="post" action="{{ route('exhibition.add') }}" autocomplete="off">
      @csrf
      @include('ahg-exhibition::_form-fields')

      <div class="d-flex justify-content-between">
        <a href="{{ route('exhibition.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary">{{ __('Create Exhibition') }}</button>
      </div>
    </form>
  </div>

  <div class="col-md-4">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Help') }}</h5>
      </div>
      <div class="card-body">
        <h6>{{ __('Exhibition Types') }}</h6>
        <ul class="small">
          <li><strong>{{ __('Permanent') }}</strong> - {{ __('Long-term display, rarely changes') }}</li>
          <li><strong>{{ __('Temporary') }}</strong> - {{ __('Fixed duration, typically 3-12 months') }}</li>
          <li><strong>{{ __('Traveling') }}</strong> - {{ __('Moves between venues') }}</li>
          <li><strong>{{ __('Online') }}</strong> - {{ __('Virtual/digital exhibition') }}</li>
          <li><strong>{{ __('Pop-up') }}</strong> - {{ __('Short-term, often &lt; 1 month') }}</li>
        </ul>

        <h6 class="mt-3">{{ __('After Creating') }}</h6>
        <p class="small text-muted">{{ __('After creating the exhibition, you can:') }}</p>
        <ul class="small text-muted">
          <li>{{ __('Add sections/galleries') }}</li>
          <li>{{ __('Add objects from the collection') }}</li>
          <li>{{ __('Create storylines and narratives') }}</li>
          <li>{{ __('Schedule events') }}</li>
          <li>{{ __('Generate checklists') }}</li>
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection
