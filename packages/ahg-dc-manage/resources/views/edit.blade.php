{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems

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

<form method="post" action="{{ route('ahgdcmanage.edit', ['slug' => $io->slug ?? '']) }}">
  @csrf

  <div class="accordion mb-3" id="dc-accordion">

    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#dc-core">Dublin Core elements</button>
      </h2>
      <div id="dc-core" class="accordion-collapse collapse show">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">dc:identifier</label>
            <input type="text" name="identifier" class="form-control" value="{{ old('identifier', $io->identifier ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">dc:title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required value="{{ old('title', $io->title ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">dc:description (scope and content)</label>
            <textarea name="scope_and_content" class="form-control" rows="4">{{ old('scope_and_content', $io->scope_and_content ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">dc:format (extent and medium)</label>
            <textarea name="extent_and_medium" class="form-control" rows="2">{{ old('extent_and_medium', $io->extent_and_medium ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">dc:rights (access conditions)</label>
            <textarea name="access_conditions" class="form-control" rows="2">{{ old('access_conditions', $io->access_conditions ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">dc:source (location of originals)</label>
            <textarea name="location_of_originals" class="form-control" rows="2">{{ old('location_of_originals', $io->location_of_originals ?? '') }}</textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dc-type">dc:type</button>
      </h2>
      <div id="dc-type" class="accordion-collapse collapse">
        <div class="accordion-body">
          <select name="dcTypeIds[]" class="form-select" multiple size="8">
            @foreach($dcTypeOptions as $opt)
              <option value="{{ $opt->id }}" @if(in_array($opt->id, $selectedDcTypeIds)) selected @endif>{{ $opt->name }}</option>
            @endforeach
          </select>
          <div class="form-text">Hold Ctrl/Cmd to select multiple.</div>
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dc-access">Access points</button>
      </h2>
      <div id="dc-access" class="accordion-collapse collapse">
        <div class="accordion-body">
          <h6>dc:subject</h6>
          @foreach($subjects as $t)
            <span class="badge bg-secondary me-1">{{ $t->name }}</span>
            <input type="hidden" name="subjectAccessPointIds[]" value="{{ $t->term_id }}">
          @endforeach
          <h6 class="mt-3">dc:coverage (place)</h6>
          @foreach($places as $t)
            <span class="badge bg-secondary me-1">{{ $t->name }}</span>
            <input type="hidden" name="placeAccessPointIds[]" value="{{ $t->term_id }}">
          @endforeach
          <h6 class="mt-3">dc:creator</h6>
          @foreach($creators as $c)
            <span class="badge bg-info me-1">{{ $c->name }}</span>
          @endforeach
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dc-language">dc:language</button>
      </h2>
      <div id="dc-language" class="accordion-collapse collapse">
        <div class="accordion-body">
          @foreach($materialLanguages as $lang)
            <input type="hidden" name="materialLanguages[]" value="{{ $lang }}">
            <span class="badge bg-secondary me-1">{{ $lang }}</span>
          @endforeach
          @if($materialLanguages->isEmpty())
            <p class="text-muted">None recorded.</p>
          @endif
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dc-admin">Administration</button>
      </h2>
      <div id="dc-admin" class="accordion-collapse collapse">
        <div class="accordion-body">
          <div class="mb-3">
            <label class="form-label">Repository</label>
            <select name="repository_id" class="form-select">
              <option value="">—</option>
              @foreach($repositories as $r)
                <option value="{{ $r->id }}" @if(($io->repository_id ?? null) == $r->id) selected @endif>{{ $r->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Display standard</label>
            <select name="display_standard_id" class="form-select">
              <option value="">—</option>
              @foreach($displayStandards as $ds)
                <option value="{{ $ds->id }}" @if(($io->display_standard_id ?? null) == $ds->id) selected @endif>{{ $ds->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Publication status</label>
            <select name="publication_status_id" class="form-select">
              <option value="">—</option>
              <option value="159" @if($publicationStatusId == 159) selected @endif>Draft</option>
              <option value="160" @if($publicationStatusId == 160) selected @endif>Published</option>
            </select>
          </div>
        </div>
      </div>
    </div>

  </div>

  <ul class="actions mb-3 nav gap-2">
    <li><a href="{{ url('/'.($io->slug ?? '')) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
    <li><button class="btn atom-btn-outline-success" type="submit">Save</button></li>
  </ul>
</form>
@endsection
