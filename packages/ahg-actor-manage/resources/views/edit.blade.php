@extends('theme::layouts.1col')

@section('title', ($actor ? 'Edit' : 'Add new') . ' authority record - ISAAR')

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      @if($actor)
        Edit authority record - ISAAR
      @else
        Add new authority record - ISAAR
      @endif
    </h1>
    @if($actor)
      <span class="small" id="heading-label">{{ $actor->authorized_form_of_name }}</span>
    @endif
  </div>

  @if($errors->any())
    <div class="alert alert-danger" role="alert">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST"
        action="{{ $actor ? route('actor.update', $actor->slug) : route('actor.store') }}"
        id="editForm">
    @csrf

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
              <label for="entity_type_id" class="form-label">
                Type of entity
                <span class="form-required" title="This is a mandatory element.">*</span>
              </label>
              <select name="entity_type_id" id="entity_type_id" class="form-select" required>
                <option value="">-- Select --</option>
                @foreach($formChoices['entityTypes'] as $type)
                  <option value="{{ $type->id }}" @selected(old('entity_type_id', $actor->entity_type_id ?? '') == $type->id)>
                    {{ $type->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text text-muted small">"Specify the type of entity that is being described in this authority record." (ISAAR 5.1.1) Select Corporate body, Family or Person from the drop-down menu.</div>
            </div>

            <div class="mb-3">
              <label for="authorized_form_of_name" class="form-label">
                Authorized form of name
                <span class="form-required" title="This is a mandatory element.">*</span>
              </label>
              <input type="text" name="authorized_form_of_name" id="authorized_form_of_name" class="form-control" required
                     value="{{ old('authorized_form_of_name', $actor->authorized_form_of_name ?? '') }}">
              <div class="form-text text-muted small">"Record the standardized form of name for the entity being described in accordance with any relevant national or international conventions or rules applied by the agency that created the authority record. Use dates, place, jurisdiction, occupation, epithet and other qualifiers as appropriate to distinguish the authorized form of name from those of other entities with similar names." (ISAAR 5.1.2)</div>
            </div>

            {{-- Other names: Parallel, Standardized, Other --}}
            @include('ahg-actor-manage::partials._other-names', [
                'otherNames' => $otherNames ?? collect(),
                'nameTypes' => $formChoices['nameTypes'],
            ])

            <div class="mb-3">
              <label for="corporate_body_identifiers" class="form-label">Identifiers for corporate bodies</label>
              <input type="text" name="corporate_body_identifiers" id="corporate_body_identifiers" class="form-control"
                     value="{{ old('corporate_body_identifiers', $actor->corporate_body_identifiers ?? '') }}">
              <div class="form-text text-muted small">"Record where possible any official number or other identifier (e.g. a company registration number) for the corporate body and reference the jurisdiction and scheme under which it has been allocated." (ISAAR 5.1.6)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Description area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="description-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#description-collapse" aria-expanded="false" aria-controls="description-collapse">
            Description area
          </button>
        </h2>
        <div id="description-collapse" class="accordion-collapse collapse" aria-labelledby="description-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="dates_of_existence" class="form-label">
                Dates of existence
                <span class="form-required" title="This is a mandatory element.">*</span>
              </label>
              <input type="text" name="dates_of_existence" id="dates_of_existence" class="form-control"
                     value="{{ old('dates_of_existence', $actor->dates_of_existence ?? '') }}">
              <div class="form-text text-muted small">"Record the dates of existence of the entity being described. For corporate bodies include the date of establishment/foundation/enabling legislation and dissolution. For persons include the dates or approximate dates of birth and death or, when these dates are not known, floruit dates. Where parallel systems of dating are used, equivalences may be recorded according to relevant conventions or rules. Specify in the Rules and/or conventions element (5.4.3) the system(s) of dating used, e.g. ISO 8601." (ISAAR 5.2.1)</div>
            </div>

            <div class="mb-3">
              <label for="history" class="form-label">History</label>
              <textarea name="history" id="history" class="form-control" rows="6">{{ old('history', $actor->history ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record in narrative form or as a chronology the main life events, activities, achievements and/or roles of the entity being described. This may include information on gender, nationality, family and religious or political affiliations. Wherever possible, supply dates as an integral component of the narrative description." (ISAAR 5.2.2)</div>
            </div>

            <div class="mb-3">
              <label for="places" class="form-label">Places</label>
              <textarea name="places" id="places" class="form-control" rows="4">{{ old('places', $actor->places ?? '') }}</textarea>
              <div class="form-text text-muted small">"Purpose: to indicate the predominant places and/or jurisdictions where the corporate body, person or family was based, lived or resided or had some other connection. Rule: record the name of the predominant place(s)/jurisdiction(s), together with the nature and covering dates of the relationship with the entity." (ISAAR 5.2.3)</div>
            </div>

            <div class="mb-3">
              <label for="legal_status" class="form-label">Legal status</label>
              <textarea name="legal_status" id="legal_status" class="form-control" rows="4">{{ old('legal_status', $actor->legal_status ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record the legal status and where appropriate the type of corporate body together with the covering dates when this status applied." (ISAAR 5.2.4)</div>
            </div>

            <div class="mb-3">
              <label for="functions" class="form-label">Functions, occupations and activities</label>
              <textarea name="functions" id="functions" class="form-control" rows="4">{{ old('functions', $actor->functions ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record the functions, occupations and activities performed by the entity being described, together with the covering dates when useful. If necessary, describe the nature of the function, occupation or activity." (ISAAR 5.2.5)</div>
            </div>

            <div class="mb-3">
              <label for="mandates" class="form-label">Mandates/sources of authority</label>
              <textarea name="mandates" id="mandates" class="form-control" rows="4">{{ old('mandates', $actor->mandates ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record any document, law, directive or charter which acts as a source of authority for the powers, functions and responsibilities of the entity being described, together with information on the jurisdiction(s) and covering dates when the mandate(s) applied or were changed." (ISAAR 5.2.6)</div>
            </div>

            <div class="mb-3">
              <label for="internal_structures" class="form-label">Internal structures/genealogy</label>
              <textarea name="internal_structures" id="internal_structures" class="form-control" rows="4">{{ old('internal_structures', $actor->internal_structures ?? '') }}</textarea>
              <div class="form-text text-muted small">"Describe the internal structure of a corporate body and the dates of any changes to that structure that are significant to the understanding of the way that corporate body conducted its affairs (e.g. by means of dated organization charts). Describe the genealogy of a family (e.g. by means of a family tree) in a way that demonstrates the inter-relationships of its members with covering dates." (ISAAR 5.2.7)</div>
            </div>

            <div class="mb-3">
              <label for="general_context" class="form-label">General context</label>
              <textarea name="general_context" id="general_context" class="form-control" rows="4">{{ old('general_context', $actor->general_context ?? '') }}</textarea>
              <div class="form-text text-muted small">"Provide any significant information on the social, cultural, economic, political and/or historical context in which the entity being described operated." (ISAAR 5.2.8)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Relationships area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="relationships-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#relationships-collapse" aria-expanded="false" aria-controls="relationships-collapse">
            Relationships area
          </button>
        </h2>
        <div id="relationships-collapse" class="accordion-collapse collapse" aria-labelledby="relationships-heading">
          <div class="accordion-body">

            <!-- Related corporate bodies, persons or families -->
            <h3 class="fs-6 mb-2">Related corporate bodies, persons or families</h3>
            <div class="table-responsive mb-3">
              <table class="table table-bordered mb-0" id="related-actors-table">
                <thead class="table-light">
                  <tr>
                    <th class="w-25">Name</th>
                    <th class="w-15">Category</th>
                    <th class="w-15">Type</th>
                    <th class="w-15">Dates</th>
                    <th class="w-30">Description</th>
                    <th><span class="visually-hidden">Actions</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><input type="text" name="relatedActors[0][name]" class="form-control form-control-sm" placeholder="Type to search..."></td>
                    <td>
                      <select name="relatedActors[0][category]" class="form-select form-select-sm">
                        <option value=""></option>
                        <option value="hierarchical">Hierarchical</option>
                        <option value="temporal">Temporal</option>
                        <option value="family">Family</option>
                        <option value="associative">Associative</option>
                      </select>
                    </td>
                    <td><input type="text" name="relatedActors[0][type]" class="form-control form-control-sm"></td>
                    <td><input type="text" name="relatedActors[0][dates]" class="form-control form-control-sm"></td>
                    <td><input type="text" name="relatedActors[0][description]" class="form-control form-control-sm"></td>
                    <td>
                      <button type="button" class="btn atom-btn-white remove-relactor-row">
                        <i class="fas fa-times" aria-hidden="true"></i>
                        <span class="visually-hidden">Delete row</span>
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="6">
                      <button type="button" class="btn atom-btn-white" id="add-relactor-row">
                        <i class="fas fa-plus me-1" aria-hidden="true"></i>Add new
                      </button>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Related resources -->
            <h3 class="fs-6 mb-2">Related resources</h3>
            <div class="table-responsive mb-3">
              <table class="table table-bordered mb-0" id="related-resources-table">
                <thead class="table-light">
                  <tr>
                    <th class="w-40">Title</th>
                    <th class="w-30">Relationship</th>
                    <th class="w-30">Dates</th>
                    <th><span class="visually-hidden">Actions</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><input type="text" name="relatedResources[0][title]" class="form-control form-control-sm" placeholder="Type to search..."></td>
                    <td><input type="text" name="relatedResources[0][relationship]" class="form-control form-control-sm"></td>
                    <td><input type="text" name="relatedResources[0][dates]" class="form-control form-control-sm"></td>
                    <td>
                      <button type="button" class="btn atom-btn-white remove-relresource-row">
                        <i class="fas fa-times" aria-hidden="true"></i>
                        <span class="visually-hidden">Delete row</span>
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="4">
                      <button type="button" class="btn atom-btn-white" id="add-relresource-row">
                        <i class="fas fa-plus me-1" aria-hidden="true"></i>Add new
                      </button>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Contact information ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="contact-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#contact-collapse" aria-expanded="false" aria-controls="contact-collapse">
            Contact information
          </button>
        </h2>
        <div id="contact-collapse" class="accordion-collapse collapse" aria-labelledby="contact-heading">
          <div class="accordion-body">
            @include('ahg-actor-manage::partials._contact-area', [
                'contacts' => $contacts ?? collect(),
            ])
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

            <!-- Occupation(s) multi-row table -->
            <h3 class="fs-6 mb-2">Occupation(s)</h3>
            <div class="table-responsive">
              <table class="table table-bordered mb-0" id="occupations-table">
                <thead class="table-light">
                  <tr>
                    <th id="occupations-occupation-head" class="w-50">Occupation</th>
                    <th id="occupations-content-head" class="w-50">Note</th>
                    <th><span class="visually-hidden">Delete</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><input type="text" name="occupations[0][occupation]" class="form-control form-control-sm" placeholder="Type to search occupations..." aria-labelledby="occupations-occupation-head"></td>
                    <td><input type="text" name="occupations[0][content]" class="form-control form-control-sm" aria-labelledby="occupations-content-head"></td>
                    <td>
                      <button type="button" class="btn atom-btn-white remove-occupation-row">
                        <i class="fas fa-times" aria-hidden="true"></i>
                        <span class="visually-hidden">Delete row</span>
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="3">
                      <button type="button" class="btn atom-btn-white" id="add-occupation-row">
                        <i class="fas fa-plus me-1" aria-hidden="true"></i>Add new
                      </button>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Control area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="control-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#control-collapse" aria-expanded="false" aria-controls="control-collapse">
            Control area
          </button>
        </h2>
        <div id="control-collapse" class="accordion-collapse collapse" aria-labelledby="control-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="description_identifier" class="form-label">
                Authority record identifier
                <span class="form-required" title="This is a mandatory element.">*</span>
              </label>
              <input type="text" name="description_identifier" id="description_identifier" class="form-control"
                     value="{{ old('description_identifier', $actor->description_identifier ?? '') }}">
              <div class="form-text text-muted small">"Record a unique authority record identifier in accordance with local and/or national conventions. If the authority record is to be used internationally, record the country code of the country in which the authority record was created in accordance with the latest version of ISO 3166 Codes for the representation of names of countries. Where the creator of the authority record is an international organization, give the organizational identifier in place of the country code." (ISAAR 5.4.1)</div>
            </div>

            <div class="mb-3">
              <label for="maintaining_repository" class="form-label">Maintaining repository</label>
              <input type="text" name="maintaining_repository" id="maintaining_repository" class="form-control"
                     value="{{ old('maintaining_repository') }}" placeholder="Type to search repositories..." autocomplete="off">
              <div class="form-text text-muted small">"Record the full authorized form of name(s) of the agency(ies) responsible for creating, modifying or disseminating the authority record or, alternatively, record a code for the agency in accordance with the national or international agency code standard. Include reference to any systems of identification used to identify the institutions (e.g. ISO 15511)." (ISAAR 5.4.2)</div>
            </div>

            <div class="mb-3">
              <label for="institution_responsible_identifier" class="form-label">Institution identifier</label>
              <input type="text" name="institution_responsible_identifier" id="institution_responsible_identifier" class="form-control"
                     value="{{ old('institution_responsible_identifier', $actor->institution_responsible_identifier ?? '') }}">
              <div class="form-text text-muted small">"Record the full authorized form of name(s) of the agency(ies) responsible for creating, modifying or disseminating the authority record or, alternatively, record a code for the agency in accordance with the national or international agency code standard. Include reference to any systems of identification used to identify the institutions (e.g. ISO 15511)." (ISAAR 5.4.2)</div>
            </div>

            <div class="mb-3">
              <label for="rules" class="form-label">Rules and/or conventions used</label>
              <textarea name="rules" id="rules" class="form-control" rows="4">{{ old('rules', $actor->rules ?? '') }}</textarea>
              <div class="form-text text-muted small">"Purpose: To identify the national or international conventions or rules applied in creating the archival authority record. Rule: Record the names and where useful the editions or publication dates of the conventions or rules applied. Specify separately which rules have been applied for creating the Authorized form of name. Include reference to any system(s) of dating used to identify dates in this authority record (e.g. ISO 8601)." (ISAAR 5.4.3)</div>
            </div>

            <div class="mb-3">
              <label for="description_status_id" class="form-label">Status</label>
              <select name="description_status_id" id="description_status_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['descriptionStatuses'] as $status)
                  <option value="{{ $status->id }}" @selected(old('description_status_id', $actor->description_status_id ?? '') == $status->id)>
                    {{ $status->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text text-muted small">The purpose of this field is "[t]o indicate the drafting status of the authority record so that users can understand the current status of the authority record." (ISAAR 5.4.4). Select Final, Revised or Draft from the drop-down menu.</div>
            </div>

            <div class="mb-3">
              <label for="description_detail_id" class="form-label">Level of detail</label>
              <select name="description_detail_id" id="description_detail_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['descriptionDetails'] as $detail)
                  <option value="{{ $detail->id }}" @selected(old('description_detail_id', $actor->description_detail_id ?? '') == $detail->id)>
                    {{ $detail->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Select Full, Partial or Minimal from the drop-down menu. "In the absence of national guidelines or rules, minimal records are those that consist only of the four essential elements of an ISAAR(CPF) compliant authority record (see 4.8), while full records are those that convey information for all relevant ISAAR(CPF) elements of description." (ISAAR 5.4.5)</div>
            </div>

            <div class="mb-3">
              <label for="revision_history" class="form-label">Dates of creation, revision and deletion</label>
              <textarea name="revision_history" id="revision_history" class="form-control" rows="4">{{ old('revision_history', $actor->revision_history ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record the date the authority record was created and the dates of any revisions to the record." (ISAAR 5.4.6)</div>
            </div>

            @if($actor && $actor->updated_at)
              <div class="mb-3">
                <h3 class="fs-6 mb-2">Last updated</h3>
                <span class="text-muted">{{ $actor->updated_at }}</span>
              </div>
            @endif

            <div class="mb-3">
              <label for="language" class="form-label">Language(s)</label>
              <input type="text" class="form-control" id="language" name="language" value="{{ old('language') }}" placeholder="e.g. English, French">
              <div class="form-text text-muted small">Select the language(s) of the authority record from the drop-down menu; enter the first few letters to narrow the choices. (ISAAR 5.4.7)</div>
            </div>

            <div class="mb-3">
              <label for="script" class="form-label">Script(s)</label>
              <input type="text" class="form-control" id="script" name="script" value="{{ old('script') }}" placeholder="e.g. Latin, Cyrillic">
              <div class="form-text text-muted small">Select the script(s) of the authority record from the drop-down menu; enter the first few letters to narrow the choices. (ISAAR 5.4.7)</div>
            </div>

            <div class="mb-3">
              <label for="sources" class="form-label">Sources</label>
              <textarea name="sources" id="sources" class="form-control" rows="4">{{ old('sources', $actor->sources ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record the sources consulted in establishing the authority record." (ISAAR 5.4.8)</div>
            </div>

            <div class="mb-3">
              <label for="maintenance_notes" class="form-label">Maintenance notes</label>
              <textarea name="maintenance_notes" id="maintenance_notes" class="form-control" rows="4">{{ old('maintenance_notes', $maintenanceNotes ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record notes pertinent to the creation and maintenance of the authority record. The names of persons responsible for creating the authority record may be recorded here." (ISAAR 5.4.9)</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($actor)
        <li><a href="{{ route('actor.show', $actor->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a href="{{ route('actor.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
      @endif
    </ul>
  </form>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Related actors multi-row
  var relActorIdx = 1;
  document.getElementById('add-relactor-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="text" name="relatedActors[' + relActorIdx + '][name]" class="form-control form-control-sm" placeholder="Type to search..."></td>' +
      '<td><select name="relatedActors[' + relActorIdx + '][category]" class="form-select form-select-sm"><option value=""></option><option value="hierarchical">Hierarchical</option><option value="temporal">Temporal</option><option value="family">Family</option><option value="associative">Associative</option></select></td>' +
      '<td><input type="text" name="relatedActors[' + relActorIdx + '][type]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="relatedActors[' + relActorIdx + '][dates]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="relatedActors[' + relActorIdx + '][description]" class="form-control form-control-sm"></td>' +
      '<td><button type="button" class="btn atom-btn-white remove-relactor-row"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Delete row</span></button></td>';
    document.querySelector('#related-actors-table tbody').appendChild(tr);
    relActorIdx++;
  });

  // Related resources multi-row
  var relResIdx = 1;
  document.getElementById('add-relresource-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="text" name="relatedResources[' + relResIdx + '][title]" class="form-control form-control-sm" placeholder="Type to search..."></td>' +
      '<td><input type="text" name="relatedResources[' + relResIdx + '][relationship]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="relatedResources[' + relResIdx + '][dates]" class="form-control form-control-sm"></td>' +
      '<td><button type="button" class="btn atom-btn-white remove-relresource-row"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Delete row</span></button></td>';
    document.querySelector('#related-resources-table tbody').appendChild(tr);
    relResIdx++;
  });

  // Occupations multi-row
  var occIdx = 1;
  document.getElementById('add-occupation-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="text" name="occupations[' + occIdx + '][occupation]" class="form-control form-control-sm" placeholder="Type to search occupations..."></td>' +
      '<td><input type="text" name="occupations[' + occIdx + '][content]" class="form-control form-control-sm"></td>' +
      '<td><button type="button" class="btn atom-btn-white remove-occupation-row"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Delete row</span></button></td>';
    document.querySelector('#occupations-table tbody').appendChild(tr);
    occIdx++;
  });

  // Remove row handler for all multi-row tables
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.remove-relactor-row, .remove-relresource-row, .remove-occupation-row');
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
