@extends('theme::layouts.2col')

@section('title', 'Gallery Cataloguing')
@section('body-class', ($isNew ? 'create' : 'edit') . ' gallery')

@section('sidebar')
<div class="sidebar-content">
  <section id="template-selector" class="sidebar-section">
    <h4>Artwork Template</h4>
    <div class="template-list">
      <a href="#" data-template="painting" class="template-option active"><i class="fas fa-palette"></i> <span>Painting</span></a>
      <a href="#" data-template="sculpture" class="template-option"><i class="fas fa-monument"></i> <span>Sculpture</span></a>
      <a href="#" data-template="photograph" class="template-option"><i class="fas fa-camera"></i> <span>Photograph</span></a>
      <a href="#" data-template="print" class="template-option"><i class="fas fa-print"></i> <span>Print</span></a>
      <a href="#" data-template="mixed_media" class="template-option"><i class="fas fa-layer-group"></i> <span>Mixed Media</span></a>
    </div>
  </section>
  <section id="completeness-meter" class="sidebar-section">
    <h4>Record Completeness</h4>
    <div class="progress-container">
      <div class="progress"><div class="progress-bar bg-danger" id="completeness-bar" style="width: 0%"></div></div>
      <span class="completeness-value" id="completeness-value">0%</span>
    </div>
    <p class="help-text">Fill all required and recommended fields for complete cataloguing.</p>
  </section>
  <section id="cco-reference" class="sidebar-section">
    <h4>Standards Reference</h4>
    <p class="small">This form follows CCO/CDWA standards for artwork cataloguing.</p>
    <a href="http://cco.vrafoundation.org/" target="_blank" class="btn btn-sm btn-cco-guide"><i class="fas fa-external-link-alt"></i> CCO Guide</a>
    <a href="https://www.getty.edu/research/publications/electronic_publications/cdwa/" target="_blank" class="btn btn-sm btn-cco-guide mt-1"><i class="fas fa-external-link-alt"></i> CDWA</a>
  </section>
  <section id="field-legend" class="sidebar-section">
    <h4>Field Legend</h4>
    <ul class="legend-list">
      <li><span class="badge badge-required">Required</span> Must be completed</li>
      <li><span class="badge badge-recommended">Recommended</span> Should be completed</li>
      <li><span class="badge badge-optional">Optional</span> Complete if applicable</li>
    </ul>
  </section>
</div>
@endsection

@section('content')
  <h1 class="multiline">
    Gallery Cataloguing
    <span class="sub">Artwork</span>
  </h1>

  @if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger" role="alert">
      <h5><i class="fas fa-exclamation-triangle"></i> Validation Errors</h5>
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" id="editForm" class="gallery-cataloguing-form"
        action="{{ $isNew ? route('gallery.store') : route('gallery.update', $artwork->slug) }}">
    @csrf
    @if(!$isNew)
      @method('PUT')
    @endif

    <div class="accordion" id="galleryAccordion">

      {{-- ===== Object/Work (CCO Ch 2) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingObjectWork">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseObjectWork" aria-expanded="true" aria-controls="collapseObjectWork">
            Object/Work
            <span class="cco-chapter">CCO Chapter 2</span>
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
              <div class="form-text text-muted small">The type or genre of artwork (CCO: Object/Work Type). Select the most specific applicable type for this artwork.</div>
            </div>

            <div class="mb-3">
              <label for="work_type_qualifier" class="form-label">Work type qualifier</label>
              <select class="form-select" id="work_type_qualifier" name="work_type_qualifier">
                <option value="">-- Select --</option>
                <option value="possibly" @selected(old('work_type_qualifier', $artwork->work_type_qualifier ?? '') === 'possibly')>Possibly</option>
                <option value="probably" @selected(old('work_type_qualifier', $artwork->work_type_qualifier ?? '') === 'probably')>Probably</option>
                <option value="formerly classified as" @selected(old('work_type_qualifier', $artwork->work_type_qualifier ?? '') === 'formerly classified as')>Formerly classified as</option>
              </select>
              <div class="form-text text-muted small">Qualifies uncertainty about the work type. (CCO 2.1.1)</div>
            </div>

            <div class="mb-3">
              <label for="components_count" class="form-label">Components/Parts</label>
              <input type="text" class="form-control" id="components_count" name="components_count"
                     value="{{ old('components_count', $artwork->components_count ?? '') }}">
              <div class="form-text text-muted small">Number and description of physical components. (CCO 2.2)</div>
            </div>

            <div class="mb-3">
              <label for="object_number" class="form-label">Object number <span class="form-required" title="This is a mandatory element.">*</span></label>
              <input type="text" class="form-control" id="object_number" name="object_number"
                     value="{{ old('object_number', $artwork->object_number ?? '') }}">
              <div class="form-text text-muted small">Unique identifier assigned by the repository. (CCO 2.3)</div>
            </div>

            <div class="mb-3">
              <label for="classification" class="form-label">Classification</label>
              <input type="text" class="form-control" id="classification" name="classification"
                     value="{{ old('classification', $artwork->classification ?? '') }}">
              <div class="form-text text-muted small">Classification scheme or category. (CCO 2.1)</div>
            </div>

            <div class="mb-3">
              <label for="identifier" class="form-label">Identifier / Accession number</label>
              <input type="text" class="form-control" id="identifier" name="identifier"
                     value="{{ old('identifier', $artwork->identifier ?? '') }}">
              <div class="form-text text-muted small">A unique numeric or alphanumeric identifier assigned to the object by the holding institution (e.g. accession number, catalogue number).</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Title ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingTitle">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTitle" aria-expanded="false" aria-controls="collapseTitle">
            Titles/Names
            <span class="cco-chapter">CCO Chapter 3</span>
          </button>
        </h2>
        <div id="collapseTitle" class="accordion-collapse collapse" aria-labelledby="headingTitle" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="title" class="form-label">Title <span class="form-required" title="This is a mandatory element.">*</span></label>
              <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title"
                     value="{{ old('title', $artwork->title ?? '') }}" required>
              @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">The primary title of the work. (CCO 3.1)</div>
            </div>
            <div class="mb-3">
              <label for="title_type" class="form-label">Title type</label>
              <select class="form-select" id="title_type" name="title_type">
                <option value="repository" @selected(old('title_type', $artwork->title_type ?? 'repository') === 'repository')>Repository</option>
                <option value="creator" @selected(old('title_type', $artwork->title_type ?? '') === 'creator')>Creator</option>
                <option value="inscribed" @selected(old('title_type', $artwork->title_type ?? '') === 'inscribed')>Inscribed</option>
                <option value="popular" @selected(old('title_type', $artwork->title_type ?? '') === 'popular')>Popular</option>
                <option value="descriptive" @selected(old('title_type', $artwork->title_type ?? '') === 'descriptive')>Descriptive</option>
                <option value="former" @selected(old('title_type', $artwork->title_type ?? '') === 'former')>Former</option>
                <option value="translated" @selected(old('title_type', $artwork->title_type ?? '') === 'translated')>Translated</option>
              </select>
              <div class="form-text text-muted small">The source or nature of the title. (CCO 3.1.1)</div>
            </div>
            <div class="mb-3">
              <label for="title_language" class="form-label">Title language</label>
              <input type="text" class="form-control" id="title_language" name="title_language" value="{{ old('title_language', $artwork->title_language ?? 'eng') }}">
              <div class="form-text text-muted small">Language of the title (ISO 639-2). (CCO 3.1.2)</div>
            </div>
            <div class="mb-3">
              <label for="alternate_titles" class="form-label">Alternate titles</label>
              <input type="text" class="form-control" id="alternate_titles" name="alternate_titles" value="{{ old('alternate_titles', $artwork->alternate_titles ?? '') }}">
              <div class="form-text text-muted small">Other titles by which the work is known. (CCO 3.2)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Creator ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCreator">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCreator" aria-expanded="false" aria-controls="collapseCreator">
            Creator
            <span class="cco-chapter">CCO Chapter 4 (Creator)</span>
          </button>
        </h2>
        <div id="collapseCreator" class="accordion-collapse collapse" aria-labelledby="headingCreator" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="creator_display" class="form-label">Creator (Display) <span class="form-required" title="This is a mandatory element.">*</span></label>
              <input type="text" class="form-control" id="creator_display" name="creator_display"
                     value="{{ old('creator_display', $artwork->creator_display ?? '') }}">
              <div class="form-text text-muted small">Creator name as it should appear in displays. Format: Surname, Forename (Nationality, birth-death). (CCO 4.1)</div>
            </div>
            <div class="mb-3">
              <label for="creator" class="form-label">Creator (Authority) <span class="form-required" title="This is a mandatory element.">*</span></label>
              <input type="text" class="form-control" id="creator" name="creator"
                     value="{{ old('creator', $artwork->creator ?? '') }}" placeholder="Type to search authority records..." autocomplete="off">
              <div class="form-text text-muted small">Link to authority record. (CCO 4.1)</div>
            </div>
            <div class="mb-3">
              <label for="creator_identity" class="form-label">Creator identity</label>
              <input type="text" class="form-control" id="creator_identity" name="creator_identity"
                     value="{{ old('creator_identity', $artwork->creator_identity ?? '') }}">
              <div class="form-text text-muted small">The name of the artist or maker.</div>
            </div>
            <div class="mb-3">
              <label for="creator_role" class="form-label">Creator role</label>
              <select class="form-select" id="creator_role" name="creator_role">
                <option value="">-- Select --</option>
                @foreach($creatorRoles as $cr)
                  <option value="{{ $cr }}" @selected(old('creator_role', $artwork->creator_role ?? '') == $cr)>{{ $cr }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">The role of the creator. (CCO 4.1.1)</div>
            </div>
            <div class="mb-3">
              <label for="attribution_qualifier" class="form-label">Attribution qualifier</label>
              <select class="form-select" id="attribution_qualifier" name="attribution_qualifier">
                <option value="">(No qualifier)</option>
                <option value="attributed_to" @selected(old('attribution_qualifier', $artwork->attribution_qualifier ?? '') === 'attributed_to')>Attributed to</option>
                <option value="workshop_of" @selected(old('attribution_qualifier', $artwork->attribution_qualifier ?? '') === 'workshop_of')>Workshop of</option>
                <option value="studio_of" @selected(old('attribution_qualifier', $artwork->attribution_qualifier ?? '') === 'studio_of')>Studio of</option>
                <option value="circle_of" @selected(old('attribution_qualifier', $artwork->attribution_qualifier ?? '') === 'circle_of')>Circle of</option>
                <option value="school_of" @selected(old('attribution_qualifier', $artwork->attribution_qualifier ?? '') === 'school_of')>School of</option>
                <option value="follower_of" @selected(old('attribution_qualifier', $artwork->attribution_qualifier ?? '') === 'follower_of')>Follower of</option>
                <option value="manner_of" @selected(old('attribution_qualifier', $artwork->attribution_qualifier ?? '') === 'manner_of')>Manner of</option>
                <option value="after" @selected(old('attribution_qualifier', $artwork->attribution_qualifier ?? '') === 'after')>After</option>
                <option value="copy_after" @selected(old('attribution_qualifier', $artwork->attribution_qualifier ?? '') === 'copy_after')>Copy after</option>
              </select>
              <div class="form-text text-muted small">Qualifies degree of certainty about attribution. (CCO 4.1.2)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Creation ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCreation">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCreation" aria-expanded="false" aria-controls="collapseCreation">
            Creation
            <span class="cco-chapter">CCO Chapter 4</span>
          </button>
        </h2>
        <div id="collapseCreation" class="accordion-collapse collapse" aria-labelledby="headingCreation" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="creation_date_display" class="form-label">Date (display)</label>
              <input type="text" class="form-control" id="creation_date_display" name="creation_date_display"
                     value="{{ old('creation_date_display', $artwork->creation_date_display ?? '') }}">
              <div class="form-text text-muted small">A free-text date for display purposes (e.g. "ca. 1885", "early 20th century", "1965-1970"). Enter the date as it should appear to users.</div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="creation_date_earliest" class="form-label">Earliest date</label>
                  <input type="text" class="form-control" id="creation_date_earliest" name="creation_date_earliest"
                         value="{{ old('creation_date_earliest', $artwork->creation_date_earliest ?? '') }}">
                  <div class="form-text text-muted small">The earliest possible creation date in ISO 8601 format (YYYY-MM-DD or YYYY). Used for date range searching.</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="creation_date_latest" class="form-label">Latest date</label>
                  <input type="text" class="form-control" id="creation_date_latest" name="creation_date_latest"
                         value="{{ old('creation_date_latest', $artwork->creation_date_latest ?? '') }}">
                  <div class="form-text text-muted small">The latest possible creation date in ISO 8601 format (YYYY-MM-DD or YYYY). Used for date range searching.</div>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label for="creation_place" class="form-label">Place of creation</label>
              <input type="text" class="form-control" id="creation_place" name="creation_place"
                     value="{{ old('creation_place', $artwork->creation_place ?? '') }}" placeholder="Type to search places..." autocomplete="off">
              <div class="form-text text-muted small">Geographic location where the work was created. (CCO 4.3)</div>
            </div>

            <div class="mb-3">
              <label for="culture" class="form-label">Culture/People</label>
              <input type="text" class="form-control" id="culture" name="culture" value="{{ old('culture', $artwork->culture ?? '') }}" placeholder="Type to search..." autocomplete="off">
              <div class="form-text text-muted small">Culture, people, or nationality associated with the creation. (CCO 4.4)</div>
            </div>

            <div class="mb-3">
              <label for="school_group" class="form-label">School/Group</label>
              <input type="text" class="form-control" id="school_group" name="school_group" value="{{ old('school_group', $artwork->school_group ?? '') }}" placeholder="Type to search..." autocomplete="off">
              <div class="form-text text-muted small">The school of art or artistic group. (CCO 5.3)</div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="style" class="form-label">Style</label>
                  <input type="text" class="form-control" id="style" name="style"
                         value="{{ old('style', $artwork->style ?? '') }}">
                  <div class="form-text text-muted small">The visual style of the work (e.g. "Impressionism", "Art Nouveau", "Abstract Expressionism").</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="period" class="form-label">Period</label>
                  <input type="text" class="form-control" id="period" name="period"
                         value="{{ old('period', $artwork->period ?? '') }}">
                  <div class="form-text text-muted small">The broad cultural or chronological period (e.g. "Renaissance", "Modern", "Contemporary").</div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="movement" class="form-label">Movement</label>
                  <input type="text" class="form-control" id="movement" name="movement"
                         value="{{ old('movement', $artwork->movement ?? '') }}">
                  <div class="form-text text-muted small">The art movement the artwork belongs to (e.g. "Cubism", "Surrealism", "Pop Art").</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="school" class="form-label">School</label>
                  <input type="text" class="form-control" id="school" name="school"
                         value="{{ old('school', $artwork->school ?? '') }}">
                  <div class="form-text text-muted small">The art school or regional tradition (e.g. "Dutch School", "Bauhaus", "Hudson River School").</div>
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
            <span class="cco-chapter">CCO Chapter 6</span>
          </button>
        </h2>
        <div id="collapseMeasurements" class="accordion-collapse collapse" aria-labelledby="headingMeasurements" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="dimensions_display" class="form-label">Dimensions (Display)</label>
              <input type="text" class="form-control" id="dimensions_display" name="dimensions_display"
                     value="{{ old('dimensions_display', $artwork->dimensions_display ?? '') }}">
              <div class="form-text text-muted small">Dimensions as displayed, e.g. "72.4 x 91.4 cm". (CCO 6.1)</div>
            </div>
            <div class="row">
              <div class="col-md-3 mb-3">
                <label for="height_value" class="form-label">Height</label>
                <input type="text" class="form-control" id="height_value" name="height_value" value="{{ old('height_value', $artwork->height_value ?? '') }}">
              </div>
              <div class="col-md-3 mb-3">
                <label for="width_value" class="form-label">Width</label>
                <input type="text" class="form-control" id="width_value" name="width_value" value="{{ old('width_value', $artwork->width_value ?? '') }}">
              </div>
              <div class="col-md-3 mb-3">
                <label for="depth_value" class="form-label">Depth</label>
                <input type="text" class="form-control" id="depth_value" name="depth_value" value="{{ old('depth_value', $artwork->depth_value ?? '') }}">
              </div>
              <div class="col-md-3 mb-3">
                <label for="weight_value" class="form-label">Weight</label>
                <input type="text" class="form-control" id="weight_value" name="weight_value" value="{{ old('weight_value', $artwork->weight_value ?? '') }}">
              </div>
            </div>
            <div class="mb-3">
              <label for="dimension_notes" class="form-label">Dimension notes</label>
              <textarea class="form-control" id="dimension_notes" name="dimension_notes" rows="2">{{ old('dimension_notes', $artwork->dimension_notes ?? '') }}</textarea>
              <div class="form-text text-muted small">Notes about how measurements were taken. (CCO 6.6)</div>
            </div>
            <div class="mb-3">
              <label for="measurements" class="form-label">Measurements (legacy)</label>
              <textarea class="form-control" id="measurements" name="measurements" rows="3">{{ old('measurements', $artwork->measurements ?? '') }}</textarea>
            </div>
            <div class="mb-3">
              <label for="dimensions" class="form-label">Dimensions (legacy)</label>
              <textarea class="form-control" id="dimensions" name="dimensions" rows="3">{{ old('dimensions', $artwork->dimensions ?? '') }}</textarea>
              <div class="form-text text-muted small">Additional or structured dimensional information (height, width, depth, weight, diameter). Include units and specify whether framed or unframed.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Materials/Techniques ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingMaterials">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMaterials" aria-expanded="false" aria-controls="collapseMaterials">
            Materials / Techniques
            <span class="cco-chapter">CCO Chapter 7</span>
          </button>
        </h2>
        <div id="collapseMaterials" class="accordion-collapse collapse" aria-labelledby="headingMaterials" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="materials_display" class="form-label">Medium (Display) <span class="form-required" title="Required">*</span></label>
              <input type="text" class="form-control" id="materials_display" name="materials_display"
                     value="{{ old('materials_display', $artwork->materials_display ?? '') }}">
              <div class="form-text text-muted small">Medium as displayed, e.g. "oil on canvas". (CCO 7.1)</div>
            </div>
            <div class="mb-3">
              <label for="materials" class="form-label">Materials (Indexed)</label>
              <textarea class="form-control" id="materials" name="materials" rows="3">{{ old('materials', $artwork->materials ?? '') }}</textarea>
              <div class="form-text text-muted small">Individual materials for searching. (CCO 7.2)</div>
            </div>
            <div class="mb-3">
              <label for="support" class="form-label">Support</label>
              <input type="text" class="form-control" id="support" name="support" value="{{ old('support', $artwork->support ?? '') }}">
              <div class="form-text text-muted small">The material on which the work is executed (e.g. canvas, paper). (CCO 7.4)</div>
            </div>

            <div class="mb-3">
              <label for="techniques" class="form-label">Techniques</label>
              <textarea class="form-control" id="techniques" name="techniques" rows="3">{{ old('techniques', $artwork->techniques ?? '') }}</textarea>
              <div class="form-text text-muted small">The techniques or processes used to create the artwork (e.g. "Impasto", "Lost-wax casting", "Screen printing", "Collage").</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Subject ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingSubject">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSubject" aria-expanded="false" aria-controls="collapseSubject">
            Subject Matter
            <span class="cco-chapter">CCO Chapter 8</span>
          </button>
        </h2>
        <div id="collapseSubject" class="accordion-collapse collapse" aria-labelledby="headingSubject" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="subject_display" class="form-label">Subject (Display)</label>
              <textarea class="form-control" id="subject_display" name="subject_display" rows="3">{{ old('subject_display', $artwork->subject_display ?? '') }}</textarea>
              <div class="form-text text-muted small">Subject as it should appear in displays. (CCO 8.1)</div>
            </div>
            <div class="mb-3">
              <label for="subjects_depicted" class="form-label">Subjects depicted</label>
              <input type="text" class="form-control" id="subjects_depicted" name="subjects_depicted" value="{{ old('subjects_depicted', $artwork->subjects_depicted ?? '') }}" placeholder="Type to search..." autocomplete="off">
              <div class="form-text text-muted small">Specific subjects depicted. (CCO 8.2)</div>
            </div>
            <div class="mb-3">
              <label for="iconography" class="form-label">Iconography</label>
              <textarea class="form-control" id="iconography" name="iconography" rows="3">{{ old('iconography', $artwork->iconography ?? '') }}</textarea>
              <div class="form-text text-muted small">Iconographic themes, symbols, or narratives. (CCO 8.3)</div>
            </div>
            <div class="mb-3">
              <label for="named_subjects" class="form-label">Named subjects</label>
              <input type="text" class="form-control" id="named_subjects" name="named_subjects" value="{{ old('named_subjects', $artwork->named_subjects ?? '') }}">
              <div class="form-text text-muted small">Named people, places, or events depicted. (CCO 8.4)</div>
            </div>
            <div class="mb-3">
              <label for="scope_and_content" class="form-label">Description</label>
              <textarea class="form-control" id="scope_and_content" name="scope_and_content" rows="5">{{ old('scope_and_content', $artwork->scope_and_content ?? '') }}</textarea>
              <div class="form-text text-muted small">Free-text description of the work. (CCO 11.1)</div>
            </div>
            <div class="mb-3">
              <label for="description" class="form-label">Description (CCO)</label>
              <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $artwork->description ?? '') }}</textarea>
              <div class="form-text text-muted small">Narrative description supplementing other fields. (CCO 11.1)</div>
            </div>
            <div class="mb-3">
              <label for="physical_description" class="form-label">Physical description</label>
              <textarea class="form-control" id="physical_description" name="physical_description" rows="3">{{ old('physical_description', $artwork->physical_description ?? '') }}</textarea>
              <div class="form-text text-muted small">Physical characteristics not covered elsewhere. (CCO 11.2)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Inscriptions ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingInscriptions">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseInscriptions" aria-expanded="false" aria-controls="collapseInscriptions">
            Inscriptions
            <span class="cco-chapter">CCO Chapter 9</span>
          </button>
        </h2>
        <div id="collapseInscriptions" class="accordion-collapse collapse" aria-labelledby="headingInscriptions" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="inscriptions" class="form-label">Inscriptions</label>
              <textarea class="form-control" id="inscriptions" name="inscriptions" rows="3">{{ old('inscriptions', $artwork->inscriptions ?? '') }}</textarea>
              <div class="form-text text-muted small">Text inscriptions on the work. (CCO 9.1)</div>
            </div>
            <div class="mb-3">
              <label for="signature" class="form-label">Signature</label>
              <textarea class="form-control" id="signature" name="signature" rows="2">{{ old('signature', $artwork->signature ?? '') }}</textarea>
              <div class="form-text text-muted small">Description of the artist's signature. (CCO 9.2)</div>
            </div>
            <div class="mb-3">
              <label for="marks" class="form-label">Marks/Labels</label>
              <textarea class="form-control" id="marks" name="marks" rows="2">{{ old('marks', $artwork->marks ?? '') }}</textarea>
              <div class="form-text text-muted small">Collector's marks, labels, stamps. (CCO 9.3)</div>
            </div>
            <div class="mb-3">
              <label for="inscription" class="form-label">Inscription (legacy)</label>
              <textarea class="form-control" id="inscription" name="inscription" rows="3">{{ old('inscription', $artwork->inscription ?? '') }}</textarea>
              <div class="form-text text-muted small">Transcription of text on the artwork, including signature and location.</div>
            </div>

            <div class="mb-3">
              <label for="mark_description" class="form-label">Marks</label>
              <textarea class="form-control" id="mark_description" name="mark_description" rows="3">{{ old('mark_description', $artwork->mark_description ?? '') }}</textarea>
              <div class="form-text text-muted small">Description of any marks, labels, stamps, or seals found on the artwork other than inscriptions. Include collector marks, gallery labels, exhibition labels, and customs stamps.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== State/Edition (CCO Ch 10) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingStateEdition">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStateEdition" aria-expanded="false" aria-controls="collapseStateEdition">
            State/Edition
            <span class="cco-chapter">CCO Chapter 10</span>
          </button>
        </h2>
        <div id="collapseStateEdition" class="accordion-collapse collapse" aria-labelledby="headingStateEdition" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="edition_number" class="form-label">Edition number</label>
                <input type="text" class="form-control" id="edition_number" name="edition_number" value="{{ old('edition_number', $artwork->edition_number ?? '') }}">
                <div class="form-text text-muted small">Number of this impression within the edition. (CCO 10.1)</div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="edition_size" class="form-label">Edition size</label>
                <input type="text" class="form-control" id="edition_size" name="edition_size" value="{{ old('edition_size', $artwork->edition_size ?? '') }}">
                <div class="form-text text-muted small">Total number of impressions. (CCO 10.2)</div>
              </div>
            </div>
            <div class="mb-3">
              <label for="state" class="form-label">State</label>
              <input type="text" class="form-control" id="state" name="state" value="{{ old('state', $artwork->state ?? '') }}">
              <div class="form-text text-muted small">The state of the print or edition. (CCO 10.3)</div>
            </div>
            <div class="mb-3">
              <label for="impression_quality" class="form-label">Impression quality</label>
              <input type="text" class="form-control" id="impression_quality" name="impression_quality" value="{{ old('impression_quality', $artwork->impression_quality ?? '') }}">
              <div class="form-text text-muted small">Quality assessment of the impression. (CCO 10.4)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Condition ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCondition">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCondition" aria-expanded="false" aria-controls="collapseCondition">
            Condition
            <span class="cco-chapter">CCO Chapter 12</span>
          </button>
        </h2>
        <div id="collapseCondition" class="accordion-collapse collapse" aria-labelledby="headingCondition" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="condition_summary" class="form-label">Condition summary</label>
              <textarea class="form-control" id="condition_summary" name="condition_summary" rows="3">{{ old('condition_summary', $artwork->condition_summary ?? '') }}</textarea>
              <div class="form-text text-muted small">Brief summary of the current condition. (CCO 12.1)</div>
            </div>
            <div class="mb-3">
              <label for="condition_notes" class="form-label">Condition notes</label>
              <textarea class="form-control" id="condition_notes" name="condition_notes" rows="3">{{ old('condition_notes', $artwork->condition_notes ?? '') }}</textarea>
              <div class="form-text text-muted small">Detailed notes on the condition. (CCO 12.2)</div>
            </div>
            <div class="mb-3">
              <label for="condition_term" class="form-label">Condition term</label>
              <input type="text" class="form-control" id="condition_term" name="condition_term"
                     value="{{ old('condition_term', $artwork->condition_term ?? '') }}">
              <div class="form-text text-muted small">A standardized term describing the overall condition (e.g. "Excellent", "Good", "Fair", "Poor", "Requires conservation").</div>
            </div>

            <div class="mb-3">
              <label for="condition_description" class="form-label">Condition notes</label>
              <textarea class="form-control" id="condition_description" name="condition_description" rows="4">{{ old('condition_description', $artwork->condition_description ?? '') }}</textarea>
              <div class="form-text text-muted small">A detailed narrative of the current physical condition. Note any damage, repairs, losses, discoloration, or conservation treatments. Record the date of the most recent condition assessment.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Provenance ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingProvenance">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProvenance" aria-expanded="false" aria-controls="collapseProvenance">
            Current Location
            <span class="cco-chapter">CCO Chapter 13</span>
          </button>
        </h2>
        <div id="collapseProvenance" class="accordion-collapse collapse" aria-labelledby="headingProvenance" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="repository" class="form-label">Repository <span class="form-required" title="Required">*</span></label>
              <input type="text" class="form-control" id="repository" name="repository" value="{{ old('repository', $artwork->repository ?? '') }}" placeholder="Type to search..." autocomplete="off">
              <div class="form-text text-muted small">The repository holding this work. (CCO 13.1)</div>
            </div>
            <div class="mb-3">
              <label for="location_within_repository" class="form-label">Location</label>
              <input type="text" class="form-control" id="location_within_repository" name="location_within_repository" value="{{ old('location_within_repository', $artwork->location_within_repository ?? '') }}">
              <div class="form-text text-muted small">Location within the repository. (CCO 13.2)</div>
            </div>
            <div class="mb-3">
              <label for="credit_line" class="form-label">Credit line</label>
              <input type="text" class="form-control" id="credit_line" name="credit_line" value="{{ old('credit_line', $artwork->credit_line ?? '') }}">
              <div class="form-text text-muted small">Credit line for display. (CCO 13.3)</div>
            </div>
            <div class="mb-3">
              <label for="provenance" class="form-label">Provenance</label>
              <textarea class="form-control" id="provenance" name="provenance" rows="4">{{ old('provenance', $artwork->provenance ?? '') }}</textarea>
              <div class="form-text text-muted small">The ownership history of the artwork from creation to present. List each owner in chronological order with dates, methods of transfer (purchase, gift, bequest, auction), and sources of information.</div>
            </div>

            <div class="mb-3">
              <label for="current_location" class="form-label">Current location</label>
              <input type="text" class="form-control" id="current_location" name="current_location"
                     value="{{ old('current_location', $artwork->current_location ?? '') }}">
              <div class="form-text text-muted small">The current physical location of the artwork within the gallery or storage facility (e.g. "Gallery 3, West Wall" or "Vault B, Rack 12").</div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="rights_type" class="form-label">Rights type</label>
                  <input type="text" class="form-control" id="rights_type" name="rights_type"
                         value="{{ old('rights_type', $artwork->rights_type ?? '') }}">
                  <div class="form-text text-muted small">The type of intellectual property rights (e.g. "Copyright", "Public Domain", "Creative Commons", "Artist's Rights Society (ARS)").</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="rights_holder" class="form-label">Rights holder</label>
                  <input type="text" class="form-control" id="rights_holder" name="rights_holder"
                         value="{{ old('rights_holder', $artwork->rights_holder ?? '') }}">
                  <div class="form-text text-muted small">The person, estate, or organization that holds the intellectual property rights to this artwork.</div>
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
            Rights
            <span class="cco-chapter">CCO Chapter 15</span>
          </button>
        </h2>
        <div id="collapseCataloging" class="accordion-collapse collapse" aria-labelledby="headingCataloging" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="rights_statement" class="form-label">Rights statement</label>
              <textarea class="form-control" id="rights_statement" name="rights_statement" rows="3">{{ old('rights_statement', $artwork->rights_statement ?? '') }}</textarea>
              <div class="form-text text-muted small">Statement of rights associated with the work. (CCO 15.1)</div>
            </div>
            <div class="mb-3">
              <label for="copyright_holder" class="form-label">Copyright holder</label>
              <input type="text" class="form-control" id="copyright_holder" name="copyright_holder" value="{{ old('copyright_holder', $artwork->copyright_holder ?? '') }}">
              <div class="form-text text-muted small">Name of the copyright holder. (CCO 15.2)</div>
            </div>
            <div class="mb-3">
              <label for="reproduction_conditions" class="form-label">Reproduction conditions</label>
              <textarea class="form-control" id="reproduction_conditions" name="reproduction_conditions" rows="2">{{ old('reproduction_conditions', $artwork->reproduction_conditions ?? '') }}</textarea>
              <div class="form-text text-muted small">Conditions governing reproduction. (CCO 15.3)</div>
            </div>
            <div class="mb-3">
              <label for="related_works" class="form-label">Related works</label>
              <textarea class="form-control" id="related_works" name="related_works" rows="3">{{ old('related_works', $artwork->related_works ?? '') }}</textarea>
              <div class="form-text text-muted small">Identify related works. (CCO 14.1)</div>
            </div>
            <div class="mb-3">
              <label for="relationship_type" class="form-label">Relationship type</label>
              <input type="text" class="form-control" id="relationship_type" name="relationship_type" value="{{ old('relationship_type', $artwork->relationship_type ?? '') }}">
              <div class="form-text text-muted small">Type of relationship to related work. (CCO 14.2)</div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="cataloger_name" class="form-label">Cataloger</label>
                  <input type="text" class="form-control" id="cataloger_name" name="cataloger_name"
                         value="{{ old('cataloger_name', $artwork->cataloger_name ?? '') }}">
                  <div class="form-text text-muted small">The name of the person who created or last edited this catalogue record.</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="cataloging_date" class="form-label">Cataloging date</label>
                  <input type="text" class="form-control" id="cataloging_date" name="cataloging_date"
                         value="{{ old('cataloging_date', $artwork->cataloging_date ?? '') }}">
                  <div class="form-text text-muted small">The date this catalogue record was created or last modified (YYYY-MM-DD).</div>
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
              <div class="form-text text-muted small">The institution or gallery that holds this artwork.</div>
            </div>
          </div>
        </div>
      </div>

    </div>{{-- end accordion --}}

    {{-- ===== Item Physical Location ===== --}}
    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="heading-physical-location">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#collapse-physical-location"
                  aria-expanded="false" aria-controls="collapse-physical-location"
                  style="background-color: var(--ahg-primary, #005837) !important; color: #fff !important;">
            Item Physical Location
            <span class="cco-chapter">Storage &amp; Access</span>
          </button>
        </h2>
        <div id="collapse-physical-location" class="accordion-collapse collapse" aria-labelledby="heading-physical-location">
          <div class="accordion-body">

            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Storage container</label>
                <select name="item_physical_object_id" class="form-select">
                  <option value="">-- Select container --</option>
                  @foreach($physicalObjects ?? [] as $poId => $poName)
                    <option value="{{ $poId }}" @selected(old('item_physical_object_id', $itemLocation['physical_object_id'] ?? '') == $poId)>{{ $poName }}</option>
                  @endforeach
                </select>
                <small class="form-text text-muted">Link to a physical storage container</small>
              </div>
              <div class="col-md-6">
                <label class="form-label">Item barcode</label>
                <input type="text" name="item_barcode" class="form-control" value="{{ old('item_barcode', $itemLocation['barcode'] ?? '') }}">
              </div>
            </div>

            <h6 class="text-white py-2 px-3 mb-3" style="background-color: var(--ahg-primary, #005837);"><i class="fas fa-box me-2"></i>Location within container</h6>
            <div class="row mb-3">
              <div class="col-md-2">
                <label class="form-label">Box</label>
                <input type="text" name="item_box_number" class="form-control" value="{{ old('item_box_number', $itemLocation['box_number'] ?? '') }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Folder</label>
                <input type="text" name="item_folder_number" class="form-control" value="{{ old('item_folder_number', $itemLocation['folder_number'] ?? '') }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Shelf</label>
                <input type="text" name="item_shelf" class="form-control" value="{{ old('item_shelf', $itemLocation['shelf'] ?? '') }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Row</label>
                <input type="text" name="item_row" class="form-control" value="{{ old('item_row', $itemLocation['row'] ?? '') }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Position</label>
                <input type="text" name="item_position" class="form-control" value="{{ old('item_position', $itemLocation['position'] ?? '') }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Item #</label>
                <input type="text" name="item_item_number" class="form-control" value="{{ old('item_item_number', $itemLocation['item_number'] ?? '') }}">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-3">
                <label class="form-label">Extent value</label>
                <input type="number" step="0.01" name="item_extent_value" class="form-control" value="{{ old('item_extent_value', $itemLocation['extent_value'] ?? '') }}">
              </div>
              <div class="col-md-3">
                <label class="form-label">Extent unit</label>
                <select name="item_extent_unit" class="form-select">
                  <option value="">-- Select --</option>
                  @foreach(['items' => 'Items', 'pages' => 'Pages', 'folders' => 'Folders', 'boxes' => 'Boxes', 'cm' => 'cm', 'm' => 'metres', 'cubic_m' => 'cubic metres'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('item_extent_unit', $itemLocation['extent_unit'] ?? '') == $val)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <h6 class="text-white py-2 px-3 mb-3" style="background-color: var(--ahg-primary, #005837);"><i class="fas fa-clipboard-check me-2"></i>Condition &amp; Status</h6>
            <div class="row mb-3">
              <div class="col-md-3">
                <label class="form-label">Condition</label>
                <select name="item_condition_status" class="form-select">
                  <option value="">-- Select --</option>
                  @foreach(['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor', 'critical' => 'Critical'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('item_condition_status', $itemLocation['condition_status'] ?? '') == $val)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Access status</label>
                <select name="item_access_status" class="form-select">
                  @foreach(['available' => 'Available', 'in_use' => 'In Use', 'restricted' => 'Restricted', 'offsite' => 'Offsite', 'missing' => 'Missing'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('item_access_status', $itemLocation['access_status'] ?? 'available') == $val)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Condition notes</label>
                <input type="text" name="item_condition_notes" class="form-control" value="{{ old('item_condition_notes', $itemLocation['condition_notes'] ?? '') }}">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-12">
                <label class="form-label">Location notes</label>
                <textarea name="item_location_notes" class="form-control" rows="2">{{ old('item_location_notes', $itemLocation['notes'] ?? '') }}</textarea>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

    {{-- ===== Administration Area ===== --}}
    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="admin-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                  data-bs-target="#admin-collapse" aria-expanded="false" aria-controls="admin-collapse"
                  style="background-color: var(--ahg-primary, #005837) !important; color: #fff !important;">
            Administration area
          </button>
        </h2>
        <div id="admin-collapse" class="accordion-collapse collapse" aria-labelledby="admin-heading">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label fw-bold">Source language</label>
                  <div>{{ $sourceCulture ?? 'English' }}</div>
                </div>
                @if(!$isNew && isset($artwork->updated_at) && $artwork->updated_at)
                <div class="mb-3">
                  <label class="form-label fw-bold">Last updated</label>
                  <div>{{ \Carbon\Carbon::parse($artwork->updated_at)->format('F j, Y, g:i a') }}</div>
                </div>
                @endif
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="displayStandard" class="form-label fw-bold">Display standard</label>
                  <select name="displayStandard" id="displayStandard" class="form-select">
                    @foreach($displayStandards ?? [] as $dsId => $dsName)
                      <option value="{{ $dsId }}" @selected(old('displayStandard', $currentDisplayStandard ?? '') == $dsId)>{{ $dsName }}</option>
                    @endforeach
                  </select>
                  <small class="form-text text-muted">Select the display standard for this record</small>
                </div>
                <div class="mb-3">
                  <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="displayStandardUpdateDescendants"
                           name="displayStandardUpdateDescendants" value="1">
                    <label class="form-check-label" for="displayStandardUpdateDescendants">
                      Make this selection the new default for existing children
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      <li>
        @if($isNew)
          <a href="{{ route('gallery.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a>
        @else
          <a href="{{ route('gallery.show', $artwork->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a>
        @endif
      </li>
    </ul>
  </form>

@push('js')
<script>
(function() {
  'use strict';
  document.addEventListener('DOMContentLoaded', function() {
    updateCompleteness();
    var form = document.getElementById('editForm');
    if (form) {
      form.addEventListener('input', function() { setTimeout(updateCompleteness, 100); });
      form.addEventListener('change', function() { setTimeout(updateCompleteness, 100); });
    }
  });
  function updateCompleteness() {
    var form = document.getElementById('editForm');
    if (!form) return;
    var fields = form.querySelectorAll('input[name]:not([type="hidden"]):not([type="submit"]), select[name], textarea[name]');
    var total = fields.length, filled = 0;
    fields.forEach(function(f) { if (f.value && f.value.trim() !== '') filled++; });
    var pct = total > 0 ? Math.round((filled / total) * 100) : 0;
    var bar = document.getElementById('completeness-bar');
    var val = document.getElementById('completeness-value');
    if (bar && val) {
      bar.style.width = pct + '%'; val.textContent = pct + '%';
      bar.className = 'progress-bar ' + (pct >= 80 ? 'bg-success' : pct >= 50 ? 'bg-warning' : 'bg-danger');
    }
  }
})();
</script>
@endpush

@push('css')
<style>
.sidebar-section { background: #fff; border-radius: 8px; padding: 15px; margin-bottom: 15px; border: 1px solid #ddd; }
.sidebar-section h4 { color: var(--ahg-primary, #005837); font-size: 13px; font-weight: 700; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e9ecef; }
.template-list { display: flex; flex-wrap: wrap; gap: 6px; }
.template-option { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 15px; font-size: 12px; text-decoration: none; color: #333; background: #f8f9fa; border: 1px solid #e0e0e0; transition: all 0.2s; }
.template-option:hover { background: #e9ecef; text-decoration: none; color: var(--ahg-primary, #005837); }
.template-option.active { background: var(--ahg-primary, #005837); color: #fff; border-color: var(--ahg-primary, #005837); }
.template-option i { margin-right: 5px; font-size: 11px; }
.progress-container { display: flex; align-items: center; gap: 10px; }
.progress { flex: 1; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; }
.progress-bar { height: 100%; border-radius: 10px; transition: width 0.3s; }
.completeness-value { font-weight: 700; min-width: 40px; }
.btn-cco-guide { background: var(--ahg-primary, #005837); color: #fff; border: none; display: block; width: 100%; text-align: center; }
.btn-cco-guide:hover { background: #145043; color: #fff; }
.legend-list { list-style: none; padding: 0; margin: 0; }
.legend-list li { margin-bottom: 6px; font-size: 12px; }
.badge-required { background: #e74c3c; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 10px; }
.badge-recommended { background: #f39c12; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 10px; }
.badge-optional { background: #95a5a6; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 10px; }
.gallery-cataloguing-form .accordion-item { border: none; margin-bottom: 10px; border-radius: 8px; overflow: hidden; border: 1px solid #ddd; }
.gallery-cataloguing-form .accordion-button { background: var(--ahg-primary, #005837) !important; color: #fff !important; }
.gallery-cataloguing-form .accordion-button:not(.collapsed) { background: var(--ahg-primary, #005837) !important; color: #fff !important; }
.gallery-cataloguing-form .accordion-button.collapsed { background-color: var(--ahg-primary, #005837); color: #fff; }
.gallery-cataloguing-form .accordion-button::after { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'//%3e%3c/svg%3e"); }
.gallery-cataloguing-form .accordion-button:focus { box-shadow: none; }
.gallery-cataloguing-form .accordion-body { padding: 20px; background: #fff; }
.cco-chapter { margin-left: 15px; float: right; font-size: 11px; opacity: 1; font-weight: normal; }
h1.multiline .sub { display: block; font-size: 0.6em; color: #666; font-weight: normal; }
.help-text { font-size: 13px; color: #666; margin: 0; }
</style>
@endpush
@endsection
