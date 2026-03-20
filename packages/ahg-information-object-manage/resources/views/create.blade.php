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

  <form method="POST" action="{{ route('informationobject.store') }}" id="editForm">
    @csrf
    @if($parentId)
      <input type="hidden" name="parent_id" value="{{ $parentId }}">
    @endif

    <div class="accordion mb-3">

      {{-- ===== Identity area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="identity-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#identity-collapse" aria-expanded="true" aria-controls="identity-collapse">
            Identity area
          </button>
        </h2>
        <div id="identity-collapse" class="accordion-collapse collapse show" aria-labelledby="identity-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="identifier" class="form-label">Identifier</label>
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
                <span class="form-required" title="This is a mandatory field.">*</span>
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
                <span class="form-required" title="This is a mandatory field.">*</span>
              </label>
              <select class="form-select" id="level_of_description_id" name="level_of_description_id">
                <option value="">- Select -</option>
                @foreach($levels as $level)
                  <option value="{{ $level->id }}" @selected(old('level_of_description_id') == $level->id)>{{ $level->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Record the level of this unit of description. (ISAD 3.1.4)</div>
            </div>

            <div class="mb-3">
              <label for="extent_and_medium" class="form-label">
                Extent and medium
                <span class="form-required" title="This is a mandatory field.">*</span>
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
              <label for="appraisal" class="form-label">Appraisal, destruction and scheduling information</label>
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
              <label class="form-label">Language(s) of material</label>
              <input type="text" class="form-control" name="language_of_material" value="{{ old('language_of_material') }}" placeholder="e.g. English, Afrikaans">
              <div class="form-text text-muted small">Record the language(s) of the materials comprising the unit of description. (ISAD 3.4.3)</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Script(s) of material</label>
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
              <label class="form-label">Publication notes</label>
              <textarea class="form-control" name="publication_notes" rows="3">{{ old('publication_notes') }}</textarea>
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
                      <option value="general">General note</option>
                      <option value="conservation">Conservation</option>
                      <option value="physical_description">Physical description</option>
                      <option value="accompanying_material">Accompanying material</option>
                      <option value="alpha_numeric_designation">Alpha-numeric designation</option>
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
              <input type="text" class="form-control" name="subject_access_points" value="{{ old('subject_access_points') }}" placeholder="Type to search subjects...">
            </div>

            <div class="mb-3">
              <label class="form-label">Place access points</label>
              <input type="text" class="form-control" name="place_access_points" value="{{ old('place_access_points') }}" placeholder="Type to search places...">
            </div>

            <div class="mb-3">
              <label class="form-label">Genre access points</label>
              <input type="text" class="form-control" name="genre_access_points" value="{{ old('genre_access_points') }}" placeholder="Type to search genres...">
            </div>

            <div class="mb-3">
              <label class="form-label">Name access points</label>
              <input type="text" class="form-control" name="name_access_points" value="{{ old('name_access_points') }}" placeholder="Type to search names...">
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
              <div class="form-text text-muted small">Record a unique description identifier in accordance with local and/or national conventions. If the description is to be used internationally, record the code of the country in which the description was created in accordance with the latest version of ISO 3166 - Codes for the representation of names of countries.</div>
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
              <label for="description_status_id" class="form-label">Status of description</label>
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
              <label class="form-label">Language(s) of description</label>
              <input type="text" class="form-control" name="language_of_description" value="{{ old('language_of_description') }}" placeholder="e.g. English">
              <div class="form-text text-muted small">Indicate the language(s) used to create the description of the archival material.</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Script(s) of description</label>
              <input type="text" class="form-control" name="script_of_description" value="{{ old('script_of_description') }}" placeholder="e.g. Latin">
              <div class="form-text text-muted small">Indicate the script(s) used to create the description of the archival material.</div>
            </div>

            <div class="mb-3">
              <label for="sources" class="form-label">Sources</label>
              <textarea class="form-control" id="sources" name="sources" rows="3">{{ old('sources') }}</textarea>
              <div class="form-text text-muted small">Record citations for any external sources used in the archival description (such as the Scope and Content, Archival History, or Notes fields).</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Archivist's notes</label>
              <textarea class="form-control" name="archivists_notes" rows="3">{{ old('archivists_notes') }}</textarea>
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
            <div class="mb-3">
              <label for="publication_status_id" class="form-label">Publication status</label>
              <select class="form-select" id="publication_status_id" name="publication_status_id">
                <option value="159">Draft</option>
                <option value="160">Published</option>
              </select>
            </div>

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
              <label class="form-label">Source language</label>
              <span class="form-control-plaintext text-muted">{{ app()->getLocale() }}</span>
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

  // Remove row handler for all tables
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.remove-event-row, .remove-altid-row, .remove-note-row');
    if (btn) {
      var table = btn.closest('table');
      if (table.querySelectorAll('tbody tr').length > 1) {
        btn.closest('tr').remove();
      }
    }
  });
});
</script>
@endpush
@endsection
