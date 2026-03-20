@extends('theme::layouts.1col')

@section('title', ($actor ? 'Edit' : 'Create') . ' authority record - ISAAR')

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">
      @if($actor)
        Edit authority record - ISAAR
      @else
        Create authority record - ISAAR
      @endif
    </h1>
    @if($actor)
      <span class="small">{{ $actor->authorized_form_of_name }}</span>
    @endif
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
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
      {{-- Identity area --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="identity-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#identity-collapse" aria-expanded="true" aria-controls="identity-collapse">
            Identity area
          </button>
        </h2>
        <div id="identity-collapse" class="accordion-collapse collapse show" aria-labelledby="identity-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="entity_type_id" class="form-label">
                Type of entity <span class="form-required text-danger" title="This is a mandatory element.">*</span>
              </label>
              <select name="entity_type_id" id="entity_type_id" class="form-select" required>
                <option value="">-- Select --</option>
                @foreach($formChoices['entityTypes'] as $type)
                  <option value="{{ $type->id }}" @selected(old('entity_type_id', $actor->entity_type_id ?? '') == $type->id)>
                    {{ $type->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text">"Specify the type of entity that is being described in this authority record." (ISAAR 5.1.1) Select Corporate body, Family or Person from the drop-down menu.</div>
            </div>

            <div class="mb-3">
              <label for="authorized_form_of_name" class="form-label">
                Authorized form of name <span class="form-required text-danger" title="This is a mandatory element.">*</span>
              </label>
              <input type="text" name="authorized_form_of_name" id="authorized_form_of_name" class="form-control" required
                     value="{{ old('authorized_form_of_name', $actor->authorized_form_of_name ?? '') }}">
              <div class="form-text">"Record the standardized form of name for the entity being described in accordance with any relevant national or international conventions or rules applied by the agency that created the authority record." (ISAAR 5.1.2)</div>
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
              <div class="form-text">"Record where possible any official number or other identifier (e.g. a company registration number) for the corporate body and reference the jurisdiction and scheme under which it has been allocated." (ISAAR 5.1.6)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- Description area --}}
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
                Dates of existence <span class="form-required text-danger" title="This is a mandatory element.">*</span>
              </label>
              <input type="text" name="dates_of_existence" id="dates_of_existence" class="form-control"
                     value="{{ old('dates_of_existence', $actor->dates_of_existence ?? '') }}">
              <div class="form-text">"Record the dates of existence of the entity being described." (ISAAR 5.2.1)</div>
            </div>

            <div class="mb-3">
              <label for="history" class="form-label">History</label>
              <textarea name="history" id="history" class="form-control" rows="6">{{ old('history', $actor->history ?? '') }}</textarea>
              <div class="form-text">"Record in narrative form or as a chronology the main life events, activities, achievements and/or roles of the entity being described." (ISAAR 5.2.2)</div>
            </div>

            <div class="mb-3">
              <label for="places" class="form-label">Places</label>
              <textarea name="places" id="places" class="form-control" rows="4">{{ old('places', $actor->places ?? '') }}</textarea>
              <div class="form-text">"Record the name of the predominant place(s)/jurisdiction(s), together with the nature and covering dates of the relationship with the entity." (ISAAR 5.2.3)</div>
            </div>

            <div class="mb-3">
              <label for="legal_status" class="form-label">Legal status</label>
              <textarea name="legal_status" id="legal_status" class="form-control" rows="4">{{ old('legal_status', $actor->legal_status ?? '') }}</textarea>
              <div class="form-text">"Record the legal status and where appropriate the type of corporate body together with the covering dates when this status applied." (ISAAR 5.2.4)</div>
            </div>

            <div class="mb-3">
              <label for="functions" class="form-label">Functions, occupations and activities</label>
              <textarea name="functions" id="functions" class="form-control" rows="4">{{ old('functions', $actor->functions ?? '') }}</textarea>
              <div class="form-text">"Record the functions, occupations and activities performed by the entity being described, together with the covering dates when useful." (ISAAR 5.2.5)</div>
            </div>

            <div class="mb-3">
              <label for="mandates" class="form-label">Mandates/sources of authority</label>
              <textarea name="mandates" id="mandates" class="form-control" rows="4">{{ old('mandates', $actor->mandates ?? '') }}</textarea>
              <div class="form-text">"Record any document, law, directive or charter which acts as a source of authority for the powers, functions and responsibilities of the entity being described." (ISAAR 5.2.6)</div>
            </div>

            <div class="mb-3">
              <label for="internal_structures" class="form-label">Internal structures/genealogy</label>
              <textarea name="internal_structures" id="internal_structures" class="form-control" rows="4">{{ old('internal_structures', $actor->internal_structures ?? '') }}</textarea>
              <div class="form-text">"Describe the internal structure of a corporate body and the dates of any changes to that structure that are significant to the understanding of the way that corporate body conducted its affairs." (ISAAR 5.2.7)</div>
            </div>

            <div class="mb-3">
              <label for="general_context" class="form-label">General context</label>
              <textarea name="general_context" id="general_context" class="form-control" rows="4">{{ old('general_context', $actor->general_context ?? '') }}</textarea>
              <div class="form-text">"Provide any significant information on the social, cultural, economic, political and/or historical context in which the entity being described operated." (ISAAR 5.2.8)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- Relationships area --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="heading-relationships"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-relationships">Relationships</button></h2>
        <div id="collapse-relationships" class="accordion-collapse collapse" aria-labelledby="heading-relationships">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="related_authority_records" class="form-label">Related authority records</label>
              <input type="text" class="form-control" id="related_authority_records" name="related_authority_records" value="{{ old('related_authority_records') }}" placeholder="Type to search authority records...">
              <div class="form-text">Link to related authority records (actors)</div>
            </div>
            <div class="mb-3">
              <label for="related_resources" class="form-label">Related resources</label>
              <input type="text" class="form-control" id="related_resources" name="related_resources" value="{{ old('related_resources') }}" placeholder="Type to search archival descriptions...">
              <div class="form-text">Link to related archival descriptions</div>
            </div>
            <div class="mb-3">
              <label for="related_functions" class="form-label">Related functions</label>
              <input type="text" class="form-control" id="related_functions" name="related_functions" value="{{ old('related_functions') }}" placeholder="Type to search functions...">
            </div>
          </div>
        </div>
      </div>

      {{-- Access points --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="heading-access-points"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-access-points">Access points</button></h2>
        <div id="collapse-access-points" class="accordion-collapse collapse" aria-labelledby="heading-access-points">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="occupation_access_points" class="form-label">Occupation access points</label>
              <input type="text" class="form-control" id="occupation_access_points" name="occupation_access_points" value="{{ old('occupation_access_points') }}" placeholder="Type to search occupations...">
            </div>
            <div class="mb-3">
              <label for="subject_access_points" class="form-label">Subject access points</label>
              <input type="text" class="form-control" id="subject_access_points" name="subject_access_points" value="{{ old('subject_access_points') }}" placeholder="Type to search subjects...">
            </div>
            <div class="mb-3">
              <label for="place_access_points" class="form-label">Place access points</label>
              <input type="text" class="form-control" id="place_access_points" name="place_access_points" value="{{ old('place_access_points') }}" placeholder="Type to search places...">
            </div>
          </div>
        </div>
      </div>

      {{-- Contact information --}}
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

      {{-- Control area --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="control-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#control-collapse" aria-expanded="false" aria-controls="control-collapse">
            Control area
          </button>
        </h2>
        <div id="control-collapse" class="accordion-collapse collapse" aria-labelledby="control-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="description_identifier" class="form-label">Authority record identifier</label>
              <input type="text" name="description_identifier" id="description_identifier" class="form-control"
                     value="{{ old('description_identifier', $actor->description_identifier ?? '') }}">
              <div class="form-text">"Record a unique authority record identifier in accordance with local and/or national conventions." (ISAAR 5.4.1)</div>
            </div>

            <div class="mb-3">
              <label for="institution_responsible_identifier" class="form-label">Institution identifier</label>
              <input type="text" name="institution_responsible_identifier" id="institution_responsible_identifier" class="form-control"
                     value="{{ old('institution_responsible_identifier', $actor->institution_responsible_identifier ?? '') }}">
              <div class="form-text">"Record the full authorized form of name(s) of the agency(ies) responsible for creating, modifying or disseminating the authority record." (ISAAR 5.4.2)</div>
            </div>

            <div class="mb-3">
              <label for="rules" class="form-label">Rules and/or conventions used</label>
              <textarea name="rules" id="rules" class="form-control" rows="4">{{ old('rules', $actor->rules ?? '') }}</textarea>
              <div class="form-text">"Record the names and where useful the editions or publication dates of the conventions or rules applied." (ISAAR 5.4.3)</div>
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
              <div class="form-text">"Indicate the drafting status of the authority record." (ISAAR 5.4.4)</div>
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
              <div class="form-text">"Select Full, Partial or Minimal." (ISAAR 5.4.5)</div>
            </div>

            <div class="mb-3">
              <label for="revision_history" class="form-label">Dates of creation, revision and deletion</label>
              <textarea name="revision_history" id="revision_history" class="form-control" rows="4">{{ old('revision_history', $actor->revision_history ?? '') }}</textarea>
              <div class="form-text">"Record the date the authority record was created and the dates of any revisions to the record." (ISAAR 5.4.6)</div>
            </div>

            @if($actor && $actor->updated_at)
              <div class="mb-3">
                <h3 class="fs-6 mb-2">Last updated</h3>
                <span class="text-muted">{{ $actor->updated_at }}</span>
              </div>
            @endif

            <div class="mb-3">
              <label for="sources" class="form-label">Sources</label>
              <textarea name="sources" id="sources" class="form-control" rows="4">{{ old('sources', $actor->sources ?? '') }}</textarea>
              <div class="form-text">"Record the sources consulted in establishing the authority record." (ISAAR 5.4.8)</div>
            </div>

            <div class="mb-3">
              <label for="maintenance_notes" class="form-label">Maintenance notes</label>
              <textarea name="maintenance_notes" id="maintenance_notes" class="form-control" rows="4">{{ old('maintenance_notes', $maintenanceNotes ?? '') }}</textarea>
              <div class="form-text">"Record notes pertinent to the creation and maintenance of the authority record." (ISAAR 5.4.9)</div>
            </div>

            <div class="mb-3">
              <label for="source_standard" class="form-label">Source standard</label>
              <input type="text" name="source_standard" id="source_standard" class="form-control"
                     value="{{ old('source_standard', $actor->source_standard ?? '') }}">
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="language" class="form-label">Language(s)</label>
                  <input type="text" class="form-control" id="language" name="language" value="{{ old('language') }}" placeholder="e.g. English, French">
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="script" class="form-label">Script(s)</label>
                  <input type="text" class="form-control" id="script" name="script" value="{{ old('script') }}" placeholder="e.g. Latin, Cyrillic">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        @if($actor)
          <li><a href="{{ route('actor.show', $actor->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
          <li><a href="{{ route('actor.confirmDelete', $actor->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
        @else
          <li><a href="{{ route('actor.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
        @endif
      </ul>
    </section>
  </form>

@endsection
