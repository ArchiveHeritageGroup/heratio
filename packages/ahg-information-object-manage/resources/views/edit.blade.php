@extends('theme::layouts.1col')

@section('title', 'Edit: ' . ($io->title ?? 'Untitled'))
@section('body-class', 'edit informationobject')

@section('content')
  <h1>Edit: {{ $io->title ?? '[Untitled]' }}</h1>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('informationobject.update', $io->slug) }}">
    @csrf
    @method('PUT')

    {{-- ===== ISAD(G) 3.1 Identity area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Identity area <small class="text-muted">(ISAD 3.1)</small></legend>

      <div class="mb-3">
        <label for="identifier" class="form-label">Identifier</label>
        <input type="text" class="form-control" id="identifier" name="identifier"
               value="{{ old('identifier', $io->identifier) }}">
      </div>

      <div class="mb-3">
        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title"
               value="{{ old('title', $io->title) }}" required>
        @error('title')
          <div class="invalid-feedback">{{ $message }}</div>
        @enderror
      </div>

      <div class="mb-3">
        <label for="level_of_description_id" class="form-label">Level of description</label>
        <select class="form-select" id="level_of_description_id" name="level_of_description_id">
          <option value="">-- Select --</option>
          @foreach($levels as $level)
            <option value="{{ $level->id }}" @selected(old('level_of_description_id', $io->level_of_description_id) == $level->id)>
              {{ $level->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="mb-3">
        <label for="repository_id" class="form-label">Repository</label>
        <select class="form-select" id="repository_id" name="repository_id">
          <option value="">-- Select --</option>
          @foreach($repositories as $repo)
            <option value="{{ $repo->id }}" @selected(old('repository_id', $io->repository_id) == $repo->id)>
              {{ $repo->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="mb-3">
        <label for="alternate_title" class="form-label">Alternate title</label>
        <input type="text" class="form-control" id="alternate_title" name="alternate_title"
               value="{{ old('alternate_title', $io->alternate_title) }}">
      </div>

      <div class="mb-3">
        <label for="edition" class="form-label">Edition</label>
        <input type="text" class="form-control" id="edition" name="edition"
               value="{{ old('edition', $io->edition) }}">
      </div>

      <div class="mb-3">
        <label for="extent_and_medium" class="form-label">Extent and medium</label>
        <textarea class="form-control" id="extent_and_medium" name="extent_and_medium" rows="3">{{ old('extent_and_medium', $io->extent_and_medium) }}</textarea>
      </div>
    </fieldset>

    {{-- ===== ISAD(G) 3.2 Context area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Context area <small class="text-muted">(ISAD 3.2)</small></legend>

      <div class="mb-3">
        <label for="creators" class="form-label">Creator(s)</label>
        <input type="text" class="form-control" id="creators" name="creators" value="{{ old('creators', $io->creators ?? '') }}" placeholder="Type to search authority records...">
        <div class="form-text">Link to existing authority records as creators</div>
      </div>

      <div class="mb-3">
        <label for="archival_history" class="form-label">Archival history</label>
        <textarea class="form-control" id="archival_history" name="archival_history" rows="3">{{ old('archival_history', $io->archival_history) }}</textarea>
      </div>

      <div class="mb-3">
        <label for="acquisition" class="form-label">Immediate source of acquisition or transfer</label>
        <textarea class="form-control" id="acquisition" name="acquisition" rows="3">{{ old('acquisition', $io->acquisition) }}</textarea>
      </div>
    </fieldset>

    {{-- ===== ISAD(G) 3.3 Content and structure area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Content and structure area <small class="text-muted">(ISAD 3.3)</small></legend>

      <div class="mb-3">
        <label for="scope_and_content" class="form-label">Scope and content</label>
        <textarea class="form-control" id="scope_and_content" name="scope_and_content" rows="4">{{ old('scope_and_content', $io->scope_and_content) }}</textarea>
      </div>

      <div class="mb-3">
        <label for="appraisal" class="form-label">Appraisal, destruction and scheduling information</label>
        <textarea class="form-control" id="appraisal" name="appraisal" rows="3">{{ old('appraisal', $io->appraisal) }}</textarea>
      </div>

      <div class="mb-3">
        <label for="accruals" class="form-label">Accruals</label>
        <textarea class="form-control" id="accruals" name="accruals" rows="3">{{ old('accruals', $io->accruals) }}</textarea>
      </div>

      <div class="mb-3">
        <label for="arrangement" class="form-label">System of arrangement</label>
        <textarea class="form-control" id="arrangement" name="arrangement" rows="3">{{ old('arrangement', $io->arrangement) }}</textarea>
      </div>
    </fieldset>

    {{-- ===== ISAD(G) 3.4 Conditions of access and use area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Conditions of access and use area <small class="text-muted">(ISAD 3.4)</small></legend>

      <div class="mb-3">
        <label for="access_conditions" class="form-label">Conditions governing access</label>
        <textarea class="form-control" id="access_conditions" name="access_conditions" rows="3">{{ old('access_conditions', $io->access_conditions) }}</textarea>
      </div>

      <div class="mb-3">
        <label for="reproduction_conditions" class="form-label">Conditions governing reproduction</label>
        <textarea class="form-control" id="reproduction_conditions" name="reproduction_conditions" rows="3">{{ old('reproduction_conditions', $io->reproduction_conditions) }}</textarea>
      </div>

      <div class="mb-3">
        <label for="physical_characteristics" class="form-label">Physical characteristics and technical requirements</label>
        <textarea class="form-control" id="physical_characteristics" name="physical_characteristics" rows="3">{{ old('physical_characteristics', $io->physical_characteristics) }}</textarea>
      </div>

      <div class="mb-3">
        <label for="finding_aids" class="form-label">Finding aids</label>
        <textarea class="form-control" id="finding_aids" name="finding_aids" rows="3">{{ old('finding_aids', $io->finding_aids) }}</textarea>
      </div>
    </fieldset>

    {{-- ===== ISAD(G) 3.5 Allied materials area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Allied materials area <small class="text-muted">(ISAD 3.5)</small></legend>

      <div class="mb-3">
        <label for="location_of_originals" class="form-label">Existence and location of originals</label>
        <textarea class="form-control" id="location_of_originals" name="location_of_originals" rows="3">{{ old('location_of_originals', $io->location_of_originals) }}</textarea>
      </div>

      <div class="mb-3">
        <label for="location_of_copies" class="form-label">Existence and location of copies</label>
        <textarea class="form-control" id="location_of_copies" name="location_of_copies" rows="3">{{ old('location_of_copies', $io->location_of_copies) }}</textarea>
      </div>

      <div class="mb-3">
        <label for="related_units_of_description" class="form-label">Related units of description</label>
        <textarea class="form-control" id="related_units_of_description" name="related_units_of_description" rows="3">{{ old('related_units_of_description', $io->related_units_of_description) }}</textarea>
      </div>
    </fieldset>

    {{-- ===== ISAD(G) 3.6 Notes area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Notes area <small class="text-muted">(ISAD 3.6)</small></legend>

      <div class="mb-3">
        <label for="general_note" class="form-label">General note</label>
        <textarea class="form-control" id="general_note" name="general_note" rows="3">{{ old('general_note', $io->general_note ?? '') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="archivist_note" class="form-label">Archivist's note</label>
        <textarea class="form-control" id="archivist_note" name="archivist_note" rows="3">{{ old('archivist_note', $io->archivist_note ?? '') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="publication_note" class="form-label">Publication note</label>
        <textarea class="form-control" id="publication_note" name="publication_note" rows="3">{{ old('publication_note', $io->publication_note ?? '') }}</textarea>
      </div>
    </fieldset>

    {{-- ===== Access points ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Access points</legend>

      <div class="mb-3">
        <label for="subject_access_points" class="form-label">Subject access points</label>
        <input type="text" class="form-control" id="subject_access_points" name="subject_access_points" value="{{ old('subject_access_points', $io->subject_access_points ?? '') }}" placeholder="Type to search subjects...">
        <div class="form-text">Separate multiple subjects with semicolons</div>
      </div>

      <div class="mb-3">
        <label for="place_access_points" class="form-label">Place access points</label>
        <input type="text" class="form-control" id="place_access_points" name="place_access_points" value="{{ old('place_access_points', $io->place_access_points ?? '') }}" placeholder="Type to search places...">
        <div class="form-text">Separate multiple places with semicolons</div>
      </div>

      <div class="mb-3">
        <label for="genre_access_points" class="form-label">Genre access points</label>
        <input type="text" class="form-control" id="genre_access_points" name="genre_access_points" value="{{ old('genre_access_points', $io->genre_access_points ?? '') }}" placeholder="Type to search genres...">
      </div>

      <div class="mb-3">
        <label for="name_access_points" class="form-label">Name access points</label>
        <input type="text" class="form-control" id="name_access_points" name="name_access_points" value="{{ old('name_access_points', $io->name_access_points ?? '') }}" placeholder="Type to search names...">
      </div>
    </fieldset>

    {{-- ===== ISAD(G) 3.7 Description control area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Description control area <small class="text-muted">(ISAD 3.7)</small></legend>

      <div class="mb-3">
        <label for="description_identifier" class="form-label">Description identifier</label>
        <input type="text" class="form-control" id="description_identifier" name="description_identifier"
               value="{{ old('description_identifier', $io->description_identifier) }}">
      </div>

      <div class="mb-3">
        <label for="institution_responsible_identifier" class="form-label">Institution identifier</label>
        <textarea class="form-control" id="institution_responsible_identifier" name="institution_responsible_identifier" rows="2">{{ old('institution_responsible_identifier', $io->institution_responsible_identifier) }}</textarea>
      </div>

      <div class="mb-3">
        <label for="rules" class="form-label">Rules or conventions</label>
        <textarea class="form-control" id="rules" name="rules" rows="3">{{ old('rules', $io->rules) }}</textarea>
      </div>

      <div class="mb-3">
        <label for="sources" class="form-label">Sources</label>
        <textarea class="form-control" id="sources" name="sources" rows="3">{{ old('sources', $io->sources) }}</textarea>
      </div>

      <div class="mb-3">
        <label for="revision_history" class="form-label">Dates of creation, revision and deletion</label>
        <textarea class="form-control" id="revision_history" name="revision_history" rows="3">{{ old('revision_history', $io->revision_history) }}</textarea>
      </div>

      <div class="row">
        <div class="col-md-6">
          <div class="mb-3">
            <label for="language_of_description" class="form-label">Language(s) of description</label>
            <input type="text" class="form-control" id="language_of_description" name="language_of_description" value="{{ old('language_of_description', $io->language_of_description ?? '') }}" placeholder="e.g. English, French">
          </div>
        </div>
        <div class="col-md-6">
          <div class="mb-3">
            <label for="script_of_description" class="form-label">Script(s) of description</label>
            <input type="text" class="form-control" id="script_of_description" name="script_of_description" value="{{ old('script_of_description', $io->script_of_description ?? '') }}" placeholder="e.g. Latin, Cyrillic">
          </div>
        </div>
      </div>
    </fieldset>

    {{-- ===== Administration area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Administration area</legend>

      <div class="mb-3">
        <label for="description_status_id" class="form-label">Description status</label>
        <select class="form-select" id="description_status_id" name="description_status_id">
          <option value="">-- Select --</option>
          @foreach($descriptionStatuses as $status)
            <option value="{{ $status->id }}" @selected(old('description_status_id', $io->description_status_id) == $status->id)>
              {{ $status->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="mb-3">
        <label for="description_detail_id" class="form-label">Level of detail</label>
        <select class="form-select" id="description_detail_id" name="description_detail_id">
          <option value="">-- Select --</option>
          @foreach($descriptionDetails as $detail)
            <option value="{{ $detail->id }}" @selected(old('description_detail_id', $io->description_detail_id) == $detail->id)>
              {{ $detail->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="mb-3">
        <label for="source_standard" class="form-label">Source standard</label>
        <input type="text" class="form-control" id="source_standard" name="source_standard"
               value="{{ old('source_standard', $io->source_standard) }}">
      </div>

      <div class="mb-3">
        <label for="display_standard_id" class="form-label">Display standard</label>
        <select class="form-select" id="display_standard_id" name="display_standard_id">
          <option value="">-- Select --</option>
          @foreach($displayStandards as $std)
            <option value="{{ $std->id }}" @selected(old('display_standard_id', $io->display_standard_id) == $std->id)>
              {{ $std->name }}
            </option>
          @endforeach
        </select>
      </div>
    </fieldset>

    {{-- ===== Form actions ===== --}}
    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        <li><a href="{{ route('informationobject.show', $io->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
        <li><a href="{{ route('informationobject.confirmDelete', $io->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
      </ul>
    </section>
  </form>

  {{-- ===== Digital object upload/manage ===== --}}
  <div class="accordion mb-4" id="digitalObjectAccordion">
    @include('io-manage::partials._upload-form')
  </div>
@endsection
