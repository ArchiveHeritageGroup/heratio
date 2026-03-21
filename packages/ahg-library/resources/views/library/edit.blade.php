@extends('theme::layouts.1col')

@section('title', $item ? 'Edit ' . ($item->title ?? '') : 'Add new library item')

@section('content')
  <h1>{{ $item ? 'Edit ' . ($item->title ?? '') : 'Add new library item' }}</h1>
    @if($item)
      <span class="small" id="heading-label">{{ $item->title ?: $item->identifier }}</span>
    @endif
  </div>

  <form method="POST"
        action="{{ $item ? route('library.update', $item->slug) : route('library.store') }}"
        id="editForm">
    @csrf
    @if($item)
      @method('PUT')
    @endif
    @if(request('parent'))
      <input type="hidden" name="parent" value="{{ request('parent') }}">
    @endif

    <div class="accordion mb-3">

      {{-- ===== Basic Information ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="basic-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#basic-collapse" aria-expanded="false" aria-controls="basic-collapse">
            Basic information
          </button>
        </h2>
        <div id="basic-collapse" class="accordion-collapse collapse" aria-labelledby="basic-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="title" class="form-label">
                Title
                <span class="form-required" title="This is a mandatory element.">*</span>
              </label>
              <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                     value="{{ old('title', $item->title ?? '') }}">
              @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">The title of the library item, as it appears on the title page or equivalent.</div>
            </div>

            <div class="mb-3">
              <label for="subtitle" class="form-label">Subtitle</label>
              <input type="text" name="subtitle" id="subtitle" class="form-control @error('subtitle') is-invalid @enderror"
                     value="{{ old('subtitle', $item->subtitle ?? '') }}">
              @error('subtitle') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">The subtitle of the library item, if applicable.</div>
            </div>

            <div class="mb-3">
              <label for="identifier" class="form-label">Identifier</label>
              <input type="text" name="identifier" id="identifier" class="form-control @error('identifier') is-invalid @enderror"
                     value="{{ old('identifier', $item->identifier ?? '') }}">
              @error('identifier') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">A unique identifier or reference code for this library item.</div>
            </div>

            <div class="mb-3">
              <label for="responsibility_statement" class="form-label">Statement of responsibility</label>
              <input type="text" name="responsibility_statement" id="responsibility_statement" class="form-control @error('responsibility_statement') is-invalid @enderror"
                     value="{{ old('responsibility_statement', $item->responsibility_statement ?? '') }}">
              @error('responsibility_statement') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">The statement of responsibility relating to the title (e.g. author names, editors, translators).</div>
            </div>

            <div class="mb-3">
              <label for="level_of_description_id" class="form-label">Level of description</label>
              <select name="level_of_description_id" id="level_of_description_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['levels'] as $level)
                  <option value="{{ $level->id }}" @selected(old('level_of_description_id', $item->level_of_description_id ?? '') == $level->id)>{{ $level->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Select the level of description for this library item.</div>
            </div>

            <div class="mb-3">
              <label for="material_type" class="form-label">Material type</label>
              <select name="material_type" id="material_type" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['materialTypes'] as $value => $label)
                  <option value="{{ $value }}" @selected(old('material_type', $item->material_type ?? '') == $value)>{{ $label }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">The type or format of the library material (e.g. monograph, journal, manuscript).</div>
            </div>

            <div class="mb-3">
              <label for="language" class="form-label">Language</label>
              <input type="text" name="language" id="language" class="form-control @error('language') is-invalid @enderror"
                     value="{{ old('language', $item->language ?? '') }}">
              @error('language') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">The language of the intellectual content of the item (e.g. en, fr, af).</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Standard Identifiers ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="identifiers-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#identifiers-collapse" aria-expanded="false" aria-controls="identifiers-collapse">
            Standard identifiers
          </button>
        </h2>
        <div id="identifiers-collapse" class="accordion-collapse collapse" aria-labelledby="identifiers-heading">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="isbn" class="form-label">ISBN</label>
                <input type="text" name="isbn" id="isbn" class="form-control @error('isbn') is-invalid @enderror"
                       value="{{ old('isbn', $item->isbn ?? '') }}">
                @error('isbn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">International Standard Book Number (10 or 13 digit).</div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="issn" class="form-label">ISSN</label>
                <input type="text" name="issn" id="issn" class="form-control @error('issn') is-invalid @enderror"
                       value="{{ old('issn', $item->issn ?? '') }}">
                @error('issn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">International Standard Serial Number for serial publications.</div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="doi" class="form-label">DOI</label>
                <input type="text" name="doi" id="doi" class="form-control @error('doi') is-invalid @enderror"
                       value="{{ old('doi', $item->doi ?? '') }}">
                @error('doi') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">Digital Object Identifier for persistent identification of the work.</div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="lccn" class="form-label">LCCN</label>
                <input type="text" name="lccn" id="lccn" class="form-control @error('lccn') is-invalid @enderror"
                       value="{{ old('lccn', $item->lccn ?? '') }}">
                @error('lccn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">Library of Congress Control Number.</div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="oclc_number" class="form-label">OCLC number</label>
                <input type="text" name="oclc_number" id="oclc_number" class="form-control @error('oclc_number') is-invalid @enderror"
                       value="{{ old('oclc_number', $item->oclc_number ?? '') }}">
                @error('oclc_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">OCLC WorldCat record number.</div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="barcode" class="form-label">Barcode</label>
                <input type="text" name="barcode" id="barcode" class="form-control @error('barcode') is-invalid @enderror"
                       value="{{ old('barcode', $item->barcode ?? '') }}">
                @error('barcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">Physical barcode label affixed to the item.</div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="openlibrary_id" class="form-label">OpenLibrary ID</label>
                <input type="text" name="openlibrary_id" id="openlibrary_id" class="form-control @error('openlibrary_id') is-invalid @enderror"
                       value="{{ old('openlibrary_id', $item->openlibrary_id ?? '') }}">
                @error('openlibrary_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">Open Library edition or work identifier.</div>
              </div>
              <div class="col-md-4 mb-3">
                <label for="goodreads_id" class="form-label">Goodreads ID</label>
                <input type="text" name="goodreads_id" id="goodreads_id" class="form-control @error('goodreads_id') is-invalid @enderror"
                       value="{{ old('goodreads_id', $item->goodreads_id ?? '') }}">
                @error('goodreads_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">Goodreads book identifier.</div>
              </div>
              <div class="col-md-4 mb-3">
                <label for="librarything_id" class="form-label">LibraryThing ID</label>
                <input type="text" name="librarything_id" id="librarything_id" class="form-control @error('librarything_id') is-invalid @enderror"
                       value="{{ old('librarything_id', $item->librarything_id ?? '') }}">
                @error('librarything_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">LibraryThing work identifier.</div>
              </div>
            </div>

            <div class="mb-3">
              <label for="openlibrary_url" class="form-label">OpenLibrary URL</label>
              <input type="url" name="openlibrary_url" id="openlibrary_url" class="form-control @error('openlibrary_url') is-invalid @enderror"
                     value="{{ old('openlibrary_url', $item->openlibrary_url ?? '') }}">
              @error('openlibrary_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">Link to the OpenLibrary page for this item.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Classification ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="classification-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#classification-collapse" aria-expanded="false" aria-controls="classification-collapse">
            Classification
          </button>
        </h2>
        <div id="classification-collapse" class="accordion-collapse collapse" aria-labelledby="classification-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="classification_scheme" class="form-label">Classification scheme</label>
              <select name="classification_scheme" id="classification_scheme" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['classificationSchemes'] as $value => $label)
                  <option value="{{ $value }}" @selected(old('classification_scheme', $item->classification_scheme ?? '') == $value)>{{ $label }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">The classification system used (e.g. Library of Congress, Dewey Decimal, UDC).</div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="call_number" class="form-label">Call number</label>
                <input type="text" name="call_number" id="call_number" class="form-control @error('call_number') is-invalid @enderror"
                       value="{{ old('call_number', $item->call_number ?? '') }}">
                @error('call_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">The complete call number as assigned to the item in the library.</div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="dewey_decimal" class="form-label">Dewey Decimal number</label>
                <input type="text" name="dewey_decimal" id="dewey_decimal" class="form-control @error('dewey_decimal') is-invalid @enderror"
                       value="{{ old('dewey_decimal', $item->dewey_decimal ?? '') }}">
                @error('dewey_decimal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">The Dewey Decimal Classification number for the item.</div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="shelf_location" class="form-label">Shelf location</label>
                <input type="text" name="shelf_location" id="shelf_location" class="form-control @error('shelf_location') is-invalid @enderror"
                       value="{{ old('shelf_location', $item->shelf_location ?? '') }}">
                @error('shelf_location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">The physical shelf or storage location within the library.</div>
              </div>
              <div class="col-md-4 mb-3">
                <label for="copy_number" class="form-label">Copy number</label>
                <input type="text" name="copy_number" id="copy_number" class="form-control @error('copy_number') is-invalid @enderror"
                       value="{{ old('copy_number', $item->copy_number ?? '') }}">
                @error('copy_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">The copy number if multiple copies exist in the collection.</div>
              </div>
              <div class="col-md-4 mb-3">
                <label for="volume_designation" class="form-label">Volume designation</label>
                <input type="text" name="volume_designation" id="volume_designation" class="form-control @error('volume_designation') is-invalid @enderror"
                       value="{{ old('volume_designation', $item->volume_designation ?? '') }}">
                @error('volume_designation') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">The volume or part number within a multi-volume work.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Publication ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="publication-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#publication-collapse" aria-expanded="false" aria-controls="publication-collapse">
            Publication
          </button>
        </h2>
        <div id="publication-collapse" class="accordion-collapse collapse" aria-labelledby="publication-heading">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="publisher" class="form-label">Publisher</label>
                <input type="text" name="publisher" id="publisher" class="form-control @error('publisher') is-invalid @enderror"
                       value="{{ old('publisher', $item->publisher ?? '') }}">
                @error('publisher') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">The name of the publisher or publishing house.</div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="publication_place" class="form-label">Place of publication</label>
                <input type="text" name="publication_place" id="publication_place" class="form-control @error('publication_place') is-invalid @enderror"
                       value="{{ old('publication_place', $item->publication_place ?? '') }}">
                @error('publication_place') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">The city or town where the item was published.</div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="publication_date" class="form-label">Date of publication</label>
                <input type="text" name="publication_date" id="publication_date" class="form-control @error('publication_date') is-invalid @enderror"
                       value="{{ old('publication_date', $item->publication_date ?? '') }}">
                @error('publication_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">The date or year the item was published.</div>
              </div>
              <div class="col-md-4 mb-3">
                <label for="edition" class="form-label">Edition</label>
                <input type="text" name="edition" id="edition" class="form-control @error('edition') is-invalid @enderror"
                       value="{{ old('edition', $item->edition ?? '') }}">
                @error('edition') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">The edition number or designation (e.g. 2nd ed., revised ed.).</div>
              </div>
              <div class="col-md-4 mb-3">
                <label for="edition_statement" class="form-label">Edition statement</label>
                <input type="text" name="edition_statement" id="edition_statement" class="form-control @error('edition_statement') is-invalid @enderror"
                       value="{{ old('edition_statement', $item->edition_statement ?? '') }}">
                @error('edition_statement') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">The full edition statement as transcribed from the item.</div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="series_title" class="form-label">Series title</label>
                <input type="text" name="series_title" id="series_title" class="form-control @error('series_title') is-invalid @enderror"
                       value="{{ old('series_title', $item->series_title ?? '') }}">
                @error('series_title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">The title of the series to which this item belongs.</div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="series_number" class="form-label">Series number</label>
                <input type="text" name="series_number" id="series_number" class="form-control @error('series_number') is-invalid @enderror"
                       value="{{ old('series_number', $item->series_number ?? '') }}">
                @error('series_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">The numbering of the item within the series.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Physical Description ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="physical-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#physical-collapse" aria-expanded="false" aria-controls="physical-collapse">
            Physical description
          </button>
        </h2>
        <div id="physical-collapse" class="accordion-collapse collapse" aria-labelledby="physical-heading">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="pages" class="form-label">Pages / Extent</label>
                <input type="text" name="pagination" id="pages" class="form-control @error('pages') is-invalid @enderror"
                       value="{{ old('pages', $item->pages ?? '') }}">
                @error('pages') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">Number of pages, volumes, or other physical extent.</div>
              </div>
              <div class="col-md-4 mb-3">
                <label for="dimensions" class="form-label">Dimensions</label>
                <input type="text" name="dimensions" id="dimensions" class="form-control @error('dimensions') is-invalid @enderror"
                       value="{{ old('dimensions', $item->dimensions ?? '') }}">
                @error('dimensions') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">Physical dimensions of the item (e.g. 24 cm).</div>
              </div>
              <div class="col-md-4 mb-3">
                <label for="physical_details" class="form-label">Physical details</label>
                <input type="text" name="physical_details" id="physical_details" class="form-control @error('physical_details') is-invalid @enderror"
                       value="{{ old('physical_details', $item->physical_details ?? '') }}">
                @error('physical_details') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text text-muted small">Other physical details (e.g. illustrations, maps, portraits).</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Content ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="content-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#content-collapse" aria-expanded="false" aria-controls="content-collapse">
            Content
          </button>
        </h2>
        <div id="content-collapse" class="accordion-collapse collapse" aria-labelledby="content-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="summary" class="form-label">Summary / Abstract</label>
              <textarea name="summary" id="summary" class="form-control" rows="4">{{ old('summary', $item->summary ?? '') }}</textarea>
              <div class="form-text text-muted small">A brief summary or abstract describing the content of the library item.</div>
            </div>

            <div class="mb-3">
              <label for="scope_and_content" class="form-label">Scope and content</label>
              <textarea name="scope_and_content" id="scope_and_content" class="form-control" rows="3">{{ old('scope_and_content', $item->scope_and_content ?? '') }}</textarea>
              <div class="form-text text-muted small">Scope and content of the item.</div>
            </div>

            <div class="mb-3">
              <label for="table_of_contents" class="form-label">Table of contents</label>
              <textarea name="contents_note" id="table_of_contents" class="form-control" rows="4">{{ old('table_of_contents', $item->table_of_contents ?? '') }}</textarea>
              <div class="form-text text-muted small">The table of contents or list of chapters/sections in the item.</div>
            </div>

            <div class="mb-3">
              <label for="general_note" class="form-label">General note</label>
              <textarea name="general_note" id="general_note" class="form-control" rows="3">{{ old('general_note', $item->general_note ?? '') }}</textarea>
              <div class="form-text text-muted small">Any general notes or remarks about the library item not covered by other fields.</div>
            </div>

            <div class="mb-3">
              <label for="bibliography_note" class="form-label">Bibliography note</label>
              <textarea name="bibliography_note" id="bibliography_note" class="form-control" rows="3">{{ old('bibliography_note', $item->bibliography_note ?? '') }}</textarea>
              <div class="form-text text-muted small">Notes on bibliographies, indexes, or references included in the item.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Cover & Links ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="links-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#links-collapse" aria-expanded="false" aria-controls="links-collapse">
            Cover image &amp; links
          </button>
        </h2>
        <div id="links-collapse" class="accordion-collapse collapse" aria-labelledby="links-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="cover_url" class="form-label">Cover image URL</label>
              <input type="url" name="cover_url" id="cover_url" class="form-control @error('cover_url') is-invalid @enderror"
                     value="{{ old('cover_url', $item->cover_url ?? '') }}">
              @error('cover_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">URL to the cover image (thumbnail or display version).</div>
            </div>

            <div class="mb-3">
              <label for="cover_url_original" class="form-label">Original cover image URL</label>
              <input type="url" name="cover_url_original" id="cover_url_original" class="form-control @error('cover_url_original') is-invalid @enderror"
                     value="{{ old('cover_url_original', $item->cover_url_original ?? '') }}">
              @error('cover_url_original') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">URL to the original high-resolution cover image.</div>
            </div>

            <div class="mb-3">
              <label for="ebook_preview_url" class="form-label">E-book preview URL</label>
              <input type="url" name="ebook_preview_url" id="ebook_preview_url" class="form-control @error('ebook_preview_url') is-invalid @enderror"
                     value="{{ old('ebook_preview_url', $item->ebook_preview_url ?? '') }}">
              @error('ebook_preview_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">Link to an e-book preview or reading sample.</div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($item)
        <li><a class="btn atom-btn-outline-light" role="button" href="{{ route('library.show', $item->slug) }}">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a class="btn atom-btn-outline-light" role="button" href="{{ route('library.browse') }}">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
      @endif
    </ul>
  </form>
@endsection
