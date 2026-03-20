@extends('theme::layouts.1col')

@section('title', ($isNew ? 'Add gallery artwork' : 'Edit: ' . ($artwork->title ?? 'Untitled')))
@section('body-class', ($isNew ? 'create' : 'edit') . ' gallery')

@section('content')
  <h1>{{ $isNew ? 'Add gallery artwork' : 'Edit: ' . ($artwork->title ?? '[Untitled]') }}</h1>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

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
        action="{{ $isNew ? route('gallery.store') : route('gallery.update', $artwork->slug) }}">
    @csrf
    @if(!$isNew)
      @method('PUT')
    @endif

    <div class="accordion" id="galleryAccordion">

      {{-- ===== Object/Work ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingObjectWork">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseObjectWork" aria-expanded="true" aria-controls="collapseObjectWork">
            Object/Work
          </button>
        </h2>
        <div id="collapseObjectWork" class="accordion-collapse collapse show" aria-labelledby="headingObjectWork" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="work_type" class="form-label">Work type</label>
              <select class="form-select" id="work_type" name="work_type">
                <option value="">-- Select --</option>
                @foreach($workTypes as $wt)
                  <option value="{{ $wt }}" @selected(old('work_type', $artwork->work_type ?? '') == $wt)>{{ $wt }}</option>
                @endforeach
              </select>
              <div class="form-text">The type or genre of artwork (CCO: Object/Work Type). Select the most specific applicable type for this artwork.</div>
            </div>

            <div class="mb-3">
              <label for="classification" class="form-label">Classification</label>
              <input type="text" class="form-control" id="classification" name="classification"
                     value="{{ old('classification', $artwork->classification ?? '') }}">
              <div class="form-text">Classification scheme or category. A term that identifies the broader context for the object, such as "Impressionist paintings" or "Contemporary sculpture".</div>
            </div>

            <div class="mb-3">
              <label for="identifier" class="form-label">Identifier / Accession number</label>
              <input type="text" class="form-control" id="identifier" name="identifier"
                     value="{{ old('identifier', $artwork->identifier ?? '') }}">
              <div class="form-text">A unique numeric or alphanumeric identifier assigned to the object by the holding institution (e.g. accession number, catalogue number).</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Title ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingTitle">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTitle" aria-expanded="false" aria-controls="collapseTitle">
            Title
          </button>
        </h2>
        <div id="collapseTitle" class="accordion-collapse collapse" aria-labelledby="headingTitle" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
              <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title"
                     value="{{ old('title', $artwork->title ?? '') }}" required>
              @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <div class="form-text">The title or name given to the artwork by the artist, collector, or institution. Use the most commonly known or preferred title. If untitled, enter "Untitled" and add any descriptive title in brackets.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Creator ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCreator">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCreator" aria-expanded="false" aria-controls="collapseCreator">
            Creator
          </button>
        </h2>
        <div id="collapseCreator" class="accordion-collapse collapse" aria-labelledby="headingCreator" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="creator_identity" class="form-label">Creator</label>
              <input type="text" class="form-control" id="creator_identity" name="creator_identity"
                     value="{{ old('creator_identity', $artwork->creator_identity ?? '') }}">
              <div class="form-text">The name of the artist or maker of the artwork (CCO: Creator). Enter the name in inverted form (Surname, Forename) or as commonly known. For unknown creators, use "Unknown" or qualifiers such as "Attributed to" or "Circle of".</div>
            </div>

            <div class="mb-3">
              <label for="creator_role" class="form-label">Creator role</label>
              <select class="form-select" id="creator_role" name="creator_role">
                <option value="">-- Select --</option>
                @foreach($creatorRoles as $cr)
                  <option value="{{ $cr }}" @selected(old('creator_role', $artwork->creator_role ?? '') == $cr)>{{ $cr }}</option>
                @endforeach
              </select>
              <div class="form-text">The role of the creator in relation to this artwork. Use qualifiers where the attribution is uncertain (e.g. "Attributed to", "Circle of", "After").</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Creation ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCreation">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCreation" aria-expanded="false" aria-controls="collapseCreation">
            Creation
          </button>
        </h2>
        <div id="collapseCreation" class="accordion-collapse collapse" aria-labelledby="headingCreation" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="creation_date_display" class="form-label">Date (display)</label>
              <input type="text" class="form-control" id="creation_date_display" name="creation_date_display"
                     value="{{ old('creation_date_display', $artwork->creation_date_display ?? '') }}">
              <div class="form-text">A free-text date for display purposes (e.g. "ca. 1885", "early 20th century", "1965-1970"). Enter the date as it should appear to users.</div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="creation_date_earliest" class="form-label">Earliest date</label>
                  <input type="text" class="form-control" id="creation_date_earliest" name="creation_date_earliest"
                         value="{{ old('creation_date_earliest', $artwork->creation_date_earliest ?? '') }}">
                  <div class="form-text">The earliest possible creation date in ISO 8601 format (YYYY-MM-DD or YYYY). Used for date range searching.</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="creation_date_latest" class="form-label">Latest date</label>
                  <input type="text" class="form-control" id="creation_date_latest" name="creation_date_latest"
                         value="{{ old('creation_date_latest', $artwork->creation_date_latest ?? '') }}">
                  <div class="form-text">The latest possible creation date in ISO 8601 format (YYYY-MM-DD or YYYY). Used for date range searching.</div>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label for="creation_place" class="form-label">Creation place</label>
              <input type="text" class="form-control" id="creation_place" name="creation_place"
                     value="{{ old('creation_place', $artwork->creation_place ?? '') }}">
              <div class="form-text">The geographic location where the artwork was created. Use a hierarchical form where possible (e.g. "Paris, France").</div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="style" class="form-label">Style</label>
                  <input type="text" class="form-control" id="style" name="style"
                         value="{{ old('style', $artwork->style ?? '') }}">
                  <div class="form-text">The visual style of the work (e.g. "Impressionism", "Art Nouveau", "Abstract Expressionism").</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="period" class="form-label">Period</label>
                  <input type="text" class="form-control" id="period" name="period"
                         value="{{ old('period', $artwork->period ?? '') }}">
                  <div class="form-text">The broad cultural or chronological period (e.g. "Renaissance", "Modern", "Contemporary").</div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="movement" class="form-label">Movement</label>
                  <input type="text" class="form-control" id="movement" name="movement"
                         value="{{ old('movement', $artwork->movement ?? '') }}">
                  <div class="form-text">The art movement the artwork belongs to (e.g. "Cubism", "Surrealism", "Pop Art").</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="school" class="form-label">School</label>
                  <input type="text" class="form-control" id="school" name="school"
                         value="{{ old('school', $artwork->school ?? '') }}">
                  <div class="form-text">The art school or regional tradition (e.g. "Dutch School", "Bauhaus", "Hudson River School").</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Measurements ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingMeasurements">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMeasurements" aria-expanded="false" aria-controls="collapseMeasurements">
            Measurements
          </button>
        </h2>
        <div id="collapseMeasurements" class="accordion-collapse collapse" aria-labelledby="headingMeasurements" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="measurements" class="form-label">Measurements</label>
              <textarea class="form-control" id="measurements" name="measurements" rows="3">{{ old('measurements', $artwork->measurements ?? '') }}</textarea>
              <div class="form-text">Physical measurements of the artwork in a display-ready format (e.g. "120 x 80 cm", "48 x 32 inches (framed)").</div>
            </div>

            <div class="mb-3">
              <label for="dimensions" class="form-label">Dimensions</label>
              <textarea class="form-control" id="dimensions" name="dimensions" rows="3">{{ old('dimensions', $artwork->dimensions ?? '') }}</textarea>
              <div class="form-text">Additional or structured dimensional information (height, width, depth, weight, diameter). Include units and specify whether framed or unframed.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Materials/Techniques ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingMaterials">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMaterials" aria-expanded="false" aria-controls="collapseMaterials">
            Materials / Techniques
          </button>
        </h2>
        <div id="collapseMaterials" class="accordion-collapse collapse" aria-labelledby="headingMaterials" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="materials" class="form-label">Materials</label>
              <textarea class="form-control" id="materials" name="materials" rows="3">{{ old('materials', $artwork->materials ?? '') }}</textarea>
              <div class="form-text">The physical materials or media used to create the artwork (e.g. "Oil on canvas", "Bronze", "Graphite on paper", "Acrylic and mixed media on board").</div>
            </div>

            <div class="mb-3">
              <label for="techniques" class="form-label">Techniques</label>
              <textarea class="form-control" id="techniques" name="techniques" rows="3">{{ old('techniques', $artwork->techniques ?? '') }}</textarea>
              <div class="form-text">The techniques or processes used to create the artwork (e.g. "Impasto", "Lost-wax casting", "Screen printing", "Collage").</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Subject ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingSubject">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSubject" aria-expanded="false" aria-controls="collapseSubject">
            Subject
          </button>
        </h2>
        <div id="collapseSubject" class="accordion-collapse collapse" aria-labelledby="headingSubject" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="scope_and_content" class="form-label">Description / Subject matter</label>
              <textarea class="form-control" id="scope_and_content" name="scope_and_content" rows="5">{{ old('scope_and_content', $artwork->scope_and_content ?? '') }}</textarea>
              <div class="form-text">A narrative description of the subject matter, imagery, or content of the artwork. Describe what is depicted or represented, including themes, motifs, and iconographic elements.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Inscriptions ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingInscriptions">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseInscriptions" aria-expanded="false" aria-controls="collapseInscriptions">
            Inscriptions
          </button>
        </h2>
        <div id="collapseInscriptions" class="accordion-collapse collapse" aria-labelledby="headingInscriptions" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="inscription" class="form-label">Inscription</label>
              <textarea class="form-control" id="inscription" name="inscription" rows="3">{{ old('inscription', $artwork->inscription ?? '') }}</textarea>
              <div class="form-text">Transcription of any text written, printed, stamped, or engraved on the artwork, including the artist's signature, date, or dedication. Note the location on the work (e.g. "Signed lower right: Monet 1872").</div>
            </div>

            <div class="mb-3">
              <label for="mark_description" class="form-label">Marks</label>
              <textarea class="form-control" id="mark_description" name="mark_description" rows="3">{{ old('mark_description', $artwork->mark_description ?? '') }}</textarea>
              <div class="form-text">Description of any marks, labels, stamps, or seals found on the artwork other than inscriptions. Include collector marks, gallery labels, exhibition labels, and customs stamps.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Condition ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCondition">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCondition" aria-expanded="false" aria-controls="collapseCondition">
            Condition
          </button>
        </h2>
        <div id="collapseCondition" class="accordion-collapse collapse" aria-labelledby="headingCondition" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="condition_term" class="form-label">Condition</label>
              <input type="text" class="form-control" id="condition_term" name="condition_term"
                     value="{{ old('condition_term', $artwork->condition_term ?? '') }}">
              <div class="form-text">A standardized term describing the overall condition (e.g. "Excellent", "Good", "Fair", "Poor", "Requires conservation").</div>
            </div>

            <div class="mb-3">
              <label for="condition_description" class="form-label">Condition notes</label>
              <textarea class="form-control" id="condition_description" name="condition_description" rows="4">{{ old('condition_description', $artwork->condition_description ?? '') }}</textarea>
              <div class="form-text">A detailed narrative of the current physical condition. Note any damage, repairs, losses, discoloration, or conservation treatments. Record the date of the most recent condition assessment.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Provenance ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingProvenance">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProvenance" aria-expanded="false" aria-controls="collapseProvenance">
            Provenance
          </button>
        </h2>
        <div id="collapseProvenance" class="accordion-collapse collapse" aria-labelledby="headingProvenance" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="provenance" class="form-label">Provenance</label>
              <textarea class="form-control" id="provenance" name="provenance" rows="4">{{ old('provenance', $artwork->provenance ?? '') }}</textarea>
              <div class="form-text">The ownership history of the artwork from creation to present. List each owner in chronological order with dates, methods of transfer (purchase, gift, bequest, auction), and sources of information.</div>
            </div>

            <div class="mb-3">
              <label for="current_location" class="form-label">Current location</label>
              <input type="text" class="form-control" id="current_location" name="current_location"
                     value="{{ old('current_location', $artwork->current_location ?? '') }}">
              <div class="form-text">The current physical location of the artwork within the gallery or storage facility (e.g. "Gallery 3, West Wall" or "Vault B, Rack 12").</div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="rights_type" class="form-label">Rights type</label>
                  <input type="text" class="form-control" id="rights_type" name="rights_type"
                         value="{{ old('rights_type', $artwork->rights_type ?? '') }}">
                  <div class="form-text">The type of intellectual property rights (e.g. "Copyright", "Public Domain", "Creative Commons", "Artist's Rights Society (ARS)").</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="rights_holder" class="form-label">Rights holder</label>
                  <input type="text" class="form-control" id="rights_holder" name="rights_holder"
                         value="{{ old('rights_holder', $artwork->rights_holder ?? '') }}">
                  <div class="form-text">The person, estate, or organization that holds the intellectual property rights to this artwork.</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Cataloging ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCataloging">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCataloging" aria-expanded="false" aria-controls="collapseCataloging">
            Cataloging
          </button>
        </h2>
        <div id="collapseCataloging" class="accordion-collapse collapse" aria-labelledby="headingCataloging" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="cataloger_name" class="form-label">Cataloger</label>
                  <input type="text" class="form-control" id="cataloger_name" name="cataloger_name"
                         value="{{ old('cataloger_name', $artwork->cataloger_name ?? '') }}">
                  <div class="form-text">The name of the person who created or last edited this catalogue record.</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="cataloging_date" class="form-label">Cataloging date</label>
                  <input type="text" class="form-control" id="cataloging_date" name="cataloging_date"
                         value="{{ old('cataloging_date', $artwork->cataloging_date ?? '') }}">
                  <div class="form-text">The date this catalogue record was created or last modified (YYYY-MM-DD).</div>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label for="repository_id" class="form-label">Repository</label>
              <select class="form-select" id="repository_id" name="repository_id">
                <option value="">-- Select --</option>
                @foreach($repositories as $repo)
                  <option value="{{ $repo->id }}" @selected(old('repository_id', $artwork->repository_id ?? '') == $repo->id)>
                    {{ $repo->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text">The institution or gallery that holds this artwork.</div>
            </div>
          </div>
        </div>
      </div>

    </div>{{-- end accordion --}}

    {{-- ===== Form actions ===== --}}
    <section class="actions mb-3 mt-3">
      <ul class="actions mb-1 nav gap-2">
        <li>
          @if($isNew)
            <a href="{{ route('gallery.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a>
          @else
            <a href="{{ route('gallery.show', $artwork->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a>
          @endif
        </li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="{{ $isNew ? 'Create' : 'Save' }}"></li>
      </ul>
    </section>
  </form>
@endsection
