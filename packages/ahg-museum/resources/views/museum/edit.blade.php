@extends('theme::layouts.2col')

@section('title', 'CCO Cataloguing')
@section('body-class', ($isNew ? 'create' : 'edit') . ' museum')

@section('sidebar')
<div class="sidebar-content">

  <!-- Template Selector -->
  <section id="template-selector" class="sidebar-section">
    <h4>Object Template</h4>
    <div class="template-list">
      <a href="#" data-template="general" class="template-option active">
        <i class="fas fa-cube"></i> <span>General</span>
      </a>
      <a href="#" data-template="painting" class="template-option">
        <i class="fas fa-palette"></i> <span>Painting</span>
      </a>
      <a href="#" data-template="sculpture" class="template-option">
        <i class="fas fa-monument"></i> <span>Sculpture</span>
      </a>
      <a href="#" data-template="photograph" class="template-option">
        <i class="fas fa-camera"></i> <span>Photograph</span>
      </a>
      <a href="#" data-template="textile" class="template-option">
        <i class="fas fa-scroll"></i> <span>Textile</span>
      </a>
    </div>
  </section>

  <!-- Completeness Meter -->
  <section id="completeness-meter" class="sidebar-section">
    <h4>Record Completeness</h4>
    <div class="progress-container">
      <div class="progress">
        <div class="progress-bar bg-danger" id="completeness-bar" style="width: 0%"></div>
      </div>
      <span class="completeness-value" id="completeness-value">0%</span>
    </div>
    <p class="help-text">Fill all required and recommended fields for complete cataloguing.</p>
  </section>

  <!-- CCO Reference -->
  <section id="cco-reference" class="sidebar-section">
    <h4>CCO Reference</h4>
    <p class="small">This form follows the Cataloguing Cultural Objects (CCO) standard.</p>
    <a href="http://cco.vrafoundation.org/" target="_blank" class="btn btn-sm btn-cco-guide">
      <i class="fas fa-external-link-alt"></i> CCO Guide
    </a>
  </section>

  <!-- Field Legend -->
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
              <label for="work_type_qualifier" class="form-label">Work type qualifier</label>
              <select class="form-select" id="work_type_qualifier" name="work_type_qualifier">
                <option value="">-- Select --</option>
                <option value="possibly" @selected(old('work_type_qualifier', $museum->work_type_qualifier ?? '') === 'possibly')>Possibly</option>
                <option value="probably" @selected(old('work_type_qualifier', $museum->work_type_qualifier ?? '') === 'probably')>Probably</option>
                <option value="formerly classified as" @selected(old('work_type_qualifier', $museum->work_type_qualifier ?? '') === 'formerly classified as')>Formerly classified as</option>
              </select>
              <div class="form-text text-muted small">Qualifies uncertainty about the work type. (CCO 2.1.1)</div>
            </div>

            <div class="mb-3">
              <label for="components_count" class="form-label">Components/Parts</label>
              <input type="text" class="form-control" id="components_count" name="components_count"
                     value="{{ old('components_count', $museum->components_count ?? '') }}">
              <div class="form-text text-muted small">Number and description of physical components, e.g. "diptych (2 panels)", "portfolio of 12 prints". (CCO 2.2)</div>
            </div>

            <div class="mb-3">
              <label for="object_number" class="form-label">Object number <span class="form-required" title="This is a mandatory element.">*</span></label>
              <input type="text" class="form-control" id="object_number" name="object_number"
                     value="{{ old('object_number', $museum->object_number ?? '') }}">
              <div class="form-text text-muted small">Unique identifier assigned by the repository. This should follow your institution's numbering system. (CCO 2.3)</div>
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
              <label for="title_type" class="form-label">Title type <span class="form-required" title="This is a mandatory element.">*</span></label>
              <select class="form-select" id="title_type" name="title_type">
                <option value="repository" @selected(old('title_type', $museum->title_type ?? 'repository') === 'repository')>Repository (assigned by institution)</option>
                <option value="creator" @selected(old('title_type', $museum->title_type ?? '') === 'creator')>Creator (given by artist)</option>
                <option value="inscribed" @selected(old('title_type', $museum->title_type ?? '') === 'inscribed')>Inscribed (found on work)</option>
                <option value="popular" @selected(old('title_type', $museum->title_type ?? '') === 'popular')>Popular (commonly known)</option>
                <option value="descriptive" @selected(old('title_type', $museum->title_type ?? '') === 'descriptive')>Descriptive (based on subject)</option>
                <option value="former" @selected(old('title_type', $museum->title_type ?? '') === 'former')>Former (previously used)</option>
                <option value="translated" @selected(old('title_type', $museum->title_type ?? '') === 'translated')>Translated</option>
                <option value="series" @selected(old('title_type', $museum->title_type ?? '') === 'series')>Series title</option>
              </select>
              <div class="form-text text-muted small">The source or nature of the title. (CCO 3.1.1)</div>
            </div>

            <div class="mb-3">
              <label for="title_language" class="form-label">Title language</label>
              <input type="text" class="form-control" id="title_language" name="title_language"
                     value="{{ old('title_language', $museum->title_language ?? 'eng') }}">
              <div class="form-text text-muted small">Language of the title (ISO 639-2 code). (CCO 3.1.2)</div>
            </div>

            <div class="mb-3">
              <label for="alternate_titles" class="form-label">Alternate titles</label>
              <input type="text" class="form-control" id="alternate_titles" name="alternate_titles"
                     value="{{ old('alternate_titles', $museum->alternate_titles ?? $museum->alternate_title ?? '') }}">
              <div class="form-text text-muted small">Other titles by which the work is known. Include former titles, translations, and variant spellings. (CCO 3.2)</div>
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
              <label for="creator_display" class="form-label">Creator (Display) <span class="form-required" title="This is a mandatory element.">*</span></label>
              <input type="text" class="form-control" id="creator_display" name="creator_display"
                     value="{{ old('creator_display', $museum->creator_display ?? '') }}">
              <div class="form-text text-muted small">Creator name as it should appear in displays. Format: Surname, Forename (Nationality, birth-death). (CCO 4.1)</div>
            </div>

            <div class="mb-3">
              <label for="creator" class="form-label">Creator (Authority) <span class="form-required" title="This is a mandatory element.">*</span></label>
              <input type="text" class="form-control" id="creator" name="creator"
                     value="{{ old('creator', $museum->creator ?? '') }}" placeholder="Type to search authority records..." autocomplete="off">
              <div class="form-text text-muted small">Link to authority record. Search ULAN or local authority file. (CCO 4.1)</div>
            </div>

            <div class="mb-3">
              <label for="attribution_qualifier" class="form-label">Attribution qualifier</label>
              <select class="form-select" id="attribution_qualifier" name="attribution_qualifier">
                <option value="" @selected(old('attribution_qualifier', $museum->attribution_qualifier ?? '') === '')>(No qualifier - certain attribution)</option>
                <option value="attributed_to" @selected(old('attribution_qualifier', $museum->attribution_qualifier ?? '') === 'attributed_to')>Attributed to</option>
                <option value="workshop_of" @selected(old('attribution_qualifier', $museum->attribution_qualifier ?? '') === 'workshop_of')>Workshop of</option>
                <option value="studio_of" @selected(old('attribution_qualifier', $museum->attribution_qualifier ?? '') === 'studio_of')>Studio of</option>
                <option value="circle_of" @selected(old('attribution_qualifier', $museum->attribution_qualifier ?? '') === 'circle_of')>Circle of</option>
                <option value="school_of" @selected(old('attribution_qualifier', $museum->attribution_qualifier ?? '') === 'school_of')>School of</option>
                <option value="follower_of" @selected(old('attribution_qualifier', $museum->attribution_qualifier ?? '') === 'follower_of')>Follower of</option>
                <option value="manner_of" @selected(old('attribution_qualifier', $museum->attribution_qualifier ?? '') === 'manner_of')>Manner of</option>
                <option value="after" @selected(old('attribution_qualifier', $museum->attribution_qualifier ?? '') === 'after')>After</option>
                <option value="copy_after" @selected(old('attribution_qualifier', $museum->attribution_qualifier ?? '') === 'copy_after')>Copy after</option>
              </select>
              <div class="form-text text-muted small">Qualifies degree of certainty about attribution. (CCO 4.1.2)</div>
            </div>

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
              <label for="creation_place" class="form-label">Place of creation</label>
              <input type="text" class="form-control" id="creation_place" name="creation_place"
                     value="{{ old('creation_place', $museum->creation_place ?? '') }}" placeholder="Type to search places..." autocomplete="off">
              <div class="form-text text-muted small">The geographic location where the object was created. Use TGN (Getty Thesaurus of Geographic Names) when possible. (CCO 4.3)</div>
            </div>

            <div class="mb-3">
              <label for="culture" class="form-label">Culture/People</label>
              <input type="text" class="form-control" id="culture" name="culture"
                     value="{{ old('culture', $museum->culture ?? '') }}" placeholder="Type to search cultures..." autocomplete="off">
              <div class="form-text text-muted small">The culture, people, or nationality associated with the creation. Use AAT culture terms when possible. (CCO 4.4)</div>
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
              <label for="school_group" class="form-label">School/Group</label>
              <input type="text" class="form-control" id="school_group" name="school_group"
                     value="{{ old('school_group', $museum->school_group ?? $museum->school ?? '') }}" placeholder="Type to search..." autocomplete="off">
              <div class="form-text text-muted small">The school of art or artistic group (e.g. Flemish School, Hudson River School). (CCO 5.3)</div>
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
              <label for="dimensions_display" class="form-label">Dimensions (Display)</label>
              <input type="text" class="form-control" id="dimensions_display" name="dimensions_display"
                     value="{{ old('dimensions_display', $museum->dimensions_display ?? '') }}">
              <div class="form-text text-muted small">Dimensions as they should appear in displays, e.g. "72.4 x 91.4 cm (28 1/2 x 36 in.)". (CCO 6.1)</div>
            </div>

            <div class="row">
              <div class="col-md-3 mb-3">
                <label for="height_value" class="form-label">Height</label>
                <input type="text" class="form-control" id="height_value" name="height_value"
                       value="{{ old('height_value', $museum->height_value ?? '') }}">
              </div>
              <div class="col-md-3 mb-3">
                <label for="width_value" class="form-label">Width</label>
                <input type="text" class="form-control" id="width_value" name="width_value"
                       value="{{ old('width_value', $museum->width_value ?? '') }}">
              </div>
              <div class="col-md-3 mb-3">
                <label for="depth_value" class="form-label">Depth</label>
                <input type="text" class="form-control" id="depth_value" name="depth_value"
                       value="{{ old('depth_value', $museum->depth_value ?? '') }}">
              </div>
              <div class="col-md-3 mb-3">
                <label for="weight_value" class="form-label">Weight</label>
                <input type="text" class="form-control" id="weight_value" name="weight_value"
                       value="{{ old('weight_value', $museum->weight_value ?? '') }}">
              </div>
            </div>

            <div class="mb-3">
              <label for="dimension_notes" class="form-label">Dimension notes</label>
              <textarea class="form-control" id="dimension_notes" name="dimension_notes" rows="2">{{ old('dimension_notes', $museum->dimension_notes ?? '') }}</textarea>
              <div class="form-text text-muted small">Notes about how measurements were taken or special considerations. (CCO 6.6)</div>
            </div>

            <div class="mb-3">
              <label for="dimensions" class="form-label">Dimensions (legacy)</label>
              <input type="text" class="form-control" id="dimensions" name="dimensions"
                     value="{{ old('dimensions', $museum->dimensions ?? '') }}">
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
              <label for="materials_display" class="form-label">Medium (Display) <span class="form-required" title="This is a mandatory element.">*</span></label>
              <input type="text" class="form-control" id="materials_display" name="materials_display"
                     value="{{ old('materials_display', $museum->materials_display ?? '') }}">
              <div class="form-text text-muted small">Medium as it should appear in displays, e.g. "oil on canvas", "gelatin silver print". (CCO 7.1)</div>
            </div>

            <div class="mb-3">
              <label for="materials" class="form-label">Materials (Indexed)</label>
              <textarea class="form-control" id="materials" name="materials" rows="3">{{ old('materials', $museum->materials ?? '') }}</textarea>
              <div class="form-text text-muted small">Individual materials indexed for searching. Use AAT material terms. (CCO 7.2)</div>
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
              <label for="support" class="form-label">Support</label>
              <input type="text" class="form-control" id="support" name="support"
                     value="{{ old('support', $museum->support ?? '') }}">
              <div class="form-text text-muted small">The material on which the work is executed, e.g. "canvas", "paper", "panel". (CCO 7.4)</div>
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
              <label for="subjects_depicted" class="form-label">Subjects depicted</label>
              <input type="text" class="form-control" id="subjects_depicted" name="subjects_depicted"
                     value="{{ old('subjects_depicted', $museum->subjects_depicted ?? '') }}" placeholder="Type to search subjects..." autocomplete="off">
              <div class="form-text text-muted small">Specific subjects depicted in the work. Use controlled vocabulary terms. (CCO 8.2)</div>
            </div>

            <div class="mb-3">
              <label for="iconography" class="form-label">Iconography</label>
              <textarea class="form-control" id="iconography" name="iconography" rows="3">{{ old('iconography', $museum->iconography ?? '') }}</textarea>
              <div class="form-text text-muted small">Iconographic themes, symbols, or narratives depicted. (CCO 8.3)</div>
            </div>

            <div class="mb-3">
              <label for="named_subjects" class="form-label">Named subjects</label>
              <input type="text" class="form-control" id="named_subjects" name="named_subjects"
                     value="{{ old('named_subjects', $museum->named_subjects ?? '') }}">
              <div class="form-text text-muted small">Named people, places, or events depicted. (CCO 8.4)</div>
            </div>

            <div class="mb-3">
              <label for="subject_display" class="form-label">Subject (Display)</label>
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
              <label for="state" class="form-label">State</label>
              <input type="text" class="form-control" id="state" name="state"
                     value="{{ old('state', $museum->state ?? '') }}">
              <div class="form-text text-muted small">The state of the print or edition. (CCO 10.3)</div>
            </div>

            <div class="mb-3">
              <label for="impression_quality" class="form-label">Impression quality</label>
              <input type="text" class="form-control" id="impression_quality" name="impression_quality"
                     value="{{ old('impression_quality', $museum->impression_quality ?? '') }}">
              <div class="form-text text-muted small">Quality assessment of the impression (for prints). (CCO 10.4)</div>
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
              <label for="signature" class="form-label">Signature</label>
              <textarea class="form-control" id="signature" name="signature" rows="2">{{ old('signature', $museum->signature ?? '') }}</textarea>
              <div class="form-text text-muted small">Description of the artist's signature on the work. (CCO 9.2)</div>
            </div>

            <div class="mb-3">
              <label for="marks" class="form-label">Marks/Labels</label>
              <textarea class="form-control" id="marks" name="marks" rows="2">{{ old('marks', $museum->marks ?? '') }}</textarea>
              <div class="form-text text-muted small">Collector's marks, labels, stamps, or other identifying marks. (CCO 9.3)</div>
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
              <label for="condition_summary" class="form-label">Condition summary <span class="form-required" title="This is a mandatory element.">*</span></label>
              <textarea class="form-control" id="condition_summary" name="condition_summary" rows="3">{{ old('condition_summary', $museum->condition_summary ?? '') }}</textarea>
              <div class="form-text text-muted small">Brief summary of the current condition. (CCO 12.1)</div>
            </div>

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
              <label for="repository" class="form-label">Repository <span class="form-required" title="This is a mandatory element.">*</span></label>
              <input type="text" class="form-control" id="repository" name="repository"
                     value="{{ old('repository', $museum->repository ?? '') }}" placeholder="Type to search repositories..." autocomplete="off">
              <div class="form-text text-muted small">The repository or institution that holds this object. (CCO 13.1)</div>
            </div>

            <div class="mb-3">
              <label for="location_within_repository" class="form-label">Location</label>
              <input type="text" class="form-control" id="location_within_repository" name="location_within_repository"
                     value="{{ old('location_within_repository', $museum->location_within_repository ?? '') }}">
              <div class="form-text text-muted small">Specific location within the repository (gallery, shelf, case). (CCO 13.2)</div>
            </div>

            <div class="mb-3">
              <label for="credit_line" class="form-label">Credit line</label>
              <input type="text" class="form-control" id="credit_line" name="credit_line"
                     value="{{ old('credit_line', $museum->credit_line ?? '') }}">
              <div class="form-text text-muted small">The credit line for display, acknowledging gift/purchase/bequest. (CCO 13.3)</div>
            </div>

            <div class="mb-3">
              <label for="description" class="form-label">Description</label>
              <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $museum->description ?? '') }}</textarea>
              <div class="form-text text-muted small">A free-text description of the work that supplements other fields. (CCO 11.1)</div>
            </div>

            <div class="mb-3">
              <label for="physical_description" class="form-label">Physical description</label>
              <textarea class="form-control" id="physical_description" name="physical_description" rows="3">{{ old('physical_description', $museum->physical_description ?? '') }}</textarea>
              <div class="form-text text-muted small">Description of the physical characteristics not covered elsewhere. (CCO 11.2)</div>
            </div>

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
              <label for="related_works" class="form-label">Related works</label>
              <textarea class="form-control" id="related_works" name="related_works" rows="3">{{ old('related_works', $museum->related_works ?? '') }}</textarea>
              <div class="form-text text-muted small">Identify related works and describe the nature of the relationship. (CCO 14.1)</div>
            </div>

            <div class="mb-3">
              <label for="relationship_type" class="form-label">Relationship type</label>
              <input type="text" class="form-control" id="relationship_type" name="relationship_type"
                     value="{{ old('relationship_type', $museum->relationship_type ?? '') }}">
              <div class="form-text text-muted small">The type of relationship between this work and the related work. (CCO 14.2)</div>
            </div>

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
              <label for="rights_statement" class="form-label">Rights statement</label>
              <textarea class="form-control" id="rights_statement" name="rights_statement" rows="3">{{ old('rights_statement', $museum->rights_statement ?? '') }}</textarea>
              <div class="form-text text-muted small">Statement of rights associated with the work. (CCO 15.1)</div>
            </div>

            <div class="mb-3">
              <label for="copyright_holder" class="form-label">Copyright holder</label>
              <input type="text" class="form-control" id="copyright_holder" name="copyright_holder"
                     value="{{ old('copyright_holder', $museum->copyright_holder ?? '') }}">
              <div class="form-text text-muted small">Name of the copyright holder. (CCO 15.2)</div>
            </div>

            <div class="mb-3">
              <label for="reproduction_conditions" class="form-label">Reproduction conditions</label>
              <textarea class="form-control" id="reproduction_conditions" name="reproduction_conditions" rows="2">{{ old('reproduction_conditions', $museum->reproduction_conditions ?? '') }}</textarea>
              <div class="form-text text-muted small">Conditions governing reproduction of the work. (CCO 15.3)</div>
            </div>

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

    {{-- ===== Watermark Settings ===== --}}
    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="heading-watermark">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#collapse-watermark"
                  aria-expanded="false" aria-controls="collapse-watermark"
                  style="background-color: var(--ahg-primary, #005837) !important; color: #fff !important;">
            Watermark Settings
            <span class="cco-chapter">Digital Protection</span>
          </button>
        </h2>
        <div id="collapse-watermark" class="accordion-collapse collapse" aria-labelledby="heading-watermark">
          <div class="accordion-body">

            <div class="cco-field">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="watermark_enabled" name="watermark_enabled" value="1"
                       {{ old('watermark_enabled', $watermarkSetting->watermark_enabled ?? 0) ? 'checked' : '' }}
                       style="width: 3em; height: 1.5em;">
                <label class="form-check-label" for="watermark_enabled" style="margin-left: 10px;">
                  <strong>Enable watermark for this object</strong>
                </label>
              </div>
            </div>

            <div id="watermark-options" style="{{ old('watermark_enabled', $watermarkSetting->watermark_enabled ?? 0) ? '' : 'display:none;' }}">

              <div class="cco-field">
                <div class="field-header">
                  <label for="watermark_type_id">System Watermark</label>
                </div>
                <div class="field-input">
                  <select name="watermark_type_id" id="watermark_type_id" class="form-select">
                    <option value="">Use default</option>
                    @foreach($watermarkTypes ?? [] as $type)
                      <option value="{{ $type->id }}" @selected(old('watermark_type_id', $watermarkSetting->watermark_type_id ?? '') == $type->id)>{{ $type->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>

              @if(($customWatermarks ?? collect())->count() > 0)
              <div class="cco-field">
                <div class="field-header">
                  <label for="custom_watermark_id">Or use Custom Watermark</label>
                </div>
                <div class="field-input">
                  <select name="custom_watermark_id" id="custom_watermark_id" class="form-select">
                    <option value="">None</option>
                    @foreach($customWatermarks as $custom)
                      <option value="{{ $custom->id }}" @selected(old('custom_watermark_id', $watermarkSetting->custom_watermark_id ?? '') == $custom->id)>
                        {{ $custom->name }}{{ $custom->object_id ? '' : ' (Global)' }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
              @endif

              <div class="cco-field" style="background: #fff3cd; border-left-color: #ffc107;">
                <div class="field-header">
                  <label>Upload NEW Custom Watermark</label>
                </div>
                <p class="text-muted small">Leave empty to keep existing selection above</p>

                <div class="row g-3">
                  <div class="col-md-6">
                    <label for="new_watermark_name" class="form-label">Watermark Name</label>
                    <input type="text" class="form-control" id="new_watermark_name"
                           name="new_watermark_name" placeholder="e.g., Company Logo">
                  </div>
                  <div class="col-md-6">
                    <label for="new_watermark_file" class="form-label">Watermark Image</label>
                    <input type="file" class="form-control" id="new_watermark_file"
                           name="new_watermark_file" accept="image/png,image/gif">
                    <div class="form-text">PNG or GIF with transparency recommended</div>
                  </div>
                </div>

                <div class="row g-3 mt-2">
                  <div class="col-md-6">
                    <label for="new_watermark_position" class="form-label">Position</label>
                    @php $wmPosition = old('new_watermark_position', $watermarkSetting->position ?? 'center'); @endphp
                    <select name="new_watermark_position" id="new_watermark_position" class="form-select">
                      @foreach(['center' => 'Center', 'top left' => 'Top Left', 'top center' => 'Top Center', 'top right' => 'Top Right', 'left center' => 'Left Center', 'right center' => 'Right Center', 'bottom left' => 'Bottom Left', 'bottom center' => 'Bottom Center', 'bottom right' => 'Bottom Right', 'repeat' => 'Repeat/Tile'] as $val => $label)
                        <option value="{{ $val }}" @selected($wmPosition == $val)>{{ $label }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-6">
                    @php $wmOpacity = old('new_watermark_opacity', round(($watermarkSetting->opacity ?? 0.4) * 100)); @endphp
                    <label for="new_watermark_opacity" class="form-label">
                      Opacity: <span id="opacity-value">{{ $wmOpacity }}%</span>
                    </label>
                    <input type="range" class="form-range" id="new_watermark_opacity"
                           name="new_watermark_opacity" min="10" max="100" step="5"
                           value="{{ $wmOpacity }}">
                  </div>
                </div>

                <div class="form-check mt-3">
                  <input class="form-check-input" type="checkbox" id="new_watermark_global"
                         name="new_watermark_global" value="1">
                  <label class="form-check-label" for="new_watermark_global">
                    Make available globally (for all records)
                  </label>
                </div>
              </div>

              <div class="cco-field">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="regenerate_watermark"
                         name="regenerate_watermark" value="1">
                  <label class="form-check-label" for="regenerate_watermark">
                    <strong>Regenerate derivatives with new watermark</strong>
                  </label>
                  <div class="form-text">Check this to apply the new watermark to existing images. This may take a moment.</div>
                </div>
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

                @if(!$isNew && isset($museum->updated_at) && $museum->updated_at)
                <div class="mb-3">
                  <label class="form-label fw-bold">Last updated</label>
                  <div>{{ \Carbon\Carbon::parse($museum->updated_at)->format('F j, Y, g:i a') }}</div>
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
      @if(!$isNew)
        <li><a href="{{ route('museum.show', $museum->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a href="{{ route('museum.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @endif
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
    var total = fields.length;
    var filled = 0;
    fields.forEach(function(f) {
      if (f.value && f.value.trim() !== '') filled++;
    });
    var pct = total > 0 ? Math.round((filled / total) * 100) : 0;
    var bar = document.getElementById('completeness-bar');
    var value = document.getElementById('completeness-value');
    if (bar && value) {
      bar.style.width = pct + '%';
      value.textContent = pct + '%';
      bar.className = 'progress-bar';
      if (pct >= 80) bar.classList.add('bg-success');
      else if (pct >= 50) bar.classList.add('bg-warning');
      else bar.classList.add('bg-danger');
    }
  }
  // Watermark toggle
  var enableToggle = document.getElementById('watermark_enabled');
  var optionsDiv = document.getElementById('watermark-options');
  if (enableToggle && optionsDiv) {
    enableToggle.addEventListener('change', function() {
      optionsDiv.style.display = this.checked ? 'block' : 'none';
    });
  }
  // Watermark opacity slider
  var opacitySlider = document.getElementById('new_watermark_opacity');
  var opacityValue = document.getElementById('opacity-value');
  if (opacitySlider && opacityValue) {
    opacitySlider.addEventListener('input', function() {
      opacityValue.textContent = this.value + '%';
    });
  }
  // Clear system watermark when custom selected and vice versa
  var systemSelect = document.getElementById('watermark_type_id');
  var customSelect = document.getElementById('custom_watermark_id');
  if (systemSelect && customSelect) {
    customSelect.addEventListener('change', function() { if (this.value) systemSelect.value = ''; });
    systemSelect.addEventListener('change', function() { if (this.value) customSelect.value = ''; });
  }
})();
</script>
@endpush

@push('css')
<style>
/* CCO Form Styling - Collections Management Dashboard Theme (cloned from AtoM) */
.sidebar-section {
  background: #fff;
  border-radius: 8px;
  padding: 15px;
  margin-bottom: 15px;
  border: 1px solid #ddd;
}
.sidebar-section h4 {
  color: var(--ahg-primary, #005837);
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 2px solid #e9ecef;
}
.template-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.template-option {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 12px;
  text-decoration: none;
  color: #333;
  background: #f8f9fa;
  border: 1px solid #e0e0e0;
  transition: all 0.2s;
}
.template-option:hover {
  background: #e9ecef;
  text-decoration: none;
  color: var(--ahg-primary, #005837);
}
.template-option.active {
  background: var(--ahg-primary, #005837);
  color: #fff;
  border-color: var(--ahg-primary, #005837);
}
.template-option i { margin-right: 5px; font-size: 11px; }
.progress-container {
  display: flex;
  align-items: center;
  gap: 10px;
}
.progress {
  flex: 1;
  height: 20px;
  background: #e9ecef;
  border-radius: 10px;
  overflow: hidden;
}
.progress-bar {
  height: 100%;
  border-radius: 10px;
  transition: width 0.3s;
}
.completeness-value { font-weight: 700; min-width: 40px; }
.btn-cco-guide {
  background: var(--ahg-primary, #005837);
  color: #fff;
  border: none;
}
.btn-cco-guide:hover { background: #145043; color: #fff; }
.legend-list { list-style: none; padding: 0; margin: 0; }
.legend-list li { margin-bottom: 6px; font-size: 12px; }
.badge-required { background: #e74c3c; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 10px; }
.badge-recommended { background: #f39c12; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 10px; }
.badge-optional { background: #95a5a6; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 10px; }

/* Accordion Sections */
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
.cco-cataloguing-form .accordion-button:focus { box-shadow: none; }
.cco-cataloguing-form .accordion-body { padding: 20px; background: #fff; }
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

.help-text { font-size: 13px; color: #666; margin: 0; }
</style>
@endpush
@endsection
