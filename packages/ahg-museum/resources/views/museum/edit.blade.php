@extends('theme::layouts.1col')

@section('title', $isNew ? 'CCO Cataloguing' : 'CCO Cataloguing')
@section('body-class', ($isNew ? 'create' : 'edit') . ' museum')

@section('content')
  <h1 class="multiline">
    CCO Cataloguing
    <span class="sub">Museum Object</span>
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

  <form method="POST" id="editForm" class="cco-cataloguing-form"
        action="{{ $isNew ? route('museum.store') : route('museum.update', $museum->slug) }}">
    @csrf
    @if(!$isNew)
      @method('PUT')
    @endif

    <div class="accordion" id="museumAccordion">

      {{-- ===== Object Identification ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingIdentification">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIdentification" aria-expanded="true" aria-controls="collapseIdentification">
            Object/Work
            <span class="cco-chapter">CCO Chapter 2</span>
          </button>
        </h2>
        <div id="collapseIdentification" class="accordion-collapse collapse show" aria-labelledby="headingIdentification" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="work_type" class="form-label">Work type</label>
              <select class="form-select" id="work_type" name="work_type">
                <option value="">-- Select --</option>
                @foreach($workTypes as $wt)
                  <option value="{{ $wt }}" @selected(old('work_type', $museum->work_type ?? '') === $wt)>{{ $wt }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">The broad category of the work (e.g. Painting, Sculpture, Drawing).</div>
            </div>

            <div class="mb-3">
              <label for="object_type" class="form-label">Object type</label>
              <input type="text" class="form-control" id="object_type" name="object_type"
                     value="{{ old('object_type', $museum->object_type ?? '') }}">
              <div class="form-text text-muted small">More specific object designation within the work type.</div>
            </div>

            <div class="mb-3">
              <label for="classification" class="form-label">Classification</label>
              <input type="text" class="form-control" id="classification" name="classification"
                     value="{{ old('classification', $museum->classification ?? '') }}">
              <div class="form-text text-muted small">Systematic arrangement or categorization of the object.</div>
            </div>

            <div class="mb-3">
              <label for="object_class" class="form-label">Object class</label>
              <input type="text" class="form-control" id="object_class" name="object_class"
                     value="{{ old('object_class', $museum->object_class ?? '') }}">
              <div class="form-text text-muted small">The class or genre to which this object belongs.</div>
            </div>

            <div class="mb-3">
              <label for="object_category" class="form-label">Object category</label>
              <input type="text" class="form-control" id="object_category" name="object_category"
                     value="{{ old('object_category', $museum->object_category ?? '') }}">
              <div class="form-text text-muted small">A broader category for grouping objects of similar type.</div>
            </div>

            <div class="mb-3">
              <label for="object_sub_category" class="form-label">Object sub-category</label>
              <input type="text" class="form-control" id="object_sub_category" name="object_sub_category"
                     value="{{ old('object_sub_category', $museum->object_sub_category ?? '') }}">
              <div class="form-text text-muted small">A more specific sub-category within the object category.</div>
            </div>

            <div class="mb-3">
              <label for="identifier" class="form-label">Identifier</label>
              <input type="text" class="form-control" id="identifier" name="identifier"
                     value="{{ old('identifier', $museum->identifier ?? '') }}">
              <div class="form-text text-muted small">The accession or catalogue number assigned to this object.</div>
            </div>

            <div class="mb-3">
              <label for="record_type" class="form-label">Record type</label>
              <input type="text" class="form-control" id="record_type" name="record_type"
                     value="{{ old('record_type', $museum->record_type ?? '') }}">
              <div class="form-text text-muted small">The type of catalogue record (e.g. item, group, volume).</div>
            </div>

            <div class="mb-3">
              <label for="record_level" class="form-label">Record level</label>
              <input type="text" class="form-control" id="record_level" name="record_level"
                     value="{{ old('record_level', $museum->record_level ?? '') }}">
              <div class="form-text text-muted small">Hierarchical level of this catalogue record.</div>
            </div>

            <div class="mb-3">
              <label for="level_of_description_id" class="form-label">Level of description</label>
              <select class="form-select" id="level_of_description_id" name="level_of_description_id">
                <option value="">-- Select --</option>
                @foreach($levels as $level)
                  <option value="{{ $level->id }}" @selected(old('level_of_description_id', $museum->level_of_description_id ?? '') == $level->id)>
                    {{ $level->name }}
                  </option>
                @endforeach
              </select>
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
        <div id="collapseTitle" class="accordion-collapse collapse" aria-labelledby="headingTitle" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
              <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title"
                     value="{{ old('title', $museum->title ?? '') }}" required>
              @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <div class="form-text text-muted small">The primary name or title of the museum object. Required.</div>
            </div>

            <div class="mb-3">
              <label for="alternate_title" class="form-label">Alternate title</label>
              <input type="text" class="form-control" id="alternate_title" name="alternate_title"
                     value="{{ old('alternate_title', $museum->alternate_title ?? '') }}">
              <div class="form-text text-muted small">Any alternative, former, or translated titles for this object.</div>
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
        <div id="collapseCreator" class="accordion-collapse collapse" aria-labelledby="headingCreator" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="creator_identity" class="form-label">Creator identity</label>
              <input type="text" class="form-control" id="creator_identity" name="creator_identity"
                     value="{{ old('creator_identity', $museum->creator_identity ?? '') }}">
              <div class="form-text text-muted small">The name of the creator, artist, or maker of the object.</div>
            </div>

            <div class="mb-3">
              <label for="creator_role" class="form-label">Creator role</label>
              <input type="text" class="form-control" id="creator_role" name="creator_role"
                     value="{{ old('creator_role', $museum->creator_role ?? '') }}">
              <div class="form-text text-muted small">The role of the creator (e.g. artist, architect, designer, maker).</div>
            </div>

            <div class="mb-3">
              <label for="creator_extent" class="form-label">Creator extent</label>
              <input type="text" class="form-control" id="creator_extent" name="creator_extent"
                     value="{{ old('creator_extent', $museum->creator_extent ?? '') }}">
              <div class="form-text text-muted small">Part of the work created by this creator (e.g. design, frame, gilding).</div>
            </div>

            <div class="mb-3">
              <label for="creator_qualifier" class="form-label">Creator qualifier</label>
              <input type="text" class="form-control" id="creator_qualifier" name="creator_qualifier"
                     value="{{ old('creator_qualifier', $museum->creator_qualifier ?? '') }}">
              <div class="form-text text-muted small">Qualifier for the attribution (e.g. attributed to, circle of, studio of).</div>
            </div>

            <div class="mb-3">
              <label for="creator_attribution" class="form-label">Creator attribution</label>
              <input type="text" class="form-control" id="creator_attribution" name="creator_attribution"
                     value="{{ old('creator_attribution', $museum->creator_attribution ?? '') }}">
              <div class="form-text text-muted small">Full attribution statement for the creator.</div>
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
        <div id="collapseCreation" class="accordion-collapse collapse" aria-labelledby="headingCreation" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="creation_date_display" class="form-label">Creation date (display)</label>
              <input type="text" class="form-control" id="creation_date_display" name="creation_date_display"
                     value="{{ old('creation_date_display', $museum->creation_date_display ?? '') }}">
              <div class="form-text text-muted small">The date of creation as displayed to users (free text, e.g. "circa 1650").</div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="creation_date_earliest" class="form-label">Creation date (earliest)</label>
                <input type="date" class="form-control" id="creation_date_earliest" name="creation_date_earliest"
                       value="{{ old('creation_date_earliest', $museum->creation_date_earliest ?? '') }}">
                <div class="form-text text-muted small">The earliest possible creation date for indexing.</div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="creation_date_latest" class="form-label">Creation date (latest)</label>
                <input type="date" class="form-control" id="creation_date_latest" name="creation_date_latest"
                       value="{{ old('creation_date_latest', $museum->creation_date_latest ?? '') }}">
                <div class="form-text text-muted small">The latest possible creation date for indexing.</div>
              </div>
            </div>

            <div class="mb-3">
              <label for="creation_date_qualifier" class="form-label">Creation date qualifier</label>
              <input type="text" class="form-control" id="creation_date_qualifier" name="creation_date_qualifier"
                     value="{{ old('creation_date_qualifier', $museum->creation_date_qualifier ?? '') }}">
              <div class="form-text text-muted small">Qualifier for the date (e.g. circa, before, after, probably).</div>
            </div>

            <div class="mb-3">
              <label for="creation_place" class="form-label">Creation place</label>
              <input type="text" class="form-control" id="creation_place" name="creation_place"
                     value="{{ old('creation_place', $museum->creation_place ?? '') }}">
              <div class="form-text text-muted small">The geographic location where the object was created.</div>
            </div>

            <div class="mb-3">
              <label for="creation_place_type" class="form-label">Creation place type</label>
              <input type="text" class="form-control" id="creation_place_type" name="creation_place_type"
                     value="{{ old('creation_place_type', $museum->creation_place_type ?? '') }}">
              <div class="form-text text-muted small">Type of place (e.g. country, city, region, site).</div>
            </div>

            <div class="mb-3">
              <label for="discovery_place" class="form-label">Discovery place</label>
              <input type="text" class="form-control" id="discovery_place" name="discovery_place"
                     value="{{ old('discovery_place', $museum->discovery_place ?? '') }}">
              <div class="form-text text-muted small">The location where the object was found or discovered.</div>
            </div>

            <div class="mb-3">
              <label for="discovery_place_type" class="form-label">Discovery place type</label>
              <input type="text" class="form-control" id="discovery_place_type" name="discovery_place_type"
                     value="{{ old('discovery_place_type', $museum->discovery_place_type ?? '') }}">
              <div class="form-text text-muted small">Type of discovery place (e.g. archaeological site, tomb).</div>
            </div>

            <div class="mb-3">
              <label for="style" class="form-label">Style</label>
              <input type="text" class="form-control" id="style" name="style"
                     value="{{ old('style', $museum->style ?? '') }}">
              <div class="form-text text-muted small">The artistic style (e.g. Baroque, Art Deco, Impressionist).</div>
            </div>

            <div class="mb-3">
              <label for="period" class="form-label">Period</label>
              <input type="text" class="form-control" id="period" name="period"
                     value="{{ old('period', $museum->period ?? '') }}">
              <div class="form-text text-muted small">The historical period (e.g. Renaissance, Iron Age, Victorian).</div>
            </div>

            <div class="mb-3">
              <label for="cultural_group" class="form-label">Cultural group</label>
              <input type="text" class="form-control" id="cultural_group" name="cultural_group"
                     value="{{ old('cultural_group', $museum->cultural_group ?? '') }}">
              <div class="form-text text-muted small">The culture or people associated with the creation of this object.</div>
            </div>

            <div class="mb-3">
              <label for="movement" class="form-label">Movement</label>
              <input type="text" class="form-control" id="movement" name="movement"
                     value="{{ old('movement', $museum->movement ?? '') }}">
              <div class="form-text text-muted small">The artistic movement (e.g. Cubism, Surrealism, Minimalism).</div>
            </div>

            <div class="mb-3">
              <label for="school" class="form-label">School</label>
              <input type="text" class="form-control" id="school" name="school"
                     value="{{ old('school', $museum->school ?? '') }}">
              <div class="form-text text-muted small">The school of art (e.g. Flemish School, Hudson River School).</div>
            </div>

            <div class="mb-3">
              <label for="dynasty" class="form-label">Dynasty</label>
              <input type="text" class="form-control" id="dynasty" name="dynasty"
                     value="{{ old('dynasty', $museum->dynasty ?? '') }}">
              <div class="form-text text-muted small">The dynasty or ruling house (e.g. Ming, Tudor, Ottoman).</div>
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
        <div id="collapseMeasurements" class="accordion-collapse collapse" aria-labelledby="headingMeasurements" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="measurements" class="form-label">Measurements</label>
              <input type="text" class="form-control" id="measurements" name="measurements"
                     value="{{ old('measurements', $museum->measurements ?? '') }}">
              <div class="form-text text-muted small">Overall measurements display (e.g. "72.4 x 91.4 cm").</div>
            </div>

            <div class="mb-3">
              <label for="dimensions" class="form-label">Dimensions</label>
              <input type="text" class="form-control" id="dimensions" name="dimensions"
                     value="{{ old('dimensions', $museum->dimensions ?? '') }}">
              <div class="form-text text-muted small">Structured dimensions (height, width, depth, weight, etc.).</div>
            </div>

            <div class="mb-3">
              <label for="orientation" class="form-label">Orientation</label>
              <input type="text" class="form-control" id="orientation" name="orientation"
                     value="{{ old('orientation', $museum->orientation ?? '') }}">
              <div class="form-text text-muted small">Orientation of the object (e.g. portrait, landscape).</div>
            </div>

            <div class="mb-3">
              <label for="shape" class="form-label">Shape</label>
              <input type="text" class="form-control" id="shape" name="shape"
                     value="{{ old('shape', $museum->shape ?? '') }}">
              <div class="form-text text-muted small">The shape of the object (e.g. rectangular, circular, irregular).</div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Materials / Techniques ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingMaterials">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMaterials" aria-expanded="false" aria-controls="collapseMaterials">
            Materials/Techniques
            <span class="cco-chapter">CCO Chapter 7</span>
          </button>
        </h2>
        <div id="collapseMaterials" class="accordion-collapse collapse" aria-labelledby="headingMaterials" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="materials" class="form-label">Materials</label>
              <textarea class="form-control" id="materials" name="materials" rows="3">{{ old('materials', $museum->materials ?? '') }}</textarea>
              <div class="form-text text-muted small">The materials and media used (e.g. oil on canvas, bronze, marble).</div>
            </div>

            <div class="mb-3">
              <label for="techniques" class="form-label">Techniques</label>
              <textarea class="form-control" id="techniques" name="techniques" rows="3">{{ old('techniques', $museum->techniques ?? '') }}</textarea>
              <div class="form-text text-muted small">The techniques or processes used in creating the object.</div>
            </div>

            <div class="mb-3">
              <label for="technique_cco" class="form-label">Technique (CCO)</label>
              <input type="text" class="form-control" id="technique_cco" name="technique_cco"
                     value="{{ old('technique_cco', $museum->technique_cco ?? '') }}">
              <div class="form-text text-muted small">Controlled term for the technique per CCO standard.</div>
            </div>

            <div class="mb-3">
              <label for="technique_qualifier" class="form-label">Technique qualifier</label>
              <input type="text" class="form-control" id="technique_qualifier" name="technique_qualifier"
                     value="{{ old('technique_qualifier', $museum->technique_qualifier ?? '') }}">
              <div class="form-text text-muted small">Qualifier for the technique (e.g. possibly, probably).</div>
            </div>

            <div class="mb-3">
              <label for="facture_description" class="form-label">Facture description</label>
              <textarea class="form-control" id="facture_description" name="facture_description" rows="3">{{ old('facture_description', $museum->facture_description ?? '') }}</textarea>
              <div class="form-text text-muted small">Description of how the object was made (brush strokes, casting marks, etc.).</div>
            </div>

            <div class="mb-3">
              <label for="color" class="form-label">Color</label>
              <input type="text" class="form-control" id="color" name="color"
                     value="{{ old('color', $museum->color ?? '') }}">
              <div class="form-text text-muted small">Predominant colors of the object.</div>
            </div>

            <div class="mb-3">
              <label for="physical_appearance" class="form-label">Physical appearance</label>
              <textarea class="form-control" id="physical_appearance" name="physical_appearance" rows="3">{{ old('physical_appearance', $museum->physical_appearance ?? '') }}</textarea>
              <div class="form-text text-muted small">General description of the physical appearance.</div>
            </div>

            <div class="mb-3">
              <label for="extent_and_medium" class="form-label">Extent and medium</label>
              <textarea class="form-control" id="extent_and_medium" name="extent_and_medium" rows="2">{{ old('extent_and_medium', $museum->extent_and_medium ?? '') }}</textarea>
              <div class="form-text text-muted small">Summary of physical extent and medium.</div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Subject / Content ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingSubject">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSubject" aria-expanded="false" aria-controls="collapseSubject">
            Subject Matter
            <span class="cco-chapter">CCO Chapter 8</span>
          </button>
        </h2>
        <div id="collapseSubject" class="accordion-collapse collapse" aria-labelledby="headingSubject" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="scope_and_content" class="form-label">Scope and content</label>
              <textarea class="form-control" id="scope_and_content" name="scope_and_content" rows="4">{{ old('scope_and_content', $museum->scope_and_content ?? '') }}</textarea>
              <div class="form-text text-muted small">Description of the content, subject matter, and iconography.</div>
            </div>

            <div class="mb-3">
              <label for="style_period" class="form-label">Style / Period</label>
              <input type="text" class="form-control" id="style_period" name="style_period"
                     value="{{ old('style_period', $museum->style_period ?? '') }}">
              <div class="form-text text-muted small">Combined style and period classification.</div>
            </div>

            <div class="mb-3">
              <label for="cultural_context" class="form-label">Cultural context</label>
              <input type="text" class="form-control" id="cultural_context" name="cultural_context"
                     value="{{ old('cultural_context', $museum->cultural_context ?? '') }}">
              <div class="form-text text-muted small">The cultural or social context of the object.</div>
            </div>

            <div class="mb-3">
              <label for="subject_indexing_type" class="form-label">Subject indexing type</label>
              <input type="text" class="form-control" id="subject_indexing_type" name="subject_indexing_type"
                     value="{{ old('subject_indexing_type', $museum->subject_indexing_type ?? '') }}">
              <div class="form-text text-muted small">Type of subject term (e.g. description, identification, interpretation).</div>
            </div>

            <div class="mb-3">
              <label for="subject_display" class="form-label">Subject display</label>
              <textarea class="form-control" id="subject_display" name="subject_display" rows="3">{{ old('subject_display', $museum->subject_display ?? '') }}</textarea>
              <div class="form-text text-muted small">Display text for the subject of the work.</div>
            </div>

            <div class="mb-3">
              <label for="subject_extent" class="form-label">Subject extent</label>
              <input type="text" class="form-control" id="subject_extent" name="subject_extent"
                     value="{{ old('subject_extent', $museum->subject_extent ?? '') }}">
              <div class="form-text text-muted small">Part of the work to which the subject pertains.</div>
            </div>

            <div class="mb-3">
              <label for="historical_context" class="form-label">Historical context</label>
              <textarea class="form-control" id="historical_context" name="historical_context" rows="3">{{ old('historical_context', $museum->historical_context ?? '') }}</textarea>
              <div class="form-text text-muted small">Historical background or context for the object.</div>
            </div>

            <div class="mb-3">
              <label for="architectural_context" class="form-label">Architectural context</label>
              <textarea class="form-control" id="architectural_context" name="architectural_context" rows="3">{{ old('architectural_context', $museum->architectural_context ?? '') }}</textarea>
              <div class="form-text text-muted small">Architectural setting or context for the object.</div>
            </div>

            <div class="mb-3">
              <label for="archaeological_context" class="form-label">Archaeological context</label>
              <textarea class="form-control" id="archaeological_context" name="archaeological_context" rows="3">{{ old('archaeological_context', $museum->archaeological_context ?? '') }}</textarea>
              <div class="form-text text-muted small">Archaeological context (excavation site, stratigraphic layer, etc.).</div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Edition / State ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingEdition">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEdition" aria-expanded="false" aria-controls="collapseEdition">
            State/Edition
            <span class="cco-chapter">CCO Chapter 10</span>
          </button>
        </h2>
        <div id="collapseEdition" class="accordion-collapse collapse" aria-labelledby="headingEdition" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="edition_description" class="form-label">Edition description</label>
              <textarea class="form-control" id="edition_description" name="edition_description" rows="2">{{ old('edition_description', $museum->edition_description ?? '') }}</textarea>
              <div class="form-text text-muted small">Description of the edition (for prints, photographs, multiples).</div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="edition_number" class="form-label">Edition number</label>
                <input type="text" class="form-control" id="edition_number" name="edition_number"
                       value="{{ old('edition_number', $museum->edition_number ?? '') }}">
                <div class="form-text text-muted small">Number of this impression within the edition.</div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="edition_size" class="form-label">Edition size</label>
                <input type="text" class="form-control" id="edition_size" name="edition_size"
                       value="{{ old('edition_size', $museum->edition_size ?? '') }}">
                <div class="form-text text-muted small">Total number of impressions in the edition.</div>
              </div>
            </div>

            <div class="mb-3">
              <label for="state_identification" class="form-label">State identification</label>
              <input type="text" class="form-control" id="state_identification" name="state_identification"
                     value="{{ old('state_identification', $museum->state_identification ?? '') }}">
              <div class="form-text text-muted small">Identification of the state (e.g. "State III of V").</div>
            </div>

            <div class="mb-3">
              <label for="state_description" class="form-label">State description</label>
              <textarea class="form-control" id="state_description" name="state_description" rows="2">{{ old('state_description', $museum->state_description ?? '') }}</textarea>
              <div class="form-text text-muted small">Description of changes made in this state.</div>
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
        <div id="collapseInscriptions" class="accordion-collapse collapse" aria-labelledby="headingInscriptions" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="inscription" class="form-label">Inscription</label>
              <textarea class="form-control" id="inscription" name="inscription" rows="3">{{ old('inscription', $museum->inscription ?? '') }}</textarea>
              <div class="form-text text-muted small">Text of any inscriptions on the object.</div>
            </div>

            <div class="mb-3">
              <label for="inscriptions" class="form-label">Inscriptions (additional)</label>
              <textarea class="form-control" id="inscriptions" name="inscriptions" rows="3">{{ old('inscriptions', $museum->inscriptions ?? '') }}</textarea>
              <div class="form-text text-muted small">Additional inscriptions not captured above.</div>
            </div>

            <div class="mb-3">
              <label for="inscription_transcription" class="form-label">Inscription transcription</label>
              <textarea class="form-control" id="inscription_transcription" name="inscription_transcription" rows="3">{{ old('inscription_transcription', $museum->inscription_transcription ?? '') }}</textarea>
              <div class="form-text text-muted small">Full transcription of the inscription text.</div>
            </div>

            <div class="mb-3">
              <label for="inscription_type" class="form-label">Inscription type</label>
              <input type="text" class="form-control" id="inscription_type" name="inscription_type"
                     value="{{ old('inscription_type', $museum->inscription_type ?? '') }}">
              <div class="form-text text-muted small">Type of inscription (e.g. signature, date, dedication, stamp).</div>
            </div>

            <div class="mb-3">
              <label for="inscription_location" class="form-label">Inscription location</label>
              <input type="text" class="form-control" id="inscription_location" name="inscription_location"
                     value="{{ old('inscription_location', $museum->inscription_location ?? '') }}">
              <div class="form-text text-muted small">Location of the inscription on the object (e.g. lower right, verso).</div>
            </div>

            <div class="mb-3">
              <label for="inscription_language" class="form-label">Inscription language</label>
              <input type="text" class="form-control" id="inscription_language" name="inscription_language"
                     value="{{ old('inscription_language', $museum->inscription_language ?? '') }}">
              <div class="form-text text-muted small">Language of the inscription.</div>
            </div>

            <div class="mb-3">
              <label for="inscription_translation" class="form-label">Inscription translation</label>
              <textarea class="form-control" id="inscription_translation" name="inscription_translation" rows="3">{{ old('inscription_translation', $museum->inscription_translation ?? '') }}</textarea>
              <div class="form-text text-muted small">Translation of the inscription if in a non-English language.</div>
            </div>

            <div class="mb-3">
              <label for="mark_type" class="form-label">Mark type</label>
              <input type="text" class="form-control" id="mark_type" name="mark_type"
                     value="{{ old('mark_type', $museum->mark_type ?? '') }}">
              <div class="form-text text-muted small">Type of mark (e.g. watermark, hallmark, collector's mark, brand).</div>
            </div>

            <div class="mb-3">
              <label for="mark_description" class="form-label">Mark description</label>
              <textarea class="form-control" id="mark_description" name="mark_description" rows="3">{{ old('mark_description', $museum->mark_description ?? '') }}</textarea>
              <div class="form-text text-muted small">Description of the mark.</div>
            </div>

            <div class="mb-3">
              <label for="mark_location" class="form-label">Mark location</label>
              <input type="text" class="form-control" id="mark_location" name="mark_location"
                     value="{{ old('mark_location', $museum->mark_location ?? '') }}">
              <div class="form-text text-muted small">Location of the mark on the object.</div>
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
        <div id="collapseCondition" class="accordion-collapse collapse" aria-labelledby="headingCondition" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="condition_term" class="form-label">Condition term</label>
              <input type="text" class="form-control" id="condition_term" name="condition_term"
                     value="{{ old('condition_term', $museum->condition_term ?? '') }}">
              <div class="form-text text-muted small">Controlled term for the condition (e.g. excellent, good, fair, poor).</div>
            </div>

            <div class="mb-3">
              <label for="condition_date" class="form-label">Condition date</label>
              <input type="date" class="form-control" id="condition_date" name="condition_date"
                     value="{{ old('condition_date', $museum->condition_date ?? '') }}">
              <div class="form-text text-muted small">Date of the condition assessment.</div>
            </div>

            <div class="mb-3">
              <label for="condition_description" class="form-label">Condition description</label>
              <textarea class="form-control" id="condition_description" name="condition_description" rows="3">{{ old('condition_description', $museum->condition_description ?? '') }}</textarea>
              <div class="form-text text-muted small">Detailed description of the current condition.</div>
            </div>

            <div class="mb-3">
              <label for="condition_notes" class="form-label">Condition notes</label>
              <textarea class="form-control" id="condition_notes" name="condition_notes" rows="3">{{ old('condition_notes', $museum->condition_notes ?? '') }}</textarea>
              <div class="form-text text-muted small">Additional notes on the condition.</div>
            </div>

            <div class="mb-3">
              <label for="condition_agent" class="form-label">Condition agent</label>
              <input type="text" class="form-control" id="condition_agent" name="condition_agent"
                     value="{{ old('condition_agent', $museum->condition_agent ?? '') }}">
              <div class="form-text text-muted small">Person who assessed the condition.</div>
            </div>

            <div class="mb-3">
              <label for="treatment_type" class="form-label">Treatment type</label>
              <input type="text" class="form-control" id="treatment_type" name="treatment_type"
                     value="{{ old('treatment_type', $museum->treatment_type ?? '') }}">
              <div class="form-text text-muted small">Type of conservation treatment performed.</div>
            </div>

            <div class="mb-3">
              <label for="treatment_date" class="form-label">Treatment date</label>
              <input type="date" class="form-control" id="treatment_date" name="treatment_date"
                     value="{{ old('treatment_date', $museum->treatment_date ?? '') }}">
              <div class="form-text text-muted small">Date the treatment was performed.</div>
            </div>

            <div class="mb-3">
              <label for="treatment_agent" class="form-label">Treatment agent</label>
              <input type="text" class="form-control" id="treatment_agent" name="treatment_agent"
                     value="{{ old('treatment_agent', $museum->treatment_agent ?? '') }}">
              <div class="form-text text-muted small">Person or organization that performed the treatment.</div>
            </div>

            <div class="mb-3">
              <label for="treatment_description" class="form-label">Treatment description</label>
              <textarea class="form-control" id="treatment_description" name="treatment_description" rows="3">{{ old('treatment_description', $museum->treatment_description ?? '') }}</textarea>
              <div class="form-text text-muted small">Detailed description of conservation treatment performed.</div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Provenance / Location ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingProvenance">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProvenance" aria-expanded="false" aria-controls="collapseProvenance">
            Current Location
            <span class="cco-chapter">CCO Chapter 13</span>
          </button>
        </h2>
        <div id="collapseProvenance" class="accordion-collapse collapse" aria-labelledby="headingProvenance" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="provenance" class="form-label">Provenance</label>
              <textarea class="form-control" id="provenance" name="provenance" rows="3">{{ old('provenance', $museum->provenance ?? '') }}</textarea>
              <div class="form-text text-muted small">Ownership history of the object.</div>
            </div>

            <div class="mb-3">
              <label for="provenance_text" class="form-label">Provenance text</label>
              <textarea class="form-control" id="provenance_text" name="provenance_text" rows="3">{{ old('provenance_text', $museum->provenance_text ?? '') }}</textarea>
              <div class="form-text text-muted small">Full provenance narrative.</div>
            </div>

            <div class="mb-3">
              <label for="ownership_history" class="form-label">Ownership history</label>
              <textarea class="form-control" id="ownership_history" name="ownership_history" rows="3">{{ old('ownership_history', $museum->ownership_history ?? '') }}</textarea>
              <div class="form-text text-muted small">Detailed ownership history including dates and methods of transfer.</div>
            </div>

            <div class="mb-3">
              <label for="current_location" class="form-label">Current location</label>
              <textarea class="form-control" id="current_location" name="current_location" rows="2">{{ old('current_location', $museum->current_location ?? '') }}</textarea>
              <div class="form-text text-muted small">Where the object is currently stored or displayed.</div>
            </div>

            <div class="mb-3">
              <label for="current_location_repository" class="form-label">Current location repository</label>
              <input type="text" class="form-control" id="current_location_repository" name="current_location_repository"
                     value="{{ old('current_location_repository', $museum->current_location_repository ?? '') }}">
              <div class="form-text text-muted small">The repository or institution where the object is currently held.</div>
            </div>

            <div class="mb-3">
              <label for="current_location_geography" class="form-label">Current location geography</label>
              <input type="text" class="form-control" id="current_location_geography" name="current_location_geography"
                     value="{{ old('current_location_geography', $museum->current_location_geography ?? '') }}">
              <div class="form-text text-muted small">Geographic location of the repository.</div>
            </div>

            <div class="mb-3">
              <label for="current_location_coordinates" class="form-label">Current location coordinates</label>
              <input type="text" class="form-control" id="current_location_coordinates" name="current_location_coordinates"
                     value="{{ old('current_location_coordinates', $museum->current_location_coordinates ?? '') }}">
              <div class="form-text text-muted small">GPS coordinates (latitude, longitude).</div>
            </div>

            <div class="mb-3">
              <label for="current_location_ref_number" class="form-label">Current location reference number</label>
              <input type="text" class="form-control" id="current_location_ref_number" name="current_location_ref_number"
                     value="{{ old('current_location_ref_number', $museum->current_location_ref_number ?? '') }}">
              <div class="form-text text-muted small">Shelf, gallery, or storage reference number at the current location.</div>
            </div>

            <div class="mb-3">
              <label for="legal_status" class="form-label">Legal status</label>
              <input type="text" class="form-control" id="legal_status" name="legal_status"
                     value="{{ old('legal_status', $museum->legal_status ?? '') }}">
              <div class="form-text text-muted small">Legal status of the object (e.g. gift, purchase, loan, public domain).</div>
            </div>

            <div class="mb-3">
              <label for="rights_type" class="form-label">Rights type</label>
              <input type="text" class="form-control" id="rights_type" name="rights_type"
                     value="{{ old('rights_type', $museum->rights_type ?? '') }}">
              <div class="form-text text-muted small">Type of rights (e.g. copyright, trademark, public domain).</div>
            </div>

            <div class="mb-3">
              <label for="rights_holder" class="form-label">Rights holder</label>
              <input type="text" class="form-control" id="rights_holder" name="rights_holder"
                     value="{{ old('rights_holder', $museum->rights_holder ?? '') }}">
              <div class="form-text text-muted small">Person or institution holding the rights.</div>
            </div>

            <div class="mb-3">
              <label for="rights_date" class="form-label">Rights date</label>
              <input type="text" class="form-control" id="rights_date" name="rights_date"
                     value="{{ old('rights_date', $museum->rights_date ?? '') }}">
              <div class="form-text text-muted small">Date or range applicable to the rights statement.</div>
            </div>

            <div class="mb-3">
              <label for="rights_remarks" class="form-label">Rights remarks</label>
              <textarea class="form-control" id="rights_remarks" name="rights_remarks" rows="2">{{ old('rights_remarks', $museum->rights_remarks ?? '') }}</textarea>
              <div class="form-text text-muted small">Additional notes on rights and reproduction.</div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Related Works ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingRelated">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRelated" aria-expanded="false" aria-controls="collapseRelated">
            Related Works
            <span class="cco-chapter">CCO Chapter 14</span>
          </button>
        </h2>
        <div id="collapseRelated" class="accordion-collapse collapse" aria-labelledby="headingRelated" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="related_work_type" class="form-label">Related work type</label>
              <input type="text" class="form-control" id="related_work_type" name="related_work_type"
                     value="{{ old('related_work_type', $museum->related_work_type ?? '') }}">
              <div class="form-text text-muted small">Type of the related work (e.g. part, copy, study, pendant).</div>
            </div>

            <div class="mb-3">
              <label for="related_work_relationship" class="form-label">Related work relationship</label>
              <input type="text" class="form-control" id="related_work_relationship" name="related_work_relationship"
                     value="{{ old('related_work_relationship', $museum->related_work_relationship ?? '') }}">
              <div class="form-text text-muted small">Nature of the relationship (e.g. is part of, is copy of, is study for).</div>
            </div>

            <div class="mb-3">
              <label for="related_work_label" class="form-label">Related work label</label>
              <input type="text" class="form-control" id="related_work_label" name="related_work_label"
                     value="{{ old('related_work_label', $museum->related_work_label ?? '') }}">
              <div class="form-text text-muted small">Title or label of the related work.</div>
            </div>

            <div class="mb-3">
              <label for="related_work_id" class="form-label">Related work identifier</label>
              <input type="text" class="form-control" id="related_work_id" name="related_work_id"
                     value="{{ old('related_work_id', $museum->related_work_id ?? '') }}">
              <div class="form-text text-muted small">Accession number or identifier of the related work.</div>
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
        <div id="collapseCataloging" class="accordion-collapse collapse" aria-labelledby="headingCataloging" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="cataloger_name" class="form-label">Cataloger name</label>
              <input type="text" class="form-control" id="cataloger_name" name="cataloger_name"
                     value="{{ old('cataloger_name', $museum->cataloger_name ?? '') }}">
              <div class="form-text text-muted small">Name of the person who created or last updated this catalogue record.</div>
            </div>

            <div class="mb-3">
              <label for="cataloging_date" class="form-label">Cataloging date</label>
              <input type="date" class="form-control" id="cataloging_date" name="cataloging_date"
                     value="{{ old('cataloging_date', $museum->cataloging_date ?? '') }}">
              <div class="form-text text-muted small">Date this record was catalogued or last modified.</div>
            </div>

            <div class="mb-3">
              <label for="cataloging_institution" class="form-label">Cataloging institution</label>
              <input type="text" class="form-control" id="cataloging_institution" name="cataloging_institution"
                     value="{{ old('cataloging_institution', $museum->cataloging_institution ?? '') }}">
              <div class="form-text text-muted small">Institution responsible for creating this catalogue record.</div>
            </div>

            <div class="mb-3">
              <label for="cataloging_remarks" class="form-label">Cataloging remarks</label>
              <textarea class="form-control" id="cataloging_remarks" name="cataloging_remarks" rows="3">{{ old('cataloging_remarks', $museum->cataloging_remarks ?? '') }}</textarea>
              <div class="form-text text-muted small">Notes about the cataloging process or data quality.</div>
            </div>

            <div class="mb-3">
              <label for="repository_id" class="form-label">Repository</label>
              <select class="form-select" id="repository_id" name="repository_id">
                <option value="">-- Select --</option>
                @foreach($repositories as $repo)
                  <option value="{{ $repo->id }}" @selected(old('repository_id', $museum->repository_id ?? '') == $repo->id)>
                    {{ $repo->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text text-muted small">The repository or institution holding this object.</div>
            </div>

          </div>
        </div>
      </div>

    </div>{{-- end accordion --}}

    <ul class="actions mb-3 nav gap-2">
      @if(!$isNew)
        <li><a href="{{ route('museum.show', $museum->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a href="{{ route('museum.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @endif
    </ul>

  </form>

@push('css')
<style>
/* CCO Form Styling - match AtoM museum cataloguing theme */
.cco-cataloguing-form .accordion-item {
  border: none;
  margin-bottom: 10px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #ddd;
}

.cco-cataloguing-form .accordion-button {
  background: var(--ahg-primary, #005837) !important;
  color: #fff !important;
}

.cco-cataloguing-form .accordion-button:not(.collapsed) {
  background: var(--ahg-primary, #005837) !important;
  color: #fff !important;
}

.cco-cataloguing-form .accordion-button.collapsed {
  background-color: var(--ahg-primary, #005837);
  color: #fff;
}

.cco-cataloguing-form .accordion-button::after {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'//%3e%3c/svg%3e");
}

.cco-cataloguing-form .accordion-button:focus {
  box-shadow: none;
}

.cco-cataloguing-form .accordion-body {
  padding: 20px;
  background: #fff;
}

.cco-chapter {
  margin-left: 15px;
  float: right;
  font-size: 11px;
  opacity: 1;
  font-weight: normal;
}

h1.multiline .sub {
  display: block;
  font-size: 0.6em;
  color: #666;
  font-weight: normal;
}
</style>
@endpush
@endsection
