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

@section('title', 'Edit archival description (Dublin Core)')

@section('content')
@php
  $io = $io ?? (object)[];
  $repositories = $repositories ?? collect();
  $displayStandards = $displayStandards ?? collect();
  $eventTypes = $eventTypes ?? collect();
  $dcTypeOptions = $dcTypeOptions ?? collect();
  $events = $events ?? collect();
  $creators = $creators ?? collect();
  $subjects = $subjects ?? collect();
  $places = $places ?? collect();
  $dcTypes = $dcTypes ?? collect();
  $publicationStatusId = $publicationStatusId ?? null;
  $materialLanguages = $materialLanguages ?? collect();
  $parentTitle = $parentTitle ?? null;
  $parentSlug = $parentSlug ?? null;
  $selectedDcTypeIds = $dcTypes->pluck('term_id')->all();
@endphp

<h1>Edit archival description
  <small class="text-muted">(Dublin Core Simple 1.1)</small>
</h1>

@if($parentTitle)
  <p class="text-muted">Parent: <a href="{{ url('/'.$parentSlug) }}">{{ $parentTitle }}</a></p>
@endif

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(isset($errors) && $errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
    </ul>
  </div>
@endif

<form method="post" action="{{ route('ahgdcmanage.edit', ['slug' => $io->slug ?? '']) }}" autocomplete="off">
  @csrf

  @include('dc-manage::_fields', get_defined_vars())

  <div class="mb-3">
    <label class="form-label">{{ __('Display standard') }}</label>
    <select name="display_standard_id" class="form-select">
      <option value="">—</option>
      @foreach(($displayStandards ?? collect()) as $ds)
        <option value="{{ $ds->id }}" @if(($io->display_standard_id ?? null) == $ds->id) selected @endif>{{ $ds->name }}</option>
      @endforeach
    </select>
  </div>

  <ul class="actions mb-3 nav gap-2">
    <li><a href="{{ url('/'.($io->slug ?? '')) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
    <li><button class="btn atom-btn-outline-success" type="submit">{{ __('Save') }}</button></li>
  </ul>
</form>
@endsection
