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

@section('title', 'Edit archival description (DACS)')

@section('content')
@php
  $io = $io ?? (object)[];
  $levels = $levels ?? collect();
  $repositories = $repositories ?? collect();
  $descriptionStatuses = $descriptionStatuses ?? collect();
  $descriptionDetails = $descriptionDetails ?? collect();
  $displayStandards = $displayStandards ?? collect();
  $eventTypes = $eventTypes ?? collect();
  $events = $events ?? collect();
  $creators = $creators ?? collect();
  $notes = $notes ?? collect();
  $publicationNotes = $publicationNotes ?? collect();
  $archivistNotes = $archivistNotes ?? collect();
  $subjects = $subjects ?? collect();
  $places = $places ?? collect();
  $genres = $genres ?? collect();
  $nameAccessPoints = $nameAccessPoints ?? collect();
  $alternativeIdentifiers = $alternativeIdentifiers ?? collect();
  $publicationStatusId = $publicationStatusId ?? null;
  $materialLanguages = $materialLanguages ?? collect();
  $materialScripts = $materialScripts ?? collect();
  $languageNotes = $languageNotes ?? '';
  $languageOfDescription = $languageOfDescription ?? '';
  $technicalAccess = $technicalAccess ?? '';
  $relatedMaterialDescriptions = $relatedMaterialDescriptions ?? collect();
  $parentTitle = $parentTitle ?? null;
  $parentSlug = $parentSlug ?? null;
@endphp

<h1>Edit archival description
  <small class="text-muted">(DACS 2nd edition)</small>
</h1>

@if($parentTitle)
  <p class="text-muted">Parent:
    <a href="{{ url('/'.$parentSlug) }}">{{ $parentTitle }}</a>
  </p>
@endif

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(isset($errors) && $errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $err)
        <li>{{ $err }}</li>
      @endforeach
    </ul>
  </div>
@endif

<form method="post" action="{{ route('ahgdacsmanage.edit', ['slug' => $io->slug ?? '']) }}" autocomplete="off">
  @csrf

  @include('dacs-manage::_fields', get_defined_vars())

  {{-- Display standard (standalone editor keeps its own selector) --}}
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
