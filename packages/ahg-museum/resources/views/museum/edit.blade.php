@extends('theme::layouts.2col')

@section('title', 'CCO Cataloguing')
@section('body-class', ($isNew ? 'create' : 'edit') . ' museum')

@section('sidebar')
<div class="sidebar-content">

  <!-- Template Selector -->
  <section id="template-selector" class="sidebar-section">
    <h4>Object Template</h4>
    <div class="template-list">
      <a href="javascript:void(0)" data-template="general" class="template-option active">
        <i class="fas fa-cube"></i> <span>General</span>
      </a>
      <a href="javascript:void(0)" data-template="painting" class="template-option">
        <i class="fas fa-palette"></i> <span>Painting</span>
      </a>
      <a href="javascript:void(0)" data-template="sculpture" class="template-option">
        <i class="fas fa-monument"></i> <span>Sculpture</span>
      </a>
      <a href="javascript:void(0)" data-template="photograph" class="template-option">
        <i class="fas fa-camera"></i> <span>Photograph</span>
      </a>
      <a href="javascript:void(0)" data-template="textile" class="template-option">
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

      {{-- ===== 1. Object/Work (CCO Ch 2) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingIdentification">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIdentification" aria-expanded="true" aria-controls="collapseIdentification">
            Object/Work
            <span class="cco-chapter">CCO Chapter 2</span>
          </button>
        </h2>
        <div id="collapseIdentification" class="accordion-collapse collapse show" aria-labelledby="headingIdentification" data-bs-parent="#museumAccordion">
          <div class="accordion-body">
            <p class="category-description">Information that identifies the work, including type, components, and count.</p>

            {{-- work_type: required, CCO 2.1, vocab AAT_OBJECT_TYPES --}}
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
                <input type="text" class="form-control" id="work_type" name="work_type" value="{{ old('work_type', $museum->work_type ?? '') }}" placeholder="Type to search..." autocomplete="off">
              </div>
              <div class="field-help" id="help-work_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The specific kind of object or work being described. Use controlled vocabulary (AAT preferred).</p>
                </div>
              </div>
            </div>

            {{-- object_number: required, CCO 2.3 --}}
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
                <div class="input-group">
                  <input type="text" class="form-control" id="object_number" name="object_number"
                         value="{{ old('object_number', $museum->object_number ?? '') }}">
                  <button class="btn atom-btn-white" type="button" id="btn-generate-identifier">
                    <i class="fas fa-sync-alt"></i> Generate identifier
                  </button>
                </div>
              </div>
              <div class="field-help" id="help-object_number" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Unique identifier assigned by the repository.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 2. Titles/Names (CCO Ch 3) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingTitle">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTitle" aria-expanded="false" aria-controls="collapseTitle">
            Titles/Names
            <span class="cco-chapter">CCO Chapter 3</span>
          </button>
        </h2>
        <div id="collapseTitle" class="accordion-collapse collapse" aria-labelledby="headingTitle" data-bs-parent="#museumAccordion">
          <div class="accordion-body">
            <p class="category-description">Titles, names, or other identifying phrases for the work.</p>

            {{-- title: required, CCO 3.1 --}}
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
                  <p class="help-text">The primary title of the work.</p>
                </div>
              </div>
            </div>

            {{-- title_type: required, CCO 3.1.1, vocab CCO_TITLE_TYPES --}}
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
                  <p class="help-text">The source or nature of the title.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 3. Creation (CCO Ch 4) — merged Creator + Creation ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCreation">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCreation" aria-expanded="false" aria-controls="collapseCreation">
            Creation
            <span class="cco-chapter">CCO Chapter 4</span>
          </button>
        </h2>
        <div id="collapseCreation" class="accordion-collapse collapse" aria-labelledby="headingCreation" data-bs-parent="#museumAccordion">
          <div class="accordion-body">
            <p class="category-description">Information about who created the work, when, and where.</p>

            {{-- creator_display: required, CCO 4.1 --}}
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
                  <p class="help-text">Creator name as it should appear in displays.</p>
                </div>
              </div>
            </div>

            {{-- creator: required, CCO 4.1, vocab ULAN --}}
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
                <select class="form-select" id="creator" name="creator">
                  <option value="">-- Select --</option>
                  @foreach($creators ?? [] as $c)
                    <option value="{{ $c->id ?? $c }}" @selected(old('creator', $museum->creator ?? '') == ($c->id ?? $c))>{{ $c->name ?? $c }}</option>
                  @endforeach
                </select>
              </div>
              <div class="field-help" id="help-creator" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Link to authority record. Search ULAN or local authority file.</p>
                </div>
              </div>
            </div>

            {{-- creator_role: required, CCO 4.1.1, vocab AAT_CREATOR_ROLES --}}
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
                  <p class="help-text">The role of the creator.</p>
                </div>
              </div>
            </div>

            {{-- attribution_qualifier: optional, CCO 4.1.2, vocab CCO_ATTRIBUTION --}}
            <div class="cco-field level-recommended" data-field="attribution_qualifier">
              <div class="field-header">
                <label for="attribution_qualifier">Attribution qualifier</label>
                <span class="field-badges">
                  <span class="badge badge-recommended">Recommended</span>
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
                  <p class="help-text">Qualifies degree of certainty about attribution.</p>
                </div>
              </div>
            </div>

            {{-- creation_date_display: required, CCO 4.2 --}}
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
                  <p class="help-text">Date as it should appear in displays.</p>
                </div>
              </div>
            </div>

            {{-- creation_date_earliest + creation_date_latest: optional, CCO 4.2.1 / 4.2.2 --}}
            <div class="row">
              <div class="col-md-6">
                <div class="cco-field level-recommended" data-field="creation_date_earliest">
                  <div class="field-header">
                    <label for="creation_date_earliest">Creation date (earliest)</label>
                    <span class="field-badges">
                      <span class="badge badge-recommended">Recommended</span>
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
                      <p class="help-text">Earliest possible creation date.</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="cco-field level-recommended" data-field="creation_date_latest">
                  <div class="field-header">
                    <label for="creation_date_latest">Creation date (latest)</label>
                    <span class="field-badges">
                      <span class="badge badge-recommended">Recommended</span>
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
                      <p class="help-text">Latest possible creation date.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {{-- creation_place: optional, CCO 4.3, vocab TGN --}}
            <div class="cco-field level-recommended" data-field="creation_place">
              <div class="field-header">
                <label for="creation_place">Place of creation</label>
                <span class="field-badges">
                  <span class="badge badge-recommended">Recommended</span>
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
                  <p class="help-text">Geographic location where the object was created.</p>
                </div>
              </div>
            </div>

            {{-- culture: optional, CCO 4.4, vocab AAT_CULTURES --}}
            <div class="cco-field level-optional" data-field="culture">
              <div class="field-header">
                <label for="culture">Culture/People</label>
                <span class="field-badges">
                  <span class="badge badge-optional">Optional</span>
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
                  <p class="help-text">Cultural, ethnic, or national group.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 4. Styles/Periods (CCO Ch 5) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingStyles">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStyles" aria-expanded="false" aria-controls="collapseStyles">
            Styles/Periods
            <span class="cco-chapter">CCO Chapter 5</span>
          </button>
        </h2>
        <div id="collapseStyles" class="accordion-collapse collapse" aria-labelledby="headingStyles" data-bs-parent="#museumAccordion">
          <div class="accordion-body">
            <p class="category-description">Style, period, group, school, or movement.</p>

            {{-- style: optional, CCO 5.1, vocab AAT_STYLES --}}
            <div class="cco-field level-recommended" data-field="style">
              <div class="field-header">
                <label for="style">Style</label>
                <span class="field-badges">
                  <span class="badge badge-recommended">Recommended</span>
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
                  <p class="help-text">Artistic style or movement.</p>
                </div>
              </div>
            </div>

            {{-- period: optional, CCO 5.2, vocab AAT_PERIODS --}}
            <div class="cco-field level-optional" data-field="period">
              <div class="field-header">
                <label for="period">Period</label>
                <span class="field-badges">
                  <span class="badge badge-optional">Optional</span>
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
                  <p class="help-text">Historical or stylistic period.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 5. Measurements (CCO Ch 6) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingMeasurements">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMeasurements" aria-expanded="false" aria-controls="collapseMeasurements">
            Measurements
            <span class="cco-chapter">CCO Chapter 6</span>
          </button>
        </h2>
        <div id="collapseMeasurements" class="accordion-collapse collapse" aria-labelledby="headingMeasurements" data-bs-parent="#museumAccordion">
          <div class="accordion-body">
            <p class="category-description">Physical dimensions and other measurements.</p>

            {{-- dimensions_display: required, CCO 6.1 --}}
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
                  <p class="help-text">Dimensions as displayed. Format: H &times; W &times; D (unit).</p>
                </div>
              </div>
            </div>

            {{-- height_value, width_value, depth_value: optional, CCO 6.2 --}}
            <div class="row">
              <div class="col-md-4">
                <div class="cco-field level-recommended" data-field="height_value">
                  <div class="field-header">
                    <label for="height_value">Height</label>
                    <span class="field-badges">
                      <span class="badge badge-recommended">Recommended</span>
                      <span class="badge badge-cco" title="CCO Reference">6.2</span>
                    </span>
                    <button type="button" class="btn-help" data-field="height_value" title="Help">
                      <i class="fa fa-question-circle"></i>
                    </button>
                  </div>
                  <div class="field-input">
                    <input type="number" step="0.01" class="form-control" id="height_value" name="height_value"
                           value="{{ old('height_value', $museum->height_value ?? '') }}">
                  </div>
                  <div class="field-help" id="help-height_value" style="display: none;">
                    <div class="help-content">
                      <p class="help-text">Height in centimeters.</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="cco-field level-recommended" data-field="width_value">
                  <div class="field-header">
                    <label for="width_value">Width</label>
                    <span class="field-badges">
                      <span class="badge badge-recommended">Recommended</span>
                      <span class="badge badge-cco" title="CCO Reference">6.2</span>
                    </span>
                    <button type="button" class="btn-help" data-field="width_value" title="Help">
                      <i class="fa fa-question-circle"></i>
                    </button>
                  </div>
                  <div class="field-input">
                    <input type="number" step="0.01" class="form-control" id="width_value" name="width_value"
                           value="{{ old('width_value', $museum->width_value ?? '') }}">
                  </div>
                  <div class="field-help" id="help-width_value" style="display: none;">
                    <div class="help-content">
                      <p class="help-text">Width in centimeters.</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="cco-field level-optional" data-field="depth_value">
                  <div class="field-header">
                    <label for="depth_value">Depth</label>
                    <span class="field-badges">
                      <span class="badge badge-optional">Optional</span>
                      <span class="badge badge-cco" title="CCO Reference">6.2</span>
                    </span>
                    <button type="button" class="btn-help" data-field="depth_value" title="Help">
                      <i class="fa fa-question-circle"></i>
                    </button>
                  </div>
                  <div class="field-input">
                    <input type="number" step="0.01" class="form-control" id="depth_value" name="depth_value"
                           value="{{ old('depth_value', $museum->depth_value ?? '') }}">
                  </div>
                  <div class="field-help" id="help-depth_value" style="display: none;">
                    <div class="help-content">
                      <p class="help-text">Depth in centimeters.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 6. Materials/Techniques (CCO Ch 7) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingMaterials">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMaterials" aria-expanded="false" aria-controls="collapseMaterials">
            Materials/Techniques
            <span class="cco-chapter">CCO Chapter 7</span>
          </button>
        </h2>
        <div id="collapseMaterials" class="accordion-collapse collapse" aria-labelledby="headingMaterials" data-bs-parent="#museumAccordion">
          <div class="accordion-body">
            <p class="category-description">Physical materials and techniques used to create the work.</p>

            {{-- materials_display: required, CCO 7.1 --}}
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
                  <p class="help-text">Materials/medium as displayed.</p>
                </div>
              </div>
            </div>

            {{-- materials: optional, CCO 7.1.1, vocab AAT_MATERIALS --}}
            <div class="cco-field level-recommended" data-field="materials">
              <div class="field-header">
                <label for="materials">Materials (Indexed)</label>
                <span class="field-badges">
                  <span class="badge badge-recommended">Recommended</span>
                  <span class="badge badge-cco" title="CCO Reference">7.1.1</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> AAT_MATERIALS</span>
                </span>
                <button type="button" class="btn-help" data-field="materials" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="materials" name="materials" value="{{ old('materials', $museum->materials ?? '') }}">
              </div>
              <div class="field-help" id="help-materials" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Individual materials for indexing.</p>
                </div>
              </div>
            </div>

            {{-- techniques: optional, CCO 7.2, vocab AAT_TECHNIQUES --}}
            <div class="cco-field level-recommended" data-field="techniques">
              <div class="field-header">
                <label for="techniques">Techniques</label>
                <span class="field-badges">
                  <span class="badge badge-recommended">Recommended</span>
                  <span class="badge badge-cco" title="CCO Reference">7.2</span>
                  <span class="badge badge-vocab" title="Controlled Vocabulary"><i class="fa fa-book"></i> AAT_TECHNIQUES</span>
                </span>
                <button type="button" class="btn-help" data-field="techniques" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="techniques" name="techniques" value="{{ old('techniques', $museum->techniques ?? '') }}">
              </div>
              <div class="field-help" id="help-techniques" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Production techniques.</p>
                </div>
              </div>
            </div>

            {{-- support: optional, CCO 7.3, vocab AAT_SUPPORTS --}}
            <div class="cco-field level-required" data-field="support">
              <div class="field-header">
                <label for="support">
                  Support
                  <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">Required</span>
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
                  <p class="help-text">The base material on which the work is executed.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 7. Subject Matter (CCO Ch 8) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingSubject">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSubject" aria-expanded="false" aria-controls="collapseSubject">
            Subject Matter
            <span class="cco-chapter">CCO Chapter 8</span>
          </button>
        </h2>
        <div id="collapseSubject" class="accordion-collapse collapse" aria-labelledby="headingSubject" data-bs-parent="#museumAccordion">
          <div class="accordion-body">
            <p class="category-description">What the work represents or depicts.</p>

            {{-- subject_display: optional, CCO 8.1 --}}
            <div class="cco-field level-recommended" data-field="subject_display">
              <div class="field-header">
                <label for="subject_display">Subject (Display)</label>
                <span class="field-badges">
                  <span class="badge badge-recommended">Recommended</span>
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
                  <p class="help-text">Brief description of what is depicted or represented.</p>
                </div>
              </div>
            </div>

            {{-- subjects_depicted: optional, CCO 8.2, vocab AAT_SUBJECTS --}}
            <div class="cco-field level-recommended" data-field="subjects_depicted">
              <div class="field-header">
                <label for="subjects_depicted">Subjects depicted</label>
                <span class="field-badges">
                  <span class="badge badge-recommended">Recommended</span>
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
                  <p class="help-text">Subjects shown in the work.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 8. Inscriptions (CCO Ch 9) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingInscriptions">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseInscriptions" aria-expanded="false" aria-controls="collapseInscriptions">
            Inscriptions
            <span class="cco-chapter">CCO Chapter 9</span>
          </button>
        </h2>
        <div id="collapseInscriptions" class="accordion-collapse collapse" aria-labelledby="headingInscriptions" data-bs-parent="#museumAccordion">
          <div class="accordion-body">
            <p class="category-description">Marks, inscriptions, and signatures on the work.</p>

            {{-- inscriptions: optional, CCO 9.1 --}}
            <div class="cco-field level-optional" data-field="inscriptions">
              <div class="field-header">
                <label for="inscriptions">Inscriptions</label>
                <span class="field-badges">
                  <span class="badge badge-optional">Optional</span>
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
                  <p class="help-text">Any writing, marks, or labels on the work.</p>
                </div>
              </div>
            </div>

            {{-- signature: optional, CCO 9.2 --}}
            <div class="cco-field level-recommended" data-field="signature">
              <div class="field-header">
                <label for="signature">Signature</label>
                <span class="field-badges">
                  <span class="badge badge-recommended">Recommended</span>
                  <span class="badge badge-cco" title="CCO Reference">9.2</span>
                </span>
                <button type="button" class="btn-help" data-field="signature" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="signature" name="signature" value="{{ old('signature', $museum->signature ?? '') }}">
              </div>
              <div class="field-help" id="help-signature" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Artist signature details.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 9. Description (CCO Ch 11) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingDescription">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDescription" aria-expanded="false" aria-controls="collapseDescription">
            Description
            <span class="cco-chapter">CCO Chapter 11</span>
          </button>
        </h2>
        <div id="collapseDescription" class="accordion-collapse collapse" aria-labelledby="headingDescription" data-bs-parent="#museumAccordion">
          <div class="accordion-body">
            <p class="category-description">Descriptive text about the work.</p>

            {{-- description: optional, CCO 11.1 --}}
            <div class="cco-field level-recommended" data-field="description">
              <div class="field-header">
                <label for="description">Description</label>
                <span class="field-badges">
                  <span class="badge badge-recommended">Recommended</span>
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
                  <p class="help-text">A narrative description of the work.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 10. Condition (CCO Ch 12) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCondition">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCondition" aria-expanded="false" aria-controls="collapseCondition">
            Condition
            <span class="cco-chapter">CCO Chapter 12</span>
          </button>
        </h2>
        <div id="collapseCondition" class="accordion-collapse collapse" aria-labelledby="headingCondition" data-bs-parent="#museumAccordion">
          <div class="accordion-body">
            <p class="category-description">Current physical condition.</p>

            {{-- condition_summary: optional, CCO 12.1 --}}
            <div class="cco-field level-recommended" data-field="condition_summary">
              <div class="field-header">
                <label for="condition_summary">Condition summary</label>
                <span class="field-badges">
                  <span class="badge badge-recommended">Recommended</span>
                  <span class="badge badge-cco" title="CCO Reference">12.1</span>
                </span>
                <button type="button" class="btn-help" data-field="condition_summary" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <select class="form-select" id="condition_summary" name="condition_summary">
                  <option value="">-- Select --</option>
                  @foreach(['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor', 'needs conservation' => 'Needs conservation'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('condition_summary', $museum->condition_summary ?? '') == $val)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="field-help" id="help-condition_summary" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Brief overall condition assessment.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 11. Current Location (CCO Ch 13) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingProvenance">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProvenance" aria-expanded="false" aria-controls="collapseProvenance">
            Current Location
            <span class="cco-chapter">CCO Chapter 13</span>
          </button>
        </h2>
        <div id="collapseProvenance" class="accordion-collapse collapse" aria-labelledby="headingProvenance" data-bs-parent="#museumAccordion">
          <div class="accordion-body">
            <p class="category-description">Where the work is currently held.</p>

            {{-- repository: required, CCO 13.1 --}}
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
                <select class="form-select" id="repository" name="repository">
                  <option value="">-- Select --</option>
                  @foreach($repositories ?? [] as $repo)
                    <option value="{{ $repo->id }}" @selected(old('repository', $museum->repository_id ?? $museum->repository ?? '') == $repo->id)>{{ $repo->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="field-help" id="help-repository" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The institution that holds the work.</p>
                </div>
              </div>
            </div>

            {{-- location_within_repository: optional, CCO 13.2 --}}
            <div class="cco-field level-recommended" data-field="location_within_repository">
              <div class="field-header">
                <label for="location_within_repository">Location</label>
                <span class="field-badges">
                  <span class="badge badge-recommended">Recommended</span>
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
                  <p class="help-text">Specific location within the repository.</p>
                </div>
              </div>
            </div>

            {{-- credit_line: optional, CCO 13.3 --}}
            <div class="cco-field level-recommended" data-field="credit_line">
              <div class="field-header">
                <label for="credit_line">Credit line</label>
                <span class="field-badges">
                  <span class="badge badge-recommended">Recommended</span>
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
                  <p class="help-text">Standard credit text for publications and labels.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 12. Related Works (CCO Ch 14) — empty section in General template ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingRelated">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRelated" aria-expanded="false" aria-controls="collapseRelated">
            Related Works
            <span class="cco-chapter">CCO Chapter 14</span>
          </button>
        </h2>
        <div id="collapseRelated" class="accordion-collapse collapse" aria-labelledby="headingRelated" data-bs-parent="#museumAccordion">
          <div class="accordion-body">
            <p class="category-description">Relationships to other works.</p>
          </div>
        </div>
      </div>

      {{-- ===== 13. Rights (CCO Ch 15) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCataloging">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCataloging" aria-expanded="false" aria-controls="collapseCataloging">
            Rights
            <span class="cco-chapter">CCO Chapter 15</span>
          </button>
        </h2>
        <div id="collapseCataloging" class="accordion-collapse collapse" aria-labelledby="headingCataloging" data-bs-parent="#museumAccordion">
          <div class="accordion-body">
            <p class="category-description">Rights and reproduction information.</p>

            {{-- rights_statement: optional, CCO 15.1 --}}
            <div class="cco-field level-recommended" data-field="rights_statement">
              <div class="field-header">
                <label for="rights_statement">Rights statement</label>
                <span class="field-badges">
                  <span class="badge badge-recommended">Recommended</span>
                  <span class="badge badge-cco" title="CCO Reference">15.1</span>
                </span>
                <button type="button" class="btn-help" data-field="rights_statement" title="Help">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="rights_statement" name="rights_statement" value="{{ old('rights_statement', $museum->rights_statement ?? '') }}">
              </div>
              <div class="field-help" id="help-rights_statement" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Copyright status and usage rights.</p>
                </div>
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
                  <span class="field-badges"><span class="badge badge-optional">Optional</span></span>
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
                  <span class="field-badges"><span class="badge badge-optional">Optional</span></span>
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
                  <span class="field-badges"><span class="badge badge-optional">Optional</span></span>
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
                      Opacity: <span id="opacity-value">{{ $wmOpacity }}%</span></label>
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

    <section class="actions">
      <ul>
        @if(!$isNew)
          <li><a href="{{ route('museum.show', $museum->slug) }}" class="btn atom-btn-outline-light">Cancel</a></li>
        @else
          <li><a href="{{ route('museum.browse') }}" class="btn atom-btn-outline-light">Cancel</a></li>
        @endif
        <li><input type="submit" class="btn atom-btn-outline-success" value="Save"></li>
      </ul>
    </section>

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

    // Generate identifier button
    var genBtn = document.getElementById('btn-generate-identifier');
    if (genBtn) {
      genBtn.addEventListener('click', function(e) {
        e.preventDefault();
        var field = document.getElementById('object_number');
        if (field) {
          var ts = Date.now().toString(36).toUpperCase();
          field.value = 'MUS-' + ts;
        }
      });
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

/* Category Description */
.category-description {
  font-size: 13px;
  color: #666;
  margin-bottom: 16px;
  padding: 8px 12px;
  background: #f0f4f8;
  border-radius: 4px;
  border-left: 3px solid var(--ahg-primary, #005837);
}

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
