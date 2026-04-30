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

<form method="post" action="{{ route('ahgdacsmanage.edit', ['slug' => $io->slug ?? '']) }}">
  @csrf

  <div class="accordion mb-3" id="dacs-accordion">

    {{-- Identity area --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#identity">{{ __('Identity area') }}</button>
      </h2>
      <div id="identity" class="accordion-collapse collapse show">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Reference code') }}</label>
            <input type="text" name="identifier" class="form-control" value="{{ old('identifier', $io->identifier ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required value="{{ old('title', $io->title ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Alternate title') }}</label>
            <input type="text" name="alternate_title" class="form-control" value="{{ old('alternate_title', $io->alternate_title ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Level of description') }}</label>
            <select name="level_of_description_id" class="form-select">
              <option value="">—</option>
              @foreach($levels as $lvl)
                <option value="{{ $lvl->id }}" @if(($io->level_of_description_id ?? null) == $lvl->id) selected @endif>{{ $lvl->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Extent and medium') }}</label>
            <textarea name="extent_and_medium" class="form-control" rows="3">{{ old('extent_and_medium', $io->extent_and_medium ?? '') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    {{-- Dates / Creators --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dates">{{ __('Dates &amp; creators') }}</button>
      </h2>
      <div id="dates" class="accordion-collapse collapse">
        <div class="accordion-body">
          <input type="hidden" name="_creatorsIncluded" value="1">
          @if($events->isEmpty())
            <p class="text-muted">No dates recorded.</p>
          @else
            <ul class="list-group mb-3">
              @foreach($events as $evt)
                <li class="list-group-item">
                  <strong>{{ $evt->date_display ?? $evt->event_name ?? '' }}</strong>
                  @if(!empty($evt->start_date)) — {{ $evt->start_date }}@endif
                  @if(!empty($evt->end_date)) / {{ $evt->end_date }}@endif
                  @if(!empty($evt->actor_name)) ({{ $evt->actor_name }})@endif
                </li>
              @endforeach
            </ul>
          @endif

          <h5>{{ __('Creators') }}</h5>
          @foreach($creators as $c)
            <div class="input-group mb-1">
              <input type="hidden" name="creatorIds[]" value="{{ $c->id }}">
              <span class="input-group-text">{{ $c->name }}</span>
            </div>
          @endforeach
          <div class="form-text">Attach existing creator actor IDs via <code>creatorIds[]</code>.</div>
        </div>
      </div>
    </div>

    {{-- Content and structure --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#content">{{ __('Content and structure') }}</button>
      </h2>
      <div id="content" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Scope and content') }}</label>
            <textarea name="scope_and_content" class="form-control" rows="4">{{ old('scope_and_content', $io->scope_and_content ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Archival history') }}</label>
            <textarea name="archival_history" class="form-control" rows="3">{{ old('archival_history', $io->archival_history ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Immediate source of acquisition') }}</label>
            <textarea name="acquisition" class="form-control" rows="3">{{ old('acquisition', $io->acquisition ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Appraisal, destruction and scheduling') }}</label>
            <textarea name="appraisal" class="form-control" rows="3">{{ old('appraisal', $io->appraisal ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Accruals') }}</label>
            <textarea name="accruals" class="form-control" rows="2">{{ old('accruals', $io->accruals ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('System of arrangement') }}</label>
            <textarea name="arrangement" class="form-control" rows="3">{{ old('arrangement', $io->arrangement ?? '') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    {{-- Conditions of access and use --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#conditions">{{ __('Conditions of access and use') }}</button>
      </h2>
      <div id="conditions" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Conditions governing access') }}</label>
            <textarea name="access_conditions" class="form-control" rows="3">{{ old('access_conditions', $io->access_conditions ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Conditions governing reproduction') }}</label>
            <textarea name="reproduction_conditions" class="form-control" rows="3">{{ old('reproduction_conditions', $io->reproduction_conditions ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Language of material') }}</label>
            @foreach($materialLanguages as $lang)
              <input type="hidden" name="materialLanguages[]" value="{{ $lang }}">
            @endforeach
            <p class="text-muted">{{ $materialLanguages->isNotEmpty() ? $materialLanguages->implode(', ') : '—' }}</p>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Script of material') }}</label>
            @foreach($materialScripts as $scr)
              <input type="hidden" name="materialScripts[]" value="{{ $scr }}">
            @endforeach
            <p class="text-muted">{{ $materialScripts->isNotEmpty() ? $materialScripts->implode(', ') : '—' }}</p>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Language &amp; script notes') }}</label>
            <textarea name="languageNotes" class="form-control" rows="2">{{ old('languageNotes', $languageNotes) }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Physical characteristics and technical requirements') }}</label>
            <textarea name="physical_characteristics" class="form-control" rows="3">{{ old('physical_characteristics', $io->physical_characteristics ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Technical access') }}</label>
            <textarea name="technicalAccess" class="form-control" rows="2">{{ old('technicalAccess', $technicalAccess) }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Finding aids') }}</label>
            <textarea name="finding_aids" class="form-control" rows="3">{{ old('finding_aids', $io->finding_aids ?? '') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    {{-- Allied materials --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#allied">{{ __('Allied materials area') }}</button>
      </h2>
      <div id="allied" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Existence and location of originals') }}</label>
            <textarea name="location_of_originals" class="form-control" rows="2">{{ old('location_of_originals', $io->location_of_originals ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Existence and location of copies') }}</label>
            <textarea name="location_of_copies" class="form-control" rows="2">{{ old('location_of_copies', $io->location_of_copies ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Related units of description') }}</label>
            <textarea name="related_units_of_description" class="form-control" rows="3">{{ old('related_units_of_description', $io->related_units_of_description ?? '') }}</textarea>
          </div>
          @if($relatedMaterialDescriptions->isNotEmpty())
            <h6>{{ __('Related descriptions') }}</h6>
            <ul>
              @foreach($relatedMaterialDescriptions as $rel)
                <li><input type="hidden" name="relatedMaterialDescriptionIds[]" value="{{ $rel->id }}">
                  <a href="{{ url('/'.$rel->slug) }}">{{ $rel->title }}</a></li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>
    </div>

    {{-- Notes --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#notes">{{ __('Notes area') }}</button>
      </h2>
      <div id="notes" class="accordion-collapse collapse">
        <div class="accordion-body">
          <h6>{{ __('Publication notes') }}</h6>
          @forelse($publicationNotes as $n)
            <p class="mb-1">{{ $n->content }}</p>
          @empty
            <p class="text-muted">None.</p>
          @endforelse
          <h6>{{ __('Archivist notes') }}</h6>
          @forelse($archivistNotes as $n)
            <p class="mb-1">{{ $n->content }}</p>
          @empty
            <p class="text-muted">None.</p>
          @endforelse
          <h6>{{ __('General notes') }}</h6>
          @forelse($notes as $n)
            <p class="mb-1">{{ $n->content }}</p>
          @empty
            <p class="text-muted">None.</p>
          @endforelse
        </div>
      </div>
    </div>

    {{-- Access points --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#accesspoints">{{ __('Access points') }}</button>
      </h2>
      <div id="accesspoints" class="accordion-collapse collapse">
        <div class="accordion-body">
          <h6>{{ __('Subject access points') }}</h6>
          @foreach($subjects as $t)
            <span class="badge bg-secondary me-1">{{ $t->name }}</span>
            <input type="hidden" name="subjectAccessPointIds[]" value="{{ $t->term_id }}">
          @endforeach
          <h6 class="mt-3">{{ __('Place access points') }}</h6>
          @foreach($places as $t)
            <span class="badge bg-secondary me-1">{{ $t->name }}</span>
            <input type="hidden" name="placeAccessPointIds[]" value="{{ $t->term_id }}">
          @endforeach
          <h6 class="mt-3">{{ __('Genre access points') }}</h6>
          @foreach($genres as $t)
            <span class="badge bg-secondary me-1">{{ $t->name }}</span>
            <input type="hidden" name="genreAccessPointIds[]" value="{{ $t->term_id }}">
          @endforeach
          <h6 class="mt-3">{{ __('Name access points') }}</h6>
          @foreach($nameAccessPoints as $n)
            <span class="badge bg-secondary me-1">{{ $n->name }}</span>
            <input type="hidden" name="nameAccessPointIds[]" value="{{ $n->actor_id }}">
          @endforeach
        </div>
      </div>
    </div>

    {{-- Description control area --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#control">{{ __('Description control area') }}</button>
      </h2>
      <div id="control" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Description identifier') }}</label>
            <input type="text" name="description_identifier" class="form-control" value="{{ old('description_identifier', $io->description_identifier ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Institution identifier') }}</label>
            <input type="text" name="institution_responsible_identifier" class="form-control" value="{{ old('institution_responsible_identifier', $io->institution_responsible_identifier ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Rules or conventions') }}</label>
            <textarea name="rules" class="form-control" rows="2">{{ old('rules', $io->rules ?? 'DACS 2nd edition') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Status') }}</label>
            <select name="description_status_id" class="form-select">
              <option value="">—</option>
              @foreach($descriptionStatuses as $s)
                <option value="{{ $s->id }}" @if(($io->description_status_id ?? null) == $s->id) selected @endif>{{ $s->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Level of detail') }}</label>
            <select name="description_detail_id" class="form-select">
              <option value="">—</option>
              @foreach($descriptionDetails as $d)
                <option value="{{ $d->id }}" @if(($io->description_detail_id ?? null) == $d->id) selected @endif>{{ $d->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Language(s) of description') }}</label>
            <input type="text" name="languageNotes" class="form-control" value="{{ old('languageNotes', $languageNotes) }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Sources') }}</label>
            <textarea name="sources" class="form-control" rows="2">{{ old('sources', $io->sources ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Revision history') }}</label>
            <textarea name="revision_history" class="form-control" rows="2">{{ old('revision_history', $io->revision_history ?? '') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    {{-- Administration area --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#admin">{{ __('Administration area') }}</button>
      </h2>
      <div id="admin" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Repository') }}</label>
            <select name="repository_id" class="form-select">
              <option value="">—</option>
              @foreach($repositories as $r)
                <option value="{{ $r->id }}" @if(($io->repository_id ?? null) == $r->id) selected @endif>{{ $r->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Display standard') }}</label>
            <select name="display_standard_id" class="form-select">
              <option value="">—</option>
              @foreach($displayStandards as $ds)
                <option value="{{ $ds->id }}" @if(($io->display_standard_id ?? null) == $ds->id) selected @endif>{{ $ds->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Publication status') }}</label>
            <select name="publication_status_id" class="form-select">
              <option value="">—</option>
              <option value="159" @if($publicationStatusId == 159) selected @endif>{{ __('Draft') }}</option>
              <option value="160" @if($publicationStatusId == 160) selected @endif>{{ __('Published') }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

  </div>

  <ul class="actions mb-3 nav gap-2">
    <li><a href="{{ url('/'.($io->slug ?? '')) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
    <li><button class="btn atom-btn-outline-success" type="submit">{{ __('Save') }}</button></li>
  </ul>
</form>
@endsection
