@extends('theme::layouts.1col')

@section('title', ($function ? 'Edit' : 'Create') . ' function - ISDF')

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">
      @if($function)
        Edit function - ISDF
      @else
        Create function - ISDF
      @endif
    </h1>
    @if($function)
      <span class="small">{{ $function->authorized_form_of_name }}</span>
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
        action="{{ $function ? route('function.update', $function->slug) : route('function.store') }}"
        id="editForm">
    @csrf

    <div class="accordion mb-3">
      {{-- Identity area (ISDF 5.1) --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="identity-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#identity-collapse" aria-expanded="true" aria-controls="identity-collapse">
            Identity area
          </button>
        </h2>
        <div id="identity-collapse" class="accordion-collapse collapse show" aria-labelledby="identity-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="type_id" class="form-label">Type</label>
              <select name="type_id" id="type_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['functionTypes'] as $type)
                  <option value="{{ $type->id }}" @selected(old('type_id', $function->type_id ?? '') == $type->id)>
                    {{ $type->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text">"Indicate whether the description is of a function, sub-function, business process, activity, task, or transaction." (ISDF 5.1.1)</div>
            </div>

            <div class="mb-3">
              <label for="authorized_form_of_name" class="form-label">
                Authorized form of name <span class="form-required text-danger" title="This is a mandatory element.">*</span>
              </label>
              <input type="text" name="authorized_form_of_name" id="authorized_form_of_name" class="form-control" required
                     value="{{ old('authorized_form_of_name', $function->authorized_form_of_name ?? '') }}">
              <div class="form-text">"Record the authorised name of the function being described." (ISDF 5.1.2)</div>
            </div>

          <div class="mb-3">
            <label for="parallel_name" class="form-label">Parallel form(s) of name</label>
            <textarea class="form-control" id="parallel_name" name="parallel_name" rows="2">{{ old('parallel_name', $function->parallel_name ?? '') }}</textarea>
          </div>
          <div class="mb-3">
            <label for="other_name" class="form-label">Other form(s) of name</label>
            <textarea class="form-control" id="other_name" name="other_name" rows="2">{{ old('other_name', $function->other_name ?? '') }}</textarea>
          </div>

            <div class="mb-3">
              <label for="classification" class="form-label">Classification</label>
              <input type="text" name="classification" id="classification" class="form-control"
                     value="{{ old('classification', $function->classification ?? '') }}">
              <div class="form-text">"Record any classification code or system associated with the function being described." (ISDF 5.1.3)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- Description area (ISDF 5.2) --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="description-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#description-collapse" aria-expanded="false" aria-controls="description-collapse">
            Description area
          </button>
        </h2>
        <div id="description-collapse" class="accordion-collapse collapse" aria-labelledby="description-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="dates" class="form-label">Dates</label>
              <input type="text" name="dates" id="dates" class="form-control"
                     value="{{ old('dates', $function->dates ?? '') }}">
              <div class="form-text">"Record as appropriate any dates associated with the function being described." (ISDF 5.2.1)</div>
            </div>

            <div class="mb-3">
              <label for="description" class="form-label">Description</label>
              <textarea name="description" id="description" class="form-control" rows="6">{{ old('description', $function->description ?? '') }}</textarea>
              <div class="form-text">"Record a description of the nature, purpose and scope of the function." (ISDF 5.2.2)</div>
            </div>

            <div class="mb-3">
              <label for="history" class="form-label">History</label>
              <textarea name="history" id="history" class="form-control" rows="6">{{ old('history', $function->history ?? '') }}</textarea>
              <div class="form-text">"Record in narrative form or as a chronology the main events relating to the function." (ISDF 5.2.3)</div>
            </div>

            <div class="mb-3">
              <label for="legislation" class="form-label">Legislation</label>
              <textarea name="legislation" id="legislation" class="form-control" rows="6">{{ old('legislation', $function->legislation ?? '') }}</textarea>
              <div class="form-text">"Record any law, directive or charter related to the function." (ISDF 5.2.4)</div>
            </div>
          </div>
        </div>
      </div>

    {{-- Relationships area (ISDF 5.3) --}}
    <div class="accordion-item">
      <h2 class="accordion-header" id="heading-relationships"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-relationships">Relationships</button></h2>
      <div id="collapse-relationships" class="accordion-collapse collapse" aria-labelledby="heading-relationships">
        <div class="accordion-body">
          <div class="mb-3">
            <label for="related_function" class="form-label">Related function</label>
            <input type="text" class="form-control" id="related_function" name="related_function" value="{{ old('related_function') }}" placeholder="Type to search functions...">
          </div>
          <div class="mb-3">
            <label for="related_authority_record" class="form-label">Related authority record</label>
            <input type="text" class="form-control" id="related_authority_record" name="related_authority_record" value="{{ old('related_authority_record') }}" placeholder="Type to search authority records...">
          </div>
          <div class="mb-3">
            <label for="related_resource" class="form-label">Related resource</label>
            <input type="text" class="form-control" id="related_resource" name="related_resource" value="{{ old('related_resource') }}" placeholder="Type to search archival descriptions...">
          </div>
        </div>
      </div>
    </div>

      {{-- Control area (ISDF 5.4) --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="control-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#control-collapse" aria-expanded="false" aria-controls="control-collapse">
            Control area
          </button>
        </h2>
        <div id="control-collapse" class="accordion-collapse collapse" aria-labelledby="control-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="description_identifier" class="form-label">Description identifier</label>
              <input type="text" name="description_identifier" id="description_identifier" class="form-control"
                     value="{{ old('description_identifier', $function->description_identifier ?? '') }}">
              <div class="form-text">"Record a unique description identifier in accordance with local and/or national conventions." (ISDF 5.4.1)</div>
            </div>

            <div class="mb-3">
              <label for="institution_identifier" class="form-label">Institution identifier</label>
              <textarea name="institution_identifier" id="institution_identifier" class="form-control" rows="4">{{ old('institution_identifier', $function->institution_identifier ?? '') }}</textarea>
              <div class="form-text">"Record the full authorised form of name(s) of the agency(ies) responsible for creating, modifying or disseminating the description." (ISDF 5.4.2)</div>
            </div>

            <div class="mb-3">
              <label for="rules" class="form-label">Rules and/or conventions used</label>
              <textarea name="rules" id="rules" class="form-control" rows="4">{{ old('rules', $function->rules ?? '') }}</textarea>
              <div class="form-text">"Record the names and where useful the editions or publication dates of the conventions or rules applied." (ISDF 5.4.3)</div>
            </div>

            <div class="mb-3">
              <label for="description_status_id" class="form-label">Status</label>
              <select name="description_status_id" id="description_status_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['descriptionStatuses'] as $status)
                  <option value="{{ $status->id }}" @selected(old('description_status_id', $function->description_status_id ?? '') == $status->id)>
                    {{ $status->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text">"Indicate the drafting status of the description." (ISDF 5.4.4)</div>
            </div>

            <div class="mb-3">
              <label for="description_detail_id" class="form-label">Level of detail</label>
              <select name="description_detail_id" id="description_detail_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['descriptionDetails'] as $detail)
                  <option value="{{ $detail->id }}" @selected(old('description_detail_id', $function->description_detail_id ?? '') == $detail->id)>
                    {{ $detail->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text">"Select Full, Partial or Minimal." (ISDF 5.4.5)</div>
            </div>

            <div class="mb-3">
              <label for="revision_history" class="form-label">Dates of creation, revision and deletion</label>
              <textarea name="revision_history" id="revision_history" class="form-control" rows="4">{{ old('revision_history', $function->revision_history ?? '') }}</textarea>
              <div class="form-text">"Record the date the description was created and the dates of any revisions to the description." (ISDF 5.4.6)</div>
            </div>

            @if($function && $function->updated_at)
              <div class="mb-3">
                <h3 class="fs-6 mb-2">Last updated</h3>
                <span class="text-muted">{{ $function->updated_at }}</span>
              </div>
            @endif

            <div class="mb-3">
              <label for="sources" class="form-label">Sources</label>
              <textarea name="sources" id="sources" class="form-control" rows="4">{{ old('sources', $function->sources ?? '') }}</textarea>
              <div class="form-text">"Record the sources consulted in establishing the description." (ISDF 5.4.8)</div>
            </div>

            <div class="mb-3">
              <label for="source_standard" class="form-label">Source standard</label>
              <input type="text" name="source_standard" id="source_standard" class="form-control"
                     value="{{ old('source_standard', $function->source_standard ?? '') }}">
            </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="language" class="form-label">Language(s)</label>
                <input type="text" class="form-control" id="language" name="language" value="{{ old('language', $function->language ?? '') }}" placeholder="e.g. English">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="script" class="form-label">Script(s)</label>
                <input type="text" class="form-control" id="script" name="script" value="{{ old('script', $function->script ?? '') }}" placeholder="e.g. Latin">
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label for="maintenance_notes" class="form-label">Maintenance notes</label>
            <textarea class="form-control" id="maintenance_notes" name="maintenance_notes" rows="3">{{ old('maintenance_notes', $function->maintenance_notes ?? '') }}</textarea>
          </div>
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        @if($function)
          <li><a href="{{ route('function.show', $function->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
          <li><a href="{{ route('function.confirmDelete', $function->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
        @else
          <li><a href="{{ route('function.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
        @endif
      </ul>
    </section>
  </form>

@endsection
