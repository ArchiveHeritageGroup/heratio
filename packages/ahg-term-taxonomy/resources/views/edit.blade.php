@extends('theme::layouts.1col')

@section('title', $term ? 'Term ' . $term->name : 'Term')
@section('body-class', 'edit term')

@section('content')

  <h1>{{ $term ? 'Term ' . $term->name : 'Term' }}</h1>

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
        action="{{ $term ? route('term.update', $term->slug) : route('term.store') }}"
        id="editForm">
    @csrf
    @if($term) @method('PUT') @endif

    <div class="accordion mb-3">
      {{-- ===== Elements area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="elements-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#elements-collapse" aria-expanded="false" aria-controls="elements-collapse">
            Elements area
          </button>
        </h2>
        <div id="elements-collapse" class="accordion-collapse collapse" aria-labelledby="elements-heading">
          <div class="accordion-body">

            {{-- Taxonomy --}}
            <div class="mb-3">
              @if($term)
                <label class="form-label">Taxonomy <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" value="{{ $taxonomyName }}" disabled>
              @else
                <label for="taxonomy_id" class="form-label">Taxonomy <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="taxonomy_id" id="taxonomy_id" class="form-control"
                       value="{{ old('taxonomy_id', $selectedTaxonomyId ?? '') }}" placeholder="Type to search taxonomies..." autocomplete="off">
              @endif
            </div>

            {{-- Name --}}
            <div class="mb-3">
              <label for="name" class="form-label">Name <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" name="name" id="name" class="form-control" required
                     value="{{ old('name', $term->name ?? '') }}"
                     @if($term && $term->is_protected) disabled @endif>
            </div>

            {{-- Use for --}}
            <div class="mb-3">
              <label for="use_for" class="form-label">Use for <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="use_for" id="use_for" class="form-control"
                     value="{{ old('use_for', $useFor ?? '') }}">
            </div>

            {{-- Code --}}
            <div class="mb-3">
              <label for="code" class="form-label">Code <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="code" id="code" class="form-control"
                     value="{{ old('code', $term->code ?? '') }}">
            </div>

            {{-- Scope note(s) - multi-row table --}}
            <h3 class="fs-6 mb-2">Scope note(s)</h3>
            <div class="table-responsive mb-2">
              <table class="table table-bordered mb-0" id="scope-notes-table">
                <thead class="table-light">
                  <tr>
                    <th id="scopeNotes-content-head" class="w-100">Content</th>
                    <th><span class="visually-hidden">Delete</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <input type="hidden" name="scopeNotes[0][type]" value="scope">
                      <textarea name="scopeNotes[0][content]" class="form-control form-control-sm" rows="2" aria-labelledby="scopeNotes-content-head">{{ old('scope_note', $scopeNote ?? '') }}</textarea>
                    </td>
                    <td>
                      <button type="button" class="btn atom-btn-white remove-scopenote-row">
                        <i class="fas fa-times" aria-hidden="true"></i>
                        <span class="visually-hidden">Delete row</span>
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="2">
                      <button type="button" class="btn atom-btn-white" id="add-scopenote-row">
                        <i class="fas fa-plus me-1" aria-hidden="true"></i>Add new
                      </button>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>

            {{-- Source note(s) - multi-row table --}}
            <h3 class="fs-6 mb-2">Source note(s)</h3>
            <div class="table-responsive mb-2">
              <table class="table table-bordered mb-0" id="source-notes-table">
                <thead class="table-light">
                  <tr>
                    <th id="sourceNotes-content-head" class="w-100">Content</th>
                    <th><span class="visually-hidden">Delete</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <input type="hidden" name="sourceNotes[0][type]" value="source">
                      <textarea name="sourceNotes[0][content]" class="form-control form-control-sm" rows="2" aria-labelledby="sourceNotes-content-head">{{ old('source_note', $sourceNote ?? '') }}</textarea>
                    </td>
                    <td>
                      <button type="button" class="btn atom-btn-white remove-sourcenote-row">
                        <i class="fas fa-times" aria-hidden="true"></i>
                        <span class="visually-hidden">Delete row</span>
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="2">
                      <button type="button" class="btn atom-btn-white" id="add-sourcenote-row">
                        <i class="fas fa-plus me-1" aria-hidden="true"></i>Add new
                      </button>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>

            {{-- Display note(s) - multi-row table --}}
            <h3 class="fs-6 mb-2">Display note(s)</h3>
            <div class="table-responsive mb-2">
              <table class="table table-bordered mb-0" id="display-notes-table">
                <thead class="table-light">
                  <tr>
                    <th id="displayNotes-content-head" class="w-100">Content</th>
                    <th><span class="visually-hidden">Delete</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <input type="hidden" name="displayNotes[0][type]" value="display">
                      <textarea name="displayNotes[0][content]" class="form-control form-control-sm" rows="2" aria-labelledby="displayNotes-content-head">{{ old('display_note', $displayNote ?? '') }}</textarea>
                    </td>
                    <td>
                      <button type="button" class="btn atom-btn-white remove-displaynote-row">
                        <i class="fas fa-times" aria-hidden="true"></i>
                        <span class="visually-hidden">Delete row</span>
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="2">
                      <button type="button" class="btn atom-btn-white" id="add-displaynote-row">
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

      {{-- ===== Relationships ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="relationships-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#relationships-collapse" aria-expanded="false" aria-controls="relationships-collapse">
            Relationships
          </button>
        </h2>
        <div id="relationships-collapse" class="accordion-collapse collapse" aria-labelledby="relationships-heading">
          <div class="accordion-body">

            {{-- Broad term (parent) --}}
            <div class="mb-3">
              <label for="parent_id" class="form-label">Broad term <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="parent_id" id="parent_id" class="form-control"
                     value="{{ old('parent_id', $parentTerm->name ?? '') }}" placeholder="Type to search terms..." autocomplete="off">
            </div>

            {{-- Related term(s) --}}
            <div class="mb-3">
              <label for="related_terms" class="form-label">Related term(s) <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="related_terms" id="related_terms" class="form-control"
                     value="{{ old('related_terms', $relatedTerms ?? '') }}" placeholder="Type to search terms..." autocomplete="off">
            </div>

            {{-- Converse term + Self-reciprocal --}}
            <div class="row">
              <div class="col-md-9">
                <div class="mb-3">
                  <label for="converse_term" class="form-label">Converse term <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" name="converse_term" id="converse_term" class="form-control"
                         value="{{ old('converse_term', $converseTerm->name ?? '') }}" placeholder="Type to search terms..." autocomplete="off">
                </div>
              </div>
              <div class="col-md-3 pb-md-2 d-flex align-items-end">
                <div class="mb-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="self_reciprocal" id="self_reciprocal" value="1"
                           @checked(old('self_reciprocal', ($converseTerm && $term && $converseTerm->id == $term->id) ? 1 : 0))>
                    <label class="form-check-label" for="self_reciprocal">Self-reciprocal <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                </div>
              </div>
            </div>

            {{-- Add new narrow terms --}}
            <div class="mb-3">
              <label for="narrow_terms" class="form-label">Add new narrow terms <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="narrow_terms" id="narrow_terms" class="form-control" rows="2">{{ old('narrow_terms', '') }}</textarea>
            </div>

          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($term)
        <li><a href="{{ route('term.show', $term->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @else
        @if($selectedTaxonomyId)
          <li><a href="{{ route('term.browse', ['taxonomy' => $selectedTaxonomyId]) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        @else
          <li><a href="{{ route('taxonomy.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        @endif
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
  // Multi-row note tables
  function setupNoteTable(tableId, addBtnId, removeBtnClass, namePrefix) {
    var idx = 1;
    document.getElementById(addBtnId)?.addEventListener('click', function() {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td><input type="hidden" name="' + namePrefix + '[' + idx + '][type]" value="' + namePrefix.replace('Notes', '').toLowerCase() + '">' +
        '<textarea name="' + namePrefix + '[' + idx + '][content]" class="form-control form-control-sm" rows="2"></textarea></td>' +
        '<td><button type="button" class="btn atom-btn-white ' + removeBtnClass + '"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Delete row</span></button></td>';
      document.querySelector('#' + tableId + ' tbody').appendChild(tr);
      idx++;
    });
  }

  setupNoteTable('scope-notes-table', 'add-scopenote-row', 'remove-scopenote-row', 'scopeNotes');
  setupNoteTable('source-notes-table', 'add-sourcenote-row', 'remove-sourcenote-row', 'sourceNotes');
  setupNoteTable('display-notes-table', 'add-displaynote-row', 'remove-displaynote-row', 'displayNotes');

  // Remove row handler
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.remove-scopenote-row, .remove-sourcenote-row, .remove-displaynote-row');
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
