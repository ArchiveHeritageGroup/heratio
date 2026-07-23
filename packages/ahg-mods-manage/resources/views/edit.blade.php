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

@section('title', 'Edit archival description (MODS)')

@section('content')
@php
  $io = $io ?? (object)[];
  $repositories = $repositories ?? collect();
  $displayStandards = $displayStandards ?? collect();
  $eventTypes = $eventTypes ?? collect();
  $modsTypeOptions = $modsTypeOptions ?? collect();
  $events = $events ?? collect();
  $creationEvents = $creationEvents ?? collect();
  $publicationEvents = $publicationEvents ?? collect();
  $publisherActorId = $publisherActorId ?? null;
  $publisherActorName = $publisherActorName ?? '';
  $publisherFreeText = $publisherFreeText ?? '';
  $placeOfPublicationId = $placeOfPublicationId ?? null;
  $placeOfPublicationName = $placeOfPublicationName ?? '';
  $modsNote = $modsNote ?? '';
  $subjects = $subjects ?? collect();
  $places = $places ?? collect();
  $nameAccessPoints = $nameAccessPoints ?? collect();
  $modsTypes = $modsTypes ?? collect();
  $publicationStatusId = $publicationStatusId ?? null;
  $materialLanguages = $materialLanguages ?? collect();
  $parentTitle = $parentTitle ?? null;
  $parentSlug = $parentSlug ?? null;
  $selectedModsTypeIds = $modsTypes->pluck('term_id')->all();

  // First creation / publication event drives the single-value originInfo inputs
  $firstCreation = $creationEvents->first();
  $firstPublication = $publicationEvents->first();
  $creationDateDisplay = $firstCreation->date_display ?? '';
  $creationStartDate = $firstCreation->start_date ?? '';
  $publicationDateDisplay = $firstPublication->date_display ?? '';
  $publicationStartDate = $firstPublication->start_date ?? '';

  // Build the existing-items lists for the multi-select autocompletes
  $existingSubjects = $subjects->map(fn($t) => ['id' => $t->term_id, 'name' => $t->name])->all();
  $existingPlaces   = $places->map(fn($t) => ['id' => $t->term_id, 'name' => $t->name])->all();
  $existingNames    = $nameAccessPoints->map(fn($n) => ['id' => $n->actor_id, 'name' => $n->name])->all();
@endphp

<h1>Edit archival description
  <small class="text-muted">(MODS version 3.3)</small>
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

<form method="post" action="{{ route('ahgmodsmanage.edit', ['slug' => $io->slug ?? '']) }}">
  @csrf

  @include('mods-manage::_fields', get_defined_vars())

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
