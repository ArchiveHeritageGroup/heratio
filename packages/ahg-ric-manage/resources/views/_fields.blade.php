{{--
  RiC-O field set - #1425 / dynamic-standard form. The field accordion ONLY
  (no <form>, no save button, no display-standard dropdown - the host create /
  edit form owns those). Rendered both by ric-manage::edit and, on standard
  change, swapped into the archival-description create / generic-edit form by
  InformationObjectController::standardFields().

  $io is nullable: null/empty on the create form, populated on edit.
--}}
@php
  $io = $io ?? null;
  $levels = $levels ?? collect();
  $repositories = $repositories ?? collect();
  $subjects = $subjects ?? collect();
  $places = $places ?? collect();
  $genres = $genres ?? collect();
  $nameAccessPoints = $nameAccessPoints ?? collect();
  $publicationStatusId = $publicationStatusId ?? null;
@endphp

<input type="hidden" name="_display_standard_code" value="ric">

<div class="accordion mb-3" id="ric-accordion">

  {{-- Identity --}}
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#ric-identity">{{ __('Identity') }}</button></h2>
    <div id="ric-identity" class="accordion-collapse collapse show">
      <div class="accordion-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Identifier') }} <span class="text-muted small">(rico:identifier)</span></label>
          <input type="text" name="identifier" class="form-control" value="{{ old('identifier', $io->identifier ?? '') }}">
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span> <span class="text-muted small">(rico:title)</span></label>
          <input type="text" name="title" class="form-control" required value="{{ old('title', $io->title ?? '') }}">
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Level / RecordSet type') }} <span class="text-muted small">(rico:hasRecordSetType)</span></label>
          <select name="level_of_description_id" class="form-select">
            <option value="">-</option>
            @foreach($levels as $lvl)
              <option value="{{ $lvl->id }}" @selected(old('level_of_description_id', $io->level_of_description_id ?? '') == $lvl->id)>{{ $lvl->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Repository / holder') }} <span class="text-muted small">(rico:hasOrHadHolder)</span></label>
          <select name="repository_id" class="form-select">
            <option value="">-</option>
            @foreach($repositories as $repo)
              <option value="{{ $repo->id }}" @selected(old('repository_id', $io->repository_id ?? '') == $repo->id)>{{ $repo->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </div>
  </div>

  {{-- Content and structure --}}
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ric-content">{{ __('Content and structure') }}</button></h2>
    <div id="ric-content" class="accordion-collapse collapse">
      <div class="accordion-body">
        @foreach([
          ['scope_and_content', 'Scope and content', 'rico:description'],
          ['arrangement', 'Arrangement', 'rico:structure'],
          ['extent_and_medium', 'Extent and medium', 'rico:hasExtent'],
          ['archival_history', 'Archival / custodial history', 'rico:history'],
          ['acquisition', 'Immediate source of acquisition', 'rico:hasSourceOfAcquisition'],
          ['appraisal', 'Appraisal, destruction and scheduling', 'rico:descriptiveNote'],
          ['accruals', 'Accruals', 'rico:descriptiveNote'],
        ] as [$field, $label, $ric])
          <div class="mb-3">
            <label class="form-label">{{ __($label) }} <span class="text-muted small">({{ $ric }})</span></label>
            <textarea name="{{ $field }}" class="form-control" rows="3">{{ old($field, $io->$field ?? '') }}</textarea>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Conditions of access and use --}}
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ric-conditions">{{ __('Conditions of access and use') }}</button></h2>
    <div id="ric-conditions" class="accordion-collapse collapse">
      <div class="accordion-body">
        @foreach([
          ['access_conditions', 'Conditions governing access', 'rico:conditionsOfAccess'],
          ['reproduction_conditions', 'Conditions governing reproduction', 'rico:conditionsOfUse'],
          ['physical_characteristics', 'Physical characteristics / technical requirements', 'rico:physicalCharacteristics'],
          ['finding_aids', 'Finding aids', 'rico:hasInstantiation'],
        ] as [$field, $label, $ric])
          <div class="mb-3">
            <label class="form-label">{{ __($label) }} <span class="text-muted small">({{ $ric }})</span></label>
            <textarea name="{{ $field }}" class="form-control" rows="2">{{ old($field, $io->$field ?? '') }}</textarea>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Related materials --}}
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ric-related">{{ __('Related materials') }}</button></h2>
    <div id="ric-related" class="accordion-collapse collapse">
      <div class="accordion-body">
        @foreach([
          ['location_of_originals', 'Existence and location of originals', 'rico:hasInstantiation'],
          ['location_of_copies', 'Existence and location of copies', 'rico:hasCopy'],
          ['related_units_of_description', 'Related units of description', 'rico:isRelatedTo'],
        ] as [$field, $label, $ric])
          <div class="mb-3">
            <label class="form-label">{{ __($label) }} <span class="text-muted small">({{ $ric }})</span></label>
            <textarea name="{{ $field }}" class="form-control" rows="2">{{ old($field, $io->$field ?? '') }}</textarea>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Access points (read-only summary; edit via the dedicated widgets) --}}
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ric-access">{{ __('Access points') }}</button></h2>
    <div id="ric-access" class="accordion-collapse collapse">
      <div class="accordion-body">
        @foreach([['Subjects', $subjects], ['Places', $places], ['Genres', $genres]] as [$label, $coll])
          <div class="mb-2">
            <strong>{{ __($label) }}:</strong>
            @forelse($coll as $t)<span class="badge bg-secondary">{{ $t->name }}</span>@empty<span class="text-muted small">{{ __('none') }}</span>@endforelse
          </div>
        @endforeach
        <div class="mb-2">
          <strong>{{ __('Name access points') }}:</strong>
          @forelse($nameAccessPoints as $n)<span class="badge bg-info text-dark">{{ $n->name }}</span>@empty<span class="text-muted small">{{ __('none') }}</span>@endforelse
        </div>
      </div>
    </div>
  </div>

  {{-- Description control (display-standard dropdown lives on the host form) --}}
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ric-control">{{ __('Description control') }}</button></h2>
    <div id="ric-control" class="accordion-collapse collapse">
      <div class="accordion-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Description identifier') }}</label>
          <input type="text" name="description_identifier" class="form-control" value="{{ old('description_identifier', $io->description_identifier ?? '') }}">
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Rules or conventions') }}</label>
          <textarea name="rules" class="form-control" rows="2">{{ old('rules', $io->rules ?? '') }}</textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Sources') }}</label>
          <textarea name="sources" class="form-control" rows="2">{{ old('sources', $io->sources ?? '') }}</textarea>
        </div>
        {{-- Publication status is owned by the host Administration area during a standard swap (#1425); hide it here so it is not duplicated. --}}
        @unless(request()->routeIs('informationobject.standard-fields'))
        <div class="mb-3">
          <label class="form-label">{{ __('Publication status') }}</label>
          <select name="publication_status_id" class="form-select">
            <option value="159" @selected($publicationStatusId == 159)>{{ __('Draft') }}</option>
            <option value="160" @selected($publicationStatusId == 160)>{{ __('Published') }}</option>
          </select>
        </div>
        @endunless
      </div>
    </div>
  </div>

</div>
