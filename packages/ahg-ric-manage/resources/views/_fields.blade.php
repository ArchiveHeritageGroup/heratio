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
  // #1425 tail: manual rico:Instantiation rows for this record (edit only;
  // empty on create). Auto-derived (source='auto') rows are NOT edited here.
  $existingInstantiations = $existingInstantiations ?? collect();
  $carrierOptions = ['', 'paper', 'digital', 'microfilm', 'photograph', 'audio', 'video', 'born-digital', 'parchment', 'glass-plate'];
  $extentUnits = ['', 'items', 'boxes', 'folders', 'pages', 'linear metres', 'megabytes', 'gigabytes', 'reels', 'files'];
  // #1425 tail: rico:Event editor. Creation (type 111) is owned by the
  // creators/date widgets, so it is excluded from the general event editor.
  $eventTypes = $eventTypes ?? collect();
  $eventTypeOptions = collect($eventTypes)->reject(fn ($t) => (int) $t->id === 111)->values();
  $existingEvents = $existingEvents ?? collect();
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

  {{-- Instantiations (#1425 tail): repeatable rico:Instantiation editor. Each
       row is a manifestation of the record - the original holding, a microfilm
       or digital copy, etc. Persisted as first-class RiC entities (source =
       'manual') by RicManageController::persist, alongside any auto-derived
       digital-object instantiations. The <template> + [data-repeat-*] hooks are
       driven by document-level delegation in the host form (survives the
       standard-swap, which re-inserts this markup via innerHTML). --}}
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ric-instantiations">{{ __('Instantiations') }} <span class="text-muted small ms-1">(rico:hasInstantiation)</span></button></h2>
    <div id="ric-instantiations" class="accordion-collapse collapse">
      <div class="accordion-body">
        <p class="text-muted small">{{ __('Physical or digital manifestations of this record (the original holding, copies, surrogates). Auto-generated instantiations from uploaded digital objects are managed separately and not shown here.') }}</p>

        <div id="ric-instantiations-list">
          @foreach($existingInstantiations as $idx => $inst)
            <div class="ric-instantiation-row border rounded p-2 mb-2" data-repeat-row>
              <input type="hidden" name="instantiations[{{ $idx }}][id]" value="{{ $inst->id }}">
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label small mb-0">{{ __('Label / title') }}</label>
                  <input type="text" name="instantiations[{{ $idx }}][title]" class="form-control form-control-sm" value="{{ $inst->title ?? '' }}" placeholder="{{ __('e.g. Original held at the National Archives') }}">
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-0">{{ __('Carrier type') }}</label>
                  <select name="instantiations[{{ $idx }}][carrier_type]" class="form-select form-select-sm">
                    @foreach($carrierOptions as $c)<option value="{{ $c }}" @selected(($inst->carrier_type ?? '') === $c)>{{ $c === '' ? '-' : $c }}</option>@endforeach
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-0">{{ __('MIME / medium') }}</label>
                  <input type="text" name="instantiations[{{ $idx }}][mime_type]" class="form-control form-control-sm" value="{{ $inst->mime_type ?? '' }}">
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-0">{{ __('Extent value') }}</label>
                  <input type="text" name="instantiations[{{ $idx }}][extent_value]" class="form-control form-control-sm" value="{{ $inst->extent_value ?? '' }}">
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-0">{{ __('Extent unit') }}</label>
                  <select name="instantiations[{{ $idx }}][extent_unit]" class="form-select form-select-sm">
                    @foreach($extentUnits as $u)<option value="{{ $u }}" @selected(($inst->extent_unit ?? '') === $u)>{{ $u === '' ? '-' : $u }}</option>@endforeach
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label small mb-0">{{ __('Location / note') }}</label>
                  <input type="text" name="instantiations[{{ $idx }}][description]" class="form-control form-control-sm" value="{{ $inst->description ?? '' }}">
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-danger mt-2" data-repeat-remove>{{ __('Remove') }}</button>
            </div>
          @endforeach
        </div>

        <button type="button" class="btn btn-sm btn-outline-secondary" data-repeat-add="ric-instantiation-template" data-repeat-target="ric-instantiations-list" data-repeat-index="{{ $existingInstantiations->count() }}">{{ __('Add instantiation') }}</button>

        <template id="ric-instantiation-template">
          <div class="ric-instantiation-row border rounded p-2 mb-2" data-repeat-row>
            <input type="hidden" name="instantiations[__IDX__][id]" value="">
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label small mb-0">{{ __('Label / title') }}</label>
                <input type="text" name="instantiations[__IDX__][title]" class="form-control form-control-sm" placeholder="{{ __('e.g. Original held at the National Archives') }}">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('Carrier type') }}</label>
                <select name="instantiations[__IDX__][carrier_type]" class="form-select form-select-sm">
                  @foreach($carrierOptions as $c)<option value="{{ $c }}">{{ $c === '' ? '-' : $c }}</option>@endforeach
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('MIME / medium') }}</label>
                <input type="text" name="instantiations[__IDX__][mime_type]" class="form-control form-control-sm">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('Extent value') }}</label>
                <input type="text" name="instantiations[__IDX__][extent_value]" class="form-control form-control-sm">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('Extent unit') }}</label>
                <select name="instantiations[__IDX__][extent_unit]" class="form-select form-select-sm">
                  @foreach($extentUnits as $u)<option value="{{ $u }}">{{ $u === '' ? '-' : $u }}</option>@endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-0">{{ __('Location / note') }}</label>
                <input type="text" name="instantiations[__IDX__][description]" class="form-control form-control-sm">
              </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger mt-2" data-repeat-remove>{{ __('Remove') }}</button>
          </div>
        </template>
      </div>
    </div>
  </div>

  {{-- Events (#1425 tail): repeatable rico:Event editor - custody, publication,
       accumulation, reproduction, etc. (Creation is captured by the creators /
       date widgets and is excluded here). Persisted as AtoM event rows by
       RicManageController::persist; the RiC serializer already emits them. --}}
  <div class="accordion-item">
    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ric-events">{{ __('Events') }} <span class="text-muted small ms-1">(rico:Event)</span></button></h2>
    <div id="ric-events" class="accordion-collapse collapse">
      <div class="accordion-body">
        <p class="text-muted small">{{ __('Datable happenings in the record\'s life - custody transfer, publication, accumulation, reproduction. Creation dates and creators are captured in the Identity / creators area.') }}</p>

        <div id="ric-events-list">
          @foreach($existingEvents as $eidx => $ev)
            <div class="ric-event-row border rounded p-2 mb-2" data-repeat-row>
              <input type="hidden" name="events[{{ $eidx }}][id]" value="{{ $ev->id }}">
              <div class="row g-2">
                <div class="col-md-4">
                  <label class="form-label small mb-0">{{ __('Event type') }}</label>
                  <select name="events[{{ $eidx }}][type_id]" class="form-select form-select-sm">
                    <option value="">-</option>
                    @foreach($eventTypeOptions as $et)<option value="{{ $et->id }}" @selected((int)($ev->type_id ?? 0) === (int)$et->id)>{{ $et->name }}</option>@endforeach
                  </select>
                </div>
                <div class="col-md-5">
                  <label class="form-label small mb-0">{{ __('Date (display)') }}</label>
                  <input type="text" name="events[{{ $eidx }}][date_display]" class="form-control form-control-sm" value="{{ $ev->date_display ?? '' }}" placeholder="{{ __('e.g. circa 1994') }}">
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-0">{{ __('Agent / actor') }}</label>
                  <input type="text" name="events[{{ $eidx }}][agent]" class="form-control form-control-sm" value="{{ $ev->agent ?? '' }}">
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-0">{{ __('Start (ISO)') }}</label>
                  <input type="text" name="events[{{ $eidx }}][start_date]" class="form-control form-control-sm" value="{{ $ev->start_date ?? '' }}" placeholder="YYYY-MM-DD">
                </div>
                <div class="col-md-3">
                  <label class="form-label small mb-0">{{ __('End (ISO)') }}</label>
                  <input type="text" name="events[{{ $eidx }}][end_date]" class="form-control form-control-sm" value="{{ $ev->end_date ?? '' }}" placeholder="YYYY-MM-DD">
                </div>
                <div class="col-md-6">
                  <label class="form-label small mb-0">{{ __('Note') }}</label>
                  <input type="text" name="events[{{ $eidx }}][description]" class="form-control form-control-sm" value="{{ $ev->description ?? '' }}">
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-danger mt-2" data-repeat-remove>{{ __('Remove') }}</button>
            </div>
          @endforeach
        </div>

        <button type="button" class="btn btn-sm btn-outline-secondary" data-repeat-add="ric-event-template" data-repeat-target="ric-events-list" data-repeat-index="{{ $existingEvents->count() }}">{{ __('Add event') }}</button>

        <template id="ric-event-template">
          <div class="ric-event-row border rounded p-2 mb-2" data-repeat-row>
            <input type="hidden" name="events[__IDX__][id]" value="">
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label small mb-0">{{ __('Event type') }}</label>
                <select name="events[__IDX__][type_id]" class="form-select form-select-sm">
                  <option value="">-</option>
                  @foreach($eventTypeOptions as $et)<option value="{{ $et->id }}">{{ $et->name }}</option>@endforeach
                </select>
              </div>
              <div class="col-md-5">
                <label class="form-label small mb-0">{{ __('Date (display)') }}</label>
                <input type="text" name="events[__IDX__][date_display]" class="form-control form-control-sm" placeholder="{{ __('e.g. circa 1994') }}">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('Agent / actor') }}</label>
                <input type="text" name="events[__IDX__][agent]" class="form-control form-control-sm">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('Start (ISO)') }}</label>
                <input type="text" name="events[__IDX__][start_date]" class="form-control form-control-sm" placeholder="YYYY-MM-DD">
              </div>
              <div class="col-md-3">
                <label class="form-label small mb-0">{{ __('End (ISO)') }}</label>
                <input type="text" name="events[__IDX__][end_date]" class="form-control form-control-sm" placeholder="YYYY-MM-DD">
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-0">{{ __('Note') }}</label>
                <input type="text" name="events[__IDX__][description]" class="form-control form-control-sm">
              </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger mt-2" data-repeat-remove>{{ __('Remove') }}</button>
          </div>
        </template>
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
