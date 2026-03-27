@extends('theme::layouts.1col')

@section('title', ($function ? 'Edit' : 'Add new') . ' function - ISDF')

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      @if($function)
        Edit function - ISDF
      @else
        Add new function - ISDF
      @endif
    </h1>
    @if($function)
      <span class="small" id="heading-label">{{ $function->authorized_form_of_name }}</span>
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
        action="{{ $function ? route('function.update', $function->slug) : route('function.store') }}"
        id="editForm">
    @csrf

    <div class="accordion mb-3">

      {{-- ===== Identity area (ISDF 5.1) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="identity-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#identity-collapse" aria-expanded="false" aria-controls="identity-collapse">
            Identity area
          </button>
        </h2>
        <div id="identity-collapse" class="accordion-collapse collapse" aria-labelledby="identity-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="type_id" class="form-label">
                Type
                <span class="form-required" title="This is a mandatory element.">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <select name="type_id" id="type_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['functionTypes'] as $type)
                  <option value="{{ $type->id }}" @selected(old('type_id', $function->type_id ?? '') == $type->id)>
                    {{ $type->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text text-muted small">"Specify whether the description is a function or one of its subdivisions." (ISDF 5.1.1) Select the type from the drop-down menu; these values are drawn from the ISDF Function Types taxonomy.</div>
            </div>

            <div class="mb-3">
              <label for="authorized_form_of_name" class="form-label">
                Authorized form of name
                <span class="form-required" title="This is a mandatory element.">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" name="authorized_form_of_name" id="authorized_form_of_name" class="form-control" required
                     value="{{ old('authorized_form_of_name', $function->authorized_form_of_name ?? '') }}">
              <div class="form-text text-muted small">"Record the authorised name of the function being described. In cases where the name is not enough, add qualifiers to make it unique such as the territorial or administrative scope, or the name of the institution which performs it. This element is to be used in conjunction with the Function description identifier element (5.4.1)." (ISDF 5.1.2)</div>
            </div>

            <div class="mb-3">
              <label for="parallel_name" class="form-label">Parallel form(s) of name <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="parallel_name" id="parallel_name" class="form-control"
                     value="{{ old('parallel_name', $function->parallel_name ?? '') }}">
              <div class="form-text text-muted small">"Purpose: To indicate the various forms in which the authorized form(s) of name occurs in other languages or script forms. Rule: Record the parallel form(s) of name in accordance with any relevant national or international conventions or rules applied by the agency that created the description, including any necessary sub elements and/or qualifiers required by those conventions or rules. Specify in the Rules and/or conventions element (5.4.3.) which rules have been applied." (ISDF 5.1.3)</div>
            </div>

            <div class="mb-3">
              <label for="other_name" class="form-label">Other form(s) of name <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="other_name" id="other_name" class="form-control"
                     value="{{ old('other_name', $function->other_name ?? '') }}">
              <div class="form-text text-muted small">"Record any other names for the function being described." (ISDF 5.1.4)</div>
            </div>

            <div class="mb-3">
              <label for="classification" class="form-label">Classification <span class="badge bg-warning ms-1">Recommended</span></label>
              <input type="text" name="classification" id="classification" class="form-control"
                     value="{{ old('classification', $function->classification ?? '') }}">
              <div class="form-text text-muted small">"Record any term and/or code from a classification scheme of functions. Record the classification scheme used in the element Rules and/or conventions used (5.4.3)." (ISDF 5.1.5)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Context area (ISDF 5.2) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="context-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#context-collapse" aria-expanded="false" aria-controls="context-collapse">
            Context area
          </button>
        </h2>
        <div id="context-collapse" class="accordion-collapse collapse" aria-labelledby="context-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="dates" class="form-label">Dates <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="dates" id="dates" class="form-control"
                     value="{{ old('dates', $function->dates ?? '') }}">
              <div class="form-text text-muted small">"Provide a date or date span which covers the dates when the function was started and when it finished. If a function is ongoing, no end date is needed." (ISDF 5.2.1)</div>
            </div>

            <div class="mb-3">
              <label for="description" class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="description" id="description" class="form-control" rows="6">{{ old('description', $function->description ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record a narrative description of the purpose of the function." (ISDF 5.2.2)</div>
            </div>

            <div class="mb-3">
              <label for="history" class="form-label">History <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="history" id="history" class="form-control" rows="6">{{ old('history', $function->history ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record in narrative form or as a chronology the main events relating to the function." (ISDF 5.2.3)</div>
            </div>

            <div class="mb-3">
              <label for="legislation" class="form-label">Legislation <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="legislation" id="legislation" class="form-control" rows="6">{{ old('legislation', $function->legislation ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record any law, directive or charter which creates, changes or ends the function." (ISDF 5.2.4)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Relationships area (ISDF 5.3) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="relationships-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#relationships-collapse" aria-expanded="false" aria-controls="relationships-collapse">
            Relationships area
          </button>
        </h2>
        <div id="relationships-collapse" class="accordion-collapse collapse" aria-labelledby="relationships-heading">
          <div class="accordion-body">

            {{-- Related functions --}}
            <div class="mb-3">
              <label class="form-label fw-bold">Related function(s)</label>
              <table class="table table-bordered table-sm" id="related-functions-table">
                <thead><tr><th>Name</th><th>Identifier</th><th>Category</th><th>Description</th><th>Dates</th><th style="width:50px"></th></tr></thead>
                <tbody>
                  @if(isset($relatedFunctions) && count($relatedFunctions) > 0)
                    @foreach($relatedFunctions as $rf)
                      <tr>
                        <td><input type="text" name="related_functions[{{ $loop->index }}][name]" class="form-control form-control-sm" value="{{ $rf->name ?? '' }}"></td>
                        <td><input type="text" name="related_functions[{{ $loop->index }}][identifier]" class="form-control form-control-sm" value="{{ $rf->identifier ?? '' }}"></td>
                        <td><input type="text" name="related_functions[{{ $loop->index }}][category]" class="form-control form-control-sm" value="{{ $rf->category ?? '' }}"></td>
                        <td><input type="text" name="related_functions[{{ $loop->index }}][description]" class="form-control form-control-sm" value="{{ $rf->description ?? '' }}"></td>
                        <td><input type="text" name="related_functions[{{ $loop->index }}][dates]" class="form-control form-control-sm" value="{{ $rf->dates ?? '' }}"></td>
                        <td><button type="button" class="btn btn-sm atom-btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                      </tr>
                    @endforeach
                  @endif
                </tbody>
              </table>
              <button type="button" class="btn btn-sm atom-btn-white" onclick="addRelRow('related-functions-table', ['name','identifier','category','description','dates'])">Add related function</button>
            </div>

            {{-- Related authority records --}}
            <div class="mb-3">
              <label class="form-label fw-bold">Related authority record(s)</label>
              <table class="table table-bordered table-sm" id="related-actors-table">
                <thead><tr><th>Name</th><th>Identifier</th><th>Nature of relationship</th><th>Dates</th><th style="width:50px"></th></tr></thead>
                <tbody>
                  @if(isset($relatedActors) && count($relatedActors) > 0)
                    @foreach($relatedActors as $ra)
                      <tr>
                        <td><input type="text" name="related_actors[{{ $loop->index }}][name]" class="form-control form-control-sm" value="{{ $ra->name ?? '' }}"></td>
                        <td><input type="text" name="related_actors[{{ $loop->index }}][identifier]" class="form-control form-control-sm" value="{{ $ra->identifier ?? '' }}"></td>
                        <td><input type="text" name="related_actors[{{ $loop->index }}][nature]" class="form-control form-control-sm" value="{{ $ra->nature ?? '' }}"></td>
                        <td><input type="text" name="related_actors[{{ $loop->index }}][dates]" class="form-control form-control-sm" value="{{ $ra->dates ?? '' }}"></td>
                        <td><button type="button" class="btn btn-sm atom-btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                      </tr>
                    @endforeach
                  @endif
                </tbody>
              </table>
              <button type="button" class="btn btn-sm atom-btn-white" onclick="addRelRow('related-actors-table', ['name','identifier','nature','dates'])">Add related authority record</button>
            </div>

            {{-- Related resources --}}
            <div class="mb-3">
              <label class="form-label fw-bold">Related resource(s)</label>
              <table class="table table-bordered table-sm" id="related-resources-table">
                <thead><tr><th>Title</th><th>Identifier</th><th>Nature of relationship</th><th>Dates</th><th style="width:50px"></th></tr></thead>
                <tbody>
                  @if(isset($relatedResources) && count($relatedResources) > 0)
                    @foreach($relatedResources as $rr)
                      <tr>
                        <td><input type="text" name="related_resources[{{ $loop->index }}][title]" class="form-control form-control-sm" value="{{ $rr->title ?? '' }}"></td>
                        <td><input type="text" name="related_resources[{{ $loop->index }}][identifier]" class="form-control form-control-sm" value="{{ $rr->identifier ?? '' }}"></td>
                        <td><input type="text" name="related_resources[{{ $loop->index }}][nature]" class="form-control form-control-sm" value="{{ $rr->nature ?? '' }}"></td>
                        <td><input type="text" name="related_resources[{{ $loop->index }}][dates]" class="form-control form-control-sm" value="{{ $rr->dates ?? '' }}"></td>
                        <td><button type="button" class="btn btn-sm atom-btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                      </tr>
                    @endforeach
                  @endif
                </tbody>
              </table>
              <button type="button" class="btn btn-sm atom-btn-white" onclick="addRelRow('related-resources-table', ['title','identifier','nature','dates'])">Add related resource</button>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Control area (ISDF 5.4) ===== --}}
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
                Description identifier
                <span class="form-required" title="This is a mandatory element.">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" name="description_identifier" id="description_identifier" class="form-control"
                     value="{{ old('description_identifier', $function->description_identifier ?? '') }}">
              <div class="form-text text-muted small">"Record a unique description identifier in accordance with local and/or national conventions. If the description is to be used internationally, record the code of the country in which the description was created in accordance with the latest version of ISO 3166 Codes for the representation of names of countries. Where the creator of the description is an international organisation, give the organisational identifier in place of the country code." (ISDF 5.4.1)</div>
            </div>

            <div class="mb-3">
              <label for="institution_identifier" class="form-label">Institution identifier <span class="badge bg-warning ms-1">Recommended</span></label>
              <textarea name="institution_identifier" id="institution_identifier" class="form-control" rows="4">{{ old('institution_identifier', $function->institution_identifier ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record the full authorised form of name(s) of agency(ies) responsible for creating, modifying or disseminating the description or, alternatively, record a recognized code for the agency." (ISDF 5.4.2)</div>
            </div>

            <div class="mb-3">
              <label for="rules" class="form-label">Rules and/or conventions used <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="rules" id="rules" class="form-control" rows="4">{{ old('rules', $function->rules ?? '') }}</textarea>
              <div class="form-text text-muted small">"Purpose: To identify the national or international conventions or rules applied in creating the archival description. Rule: Record the names and where useful the editions or publication dates of the conventions or rules applied." (ISDF 5.4.3)</div>
            </div>

            <div class="mb-3">
              <label for="description_status_id" class="form-label">Status <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="description_status_id" id="description_status_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['descriptionStatuses'] as $status)
                  <option value="{{ $status->id }}" @selected(old('description_status_id', $function->description_status_id ?? '') == $status->id)>
                    {{ $status->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text text-muted small">The purpose of this field is "[t]o indicate the drafting status of the description so that users can understand the current status of the description." (ISDF 5.4.4). Select Final, Revised or Draft from the drop-down menu.</div>
            </div>

            <div class="mb-3">
              <label for="description_detail_id" class="form-label">Level of detail <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="description_detail_id" id="description_detail_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['descriptionDetails'] as $detail)
                  <option value="{{ $detail->id }}" @selected(old('description_detail_id', $function->description_detail_id ?? '') == $detail->id)>
                    {{ $detail->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Select Full, Partial or Minimal from the drop-down menu. "In the absence of national guidelines or rules, minimum records are those that consist only of the three essential elements of an ISDF compliant record (see 4.7), while full records are those that convey information for all relevant ISDF elements of description." (ISDF 5.4.5)</div>
            </div>

            <div class="mb-3">
              <label for="revision_history" class="form-label">Dates of creation, revision or deletion <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="revision_history" id="revision_history" class="form-control" rows="4">{{ old('revision_history', $function->revision_history ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record the date the description was created and the dates of any revisions to the description." (ISDF 5.4.6)</div>
            </div>

            <div class="mb-3">
              <label for="language" class="form-label">Language(s) <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" id="language" name="language"
                     value="{{ old('language', $function->language ?? '') }}" placeholder="e.g. English">
              <div class="form-text text-muted small">Select the language(s) of this record from the drop-down menu; enter the first few letters to narrow the choices. (ISDF 5.4.7)</div>
            </div>

            <div class="mb-3">
              <label for="script" class="form-label">Script(s) <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" id="script" name="script"
                     value="{{ old('script', $function->script ?? '') }}" placeholder="e.g. Latin">
              <div class="form-text text-muted small">Select the script(s) of this record from the drop-down menu; enter the first few letters to narrow the choices. (ISDF 5.4.7)</div>
            </div>

            <div class="mb-3">
              <label for="sources" class="form-label">Sources <span class="badge bg-warning ms-1">Recommended</span></label>
              <textarea name="sources" id="sources" class="form-control" rows="4">{{ old('sources', $function->sources ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record the sources consulted in establishing the function description." (ISDF 5.4.8)</div>
            </div>

            <div class="mb-3">
              <label for="maintenance_notes" class="form-label">Maintenance notes <span class="badge bg-warning ms-1">Recommended</span></label>
              <textarea name="maintenance_notes" id="maintenance_notes" class="form-control" rows="3">{{ old('maintenance_notes', $function->maintenance_notes ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record notes pertinent to the creation and maintenance of the description." (ISDF 5.4.9)</div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($function)
        <li><a href="{{ route('function.show', $function->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a href="{{ route('function.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
      @endif
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
  // Related functions multi-row
  var relFuncIdx = 1;
  document.getElementById('add-relfunc-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="text" name="relatedFunctions[' + relFuncIdx + '][name]" class="form-control form-control-sm" placeholder="Type to search..."></td>' +
      '<td><input type="text" name="relatedFunctions[' + relFuncIdx + '][identifier]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="relatedFunctions[' + relFuncIdx + '][category]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="relatedFunctions[' + relFuncIdx + '][description]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="relatedFunctions[' + relFuncIdx + '][dates]" class="form-control form-control-sm"></td>' +
      '<td><button type="button" class="btn atom-btn-white remove-relfunc-row"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Delete row</span></button></td>';
    document.querySelector('#related-functions-table tbody').appendChild(tr);
    relFuncIdx++;
  });

  // Related authority records multi-row
  var relActorIdx = 1;
  document.getElementById('add-relactor-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="text" name="relatedActors[' + relActorIdx + '][name]" class="form-control form-control-sm" placeholder="Type to search..."></td>' +
      '<td><input type="text" name="relatedActors[' + relActorIdx + '][identifier]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="relatedActors[' + relActorIdx + '][nature]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="relatedActors[' + relActorIdx + '][dates]" class="form-control form-control-sm"></td>' +
      '<td><button type="button" class="btn atom-btn-white remove-relactor-row"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Delete row</span></button></td>';
    document.querySelector('#related-actors-table tbody').appendChild(tr);
    relActorIdx++;
  });

  // Related resources multi-row
  var relResIdx = 1;
  document.getElementById('add-relresource-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input type="text" name="relatedResources[' + relResIdx + '][title]" class="form-control form-control-sm" placeholder="Type to search..."></td>' +
      '<td><input type="text" name="relatedResources[' + relResIdx + '][identifier]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="relatedResources[' + relResIdx + '][nature]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="relatedResources[' + relResIdx + '][dates]" class="form-control form-control-sm"></td>' +
      '<td><button type="button" class="btn atom-btn-white remove-relresource-row"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Delete row</span></button></td>';
    document.querySelector('#related-resources-table tbody').appendChild(tr);
    relResIdx++;
  });

  // Remove row handler
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.remove-relfunc-row, .remove-relactor-row, .remove-relresource-row');
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
