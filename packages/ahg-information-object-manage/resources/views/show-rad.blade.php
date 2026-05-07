{{--
  ============================================================================
  Heratio archival-record show template — RAD (Rules for Archival
  Description, Canadian Council of Archives, 1990 + 2008 revisions).

  Issue #98 Phase 2 — uses ahg_io_rad sidecar for RAD-specific elements
  (GMD, Statement of Responsibility, Specific Material Designation,
  Publisher's Series, Standard Number) not stored in Heratio's
  information_object_i18n schema. Honours the AtoM-seeded element_visibility
  flags (rad_title_responsibility_area / rad_edition_area /
  rad_material_specific_details_area / rad_dates_of_creation_area /
  rad_physical_description_area / rad_publishers_series_area /
  rad_archival_description_area / rad_notes_area / rad_standard_number_area /
  rad_access_points_area / rad_description_control_area — see
  packages/ahg-settings/resources/views/visible-elements.blade.php).

  Field mapping:

    RAD area / element                    | Source
    --------------------------------------|------------------------------------
    1.1A Reference number                 | $io->identifier
    1.1B General Material Designation     | $radExt->general_material_designation (sidecar)
    1.1C Title proper                     | $io->title
    1.1F Statement of responsibility      | $radExt->statement_of_responsibility (sidecar)
    1.2  Edition area                     | $io->edition
    1.3  Class of material specific       | $radExt->specific_material_designation (sidecar)
    1.4  Date(s) of creation              | $events
    1.5A Extent                           | $io->extent_and_medium
    1.5B Specific material designation    | $radExt->specific_material_designation (sidecar) cross-ref
    1.5D Other physical details           | $io->physical_characteristics
    1.6A Publisher's series — title       | $radExt->publisher_series_title (sidecar)
    1.6B Publisher's series — statement   | $radExt->publisher_series_statement (sidecar)
    1.6E Publisher's series — ISSN        | $radExt->publisher_series_issn (sidecar)
    1.6F Publisher's series — numbering   | $radExt->publisher_series_numbering (sidecar)
    1.7B1 Custodial history               | $io->archival_history
    1.7B2 Scope and content               | $io->scope_and_content
    1.7C  Notes (general)                 | $notes (type_id=125)
    1.7D  Conservation note               | (no Heratio field — flagged)
    1.7D  Rights note                     | (no Heratio field — flagged; rights area handles separately)
    1.8   Standard number area            | $radExt->standard_number_type +
                                          | $radExt->standard_number_value (sidecar)
    Access points (subjects/places/etc.)  | reuses access-points-area partial
    Description control                   | $io->rules / $io->sources / $io->revision_history /
                                          | $descriptionStatusName / etc.

  Sidecar table:
    ahg_io_rad(information_object_id, general_material_designation,
               statement_of_responsibility, specific_material_designation,
               publisher_series_*, standard_number_type, standard_number_value,
               timestamps).

  Reviewer note:
    - RAD's "Class of Material Specific Details Area" (1.3) is a parent
      heading for material-class extensions (cartographic notation,
      published/unpublished hierarchy, sound-recording playback speed,
      etc.). Heratio doesn't model these granular sub-elements; the
      sidecar's specific_material_designation acts as a free-text
      catch-all. A future standalone module per material class is the
      proper fix.
    - RAD distinguishes Custodial History (1.7B1) from Bio/Admin History
      (1.7B6); Heratio collapses both into archival_history. We render
      it under 1.7B1 with a comment flagging the dual use.
--}}

@extends('theme::layouts.3col')

@section('title', ($io->title ?? config('app.ui_label_informationobject', 'Archival description')))
@section('body-class', 'view informationobject template-rad')

@section('sidebar')
  @include('ahg-menu-manage::_static-pages-menu')
  @include('ahg-information-object-manage::partials._treeview', ['io' => $io])
@endsection

@section('title-block')
  <div class="d-flex flex-column">
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge bg-secondary">RAD</span>
      <span class="text-muted small">{{ __('Described per Rules for Archival Description (CCA)') }}</span>
    </div>
    <h1 class="h3 mb-0">
      {{ $io->title ?? __('Untitled archival material') }}
      @if($radExt && $radExt->general_material_designation)
        <span class="text-muted h5 ms-2">{{ $radExt->general_material_designation }}</span>
      @endif
    </h1>
    @if($radExt && $radExt->statement_of_responsibility)
      <div class="text-muted small">{{ $radExt->statement_of_responsibility }}</div>
    @endif
    @if($levelName)<small class="text-muted">{{ $levelName }}</small>@endif
  </div>
@endsection

@section('before-content')
  @include('ahg-information-object-manage::partials._redaction-overlay')
  @include('ahg-information-object-manage::partials._digital-object-viewer')
@endsection


@section('content')

  @include('ahg-ric::_view-switch', ['standard' => 'RAD'])

  @php
    $translationSources = \AhgTranslation\Helpers\TranslationProvenance::forRecord((int) $io->id, app()->getLocale());
  @endphp

  {{-- ========== 1.1 Title and Statement of Responsibility Area ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('rad_title_responsibility_area'))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('1.1 Title and Statement of Responsibility Area') }}
      </h2>
      @if($io->identifier)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.1A Reference number') }}</h3>
          <div class="col-9 p-2">{{ $io->identifier }}</div>
        </div>
      @endif
      @if($radExt && $radExt->general_material_designation)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.1B General material designation') }}</h3>
          <div class="col-9 p-2">{{ $radExt->general_material_designation }}</div>
        </div>
      @endif
      @if($io->title)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.1C Title proper') }}</h3>
          <div class="col-9 p-2">{{ $io->title }}@include('ahg-translation::components.badge', ['source' => $translationSources['title'] ?? null])</div>
        </div>
      @endif
      @if($io->alternate_title ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.1E Parallel title / variant') }}</h3>
          <div class="col-9 p-2">{{ $io->alternate_title }}</div>
        </div>
      @endif
      @if($radExt && $radExt->statement_of_responsibility)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.1F Statement of responsibility') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($radExt->statement_of_responsibility)) !!}</div>
        </div>
      @elseif(isset($creators) && $creators->isNotEmpty())
        {{-- Fall back to controlled-vocab creators when free-text statement
             of responsibility hasn't been authored. --}}
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.1F Statement of responsibility') }}<span class="d-block small text-muted">({{ __('derived from creators') }})</span></h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($creators as $creator)
                <li><a href="{{ route('actor.show', $creator->slug ?? '') }}">{{ $creator->authorized_form_of_name ?? '' }}</a>@if($creator->dates_of_existence ?? null)<span class="text-muted small"> ({{ $creator->dates_of_existence }})</span>@endif</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif
    </section>
  @endif

  {{-- ========== 1.2 Edition Area ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('rad_edition_area') && ($io->edition ?? null))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('1.2 Edition Area') }}
      </h2>
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.2B Edition statement') }}</h3>
        <div class="col-9 p-2">{{ $io->edition }}</div>
      </div>
    </section>
  @endif

  {{-- ========== 1.3 Class of Material Specific Details Area ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('rad_material_specific_details_area') && $radExt && $radExt->specific_material_designation)
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('1.3 Class of Material Specific Details Area') }}
      </h2>
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.3 Specific material designation') }}</h3>
        <div class="col-9 p-2">{{ $radExt->specific_material_designation }}</div>
      </div>
    </section>
  @endif

  {{-- ========== 1.4 Dates of Creation Area ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('rad_dates_of_creation_area') && isset($events) && $events->isNotEmpty())
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('1.4 Dates of Creation Area') }}
      </h2>
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.4 Dates of creation') }}</h3>
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
    </section>
  @endif

  {{-- ========== 1.5 Physical Description Area ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('rad_physical_description_area') && ($io->extent_and_medium || $io->physical_characteristics))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('1.5 Physical Description Area') }}
      </h2>
      @if($io->extent_and_medium)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.5A Extent of descriptive unit') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->extent_and_medium)) !!}</div>
        </div>
      @endif
      @if($io->physical_characteristics)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.5D Other physical details') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->physical_characteristics)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ========== 1.6 Publisher's Series Area ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('rad_publishers_series_area') && $radExt && ($radExt->publisher_series_title || $radExt->publisher_series_statement || $radExt->publisher_series_issn || $radExt->publisher_series_numbering))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __("1.6 Publisher's Series Area") }}
      </h2>
      @if($radExt->publisher_series_title)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.6A Title proper of series') }}</h3>
          <div class="col-9 p-2">{{ $radExt->publisher_series_title }}</div>
        </div>
      @endif
      @if($radExt->publisher_series_statement)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.6B Statement of responsibility relating to series') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($radExt->publisher_series_statement)) !!}</div>
        </div>
      @endif
      @if($radExt->publisher_series_issn)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.6E ISSN of series') }}</h3>
          <div class="col-9 p-2">{{ $radExt->publisher_series_issn }}</div>
        </div>
      @endif
      @if($radExt->publisher_series_numbering)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.6F Numbering within series') }}</h3>
          <div class="col-9 p-2">{{ $radExt->publisher_series_numbering }}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ========== 1.7 Archival Description Area ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('rad_archival_description_area'))
    @php
      $hasArch = $io->archival_history || $io->scope_and_content || $io->arrangement
              || $io->access_conditions || $io->reproduction_conditions
              || $io->location_of_originals || $io->location_of_copies
              || $io->finding_aids || $io->related_units_of_description
              || $io->acquisition || $io->appraisal || $io->accruals;
    @endphp
    @if($hasArch)
      <section class="border-bottom">
        <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
          {{ __('1.7 Archival Description Area') }}
        </h2>
        @if($io->archival_history)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.7B1 Custodial history / biographical sketch') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e(\AhgInformationObjectManage\Services\PiiMaskingService::mask((int)$io->id, $io->archival_history))) !!}@include('ahg-translation::components.badge', ['source' => $translationSources['archival_history'] ?? null])</div>
          </div>
        @endif
        @if($io->scope_and_content)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.7B2 Scope and content') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e(\AhgInformationObjectManage\Services\PiiMaskingService::mask((int)$io->id, $io->scope_and_content))) !!}@include('ahg-translation::components.badge', ['source' => $translationSources['scope_and_content'] ?? null])</div>
          </div>
        @endif
        @if($io->arrangement)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.7B3 Arrangement') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->arrangement)) !!}</div>
          </div>
        @endif
        @if($io->access_conditions)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.7B4 Restrictions on access') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->access_conditions)) !!}</div>
          </div>
        @endif
        @if($io->reproduction_conditions)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.7B5 Terms governing use, reproduction, publication') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->reproduction_conditions)) !!}</div>
          </div>
        @endif
        @if($io->finding_aids)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.7B7 Finding aids') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->finding_aids)) !!}</div>
          </div>
        @endif
        @if($io->location_of_originals)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.7B8 Location of originals') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->location_of_originals)) !!}</div>
          </div>
        @endif
        @if($io->location_of_copies)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.7B9 Availability of other formats') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->location_of_copies)) !!}</div>
          </div>
        @endif
        @if($io->related_units_of_description)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.7B11 Associated material') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->related_units_of_description)) !!}</div>
          </div>
        @endif
        @if($io->acquisition)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.7C Immediate source of acquisition') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->acquisition)) !!}</div>
          </div>
        @endif
        @if($io->appraisal)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.7D Appraisal, destruction and scheduling') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->appraisal)) !!}</div>
          </div>
        @endif
        @if($io->accruals)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('1.7E Accruals') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->accruals)) !!}</div>
          </div>
        @endif
      </section>
    @endif
  @endif

  {{-- ========== 1.7C Notes (RAD General notes — reuse ISAD partial) ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('rad_notes_area'))
    @include('ahg-information-object-manage::partials.sections.notes-area')
  @endif

  {{-- ========== 1.8 Standard Number Area ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('rad_standard_number_area') && $radExt && $radExt->standard_number_value)
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('1.8 Standard Number Area') }}
      </h2>
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ $radExt->standard_number_type ?: __('1.8 Standard number') }}</h3>
        <div class="col-9 p-2">{{ $radExt->standard_number_value }}</div>
      </div>
    </section>
  @endif

  {{-- ========== Access Points (reuses ISAD partial) ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('rad_access_points_area'))
    @include('ahg-information-object-manage::partials.sections.access-points-area')
  @endif

  {{-- ========== Description Control (auth-only) ========== --}}
  @auth
    @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('rad_description_control_area') && ($io->rules || $io->sources || $io->revision_history || $descriptionStatusName))
      <section class="border-bottom">
        <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
          {{ __('Description Control Area') }}
        </h2>
        @if($io->rules)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rules or conventions') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->rules)) !!}</div>
          </div>
        @endif
        @if($descriptionStatusName)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Status') }}</h3>
            <div class="col-9 p-2">{{ $descriptionStatusName }}</div>
          </div>
        @endif
        @if($descriptionDetailName ?? null)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Level of detail') }}</h3>
            <div class="col-9 p-2">{{ $descriptionDetailName }}</div>
          </div>
        @endif
        @if(isset($languagesOfDescription) && (is_array($languagesOfDescription) ? count($languagesOfDescription) > 0 : $languagesOfDescription->isNotEmpty()))
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Language of description') }}</h3>
            <div class="col-9 p-2">{{ is_array($languagesOfDescription) ? implode(', ', $languagesOfDescription) : $languagesOfDescription->implode(', ') }}</div>
          </div>
        @endif
        @if($io->sources)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Sources used') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->sources)) !!}</div>
          </div>
        @endif
        @if($io->revision_history)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Revision history') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->revision_history)) !!}</div>
          </div>
        @endif
        @if($io->updated_at)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Last updated') }}</h3>
            <div class="col-9 p-2">{{ \Carbon\Carbon::parse($io->updated_at)->format('j F Y') }}</div>
          </div>
        @endif
      </section>
    @endif
  @endauth

  {{-- Universal sections (not part of RAD but page features users expect). --}}
  @include('ahg-information-object-manage::partials.sections.security-classification-area')
  @include('ahg-information-object-manage::partials.sections.accession-area')
  @include('ahg-information-object-manage::partials.sections.museum-metadata')

@endsection
