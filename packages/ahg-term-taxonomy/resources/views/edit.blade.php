@extends('theme::layouts.1col')

@section('title', $term ? 'Term ' . $term->name : 'New term')
@section('body-class', 'edit term')

@section('content')

  <h1>{{ $term ? 'Term ' . $term->name : 'Term' }}</h1>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div> @endif
  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li> @endforeach</ul></div>
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
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#elements-collapse" aria-expanded="true">
            Elements area
          </button>
        </h2>
        <div id="elements-collapse" class="accordion-collapse collapse show" aria-labelledby="elements-heading">
          <div class="accordion-body">

            {{-- Taxonomy --}}
            <div class="mb-3">
              @if($term)
                <label class="form-label">Taxonomy</label>
                <input type="text" class="form-control" value="{{ $taxonomyName }}" disabled>
              @else
                <label for="taxonomy_id" class="form-label">
                  Taxonomy <span class="text-danger">*</span>
                </label>
                <select name="taxonomy_id" id="taxonomy_id" class="form-select" required>
                  <option value="">-- Select --</option>
                  @foreach($taxonomies as $taxonomy)
                    <option value="{{ $taxonomy->id }}" @selected(old('taxonomy_id', $selectedTaxonomyId ?? '') == $taxonomy->id)>
                      {{ $taxonomy->name }}
                    </option>
                  @endforeach
                </select>
              @endif
            </div>

            {{-- Name --}}
            <div class="mb-3">
              <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="name" class="form-control" required
                     value="{{ old('name', $term->name ?? '') }}">
            </div>

            {{-- Use for --}}
            <div class="mb-3">
              <label for="use_for" class="form-label">Use for</label>
              <input type="text" name="use_for" id="use_for" class="form-control"
                     value="{{ old('use_for', $useFor ?? '') }}"
                     placeholder="Comma-separated alternative labels">
            </div>

            {{-- Code --}}
            <div class="mb-3">
              <label for="code" class="form-label">Code</label>
              <input type="text" name="code" id="code" class="form-control"
                     value="{{ old('code', $term->code ?? '') }}">
            </div>

            {{-- Scope note --}}
            <div class="mb-3">
              <label for="scope_note" class="form-label">Scope note(s)</label>
              <textarea name="scope_note" id="scope_note" class="form-control" rows="3">{{ old('scope_note', $scopeNote ?? '') }}</textarea>
            </div>

            {{-- Source note --}}
            <div class="mb-3">
              <label for="source_note" class="form-label">Source note(s)</label>
              <textarea name="source_note" id="source_note" class="form-control" rows="3">{{ old('source_note', $sourceNote ?? '') }}</textarea>
            </div>

            {{-- Display note --}}
            <div class="mb-3">
              <label for="display_note" class="form-label">Display note(s)</label>
              <textarea name="display_note" id="display_note" class="form-control" rows="3">{{ old('display_note', $displayNote ?? '') }}</textarea>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Relationships ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="relationships-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#relationships-collapse" aria-expanded="false">
            Relationships
          </button>
        </h2>
        <div id="relationships-collapse" class="accordion-collapse collapse" aria-labelledby="relationships-heading">
          <div class="accordion-body">

            {{-- Broad term (parent) --}}
            <div class="mb-3">
              <label for="parent_id" class="form-label">Broad term</label>
              <select name="parent_id" id="parent_id" class="form-select">
                <option value="">-- None --</option>
                @if(isset($termsForAutocomplete))
                  @foreach($termsForAutocomplete as $t)
                    @if(!$term || $t->id != $term->id)
                      <option value="{{ $t->id }}" @selected(old('parent_id', ($parentTerm->id ?? '')) == $t->id)>
                        {{ $t->name }}
                      </option>
                    @endif
                  @endforeach
                @endif
              </select>
            </div>

            {{-- Related term(s) --}}
            <div class="mb-3">
              <label for="related_terms" class="form-label">Related term(s)</label>
              <select name="related_terms[]" id="related_terms" class="form-select" multiple size="4">
                @if(isset($termsForAutocomplete))
                  @foreach($termsForAutocomplete as $t)
                    @if(!$term || $t->id != $term->id)
                      <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endif
                  @endforeach
                @endif
              </select>
              <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
            </div>

            {{-- Converse term + Self-reciprocal --}}
            <div class="row">
              <div class="col-md-9 mb-3">
                <label for="converse_term" class="form-label">Converse term</label>
                <select name="converse_term" id="converse_term" class="form-select">
                  <option value="">-- None --</option>
                  @if(isset($termsForAutocomplete))
                    @foreach($termsForAutocomplete as $t)
                      @if(!$term || $t->id != $term->id)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                      @endif
                    @endforeach
                  @endif
                </select>
              </div>
              <div class="col-md-3 mb-3 d-flex align-items-end pb-2">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="self_reciprocal" id="self_reciprocal" value="1">
                  <label class="form-check-label" for="self_reciprocal">Self-reciprocal</label>
                </div>
              </div>
            </div>

            {{-- Add new narrow terms --}}
            <div class="mb-3">
              <label for="narrow_terms" class="form-label">Add new narrow terms</label>
              <textarea name="narrow_terms" id="narrow_terms" class="form-control" rows="2" placeholder="One term per line">{{ old('narrow_terms', '') }}</textarea>
              <small class="text-muted">Enter one term name per line to create as narrower terms</small>
            </div>

          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        @if($term)
          <li><a href="{{ route('term.show', $term->slug) }}" class="btn atom-btn-outline-light">Cancel</a></li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
          <li><a href="{{ route('term.confirmDelete', $term->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
        @else
          <li>
            @if($selectedTaxonomyId)
              <a href="{{ route('term.browse', ['taxonomy' => $selectedTaxonomyId]) }}" class="btn atom-btn-outline-light">Cancel</a>
            @else
              <a href="{{ route('taxonomy.browse') }}" class="btn atom-btn-outline-light">Cancel</a>
            @endif
          </li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
        @endif
      </ul>
    </section>

  </form>

@endsection
