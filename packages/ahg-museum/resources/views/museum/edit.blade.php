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

      {{-- ===== Object Identification (CCO Ch 2) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingIdentification">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIdentification" aria-expanded="true" aria-controls="collapseIdentification">
            Object/Work
            <span class="cco-chapter">CCO Chapter 2</span>
          </button>
        </h2>
        <div id="collapseIdentification" class="accordion-collapse collapse show" aria-labelledby="headingIdentification" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            {{-- work_type: required, CCO 2.1, vocab aat_object_types --}}
            <div class="cco-field level-required" data-field="work_type">
              <div class="field-header">
                <label for="work_type">
                  Work type
                  <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">Required</span>
                  <span class="badge badge-cco" title="CCO Reference">2.1</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> AAT_OBJECT_TYPES</span>
                </span>
                <button type="button" class="btn-help" data-field="work_type" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <select class="form-select" id="work_type" name="work_type">
                  <option value="">-- Select --</option>
                  @foreach($workTypes as $wt)
                    <option value="{{ $wt }}" @selected(old('work_type', $museum->work_type ?? '') === $wt)>{{ $wt }}</option>
                  @endforeach
                </select>
              </div>
              <div class="field-help" id="help-work_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The broad category of the work (e.g. Painting, Sculpture, Drawing).</p>
                </div>
              </div>
            </div>

            {{-- work_type_qualifier: optional, CCO 2.1.1, no vocab --}}
            <div class="cco-field level-optional" data-field="work_type_qualifier">
              <div class="field-header">
                <label for="work_type_qualifier">
                  Work type qualifier
                </label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">2.1.1</span>
                </span>
                <button type="button" class="btn-help" data-field="work_type_qualifier" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <select class="form-select" id="work_type_qualifier" name="work_type_qualifier">
                  <option value="">-- Select --</option>
                  <option value="possibly" @selected(old('work_type_qualifier', $museum->work_type_qualifier ?? '') === 'possibly')>Possibly</option>
                  <option value="probably" @selected(old('work_type_qualifier', $museum->work_type_qualifier ?? '') === 'probably')>Probably</option>
                  <option value="formerly classified as" @selected(old('work_type_qualifier', $museum->work_type_qualifier ?? '') === 'formerly classified as')>Formerly classified as</option>
                </select>
              </div>
              <div class="field-help" id="help-work_type_qualifier" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Qualifies uncertainty about the work type. (CCO 2.1.1)</p>
                </div>
              </div>
            </div>

            {{-- components_count: optional, CCO 2.2, no vocab --}}
            <div class="cco-field level-optional" data-field="components_count">
              <div class="field-header">
                <label for="components_count">
                  Components/Parts
                </label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">2.2</span>
                </span>
                <button type="button" class="btn-help" data-field="components_count" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="components_count" name="components_count"
                       value="{{ old('components_count', $museum->components_count ?? '') }}">
              </div>
              <div class="field-help" id="help-components_count" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Number and description of physical components, e.g. "diptych (2 panels)", "portfolio of 12 prints". (CCO 2.2)</p>
                </div>
              </div>
            </div>

            {{-- object_number: required, CCO 2.3, no vocab --}}
            <div class="cco-field level-required" data-field="object_number">
              <div class="field-header">
                <label for="object_number">
                  Object number
                  <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">Required</span>
                  <span class="badge badge-cco" title="CCO Reference">2.3</span>
                </span>
                <button type="button" class="btn-help" data-field="object_number" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="object_number" name="object_number"
                       value="{{ old('object_number', $museum->object_number ?? '') }}">
              </div>
              <div class="field-help" id="help-object_number" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Unique identifier assigned by the repository. This should follow your institution's numbering system. (CCO 2.3)</p>
                </div>
              </div>
            </div>

            {{-- object_type: non-CCO field --}}
            <div class="cco-field level-optional" data-field="object_type">
              <div class="field-header">
                <label for="object_type">Object type</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="object_type" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="object_type" name="object_type"
                       value="{{ old('object_type', $museum->object_type ?? '') }}">
              </div>
              <div class="field-help" id="help-object_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">More specific object designation within the work type.</p>
                </div>
              </div>
            </div>

            {{-- classification: non-CCO field --}}
            <div class="cco-field level-optional" data-field="classification">
              <div class="field-header">
                <label for="classification">Classification</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="classification" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="classification" name="classification"
                       value="{{ old('classification', $museum->classification ?? '') }}">
              </div>
              <div class="field-help" id="help-classification" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Systematic arrangement or categorization of the object.</p>
                </div>
              </div>
            </div>

            {{-- object_class: non-CCO field --}}
            <div class="cco-field level-optional" data-field="object_class">
              <div class="field-header">
                <label for="object_class">Object class</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="object_class" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="object_class" name="object_class"
                       value="{{ old('object_class', $museum->object_class ?? '') }}">
              </div>
              <div class="field-help" id="help-object_class" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The class or genre to which this object belongs.</p>
                </div>
              </div>
            </div>

            {{-- object_category: non-CCO field --}}
            <div class="cco-field level-optional" data-field="object_category">
              <div class="field-header">
                <label for="object_category">Object category</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="object_category" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="object_category" name="object_category"
                       value="{{ old('object_category', $museum->object_category ?? '') }}">
              </div>
              <div class="field-help" id="help-object_category" style="display: none;">
                <div class="help-content">
                  <p class="help-text">A broader category for grouping objects of similar type.</p>
                </div>
              </div>
            </div>

            {{-- object_sub_category: non-CCO field --}}
            <div class="cco-field level-optional" data-field="object_sub_category">
              <div class="field-header">
                <label for="object_sub_category">Object sub-category</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="object_sub_category" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="object_sub_category" name="object_sub_category"
                       value="{{ old('object_sub_category', $museum->object_sub_category ?? '') }}">
              </div>
              <div class="field-help" id="help-object_sub_category" style="display: none;">
                <div class="help-content">
                  <p class="help-text">A more specific sub-category within the object category.</p>
                </div>
              </div>
            </div>

            {{-- identifier: non-CCO field --}}
            <div class="cco-field level-optional" data-field="identifier">
              <div class="field-header">
                <label for="identifier">Identifier</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="identifier" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="identifier" name="identifier"
                       value="{{ old('identifier', $museum->identifier ?? '') }}">
              </div>
              <div class="field-help" id="help-identifier" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The accession or catalogue number assigned to this object.</p>
                </div>
              </div>
            </div>

            {{-- record_type: non-CCO field --}}
            <div class="cco-field level-optional" data-field="record_type">
              <div class="field-header">
                <label for="record_type">Record type</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="record_type" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="record_type" name="record_type"
                       value="{{ old('record_type', $museum->record_type ?? '') }}">
              </div>
              <div class="field-help" id="help-record_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The type of catalogue record (e.g. item, group, volume).</p>
                </div>
              </div>
            </div>

            {{-- record_level: non-CCO field --}}
            <div class="cco-field level-optional" data-field="record_level">
              <div class="field-header">
                <label for="record_level">Record level</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="record_level" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="record_level" name="record_level"
                       value="{{ old('record_level', $museum->record_level ?? '') }}">
              </div>
              <div class="field-help" id="help-record_level" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Hierarchical level of this catalogue record.</p>
                </div>
              </div>
            </div>

            {{-- level_of_description_id: non-CCO field --}}
            <div class="cco-field level-optional" data-field="level_of_description_id">
              <div class="field-header">
                <label for="level_of_description_id">Level of description</label>
                <span class="field-badges"></span>
              </div>
              <div class="field-input">
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
      </div>

      {{-- ===== Titles/Names (CCO Ch 3) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingTitle">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTitle" aria-expanded="false" aria-controls="collapseTitle">
            Titles/Names
            <span class="cco-chapter">CCO Chapter 3</span>
          </button>
        </h2>
        <div id="collapseTitle" class="accordion-collapse collapse" aria-labelledby="headingTitle" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            {{-- title: required, CCO 3.1, no vocab --}}
            <div class="cco-field level-required" data-field="title">
              <div class="field-header">
                <label for="title">
                  Title
                  <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">Required</span>
                  <span class="badge badge-cco" title="CCO Reference">3.1</span>
                </span>
                <button type="button" class="btn-help" data-field="title" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title"
                       value="{{ old('title', $museum->title ?? '') }}" required>
                @error('title')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="field-help" id="help-title" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The primary name or title of the museum object. Required.</p>
                </div>
              </div>
            </div>

            {{-- title_type: required, CCO 3.1.1, vocab cco_title_types --}}
            <div class="cco-field level-required" data-field="title_type">
              <div class="field-header">
                <label for="title_type">
                  Title type
                  <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">Required</span>
                  <span class="badge badge-cco" title="CCO Reference">3.1.1</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> CCO_TITLE_TYPES</span>
                </span>
                <button type="button" class="btn-help" data-field="title_type" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
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
              </div>
              <div class="field-help" id="help-title_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The source or nature of the title. (CCO 3.1.1)</p>
                </div>
              </div>
            </div>

            {{-- title_language: optional, CCO 3.1.2, vocab iso639_2 --}}
            <div class="cco-field level-optional" data-field="title_language">
              <div class="field-header">
                <label for="title_language">Title language</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">3.1.2</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> ISO639_2</span>
                </span>
                <button type="button" class="btn-help" data-field="title_language" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="title_language" name="title_language"
                       value="{{ old('title_language', $museum->title_language ?? 'eng') }}">
              </div>
              <div class="field-help" id="help-title_language" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Language of the title (ISO 639-2 code). (CCO 3.1.2)</p>
                </div>
              </div>
            </div>

            {{-- alternate_titles: optional, CCO 3.2, no vocab --}}
            <div class="cco-field level-optional" data-field="alternate_titles">
              <div class="field-header">
                <label for="alternate_titles">Alternate titles</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">3.2</span>
                </span>
                <button type="button" class="btn-help" data-field="alternate_titles" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="alternate_titles" name="alternate_titles"
                       value="{{ old('alternate_titles', $museum->alternate_titles ?? $museum->alternate_title ?? '') }}">
              </div>
              <div class="field-help" id="help-alternate_titles" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Other titles by which the work is known. Include former titles, translations, and variant spellings. (CCO 3.2)</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Creator (CCO Ch 4) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCreator">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCreator" aria-expanded="false" aria-controls="collapseCreator">
            Creator
            <span class="cco-chapter">CCO Chapter 4 (Creator)</span>
          </button>
        </h2>
        <div id="collapseCreator" class="accordion-collapse collapse" aria-labelledby="headingCreator" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            {{-- creator_display: required, CCO 4.1, no vocab --}}
            <div class="cco-field level-required" data-field="creator_display">
              <div class="field-header">
                <label for="creator_display">
                  Creator (Display)
                  <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">Required</span>
                  <span class="badge badge-cco" title="CCO Reference">4.1</span>
                </span>
                <button type="button" class="btn-help" data-field="creator_display" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creator_display" name="creator_display"
                       value="{{ old('creator_display', $museum->creator_display ?? '') }}">
              </div>
              <div class="field-help" id="help-creator_display" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Creator name as it should appear in displays. Format: Surname, Forename (Nationality, birth-death). (CCO 4.1)</p>
                </div>
              </div>
            </div>

            {{-- creator: required, CCO 4.1, vocab ulan --}}
            <div class="cco-field level-required" data-field="creator">
              <div class="field-header">
                <label for="creator">
                  Creator (Authority)
                  <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">Required</span>
                  <span class="badge badge-cco" title="CCO Reference">4.1</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> ULAN</span>
                </span>
                <button type="button" class="btn-help" data-field="creator" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creator" name="creator"
                       value="{{ old('creator', $museum->creator ?? '') }}" placeholder="Type to search authority records..." autocomplete="off">
              </div>
              <div class="field-help" id="help-creator" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Link to authority record. Search ULAN or local authority file. (CCO 4.1)</p>
                </div>
              </div>
            </div>

            {{-- attribution_qualifier: optional, CCO 4.1.2, vocab cco_attribution --}}
            <div class="cco-field level-optional" data-field="attribution_qualifier">
              <div class="field-header">
                <label for="attribution_qualifier">Attribution qualifier</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">4.1.2</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> CCO_ATTRIBUTION</span>
                </span>
                <button type="button" class="btn-help" data-field="attribution_qualifier" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
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
              </div>
              <div class="field-help" id="help-attribution_qualifier" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Qualifies degree of certainty about attribution. (CCO 4.1.2)</p>
                </div>
              </div>
            </div>

            {{-- creator_identity: non-CCO field --}}
            <div class="cco-field level-optional" data-field="creator_identity">
              <div class="field-header">
                <label for="creator_identity">Creator identity</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="creator_identity" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creator_identity" name="creator_identity"
                       value="{{ old('creator_identity', $museum->creator_identity ?? '') }}">
              </div>
              <div class="field-help" id="help-creator_identity" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The name of the creator, artist, or maker of the object.</p>
                </div>
              </div>
            </div>

            {{-- creator_role: required, CCO 4.1.1, vocab aat_creator_roles --}}
            <div class="cco-field level-required" data-field="creator_role">
              <div class="field-header">
                <label for="creator_role">
                  Creator role
                  <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">Required</span>
                  <span class="badge badge-cco" title="CCO Reference">4.1.1</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> AAT_CREATOR_ROLES</span>
                </span>
                <button type="button" class="btn-help" data-field="creator_role" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creator_role" name="creator_role"
                       value="{{ old('creator_role', $museum->creator_role ?? '') }}">
              </div>
              <div class="field-help" id="help-creator_role" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The role of the creator (e.g. artist, architect, designer, maker). (CCO 4.1.1)</p>
                </div>
              </div>
            </div>

            {{-- creator_extent: non-CCO field --}}
            <div class="cco-field level-optional" data-field="creator_extent">
              <div class="field-header">
                <label for="creator_extent">Creator extent</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="creator_extent" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creator_extent" name="creator_extent"
                       value="{{ old('creator_extent', $museum->creator_extent ?? '') }}">
              </div>
              <div class="field-help" id="help-creator_extent" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Part of the work created by this creator (e.g. design, frame, gilding).</p>
                </div>
              </div>
            </div>

            {{-- creator_qualifier: non-CCO field --}}
            <div class="cco-field level-optional" data-field="creator_qualifier">
              <div class="field-header">
                <label for="creator_qualifier">Creator qualifier</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="creator_qualifier" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creator_qualifier" name="creator_qualifier"
                       value="{{ old('creator_qualifier', $museum->creator_qualifier ?? '') }}">
              </div>
              <div class="field-help" id="help-creator_qualifier" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Qualifier for the attribution (e.g. attributed to, circle of, studio of).</p>
                </div>
              </div>
            </div>

            {{-- creator_attribution: non-CCO field --}}
            <div class="cco-field level-optional" data-field="creator_attribution">
              <div class="field-header">
                <label for="creator_attribution">Creator attribution</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="creator_attribution" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creator_attribution" name="creator_attribution"
                       value="{{ old('creator_attribution', $museum->creator_attribution ?? '') }}">
              </div>
              <div class="field-help" id="help-creator_attribution" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Full attribution statement for the creator.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Creation (CCO Ch 4 cont. + Ch 5) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCreation">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCreation" aria-expanded="false" aria-controls="collapseCreation">
            Creation
            <span class="cco-chapter">CCO Chapter 4</span>
          </button>
        </h2>
        <div id="collapseCreation" class="accordion-collapse collapse" aria-labelledby="headingCreation" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            {{-- creation_date_display: required, CCO 4.2, no vocab --}}
            <div class="cco-field level-required" data-field="creation_date_display">
              <div class="field-header">
                <label for="creation_date_display">
                  Creation date (display)
                  <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">Required</span>
                  <span class="badge badge-cco" title="CCO Reference">4.2</span>
                </span>
                <button type="button" class="btn-help" data-field="creation_date_display" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creation_date_display" name="creation_date_display"
                       value="{{ old('creation_date_display', $museum->creation_date_display ?? '') }}">
              </div>
              <div class="field-help" id="help-creation_date_display" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The date of creation as displayed to users (free text, e.g. "circa 1650"). (CCO 4.2)</p>
                </div>
              </div>
            </div>

            {{-- creation_date_earliest: optional, CCO 4.2.1, no vocab --}}
            {{-- creation_date_latest: optional, CCO 4.2.2, no vocab --}}
            <div class="row">
              <div class="col-md-6">
                <div class="cco-field level-optional" data-field="creation_date_earliest">
                  <div class="field-header">
                    <label for="creation_date_earliest">Creation date (earliest)</label>
                    <span class="field-badges">
                      <span class="badge badge-cco" title="CCO Reference">4.2.1</span>
                    </span>
                    <button type="button" class="btn-help" data-field="creation_date_earliest" title="Help">
                      <i class="fa fa-question-circle"></i>
                    </button>
                  </div>
                  <div class="field-input">
                    <input type="date" class="form-control" id="creation_date_earliest" name="creation_date_earliest"
                           value="{{ old('creation_date_earliest', $museum->creation_date_earliest ?? '') }}">
                  </div>
                  <div class="field-help" id="help-creation_date_earliest" style="display: none;">
                    <div class="help-content">
                      <p class="help-text">The earliest possible creation date for indexing. (CCO 4.2.1)</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="cco-field level-optional" data-field="creation_date_latest">
                  <div class="field-header">
                    <label for="creation_date_latest">Creation date (latest)</label>
                    <span class="field-badges">
                      <span class="badge badge-cco" title="CCO Reference">4.2.2</span>
                    </span>
                    <button type="button" class="btn-help" data-field="creation_date_latest" title="Help">
                      <i class="fa fa-question-circle"></i>
                    </button>
                  </div>
                  <div class="field-input">
                    <input type="date" class="form-control" id="creation_date_latest" name="creation_date_latest"
                           value="{{ old('creation_date_latest', $museum->creation_date_latest ?? '') }}">
                  </div>
                  <div class="field-help" id="help-creation_date_latest" style="display: none;">
                    <div class="help-content">
                      <p class="help-text">The latest possible creation date for indexing. (CCO 4.2.2)</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {{-- creation_date_qualifier: non-CCO field --}}
            <div class="cco-field level-optional" data-field="creation_date_qualifier">
              <div class="field-header">
                <label for="creation_date_qualifier">Creation date qualifier</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="creation_date_qualifier" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creation_date_qualifier" name="creation_date_qualifier"
                       value="{{ old('creation_date_qualifier', $museum->creation_date_qualifier ?? '') }}">
              </div>
              <div class="field-help" id="help-creation_date_qualifier" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Qualifier for the date (e.g. circa, before, after, probably).</p>
                </div>
              </div>
            </div>

            {{-- creation_place: optional, CCO 4.3, vocab tgn --}}
            <div class="cco-field level-optional" data-field="creation_place">
              <div class="field-header">
                <label for="creation_place">Place of creation</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">4.3</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> TGN</span>
                </span>
                <button type="button" class="btn-help" data-field="creation_place" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creation_place" name="creation_place"
                       value="{{ old('creation_place', $museum->creation_place ?? '') }}" placeholder="Type to search places..." autocomplete="off">
              </div>
              <div class="field-help" id="help-creation_place" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The geographic location where the object was created. Use TGN (Getty Thesaurus of Geographic Names) when possible. (CCO 4.3)</p>
                </div>
              </div>
            </div>

            {{-- culture: optional, CCO 4.4, vocab aat_cultures --}}
            <div class="cco-field level-optional" data-field="culture">
              <div class="field-header">
                <label for="culture">Culture/People</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">4.4</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> AAT_CULTURES</span>
                </span>
                <button type="button" class="btn-help" data-field="culture" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="culture" name="culture"
                       value="{{ old('culture', $museum->culture ?? '') }}" placeholder="Type to search cultures..." autocomplete="off">
              </div>
              <div class="field-help" id="help-culture" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The culture, people, or nationality associated with the creation. Use AAT culture terms when possible. (CCO 4.4)</p>
                </div>
              </div>
            </div>

            {{-- creation_place_type: non-CCO field --}}
            <div class="cco-field level-optional" data-field="creation_place_type">
              <div class="field-header">
                <label for="creation_place_type">Creation place type</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="creation_place_type" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creation_place_type" name="creation_place_type"
                       value="{{ old('creation_place_type', $museum->creation_place_type ?? '') }}">
              </div>
              <div class="field-help" id="help-creation_place_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Type of place (e.g. country, city, region, site).</p>
                </div>
              </div>
            </div>

            {{-- discovery_place: non-CCO field --}}
            <div class="cco-field level-optional" data-field="discovery_place">
              <div class="field-header">
                <label for="discovery_place">Discovery place</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="discovery_place" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="discovery_place" name="discovery_place"
                       value="{{ old('discovery_place', $museum->discovery_place ?? '') }}">
              </div>
              <div class="field-help" id="help-discovery_place" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The location where the object was found or discovered.</p>
                </div>
              </div>
            </div>

            {{-- discovery_place_type: non-CCO field --}}
            <div class="cco-field level-optional" data-field="discovery_place_type">
              <div class="field-header">
                <label for="discovery_place_type">Discovery place type</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="discovery_place_type" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="discovery_place_type" name="discovery_place_type"
                       value="{{ old('discovery_place_type', $museum->discovery_place_type ?? '') }}">
              </div>
              <div class="field-help" id="help-discovery_place_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Type of discovery place (e.g. archaeological site, tomb).</p>
                </div>
              </div>
            </div>

            {{-- style: optional, CCO 5.1, vocab aat_styles --}}
            <div class="cco-field level-optional" data-field="style">
              <div class="field-header">
                <label for="style">Style</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">5.1</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> AAT_STYLES</span>
                </span>
                <button type="button" class="btn-help" data-field="style" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="style" name="style"
                       value="{{ old('style', $museum->style ?? '') }}">
              </div>
              <div class="field-help" id="help-style" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The artistic style (e.g. Baroque, Art Deco, Impressionist). (CCO 5.1)</p>
                </div>
              </div>
            </div>

            {{-- period: optional, CCO 5.2, vocab aat_periods --}}
            <div class="cco-field level-optional" data-field="period">
              <div class="field-header">
                <label for="period">Period</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">5.2</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> AAT_PERIODS</span>
                </span>
                <button type="button" class="btn-help" data-field="period" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="period" name="period"
                       value="{{ old('period', $museum->period ?? '') }}">
              </div>
              <div class="field-help" id="help-period" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The historical period (e.g. Renaissance, Iron Age, Victorian). (CCO 5.2)</p>
                </div>
              </div>
            </div>

            {{-- cultural_group: non-CCO field --}}
            <div class="cco-field level-optional" data-field="cultural_group">
              <div class="field-header">
                <label for="cultural_group">Cultural group</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="cultural_group" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="cultural_group" name="cultural_group"
                       value="{{ old('cultural_group', $museum->cultural_group ?? '') }}">
              </div>
              <div class="field-help" id="help-cultural_group" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The culture or people associated with the creation of this object.</p>
                </div>
              </div>
            </div>

            {{-- movement: non-CCO field --}}
            <div class="cco-field level-optional" data-field="movement">
              <div class="field-header">
                <label for="movement">Movement</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="movement" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="movement" name="movement"
                       value="{{ old('movement', $museum->movement ?? '') }}">
              </div>
              <div class="field-help" id="help-movement" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The artistic movement (e.g. Cubism, Surrealism, Minimalism).</p>
                </div>
              </div>
            </div>

            {{-- school_group: optional, CCO 5.3, no vocab --}}
            <div class="cco-field level-optional" data-field="school_group">
              <div class="field-header">
                <label for="school_group">School/Group</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">5.3</span>
                </span>
                <button type="button" class="btn-help" data-field="school_group" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="school_group" name="school_group"
                       value="{{ old('school_group', $museum->school_group ?? $museum->school ?? '') }}" placeholder="Type to search..." autocomplete="off">
              </div>
              <div class="field-help" id="help-school_group" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The school of art or artistic group (e.g. Flemish School, Hudson River School). (CCO 5.3)</p>
                </div>
              </div>
            </div>

            {{-- dynasty: non-CCO field --}}
            <div class="cco-field level-optional" data-field="dynasty">
              <div class="field-header">
                <label for="dynasty">Dynasty</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="dynasty" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="dynasty" name="dynasty"
                       value="{{ old('dynasty', $museum->dynasty ?? '') }}">
              </div>
              <div class="field-help" id="help-dynasty" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The dynasty or ruling house (e.g. Ming, Tudor, Ottoman).</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Measurements (CCO Ch 6) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingMeasurements">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMeasurements" aria-expanded="false" aria-controls="collapseMeasurements">
            Measurements
            <span class="cco-chapter">CCO Chapter 6</span>
          </button>
        </h2>
        <div id="collapseMeasurements" class="accordion-collapse collapse" aria-labelledby="headingMeasurements" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            {{-- measurements: non-CCO field --}}
            <div class="cco-field level-optional" data-field="measurements">
              <div class="field-header">
                <label for="measurements">Measurements</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="measurements" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="measurements" name="measurements"
                       value="{{ old('measurements', $museum->measurements ?? '') }}">
              </div>
              <div class="field-help" id="help-measurements" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Overall measurements display (e.g. "72.4 x 91.4 cm").</p>
                </div>
              </div>
            </div>

            {{-- dimensions_display: required, CCO 6.1, no vocab --}}
            <div class="cco-field level-required" data-field="dimensions_display">
              <div class="field-header">
                <label for="dimensions_display">
                  Dimensions (Display)
                  <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">Required</span>
                  <span class="badge badge-cco" title="CCO Reference">6.1</span>
                </span>
                <button type="button" class="btn-help" data-field="dimensions_display" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="dimensions_display" name="dimensions_display"
                       value="{{ old('dimensions_display', $museum->dimensions_display ?? '') }}">
              </div>
              <div class="field-help" id="help-dimensions_display" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Dimensions as they should appear in displays, e.g. "72.4 x 91.4 cm (28 1/2 x 36 in.)". (CCO 6.1)</p>
                </div>
              </div>
            </div>

            {{-- height_value, width_value, depth_value: optional, CCO 6.2 --}}
            {{-- weight_value: optional, CCO 6.3 --}}
            <div class="row">
              <div class="col-md-3">
                <div class="cco-field level-optional" data-field="height_value">
                  <div class="field-header">
                    <label for="height_value">Height</label>
                    <span class="field-badges">
                      <span class="badge badge-cco" title="CCO Reference">6.2</span>
                    </span>
                  </div>
                  <div class="field-input">
                    <input type="text" class="form-control" id="height_value" name="height_value"
                           value="{{ old('height_value', $museum->height_value ?? '') }}">
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="cco-field level-optional" data-field="width_value">
                  <div class="field-header">
                    <label for="width_value">Width</label>
                    <span class="field-badges">
                      <span class="badge badge-cco" title="CCO Reference">6.2</span>
                    </span>
                  </div>
                  <div class="field-input">
                    <input type="text" class="form-control" id="width_value" name="width_value"
                           value="{{ old('width_value', $museum->width_value ?? '') }}">
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="cco-field level-optional" data-field="depth_value">
                  <div class="field-header">
                    <label for="depth_value">Depth</label>
                    <span class="field-badges">
                      <span class="badge badge-cco" title="CCO Reference">6.2</span>
                    </span>
                  </div>
                  <div class="field-input">
                    <input type="text" class="form-control" id="depth_value" name="depth_value"
                           value="{{ old('depth_value', $museum->depth_value ?? '') }}">
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="cco-field level-optional" data-field="weight_value">
                  <div class="field-header">
                    <label for="weight_value">Weight</label>
                    <span class="field-badges">
                      <span class="badge badge-cco" title="CCO Reference">6.3</span>
                    </span>
                  </div>
                  <div class="field-input">
                    <input type="text" class="form-control" id="weight_value" name="weight_value"
                           value="{{ old('weight_value', $museum->weight_value ?? '') }}">
                  </div>
                </div>
              </div>
            </div>

            {{-- dimension_notes: optional, CCO 6.4, no vocab --}}
            <div class="cco-field level-optional" data-field="dimension_notes">
              <div class="field-header">
                <label for="dimension_notes">Dimension notes</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">6.4</span>
                </span>
                <button type="button" class="btn-help" data-field="dimension_notes" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="dimension_notes" name="dimension_notes" rows="2">{{ old('dimension_notes', $museum->dimension_notes ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-dimension_notes" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Notes about how measurements were taken or special considerations. (CCO 6.4)</p>
                </div>
              </div>
            </div>

            {{-- dimensions: non-CCO field --}}
            <div class="cco-field level-optional" data-field="dimensions">
              <div class="field-header">
                <label for="dimensions">Dimensions (legacy)</label>
                <span class="field-badges"></span>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="dimensions" name="dimensions"
                       value="{{ old('dimensions', $museum->dimensions ?? '') }}">
              </div>
            </div>

            {{-- orientation: non-CCO field --}}
            <div class="cco-field level-optional" data-field="orientation">
              <div class="field-header">
                <label for="orientation">Orientation</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="orientation" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="orientation" name="orientation"
                       value="{{ old('orientation', $museum->orientation ?? '') }}">
              </div>
              <div class="field-help" id="help-orientation" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Orientation of the object (e.g. portrait, landscape).</p>
                </div>
              </div>
            </div>

            {{-- shape: non-CCO field --}}
            <div class="cco-field level-optional" data-field="shape">
              <div class="field-header">
                <label for="shape">Shape</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="shape" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="shape" name="shape"
                       value="{{ old('shape', $museum->shape ?? '') }}">
              </div>
              <div class="field-help" id="help-shape" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The shape of the object (e.g. rectangular, circular, irregular).</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Materials / Techniques (CCO Ch 7) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingMaterials">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMaterials" aria-expanded="false" aria-controls="collapseMaterials">
            Materials/Techniques
            <span class="cco-chapter">CCO Chapter 7</span>
          </button>
        </h2>
        <div id="collapseMaterials" class="accordion-collapse collapse" aria-labelledby="headingMaterials" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            {{-- materials_display: required, CCO 7.1, no vocab --}}
            <div class="cco-field level-required" data-field="materials_display">
              <div class="field-header">
                <label for="materials_display">
                  Medium (Display)
                  <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">Required</span>
                  <span class="badge badge-cco" title="CCO Reference">7.1</span>
                </span>
                <button type="button" class="btn-help" data-field="materials_display" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="materials_display" name="materials_display"
                       value="{{ old('materials_display', $museum->materials_display ?? '') }}">
              </div>
              <div class="field-help" id="help-materials_display" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Medium as it should appear in displays, e.g. "oil on canvas", "gelatin silver print". (CCO 7.1)</p>
                </div>
              </div>
            </div>

            {{-- materials: optional, CCO 7.1.1, vocab aat_materials --}}
            <div class="cco-field level-optional" data-field="materials">
              <div class="field-header">
                <label for="materials">Materials (Indexed)</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">7.1.1</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> AAT_MATERIALS</span>
                </span>
                <button type="button" class="btn-help" data-field="materials" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="materials" name="materials" rows="3">{{ old('materials', $museum->materials ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-materials" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Individual materials indexed for searching. Use AAT material terms. (CCO 7.1.1)</p>
                </div>
              </div>
            </div>

            {{-- techniques: optional, CCO 7.2, vocab aat_techniques --}}
            <div class="cco-field level-optional" data-field="techniques">
              <div class="field-header">
                <label for="techniques">Techniques</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">7.2</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> AAT_TECHNIQUES</span>
                </span>
                <button type="button" class="btn-help" data-field="techniques" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="techniques" name="techniques" rows="3">{{ old('techniques', $museum->techniques ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-techniques" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The techniques or processes used in creating the object. (CCO 7.2)</p>
                </div>
              </div>
            </div>

            {{-- technique_cco: non-CCO field --}}
            <div class="cco-field level-optional" data-field="technique_cco">
              <div class="field-header">
                <label for="technique_cco">Technique (CCO)</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="technique_cco" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="technique_cco" name="technique_cco"
                       value="{{ old('technique_cco', $museum->technique_cco ?? '') }}">
              </div>
              <div class="field-help" id="help-technique_cco" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Controlled term for the technique per CCO standard.</p>
                </div>
              </div>
            </div>

            {{-- technique_qualifier: non-CCO field --}}
            <div class="cco-field level-optional" data-field="technique_qualifier">
              <div class="field-header">
                <label for="technique_qualifier">Technique qualifier</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="technique_qualifier" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="technique_qualifier" name="technique_qualifier"
                       value="{{ old('technique_qualifier', $museum->technique_qualifier ?? '') }}">
              </div>
              <div class="field-help" id="help-technique_qualifier" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Qualifier for the technique (e.g. possibly, probably).</p>
                </div>
              </div>
            </div>

            {{-- support: optional, CCO 7.3, vocab aat_supports --}}
            <div class="cco-field level-optional" data-field="support">
              <div class="field-header">
                <label for="support">Support</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">7.3</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> AAT_SUPPORTS</span>
                </span>
                <button type="button" class="btn-help" data-field="support" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="support" name="support"
                       value="{{ old('support', $museum->support ?? '') }}">
              </div>
              <div class="field-help" id="help-support" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The material on which the work is executed, e.g. "canvas", "paper", "panel". (CCO 7.3)</p>
                </div>
              </div>
            </div>

            {{-- facture_description: non-CCO field --}}
            <div class="cco-field level-optional" data-field="facture_description">
              <div class="field-header">
                <label for="facture_description">Facture description</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="facture_description" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="facture_description" name="facture_description" rows="3">{{ old('facture_description', $museum->facture_description ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-facture_description" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Description of how the object was made (brush strokes, casting marks, etc.).</p>
                </div>
              </div>
            </div>

            {{-- color: non-CCO field --}}
            <div class="cco-field level-optional" data-field="color">
              <div class="field-header">
                <label for="color">Color</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="color" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="color" name="color"
                       value="{{ old('color', $museum->color ?? '') }}">
              </div>
              <div class="field-help" id="help-color" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Predominant colors of the object.</p>
                </div>
              </div>
            </div>

            {{-- physical_appearance: non-CCO field --}}
            <div class="cco-field level-optional" data-field="physical_appearance">
              <div class="field-header">
                <label for="physical_appearance">Physical appearance</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="physical_appearance" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="physical_appearance" name="physical_appearance" rows="3">{{ old('physical_appearance', $museum->physical_appearance ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-physical_appearance" style="display: none;">
                <div class="help-content">
                  <p class="help-text">General description of the physical appearance.</p>
                </div>
              </div>
            </div>

            {{-- extent_and_medium: non-CCO field --}}
            <div class="cco-field level-optional" data-field="extent_and_medium">
              <div class="field-header">
                <label for="extent_and_medium">Extent and medium</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="extent_and_medium" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="extent_and_medium" name="extent_and_medium" rows="2">{{ old('extent_and_medium', $museum->extent_and_medium ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-extent_and_medium" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Summary of physical extent and medium.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Subject / Content (CCO Ch 8) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingSubject">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSubject" aria-expanded="false" aria-controls="collapseSubject">
            Subject Matter
            <span class="cco-chapter">CCO Chapter 8</span>
          </button>
        </h2>
        <div id="collapseSubject" class="accordion-collapse collapse" aria-labelledby="headingSubject" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            {{-- scope_and_content: non-CCO field --}}
            <div class="cco-field level-optional" data-field="scope_and_content">
              <div class="field-header">
                <label for="scope_and_content">Scope and content</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="scope_and_content" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="scope_and_content" name="scope_and_content" rows="4">{{ old('scope_and_content', $museum->scope_and_content ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-scope_and_content" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Description of the content, subject matter, and iconography.</p>
                </div>
              </div>
            </div>

            {{-- style_period: non-CCO field --}}
            <div class="cco-field level-optional" data-field="style_period">
              <div class="field-header">
                <label for="style_period">Style / Period</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="style_period" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="style_period" name="style_period"
                       value="{{ old('style_period', $museum->style_period ?? '') }}">
              </div>
              <div class="field-help" id="help-style_period" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Combined style and period classification.</p>
                </div>
              </div>
            </div>

            {{-- cultural_context: non-CCO field --}}
            <div class="cco-field level-optional" data-field="cultural_context">
              <div class="field-header">
                <label for="cultural_context">Cultural context</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="cultural_context" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="cultural_context" name="cultural_context"
                       value="{{ old('cultural_context', $museum->cultural_context ?? '') }}">
              </div>
              <div class="field-help" id="help-cultural_context" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The cultural or social context of the object.</p>
                </div>
              </div>
            </div>

            {{-- subject_indexing_type: non-CCO field --}}
            <div class="cco-field level-optional" data-field="subject_indexing_type">
              <div class="field-header">
                <label for="subject_indexing_type">Subject indexing type</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="subject_indexing_type" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="subject_indexing_type" name="subject_indexing_type"
                       value="{{ old('subject_indexing_type', $museum->subject_indexing_type ?? '') }}">
              </div>
              <div class="field-help" id="help-subject_indexing_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Type of subject term (e.g. description, identification, interpretation).</p>
                </div>
              </div>
            </div>

            {{-- subjects_depicted: optional, CCO 8.2, vocab aat_subjects --}}
            <div class="cco-field level-optional" data-field="subjects_depicted">
              <div class="field-header">
                <label for="subjects_depicted">Subjects depicted</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">8.2</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> AAT_SUBJECTS</span>
                </span>
                <button type="button" class="btn-help" data-field="subjects_depicted" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="subjects_depicted" name="subjects_depicted"
                       value="{{ old('subjects_depicted', $museum->subjects_depicted ?? '') }}" placeholder="Type to search subjects..." autocomplete="off">
              </div>
              <div class="field-help" id="help-subjects_depicted" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Specific subjects depicted in the work. Use controlled vocabulary terms. (CCO 8.2)</p>
                </div>
              </div>
            </div>

            {{-- iconography: optional, CCO 8.3, vocab iconclass --}}
            <div class="cco-field level-optional" data-field="iconography">
              <div class="field-header">
                <label for="iconography">Iconography</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">8.3</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> ICONCLASS</span>
                </span>
                <button type="button" class="btn-help" data-field="iconography" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="iconography" name="iconography" rows="3">{{ old('iconography', $museum->iconography ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-iconography" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Iconographic themes, symbols, or narratives depicted. (CCO 8.3)</p>
                </div>
              </div>
            </div>

            {{-- named_subjects: optional, CCO 8.4, no vocab --}}
            <div class="cco-field level-optional" data-field="named_subjects">
              <div class="field-header">
                <label for="named_subjects">Named subjects</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">8.4</span>
                </span>
                <button type="button" class="btn-help" data-field="named_subjects" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="named_subjects" name="named_subjects"
                       value="{{ old('named_subjects', $museum->named_subjects ?? '') }}">
              </div>
              <div class="field-help" id="help-named_subjects" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Named people, places, or events depicted. (CCO 8.4)</p>
                </div>
              </div>
            </div>

            {{-- subject_display: optional, CCO 8.1, no vocab --}}
            <div class="cco-field level-optional" data-field="subject_display">
              <div class="field-header">
                <label for="subject_display">Subject (Display)</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">8.1</span>
                </span>
                <button type="button" class="btn-help" data-field="subject_display" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="subject_display" name="subject_display" rows="3">{{ old('subject_display', $museum->subject_display ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-subject_display" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Display text for the subject of the work. (CCO 8.1)</p>
                </div>
              </div>
            </div>

            {{-- subject_extent: non-CCO field --}}
            <div class="cco-field level-optional" data-field="subject_extent">
              <div class="field-header">
                <label for="subject_extent">Subject extent</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="subject_extent" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="subject_extent" name="subject_extent"
                       value="{{ old('subject_extent', $museum->subject_extent ?? '') }}">
              </div>
              <div class="field-help" id="help-subject_extent" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Part of the work to which the subject pertains.</p>
                </div>
              </div>
            </div>

            {{-- historical_context: non-CCO field --}}
            <div class="cco-field level-optional" data-field="historical_context">
              <div class="field-header">
                <label for="historical_context">Historical context</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="historical_context" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="historical_context" name="historical_context" rows="3">{{ old('historical_context', $museum->historical_context ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-historical_context" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Historical background or context for the object.</p>
                </div>
              </div>
            </div>

            {{-- architectural_context: non-CCO field --}}
            <div class="cco-field level-optional" data-field="architectural_context">
              <div class="field-header">
                <label for="architectural_context">Architectural context</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="architectural_context" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="architectural_context" name="architectural_context" rows="3">{{ old('architectural_context', $museum->architectural_context ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-architectural_context" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Architectural setting or context for the object.</p>
                </div>
              </div>
            </div>

            {{-- archaeological_context: non-CCO field --}}
            <div class="cco-field level-optional" data-field="archaeological_context">
              <div class="field-header">
                <label for="archaeological_context">Archaeological context</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="archaeological_context" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="archaeological_context" name="archaeological_context" rows="3">{{ old('archaeological_context', $museum->archaeological_context ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-archaeological_context" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Archaeological context (excavation site, stratigraphic layer, etc.).</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== State/Edition (CCO Ch 10) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingEdition">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEdition" aria-expanded="false" aria-controls="collapseEdition">
            State/Edition
            <span class="cco-chapter">CCO Chapter 10</span>
          </button>
        </h2>
        <div id="collapseEdition" class="accordion-collapse collapse" aria-labelledby="headingEdition" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            {{-- edition_description: non-CCO field --}}
            <div class="cco-field level-optional" data-field="edition_description">
              <div class="field-header">
                <label for="edition_description">Edition description</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="edition_description" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="edition_description" name="edition_description" rows="2">{{ old('edition_description', $museum->edition_description ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-edition_description" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Description of the edition (for prints, photographs, multiples).</p>
                </div>
              </div>
            </div>

            {{-- edition_number: optional, CCO 10.1, no vocab --}}
            {{-- edition_size: optional, CCO 10.2, no vocab --}}
            <div class="row">
              <div class="col-md-6">
                <div class="cco-field level-optional" data-field="edition_number">
                  <div class="field-header">
                    <label for="edition_number">Edition number</label>
                    <span class="field-badges">
                      <span class="badge badge-cco" title="CCO Reference">10.1</span>
                    </span>
                    <button type="button" class="btn-help" data-field="edition_number" title="Help">
                      <i class="fa fa-question-circle"></i>
                    </button>
                  </div>
                  <div class="field-input">
                    <input type="text" class="form-control" id="edition_number" name="edition_number"
                           value="{{ old('edition_number', $museum->edition_number ?? '') }}">
                  </div>
                  <div class="field-help" id="help-edition_number" style="display: none;">
                    <div class="help-content">
                      <p class="help-text">Number of this impression within the edition. (CCO 10.1)</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="cco-field level-optional" data-field="edition_size">
                  <div class="field-header">
                    <label for="edition_size">Edition size</label>
                    <span class="field-badges">
                      <span class="badge badge-cco" title="CCO Reference">10.2</span>
                    </span>
                    <button type="button" class="btn-help" data-field="edition_size" title="Help">
                      <i class="fa fa-question-circle"></i>
                    </button>
                  </div>
                  <div class="field-input">
                    <input type="text" class="form-control" id="edition_size" name="edition_size"
                           value="{{ old('edition_size', $museum->edition_size ?? '') }}">
                  </div>
                  <div class="field-help" id="help-edition_size" style="display: none;">
                    <div class="help-content">
                      <p class="help-text">Total number of impressions in the edition. (CCO 10.2)</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {{-- state: optional, CCO 10.3, no vocab --}}
            <div class="cco-field level-optional" data-field="state">
              <div class="field-header">
                <label for="state">State</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">10.3</span>
                </span>
                <button type="button" class="btn-help" data-field="state" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="state" name="state"
                       value="{{ old('state', $museum->state ?? '') }}">
              </div>
              <div class="field-help" id="help-state" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The state of the print or edition. (CCO 10.3)</p>
                </div>
              </div>
            </div>

            {{-- impression_quality: optional, CCO 10.4, no vocab --}}
            <div class="cco-field level-optional" data-field="impression_quality">
              <div class="field-header">
                <label for="impression_quality">Impression quality</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">10.4</span>
                </span>
                <button type="button" class="btn-help" data-field="impression_quality" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="impression_quality" name="impression_quality"
                       value="{{ old('impression_quality', $museum->impression_quality ?? '') }}">
              </div>
              <div class="field-help" id="help-impression_quality" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Quality assessment of the impression (for prints). (CCO 10.4)</p>
                </div>
              </div>
            </div>

            {{-- state_identification: non-CCO field --}}
            <div class="cco-field level-optional" data-field="state_identification">
              <div class="field-header">
                <label for="state_identification">State identification</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="state_identification" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="state_identification" name="state_identification"
                       value="{{ old('state_identification', $museum->state_identification ?? '') }}">
              </div>
              <div class="field-help" id="help-state_identification" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Identification of the state (e.g. "State III of V").</p>
                </div>
              </div>
            </div>

            {{-- state_description: non-CCO field --}}
            <div class="cco-field level-optional" data-field="state_description">
              <div class="field-header">
                <label for="state_description">State description</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="state_description" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="state_description" name="state_description" rows="2">{{ old('state_description', $museum->state_description ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-state_description" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Description of changes made in this state.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Inscriptions (CCO Ch 9) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingInscriptions">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseInscriptions" aria-expanded="false" aria-controls="collapseInscriptions">
            Inscriptions
            <span class="cco-chapter">CCO Chapter 9</span>
          </button>
        </h2>
        <div id="collapseInscriptions" class="accordion-collapse collapse" aria-labelledby="headingInscriptions" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            {{-- inscription: non-CCO field --}}
            <div class="cco-field level-optional" data-field="inscription">
              <div class="field-header">
                <label for="inscription">Inscription</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="inscription" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="inscription" name="inscription" rows="3">{{ old('inscription', $museum->inscription ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-inscription" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Text of any inscriptions on the object.</p>
                </div>
              </div>
            </div>

            {{-- inscriptions: optional, CCO 9.1, no vocab --}}
            <div class="cco-field level-optional" data-field="inscriptions">
              <div class="field-header">
                <label for="inscriptions">Inscriptions (additional)</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">9.1</span>
                </span>
                <button type="button" class="btn-help" data-field="inscriptions" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="inscriptions" name="inscriptions" rows="3">{{ old('inscriptions', $museum->inscriptions ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-inscriptions" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Additional inscriptions not captured above. (CCO 9.1)</p>
                </div>
              </div>
            </div>

            {{-- inscription_transcription: non-CCO field --}}
            <div class="cco-field level-optional" data-field="inscription_transcription">
              <div class="field-header">
                <label for="inscription_transcription">Inscription transcription</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="inscription_transcription" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="inscription_transcription" name="inscription_transcription" rows="3">{{ old('inscription_transcription', $museum->inscription_transcription ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-inscription_transcription" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Full transcription of the inscription text.</p>
                </div>
              </div>
            </div>

            {{-- inscription_type: non-CCO field --}}
            <div class="cco-field level-optional" data-field="inscription_type">
              <div class="field-header">
                <label for="inscription_type">Inscription type</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="inscription_type" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="inscription_type" name="inscription_type"
                       value="{{ old('inscription_type', $museum->inscription_type ?? '') }}">
              </div>
              <div class="field-help" id="help-inscription_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Type of inscription (e.g. signature, date, dedication, stamp).</p>
                </div>
              </div>
            </div>

            {{-- inscription_location: non-CCO field --}}
            <div class="cco-field level-optional" data-field="inscription_location">
              <div class="field-header">
                <label for="inscription_location">Inscription location</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="inscription_location" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="inscription_location" name="inscription_location"
                       value="{{ old('inscription_location', $museum->inscription_location ?? '') }}">
              </div>
              <div class="field-help" id="help-inscription_location" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Location of the inscription on the object (e.g. lower right, verso).</p>
                </div>
              </div>
            </div>

            {{-- inscription_language: non-CCO field --}}
            <div class="cco-field level-optional" data-field="inscription_language">
              <div class="field-header">
                <label for="inscription_language">Inscription language</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="inscription_language" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="inscription_language" name="inscription_language"
                       value="{{ old('inscription_language', $museum->inscription_language ?? '') }}">
              </div>
              <div class="field-help" id="help-inscription_language" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Language of the inscription.</p>
                </div>
              </div>
            </div>

            {{-- inscription_translation: non-CCO field --}}
            <div class="cco-field level-optional" data-field="inscription_translation">
              <div class="field-header">
                <label for="inscription_translation">Inscription translation</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="inscription_translation" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="inscription_translation" name="inscription_translation" rows="3">{{ old('inscription_translation', $museum->inscription_translation ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-inscription_translation" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Translation of the inscription if in a non-English language.</p>
                </div>
              </div>
            </div>

            {{-- signature: optional, CCO 9.2, no vocab --}}
            <div class="cco-field level-optional" data-field="signature">
              <div class="field-header">
                <label for="signature">Signature</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">9.2</span>
                </span>
                <button type="button" class="btn-help" data-field="signature" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="signature" name="signature" rows="2">{{ old('signature', $museum->signature ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-signature" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Description of the artist's signature on the work. (CCO 9.2)</p>
                </div>
              </div>
            </div>

            {{-- marks: optional, CCO 9.3, no vocab --}}
            <div class="cco-field level-optional" data-field="marks">
              <div class="field-header">
                <label for="marks">Marks/Labels</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">9.3</span>
                </span>
                <button type="button" class="btn-help" data-field="marks" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="marks" name="marks" rows="2">{{ old('marks', $museum->marks ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-marks" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Collector's marks, labels, stamps, or other identifying marks. (CCO 9.3)</p>
                </div>
              </div>
            </div>

            {{-- mark_type: non-CCO field --}}
            <div class="cco-field level-optional" data-field="mark_type">
              <div class="field-header">
                <label for="mark_type">Mark type</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="mark_type" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="mark_type" name="mark_type"
                       value="{{ old('mark_type', $museum->mark_type ?? '') }}">
              </div>
              <div class="field-help" id="help-mark_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Type of mark (e.g. watermark, hallmark, collector's mark, brand).</p>
                </div>
              </div>
            </div>

            {{-- mark_description: non-CCO field --}}
            <div class="cco-field level-optional" data-field="mark_description">
              <div class="field-header">
                <label for="mark_description">Mark description</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="mark_description" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="mark_description" name="mark_description" rows="3">{{ old('mark_description', $museum->mark_description ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-mark_description" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Description of the mark.</p>
                </div>
              </div>
            </div>

            {{-- mark_location: non-CCO field --}}
            <div class="cco-field level-optional" data-field="mark_location">
              <div class="field-header">
                <label for="mark_location">Mark location</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="mark_location" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="mark_location" name="mark_location"
                       value="{{ old('mark_location', $museum->mark_location ?? '') }}">
              </div>
              <div class="field-help" id="help-mark_location" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Location of the mark on the object.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Condition (CCO Ch 12) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCondition">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCondition" aria-expanded="false" aria-controls="collapseCondition">
            Condition
            <span class="cco-chapter">CCO Chapter 12</span>
          </button>
        </h2>
        <div id="collapseCondition" class="accordion-collapse collapse" aria-labelledby="headingCondition" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            {{-- condition_summary: optional, CCO 12.1, no vocab --}}
            <div class="cco-field level-optional" data-field="condition_summary">
              <div class="field-header">
                <label for="condition_summary">Condition summary</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">12.1</span>
                </span>
                <button type="button" class="btn-help" data-field="condition_summary" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="condition_summary" name="condition_summary" rows="3">{{ old('condition_summary', $museum->condition_summary ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-condition_summary" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Brief summary of the current condition. (CCO 12.1)</p>
                </div>
              </div>
            </div>

            {{-- condition_term: non-CCO field --}}
            <div class="cco-field level-optional" data-field="condition_term">
              <div class="field-header">
                <label for="condition_term">Condition term</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="condition_term" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="condition_term" name="condition_term"
                       value="{{ old('condition_term', $museum->condition_term ?? '') }}">
              </div>
              <div class="field-help" id="help-condition_term" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Controlled term for the condition (e.g. excellent, good, fair, poor).</p>
                </div>
              </div>
            </div>

            {{-- condition_date: non-CCO field --}}
            <div class="cco-field level-optional" data-field="condition_date">
              <div class="field-header">
                <label for="condition_date">Condition date</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="condition_date" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="date" class="form-control" id="condition_date" name="condition_date"
                       value="{{ old('condition_date', $museum->condition_date ?? '') }}">
              </div>
              <div class="field-help" id="help-condition_date" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Date of the condition assessment.</p>
                </div>
              </div>
            </div>

            {{-- condition_description: non-CCO field --}}
            <div class="cco-field level-optional" data-field="condition_description">
              <div class="field-header">
                <label for="condition_description">Condition description</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="condition_description" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="condition_description" name="condition_description" rows="3">{{ old('condition_description', $museum->condition_description ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-condition_description" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Detailed description of the current condition.</p>
                </div>
              </div>
            </div>

            {{-- condition_notes: optional, CCO 12.2, no vocab --}}
            <div class="cco-field level-optional" data-field="condition_notes">
              <div class="field-header">
                <label for="condition_notes">Condition notes</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">12.2</span>
                </span>
                <button type="button" class="btn-help" data-field="condition_notes" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="condition_notes" name="condition_notes" rows="3">{{ old('condition_notes', $museum->condition_notes ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-condition_notes" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Additional notes on the condition. (CCO 12.2)</p>
                </div>
              </div>
            </div>

            {{-- condition_agent: non-CCO field --}}
            <div class="cco-field level-optional" data-field="condition_agent">
              <div class="field-header">
                <label for="condition_agent">Condition agent</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="condition_agent" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="condition_agent" name="condition_agent"
                       value="{{ old('condition_agent', $museum->condition_agent ?? '') }}">
              </div>
              <div class="field-help" id="help-condition_agent" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Person who assessed the condition.</p>
                </div>
              </div>
            </div>

            {{-- treatment_type: non-CCO field --}}
            <div class="cco-field level-optional" data-field="treatment_type">
              <div class="field-header">
                <label for="treatment_type">Treatment type</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="treatment_type" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="treatment_type" name="treatment_type"
                       value="{{ old('treatment_type', $museum->treatment_type ?? '') }}">
              </div>
              <div class="field-help" id="help-treatment_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Type of conservation treatment performed.</p>
                </div>
              </div>
            </div>

            {{-- treatment_date: non-CCO field --}}
            <div class="cco-field level-optional" data-field="treatment_date">
              <div class="field-header">
                <label for="treatment_date">Treatment date</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="treatment_date" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="date" class="form-control" id="treatment_date" name="treatment_date"
                       value="{{ old('treatment_date', $museum->treatment_date ?? '') }}">
              </div>
              <div class="field-help" id="help-treatment_date" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Date the treatment was performed.</p>
                </div>
              </div>
            </div>

            {{-- treatment_agent: non-CCO field --}}
            <div class="cco-field level-optional" data-field="treatment_agent">
              <div class="field-header">
                <label for="treatment_agent">Treatment agent</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="treatment_agent" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="treatment_agent" name="treatment_agent"
                       value="{{ old('treatment_agent', $museum->treatment_agent ?? '') }}">
              </div>
              <div class="field-help" id="help-treatment_agent" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Person or organization that performed the treatment.</p>
                </div>
              </div>
            </div>

            {{-- treatment_description: non-CCO field --}}
            <div class="cco-field level-optional" data-field="treatment_description">
              <div class="field-header">
                <label for="treatment_description">Treatment description</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="treatment_description" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="treatment_description" name="treatment_description" rows="3">{{ old('treatment_description', $museum->treatment_description ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-treatment_description" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Detailed description of conservation treatment performed.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Current Location (CCO Ch 13) + Description (CCO Ch 11) + Provenance ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingProvenance">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProvenance" aria-expanded="false" aria-controls="collapseProvenance">
            Current Location
            <span class="cco-chapter">CCO Chapter 13</span>
          </button>
        </h2>
        <div id="collapseProvenance" class="accordion-collapse collapse" aria-labelledby="headingProvenance" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            {{-- repository: required, CCO 13.1, no vocab --}}
            <div class="cco-field level-required" data-field="repository">
              <div class="field-header">
                <label for="repository">
                  Repository
                  <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">Required</span>
                  <span class="badge badge-cco" title="CCO Reference">13.1</span>
                </span>
                <button type="button" class="btn-help" data-field="repository" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="repository" name="repository"
                       value="{{ old('repository', $museum->repository ?? '') }}" placeholder="Type to search repositories..." autocomplete="off">
              </div>
              <div class="field-help" id="help-repository" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The repository or institution that holds this object. (CCO 13.1)</p>
                </div>
              </div>
            </div>

            {{-- location_within_repository: optional, CCO 13.2, no vocab --}}
            <div class="cco-field level-optional" data-field="location_within_repository">
              <div class="field-header">
                <label for="location_within_repository">Location</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">13.2</span>
                </span>
                <button type="button" class="btn-help" data-field="location_within_repository" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="location_within_repository" name="location_within_repository"
                       value="{{ old('location_within_repository', $museum->location_within_repository ?? '') }}">
              </div>
              <div class="field-help" id="help-location_within_repository" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Specific location within the repository (gallery, shelf, case). (CCO 13.2)</p>
                </div>
              </div>
            </div>

            {{-- credit_line: optional, CCO 13.3, no vocab --}}
            <div class="cco-field level-optional" data-field="credit_line">
              <div class="field-header">
                <label for="credit_line">Credit line</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">13.3</span>
                </span>
                <button type="button" class="btn-help" data-field="credit_line" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="credit_line" name="credit_line"
                       value="{{ old('credit_line', $museum->credit_line ?? '') }}">
              </div>
              <div class="field-help" id="help-credit_line" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The credit line for display, acknowledging gift/purchase/bequest. (CCO 13.3)</p>
                </div>
              </div>
            </div>

            {{-- description: optional, CCO 11.1, no vocab --}}
            <div class="cco-field level-optional" data-field="description">
              <div class="field-header">
                <label for="description">Description</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">11.1</span>
                </span>
                <button type="button" class="btn-help" data-field="description" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $museum->description ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-description" style="display: none;">
                <div class="help-content">
                  <p class="help-text">A free-text description of the work that supplements other fields. (CCO 11.1)</p>
                </div>
              </div>
            </div>

            {{-- physical_description: optional, CCO 11.2, no vocab --}}
            <div class="cco-field level-optional" data-field="physical_description">
              <div class="field-header">
                <label for="physical_description">Physical description</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">11.2</span>
                </span>
                <button type="button" class="btn-help" data-field="physical_description" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="physical_description" name="physical_description" rows="3">{{ old('physical_description', $museum->physical_description ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-physical_description" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Description of the physical characteristics not covered elsewhere. (CCO 11.2)</p>
                </div>
              </div>
            </div>

            {{-- provenance: non-CCO field --}}
            <div class="cco-field level-optional" data-field="provenance">
              <div class="field-header">
                <label for="provenance">Provenance</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="provenance" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="provenance" name="provenance" rows="3">{{ old('provenance', $museum->provenance ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-provenance" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Ownership history of the object.</p>
                </div>
              </div>
            </div>

            {{-- provenance_text: non-CCO field --}}
            <div class="cco-field level-optional" data-field="provenance_text">
              <div class="field-header">
                <label for="provenance_text">Provenance text</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="provenance_text" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="provenance_text" name="provenance_text" rows="3">{{ old('provenance_text', $museum->provenance_text ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-provenance_text" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Full provenance narrative.</p>
                </div>
              </div>
            </div>

            {{-- ownership_history: non-CCO field --}}
            <div class="cco-field level-optional" data-field="ownership_history">
              <div class="field-header">
                <label for="ownership_history">Ownership history</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="ownership_history" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="ownership_history" name="ownership_history" rows="3">{{ old('ownership_history', $museum->ownership_history ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-ownership_history" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Detailed ownership history including dates and methods of transfer.</p>
                </div>
              </div>
            </div>

            {{-- current_location: non-CCO field --}}
            <div class="cco-field level-optional" data-field="current_location">
              <div class="field-header">
                <label for="current_location">Current location</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="current_location" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="current_location" name="current_location" rows="2">{{ old('current_location', $museum->current_location ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-current_location" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Where the object is currently stored or displayed.</p>
                </div>
              </div>
            </div>

            {{-- current_location_repository: non-CCO field --}}
            <div class="cco-field level-optional" data-field="current_location_repository">
              <div class="field-header">
                <label for="current_location_repository">Current location repository</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="current_location_repository" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="current_location_repository" name="current_location_repository"
                       value="{{ old('current_location_repository', $museum->current_location_repository ?? '') }}">
              </div>
              <div class="field-help" id="help-current_location_repository" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The repository or institution where the object is currently held.</p>
                </div>
              </div>
            </div>

            {{-- current_location_geography: non-CCO field --}}
            <div class="cco-field level-optional" data-field="current_location_geography">
              <div class="field-header">
                <label for="current_location_geography">Current location geography</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="current_location_geography" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="current_location_geography" name="current_location_geography"
                       value="{{ old('current_location_geography', $museum->current_location_geography ?? '') }}">
              </div>
              <div class="field-help" id="help-current_location_geography" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Geographic location of the repository.</p>
                </div>
              </div>
            </div>

            {{-- current_location_coordinates: non-CCO field --}}
            <div class="cco-field level-optional" data-field="current_location_coordinates">
              <div class="field-header">
                <label for="current_location_coordinates">Current location coordinates</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="current_location_coordinates" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="current_location_coordinates" name="current_location_coordinates"
                       value="{{ old('current_location_coordinates', $museum->current_location_coordinates ?? '') }}">
              </div>
              <div class="field-help" id="help-current_location_coordinates" style="display: none;">
                <div class="help-content">
                  <p class="help-text">GPS coordinates (latitude, longitude).</p>
                </div>
              </div>
            </div>

            {{-- current_location_ref_number: non-CCO field --}}
            <div class="cco-field level-optional" data-field="current_location_ref_number">
              <div class="field-header">
                <label for="current_location_ref_number">Current location reference number</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="current_location_ref_number" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="current_location_ref_number" name="current_location_ref_number"
                       value="{{ old('current_location_ref_number', $museum->current_location_ref_number ?? '') }}">
              </div>
              <div class="field-help" id="help-current_location_ref_number" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Shelf, gallery, or storage reference number at the current location.</p>
                </div>
              </div>
            </div>

            {{-- legal_status: non-CCO field --}}
            <div class="cco-field level-optional" data-field="legal_status">
              <div class="field-header">
                <label for="legal_status">Legal status</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="legal_status" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="legal_status" name="legal_status"
                       value="{{ old('legal_status', $museum->legal_status ?? '') }}">
              </div>
              <div class="field-help" id="help-legal_status" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Legal status of the object (e.g. gift, purchase, loan, public domain).</p>
                </div>
              </div>
            </div>

            {{-- rights_type: non-CCO field --}}
            <div class="cco-field level-optional" data-field="rights_type">
              <div class="field-header">
                <label for="rights_type">Rights type</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="rights_type" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="rights_type" name="rights_type"
                       value="{{ old('rights_type', $museum->rights_type ?? '') }}">
              </div>
              <div class="field-help" id="help-rights_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Type of rights (e.g. copyright, trademark, public domain).</p>
                </div>
              </div>
            </div>

            {{-- rights_holder: non-CCO field --}}
            <div class="cco-field level-optional" data-field="rights_holder">
              <div class="field-header">
                <label for="rights_holder">Rights holder</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="rights_holder" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="rights_holder" name="rights_holder"
                       value="{{ old('rights_holder', $museum->rights_holder ?? '') }}">
              </div>
              <div class="field-help" id="help-rights_holder" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Person or institution holding the rights.</p>
                </div>
              </div>
            </div>

            {{-- rights_date: non-CCO field --}}
            <div class="cco-field level-optional" data-field="rights_date">
              <div class="field-header">
                <label for="rights_date">Rights date</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="rights_date" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="rights_date" name="rights_date"
                       value="{{ old('rights_date', $museum->rights_date ?? '') }}">
              </div>
              <div class="field-help" id="help-rights_date" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Date or range applicable to the rights statement.</p>
                </div>
              </div>
            </div>

            {{-- rights_remarks: non-CCO field --}}
            <div class="cco-field level-optional" data-field="rights_remarks">
              <div class="field-header">
                <label for="rights_remarks">Rights remarks</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="rights_remarks" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="rights_remarks" name="rights_remarks" rows="2">{{ old('rights_remarks', $museum->rights_remarks ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-rights_remarks" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Additional notes on rights and reproduction.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Related Works (CCO Ch 14) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingRelated">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRelated" aria-expanded="false" aria-controls="collapseRelated">
            Related Works
            <span class="cco-chapter">CCO Chapter 14</span>
          </button>
        </h2>
        <div id="collapseRelated" class="accordion-collapse collapse" aria-labelledby="headingRelated" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            {{-- related_works: optional, CCO 14.1, no vocab --}}
            <div class="cco-field level-optional" data-field="related_works">
              <div class="field-header">
                <label for="related_works">Related works</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">14.1</span>
                </span>
                <button type="button" class="btn-help" data-field="related_works" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="related_works" name="related_works" rows="3">{{ old('related_works', $museum->related_works ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-related_works" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Identify related works and describe the nature of the relationship. (CCO 14.1)</p>
                </div>
              </div>
            </div>

            {{-- relationship_type: optional, CCO 14.2, no vocab --}}
            <div class="cco-field level-optional" data-field="relationship_type">
              <div class="field-header">
                <label for="relationship_type">Relationship type</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">14.2</span>
                </span>
                <button type="button" class="btn-help" data-field="relationship_type" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="relationship_type" name="relationship_type"
                       value="{{ old('relationship_type', $museum->relationship_type ?? '') }}">
              </div>
              <div class="field-help" id="help-relationship_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The type of relationship between this work and the related work. (CCO 14.2)</p>
                </div>
              </div>
            </div>

            {{-- related_work_type: non-CCO field --}}
            <div class="cco-field level-optional" data-field="related_work_type">
              <div class="field-header">
                <label for="related_work_type">Related work type</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="related_work_type" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="related_work_type" name="related_work_type"
                       value="{{ old('related_work_type', $museum->related_work_type ?? '') }}">
              </div>
              <div class="field-help" id="help-related_work_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Type of the related work (e.g. part, copy, study, pendant).</p>
                </div>
              </div>
            </div>

            {{-- related_work_relationship: non-CCO field --}}
            <div class="cco-field level-optional" data-field="related_work_relationship">
              <div class="field-header">
                <label for="related_work_relationship">Related work relationship</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="related_work_relationship" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="related_work_relationship" name="related_work_relationship"
                       value="{{ old('related_work_relationship', $museum->related_work_relationship ?? '') }}">
              </div>
              <div class="field-help" id="help-related_work_relationship" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Nature of the relationship (e.g. is part of, is copy of, is study for).</p>
                </div>
              </div>
            </div>

            {{-- related_work_label: non-CCO field --}}
            <div class="cco-field level-optional" data-field="related_work_label">
              <div class="field-header">
                <label for="related_work_label">Related work label</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="related_work_label" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="related_work_label" name="related_work_label"
                       value="{{ old('related_work_label', $museum->related_work_label ?? '') }}">
              </div>
              <div class="field-help" id="help-related_work_label" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Title or label of the related work.</p>
                </div>
              </div>
            </div>

            {{-- related_work_id: non-CCO field --}}
            <div class="cco-field level-optional" data-field="related_work_id">
              <div class="field-header">
                <label for="related_work_id">Related work identifier</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="related_work_id" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="related_work_id" name="related_work_id"
                       value="{{ old('related_work_id', $museum->related_work_id ?? '') }}">
              </div>
              <div class="field-help" id="help-related_work_id" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Accession number or identifier of the related work.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Rights (CCO Ch 15) + Cataloging ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCataloging">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCataloging" aria-expanded="false" aria-controls="collapseCataloging">
            Rights
            <span class="cco-chapter">CCO Chapter 15</span>
          </button>
        </h2>
        <div id="collapseCataloging" class="accordion-collapse collapse" aria-labelledby="headingCataloging" data-bs-parent="#museumAccordion">
          <div class="accordion-body">

            {{-- rights_statement: optional, CCO 15.1, no vocab --}}
            <div class="cco-field level-optional" data-field="rights_statement">
              <div class="field-header">
                <label for="rights_statement">Rights statement</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">15.1</span>
                </span>
                <button type="button" class="btn-help" data-field="rights_statement" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="rights_statement" name="rights_statement" rows="3">{{ old('rights_statement', $museum->rights_statement ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-rights_statement" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Statement of rights associated with the work. (CCO 15.1)</p>
                </div>
              </div>
            </div>

            {{-- copyright_holder: optional, CCO 15.2, no vocab --}}
            <div class="cco-field level-optional" data-field="copyright_holder">
              <div class="field-header">
                <label for="copyright_holder">Copyright holder</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">15.2</span>
                </span>
                <button type="button" class="btn-help" data-field="copyright_holder" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="copyright_holder" name="copyright_holder"
                       value="{{ old('copyright_holder', $museum->copyright_holder ?? '') }}">
              </div>
              <div class="field-help" id="help-copyright_holder" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Name of the copyright holder. (CCO 15.2)</p>
                </div>
              </div>
            </div>

            {{-- reproduction_conditions: optional, CCO 15.3, no vocab --}}
            <div class="cco-field level-optional" data-field="reproduction_conditions">
              <div class="field-header">
                <label for="reproduction_conditions">Reproduction conditions</label>
                <span class="field-badges">
                  <span class="badge badge-cco" title="CCO Reference">15.3</span>
                </span>
                <button type="button" class="btn-help" data-field="reproduction_conditions" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="reproduction_conditions" name="reproduction_conditions" rows="2">{{ old('reproduction_conditions', $museum->reproduction_conditions ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-reproduction_conditions" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Conditions governing reproduction of the work. (CCO 15.3)</p>
                </div>
              </div>
            </div>

            {{-- cataloger_name: non-CCO field --}}
            <div class="cco-field level-optional" data-field="cataloger_name">
              <div class="field-header">
                <label for="cataloger_name">Cataloger name</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="cataloger_name" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="cataloger_name" name="cataloger_name"
                       value="{{ old('cataloger_name', $museum->cataloger_name ?? '') }}">
              </div>
              <div class="field-help" id="help-cataloger_name" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Name of the person who created or last updated this catalogue record.</p>
                </div>
              </div>
            </div>

            {{-- cataloging_date: non-CCO field --}}
            <div class="cco-field level-optional" data-field="cataloging_date">
              <div class="field-header">
                <label for="cataloging_date">Cataloging date</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="cataloging_date" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="date" class="form-control" id="cataloging_date" name="cataloging_date"
                       value="{{ old('cataloging_date', $museum->cataloging_date ?? '') }}">
              </div>
              <div class="field-help" id="help-cataloging_date" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Date this record was catalogued or last modified.</p>
                </div>
              </div>
            </div>

            {{-- cataloging_institution: non-CCO field --}}
            <div class="cco-field level-optional" data-field="cataloging_institution">
              <div class="field-header">
                <label for="cataloging_institution">Cataloging institution</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="cataloging_institution" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="cataloging_institution" name="cataloging_institution"
                       value="{{ old('cataloging_institution', $museum->cataloging_institution ?? '') }}">
              </div>
              <div class="field-help" id="help-cataloging_institution" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Institution responsible for creating this catalogue record.</p>
                </div>
              </div>
            </div>

            {{-- cataloging_remarks: non-CCO field --}}
            <div class="cco-field level-optional" data-field="cataloging_remarks">
              <div class="field-header">
                <label for="cataloging_remarks">Cataloging remarks</label>
                <span class="field-badges"></span>
                <button type="button" class="btn-help" data-field="cataloging_remarks" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="cataloging_remarks" name="cataloging_remarks" rows="3">{{ old('cataloging_remarks', $museum->cataloging_remarks ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-cataloging_remarks" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Notes about the cataloging process or data quality.</p>
                </div>
              </div>
            </div>

            {{-- repository_id: non-CCO field --}}
            <div class="cco-field level-optional" data-field="repository_id">
              <div class="field-header">
                <label for="repository_id">Repository</label>
                <span class="field-badges"></span>
              </div>
              <div class="field-input">
                <select class="form-select" id="repository_id" name="repository_id">
                  <option value="">-- Select --</option>
                  @foreach($repositories as $repo)
                    <option value="{{ $repo->id }}" @selected(old('repository_id', $museum->repository_id ?? '') == $repo->id)>
                      {{ $repo->name }}
                    </option>
                  @endforeach
                </select>
              </div>
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

    // Help button toggle
    document.querySelectorAll('.btn-help').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        var fieldName = this.getAttribute('data-field');
        var helpDiv = document.getElementById('help-' + fieldName);
        if (helpDiv) {
          if (helpDiv.style.display === 'none' || helpDiv.style.display === '') {
            helpDiv.style.display = 'block';
            this.classList.add('active');
          } else {
            helpDiv.style.display = 'none';
            this.classList.remove('active');
          }
        }
      });
    });
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

/* CCO Field Wrapper */
.cco-field {
  margin-bottom: 16px;
  padding: 12px 15px;
  border-left: 3px solid #dee2e6;
  background: #fafbfc;
  border-radius: 0 4px 4px 0;
}
.cco-field.level-required {
  border-left-color: #e74c3c;
  background: #fef9f9;
}
.cco-field.level-recommended {
  border-left-color: #f39c12;
  background: #fefcf5;
}
.cco-field.level-optional {
  border-left-color: #dee2e6;
  background: #fafbfc;
}

/* Field Header */
.cco-field .field-header {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}
.cco-field .field-header label {
  font-weight: 600;
  font-size: 14px;
  color: #333;
  margin-bottom: 0;
}
.cco-field .field-header label .required {
  color: #e74c3c;
  font-weight: 700;
  margin-left: 2px;
}

/* Field Badges */
.field-badges {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-left: auto;
}
.badge-cco {
  background: #3498db;
  color: #fff;
  padding: 2px 8px;
  border-radius: 3px;
  font-size: 10px;
  font-weight: 600;
}
.badge-vocab {
  background: #8e44ad;
  color: #fff;
  padding: 2px 8px;
  border-radius: 3px;
  font-size: 10px;
  font-weight: 600;
}
.badge-vocab i {
  margin-right: 3px;
}

/* Help Button */
.btn-help {
  background: none;
  border: 1px solid #ccc;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #999;
  padding: 0;
  font-size: 13px;
  transition: all 0.2s;
  flex-shrink: 0;
}
.btn-help:hover,
.btn-help.active {
  color: #3498db;
  border-color: #3498db;
  background: #ebf5fb;
}

/* Field Input */
.cco-field .field-input {
  margin-bottom: 0;
}

/* Field Help */
.cco-field .field-help {
  margin-top: 8px;
  padding: 10px 12px;
  background: #ebf5fb;
  border-radius: 4px;
  border: 1px solid #bee5eb;
}
.cco-field .field-help .help-content .help-text {
  font-size: 12px;
  color: #555;
  margin: 0;
}
</style>
@endpush
@endsection
