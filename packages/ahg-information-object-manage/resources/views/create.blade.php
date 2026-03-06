@extends('theme::layouts.1col')

@section('title', 'Add new archival description')
@section('body-class', 'create informationobject')

@section('content')
  <h1>Add new archival description</h1>

  @if($parentTitle)
    <div class="alert alert-info">
      <i class="fas fa-sitemap me-1"></i>
      Adding child record under: <strong>{{ $parentTitle }}</strong>
    </div>
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

  <form method="POST" action="{{ route('informationobject.store') }}">
    @csrf

    @if($parentId)
      <input type="hidden" name="parent_id" value="{{ $parentId }}">
    @endif

    {{-- ===== ISAD(G) 3.1 Identity area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Identity area <small class="text-muted">(ISAD 3.1)</small></legend>

      <div class="mb-3">
        <label for="identifier" class="form-label">Identifier</label>
        <input type="text" class="form-control" id="identifier" name="identifier"
               value="{{ old('identifier') }}">
      </div>

      <div class="mb-3">
        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title"
               value="{{ old('title') }}" required>
        @error('title')
          <div class="invalid-feedback">{{ $message }}</div>
        @enderror
      </div>

      <div class="mb-3">
        <label for="level_of_description_id" class="form-label">Level of description</label>
        <select class="form-select" id="level_of_description_id" name="level_of_description_id">
          <option value="">-- Select --</option>
          @foreach($levels as $level)
            <option value="{{ $level->id }}" @selected(old('level_of_description_id') == $level->id)>
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
            <option value="{{ $repo->id }}" @selected(old('repository_id') == $repo->id)>
              {{ $repo->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="mb-3">
        <label for="alternate_title" class="form-label">Alternate title</label>
        <input type="text" class="form-control" id="alternate_title" name="alternate_title"
               value="{{ old('alternate_title') }}">
      </div>

      <div class="mb-3">
        <label for="edition" class="form-label">Edition</label>
        <input type="text" class="form-control" id="edition" name="edition"
               value="{{ old('edition') }}">
      </div>

      <div class="mb-3">
        <label for="extent_and_medium" class="form-label">Extent and medium</label>
        <textarea class="form-control" id="extent_and_medium" name="extent_and_medium" rows="3">{{ old('extent_and_medium') }}</textarea>
      </div>
    </fieldset>

    {{-- ===== ISAD(G) 3.2 Context area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Context area <small class="text-muted">(ISAD 3.2)</small></legend>

      <div class="mb-3">
        <label for="archival_history" class="form-label">Archival history</label>
        <textarea class="form-control" id="archival_history" name="archival_history" rows="3">{{ old('archival_history') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="acquisition" class="form-label">Immediate source of acquisition or transfer</label>
        <textarea class="form-control" id="acquisition" name="acquisition" rows="3">{{ old('acquisition') }}</textarea>
      </div>
    </fieldset>

    {{-- ===== ISAD(G) 3.3 Content and structure area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Content and structure area <small class="text-muted">(ISAD 3.3)</small></legend>

      <div class="mb-3">
        <label for="scope_and_content" class="form-label">Scope and content</label>
        <textarea class="form-control" id="scope_and_content" name="scope_and_content" rows="4">{{ old('scope_and_content') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="appraisal" class="form-label">Appraisal, destruction and scheduling information</label>
        <textarea class="form-control" id="appraisal" name="appraisal" rows="3">{{ old('appraisal') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="accruals" class="form-label">Accruals</label>
        <textarea class="form-control" id="accruals" name="accruals" rows="3">{{ old('accruals') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="arrangement" class="form-label">System of arrangement</label>
        <textarea class="form-control" id="arrangement" name="arrangement" rows="3">{{ old('arrangement') }}</textarea>
      </div>
    </fieldset>

    {{-- ===== ISAD(G) 3.4 Conditions of access and use area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Conditions of access and use area <small class="text-muted">(ISAD 3.4)</small></legend>

      <div class="mb-3">
        <label for="access_conditions" class="form-label">Conditions governing access</label>
        <textarea class="form-control" id="access_conditions" name="access_conditions" rows="3">{{ old('access_conditions') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="reproduction_conditions" class="form-label">Conditions governing reproduction</label>
        <textarea class="form-control" id="reproduction_conditions" name="reproduction_conditions" rows="3">{{ old('reproduction_conditions') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="physical_characteristics" class="form-label">Physical characteristics and technical requirements</label>
        <textarea class="form-control" id="physical_characteristics" name="physical_characteristics" rows="3">{{ old('physical_characteristics') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="finding_aids" class="form-label">Finding aids</label>
        <textarea class="form-control" id="finding_aids" name="finding_aids" rows="3">{{ old('finding_aids') }}</textarea>
      </div>
    </fieldset>

    {{-- ===== ISAD(G) 3.5 Allied materials area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Allied materials area <small class="text-muted">(ISAD 3.5)</small></legend>

      <div class="mb-3">
        <label for="location_of_originals" class="form-label">Existence and location of originals</label>
        <textarea class="form-control" id="location_of_originals" name="location_of_originals" rows="3">{{ old('location_of_originals') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="location_of_copies" class="form-label">Existence and location of copies</label>
        <textarea class="form-control" id="location_of_copies" name="location_of_copies" rows="3">{{ old('location_of_copies') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="related_units_of_description" class="form-label">Related units of description</label>
        <textarea class="form-control" id="related_units_of_description" name="related_units_of_description" rows="3">{{ old('related_units_of_description') }}</textarea>
      </div>
    </fieldset>

    {{-- ===== ISAD(G) 3.7 Description control area ===== --}}
    <fieldset class="mb-4">
      <legend class="fs-5 border-bottom pb-2">Description control area <small class="text-muted">(ISAD 3.7)</small></legend>

      <div class="mb-3">
        <label for="description_identifier" class="form-label">Description identifier</label>
        <input type="text" class="form-control" id="description_identifier" name="description_identifier"
               value="{{ old('description_identifier') }}">
      </div>

      <div class="mb-3">
        <label for="institution_responsible_identifier" class="form-label">Institution identifier</label>
        <textarea class="form-control" id="institution_responsible_identifier" name="institution_responsible_identifier" rows="2">{{ old('institution_responsible_identifier') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="rules" class="form-label">Rules or conventions</label>
        <textarea class="form-control" id="rules" name="rules" rows="3">{{ old('rules') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="sources" class="form-label">Sources</label>
        <textarea class="form-control" id="sources" name="sources" rows="3">{{ old('sources') }}</textarea>
      </div>

      <div class="mb-3">
        <label for="revision_history" class="form-label">Dates of creation, revision and deletion</label>
        <textarea class="form-control" id="revision_history" name="revision_history" rows="3">{{ old('revision_history') }}</textarea>
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
            <option value="{{ $status->id }}" @selected(old('description_status_id') == $status->id)>
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
            <option value="{{ $detail->id }}" @selected(old('description_detail_id') == $detail->id)>
              {{ $detail->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="mb-3">
        <label for="source_standard" class="form-label">Source standard</label>
        <input type="text" class="form-control" id="source_standard" name="source_standard"
               value="{{ old('source_standard') }}">
      </div>

      <div class="mb-3">
        <label for="display_standard_id" class="form-label">Display standard</label>
        <select class="form-select" id="display_standard_id" name="display_standard_id">
          <option value="">-- Select --</option>
          @foreach($displayStandards as $std)
            <option value="{{ $std->id }}" @selected(old('display_standard_id') == $std->id)>
              {{ $std->name }}
            </option>
          @endforeach
        </select>
      </div>
    </fieldset>

    {{-- ===== Form actions ===== --}}
    <div class="d-flex gap-2 mb-4">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> Create
      </button>
      <a href="{{ route('informationobject.browse') }}" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
@endsection
