@extends('theme::layouts.1col')

@section('title', 'Add new archival description')
@section('body-class', 'create informationobject')

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">Add new archival description</h1>
  </div>

  @if($parentTitle)
    <div class="alert alert-info" role="alert">
      Adding child record under: <strong>{{ $parentTitle }}</strong>
    </div>
  @endif

  <form method="POST" action="{{ route('informationobject.store') }}" id="editForm" enctype="multipart/form-data">
    @csrf
    @if($parentId)
      <input type="hidden" name="parent_id" value="{{ $parentId }}">
    @endif

    <div class="accordion mb-3">

      {{-- ===== Identity area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="identity-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#identity-collapse" aria-expanded="false" aria-controls="identity-collapse">
            Identity area
          </button>
        </h2>
        <div id="identity-collapse" class="accordion-collapse collapse" aria-labelledby="identity-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="identifier" class="form-label">
                Identifier
                <span class="form-required" title="This is a mandatory element.">*</span>
              </label>
              <div class="input-group">
                <input type="text" class="form-control" id="identifier" name="identifier" value="{{ old('identifier') }}">
                <button type="button" class="btn btn-outline-secondary" id="generate-identifier" data-url="{{ url('/informationobject/generateIdentifier') }}">
                  <i class="fas fa-cog me-1" aria-hidden="true"></i>Generate
                </button>
              </div>
              <div class="form-text text-muted small">Provide a specific local reference code, control number, or other unique identifier. The country and repository code will be automatically added from the linked repository record to form a full reference code. (ISAD 3.1.1)</div>
            </div>

            <!-- Alternative identifiers -->
            <div class="mb-3">
              <label class="form-label">Alternative identifier(s)</label>
              <table class="table table-sm" id="altids-table">
                <thead>
                  <tr>
                    <th>Label</th>
                    <th>Value</th>
                    <th style="width:80px"></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="add-altid-row">Add alternative identifier</button>
            </div>

            <div class="mb-3">
              <label for="title" class="form-label">
                Title
                <span class="form-required" title="This is a mandatory element.">*</span>
              </label>
              <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required>
              @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">Provide either a formal title or a concise supplied title in accordance with the rules of multilevel description and national conventions. (ISAD 3.1.2)</div>
            </div>

            <!-- Events (dates) multi-row -->
            <div class="mb-3">
              <label class="form-label">Date(s)</label>
              <table class="table table-sm" id="events-table">
                <thead>
                  <tr>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Actor</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="event-row">
                    <td><select name="events[0][type_id]" class="form-select form-select-sm">
                      <option value="">-- Select --</option>
                      @foreach($eventTypes ?? [] as $et)
                        <option value="{{ $et->id }}">{{ $et->name }}</option>
                      @endforeach
                    </select></td>
                    <td><input type="text" name="events[0][date]" class="form-control form-control-sm" placeholder="e.g. ca. 1940-1960"></td>
                    <td><input type="text" name="events[0][start_date]" class="form-control form-control-sm" placeholder="YYYY-MM-DD"></td>
                    <td><input type="text" name="events[0][end_date]" class="form-control form-control-sm" placeholder="YYYY-MM-DD"></td>
                    <td><input type="text" name="events[0][actor]" class="form-control form-control-sm" placeholder="Actor name"></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-event-row"><i class="fas fa-times"></i></button></td>
                  </tr>
                </tbody>
              </table>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="add-event-row">Add date</button>
              <div class="form-text text-muted small">"Identify and record the date(s) of the unit of description. Identify the type of date given. Record as a single date or a range of dates as appropriate." (ISAD 3.1.3). The Date display field can be used to enter free-text date information, including typographical marks to express approximation, uncertainty, or qualification. Use the start and end fields to make the dates searchable. Do not use any qualifiers or typographical symbols to express uncertainty. Acceptable date formats: YYYYMMDD, YYYY-MM-DD, YYYY-MM, YYYY.</div>
            </div>

            <div class="mb-3">
              <label for="level_of_description_id" class="form-label">
                Level of description
                <span class="form-required" title="This is a mandatory element.">*</span>
              </label>
              <select class="form-select" id="level_of_description_id" name="level_of_description_id">
                <option value="">- Select -</option>
                @foreach($levels as $level)
                  <option value="{{ $level->id }}" @selected(old('level_of_description_id') == $level->id)>{{ $level->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Record the level of this unit of description. (ISAD 3.1.4)</div>
            </div>

            <!-- Add new child levels -->
            <div class="mb-3">
              <h3 class="fs-6 mb-2">Add new child levels</h3>
              <div class="table-responsive mb-2">
                <table class="table table-bordered mb-0" id="childlevels-table">
                  <thead class="table-light">
                    <tr>
                      <th id="child-identifier-head" class="w-20">Identifier</th>
                      <th id="child-level-head" class="w-20">Level</th>
                      <th id="child-title-head" class="w-40">Title</th>
                      <th id="child-date-head" class="w-20">Date</th>
                      <th><span class="visually-hidden">Delete</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><input type="text" name="childLevels[0][identifier]" class="form-control form-control-sm" aria-labelledby="child-identifier-head" aria-describedby="child-table-help"></td>
                      <td>
                        <select name="childLevels[0][levelOfDescription]" class="form-select form-select-sm" aria-labelledby="child-level-head" aria-describedby="child-table-help">
                          <option value=""></option>
                          @foreach($levels as $level)
                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                          @endforeach
                        </select>
                      </td>
                      <td><input type="text" name="childLevels[0][title]" class="form-control form-control-sm" aria-labelledby="child-title-head" aria-describedby="child-table-help"></td>
                      <td><input type="text" name="childLevels[0][date]" class="form-control form-control-sm" aria-labelledby="child-date-head" aria-describedby="child-table-help"></td>
                      <td>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-childlevel-row">
                          <i class="fas fa-times" aria-hidden="true"></i>
                          <span class="visually-hidden">Delete row</span>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="5">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="add-childlevel-row">
                          <i class="fas fa-plus me-1" aria-hidden="true"></i>Add new
                        </button>
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <div class="form-text mb-3" id="child-table-help">
                Identifier: Provide a specific local reference code, control number, or other unique identifier. Level of description: Record the level of this unit of description. Title: Provide either a formal title or a concise supplied title in accordance with the rules of multilevel description and national conventions.
              </div>
            </div>

            <div class="mb-3">
              <label for="extent_and_medium" class="form-label">
                Extent and medium
                <span class="form-required" title="This is a mandatory element.">*</span>
              </label>
              <textarea class="form-control" id="extent_and_medium" name="extent_and_medium" rows="3">{{ old('extent_and_medium') }}</textarea>
              <div class="form-text text-muted small">Record the extent of the unit of description by giving the number of physical or logical units in arabic numerals and the unit of measurement. Give the specific medium (media) of the unit of description. Separate multiple extents with a linebreak. (ISAD 3.1.5)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Context area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="context-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#context-collapse" aria-expanded="false" aria-controls="context-collapse">
            Context area
          </button>
        </h2>
        <div id="context-collapse" class="accordion-collapse collapse" aria-labelledby="context-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="creators" class="form-label">
                Name of creator(s)
                <span class="form-required" title="This archival description, or one of its higher levels, requires at least one creator.">*</span>
              </label>
              <input type="text" class="form-control" id="creators" name="creators" value="{{ old('creators') }}" placeholder="Type to search creators..." autocomplete="off">
              <div class="form-text text-muted small">Record the name of the organization(s) or the individual(s) responsible for the creation, accumulation and maintenance of the records in the unit of description. Search for an existing name in the authority records by typing the first few characters of the name. Alternatively, type a new name to create and link to a new authority record. (ISAD 3.2.1)</div>
            </div>

            <div class="mb-3">
              <label for="repository_id" class="form-label">Repository</label>
              <select class="form-select" id="repository_id" name="repository_id">
                <option value="">-- Select --</option>
                @foreach($repositories as $repo)
                  <option value="{{ $repo->id }}" @selected(old('repository_id') == $repo->id)>{{ $repo->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Record the name of the organization which has custody of the archival material. Search for an existing name in the archival institution records by typing the first few characters of the name. Alternatively, type a new name to create and link to a new archival institution record.</div>
            </div>

            <div class="mb-3">
              <label for="archival_history" class="form-label">Archival history</label>
              <textarea class="form-control" id="archival_history" name="archival_history" rows="3">{{ old('archival_history') }}</textarea>
              <div class="form-text text-muted small">Record the successive transfers of ownership, responsibility and/or custody of the unit of description and indicate those actions, such as history of the arrangement, production of contemporary finding aids, re-use of the records for other purposes or software migrations, that have contributed to its present structure and arrangement. Give the dates of these actions, insofar as they can be ascertained. If the archival history is unknown, record that information. (ISAD 3.2.3)</div>
            </div>

            <div class="mb-3">
              <label for="acquisition" class="form-label">Immediate source of acquisition or transfer</label>
              <textarea class="form-control" id="acquisition" name="acquisition" rows="3">{{ old('acquisition') }}</textarea>
              <div class="form-text text-muted small">Record the source from which the unit of description was acquired and the date and/or method of acquisition if any or all of this information is not confidential. If the source is unknown, record that information. Optionally, add accession numbers or codes. (ISAD 3.2.4)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Content and structure area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="content-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#content-collapse" aria-expanded="false" aria-controls="content-collapse">
            Content and structure area
          </button>
        </h2>
        <div id="content-collapse" class="accordion-collapse collapse" aria-labelledby="content-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="scope_and_content" class="form-label">Scope and content</label>
              <textarea class="form-control" id="scope_and_content" name="scope_and_content" rows="4">{{ old('scope_and_content') }}</textarea>
              <div class="form-text text-muted small">Give a summary of the scope (such as, time periods, geography) and content, (such as documentary forms, subject matter, administrative processes) of the unit of description, appropriate to the level of description. (ISAD 3.3.1)</div>
            </div>

            <div class="mb-3">
              <label for="appraisal" class="form-label">Appraisal, destruction and scheduling</label>
              <textarea class="form-control" id="appraisal" name="appraisal" rows="3">{{ old('appraisal') }}</textarea>
              <div class="form-text text-muted small">Record appraisal, destruction and scheduling actions taken on or planned for the unit of description, especially if they may affect the interpretation of the material. (ISAD 3.3.2)</div>
            </div>

            <div class="mb-3">
              <label for="accruals" class="form-label">Accruals</label>
              <textarea class="form-control" id="accruals" name="accruals" rows="3">{{ old('accruals') }}</textarea>
              <div class="form-text text-muted small">Indicate if accruals are expected. Where appropriate, give an estimate of their quantity and frequency. (ISAD 3.3.3)</div>
            </div>

            <div class="mb-3">
              <label for="arrangement" class="form-label">System of arrangement</label>
              <textarea class="form-control" id="arrangement" name="arrangement" rows="3">{{ old('arrangement') }}</textarea>
              <div class="form-text text-muted small">Specify the internal structure, order and/or the system of classification of the unit of description. Note how these have been treated by the archivist. For electronic records, record or reference information on system design. (ISAD 3.3.4)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Conditions of access and use area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="conditions-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#conditions-collapse" aria-expanded="false" aria-controls="conditions-collapse">
            Conditions of access and use area
          </button>
        </h2>
        <div id="conditions-collapse" class="accordion-collapse collapse" aria-labelledby="conditions-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="access_conditions" class="form-label">Conditions governing access</label>
              <textarea class="form-control" id="access_conditions" name="access_conditions" rows="3">{{ old('access_conditions') }}</textarea>
              <div class="form-text text-muted small">Specify the law or legal status, contract, regulation or policy that affects access to the unit of description. Indicate the extent of the period of closure and the date at which the material will open when appropriate. (ISAD 3.4.1)</div>
            </div>

            <div class="mb-3">
              <label for="reproduction_conditions" class="form-label">Conditions governing reproduction</label>
              <textarea class="form-control" id="reproduction_conditions" name="reproduction_conditions" rows="3">{{ old('reproduction_conditions') }}</textarea>
              <div class="form-text text-muted small">Give information about conditions, such as copyright, governing the reproduction of the unit of description after access has been provided. If the existence of such conditions is unknown, record this. If there are no conditions, no statement is necessary. (ISAD 3.4.2)</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Languages of the material</label>
              <input type="text" class="form-control" name="language_of_material" value="{{ old('language_of_material') }}" placeholder="e.g. English, Afrikaans">
              <div class="form-text text-muted small">Record the language(s) of the materials comprising the unit of description. (ISAD 3.4.3)</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Scripts of the material</label>
              <input type="text" class="form-control" name="script_of_material" value="{{ old('script_of_material') }}" placeholder="e.g. Latin">
              <div class="form-text text-muted small">Record the script(s) of the materials comprising the unit of description. (ISAD 3.4.3)</div>
            </div>

            <div class="mb-3">
              <label for="language_notes" class="form-label">Language and script notes</label>
              <textarea class="form-control" id="language_notes" name="language_notes" rows="2">{{ old('language_notes') }}</textarea>
              <div class="form-text text-muted small">Note any distinctive alphabets, scripts, symbol systems or abbreviations employed. (ISAD 3.4.3)</div>
            </div>

            <div class="mb-3">
              <label for="physical_characteristics" class="form-label">Physical characteristics and technical requirements</label>
              <textarea class="form-control" id="physical_characteristics" name="physical_characteristics" rows="3">{{ old('physical_characteristics') }}</textarea>
              <div class="form-text text-muted small">Indicate any important physical conditions, such as preservation requirements, that affect the use of the unit of description. Note any software and/or hardware required to access the unit of description.</div>
            </div>

            <div class="mb-3">
              <label for="finding_aids" class="form-label">Finding aids</label>
              <textarea class="form-control" id="finding_aids" name="finding_aids" rows="3">{{ old('finding_aids') }}</textarea>
              <div class="form-text text-muted small">Give information about any finding aids that the repository or records creator may have that provide information relating to the context and contents of the unit of description. If appropriate, include information on where to obtain a copy. (ISAD 3.4.5)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Allied materials area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="allied-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#allied-collapse" aria-expanded="false" aria-controls="allied-collapse">
            Allied materials area
          </button>
        </h2>
        <div id="allied-collapse" class="accordion-collapse collapse" aria-labelledby="allied-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="location_of_originals" class="form-label">Existence and location of originals</label>
              <textarea class="form-control" id="location_of_originals" name="location_of_originals" rows="3">{{ old('location_of_originals') }}</textarea>
              <div class="form-text text-muted small">If the original of the unit of description is available (either in the institution or elsewhere) record its location, together with any significant control numbers. If the originals no longer exist, or their location is unknown, give that information. (ISAD 3.5.1)</div>
            </div>

            <div class="mb-3">
              <label for="location_of_copies" class="form-label">Existence and location of copies</label>
              <textarea class="form-control" id="location_of_copies" name="location_of_copies" rows="3">{{ old('location_of_copies') }}</textarea>
              <div class="form-text text-muted small">If the copy of the unit of description is available (either in the institution or elsewhere) record its location, together with any significant control numbers. (ISAD 3.5.2)</div>
            </div>

            <div class="mb-3">
              <label for="related_units_of_description" class="form-label">Related units of description</label>
              <textarea class="form-control" id="related_units_of_description" name="related_units_of_description" rows="3">{{ old('related_units_of_description') }}</textarea>
              <div class="form-text text-muted small">Record information about units of description in the same repository or elsewhere that are related by provenance or other association(s). Use appropriate introductory wording and explain the nature of the relationship. If the related unit of description is a finding aid, use the finding aids element of description (3.4.5) to make the reference to it. (ISAD 3.5.3)</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Related descriptions</label>
              <input type="text" class="form-control" name="related_descriptions" value="{{ old('related_descriptions') }}" placeholder="Type to search related descriptions..." autocomplete="off">
              <div class="form-text text-muted small">To create a relationship between this description and another description held in the system, begin typing the name of the related description and select it from the autocomplete drop-down menu when it appears below. Multiple relationships can be created.</div>
            </div>

            <!-- Publication notes (multi-row) -->
            <div class="mb-3">
              <label class="form-label">Publication notes</label>
              <table class="table table-sm" id="pubnotes-table">
                <thead><tr><th>Content</th><th></th></tr></thead>
                <tbody>
                  <tr class="pubnote-row">
                    <td><textarea name="publication_notes[0][content]" class="form-control form-control-sm" rows="2">{{ old('publication_notes.0.content') }}</textarea></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-pubnote-row"><i class="fas fa-times"></i></button></td>
                  </tr>
                </tbody>
              </table>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="add-pubnote-row">Add publication note</button>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Notes area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="notes-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#notes-collapse" aria-expanded="false" aria-controls="notes-collapse">
            Notes area
          </button>
        </h2>
        <div id="notes-collapse" class="accordion-collapse collapse" aria-labelledby="notes-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <table class="table table-sm" id="notes-table">
                <thead><tr><th>Content</th><th>Type</th><th></th></tr></thead>
                <tbody>
                  <tr class="note-row">
                    <td><textarea name="notes[0][content]" class="form-control form-control-sm" rows="2">{{ old('notes.0.content') }}</textarea></td>
                    <td><select name="notes[0][type]" class="form-select form-select-sm">
                      <option value="125">General note</option>
                      <option value="174">Language note</option>
                    </select></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-note-row"><i class="fas fa-times"></i></button></td>
                  </tr>
                </tbody>
              </table>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="add-note-row">Add note</button>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Access points ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="access-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#access-collapse" aria-expanded="false" aria-controls="access-collapse">
            Access points
          </button>
        </h2>
        <div id="access-collapse" class="accordion-collapse collapse" aria-labelledby="access-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label">Subject access points</label>
              <input type="text" class="form-control" name="subject_access_points" value="{{ old('subject_access_points') }}" placeholder="Type to search subjects..." autocomplete="off">
            </div>

            <div class="mb-3">
              <label class="form-label">Place access points</label>
              <input type="text" class="form-control" name="place_access_points" value="{{ old('place_access_points') }}" placeholder="Type to search places..." autocomplete="off">
            </div>

            <div class="mb-3">
              <label class="form-label">Genre access points</label>
              <input type="text" class="form-control" name="genre_access_points" value="{{ old('genre_access_points') }}" placeholder="Type to search genres..." autocomplete="off">
            </div>

            <div class="mb-3">
              <label class="form-label">Name access points (subjects)</label>
              <input type="text" class="form-control" name="name_access_points" value="{{ old('name_access_points') }}" placeholder="Type to search names..." autocomplete="off">
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Description control area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="description-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#description-collapse" aria-expanded="false" aria-controls="description-collapse">
            Description control area
          </button>
        </h2>
        <div id="description-collapse" class="accordion-collapse collapse" aria-labelledby="description-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="description_identifier" class="form-label">Description identifier</label>
              <input type="text" class="form-control" id="description_identifier" name="description_identifier" value="{{ old('description_identifier') }}">
              <div class="form-text text-muted small">Record a unique description identifier in accordance with local and/or national conventions. If the description is to be used internationally, record the code of the country in which the description was created in accordance with the latest version of ISO 3166 - Codes for the representation of names of countries. Where the creator of the description is an international organisation, give the organisational identifier in place of the country code.</div>
            </div>

            <div class="mb-3">
              <label for="institution_responsible_identifier" class="form-label">Institution identifier</label>
              <textarea class="form-control" id="institution_responsible_identifier" name="institution_responsible_identifier" rows="2">{{ old('institution_responsible_identifier') }}</textarea>
              <div class="form-text text-muted small">Record the full authorised form of name(s) of the agency(ies) responsible for creating, modifying or disseminating the description or, alternatively, record a code for the agency in accordance with the national or international agency code standard.</div>
            </div>

            <div class="mb-3">
              <label for="rules" class="form-label">Rules or conventions</label>
              <textarea class="form-control" id="rules" name="rules" rows="3">{{ old('rules') }}</textarea>
              <div class="form-text text-muted small">Record the international, national and/or local rules or conventions followed in preparing the description. (ISAD 3.7.2)</div>
            </div>

            <div class="mb-3">
              <label for="description_status_id" class="form-label">Status</label>
              <select class="form-select" id="description_status_id" name="description_status_id">
                <option value="">-- Select --</option>
                @foreach($descriptionStatuses as $status)
                  <option value="{{ $status->id }}" @selected(old('description_status_id') == $status->id)>{{ $status->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Record the current status of the description, indicating whether it is a draft, finalized and/or revised or deleted.</div>
            </div>

            <div class="mb-3">
              <label for="description_detail_id" class="form-label">Level of detail</label>
              <select class="form-select" id="description_detail_id" name="description_detail_id">
                <option value="">-- Select --</option>
                @foreach($descriptionDetails as $detail)
                  <option value="{{ $detail->id }}" @selected(old('description_detail_id') == $detail->id)>{{ $detail->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Record whether the description consists of a minimal, partial or full level of detail in accordance with relevant international and/or national guidelines and/or rules.</div>
            </div>

            <div class="mb-3">
              <label for="revision_history" class="form-label">Dates of creation, revision and deletion</label>
              <textarea class="form-control" id="revision_history" name="revision_history" rows="3">{{ old('revision_history') }}</textarea>
              <div class="form-text text-muted small">Record the date(s) the entry was prepared and/or revised.</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Language(s)</label>
              <input type="text" class="form-control" name="language_of_description" value="{{ old('language_of_description') }}" placeholder="e.g. English">
              <div class="form-text text-muted small">Indicate the language(s) used to create the description of the archival material.</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Script(s)</label>
              <input type="text" class="form-control" name="script_of_description" value="{{ old('script_of_description') }}" placeholder="e.g. Latin">
              <div class="form-text text-muted small">Indicate the script(s) used to create the description of the archival material.</div>
            </div>

            <div class="mb-3">
              <label for="sources" class="form-label">Sources</label>
              <textarea class="form-control" id="sources" name="sources" rows="3">{{ old('sources') }}</textarea>
              <div class="form-text text-muted small">Record citations for any external sources used in the archival description (such as the Scope and Content, Archival History, or Notes fields).</div>
            </div>

            <!-- Archivist's notes (multi-row) -->
            <div class="mb-3">
              <label class="form-label">Archivist's notes</label>
              <table class="table table-sm" id="archnotes-table">
                <thead><tr><th>Content</th><th></th></tr></thead>
                <tbody>
                  <tr class="archnote-row">
                    <td><textarea name="archivists_notes[0][content]" class="form-control form-control-sm" rows="2">{{ old('archivists_notes.0.content') }}</textarea></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-archnote-row"><i class="fas fa-times"></i></button></td>
                  </tr>
                </tbody>
              </table>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="add-archnote-row">Add note</button>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Security Classification ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="security-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#security-collapse" aria-expanded="false" aria-controls="security-collapse">
            Security Classification
          </button>
        </h2>
        <div id="security-collapse" class="accordion-collapse collapse" aria-labelledby="security-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="security_classification_id" class="form-label">Security Classification</label>
              <select class="form-select" id="security_classification_id" name="security_classification_id">
                <option value="">Public (No Classification)</option>
                @foreach($securityClassifications as $classification)
                  <option value="{{ $classification->id }}" data-level="{{ $classification->level }}" @selected(old('security_classification_id') == $classification->id)>{{ $classification->name }}</option>
                @endforeach
              </select>
              <small class="text-muted">Security classification watermarks override all other watermarks.</small>
            </div>

            <div id="classification-details" style="display: none;">
              <div class="mb-3">
                <label for="security_reason" class="form-label">Classification Reason</label>
                <textarea class="form-control" id="security_reason" name="security_reason" rows="2">{{ old('security_reason') }}</textarea>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="security_review_date" class="form-label">Review Date</label>
                  <input type="date" class="form-control" id="security_review_date" name="security_review_date" value="{{ old('security_review_date') }}">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="security_declassify_date" class="form-label">Declassify Date</label>
                  <input type="date" class="form-control" id="security_declassify_date" name="security_declassify_date" value="{{ old('security_declassify_date') }}">
                </div>
              </div>

              <div class="mb-3">
                <label for="security_handling_instructions" class="form-label">Handling Instructions</label>
                <textarea class="form-control" id="security_handling_instructions" name="security_handling_instructions" rows="2">{{ old('security_handling_instructions') }}</textarea>
              </div>

              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="security_inherit_to_children" name="security_inherit_to_children" value="1" checked>
                <label class="form-check-label" for="security_inherit_to_children">
                  Apply to child records
                </label>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Watermark Settings ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="watermark-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#watermark-collapse" aria-expanded="false" aria-controls="watermark-collapse">
            Watermark Settings
          </button>
        </h2>
        <div id="watermark-collapse" class="accordion-collapse collapse" aria-labelledby="watermark-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="watermark_enabled" name="watermark_enabled" value="1" checked>
                <label class="form-check-label" for="watermark_enabled">
                  Enable watermark for this object
                </label>
              </div>
            </div>

            <div id="watermark-options">

              <div class="mb-3">
                <label for="watermark_type_id" class="form-label">System Watermark</label>
                <select class="form-select" id="watermark_type_id" name="watermark_type_id">
                  <option value="">Use default</option>
                  @foreach($watermarkTypes as $wtype)
                    <option value="{{ $wtype->id }}" @selected(old('watermark_type_id') == $wtype->id)>{{ $wtype->name }}</option>
                  @endforeach
                </select>
              </div>

              <!-- Upload New Custom Watermark -->
              <div class="card bg-light mb-3">
                <div class="card-body">
                  <h6 class="card-title">Upload NEW Custom Watermark</h6>
                  <small class="text-muted d-block mb-2">Leave empty to keep existing selection above</small>

                  <div class="mb-2">
                    <label for="new_watermark_name" class="form-label">Watermark Name</label>
                    <input type="text" class="form-control form-control-sm" id="new_watermark_name" name="new_watermark_name" placeholder="e.g., Company Logo">
                  </div>

                  <div class="mb-2">
                    <label for="new_watermark_file" class="form-label">Watermark Image</label>
                    <input type="file" class="form-control form-control-sm" id="new_watermark_file" name="new_watermark_file" accept="image/png,image/gif">
                    <small class="text-muted">PNG or GIF with transparency recommended</small>
                  </div>

                  <div class="row">
                    <div class="col-md-6 mb-2">
                      <label for="new_watermark_position" class="form-label">Position</label>
                      <select class="form-select form-select-sm" id="new_watermark_position" name="new_watermark_position">
                        <option value="center" selected>Center</option>
                        <option value="repeat">Repeat (tile)</option>
                        <option value="bottom right">Bottom Right</option>
                        <option value="bottom left">Bottom Left</option>
                        <option value="top right">Top Right</option>
                        <option value="top left">Top Left</option>
                      </select>
                    </div>
                    <div class="col-md-6 mb-2">
                      <label for="new_watermark_opacity" class="form-label">Opacity</label>
                      <input type="range" class="form-range" id="new_watermark_opacity" name="new_watermark_opacity" min="10" max="80" value="40">
                      <small class="text-muted"><span id="opacity-value">40</span>%</small>
                    </div>
                  </div>

                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="new_watermark_global" name="new_watermark_global" value="1">
                    <label class="form-check-label" for="new_watermark_global">
                      Make available globally (for all records)
                    </label>
                  </div>
                </div>
              </div>

              <div class="alert alert-info py-2 mb-0">
                <small><i class="fas fa-info-circle me-1"></i>
                Security classification watermarks have the highest priority and will override custom watermarks.
                </small>
              </div>

            </div>
          </div>
        </div>
      </div>

      {{-- ===== Administration area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="admin-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#admin-collapse" aria-expanded="false" aria-controls="admin-collapse">
            Administration area
          </button>
        </h2>
        <div id="admin-collapse" class="accordion-collapse collapse" aria-labelledby="admin-heading">
          <div class="accordion-body">
            <div class="row">

              <div class="col-md-6">
                <div class="mb-3">
                  <label for="publication_status_id" class="form-label">Publication status</label>
                  <select class="form-select" id="publication_status_id" name="publication_status_id">
                    <option value="159">Draft</option>
                    <option value="160">Published</option>
                  </select>
                </div>

                <div class="mb-3">
                  <h3 class="fs-6 mb-2">Source language</h3>
                  <span class="text-muted">{{ app()->getLocale() == 'en' ? 'English' : app()->getLocale() }}</span>
                </div>
              </div>

              <div class="col-md-6">
                <div class="mb-3">
                  <label for="display_standard_id" class="form-label">Display standard</label>
                  <select class="form-select" id="display_standard_id" name="display_standard_id">
                    <option value="">-- Select --</option>
                    @foreach($displayStandards as $std)
                      <option value="{{ $std->id }}" @selected(old('display_standard_id') == $std->id)>{{ $std->name }}</option>
                    @endforeach
                  </select>
                </div>

                <div class="mb-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="display_standard_update_descendants" name="display_standard_update_descendants" value="1">
                    <label class="form-check-label" for="display_standard_update_descendants">
                      Make this selection the new default for existing children
                    </label>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>

    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a class="btn atom-btn-outline-light" role="button" href="{{ route('informationobject.browse') }}" title="Cancel">Cancel</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
    </ul>
  </form>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Events (dates) multi-row
  var eventIdx = 1;
  var eventTypeOptions = document.querySelector('#events-table select')?.innerHTML || '';
  document.getElementById('add-event-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.className = 'event-row';
    tr.innerHTML = '<td><select name="events[' + eventIdx + '][type_id]" class="form-select form-select-sm">' + eventTypeOptions + '</select></td>' +
      '<td><input type="text" name="events[' + eventIdx + '][date]" class="form-control form-control-sm" placeholder="e.g. ca. 1940-1960"></td>' +
      '<td><input type="text" name="events[' + eventIdx + '][start_date]" class="form-control form-control-sm" placeholder="YYYY-MM-DD"></td>' +
      '<td><input type="text" name="events[' + eventIdx + '][end_date]" class="form-control form-control-sm" placeholder="YYYY-MM-DD"></td>' +
      '<td><input type="text" name="events[' + eventIdx + '][actor]" class="form-control form-control-sm" placeholder="Actor name"></td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger remove-event-row"><i class="fas fa-times"></i></button></td>';
    document.querySelector('#events-table tbody').appendChild(tr);
    eventIdx++;
  });

  // Alternative identifiers multi-row
  var altIdx = 0;
  document.getElementById('add-altid-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.className = 'altid-row';
    tr.innerHTML = '<td><input type="text" name="alt_ids[' + altIdx + '][label]" class="form-control form-control-sm" placeholder="Label"></td>' +
      '<td><input type="text" name="alt_ids[' + altIdx + '][value]" class="form-control form-control-sm" placeholder="Value"></td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger remove-altid-row"><i class="fas fa-times"></i></button></td>';
    document.querySelector('#altids-table tbody').appendChild(tr);
    altIdx++;
  });

  // Notes multi-row
  var noteIdx = 1;
  var noteTypeOptions = document.querySelector('#notes-table select')?.innerHTML || '';
  document.getElementById('add-note-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.className = 'note-row';
    tr.innerHTML = '<td><textarea name="notes[' + noteIdx + '][content]" class="form-control form-control-sm" rows="2"></textarea></td>' +
      '<td><select name="notes[' + noteIdx + '][type]" class="form-select form-select-sm">' + noteTypeOptions + '</select></td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger remove-note-row"><i class="fas fa-times"></i></button></td>';
    document.querySelector('#notes-table tbody').appendChild(tr);
    noteIdx++;
  });

  // Child levels multi-row
  var childIdx = 1;
  var childLevelOptions = document.querySelector('#childlevels-table select')?.innerHTML || '';
  document.getElementById('add-childlevel-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="text" name="childLevels[' + childIdx + '][identifier]" class="form-control form-control-sm"></td>' +
      '<td><select name="childLevels[' + childIdx + '][levelOfDescription]" class="form-select form-select-sm">' + childLevelOptions + '</select></td>' +
      '<td><input type="text" name="childLevels[' + childIdx + '][title]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="childLevels[' + childIdx + '][date]" class="form-control form-control-sm"></td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger remove-childlevel-row"><i class="fas fa-times" aria-hidden="true"></i></button></td>';
    document.querySelector('#childlevels-table tbody').appendChild(tr);
    childIdx++;
  });

  // Publication notes multi-row
  var pubIdx = 1;
  document.getElementById('add-pubnote-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.className = 'pubnote-row';
    tr.innerHTML = '<td><textarea name="publication_notes[' + pubIdx + '][content]" class="form-control form-control-sm" rows="2"></textarea></td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger remove-pubnote-row"><i class="fas fa-times"></i></button></td>';
    document.querySelector('#pubnotes-table tbody').appendChild(tr);
    pubIdx++;
  });

  // Archivist's notes multi-row
  var archIdx = 1;
  document.getElementById('add-archnote-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.className = 'archnote-row';
    tr.innerHTML = '<td><textarea name="archivists_notes[' + archIdx + '][content]" class="form-control form-control-sm" rows="2"></textarea></td>' +
      '<td><button type="button" class="btn btn-sm btn-outline-danger remove-archnote-row"><i class="fas fa-times"></i></button></td>';
    document.querySelector('#archnotes-table tbody').appendChild(tr);
    archIdx++;
  });

  // Remove row handler for all tables
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.remove-event-row, .remove-altid-row, .remove-note-row, .remove-childlevel-row, .remove-pubnote-row, .remove-archnote-row');
    if (btn) {
      var table = btn.closest('table');
      if (table.querySelectorAll('tbody tr').length > 1) {
        btn.closest('tr').remove();
      }
    }
  });

  // Security classification toggle
  var classSelect = document.getElementById('security_classification_id');
  var classDetails = document.getElementById('classification-details');
  if (classSelect && classDetails) {
    classSelect.addEventListener('change', function() {
      classDetails.style.display = this.value ? 'block' : 'none';
    });
  }

  // Watermark enabled toggle
  var wmEnabled = document.getElementById('watermark_enabled');
  var wmOptions = document.getElementById('watermark-options');
  if (wmEnabled && wmOptions) {
    wmEnabled.addEventListener('change', function() {
      wmOptions.style.display = this.checked ? 'block' : 'none';
    });
  }

  // Opacity slider display
  var opacitySlider = document.getElementById('new_watermark_opacity');
  var opacityValue = document.getElementById('opacity-value');
  if (opacitySlider && opacityValue) {
    opacitySlider.addEventListener('input', function() {
      opacityValue.textContent = this.value;
    });
  }
});
</script>
@endpush
@endsection
