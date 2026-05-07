{{--
  ============================================================================
  Heratio archival-record show template — MODS (Metadata Object Description
  Schema, Library of Congress; current 3.8).

  Issue #98 Phase 2 — uses ahg_io_mods sidecar for MODS-specific elements
  not stored in Heratio's information_object_i18n schema (typeOfResource,
  genre, physicalDescription sub-elements, abstract, classification,
  recordInfo). Honours the new mods_*_area element_visibility flags
  seeded by install_template_extensions.sql.

  MODS is library-oriented; the layout is flatter than the archival-area
  conventions of ISAD/DACS/RAD. Elements appear in the spec's natural
  order with each top-level element as its own section.

  Field mapping:

    MODS element                          | Source
    --------------------------------------|------------------------------------
    titleInfo                             | $io->title (+ $io->alternate_title)
    name                                  | $creators
    typeOfResource                        | $modsExt->type_of_resource (sidecar)
    genre                                 | $modsExt->genre (sidecar)
    originInfo (place / date / edition)   | $events + $io->edition
    language (of the resource)            | $materialLanguages + $materialScripts
    physicalDescription
      .form                               | $modsExt->physical_form (sidecar)
      .extent                             | $modsExt->physical_extent (sidecar)
                                          |   OR $io->extent_and_medium fallback
      .internetMediaType                  | $modsExt->internet_media_type (sidecar)
      .digitalOrigin                      | $modsExt->digital_origin (sidecar)
    abstract                              | $modsExt->abstract (sidecar)
                                          |   OR $io->scope_and_content fallback
    tableOfContents                       | $modsExt->table_of_contents (sidecar)
    targetAudience                        | $modsExt->target_audience (sidecar)
    subject                               | $subjects + $places + $nameAccessPoints + $genres
    classification                        | $modsExt->classification_authority +
                                          | $modsExt->classification_value (sidecar)
    relatedItem                           | $relatedMaterialDescriptions (collection)
    identifier                            | $io->identifier + $alternativeIdentifiers
    location                              | $repository + DOs (where in repo)
    accessCondition                       | $io->access_conditions
    recordInfo
      .recordContentSource                | $modsExt->record_content_source (sidecar)
      .recordOrigin                       | $modsExt->record_origin (sidecar)
      .languageOfCataloging               | $modsExt->language_of_cataloging (sidecar)
                                          |   OR $languagesOfDescription fallback
      .recordCreationDate                 | $io->created_at (if column exists)
      .recordChangeDate                   | $io->updated_at

  Sidecar table:
    ahg_io_mods(information_object_id, type_of_resource, genre, physical_form,
                physical_extent, internet_media_type, digital_origin,
                abstract, table_of_contents, target_audience,
                classification_authority, classification_value,
                record_content_source, record_origin, language_of_cataloging,
                timestamps).

  Reviewer notes:
    - MODS doesn't have an "archival history" / "appraisal" notion — those
      Heratio fields are surfaced under <note> if visible at all. This
      draft elides them (operator can switch to ISAD/DACS/RAD if needed).
    - subject elements in MODS distinguish topic / geographic / temporal /
      name / titleInfo. Heratio's $subjects (plain) + $places + $nameAccessPoints
      map onto the first three categories cleanly; temporal subject
      access is rare and not modelled.
    - relatedItem in MODS uses a `type` attribute (preceding / succeeding /
      original / host / etc.). Heratio's $relatedMaterialDescriptions is
      free-text without a typed relationship — render unlabelled.
--}}

@extends('theme::layouts.3col')

@section('title', ($io->title ?? config('app.ui_label_informationobject', 'Archival description')))
@section('body-class', 'view informationobject template-mods')

@section('sidebar')
  @include('ahg-menu-manage::_static-pages-menu')
  @include('ahg-information-object-manage::partials._treeview', ['io' => $io])
@endsection

@section('title-block')
  <div class="d-flex flex-column">
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge bg-secondary">MODS</span>
      <span class="text-muted small">{{ __('Described per Metadata Object Description Schema (LC)') }}</span>
    </div>
    <h1 class="h3 mb-0">{{ $io->title ?? __('Untitled resource') }}</h1>
    @if($modsExt && $modsExt->type_of_resource)
      <small class="text-muted">{{ $modsExt->type_of_resource }}@if($modsExt->genre) — {{ $modsExt->genre }}@endif</small>
    @elseif($levelName)
      <small class="text-muted">{{ $levelName }}</small>
    @endif
  </div>
@endsection

@section('before-content')
  @include('ahg-information-object-manage::partials._redaction-overlay')
  @include('ahg-information-object-manage::partials._digital-object-viewer')
@endsection


@section('content')

  @include('ahg-ric::_view-switch', ['standard' => 'MODS'])

  @php
    $translationSources = \AhgTranslation\Helpers\TranslationProvenance::forRecord((int) $io->id, app()->getLocale());
  @endphp

  {{-- ========== <titleInfo> ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_titleinfo_area') && ($io->title || ($io->alternate_title ?? null)))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('titleInfo') }}
      </h2>
      @if($io->title)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('title') }}</h3>
          <div class="col-9 p-2">{{ $io->title }}@include('ahg-translation::components.badge', ['source' => $translationSources['title'] ?? null])</div>
        </div>
      @endif
      @if($io->alternate_title ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('alternative title') }}</h3>
          <div class="col-9 p-2">{{ $io->alternate_title }}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ========== <name> (creators) ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_name_area') && isset($creators) && $creators->isNotEmpty())
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('name') }}
      </h2>
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('namePart / role') }}</h3>
        <div class="col-9 p-2">
          <ul class="m-0 ms-1 ps-3">
            @foreach($creators as $creator)
              <li>
                <a href="{{ route('actor.show', $creator->slug ?? '') }}">{{ $creator->authorized_form_of_name ?? '' }}</a>
                @if($creator->dates_of_existence ?? null)<span class="text-muted small"> ({{ $creator->dates_of_existence }})</span>@endif
                <span class="text-muted small">[creator]</span>
              </li>
            @endforeach
          </ul>
        </div>
      </div>
    </section>
  @endif

  {{-- ========== <typeOfResource> ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_type_of_resource_area') && $modsExt && $modsExt->type_of_resource)
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('typeOfResource') }}
      </h2>
      <div class="field text-break row g-0">
        <div class="col-12 p-2">{{ $modsExt->type_of_resource }}</div>
      </div>
    </section>
  @endif

  {{-- ========== <genre> ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_genre_area') && $modsExt && $modsExt->genre)
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('genre') }}
      </h2>
      <div class="field text-break row g-0">
        <div class="col-12 p-2">{{ $modsExt->genre }}</div>
      </div>
    </section>
  @endif

  {{-- ========== <originInfo> ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_origininfo_area'))
    @php
      $hasOriginInfo = (isset($events) && $events->isNotEmpty()) || ($io->edition ?? null);
    @endphp
    @if($hasOriginInfo)
      <section class="border-bottom">
        <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
          {{ __('originInfo') }}
        </h2>
        @if(isset($events) && $events->isNotEmpty())
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('dateCreated / dateIssued') }}</h3>
            <div class="col-9 p-2">
              <ul class="m-0 ms-1 ps-3">
                @foreach($events as $event)
                  <li>
                    {{ $event->date_display ?? '' }}
                    @if($event->start_date || $event->end_date)
                      @if(!$event->date_display) ({{ $event->start_date ?? '?' }} – {{ $event->end_date ?? '?' }})@endif
                    @endif
                    @if($event->type_id && isset($eventTypeNames[$event->type_id]))
                      <span class="text-muted small">— {{ $eventTypeNames[$event->type_id] }}</span>
                    @endif
                  </li>
                @endforeach
              </ul>
            </div>
          </div>
        @endif
        @if($io->edition ?? null)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('edition') }}</h3>
            <div class="col-9 p-2">{{ $io->edition }}</div>
          </div>
        @endif
      </section>
    @endif
  @endif

  {{-- ========== <language> (of the resource) ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_language_area') && isset($materialLanguages) && (is_array($materialLanguages) ? count($materialLanguages) > 0 : $materialLanguages->isNotEmpty()))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('language') }}
      </h2>
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('languageTerm') }}</h3>
        <div class="col-9 p-2">
          {{ is_array($materialLanguages) ? implode(', ', $materialLanguages) : $materialLanguages->implode(', ') }}
          @if(isset($materialScripts) && (is_array($materialScripts) ? count($materialScripts) > 0 : $materialScripts->isNotEmpty()))
            <br><span class="text-muted small">{{ __('scriptTerm:') }} {{ is_array($materialScripts) ? implode(', ', $materialScripts) : $materialScripts->implode(', ') }}</span>
          @endif
        </div>
      </div>
    </section>
  @endif

  {{-- ========== <physicalDescription> ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_physicaldescription_area'))
    @php
      $hasPhys = ($modsExt && ($modsExt->physical_form || $modsExt->physical_extent || $modsExt->internet_media_type || $modsExt->digital_origin)) || $io->extent_and_medium || $io->physical_characteristics;
    @endphp
    @if($hasPhys)
      <section class="border-bottom">
        <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
          {{ __('physicalDescription') }}
        </h2>
        @if($modsExt && $modsExt->physical_form)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('form') }}</h3>
            <div class="col-9 p-2">{{ $modsExt->physical_form }}</div>
          </div>
        @endif
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('extent') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($modsExt->physical_extent ?? $io->extent_and_medium ?? '')) !!}</div>
        </div>
        @if($modsExt && $modsExt->internet_media_type)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('internetMediaType') }}</h3>
            <div class="col-9 p-2">{{ $modsExt->internet_media_type }}</div>
          </div>
        @endif
        @if($modsExt && $modsExt->digital_origin)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('digitalOrigin') }}</h3>
            <div class="col-9 p-2">{{ $modsExt->digital_origin }}</div>
          </div>
        @endif
        @if($io->physical_characteristics)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('note (physical)') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->physical_characteristics)) !!}</div>
          </div>
        @endif
      </section>
    @endif
  @endif

  {{-- ========== <abstract> ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_abstract_area'))
    @php $abstractText = ($modsExt->abstract ?? null) ?: $io->scope_and_content; @endphp
    @if($abstractText)
      <section class="border-bottom">
        <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
          {{ __('abstract') }}
        </h2>
        <div class="field text-break row g-0">
          <div class="col-12 p-2">{!! nl2br(e(\AhgInformationObjectManage\Services\PiiMaskingService::mask((int)$io->id, $abstractText))) !!}</div>
        </div>
      </section>
    @endif
  @endif

  {{-- ========== <tableOfContents> ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_table_of_contents_area') && $modsExt && $modsExt->table_of_contents)
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('tableOfContents') }}
      </h2>
      <div class="field text-break row g-0">
        <div class="col-12 p-2">{!! nl2br(e($modsExt->table_of_contents)) !!}</div>
      </div>
    </section>
  @endif

  {{-- ========== <subject> — uses access-points partial which already
       structures by topic / geographic / name / genre. ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_subject_area'))
    @include('ahg-information-object-manage::partials.sections.access-points-area')
  @endif

  {{-- ========== <classification> ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_classification_area') && $modsExt && $modsExt->classification_value)
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('classification') }}
      </h2>
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">
          {{ $modsExt->classification_authority ?: __('classification') }}
        </h3>
        <div class="col-9 p-2">{{ $modsExt->classification_value }}</div>
      </div>
    </section>
  @endif

  {{-- ========== <relatedItem> ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_related_item_area') && ($io->related_units_of_description || $io->location_of_originals || $io->location_of_copies))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('relatedItem') }}
      </h2>
      @if($io->related_units_of_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('related') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->related_units_of_description)) !!}</div>
        </div>
      @endif
      @if($io->location_of_originals)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('original') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->location_of_originals)) !!}</div>
        </div>
      @endif
      @if($io->location_of_copies)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('otherFormat') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->location_of_copies)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ========== <identifier> ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_identifier_area') && ($io->identifier || (isset($alternativeIdentifiers) && (is_countable($alternativeIdentifiers) ? count($alternativeIdentifiers) > 0 : !empty($alternativeIdentifiers)))))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('identifier') }}
      </h2>
      @if($io->identifier)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('local') }}</h3>
          <div class="col-9 p-2">{{ $io->identifier }}</div>
        </div>
      @endif
      @if(isset($alternativeIdentifiers) && (is_countable($alternativeIdentifiers) ? count($alternativeIdentifiers) > 0 : !empty($alternativeIdentifiers)))
        @foreach($alternativeIdentifiers as $altId)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ $altId->label ?? __('alternate') }}</h3>
            <div class="col-9 p-2">{{ $altId->value ?? $altId->name ?? '' }}</div>
          </div>
        @endforeach
      @endif
    </section>
  @endif

  {{-- ========== <location> ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_location_area') && $repository)
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('location') }}
      </h2>
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('physicalLocation') }}</h3>
        <div class="col-9 p-2">
          <a href="{{ route('repository.show', $repository->slug ?? '') }}">{{ $repository->authorized_form_of_name ?? $repository->name ?? '' }}</a>
          @if($repository->contact_information ?? null)
            <div class="small text-muted mt-1">{!! nl2br(e($repository->contact_information)) !!}</div>
          @endif
        </div>
      </div>
    </section>
  @endif

  {{-- ========== <accessCondition> ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_access_condition_area') && ($io->access_conditions || $io->reproduction_conditions))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('accessCondition') }}
      </h2>
      @if($io->access_conditions)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('restrictionOnAccess') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->access_conditions)) !!}</div>
        </div>
      @endif
      @if($io->reproduction_conditions)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('useAndReproduction') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->reproduction_conditions)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ========== <recordInfo> (auth-only) ========== --}}
  @auth
    @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('mods_record_info_area'))
      @php
        $hasRecInfo = ($modsExt && ($modsExt->record_content_source || $modsExt->record_origin || $modsExt->language_of_cataloging))
                   || ($io->updated_at)
                   || (isset($languagesOfDescription) && (is_array($languagesOfDescription) ? count($languagesOfDescription) > 0 : $languagesOfDescription->isNotEmpty()));
      @endphp
      @if($hasRecInfo)
        <section class="border-bottom">
          <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
            {{ __('recordInfo') }}
          </h2>
          @if($modsExt && $modsExt->record_content_source)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('recordContentSource') }}</h3>
              <div class="col-9 p-2">{{ $modsExt->record_content_source }}</div>
            </div>
          @endif
          @if($modsExt && $modsExt->record_origin)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('recordOrigin') }}</h3>
              <div class="col-9 p-2">{!! nl2br(e($modsExt->record_origin)) !!}</div>
            </div>
          @endif
          @if($io->updated_at)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('recordChangeDate') }}</h3>
              <div class="col-9 p-2">{{ \Carbon\Carbon::parse($io->updated_at)->format('Y-m-d') }}</div>
            </div>
          @endif
          @php
            $catLang = ($modsExt->language_of_cataloging ?? null) ?: (isset($languagesOfDescription) ? (is_array($languagesOfDescription) ? implode(', ', $languagesOfDescription) : $languagesOfDescription->implode(', ')) : null);
          @endphp
          @if($catLang)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('languageOfCataloging') }}</h3>
              <div class="col-9 p-2">{{ $catLang }}</div>
            </div>
          @endif
          @if($io->rules)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('descriptionStandard') }}</h3>
              <div class="col-9 p-2">{!! nl2br(e($io->rules)) !!}</div>
            </div>
          @endif
        </section>
      @endif
    @endif
  @endauth

  {{-- Universal sections (not part of MODS but page features users expect). --}}
  @include('ahg-information-object-manage::partials.sections.security-classification-area')
  @include('ahg-information-object-manage::partials.sections.accession-area')
  @include('ahg-information-object-manage::partials.sections.museum-metadata')

@endsection
