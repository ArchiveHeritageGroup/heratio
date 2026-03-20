@extends('theme::layouts.1col')

@section('title', ($repository ? 'Edit' : 'Create') . ' repository - ISDIAH')

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">
      @if($repository)
        Edit repository - ISDIAH
      @else
        Create repository - ISDIAH
      @endif
    </h1>
    @if($repository)
      <span class="small">{{ $repository->authorized_form_of_name }}</span>
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
        action="{{ $repository ? route('repository.update', $repository->slug) : route('repository.store') }}"
        id="editForm">
    @csrf

    <div class="accordion mb-3">
      {{-- Identity area (ISDIAH 5.1) --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="identity-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#identity-collapse" aria-expanded="true">
            Identity area
          </button>
        </h2>
        <div id="identity-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="identifier" class="form-label">Identifier</label>
              <input type="text" name="identifier" id="identifier" class="form-control"
                     value="{{ old('identifier', $repository->identifier ?? '') }}">
              <div class="form-text">"Record the numeric or alpha-numeric code identifying the institution in accordance with the relevant international standard." (ISDIAH 5.1.1)</div>
            </div>

            <div class="mb-3">
              <label for="authorized_form_of_name" class="form-label">
                Authorized form of name <span class="text-danger">*</span>
              </label>
              <input type="text" name="authorized_form_of_name" id="authorized_form_of_name" class="form-control" required
                     value="{{ old('authorized_form_of_name', $repository->authorized_form_of_name ?? '') }}">
              <div class="form-text">"Record the standardized form of name of the institution." (ISDIAH 5.1.2)</div>
            </div>

          <div class="mb-3">
            <label for="parallel_name" class="form-label">Parallel form(s) of name</label>
            <textarea class="form-control" id="parallel_name" name="parallel_name" rows="2">{{ old('parallel_name', $repo->parallel_name ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label for="other_name" class="form-label">Other form(s) of name</label>
            <textarea class="form-control" id="other_name" name="other_name" rows="2">{{ old('other_name', $repo->other_name ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label for="repository_type" class="form-label">Type</label>
            <input type="text" class="form-control" id="repository_type" name="repository_type" value="{{ old('repository_type', $repo->repository_type ?? '') }}" placeholder="Type to search repository types...">
            <div class="form-text">Select from the repository type taxonomy</div>
          </div>
          </div>
        </div>
      </div>

      {{-- Contact area (ISDIAH 5.2) --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="contact-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#contact-collapse">
            Contact area
          </button>
        </h2>
        <div id="contact-collapse" class="accordion-collapse collapse">
          <div class="accordion-body">
            @include('ahg-actor-manage::partials._contact-area', ['contacts' => $contacts])
          </div>
        </div>
      </div>

      {{-- Description area (ISDIAH 5.3) --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="description-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#description-collapse">
            Description area
          </button>
        </h2>
        <div id="description-collapse" class="accordion-collapse collapse">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="history" class="form-label">History</label>
              <textarea name="history" id="history" class="form-control" rows="6">{{ old('history', $repository->history ?? '') }}</textarea>
              <div class="form-text">"Record any relevant information about the history of the institution." (ISDIAH 5.3.1)</div>
            </div>

            <div class="mb-3">
              <label for="geocultural_context" class="form-label">Geographical and cultural context</label>
              <textarea name="geocultural_context" id="geocultural_context" class="form-control" rows="4">{{ old('geocultural_context', $repository->geocultural_context ?? '') }}</textarea>
              <div class="form-text">"Record any relevant information about the geographical and cultural context of the institution." (ISDIAH 5.3.2)</div>
            </div>

            <div class="mb-3">
              <label for="mandates" class="form-label">Mandates/Sources of authority</label>
              <textarea name="mandates" id="mandates" class="form-control" rows="4">{{ old('mandates', $repository->mandates ?? '') }}</textarea>
              <div class="form-text">"Record the sources of authority for the institution in terms of its powers, functions, responsibilities or sphere of activities." (ISDIAH 5.3.3)</div>
            </div>

            <div class="mb-3">
              <label for="internal_structures" class="form-label">Administrative structure</label>
              <textarea name="internal_structures" id="internal_structures" class="form-control" rows="4">{{ old('internal_structures', $repository->internal_structures ?? '') }}</textarea>
              <div class="form-text">"Describe the current administrative structure of the institution." (ISDIAH 5.3.4)</div>
            </div>

            <div class="mb-3">
              <label for="collecting_policies" class="form-label">Collecting policies</label>
              <textarea name="collecting_policies" id="collecting_policies" class="form-control" rows="4">{{ old('collecting_policies', $repository->collecting_policies ?? '') }}</textarea>
              <div class="form-text">"Record information about the collecting policies of the institution." (ISDIAH 5.3.5)</div>
            </div>

            <div class="mb-3">
              <label for="buildings" class="form-label">Buildings</label>
              <textarea name="buildings" id="buildings" class="form-control" rows="4">{{ old('buildings', $repository->buildings ?? '') }}</textarea>
              <div class="form-text">"Record information about the building(s) of the institution." (ISDIAH 5.3.6)</div>
            </div>

            <div class="mb-3">
              <label for="holdings" class="form-label">Archival and other holdings</label>
              <textarea name="holdings" id="holdings" class="form-control" rows="4">{{ old('holdings', $repository->holdings ?? '') }}</textarea>
              <div class="form-text">"Record a brief description of the holdings of the institution." (ISDIAH 5.3.7)</div>
            </div>

            <div class="mb-3">
              <label for="finding_aids" class="form-label">Finding aids, guides and publication</label>
              <textarea name="finding_aids" id="finding_aids" class="form-control" rows="4">{{ old('finding_aids', $repository->finding_aids ?? '') }}</textarea>
              <div class="form-text">"Record any published or unpublished finding aids and guides prepared by the institution." (ISDIAH 5.3.8)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- Access area (ISDIAH 5.4) --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="access-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#access-collapse">
            Access area
          </button>
        </h2>
        <div id="access-collapse" class="accordion-collapse collapse">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="opening_times" class="form-label">Opening times</label>
              <textarea name="opening_times" id="opening_times" class="form-control" rows="4">{{ old('opening_times', $repository->opening_times ?? '') }}</textarea>
              <div class="form-text">"Record the opening times and dates of annual closings." (ISDIAH 5.4.1)</div>
            </div>

            <div class="mb-3">
              <label for="access_conditions" class="form-label">Conditions and requirements</label>
              <textarea name="access_conditions" id="access_conditions" class="form-control" rows="4">{{ old('access_conditions', $repository->access_conditions ?? '') }}</textarea>
              <div class="form-text">"Describe access conditions and any restrictions." (ISDIAH 5.4.2)</div>
            </div>

            <div class="mb-3">
              <label for="disabled_access" class="form-label">Accessibility</label>
              <textarea name="disabled_access" id="disabled_access" class="form-control" rows="4">{{ old('disabled_access', $repository->disabled_access ?? '') }}</textarea>
              <div class="form-text">"Record information about access facilities for persons with disabilities." (ISDIAH 5.4.3)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- Services area (ISDIAH 5.5) --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="services-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#services-collapse">
            Services area
          </button>
        </h2>
        <div id="services-collapse" class="accordion-collapse collapse">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="research_services" class="form-label">Research services</label>
              <textarea name="research_services" id="research_services" class="form-control" rows="4">{{ old('research_services', $repository->research_services ?? '') }}</textarea>
              <div class="form-text">"Record information about research services." (ISDIAH 5.5.1)</div>
            </div>

            <div class="mb-3">
              <label for="reproduction_services" class="form-label">Reproduction services</label>
              <textarea name="reproduction_services" id="reproduction_services" class="form-control" rows="4">{{ old('reproduction_services', $repository->reproduction_services ?? '') }}</textarea>
              <div class="form-text">"Record information about reproduction services." (ISDIAH 5.5.2)</div>
            </div>

            <div class="mb-3">
              <label for="public_facilities" class="form-label">Public areas</label>
              <textarea name="public_facilities" id="public_facilities" class="form-control" rows="4">{{ old('public_facilities', $repository->public_facilities ?? '') }}</textarea>
              <div class="form-text">"Record information about areas of the institution accessible to the public." (ISDIAH 5.5.3)</div>
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
            <label for="thematic_area" class="form-label">Thematic area</label>
            <input type="text" class="form-control" id="thematic_area" name="thematic_area" value="{{ old('thematic_area', $repo->thematic_area ?? '') }}" placeholder="Type to search thematic areas...">
          </div>
          <div class="mb-3">
            <label for="geographic_subregion" class="form-label">Geographic subregion</label>
            <input type="text" class="form-control" id="geographic_subregion" name="geographic_subregion" value="{{ old('geographic_subregion', $repo->geographic_subregion ?? '') }}" placeholder="Type to search geographic subregions...">
          </div>
        </div>
      </div>
    </div>

      {{-- Control area (ISDIAH 5.6) --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="control-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#control-collapse">
            Control area
          </button>
        </h2>
        <div id="control-collapse" class="accordion-collapse collapse">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="desc_identifier" class="form-label">Description identifier</label>
              <input type="text" name="desc_identifier" id="desc_identifier" class="form-control"
                     value="{{ old('desc_identifier', $repository->desc_identifier ?? '') }}">
              <div class="form-text">"Record a unique identifier for the description." (ISDIAH 5.6.1)</div>
            </div>

            <div class="mb-3">
              <label for="desc_institution_identifier" class="form-label">Institution identifier</label>
              <input type="text" name="desc_institution_identifier" id="desc_institution_identifier" class="form-control"
                     value="{{ old('desc_institution_identifier', $repository->desc_institution_identifier ?? '') }}">
              <div class="form-text">"Record the full authorized form of name(s) of the agency(ies) responsible for creating, modifying or disseminating the description." (ISDIAH 5.6.2)</div>
            </div>

            <div class="mb-3">
              <label for="desc_rules" class="form-label">Rules and/or conventions used</label>
              <textarea name="desc_rules" id="desc_rules" class="form-control" rows="4">{{ old('desc_rules', $repository->desc_rules ?? '') }}</textarea>
              <div class="form-text">"Record the international, national and/or local rules or conventions applied." (ISDIAH 5.6.3)</div>
            </div>

            <div class="mb-3">
              <label for="desc_status_id" class="form-label">Status</label>
              <select name="desc_status_id" id="desc_status_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['descriptionStatuses'] as $status)
                  <option value="{{ $status->id }}" @selected(old('desc_status_id', $repository->desc_status_id ?? '') == $status->id)>
                    {{ $status->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text">"Indicate the drafting status of the description." (ISDIAH 5.6.4)</div>
            </div>

            <div class="mb-3">
              <label for="desc_detail_id" class="form-label">Level of detail</label>
              <select name="desc_detail_id" id="desc_detail_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['descriptionDetails'] as $detail)
                  <option value="{{ $detail->id }}" @selected(old('desc_detail_id', $repository->desc_detail_id ?? '') == $detail->id)>
                    {{ $detail->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text">"Indicate whether the description applies the minimum or maximum number of elements." (ISDIAH 5.6.5)</div>
            </div>

            <div class="mb-3">
              <label for="desc_revision_history" class="form-label">Dates of creation, revision and deletion</label>
              <textarea name="desc_revision_history" id="desc_revision_history" class="form-control" rows="4">{{ old('desc_revision_history', $repository->desc_revision_history ?? '') }}</textarea>
              <div class="form-text">"Record the date(s) the description was created and/or revised." (ISDIAH 5.6.6)</div>
            </div>

            <div class="mb-3">
              <label for="desc_sources" class="form-label">Sources</label>
              <textarea name="desc_sources" id="desc_sources" class="form-control" rows="4">{{ old('desc_sources', $repository->desc_sources ?? '') }}</textarea>
              <div class="form-text">"Record the sources consulted in establishing the description." (ISDIAH 5.6.8)</div>
            </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="desc_language" class="form-label">Language(s) of description</label>
                <input type="text" class="form-control" id="desc_language" name="desc_language" value="{{ old('desc_language', $repo->desc_language ?? '') }}" placeholder="e.g. English, French">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="desc_script" class="form-label">Script(s) of description</label>
                <input type="text" class="form-control" id="desc_script" name="desc_script" value="{{ old('desc_script', $repo->desc_script ?? '') }}" placeholder="e.g. Latin, Cyrillic">
              </div>
            </div>
          </div>

            @if($repository && $repository->updated_at)
              <div class="mb-3">
                <h3 class="fs-6 mb-2">Last updated</h3>
                <span class="text-muted">{{ $repository->updated_at }}</span>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        @if($repository)
          <li><a href="{{ route('repository.show', $repository->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
          <li><a href="{{ route('repository.confirmDelete', $repository->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
        @else
          <li><a href="{{ route('repository.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
        @endif
      </ul>
    </section>
  </form>

@endsection
