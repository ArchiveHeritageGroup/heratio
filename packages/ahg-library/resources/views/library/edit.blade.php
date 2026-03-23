@extends('theme::layouts.1col')

@section('title', $item ? 'Edit ' . ($item->title ?? '') : 'Add new library item')

@section('content')
  <h1>{{ $item ? 'Edit ' . ($item->title ?? '') : 'Add new library item' }}</h1>

  <form method="POST"
        action="{{ $item ? route('library.update', $item->slug) : route('library.store') }}"
        id="library-form">
    @csrf
    @if($item)
      @method('PUT')
    @endif
    @if(request('parent'))
      <input type="hidden" name="parent" value="{{ request('parent') }}">
    @endif

    <div class="row">
      {{-- ==================== LEFT COLUMN (col-md-8) ==================== --}}
      <div class="col-md-8">

        {{-- ===== Basic Information ===== --}}
        <section class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-book me-2"></i>Basic Information</h5>
          </div>
          <div class="card-body">

            <div class="mb-3">
              <label for="title" class="form-label required">Title <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                     value="{{ old('title', $item->title ?? '') }}" required>
              @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text">The title of the library item, as it appears on the title page or equivalent.</div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="subtitle" class="form-label">Subtitle <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="subtitle" id="subtitle" class="form-control @error('subtitle') is-invalid @enderror"
                       value="{{ old('subtitle', $item->subtitle ?? '') }}">
                @error('subtitle') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6 mb-3">
                <label for="identifier" class="form-label">Identifier <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="identifier" id="identifier" class="form-control @error('identifier') is-invalid @enderror"
                       value="{{ old('identifier', $item->identifier ?? '') }}">
                @error('identifier') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="mb-3">
              <label for="responsibility_statement" class="form-label">Statement of responsibility <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="responsibility_statement" id="responsibility_statement" class="form-control @error('responsibility_statement') is-invalid @enderror"
                     value="{{ old('responsibility_statement', $item->responsibility_statement ?? '') }}"
                     placeholder="e.g. by John Smith ; edited by Jane Doe">
              @error('responsibility_statement') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text">Names and roles as they appear on the item</div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="level_of_description_id" class="form-label required">Level of description <span class="badge bg-danger ms-1">Required</span></label>
                <select name="level_of_description_id" id="level_of_description_id" class="form-select" required>
                  <option value="">-- Select --</option>
                  @foreach($formChoices['levels'] as $level)
                    <option value="{{ $level->id }}" @selected(old('level_of_description_id', $item->level_of_description_id ?? '') == $level->id)>{{ $level->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4 mb-3">
                <label for="material_type" class="form-label">Material type <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="material_type" id="material_type" class="form-select">
                  <option value="">-- Select --</option>
                  @foreach($formChoices['materialTypes'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('material_type', $item->material_type ?? '') == $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4 mb-3">
                <label for="language" class="form-label">Language <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="language" id="language" class="form-select">
                  <option value="">-- Select --</option>
                  @foreach($formChoices['languages'] as $code => $name)
                    <option value="{{ $code }}" @selected(old('language', $item->language ?? '') === $code)>{{ $name }}</option>
                  @endforeach
                </select>
              </div>
            </div>

          </div>
        </section>

        {{-- ===== Creators / Authors ===== --}}
        <section class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Creators / Authors</h5>
            <button type="button" class="btn btn-sm atom-btn-white" id="add-creator-btn">
              <i class="fas fa-plus me-1"></i>Add
            </button>
          </div>
          <div class="card-body">
            <div id="creators-container">
              @if($creators->count())
                @foreach($creators as $i => $creator)
                  <div class="row creator-row mb-2 align-items-center" data-index="{{ $i }}">
                    <div class="col-md-5">
                      <input type="text" name="creators[{{ $i }}][name]" class="form-control form-control-sm"
                             placeholder="Name" value="{{ old("creators.{$i}.name", $creator->name ?? '') }}">
                    </div>
                    <div class="col-md-3">
                      <select name="creators[{{ $i }}][role]" class="form-select form-select-sm">
                        @foreach($formChoices['creatorRoles'] as $code => $label)
                          <option value="{{ $code }}" @selected(old("creators.{$i}.role", $creator->role ?? 'author') === $code)>{{ $label }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="col-md-3">
                      <input type="text" name="creators[{{ $i }}][authority_uri]" class="form-control form-control-sm"
                             placeholder="Authority URI" value="{{ old("creators.{$i}.authority_uri", '') }}">
                    </div>
                    <div class="col-md-1">
                      <button type="button" class="btn btn-sm atom-btn-outline-danger remove-creator-btn w-100">
                        <i class="fas fa-times"></i>
                      </button>
                    </div>
                  </div>
                @endforeach
              @endif
            </div>
            @if($creators->isEmpty())
              <p class="text-muted small mb-0" id="no-creators-msg">No creators added. Click "Add" or use ISBN lookup.</p>
            @endif
          </div>
        </section>

        {{-- ===== Standard Identifiers ===== --}}
        <section class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-barcode me-2"></i>Standard Identifiers</h5>
          </div>
          <div class="card-body">

            <div class="row">
              <div class="col-md-5 mb-3">
                <label class="form-label">ISBN <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="input-group">
                  <input type="text" name="isbn" id="isbn-input" class="form-control @error('isbn') is-invalid @enderror"
                         value="{{ old('isbn', $item->isbn ?? '') }}" placeholder="978-0-123456-78-9">
                  <button type="button" class="btn atom-btn-white" id="isbn-lookup" title="Lookup ISBN and auto-fill form">
                    <i class="fas fa-search me-1"></i>Lookup
                  </button>
                </div>
                @error('isbn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Enter ISBN and click Lookup to auto-fill</div>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">ISSN <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="issn" class="form-control @error('issn') is-invalid @enderror"
                       value="{{ old('issn', $item->issn ?? '') }}" placeholder="1234-5678">
                @error('issn') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">DOI <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="doi" class="form-control @error('doi') is-invalid @enderror"
                       value="{{ old('doi', $item->doi ?? '') }}" placeholder="10.1000/xyz123">
                @error('doi') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="row">
              <div class="col-md-3 mb-3">
                <label class="form-label">LCCN <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="lccn" class="form-control @error('lccn') is-invalid @enderror"
                       value="{{ old('lccn', $item->lccn ?? '') }}">
                @error('lccn') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">OCLC Number <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="oclc_number" class="form-control @error('oclc_number') is-invalid @enderror"
                       value="{{ old('oclc_number', $item->oclc_number ?? '') }}">
                @error('oclc_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Barcode <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="barcode" class="form-control @error('barcode') is-invalid @enderror"
                       value="{{ old('barcode', $item->barcode ?? '') }}">
                @error('barcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">Open Library ID <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="openlibrary_id" class="form-control @error('openlibrary_id') is-invalid @enderror"
                       value="{{ old('openlibrary_id', $item->openlibrary_id ?? '') }}" placeholder="OL12345M">
                @error('openlibrary_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Goodreads ID <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="goodreads_id" class="form-control @error('goodreads_id') is-invalid @enderror"
                       value="{{ old('goodreads_id', $item->goodreads_id ?? '') }}">
                @error('goodreads_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">LibraryThing ID <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="librarything_id" class="form-control @error('librarything_id') is-invalid @enderror"
                       value="{{ old('librarything_id', $item->librarything_id ?? '') }}">
                @error('librarything_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Open Library URL <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="input-group">
                  <input type="text" name="openlibrary_url" id="openlibrary_url" class="form-control @error('openlibrary_url') is-invalid @enderror"
                         value="{{ old('openlibrary_url', $item->openlibrary_url ?? '') }}">
                  @if(!empty($item->openlibrary_url))
                    <a href="{{ $item->openlibrary_url }}" target="_blank" class="btn atom-btn-white">
                      <i class="fas fa-external-link-alt"></i>
                    </a>
                  @endif
                </div>
                @error('openlibrary_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

          </div>
        </section>

        {{-- ===== Classification ===== --}}
        <section class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Classification</h5>
          </div>
          <div class="card-body">

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Classification scheme <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="classification_scheme" id="classification_scheme" class="form-select">
                  <option value="">-- Select --</option>
                  @foreach($formChoices['classificationSchemes'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('classification_scheme', $item->classification_scheme ?? '') == $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4 mb-3">
                <label for="call_number" class="form-label">Call number (LC) <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="call_number" id="call_number" class="form-control @error('call_number') is-invalid @enderror"
                       value="{{ old('call_number', $item->call_number ?? '') }}" placeholder="e.g. QA76.73.J38">
                @error('call_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4 mb-3">
                <label for="dewey_decimal" class="form-label">Dewey Decimal <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="dewey_decimal" id="dewey_decimal" class="form-control @error('dewey_decimal') is-invalid @enderror"
                       value="{{ old('dewey_decimal', $item->dewey_decimal ?? '') }}" placeholder="e.g. 005.133">
                @error('dewey_decimal') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="shelf_location" class="form-label">Shelf location <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="shelf_location" id="shelf_location" class="form-control @error('shelf_location') is-invalid @enderror"
                       value="{{ old('shelf_location', $item->shelf_location ?? '') }}" placeholder="e.g. Main Library, Floor 2, Section A">
                @error('shelf_location') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3 mb-3">
                <label for="copy_number" class="form-label">Copy number <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="copy_number" id="copy_number" class="form-control @error('copy_number') is-invalid @enderror"
                       value="{{ old('copy_number', $item->copy_number ?? '') }}">
                @error('copy_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3 mb-3">
                <label for="volume_designation" class="form-label">Volume <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="volume_designation" id="volume_designation" class="form-control @error('volume_designation') is-invalid @enderror"
                       value="{{ old('volume_designation', $item->volume_designation ?? '') }}">
                @error('volume_designation') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

          </div>
        </section>

        {{-- ===== Publication Information ===== --}}
        <section class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-building me-2"></i>Publication Information</h5>
          </div>
          <div class="card-body">

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="publisher" class="form-label">Publisher <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="publisher" id="publisher" class="form-control @error('publisher') is-invalid @enderror"
                       value="{{ old('publisher', $item->publisher ?? '') }}">
                @error('publisher') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6 mb-3">
                <label for="publication_place" class="form-label">Place of publication <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="publication_place" id="publication_place" class="form-control @error('publication_place') is-invalid @enderror"
                       value="{{ old('publication_place', $item->publication_place ?? '') }}">
                @error('publication_place') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="row">
              <div class="col-md-3 mb-3">
                <label for="publication_date" class="form-label">Publication date <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="publication_date" id="publication_date" class="form-control @error('publication_date') is-invalid @enderror"
                       value="{{ old('publication_date', $item->publication_date ?? '') }}" placeholder="e.g. 2023">
                @error('publication_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3 mb-3">
                <label for="edition" class="form-label">Edition <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="edition" id="edition" class="form-control @error('edition') is-invalid @enderror"
                       value="{{ old('edition', $item->edition ?? '') }}" placeholder="e.g. 3rd ed.">
                @error('edition') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6 mb-3">
                <label for="edition_statement" class="form-label">Edition statement <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="edition_statement" id="edition_statement" class="form-control @error('edition_statement') is-invalid @enderror"
                       value="{{ old('edition_statement', $item->edition_statement ?? '') }}" placeholder="e.g. Revised and expanded">
                @error('edition_statement') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="row">
              <div class="col-md-8 mb-3">
                <label for="series_title" class="form-label">Series title <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="series_title" id="series_title" class="form-control @error('series_title') is-invalid @enderror"
                       value="{{ old('series_title', $item->series_title ?? '') }}">
                @error('series_title') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4 mb-3">
                <label for="series_number" class="form-label">Series number <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="series_number" id="series_number" class="form-control @error('series_number') is-invalid @enderror"
                       value="{{ old('series_number', $item->series_number ?? '') }}" placeholder="e.g. vol. 3">
                @error('series_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

          </div>
        </section>

        {{-- ===== Physical Description ===== --}}
        <section class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-ruler me-2"></i>Physical Description</h5>
          </div>
          <div class="card-body">

            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="pagination" class="form-label">Pages <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="pagination" id="pagination" class="form-control @error('pagination') is-invalid @enderror"
                       value="{{ old('pagination', $item->pagination ?? '') }}" placeholder="e.g. xiv, 350 p.">
                @error('pagination') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4 mb-3">
                <label for="dimensions" class="form-label">Dimensions <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="dimensions" id="dimensions" class="form-control @error('dimensions') is-invalid @enderror"
                       value="{{ old('dimensions', $item->dimensions ?? '') }}" placeholder="e.g. 24 cm">
                @error('dimensions') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4 mb-3">
                <label for="physical_details" class="form-label">Physical details <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="physical_details" id="physical_details" class="form-control @error('physical_details') is-invalid @enderror"
                       value="{{ old('physical_details', $item->physical_details ?? '') }}" placeholder="e.g. ill., maps">
                @error('physical_details') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

          </div>
        </section>

        {{-- ===== Subjects ===== --}}
        <section class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Subjects</h5>
            <div class="btn-group">
              <button type="button" class="btn btn-sm atom-btn-white" id="suggest-subjects-btn" title="Get AI-powered subject suggestions">
                <i class="fas fa-magic me-1"></i>Suggest
              </button>
              <button type="button" class="btn btn-sm atom-btn-white" id="add-subject-btn">
                <i class="fas fa-plus me-1"></i>Add
              </button>
            </div>
          </div>
          <div class="card-body">
            <div id="subjects-container">
              @if($subjects->count())
                @foreach($subjects as $i => $subject)
                  <div class="row subject-row mb-2 align-items-center" data-index="{{ $i }}">
                    <div class="col-md-11">
                      <input type="text" name="subjects[{{ $i }}][heading]" class="form-control form-control-sm"
                             placeholder="Subject heading" value="{{ old("subjects.{$i}.heading", $subject->name ?? '') }}">
                    </div>
                    <div class="col-md-1">
                      <button type="button" class="btn btn-sm atom-btn-outline-danger remove-subject-btn w-100">
                        <i class="fas fa-times"></i>
                      </button>
                    </div>
                  </div>
                @endforeach
              @endif
            </div>
            @if($subjects->isEmpty())
              <p class="text-muted small mb-0" id="no-subjects-msg">No subjects added. Click "Add" or use ISBN lookup.</p>
            @endif
          </div>
        </section>

        {{-- ===== Content ===== --}}
        <section class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-align-left me-2"></i>Content</h5>
          </div>
          <div class="card-body">

            <div class="mb-3">
              <label for="summary" class="form-label">Summary / Abstract <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="summary" id="summary" class="form-control" rows="4">{{ old('summary', $item->summary ?? '') }}</textarea>
            </div>

            <div class="mb-3">
              <label for="scope_and_content" class="form-label">Scope and content <span class="badge bg-warning ms-1">Recommended</span></label>
              <textarea name="scope_and_content" id="scope_and_content" class="form-control" rows="3">{{ old('scope_and_content', $item->scope_and_content ?? '') }}</textarea>
            </div>

            <div class="mb-3">
              <label for="contents_note" class="form-label">Table of contents <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="contents_note" id="contents_note" class="form-control" rows="3"
                        placeholder="Chapter listing or table of contents">{{ old('contents_note', $item->contents_note ?? '') }}</textarea>
            </div>

          </div>
        </section>

        {{-- ===== Notes ===== --}}
        <section class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes</h5>
          </div>
          <div class="card-body">

            <div class="mb-3">
              <label for="general_note" class="form-label">General note <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="general_note" id="general_note" class="form-control" rows="2">{{ old('general_note', $item->general_note ?? '') }}</textarea>
            </div>

            <div class="mb-3">
              <label for="bibliography_note" class="form-label">Bibliography note <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="bibliography_note" id="bibliography_note" class="form-control" rows="2"
                        placeholder="e.g. Includes bibliographical references and index">{{ old('bibliography_note', $item->bibliography_note ?? '') }}</textarea>
            </div>

          </div>
        </section>

      </div>

      {{-- ==================== RIGHT COLUMN (col-md-4) ==================== --}}
      <div class="col-md-4">

        {{-- ===== Actions ===== --}}
        <section class="card mb-4 sticky-top" style="top: 1rem; z-index: 100;">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-save me-2"></i>Actions</h5>
          </div>
          <div class="card-body">
            <button type="submit" class="btn atom-btn-outline-success w-100 mb-2">
              <i class="fas fa-save me-2"></i>{{ $item ? 'Save' : 'Create' }}
            </button>

            @if($item)
              <a href="{{ route('library.show', $item->slug) }}" class="btn atom-btn-white w-100 mb-2">
                <i class="fas fa-times me-2"></i>Cancel
              </a>
            @else
              <a href="{{ route('library.browse') }}" class="btn atom-btn-white w-100 mb-2">
                <i class="fas fa-times me-2"></i>Cancel
              </a>
            @endif
          </div>
        </section>

        {{-- ===== Cover / Digital Object ===== --}}
        <section class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-image me-2"></i>Cover / Digital Object</h5>
          </div>
          <div class="card-body text-center">
            @php
              $isbn = $item->isbn ?? '';
              $cleanIsbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn));
            @endphp

            <div id="cover-preview">
              @if(!empty($item->cover_url))
                <img src="{{ $item->cover_url }}" alt="Cover" class="img-fluid rounded shadow-sm mb-2" style="max-height: 200px;">
                <div class="mt-1"><small class="text-muted">Current cover</small></div>
              @elseif(!empty($cleanIsbn))
                <img src="/library/cover/{{ $cleanIsbn }}" alt="Cover" class="img-fluid rounded shadow-sm mb-2" style="max-height: 200px;"
                     onerror="this.parentElement.innerHTML='<p class=\'text-muted\'>No Open Library cover found</p>'">
                <div class="mt-1"><small class="text-muted">Open Library Preview</small></div>
                <div class="mt-1"><small class="text-success"><i class="fas fa-info-circle me-1"></i>Will be saved on save</small></div>
              @elseif($item)
                <p class="text-muted fst-italic mb-2">Enter ISBN to preview Open Library cover</p>
                <a href="{{ route('library.show', $item->slug) }}" class="btn btn-sm atom-btn-outline-success">
                  <i class="fas fa-upload me-1"></i>Upload cover
                </a>
              @else
                <p class="text-muted fst-italic mb-0">Save record first to upload cover</p>
              @endif
            </div>

            <input type="hidden" name="cover_url" id="cover-url-input" value="{{ old('cover_url', $item->cover_url ?? '') }}">
            <input type="hidden" name="ebook_preview_url" id="ebook-preview-url" value="{{ old('ebook_preview_url', $item->ebook_preview_url ?? '') }}">
          </div>
        </section>

        @if(!empty($item->ebook_preview_url ?? old('ebook_preview_url')))
          <section class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
              <h5 class="mb-0"><i class="fas fa-tablet-alt me-2"></i>E-book Access</h5>
            </div>
            <div class="card-body">
              <a href="{{ $item->ebook_preview_url ?? old('ebook_preview_url') }}" target="_blank" class="btn atom-btn-white w-100">
                <i class="fas fa-book-reader me-2"></i>Preview on Archive.org
              </a>
            </div>
          </section>
        @endif

        {{-- ===== Item Physical Location ===== --}}
        @auth
        <section class="card mb-4">
          <div class="card-header" style="background-color: var(--ahg-primary, #005837); color: #fff;">
            <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Item Physical Location</h5>
          </div>
          <div class="card-body">

            {{-- Container Link --}}
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Storage container <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="item_physical_object_id" class="form-select">
                  <option value="">-- Select container --</option>
                  @foreach($formChoices['physicalObjects'] as $poId => $poName)
                    <option value="{{ $poId }}" @selected(($itemLocation['physical_object_id'] ?? '') == $poId)>{{ $poName }}</option>
                  @endforeach
                </select>
                <div class="form-text">Link to a physical storage container</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Item barcode <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="item_barcode" class="form-control" value="{{ $itemLocation['barcode'] ?? '' }}">
              </div>
            </div>

            {{-- Location within container --}}
            <h6 class="text-white py-2 px-3 mb-3" style="background-color: var(--ahg-primary, #005837);"><i class="fas fa-box me-2"></i>Location within container</h6>
            <div class="row mb-3">
              <div class="col-md-2">
                <label class="form-label">Box <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="item_box_number" class="form-control" value="{{ $itemLocation['box_number'] ?? '' }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Folder <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="item_folder_number" class="form-control" value="{{ $itemLocation['folder_number'] ?? '' }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Shelf <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="item_shelf" class="form-control" value="{{ $itemLocation['shelf'] ?? '' }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Row <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="item_row" class="form-control" value="{{ $itemLocation['row'] ?? '' }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Position <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="item_position" class="form-control" value="{{ $itemLocation['position'] ?? '' }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Item # <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="item_item_number" class="form-control" value="{{ $itemLocation['item_number'] ?? '' }}">
              </div>
            </div>

            {{-- Extent --}}
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Extent value <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="number" step="0.01" name="item_extent_value" class="form-control" value="{{ $itemLocation['extent_value'] ?? '' }}">
              </div>
              <div class="col-md-6">
                <label class="form-label">Extent unit <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="item_extent_unit" class="form-select">
                  <option value="">-- Select --</option>
                  @foreach(['items' => 'Items', 'pages' => 'Pages', 'folders' => 'Folders', 'boxes' => 'Boxes', 'cm' => 'cm', 'm' => 'metres', 'cubic_m' => 'cubic metres'] as $val => $label)
                    <option value="{{ $val }}" @selected(($itemLocation['extent_unit'] ?? '') == $val)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            {{-- Condition & Status --}}
            <h6 class="text-white py-2 px-3 mb-3" style="background-color: var(--ahg-primary, #005837);"><i class="fas fa-clipboard-check me-2"></i>Condition &amp; Status</h6>
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Condition <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="item_condition_status" class="form-select">
                  <option value="">-- Select --</option>
                  @foreach(['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor', 'critical' => 'Critical'] as $val => $label)
                    <option value="{{ $val }}" @selected(($itemLocation['condition_status'] ?? '') == $val)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Access status <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="item_access_status" class="form-select">
                  @foreach(['available' => 'Available', 'in_use' => 'In Use', 'restricted' => 'Restricted', 'offsite' => 'Offsite', 'missing' => 'Missing'] as $val => $label)
                    <option value="{{ $val }}" @selected(($itemLocation['access_status'] ?? 'available') == $val)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-md-12">
                <label class="form-label">Condition notes <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="item_condition_notes" class="form-control" value="{{ $itemLocation['condition_notes'] ?? '' }}">
              </div>
            </div>

            {{-- Notes --}}
            <div class="row mb-3">
              <div class="col-md-12">
                <label class="form-label">Location notes <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea name="item_location_notes" class="form-control" rows="2">{{ $itemLocation['notes'] ?? '' }}</textarea>
              </div>
            </div>

          </div>
        </section>
        @endauth

        {{-- ===== Quick Links ===== --}}
        <section class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-link me-2"></i>Quick Links</h5>
          </div>
          <div class="card-body">
            <a href="{{ route('library.browse') }}" class="btn atom-btn-white w-100 mb-2">
              <i class="fas fa-list me-2"></i>Browse library
            </a>
            @if($item)
              <a href="{{ url('/' . $item->slug . '/object/addDigitalObject') }}" class="btn atom-btn-white w-100 mb-2">
                <i class="fas fa-upload me-2"></i>Add digital object
              </a>
              <a href="{{ url('/' . $item->slug . '/digitalobject/edit') }}" class="btn atom-btn-white w-100 mb-2">
                <i class="fas fa-edit me-2"></i>Edit digital object
              </a>
            @endif
          </div>
        </section>

        {{-- ===== External Catalogs ===== --}}
        @if($item && ($item->oclc_number || $item->openlibrary_id || $item->openlibrary_url))
        <section class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-external-link-alt me-2"></i>External Catalogs</h5>
          </div>
          <div class="list-group list-group-flush">
            @if($item->oclc_number)
              <a href="https://www.worldcat.org/oclc/{{ $item->oclc_number }}" target="_blank" class="list-group-item list-group-item-action small">
                <i class="fas fa-globe me-1"></i> WorldCat
              </a>
            @endif
            @if($item->openlibrary_url)
              <a href="{{ $item->openlibrary_url }}" target="_blank" class="list-group-item list-group-item-action small">
                <i class="fas fa-book-open me-1"></i> OpenLibrary
              </a>
            @elseif($item->openlibrary_id)
              <a href="https://openlibrary.org/books/{{ $item->openlibrary_id }}" target="_blank" class="list-group-item list-group-item-action small">
                <i class="fas fa-book-open me-1"></i> OpenLibrary
              </a>
            @endif
          </div>
        </section>
        @endif

      </div>
    </div>

  </form>

<script>
// Creator role options (for dynamic rows)
var creatorRoleOptions = '{!! collect($formChoices["creatorRoles"])->map(function($label, $code) { return "<option value=\"" . e($code) . "\"" . ($code === "author" ? " selected" : "") . ">" . e($label) . "</option>"; })->implode("") !!}';

document.addEventListener('DOMContentLoaded', function() {

    // =============================================
    // ISBN Lookup
    // =============================================
    var lookupBtn = document.getElementById('isbn-lookup');
    if (lookupBtn) {
        lookupBtn.addEventListener('click', async function() {
            var isbnInput = document.getElementById('isbn-input');
            var isbn = isbnInput.value.trim();
            if (!isbn) {
                alert('Please enter an ISBN');
                return;
            }

            var originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Looking up...';

            try {
                var cleanIsbn = isbn.replace(/[\s-]/g, '');
                var response = await fetch('/library/isbn-lookup?isbn=' + encodeURIComponent(cleanIsbn));
                var result = await response.json();

                if (result.success) {
                    var d = result.data;

                    var title = d.title || '';
                    var authors = d.authors ? d.authors.map(function(a) { return {name: a.name, url: a.url}; }) : [];
                    var publisher = d.publishers && d.publishers[0] ? d.publishers[0].name : '';
                    var publishPlace = d.publish_places && d.publish_places[0] ? d.publish_places[0].name : '';
                    var date = d.publish_date || '';
                    var pages = d.number_of_pages || '';
                    var pagination = d.pagination || (pages ? pages + ' p.' : '');
                    var byStatement = d.by_statement || '';
                    var subjects = d.subjects ? d.subjects.slice(0, 10).map(function(s) { return {name: s.name, url: s.url}; }) : [];
                    var description = d.description || (result.preview && result.preview.description) || '';
                    var notes = d.notes || '';
                    var coverUrl = d.cover ? d.cover.medium : '';
                    var openLibraryUrl = d.url || '';
                    var openLibraryId = d.identifiers && d.identifiers.openlibrary ? d.identifiers.openlibrary[0] : '';
                    var lccn = d.identifiers && d.identifiers.lccn ? d.identifiers.lccn[0] : '';
                    var oclc = d.identifiers && d.identifiers.oclc ? d.identifiers.oclc[0] : '';
                    var goodreads = d.identifiers && d.identifiers.goodreads ? d.identifiers.goodreads[0] : '';
                    var librarything = d.identifiers && d.identifiers.librarything ? d.identifiers.librarything[0] : '';
                    var lcClass = d.classifications && d.classifications.lc_classifications ? d.classifications.lc_classifications[0] : '';
                    var dewey = d.classifications && d.classifications.dewey_decimal_class ? d.classifications.dewey_decimal_class[0] : '';
                    var ebookUrl = d.ebooks && d.ebooks[0] ? d.ebooks[0].preview_url : '';

                    var msg = 'Found: ' + title + '\n';
                    if (authors.length) msg += 'By: ' + authors.map(a => a.name).join(', ') + '\n';
                    if (publisher) msg += 'Publisher: ' + publisher + '\n';
                    if (date) msg += 'Date: ' + date + '\n';
                    if (pages) msg += 'Pages: ' + pages + '\n';
                    if (subjects.length) msg += 'Subjects: ' + subjects.slice(0,3).map(s => s.name).join(', ') + '...\n';
                    if (description) msg += 'Summary: ' + description.substring(0, 150) + '...\n';
                    msg += '\nApply to form?';

                    if (confirm(msg)) {
                        function setField(name, value) {
                            var field = document.querySelector('[name="' + name + '"]');
                            if (field && value) {
                                field.value = value;
                            }
                        }

                        setField('title', title);
                        setField('responsibility_statement', byStatement);
                        setField('publisher', publisher);
                        setField('publication_place', publishPlace);
                        setField('publication_date', date);
                        setField('pagination', pagination);
                        setField('lccn', lccn);
                        setField('oclc_number', oclc);
                        setField('openlibrary_id', openLibraryId);
                        setField('openlibrary_url', openLibraryUrl);
                        setField('goodreads_id', goodreads);
                        setField('librarything_id', librarything);
                        setField('call_number', lcClass);
                        setField('dewey_decimal', dewey);
                        setField('general_note', notes);
                        setField('summary', description);
                        setField('cover_url', coverUrl);
                        setField('ebook_preview_url', ebookUrl);

                        // Fill Authors
                        if (authors.length > 0) {
                            var container = document.getElementById('creators-container');
                            if (container) {
                                container.innerHTML = '';
                                var noMsg = document.getElementById('no-creators-msg');
                                if (noMsg) noMsg.remove();

                                authors.forEach(function(author, i) {
                                    var html = '<div class="row creator-row mb-2 align-items-center" data-index="' + i + '">' +
                                        '<div class="col-md-5"><input type="text" name="creators[' + i + '][name]" class="form-control form-control-sm" value="' + escapeHtml(author.name) + '"></div>' +
                                        '<div class="col-md-3"><select name="creators[' + i + '][role]" class="form-select form-select-sm">' + creatorRoleOptions + '</select></div>' +
                                        '<div class="col-md-3"><input type="text" name="creators[' + i + '][authority_uri]" class="form-control form-control-sm" value="' + escapeHtml(author.url || '') + '" placeholder="URI"></div>' +
                                        '<div class="col-md-1"><button type="button" class="btn btn-sm atom-btn-outline-danger remove-creator-btn w-100"><i class="fas fa-times"></i></button></div></div>';
                                    container.insertAdjacentHTML('beforeend', html);
                                });
                                creatorIndex = authors.length;
                            }
                        }

                        // Fill Subjects
                        if (subjects.length > 0) {
                            var subContainer = document.getElementById('subjects-container');
                            if (subContainer) {
                                subContainer.innerHTML = '';
                                var noSubMsg = document.getElementById('no-subjects-msg');
                                if (noSubMsg) noSubMsg.remove();

                                subjects.forEach(function(subject, i) {
                                    var html = '<div class="row subject-row mb-2 align-items-center" data-index="' + i + '">' +
                                        '<div class="col-md-11"><input type="text" name="subjects[' + i + '][heading]" class="form-control form-control-sm" value="' + escapeHtml(subject.name) + '"></div>' +
                                        '<div class="col-md-1"><button type="button" class="btn btn-sm atom-btn-outline-danger remove-subject-btn w-100"><i class="fas fa-times"></i></button></div></div>';
                                    subContainer.insertAdjacentHTML('beforeend', html);
                                });
                                subjectIndex = subjects.length;
                            }
                        }

                        // Update cover preview
                        var coverPreview = document.getElementById('cover-preview');
                        if (coverPreview && coverUrl) {
                            coverPreview.innerHTML =
                                '<img src="' + coverUrl + '" class="img-fluid rounded shadow-sm" style="max-height:250px">' +
                                '<div class="mt-2"><small class="text-muted">Open Library</small></div>';
                            document.getElementById('cover-url-input').value = coverUrl;
                        }
                    }
                } else {
                    alert('ISBN not found in Open Library');
                }
            } catch (err) {
                console.error('ISBN lookup error:', err);
                alert('Error looking up ISBN: ' + err.message);
            } finally {
                this.disabled = false;
                this.innerHTML = originalHtml;
            }
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/"/g, '&quot;');
    }

    // =============================================
    // Cover preview on ISBN blur
    // =============================================
    var isbnInput = document.getElementById('isbn-input');
    if (isbnInput) {
        isbnInput.addEventListener('blur', function() {
            var isbn = this.value.replace(/[\s-]/g, '');
            if (isbn.length >= 10) {
                var coverPreview = document.getElementById('cover-preview');
                if (coverPreview) {
                    coverPreview.innerHTML =
                        '<img src="/library/cover/' + isbn + '" class="img-fluid rounded shadow-sm" style="max-height:250px" onerror="this.style.display=\'none\'">' +
                        '<div class="mt-2"><small class="text-muted">Open Library</small></div>';
                }
            }
        });
    }

    // =============================================
    // Creator management
    // =============================================
    var creatorIndex = document.querySelectorAll('.creator-row').length;

    document.getElementById('add-creator-btn')?.addEventListener('click', function() {
        var container = document.getElementById('creators-container');
        var noMsg = document.getElementById('no-creators-msg');
        if (noMsg) noMsg.remove();

        var html = '<div class="row creator-row mb-2 align-items-center" data-index="' + creatorIndex + '">' +
            '<div class="col-md-5"><input type="text" name="creators[' + creatorIndex + '][name]" class="form-control form-control-sm" placeholder="Name"></div>' +
            '<div class="col-md-3"><select name="creators[' + creatorIndex + '][role]" class="form-select form-select-sm">' + creatorRoleOptions + '</select></div>' +
            '<div class="col-md-3"><input type="text" name="creators[' + creatorIndex + '][authority_uri]" class="form-control form-control-sm" placeholder="Authority URI"></div>' +
            '<div class="col-md-1"><button type="button" class="btn btn-sm atom-btn-outline-danger remove-creator-btn w-100"><i class="fas fa-times"></i></button></div></div>';
        container.insertAdjacentHTML('beforeend', html);
        creatorIndex++;
        container.lastElementChild.querySelector('input').focus();
    });

    document.getElementById('creators-container')?.addEventListener('click', function(e) {
        if (e.target.closest('.remove-creator-btn')) {
            e.target.closest('.creator-row').remove();
        }
    });

    // =============================================
    // Subject management
    // =============================================
    var subjectIndex = document.querySelectorAll('.subject-row').length;

    document.getElementById('add-subject-btn')?.addEventListener('click', function() {
        var container = document.getElementById('subjects-container');
        var noMsg = document.getElementById('no-subjects-msg');
        if (noMsg) noMsg.remove();

        var html = '<div class="row subject-row mb-2 align-items-center" data-index="' + subjectIndex + '">' +
            '<div class="col-md-11"><input type="text" name="subjects[' + subjectIndex + '][heading]" class="form-control form-control-sm" placeholder="Subject heading"></div>' +
            '<div class="col-md-1"><button type="button" class="btn btn-sm atom-btn-outline-danger remove-subject-btn w-100"><i class="fas fa-times"></i></button></div></div>';
        container.insertAdjacentHTML('beforeend', html);
        subjectIndex++;
        container.lastElementChild.querySelector('input').focus();
    });

    document.getElementById('subjects-container')?.addEventListener('click', function(e) {
        if (e.target.closest('.remove-subject-btn')) {
            e.target.closest('.subject-row').remove();
        }
    });

    // =============================================
    // Subject suggestion feature
    // =============================================
    var suggestBtn = document.getElementById('suggest-subjects-btn');
    if (suggestBtn) {
        suggestBtn.addEventListener('click', async function() {
            var btn = this;
            var originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading...';

            try {
                var title = document.querySelector('[name="title"]')?.value || '';
                var description = document.querySelector('[name="summary"]')?.value || '';
                var objectId = {{ $item->id ?? 'null' }};

                var existingSubjects = [];
                document.querySelectorAll('.subject-row input[name*="[heading]"]').forEach(function(input) {
                    if (input.value.trim()) {
                        existingSubjects.push(input.value.trim());
                    }
                });

                var params = new URLSearchParams({
                    title: title,
                    description: description,
                    existing_subjects: JSON.stringify(existingSubjects)
                });
                if (objectId) {
                    params.append('object_id', objectId);
                }

                var response = await fetch('/library/suggest-subjects?' + params.toString());
                var result = await response.json();

                if (result.success && result.suggestions.length > 0) {
                    showSuggestionModal(result.suggestions);
                } else if (result.success && result.suggestions.length === 0) {
                    alert('No suggestions available. Try adding more content to the title or summary.');
                } else {
                    alert('Error getting suggestions: ' + (result.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('Subject suggestion error:', err);
                alert('Error getting suggestions: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    }

    // Subject suggestion modal
    function showSuggestionModal(suggestions) {
        var existingModal = document.getElementById('subject-suggest-modal');
        if (existingModal) existingModal.remove();

        var typeLabels = {
            'topical': 'Topical',
            'personal': 'Personal',
            'corporate': 'Corporate',
            'geographic': 'Geographic',
            'genre': 'Genre',
            'meeting': 'Meeting'
        };

        var suggestionRows = suggestions.map(function(s, i) {
            var scorePercent = Math.round(s.score * 100);
            var typeLabel = typeLabels[s.heading_type] || s.heading_type;
            var nerBadge = s.ner_source ? '<span class="badge bg-success ms-1" title="Matched from NER entities"><i class="fas fa-brain"></i></span>' : '';

            return '<div class="form-check mb-2 p-2 border rounded suggestion-item" data-heading="' + escapeHtml(s.heading) + '">' +
                '<input class="form-check-input" type="checkbox" id="suggest-' + i + '" value="' + escapeHtml(s.heading) + '">' +
                '<label class="form-check-label w-100" for="suggest-' + i + '">' +
                    '<div class="d-flex justify-content-between align-items-center">' +
                        '<div>' +
                            '<strong>' + escapeHtml(s.heading) + '</strong>' + nerBadge +
                            '<br><small class="text-muted">' + typeLabel + ' &bull; ' + s.source + '</small>' +
                        '</div>' +
                        '<div class="text-end">' +
                            '<span class="badge bg-primary">' + scorePercent + '%</span><br>' +
                            '<small class="text-muted">Used ' + s.usage_count + 'x</small>' +
                        '</div>' +
                    '</div>' +
                '</label>' +
            '</div>';
        }).join('');

        var modalHtml = '<div class="modal fade" id="subject-suggest-modal" tabindex="-1">' +
            '<div class="modal-dialog modal-lg">' +
                '<div class="modal-content">' +
                    '<div class="modal-header bg-warning">' +
                        '<h5 class="modal-title"><i class="fas fa-magic me-2"></i>Subject Suggestions</h5>' +
                        '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
                    '</div>' +
                    '<div class="modal-body">' +
                        '<p class="text-muted mb-3">Select subjects to add. Suggestions are ranked by relevance to your title/description and usage frequency.</p>' +
                        '<div class="mb-3">' +
                            '<div class="form-check">' +
                                '<input class="form-check-input" type="checkbox" id="select-all-suggestions">' +
                                '<label class="form-check-label" for="select-all-suggestions"><strong>Select All</strong> <span class="badge bg-secondary ms-1">Optional</span></label>' +
                            '</div>' +
                        '</div>' +
                        '<div class="suggestions-list" style="max-height: 400px; overflow-y: auto;">' +
                            suggestionRows +
                        '</div>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>' +
                        '<button type="button" class="btn atom-btn-outline-success" id="add-selected-subjects">' +
                            '<i class="fas fa-plus me-1"></i>Add Selected' +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        var modal = new bootstrap.Modal(document.getElementById('subject-suggest-modal'));
        modal.show();

        document.getElementById('select-all-suggestions').addEventListener('change', function() {
            var checked = this.checked;
            document.querySelectorAll('.suggestions-list input[type="checkbox"]').forEach(function(cb) {
                cb.checked = checked;
            });
        });

        document.getElementById('add-selected-subjects').addEventListener('click', function() {
            var container = document.getElementById('subjects-container');
            var noMsg = document.getElementById('no-subjects-msg');
            if (noMsg) noMsg.remove();

            var added = 0;
            document.querySelectorAll('.suggestions-list input[type="checkbox"]:checked').forEach(function(cb) {
                var heading = cb.value;

                var exists = false;
                document.querySelectorAll('.subject-row input[name*="[heading]"]').forEach(function(input) {
                    if (input.value.trim().toLowerCase() === heading.toLowerCase()) {
                        exists = true;
                    }
                });

                if (!exists) {
                    var html = '<div class="row subject-row mb-2 align-items-center" data-index="' + subjectIndex + '">' +
                        '<div class="col-md-11"><input type="text" name="subjects[' + subjectIndex + '][heading]" class="form-control form-control-sm" value="' + escapeHtml(heading) + '"></div>' +
                        '<div class="col-md-1"><button type="button" class="btn btn-sm atom-btn-outline-danger remove-subject-btn w-100"><i class="fas fa-times"></i></button></div></div>';
                    container.insertAdjacentHTML('beforeend', html);
                    subjectIndex++;
                    added++;
                }
            });

            modal.hide();

            if (added > 0) {
                var subjectsCard = document.querySelector('#subjects-container').closest('.card');
                subjectsCard.classList.add('border-success');
                setTimeout(function() {
                    subjectsCard.classList.remove('border-success');
                }, 2000);
            }
        });

        document.getElementById('subject-suggest-modal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

});
</script>
@endsection
