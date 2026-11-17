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

@section('title', 'Edit Exhibition')

@section('content')
@php
  $types = $types ?? [];
  $statuses = $statuses ?? [];
  $data = $exhibition ?? (object) [];
  $exId = $data->id ?? 0;
@endphp

<div class="row">
  <div class="col-md-8">
    <h1>{{ __('Edit Exhibition') }}</h1>

    <form method="post" action="{{ route('exhibition.edit', ['id' => $exId]) }}" autocomplete="off">
      @csrf
      @include('ahg-exhibition::_form-fields')

      <div class="d-flex justify-content-between">
        <a href="{{ route('exhibition.show', ['id' => $exId]) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
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
      </div>
    </div>
  </div>
</div>
@endsection
