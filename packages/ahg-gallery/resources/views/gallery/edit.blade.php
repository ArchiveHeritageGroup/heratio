@extends('theme::layouts.2col')

@section('title', 'Gallery Cataloguing')
@section('body-class', ($isNew ? 'create' : 'edit') . ' gallery')

@section('sidebar')
<div class="sidebar-content">
  <section id="template-selector" class="sidebar-section">
    <h4>{{ __('Artwork Template') }}</h4>
    <div class="template-list">
      <a href="javascript:void(0)" data-template="painting" class="template-option active"><i class="fas fa-palette"></i> <span>{{ __('Painting') }}</span></a>
      <a href="javascript:void(0)" data-template="sculpture" class="template-option"><i class="fas fa-monument"></i> <span>{{ __('Sculpture') }}</span></a>
      <a href="javascript:void(0)" data-template="photograph" class="template-option"><i class="fas fa-camera"></i> <span>{{ __('Photograph') }}</span></a>
      <a href="javascript:void(0)" data-template="print" class="template-option"><i class="fas fa-print"></i> <span>{{ __('Print') }}</span></a>
      <a href="javascript:void(0)" data-template="mixed_media" class="template-option"><i class="fas fa-layer-group"></i> <span>{{ __('Mixed Media') }}</span></a>
    </div>
  </section>
  <section id="completeness-meter" class="sidebar-section">
    <h4>{{ __('Record Completeness') }}</h4>
    <div class="progress-container">
      <div class="progress"><div class="progress-bar bg-danger" id="completeness-bar" style="width: 0%"></div></div>
      <span class="completeness-value" id="completeness-value">0%</span>
    </div>
    <p class="help-text">Fill all required and recommended fields for complete cataloguing.</p>
  </section>
  <section id="cco-reference" class="sidebar-section">
    <h4>{{ __('Standards Reference') }}</h4>
    <p class="small">This form follows CCO/CDWA standards for artwork cataloguing.</p>
    <a href="http://cco.vrafoundation.org/" target="_blank" class="btn btn-sm btn-cco-guide"><i class="fas fa-external-link-alt"></i> {{ __('CCO Guide') }}</a>
    <a href="https://www.getty.edu/research/publications/electronic_publications/cdwa/" target="_blank" class="btn btn-sm btn-cco-guide mt-1"><i class="fas fa-external-link-alt"></i> CDWA</a>
  </section>
  <section id="field-legend" class="sidebar-section">
    <h4>{{ __('Field Legend') }}</h4>
    <ul class="legend-list">
      <li><span class="badge badge-required">{{ __('Required') }}</span> Must be completed</li>
      <li><span class="badge badge-recommended">{{ __('Recommended') }}</span> Should be completed</li>
      <li><span class="badge badge-optional">{{ __('Optional') }}</span> Complete if applicable</li>
    </ul>
  </section>
</div>
@endsection

@section('content')
  <h1 class="multiline">
    Gallery Cataloguing
    <span class="sub">{{ __('Artwork') }}</span>
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
      <p class="mt-2">
        <button type="submit" name="saveAnyway" value="1" form="editForm" class="btn btn-sm atom-btn-outline-warning">{{ __('Save anyway') }}</button>
      </p>
    </div>
  @endif

  <form method="POST" id="editForm" class="gallery-cataloguing-form"
        action="{{ $isNew ? route('gallery.store') : route('gallery.update', $artwork->slug) }}">
    @csrf
    @if(!$isNew)
      @method('PUT')
    @endif

    {{-- Hidden metadata fields (match AtoM: template, parent, id, saveAnyway, switch_template) --}}
    <input type="hidden" name="template" value="{{ old('template', $artwork->template ?? 'painting') }}">
    @if(request('parent'))
      <input type="hidden" name="parent" value="{{ request('parent') }}">
    @endif
    @if(!$isNew && isset($artwork))
      <input type="hidden" name="id" value="{{ $artwork->id }}">
    @endif

    <div class="accordion" id="galleryAccordion">

      {{-- ===== 1. Object/Work (CCO Ch 2) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingObjectWork">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseObjectWork" aria-expanded="true" aria-controls="collapseObjectWork">
            Object/Work
            <span class="cco-chapter">{{ __('CCO Chapter 2') }}</span>
          </button>
        </h2>
        <div id="collapseObjectWork" class="accordion-collapse collapse show" aria-labelledby="headingObjectWork" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">Information that identifies the work, including type, components, and count.</p>

            {{-- work_type: required, CCO 2.1, AAT_OBJECT_TYPES --}}
            <div class="cco-field level-required" data-field="work_type">
              <div class="field-header">
                <label for="work_type">
                  Work type <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">{{ __('Required') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">2.1</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> {{ __('AAT_OBJECT_TYPES') }}</span>
                </span>
                <button type="button" class="btn-help" data-field="work_type" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="work_type" name="work_type" value="{{ old('work_type', $artwork->work_type ?? '') }}" placeholder="{{ __('Type to search...') }}" autocomplete="off">
              </div>
              <div class="field-help" id="help-work_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The type or genre of artwork (CCO: Object/Work Type). Select the most specific applicable type for this artwork.</p>
                </div>
              </div>
            </div>

            {{-- work_type_qualifier: optional, CCO 2.1.1 --}}
            <div class="cco-field level-optional" data-field="work_type_qualifier">
              <div class="field-header">
                <label for="work_type_qualifier">
                  Work type qualifier
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">2.1.1</span>
                </span>
                <button type="button" class="btn-help" data-field="work_type_qualifier" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <select class="form-select" id="work_type_qualifier" name="work_type_qualifier">
                  <option value="">-- Select --</option>
                  <option value="possibly" @selected(old('work_type_qualifier', $artwork->work_type_qualifier ?? '') === 'possibly')>Possibly</option>
                  <option value="probably" @selected(old('work_type_qualifier', $artwork->work_type_qualifier ?? '') === 'probably')>Probably</option>
                  <option value="formerly classified as" @selected(old('work_type_qualifier', $artwork->work_type_qualifier ?? '') === 'formerly classified as')>Formerly classified as</option>
                </select>
              </div>
              <div class="field-help" id="help-work_type_qualifier" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Qualifies uncertainty about the work type. (CCO 2.1.1)</p>
                </div>
              </div>
            </div>

            {{-- components_count: optional, CCO 2.2 --}}
            <div class="cco-field level-optional" data-field="components_count">
              <div class="field-header">
                <label for="components_count">
                  Components/Parts
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">2.2</span>
                </span>
                <button type="button" class="btn-help" data-field="components_count" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="components_count" name="components_count"
                       value="{{ old('components_count', $artwork->components_count ?? '') }}">
              </div>
              <div class="field-help" id="help-components_count" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Number and description of physical components. (CCO 2.2)</p>
                </div>
              </div>
            </div>

            {{-- object_number: required, CCO 2.3 --}}
            <div class="cco-field level-required" data-field="object_number">
              <div class="field-header">
                <label for="object_number">
                  Object number <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">{{ __('Required') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">2.3</span>
                </span>
                <button type="button" class="btn-help" data-field="object_number" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="object_number" name="object_number"
                       value="{{ old('object_number', $artwork->object_number ?? '') }}">
                <div class="d-flex gap-2 mt-1">
                  <button type="button" id="btn-generate-identifier" class="btn btn-sm atom-btn-white">
                    <i class="fa fa-cog me-1"></i> {{ __('Generate identifier') }}
                  </button>
                  <small id="identifier-scheme-info" class="text-muted align-self-center"></small>
                </div>
              </div>
              <div class="field-help" id="help-object_number" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Unique identifier assigned by the repository. (CCO 2.3)</p>
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
            <span class="cco-chapter">{{ __('CCO Chapter 3') }}</span>
          </button>
        </h2>
        <div id="collapseTitle" class="accordion-collapse collapse" aria-labelledby="headingTitle" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">Titles, names, or other identifying phrases for the work.</p>

            {{-- title: required, CCO 3.1 --}}
            <div class="cco-field level-required" data-field="title">
              <div class="field-header">
                <label for="title">
                  Title <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">{{ __('Required') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">3.1</span>
                </span>
                <button type="button" class="btn-help" data-field="title" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title"
                       value="{{ old('title', $artwork->title ?? '') }}" required>
                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="field-help" id="help-title" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The primary title of the work. (CCO 3.1)</p>
                </div>
              </div>
            </div>

            {{-- title_type: required, CCO 3.1.1, CCO_TITLE_TYPES --}}
            <div class="cco-field level-required" data-field="title_type">
              <div class="field-header">
                <label for="title_type">
                  Title type <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">{{ __('Required') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">3.1.1</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> {{ __('CCO_TITLE_TYPES') }}</span>
                </span>
                <button type="button" class="btn-help" data-field="title_type" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <select class="form-select" id="title_type" name="title_type">
                  <option value="repository" @selected(old('title_type', $artwork->title_type ?? 'repository') === 'repository')>Repository</option>
                  <option value="creator" @selected(old('title_type', $artwork->title_type ?? '') === 'creator')>Creator</option>
                  <option value="inscribed" @selected(old('title_type', $artwork->title_type ?? '') === 'inscribed')>Inscribed</option>
                  <option value="popular" @selected(old('title_type', $artwork->title_type ?? '') === 'popular')>Popular</option>
                  <option value="descriptive" @selected(old('title_type', $artwork->title_type ?? '') === 'descriptive')>Descriptive</option>
                  <option value="former" @selected(old('title_type', $artwork->title_type ?? '') === 'former')>Former</option>
                  <option value="translated" @selected(old('title_type', $artwork->title_type ?? '') === 'translated')>Translated</option>
                </select>
              </div>
              <div class="field-help" id="help-title_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The source or nature of the title. (CCO 3.1.1)</p>
                </div>
              </div>
            </div>

            {{-- title_language: optional, CCO 3.1.2, ISO639_2 --}}
            <div class="cco-field level-optional" data-field="title_language">
              <div class="field-header">
                <label for="title_language">
                  Title language
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">3.1.2</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> {{ __('ISO639_2') }}</span>
                </span>
                <button type="button" class="btn-help" data-field="title_language" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="title_language" name="title_language" value="{{ old('title_language', $artwork->title_language ?? 'eng') }}">
              </div>
              <div class="field-help" id="help-title_language" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Language of the title (ISO 639-2). (CCO 3.1.2)</p>
                </div>
              </div>
            </div>

            {{-- alternate_titles: optional, CCO 3.2 --}}
            <div class="cco-field level-optional" data-field="alternate_titles">
              <div class="field-header">
                <label for="alternate_titles">
                  Alternate titles
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">3.2</span>
                </span>
                <button type="button" class="btn-help" data-field="alternate_titles" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="alternate_titles" name="alternate_titles" rows="2">{{ old('alternate_titles', $artwork->alternate_titles ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-alternate_titles" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Other titles by which the work is known. (CCO 3.2)</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 3. Creation (CCO Ch 4) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCreation">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCreation" aria-expanded="false" aria-controls="collapseCreation">
            Creation
            <span class="cco-chapter">{{ __('CCO Chapter 4') }}</span>
          </button>
        </h2>
        <div id="collapseCreation" class="accordion-collapse collapse" aria-labelledby="headingCreation" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">Information about who created the work, when, and where.</p>

            {{-- creator_display: required, CCO 4.1 --}}
            <div class="cco-field level-required" data-field="creator_display">
              <div class="field-header">
                <label for="creator_display">
                  Creator (Display) <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">{{ __('Required') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">4.1</span>
                </span>
                <button type="button" class="btn-help" data-field="creator_display" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creator_display" name="creator_display"
                       value="{{ old('creator_display', $artwork->creator_display ?? '') }}">
              </div>
              <div class="field-help" id="help-creator_display" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Creator name as it should appear in displays. Format: Surname, Forename (Nationality, birth-death). (CCO 4.1)</p>
                </div>
              </div>
            </div>

            {{-- creator: required, CCO 4.1, ULAN --}}
            <div class="cco-field level-required" data-field="creator">
              <div class="field-header">
                <label for="creator">
                  Creator (Authority) <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">{{ __('Required') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">4.1</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> ULAN</span>
                </span>
                <button type="button" class="btn-help" data-field="creator" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <select class="form-select" id="creator" name="creator">
                  <option value="">-- Select --</option>
                  @foreach($creators ?? [] as $c)
                    <option value="{{ $c->id ?? $c }}" @selected(old('creator', $artwork->creator ?? '') == ($c->id ?? $c))>{{ $c->name ?? $c }}</option>
                  @endforeach
                </select>
              </div>
              <div class="field-help" id="help-creator" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Link to authority record. (CCO 4.1)</p>
                </div>
              </div>
            </div>

            {{-- creator_role: required, CCO 4.1.1, AAT_CREATOR_ROLES --}}
            <div class="cco-field level-required" data-field="creator_role">
              <div class="field-header">
                <label for="creator_role">
                  Creator role <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">{{ __('Required') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">4.1.1</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> {{ __('AAT_CREATOR_ROLES') }}</span>
                </span>
                <button type="button" class="btn-help" data-field="creator_role" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creator_role" name="creator_role" value="{{ old('creator_role', $artwork->creator_role ?? '') }}">
              </div>
              <div class="field-help" id="help-creator_role" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The role of the creator. (CCO 4.1.1)</p>
                </div>
              </div>
            </div>

            {{-- attribution_qualifier: recommended, CCO 4.1.2, CCO_ATTRIBUTION --}}
            <div class="cco-field level-recommended" data-field="attribution_qualifier">
              <div class="field-header">
                <label for="attribution_qualifier">
                  Attribution qualifier
                </label>
                <span class="field-badges">
                  <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">4.1.2</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> {{ __('CCO_ATTRIBUTION') }}</span>
                </span>
                <button type="button" class="btn-help" data-field="attribution_qualifier" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
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
              </div>
              <div class="field-help" id="help-attribution_qualifier" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Qualifies degree of certainty about attribution. (CCO 4.1.2)</p>
                </div>
              </div>
            </div>

            {{-- creation_date_display: required, CCO 4.2 --}}
            <div class="cco-field level-required" data-field="creation_date_display">
              <div class="field-header">
                <label for="creation_date_display">
                  Date (display) <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">{{ __('Required') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">4.2</span>
                </span>
                <button type="button" class="btn-help" data-field="creation_date_display" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creation_date_display" name="creation_date_display"
                       value="{{ old('creation_date_display', $artwork->creation_date_display ?? '') }}">
              </div>
              <div class="field-help" id="help-creation_date_display" style="display: none;">
                <div class="help-content">
                  <p class="help-text">A free-text date for display purposes (e.g. "ca. 1885", "early 20th century", "1965-1970"). Enter the date as it should appear to users.</p>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                {{-- creation_date_earliest: recommended, CCO 4.2.1 --}}
                <div class="cco-field level-recommended" data-field="creation_date_earliest">
                  <div class="field-header">
                    <label for="creation_date_earliest">
                      Earliest date
                    </label>
                    <span class="field-badges">
                      <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                      <span class="badge badge-cco" title="{{ __('CCO Reference') }}">4.2.1</span>
                    </span>
                    <button type="button" class="btn-help" data-field="creation_date_earliest" title="{{ __('Help') }}">
                      <i class="fa fa-question-circle"></i>
                    </button>
                  </div>
                  <div class="field-input">
                    <input type="date" class="form-control" id="creation_date_earliest" name="creation_date_earliest" value="{{ old('creation_date_earliest', $artwork->creation_date_earliest ?? '') }}">
                  </div>
                  <div class="field-help" id="help-creation_date_earliest" style="display: none;">
                    <div class="help-content">
                      <p class="help-text">The earliest possible creation date in ISO 8601 format (YYYY-MM-DD or YYYY). Used for date range searching.</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                {{-- creation_date_latest: recommended, CCO 4.2.2 --}}
                <div class="cco-field level-recommended" data-field="creation_date_latest">
                  <div class="field-header">
                    <label for="creation_date_latest">
                      Latest date
                    </label>
                    <span class="field-badges">
                      <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                      <span class="badge badge-cco" title="{{ __('CCO Reference') }}">4.2.2</span>
                    </span>
                    <button type="button" class="btn-help" data-field="creation_date_latest" title="{{ __('Help') }}">
                      <i class="fa fa-question-circle"></i>
                    </button>
                  </div>
                  <div class="field-input">
                    <input type="date" class="form-control" id="creation_date_latest" name="creation_date_latest" value="{{ old('creation_date_latest', $artwork->creation_date_latest ?? '') }}">
                  </div>
                  <div class="field-help" id="help-creation_date_latest" style="display: none;">
                    <div class="help-content">
                      <p class="help-text">The latest possible creation date in ISO 8601 format (YYYY-MM-DD or YYYY). Used for date range searching.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {{-- creation_place: recommended, CCO 4.3, TGN --}}
            <div class="cco-field level-recommended" data-field="creation_place">
              <div class="field-header">
                <label for="creation_place">
                  Place of creation
                </label>
                <span class="field-badges">
                  <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">4.3</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> TGN</span>
                </span>
                <button type="button" class="btn-help" data-field="creation_place" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="creation_place" name="creation_place"
                       value="{{ old('creation_place', $artwork->creation_place ?? '') }}" placeholder="{{ __('Type to search places...') }}" autocomplete="off">
              </div>
              <div class="field-help" id="help-creation_place" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Geographic location where the work was created. (CCO 4.3)</p>
                </div>
              </div>
            </div>

            {{-- culture: optional, CCO 4.4, AAT_CULTURES --}}
            <div class="cco-field level-optional" data-field="culture">
              <div class="field-header">
                <label for="culture">
                  Culture/People
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">4.4</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> {{ __('AAT_CULTURES') }}</span>
                </span>
                <button type="button" class="btn-help" data-field="culture" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="culture" name="culture" value="{{ old('culture', $artwork->culture ?? '') }}" placeholder="{{ __('Type to search...') }}" autocomplete="off">
              </div>
              <div class="field-help" id="help-culture" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Culture, people, or nationality associated with the creation. (CCO 4.4)</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 4. Styles/Periods (CCO Ch 5) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingStylesPeriods">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStylesPeriods" aria-expanded="false" aria-controls="collapseStylesPeriods">
            Styles/Periods
            <span class="cco-chapter">{{ __('CCO Chapter 5') }}</span>
          </button>
        </h2>
        <div id="collapseStylesPeriods" class="accordion-collapse collapse" aria-labelledby="headingStylesPeriods" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">Style, period, group, school, or movement.</p>

            {{-- style: recommended, CCO 5.1, AAT_STYLES --}}
            <div class="cco-field level-recommended" data-field="style">
              <div class="field-header">
                <label for="style">
                  Style
                </label>
                <span class="field-badges">
                  <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">5.1</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> {{ __('AAT_STYLES') }}</span>
                </span>
                <button type="button" class="btn-help" data-field="style" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="style" name="style"
                       value="{{ old('style', $artwork->style ?? '') }}">
              </div>
              <div class="field-help" id="help-style" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The visual style of the work (e.g. "Impressionism", "Art Nouveau", "Abstract Expressionism").</p>
                </div>
              </div>
            </div>

            {{-- period: optional, CCO 5.2, AAT_PERIODS --}}
            <div class="cco-field level-optional" data-field="period">
              <div class="field-header">
                <label for="period">
                  Period
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">5.2</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> {{ __('AAT_PERIODS') }}</span>
                </span>
                <button type="button" class="btn-help" data-field="period" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="period" name="period"
                       value="{{ old('period', $artwork->period ?? '') }}">
              </div>
              <div class="field-help" id="help-period" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The broad cultural or chronological period (e.g. "Renaissance", "Modern", "Contemporary").</p>
                </div>
              </div>
            </div>

            {{-- school_group: optional, CCO 5.3 --}}
            <div class="cco-field level-optional" data-field="school_group">
              <div class="field-header">
                <label for="school_group">
                  School/Group
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">5.3</span>
                </span>
                <button type="button" class="btn-help" data-field="school_group" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="school_group" name="school_group" value="{{ old('school_group', $artwork->school_group ?? '') }}" placeholder="{{ __('Type to search...') }}" autocomplete="off">
              </div>
              <div class="field-help" id="help-school_group" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The school of art or artistic group. (CCO 5.3)</p>
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
            <span class="cco-chapter">{{ __('CCO Chapter 6') }}</span>
          </button>
        </h2>
        <div id="collapseMeasurements" class="accordion-collapse collapse" aria-labelledby="headingMeasurements" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">Physical dimensions and other measurements.</p>

            {{-- dimensions_display: required, CCO 6.1 --}}
            <div class="cco-field level-required" data-field="dimensions_display">
              <div class="field-header">
                <label for="dimensions_display">
                  Dimensions (Display) <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">{{ __('Required') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">6.1</span>
                </span>
                <button type="button" class="btn-help" data-field="dimensions_display" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="dimensions_display" name="dimensions_display" rows="2">{{ old('dimensions_display', $artwork->dimensions_display ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-dimensions_display" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Dimensions as displayed, e.g. "72.4 x 91.4 cm". (CCO 6.1)</p>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-3">
                {{-- height_value: recommended, CCO 6.2 --}}
                <div class="cco-field level-recommended" data-field="height_value">
                  <div class="field-header">
                    <label for="height_value">
                      Height
                    </label>
                    <span class="field-badges">
                      <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                      <span class="badge badge-cco" title="{{ __('CCO Reference') }}">6.2</span>
                    </span>
                    <button type="button" class="btn-help" data-field="height_value" title="{{ __('Help') }}">
                      <i class="fa fa-question-circle"></i>
                    </button>
                  </div>
                  <div class="field-input">
                    <input type="text" class="form-control" id="height_value" name="height_value" value="{{ old('height_value', $artwork->height_value ?? '') }}">
                  </div>
                  <div class="field-help" id="help-height_value" style="display: none;">
                    <div class="help-content">
                      <p class="help-text">Height measurement value.</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                {{-- width_value: recommended, CCO 6.2 --}}
                <div class="cco-field level-recommended" data-field="width_value">
                  <div class="field-header">
                    <label for="width_value">
                      Width
                    </label>
                    <span class="field-badges">
                      <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                      <span class="badge badge-cco" title="{{ __('CCO Reference') }}">6.2</span>
                    </span>
                    <button type="button" class="btn-help" data-field="width_value" title="{{ __('Help') }}">
                      <i class="fa fa-question-circle"></i>
                    </button>
                  </div>
                  <div class="field-input">
                    <input type="text" class="form-control" id="width_value" name="width_value" value="{{ old('width_value', $artwork->width_value ?? '') }}">
                  </div>
                  <div class="field-help" id="help-width_value" style="display: none;">
                    <div class="help-content">
                      <p class="help-text">Width measurement value.</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                {{-- depth_value: optional, CCO 6.2 --}}
                <div class="cco-field level-optional" data-field="depth_value">
                  <div class="field-header">
                    <label for="depth_value">
                      Depth
                    </label>
                    <span class="field-badges">
                      <span class="badge badge-optional">{{ __('Optional') }}</span>
                      <span class="badge badge-cco" title="{{ __('CCO Reference') }}">6.2</span>
                    </span>
                    <button type="button" class="btn-help" data-field="depth_value" title="{{ __('Help') }}">
                      <i class="fa fa-question-circle"></i>
                    </button>
                  </div>
                  <div class="field-input">
                    <input type="text" class="form-control" id="depth_value" name="depth_value" value="{{ old('depth_value', $artwork->depth_value ?? '') }}">
                  </div>
                  <div class="field-help" id="help-depth_value" style="display: none;">
                    <div class="help-content">
                      <p class="help-text">Depth measurement value.</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                {{-- weight_value: optional, CCO 6.3 --}}
                <div class="cco-field level-optional" data-field="weight_value">
                  <div class="field-header">
                    <label for="weight_value">
                      Weight
                    </label>
                    <span class="field-badges">
                      <span class="badge badge-optional">{{ __('Optional') }}</span>
                      <span class="badge badge-cco" title="{{ __('CCO Reference') }}">6.3</span>
                    </span>
                    <button type="button" class="btn-help" data-field="weight_value" title="{{ __('Help') }}">
                      <i class="fa fa-question-circle"></i>
                    </button>
                  </div>
                  <div class="field-input">
                    <input type="text" class="form-control" id="weight_value" name="weight_value" value="{{ old('weight_value', $artwork->weight_value ?? '') }}">
                  </div>
                  <div class="field-help" id="help-weight_value" style="display: none;">
                    <div class="help-content">
                      <p class="help-text">Weight measurement value.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {{-- dimension_notes: optional, CCO 6.4 --}}
            <div class="cco-field level-optional" data-field="dimension_notes">
              <div class="field-header">
                <label for="dimension_notes">
                  Dimension notes
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">6.4</span>
                </span>
                <button type="button" class="btn-help" data-field="dimension_notes" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="dimension_notes" name="dimension_notes" rows="2">{{ old('dimension_notes', $artwork->dimension_notes ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-dimension_notes" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Notes about how measurements were taken. (CCO 6.4)</p>
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
            <span class="cco-chapter">{{ __('CCO Chapter 7') }}</span>
          </button>
        </h2>
        <div id="collapseMaterials" class="accordion-collapse collapse" aria-labelledby="headingMaterials" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">Physical materials and techniques used to create the work.</p>

            {{-- materials_display: required, CCO 7.1 --}}
            <div class="cco-field level-required" data-field="materials_display">
              <div class="field-header">
                <label for="materials_display">
                  Medium (Display) <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">{{ __('Required') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">7.1</span>
                </span>
                <button type="button" class="btn-help" data-field="materials_display" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="materials_display" name="materials_display" rows="2">{{ old('materials_display', $artwork->materials_display ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-materials_display" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Medium as displayed, e.g. "oil on canvas". (CCO 7.1)</p>
                </div>
              </div>
            </div>

            {{-- materials: recommended, CCO 7.1.1, AAT_MATERIALS --}}
            <div class="cco-field level-recommended" data-field="materials">
              <div class="field-header">
                <label for="materials">
                  Materials (Indexed)
                </label>
                <span class="field-badges">
                  <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">7.1.1</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> {{ __('AAT_MATERIALS') }}</span>
                </span>
                <button type="button" class="btn-help" data-field="materials" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="materials" name="materials" value="{{ old('materials', $artwork->materials ?? '') }}">
              </div>
              <div class="field-help" id="help-materials" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Individual materials for searching. (CCO 7.1.1)</p>
                </div>
              </div>
            </div>

            {{-- techniques: recommended, CCO 7.2, AAT_TECHNIQUES --}}
            <div class="cco-field level-recommended" data-field="techniques">
              <div class="field-header">
                <label for="techniques">
                  Techniques
                </label>
                <span class="field-badges">
                  <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">7.2</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> {{ __('AAT_TECHNIQUES') }}</span>
                </span>
                <button type="button" class="btn-help" data-field="techniques" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="techniques" name="techniques" value="{{ old('techniques', $artwork->techniques ?? '') }}">
              </div>
              <div class="field-help" id="help-techniques" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The techniques or processes used to create the artwork (e.g. "Impasto", "Lost-wax casting", "Screen printing", "Collage").</p>
                </div>
              </div>
            </div>

            {{-- support: required, CCO 7.3, AAT_SUPPORTS --}}
            <div class="cco-field level-required" data-field="support">
              <div class="field-header">
                <label for="support">
                  Support <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">{{ __('Required') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">7.3</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> {{ __('AAT_SUPPORTS') }}</span>
                </span>
                <button type="button" class="btn-help" data-field="support" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="support" name="support" value="{{ old('support', $artwork->support ?? '') }}">
              </div>
              <div class="field-help" id="help-support" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The material on which the work is executed (e.g. canvas, paper). (CCO 7.3)</p>
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
            <span class="cco-chapter">{{ __('CCO Chapter 8') }}</span>
          </button>
        </h2>
        <div id="collapseSubject" class="accordion-collapse collapse" aria-labelledby="headingSubject" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">What the work represents or depicts.</p>

            {{-- subject_display: recommended, CCO 8.1 --}}
            <div class="cco-field level-recommended" data-field="subject_display">
              <div class="field-header">
                <label for="subject_display">
                  Subject (Display)
                </label>
                <span class="field-badges">
                  <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">8.1</span>
                </span>
                <button type="button" class="btn-help" data-field="subject_display" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="subject_display" name="subject_display" rows="3">{{ old('subject_display', $artwork->subject_display ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-subject_display" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Subject as it should appear in displays. (CCO 8.1)</p>
                </div>
              </div>
            </div>

            {{-- subjects_depicted: recommended, CCO 8.2, AAT_SUBJECTS --}}
            <div class="cco-field level-recommended" data-field="subjects_depicted">
              <div class="field-header">
                <label for="subjects_depicted">
                  Subjects depicted
                </label>
                <span class="field-badges">
                  <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">8.2</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> {{ __('AAT_SUBJECTS') }}</span>
                </span>
                <button type="button" class="btn-help" data-field="subjects_depicted" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="subjects_depicted" name="subjects_depicted" value="{{ old('subjects_depicted', $artwork->subjects_depicted ?? '') }}" placeholder="{{ __('Type to search...') }}" autocomplete="off">
              </div>
              <div class="field-help" id="help-subjects_depicted" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Specific subjects depicted. (CCO 8.2)</p>
                </div>
              </div>
            </div>

            {{-- iconography: optional, CCO 8.3, ICONCLASS --}}
            <div class="cco-field level-optional" data-field="iconography">
              <div class="field-header">
                <label for="iconography">
                  Iconography
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">8.3</span>
                  <span class="badge badge-vocab" title="{{ __('Controlled Vocabulary') }}"><i class="fa fa-book"></i> {{ __('ICONCLASS') }}</span>
                </span>
                <button type="button" class="btn-help" data-field="iconography" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="iconography" name="iconography" value="{{ old('iconography', $artwork->iconography ?? '') }}">
              </div>
              <div class="field-help" id="help-iconography" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Iconographic themes, symbols, or narratives. (CCO 8.3)</p>
                </div>
              </div>
            </div>

            {{-- named_subjects: optional, CCO 8.4 --}}
            <div class="cco-field level-optional" data-field="named_subjects">
              <div class="field-header">
                <label for="named_subjects">
                  Named subjects
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">8.4</span>
                </span>
                <button type="button" class="btn-help" data-field="named_subjects" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="named_subjects" name="named_subjects" value="{{ old('named_subjects', $artwork->named_subjects ?? '') }}">
              </div>
              <div class="field-help" id="help-named_subjects" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Named people, places, or events depicted. (CCO 8.4)</p>
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
            <span class="cco-chapter">{{ __('CCO Chapter 9') }}</span>
          </button>
        </h2>
        <div id="collapseInscriptions" class="accordion-collapse collapse" aria-labelledby="headingInscriptions" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">Marks, inscriptions, and signatures on the work.</p>

            {{-- inscriptions: optional, CCO 9.1 --}}
            <div class="cco-field level-optional" data-field="inscriptions">
              <div class="field-header">
                <label for="inscriptions">
                  Inscriptions
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">9.1</span>
                </span>
                <button type="button" class="btn-help" data-field="inscriptions" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="inscriptions" name="inscriptions" rows="3">{{ old('inscriptions', $artwork->inscriptions ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-inscriptions" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Text inscriptions on the work. (CCO 9.1)</p>
                </div>
              </div>
            </div>

            {{-- signature: recommended, CCO 9.2 --}}
            <div class="cco-field level-recommended" data-field="signature">
              <div class="field-header">
                <label for="signature">
                  Signature
                </label>
                <span class="field-badges">
                  <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">9.2</span>
                </span>
                <button type="button" class="btn-help" data-field="signature" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="signature" name="signature" value="{{ old('signature', $artwork->signature ?? '') }}">
              </div>
              <div class="field-help" id="help-signature" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Description of the artist's signature. (CCO 9.2)</p>
                </div>
              </div>
            </div>

            {{-- marks: optional, CCO 9.3 --}}
            <div class="cco-field level-optional" data-field="marks">
              <div class="field-header">
                <label for="marks">
                  Marks/Labels
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">9.3</span>
                </span>
                <button type="button" class="btn-help" data-field="marks" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="marks" name="marks" rows="2">{{ old('marks', $artwork->marks ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-marks" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Collector's marks, labels, stamps. (CCO 9.3)</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 9. State/Edition (CCO Ch 10) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingStateEdition">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStateEdition" aria-expanded="false" aria-controls="collapseStateEdition">
            State/Edition
            <span class="cco-chapter">{{ __('CCO Chapter 10') }}</span>
          </button>
        </h2>
        <div id="collapseStateEdition" class="accordion-collapse collapse" aria-labelledby="headingStateEdition" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">For prints, photographs, and multiples.</p>

            {{-- edition_number: optional, CCO 10.1 --}}
            <div class="cco-field level-optional" data-field="edition_number">
              <div class="field-header">
                <label for="edition_number">
                  Edition number
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">10.1</span>
                </span>
                <button type="button" class="btn-help" data-field="edition_number" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="edition_number" name="edition_number"
                       value="{{ old('edition_number', $artwork->edition_number ?? '') }}">
              </div>
              <div class="field-help" id="help-edition_number" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The specific number within an edition (e.g. "3/50"). (CCO 10.1)</p>
                </div>
              </div>
            </div>

            {{-- edition_size: optional, CCO 10.2 --}}
            <div class="cco-field level-optional" data-field="edition_size">
              <div class="field-header">
                <label for="edition_size">
                  Edition size
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">10.2</span>
                </span>
                <button type="button" class="btn-help" data-field="edition_size" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="edition_size" name="edition_size"
                       value="{{ old('edition_size', $artwork->edition_size ?? '') }}">
              </div>
              <div class="field-help" id="help-edition_size" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Total size of the edition. (CCO 10.2)</p>
                </div>
              </div>
            </div>

            {{-- state: optional, CCO 10.3 --}}
            <div class="cco-field level-optional" data-field="state">
              <div class="field-header">
                <label for="state">
                  State
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">10.3</span>
                </span>
                <button type="button" class="btn-help" data-field="state" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="state" name="state"
                       value="{{ old('state', $artwork->state ?? '') }}">
              </div>
              <div class="field-help" id="help-state" style="display: none;">
                <div class="help-content">
                  <p class="help-text">For prints: which state of the plate. (CCO 10.3)</p>
                </div>
              </div>
            </div>

            {{-- impression_quality: optional, CCO 10.4 --}}
            <div class="cco-field level-optional" data-field="impression_quality">
              <div class="field-header">
                <label for="impression_quality">
                  Impression quality
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">10.4</span>
                </span>
                <button type="button" class="btn-help" data-field="impression_quality" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <select class="form-select" id="impression_quality" name="impression_quality">
                  <option value="">-- Select --</option>
                  @foreach(['excellent' => 'Excellent', 'very_good' => 'Very good', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('impression_quality', $artwork->impression_quality ?? '') == $val)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="field-help" id="help-impression_quality" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Quality of the impression. (CCO 10.4)</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 10. Description (CCO Ch 11) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingDescription">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDescription" aria-expanded="false" aria-controls="collapseDescription">
            Description
            <span class="cco-chapter">{{ __('CCO Chapter 11') }}</span>
          </button>
        </h2>
        <div id="collapseDescription" class="accordion-collapse collapse" aria-labelledby="headingDescription" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">Descriptive text about the work.</p>

            {{-- description: recommended, CCO 11.1 --}}
            <div class="cco-field level-recommended" data-field="description">
              <div class="field-header">
                <label for="description">
                  Description
                </label>
                <span class="field-badges">
                  <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">11.1</span>
                </span>
                <button type="button" class="btn-help" data-field="description" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $artwork->description ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-description" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Narrative description supplementing other fields. (CCO 11.1)</p>
                </div>
              </div>
            </div>

            {{-- physical_description: optional, CCO 11.2 --}}
            <div class="cco-field level-optional" data-field="physical_description">
              <div class="field-header">
                <label for="physical_description">
                  Physical description
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">11.2</span>
                </span>
                <button type="button" class="btn-help" data-field="physical_description" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="physical_description" name="physical_description" rows="4">{{ old('physical_description', $artwork->physical_description ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-physical_description" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Physical characteristics not covered elsewhere. (CCO 11.2)</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 11. Condition (CCO Ch 12) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCondition">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCondition" aria-expanded="false" aria-controls="collapseCondition">
            Condition
            <span class="cco-chapter">{{ __('CCO Chapter 12') }}</span>
          </button>
        </h2>
        <div id="collapseCondition" class="accordion-collapse collapse" aria-labelledby="headingCondition" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">Current physical condition.</p>

            {{-- condition_summary: recommended, CCO 12.1 --}}
            <div class="cco-field level-recommended" data-field="condition_summary">
              <div class="field-header">
                <label for="condition_summary">
                  Condition summary
                </label>
                <span class="field-badges">
                  <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">12.1</span>
                </span>
                <button type="button" class="btn-help" data-field="condition_summary" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="condition_summary" name="condition_summary" rows="3">{{ old('condition_summary', $artwork->condition_summary ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-condition_summary" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Brief summary of the current condition. (CCO 12.1)</p>
                </div>
              </div>
            </div>

            {{-- condition_notes: optional, CCO 12.2 --}}
            <div class="cco-field level-optional" data-field="condition_notes">
              <div class="field-header">
                <label for="condition_notes">
                  Condition notes
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">12.2</span>
                </span>
                <button type="button" class="btn-help" data-field="condition_notes" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="condition_notes" name="condition_notes" rows="3">{{ old('condition_notes', $artwork->condition_notes ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-condition_notes" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Detailed notes on the condition. (CCO 12.2)</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 12. Current Location (CCO Ch 13) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingCurrentLocation">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCurrentLocation" aria-expanded="false" aria-controls="collapseCurrentLocation">
            Current Location
            <span class="cco-chapter">{{ __('CCO Chapter 13') }}</span>
          </button>
        </h2>
        <div id="collapseCurrentLocation" class="accordion-collapse collapse" aria-labelledby="headingCurrentLocation" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">Where the work is currently held.</p>

            {{-- repository: required, CCO 13.1 --}}
            <div class="cco-field level-required" data-field="repository">
              <div class="field-header">
                <label for="repository">
                  Repository <span class="required">*</span>
                </label>
                <span class="field-badges">
                  <span class="badge badge-required">{{ __('Required') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">13.1</span>
                </span>
                <button type="button" class="btn-help" data-field="repository" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <select class="form-select" id="repository" name="repository">
                  <option value="">-- Select --</option>
                  @foreach($repositories ?? [] as $repo)
                    <option value="{{ $repo->id }}" @selected(old('repository', $artwork->repository_id ?? $artwork->repository ?? '') == $repo->id)>{{ $repo->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="field-help" id="help-repository" style="display: none;">
                <div class="help-content">
                  <p class="help-text">The repository holding this work. (CCO 13.1)</p>
                </div>
              </div>
            </div>

            {{-- location_within_repository: recommended, CCO 13.2 --}}
            <div class="cco-field level-recommended" data-field="location_within_repository">
              <div class="field-header">
                <label for="location_within_repository">
                  Location
                </label>
                <span class="field-badges">
                  <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">13.2</span>
                </span>
                <button type="button" class="btn-help" data-field="location_within_repository" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="location_within_repository" name="location_within_repository" value="{{ old('location_within_repository', $artwork->location_within_repository ?? '') }}">
              </div>
              <div class="field-help" id="help-location_within_repository" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Location within the repository. (CCO 13.2)</p>
                </div>
              </div>
            </div>

            {{-- credit_line: recommended, CCO 13.3 --}}
            <div class="cco-field level-recommended" data-field="credit_line">
              <div class="field-header">
                <label for="credit_line">
                  Credit line
                </label>
                <span class="field-badges">
                  <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">13.3</span>
                </span>
                <button type="button" class="btn-help" data-field="credit_line" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="credit_line" name="credit_line" value="{{ old('credit_line', $artwork->credit_line ?? '') }}">
              </div>
              <div class="field-help" id="help-credit_line" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Credit line for display. (CCO 13.3)</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 13. Related Works (CCO Ch 14) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingRelatedWorks">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRelatedWorks" aria-expanded="false" aria-controls="collapseRelatedWorks">
            Related Works
            <span class="cco-chapter">{{ __('CCO Chapter 14') }}</span>
          </button>
        </h2>
        <div id="collapseRelatedWorks" class="accordion-collapse collapse" aria-labelledby="headingRelatedWorks" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">Relationships to other works.</p>

            {{-- related_works: optional, CCO 14.1 --}}
            <div class="cco-field level-optional" data-field="related_works">
              <div class="field-header">
                <label for="related_works">
                  Related works
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">14.1</span>
                </span>
                <button type="button" class="btn-help" data-field="related_works" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="related_works" name="related_works" rows="3">{{ old('related_works', $artwork->related_works ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-related_works" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Identify related works. (CCO 14.1)</p>
                </div>
              </div>
            </div>

            {{-- relationship_type: optional, CCO 14.2 --}}
            <div class="cco-field level-optional" data-field="relationship_type">
              <div class="field-header">
                <label for="relationship_type">
                  Relationship type
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">14.2</span>
                </span>
                <button type="button" class="btn-help" data-field="relationship_type" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <select class="form-select" id="relationship_type" name="relationship_type">
                  <option value="">-- Select --</option>
                  @foreach(['study_for' => 'Study for', 'copy_of' => 'Copy of', 'copy_after' => 'Copy after', 'pendant_to' => 'Pendant to', 'part_of' => 'Part of (series)', 'variant_of' => 'Variant of', 'model_for' => 'Model for', 'related_to' => 'Related to'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('relationship_type', $artwork->relationship_type ?? '') == $val)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="field-help" id="help-relationship_type" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Type of relationship to related work. (CCO 14.2)</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== 14. Rights (CCO Ch 15) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="headingRights">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRights" aria-expanded="false" aria-controls="collapseRights">
            Rights
            <span class="cco-chapter">{{ __('CCO Chapter 15') }}</span>
          </button>
        </h2>
        <div id="collapseRights" class="accordion-collapse collapse" aria-labelledby="headingRights" data-bs-parent="#galleryAccordion">
          <div class="accordion-body">
            <p class="category-description">Rights and reproduction information.</p>

            {{-- rights_statement: recommended, CCO 15.1 --}}
            <div class="cco-field level-recommended" data-field="rights_statement">
              <div class="field-header">
                <label for="rights_statement">
                  Rights statement
                </label>
                <span class="field-badges">
                  <span class="badge badge-recommended">{{ __('Recommended') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">15.1</span>
                </span>
                <button type="button" class="btn-help" data-field="rights_statement" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="rights_statement" name="rights_statement" rows="3">{{ old('rights_statement', $artwork->rights_statement ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-rights_statement" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Statement of rights associated with the work. (CCO 15.1)</p>
                </div>
              </div>
            </div>

            {{-- copyright_holder: optional, CCO 15.2 --}}
            <div class="cco-field level-optional" data-field="copyright_holder">
              <div class="field-header">
                <label for="copyright_holder">
                  Copyright holder
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">15.2</span>
                </span>
                <button type="button" class="btn-help" data-field="copyright_holder" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <input type="text" class="form-control" id="copyright_holder" name="copyright_holder" value="{{ old('copyright_holder', $artwork->copyright_holder ?? '') }}">
              </div>
              <div class="field-help" id="help-copyright_holder" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Name of the copyright holder. (CCO 15.2)</p>
                </div>
              </div>
            </div>

            {{-- reproduction_conditions: optional, CCO 15.3 --}}
            <div class="cco-field level-optional" data-field="reproduction_conditions">
              <div class="field-header">
                <label for="reproduction_conditions">
                  Reproduction conditions
                </label>
                <span class="field-badges">
                  <span class="badge badge-optional">{{ __('Optional') }}</span>
                  <span class="badge badge-cco" title="{{ __('CCO Reference') }}">15.3</span>
                </span>
                <button type="button" class="btn-help" data-field="reproduction_conditions" title="{{ __('Help') }}">
                  <i class="fa fa-question-circle"></i>
                </button>
              </div>
              <div class="field-input">
                <textarea class="form-control" id="reproduction_conditions" name="reproduction_conditions" rows="2">{{ old('reproduction_conditions', $artwork->reproduction_conditions ?? '') }}</textarea>
              </div>
              <div class="field-help" id="help-reproduction_conditions" style="display: none;">
                <div class="help-content">
                  <p class="help-text">Conditions governing reproduction. (CCO 15.3)</p>
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
            <span class="cco-chapter">{{ __('Storage &amp; Access') }}</span>
          </button>
        </h2>
        <div id="collapse-physical-location" class="accordion-collapse collapse" aria-labelledby="heading-physical-location">
          <div class="accordion-body">

            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Storage container <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <select name="item_physical_object_id" class="form-select">
                  <option value="">-- Select container --</option>
                  @foreach($physicalObjects ?? [] as $poId => $poName)
                    <option value="{{ $poId }}" @selected(old('item_physical_object_id', $itemLocation['physical_object_id'] ?? '') == $poId)>{{ $poName }}</option>
                  @endforeach
                </select>
                <small class="form-text text-muted">{{ __('Link to a physical storage container') }}</small>
              </div>
              <div class="col-md-6">
                <label class="form-label">Item barcode <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="item_barcode" class="form-control" value="{{ old('item_barcode', $itemLocation['barcode'] ?? '') }}">
              </div>
            </div>

            <h6 class="text-white py-2 px-3 mb-3" style="background-color: var(--ahg-primary, #005837);"><i class="fas fa-box me-2"></i>Location within container</h6>
            <div class="row mb-3">
              <div class="col-md-2">
                <label class="form-label">Box <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="item_box_number" class="form-control" value="{{ old('item_box_number', $itemLocation['box_number'] ?? '') }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Folder <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="item_folder_number" class="form-control" value="{{ old('item_folder_number', $itemLocation['folder_number'] ?? '') }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Shelf <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="item_shelf" class="form-control" value="{{ old('item_shelf', $itemLocation['shelf'] ?? '') }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Row <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="item_row" class="form-control" value="{{ old('item_row', $itemLocation['row'] ?? '') }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Position <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="item_position" class="form-control" value="{{ old('item_position', $itemLocation['position'] ?? '') }}">
              </div>
              <div class="col-md-2">
                <label class="form-label">Item # <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="item_item_number" class="form-control" value="{{ old('item_item_number', $itemLocation['item_number'] ?? '') }}">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-3">
                <label class="form-label">Extent value <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="number" step="0.01" name="item_extent_value" class="form-control" value="{{ old('item_extent_value', $itemLocation['extent_value'] ?? '') }}">
              </div>
              <div class="col-md-3">
                <label class="form-label">Extent unit <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
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
                <label class="form-label">Condition <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <select name="item_condition_status" class="form-select">
                  <option value="">-- Select --</option>
                  @foreach(['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor', 'critical' => 'Critical'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('item_condition_status', $itemLocation['condition_status'] ?? '') == $val)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Access status <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <select name="item_access_status" class="form-select">
                  @foreach(['available' => 'Available', 'in_use' => 'In Use', 'restricted' => 'Restricted', 'offsite' => 'Offsite', 'missing' => 'Missing'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('item_access_status', $itemLocation['access_status'] ?? 'available') == $val)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Condition notes <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="item_condition_notes" class="form-control" value="{{ old('item_condition_notes', $itemLocation['condition_notes'] ?? '') }}">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-12">
                <label class="form-label">Location notes <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
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
            {{ __('Administration area') }}
          </button>
        </h2>
        <div id="admin-collapse" class="accordion-collapse collapse" aria-labelledby="admin-heading">
          <div class="accordion-body">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label fw-bold">Source language <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  <div>{{ $sourceCulture ?? 'English' }}</div>
                </div>
                @if(!$isNew && isset($artwork->updated_at) && $artwork->updated_at)
                <div class="mb-3">
                  <label class="form-label fw-bold">Last updated <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  <div>{{ \Carbon\Carbon::parse($artwork->updated_at)->format('F j, Y, g:i a') }}</div>
                </div>
                @endif
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="displayStandard" class="form-label fw-bold">Display standard <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  <select name="displayStandard" id="displayStandard" class="form-select">
                    @foreach($displayStandards ?? [] as $dsId => $dsName)
                      <option value="{{ $dsId }}" @selected(old('displayStandard', $currentDisplayStandard ?? '') == $dsId)>{{ $dsName }}</option>
                    @endforeach
                  </select>
                  <small class="form-text text-muted">{{ __('Select the display standard for this record') }}</small>
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

    // Template switching (sets hidden template field + switch_template flag)
    document.querySelectorAll('.template-option').forEach(function(opt) {
      opt.addEventListener('click', function(e) {
        e.preventDefault();
        var tpl = this.getAttribute('data-template');
        var form = document.getElementById('editForm');
        if (!form) return;
        // Update active class
        document.querySelectorAll('.template-option').forEach(function(o) { o.classList.remove('active'); });
        this.classList.add('active');
        // Update hidden template field
        var templateInput = form.querySelector('input[name="template"]');
        if (templateInput) templateInput.value = tpl;
        // Add switch_template flag and submit to reload with new template
        var switchFlag = form.querySelector('input[name="switch_template"]');
        if (!switchFlag) {
          switchFlag = document.createElement('input');
          switchFlag.type = 'hidden';
          switchFlag.name = 'switch_template';
          form.appendChild(switchFlag);
        }
        switchFlag.value = tpl;
        form.submit();
      });
    });

    // Help toggle buttons
    document.querySelectorAll('.btn-help').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        var fieldName = this.getAttribute('data-field');
        var helpDiv = document.getElementById('help-' + fieldName);
        if (helpDiv) {
          if (helpDiv.style.display === 'none') {
            helpDiv.style.display = 'block';
          } else {
            helpDiv.style.display = 'none';
          }
        }
      });
    });
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
/* Font size overrides (match AtoM) */
.accordion-body label { font-size: 14px !important; font-weight: 500 !important; }
.field-input input, .field-input select, .field-input textarea { font-size: 13px !important; }
.accordion-button { font-size: 14px !important; }
.cco-chapter { font-size: 11px !important; }
.category-description { font-size: 12px !important; color: #666; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
.help-text, .field-help { font-size: 12px !important; }
.badge { font-size: 10px !important; }
.cco-field label { font-size: 14px !important; }

/* Sidebar */
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

/* Accordion */
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

/* CCO Field Structure */
.cco-field { margin-bottom: 16px; border: 1px solid #e9ecef; border-radius: 6px; padding: 0; background: #fff; }
.cco-field.level-required { border-left: 3px solid #e74c3c; }
.cco-field.level-optional { border-left: 3px solid #95a5a6; }

.cco-field .field-header { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; padding: 8px 12px; background: #f8f9fa; border-bottom: 1px solid #e9ecef; border-radius: 6px 6px 0 0; }
.cco-field .field-header label { margin-bottom: 0; font-weight: 600; font-size: 13px; color: #333; flex-shrink: 0; }
.cco-field .field-header label .required { color: #e74c3c; font-weight: 700; }

.cco-field .field-badges { display: inline-flex; align-items: center; gap: 4px; margin-left: auto; flex-shrink: 0; }
.cco-field .field-badges .badge { display: inline-flex; align-items: center; padding: 2px 7px; border-radius: 3px; font-size: 10px; font-weight: 600; line-height: 1.4; white-space: nowrap; }
.cco-field .field-badges .badge-required { background: #e74c3c; color: #fff; }
.cco-field .field-badges .badge-cco { background: var(--ahg-primary, #005837); color: #fff; }
.cco-field .field-badges .badge-vocab { background: #3498db; color: #fff; }
.cco-field .field-badges .badge-vocab i { margin-right: 3px; font-size: 9px; }

.cco-field .btn-help { background: none; border: 1px solid #ccc; border-radius: 50%; width: 22px; height: 22px; padding: 0; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; color: #999; font-size: 12px; flex-shrink: 0; transition: all 0.2s; }
.cco-field .btn-help:hover { background: var(--ahg-primary, #005837); color: #fff; border-color: var(--ahg-primary, #005837); }

.cco-field .field-input { padding: 10px 12px; }
.cco-field .field-input .form-control,
.cco-field .field-input .form-select { font-size: 13px; }

.cco-field .field-help { padding: 8px 12px; background: #fffde7; border-top: 1px solid #e9ecef; border-radius: 0 0 6px 6px; }
.cco-field .field-help .help-content { font-size: 12px; color: #666; }
.cco-field .field-help .help-content .help-text { margin: 0; line-height: 1.5; }
</style>
@endpush
@endsection