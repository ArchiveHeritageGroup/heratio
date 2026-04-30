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

@section('title', 'Edit archival description (RAD)')

@section('content')
@php
  $io = $io ?? (object)[];
  $levels = $levels ?? collect();
  $repositories = $repositories ?? collect();
  $descriptionStatuses = $descriptionStatuses ?? collect();
  $descriptionDetails = $descriptionDetails ?? collect();
  $displayStandards = $displayStandards ?? collect();
  $eventTypes = $eventTypes ?? collect();
  $materialTypeOptions = $materialTypeOptions ?? collect();
  $events = $events ?? collect();
  $notes = $notes ?? collect();
  $publicationNotes = $publicationNotes ?? collect();
  $archivistNotes = $archivistNotes ?? collect();
  $subjects = $subjects ?? collect();
  $places = $places ?? collect();
  $genres = $genres ?? collect();
  $nameAccessPoints = $nameAccessPoints ?? collect();
  $materialTypes = $materialTypes ?? collect();
  $alternativeIdentifiers = $alternativeIdentifiers ?? collect();
  $publicationStatusId = $publicationStatusId ?? null;
  $materialLanguages = $materialLanguages ?? collect();
  $materialScripts = $materialScripts ?? collect();
  $languagesOfDescription = $languagesOfDescription ?? collect();
  $scriptsOfDescription = $scriptsOfDescription ?? collect();
  $radProperties = $radProperties ?? [];
  $relatedMaterialDescriptions = $relatedMaterialDescriptions ?? collect();
  $parentTitle = $parentTitle ?? null;
  $parentSlug = $parentSlug ?? null;
  $selectedMaterialTypeIds = $materialTypes->pluck('term_id')->all();
@endphp

<h1>Edit archival description
  <small class="text-muted">(RAD Jul 2008)</small>
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

<form method="post" action="{{ route('ahgradmanage.edit', ['slug' => $io->slug ?? '']) }}">
  @csrf

  <div class="accordion mb-3" id="rad-accordion">

    {{-- Title and statement of responsibility --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#rad-title">{{ __('Title and statement of responsibility') }}</button>
      </h2>
      <div id="rad-title" class="accordion-collapse collapse show">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Reference code') }}</label>
            <input type="text" name="identifier" class="form-control" value="{{ old('identifier', $io->identifier ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Title proper <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required value="{{ old('title', $io->title ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Title statement of responsibility') }}</label>
            <input type="text" name="titleStatementOfResponsibility" class="form-control" value="{{ old('titleStatementOfResponsibility', $radProperties['titleStatementOfResponsibility'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Other title information') }}</label>
            <input type="text" name="otherTitleInformation" class="form-control" value="{{ old('otherTitleInformation', $radProperties['otherTitleInformation'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Parallel / alternate title') }}</label>
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
            <label class="form-label">{{ __('Material type') }}</label>
            <select name="materialTypeIds[]" class="form-select" multiple size="6">
              @foreach($materialTypeOptions as $opt)
                <option value="{{ $opt->id }}" @if(in_array($opt->id, $selectedMaterialTypeIds)) selected @endif>{{ $opt->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>
    </div>

    {{-- Edition --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rad-edition">{{ __('Edition') }}</button>
      </h2>
      <div id="rad-edition" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Edition statement') }}</label>
            <input type="text" name="edition" class="form-control" value="{{ old('edition', $io->edition ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Edition statement of responsibility') }}</label>
            <input type="text" name="editionStatementOfResponsibility" class="form-control" value="{{ old('editionStatementOfResponsibility', $radProperties['editionStatementOfResponsibility'] ?? '') }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Class of material specific details --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rad-classspec">{{ __('Class of material specific details') }}</button>
      </h2>
      <div id="rad-classspec" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Statement of scale (cartographic)') }}</label>
            <input type="text" name="statementOfScaleCartographic" class="form-control" value="{{ old('statementOfScaleCartographic', $radProperties['statementOfScaleCartographic'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Statement of projection') }}</label>
            <input type="text" name="statementOfProjection" class="form-control" value="{{ old('statementOfProjection', $radProperties['statementOfProjection'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Statement of coordinates') }}</label>
            <input type="text" name="statementOfCoordinates" class="form-control" value="{{ old('statementOfCoordinates', $radProperties['statementOfCoordinates'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Statement of scale (architectural)') }}</label>
            <input type="text" name="statementOfScaleArchitectural" class="form-control" value="{{ old('statementOfScaleArchitectural', $radProperties['statementOfScaleArchitectural'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Issuing jurisdiction and denomination') }}</label>
            <input type="text" name="issuingJurisdictionAndDenomination" class="form-control" value="{{ old('issuingJurisdictionAndDenomination', $radProperties['issuingJurisdictionAndDenomination'] ?? '') }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Dates of creation --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rad-dates">{{ __('Dates of creation') }}</button>
      </h2>
      <div id="rad-dates" class="accordion-collapse collapse">
        <div class="accordion-body">
          <input type="hidden" name="_eventsIncluded" value="1">
          @if($events->isEmpty())
            <p class="text-muted">No dates recorded.</p>
          @else
            @foreach($events as $i => $evt)
              <div class="row g-2 mb-2">
                <div class="col-md-3">
                  <input type="hidden" name="events[{{ $i }}][type_id]" value="{{ $evt->type_id }}">
                  <input type="text" name="events[{{ $i }}][date_display]" class="form-control" value="{{ $evt->date_display }}" placeholder="{{ __('Date display') }}">
                </div>
                <div class="col-md-3">
                  <input type="date" name="events[{{ $i }}][start_date]" class="form-control" value="{{ $evt->start_date }}">
                </div>
                <div class="col-md-3">
                  <input type="date" name="events[{{ $i }}][end_date]" class="form-control" value="{{ $evt->end_date }}">
                </div>
                <div class="col-md-3">
                  <input type="hidden" name="events[{{ $i }}][actor_id]" value="{{ $evt->actor_id }}">
                  <span class="text-muted">{{ $evt->actor_name ?? '' }}</span>
                </div>
              </div>
            @endforeach
          @endif
        </div>
      </div>
    </div>

    {{-- Physical description --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rad-physical">{{ __('Physical description') }}</button>
      </h2>
      <div id="rad-physical" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Extent and physical description') }}</label>
            <textarea name="extent_and_medium" class="form-control" rows="3">{{ old('extent_and_medium', $io->extent_and_medium ?? '') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    {{-- Publisher's series --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rad-series">{{ __("Publisher's series") }}</button>
      </h2>
      <div id="rad-series" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __("Title proper of publisher's series") }}</label>
            <input type="text" name="titleProperOfPublishersSeries" class="form-control" value="{{ old('titleProperOfPublishersSeries', $radProperties['titleProperOfPublishersSeries'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __("Parallel titles of publisher's series") }}</label>
            <input type="text" name="parallelTitleOfPublishersSeries" class="form-control" value="{{ old('parallelTitleOfPublishersSeries', $radProperties['parallelTitleOfPublishersSeries'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __("Other title information of publisher's series") }}</label>
            <input type="text" name="otherTitleInformationOfPublishersSeries" class="form-control" value="{{ old('otherTitleInformationOfPublishersSeries', $radProperties['otherTitleInformationOfPublishersSeries'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __("Statement of responsibility relating to publisher's series") }}</label>
            <input type="text" name="statementOfResponsibilityRelatingToPublishersSeries" class="form-control" value="{{ old('statementOfResponsibilityRelatingToPublishersSeries', $radProperties['statementOfResponsibilityRelatingToPublishersSeries'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __("Numbering within publisher's series") }}</label>
            <input type="text" name="numberingWithinPublishersSeries" class="form-control" value="{{ old('numberingWithinPublishersSeries', $radProperties['numberingWithinPublishersSeries'] ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __("Note on publisher's series") }}</label>
            <textarea name="noteOnPublishersSeries" class="form-control" rows="2">{{ old('noteOnPublishersSeries', $radProperties['noteOnPublishersSeries'] ?? '') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    {{-- Archival description --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rad-archival">{{ __('Archival description') }}</button>
      </h2>
      <div id="rad-archival" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Scope and content') }}</label>
            <textarea name="scope_and_content" class="form-control" rows="4">{{ old('scope_and_content', $io->scope_and_content ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Custodial history') }}</label>
            <textarea name="archival_history" class="form-control" rows="3">{{ old('archival_history', $io->archival_history ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Immediate source of acquisition') }}</label>
            <textarea name="acquisition" class="form-control" rows="3">{{ old('acquisition', $io->acquisition ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Arrangement') }}</label>
            <textarea name="arrangement" class="form-control" rows="3">{{ old('arrangement', $io->arrangement ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Accruals') }}</label>
            <textarea name="accruals" class="form-control" rows="2">{{ old('accruals', $io->accruals ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Appraisal') }}</label>
            <textarea name="appraisal" class="form-control" rows="3">{{ old('appraisal', $io->appraisal ?? '') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    {{-- Standard number --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rad-stdnum">{{ __('Standard number') }}</button>
      </h2>
      <div id="rad-stdnum" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Standard number') }}</label>
            <input type="text" name="standardNumber" class="form-control" value="{{ old('standardNumber', $radProperties['standardNumber'] ?? '') }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Notes --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rad-notes">{{ __('Notes') }}</button>
      </h2>
      <div id="rad-notes" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Conditions governing access') }}</label>
            <textarea name="access_conditions" class="form-control" rows="2">{{ old('access_conditions', $io->access_conditions ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Conditions governing reproduction') }}</label>
            <textarea name="reproduction_conditions" class="form-control" rows="2">{{ old('reproduction_conditions', $io->reproduction_conditions ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Physical characteristics and technical requirements') }}</label>
            <textarea name="physical_characteristics" class="form-control" rows="2">{{ old('physical_characteristics', $io->physical_characteristics ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Finding aids') }}</label>
            <textarea name="finding_aids" class="form-control" rows="2">{{ old('finding_aids', $io->finding_aids ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Language notes') }}</label>
            <textarea name="languageNotes" class="form-control" rows="2">{{ old('languageNotes', $radProperties['languageNotes'] ?? '') }}</textarea>
          </div>
          <h6 class="mt-3">{{ __('Publication notes') }}</h6>
          @forelse($publicationNotes as $n)
            <p class="mb-1">{{ $n->content }}</p>
          @empty
            <p class="text-muted">None.</p>
          @endforelse
          <h6>{{ __("Archivist's notes") }}</h6>
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
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rad-access">{{ __('Access points') }}</button>
      </h2>
      <div id="rad-access" class="accordion-collapse collapse">
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

    {{-- Allied materials area --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rad-allied">{{ __('Allied materials area') }}</button>
      </h2>
      <div id="rad-allied" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Location of originals') }}</label>
            <textarea name="location_of_originals" class="form-control" rows="2">{{ old('location_of_originals', $io->location_of_originals ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Availability of other formats') }}</label>
            <textarea name="location_of_copies" class="form-control" rows="2">{{ old('location_of_copies', $io->location_of_copies ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Related units of description') }}</label>
            <textarea name="related_units_of_description" class="form-control" rows="3">{{ old('related_units_of_description', $io->related_units_of_description ?? '') }}</textarea>
          </div>
          @if($relatedMaterialDescriptions->isNotEmpty())
            <ul>
              @foreach($relatedMaterialDescriptions as $rel)
                <li>
                  <input type="hidden" name="relatedMaterialDescriptionIds[]" value="{{ $rel->id }}">
                  <a href="{{ url('/'.$rel->slug) }}">{{ $rel->title }}</a>
                </li>
              @endforeach
            </ul>
          @endif
        </div>
      </div>
    </div>

    {{-- Description control --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rad-control">{{ __('Description control area') }}</button>
      </h2>
      <div id="rad-control" class="accordion-collapse collapse">
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
            <textarea name="rules" class="form-control" rows="2">{{ old('rules', $io->rules ?? 'RAD Jul 2008') }}</textarea>
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

    {{-- Admin --}}
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rad-admin">{{ __('Administration') }}</button>
      </h2>
      <div id="rad-admin" class="accordion-collapse collapse">
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
