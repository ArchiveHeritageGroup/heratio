@extends(request('copy_from') ? 'theme::layouts.2col' : 'theme::layouts.1col')

@section('title', 'Add new archival description')
@section('body-class', 'create informationobject')

@if(request('copy_from'))
@section('sidebar')
  @if($parentId)
    @php
      $repoId = \Illuminate\Support\Facades\DB::table('information_object')->where('id', $parentId)->value('repository_id');
      $repoSlug = $repoId ? \Illuminate\Support\Facades\DB::table('slug')->where('object_id', $repoId)->value('slug') : null;
    @endphp
    @if($repoSlug)
      <div class="repository-logo mb-3 mx-auto">
        <a class="text-decoration-none" href="{{ url('/repository/' . $repoSlug) }}">
          <img alt="Go to repository" class="img-fluid img-thumbnail border-4 shadow-sm bg-white"
               src="/uploads/r/{{ $repoSlug }}/conf/logo.png"
               onerror="this.parentElement.style.display='none'">
        </a>
      </div>
    @endif
  @endif
@endsection
@endif

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      @if($parentTitle && request('copy_from'))
        Item - {{ $parentTitle }}
      @else
        Add new archival description
      @endif
    </h1>
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
                <button type="button" class="btn atom-btn-white" id="generate-identifier" data-url="{{ url('/informationobject/generateIdentifier') }}">
                  <i class="fas fa-cog me-1" aria-hidden="true"></i>Generate
                </button>
              </div>
              <div class="form-text text-muted small">Provide a specific local reference code, control number, or other unique identifier. The country and repository code will be automatically added from the linked repository record to form a full reference code. (ISAD 3.1.1)</div>
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
            <div class="table-responsive">
              <table class="table table-bordered mb-0">
                <thead>
                  <tr style="background:var(--ahg-primary);color:#fff">
                    <th>Type</th>
                    <th>Content</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
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
              <input type="text" class="form-control" id="institution_responsible_identifier" name="institution_responsible_identifier" value="{{ old('institution_responsible_identifier') }}">
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
          </div>
        </div>
      </div>


    </div>

    @if(request('copy_from'))
      {{-- Security Classification (shown on copy) --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="security-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#security-collapse" aria-expanded="false">
            Security Classification
          </button>
        </h2>
        <div id="security-collapse" class="accordion-collapse collapse" aria-labelledby="security-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="security_classification_id" class="form-label">Classification level</label>
              <select name="security_classification_id" id="security_classification_id" class="form-select">
                <option value="">-- None --</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      {{-- Watermark Settings (shown on copy) --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="watermark-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#watermark-collapse" aria-expanded="false">
            Watermark Settings
          </button>
        </h2>
        <div id="watermark-collapse" class="accordion-collapse collapse" aria-labelledby="watermark-heading">
          <div class="accordion-body">
            <p class="text-muted">Watermark settings are managed via the digital object interface.</p>
          </div>
        </div>
      </div>

      {{-- Administration area (shown on copy) --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="admin-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#admin-collapse" aria-expanded="false">
            Administration area
          </button>
        </h2>
        <div id="admin-collapse" class="accordion-collapse collapse" aria-labelledby="admin-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="publication_status_id" class="form-label">Publication status</label>
              <select name="publication_status_id" id="publication_status_id" class="form-select">
                <option value="159" selected>Draft</option>
                <option value="160">Published</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    @endif

    <ul class="actions mb-3 nav gap-2">
      @if(request('copy_from') && $parentTitle)
        @php $sourceSlug = \Illuminate\Support\Facades\DB::table('slug')->where('object_id', request('copy_from'))->value('slug'); @endphp
        <li><a class="btn atom-btn-outline-light" role="button" href="{{ $sourceSlug ? url('/' . $sourceSlug) : route('informationobject.browse') }}" title="Cancel">Cancel</a></li>
      @else
        <li><a class="btn atom-btn-outline-light" role="button" href="{{ route('informationobject.browse') }}" title="Cancel">Cancel</a></li>
      @endif
      <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
    </ul>
  </form>

@push('css')
<style>
.accordion-button {
  background-color: var(--ahg-primary) !important;
  color: var(--ahg-card-header-text, #fff) !important;
}
.accordion-button:not(.collapsed) {
  background-color: var(--ahg-primary) !important;
  color: var(--ahg-card-header-text, #fff) !important;
  box-shadow: none;
}
.accordion-button.collapsed {
  background-color: var(--ahg-primary) !important;
  color: var(--ahg-card-header-text, #fff) !important;
}
.accordion-button::after {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'//%3e%3c/svg%3e");
}
.accordion-button:focus {
  box-shadow: 0 0 0 0.25rem var(--ahg-input-focus, rgba(0,88,55,0.25));
}
</style>
@endpush
@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Generate identifier button
  var genBtn = document.getElementById('generate-identifier');
  if (genBtn) {
    genBtn.addEventListener('click', function() {
      var url = this.getAttribute('data-url');
      fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (data.identifier) {
          document.getElementById('identifier').value = data.identifier;
        }
      });
    });
  }
});
</script>
@endpush
@endsection
