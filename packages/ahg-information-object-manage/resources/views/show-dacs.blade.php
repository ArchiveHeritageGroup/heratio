{{--
  ============================================================================
  Heratio archival-record show template — DACS (Describing Archives: A
  Content Standard, 2nd ed., Society of American Archivists).

  Issue #98 Phase 2 — uses ahg_io_dacs sidecar for DACS-specific elements
  not stored in Heratio's information_object_i18n schema, and honours the
  AtoM-seeded element_visibility flags (dacs_identity_area / dacs_content_area
  / dacs_conditions_of_access_area / dacs_acquisition_area / dacs_materials_area
  / dacs_notes_area / dacs_control_area / dacs_access_points_area /
  dacs_physical_access — see packages/ahg-settings/resources/views/visible-elements.blade.php).

  Field mapping:

    DACS element                          | Source
    --------------------------------------|------------------------------------
    2.1 Reference Code                    | $io->identifier
    2.2 Name and Location of Repository   | $repository (joined record)
    2.3 Title                             | $io->title
    2.4 Date                              | $events
    2.5 Extent                            | $io->extent_and_medium
    2.6 Name of Creator(s)                | $creators
    2.7 Administrative/Biographical Hist. | $io->archival_history
    3.1 Scope and Content                 | $io->scope_and_content
    3.2 System of Arrangement             | $io->arrangement
    3.3 Accruals                          | $io->accruals  (also 5.4)
    4.1 Conditions Governing Access       | $io->access_conditions
    4.2 Physical Access                   | $dacsExt->physical_access_note
    4.3 Technical Access                  | $dacsExt->technical_access_note
    4.4 Reproduction and Use              | $io->reproduction_conditions
    4.5 Languages and Scripts             | $materialLanguages + $materialScripts
    4.6 Finding Aids                      | $io->finding_aids
    5.1 Custodial History                 | $io->archival_history (cross-ref 2.7)
    5.2 Immediate Source of Acquisition   | $io->acquisition
    5.3 Appraisal, Destruction, Sched.    | $io->appraisal
    5.4 Accruals                          | $io->accruals (cross-ref 3.3)
    6.1 Existence/Location of Originals   | $io->location_of_originals
    6.2 Existence/Location of Copies      | $io->location_of_copies
    6.3 Related Archival Materials        | $io->related_units_of_description
    6.4 Publication Note                  | $dacsExt->publication_note
    7.1 Notes                             | $notes (type_id=125)
    8.x Description Control               | $io->rules / $io->sources / $io->revision_history
                                          | / $descriptionStatusName / $sourceLanguageName

  Sidecar table (Phase 2 schema):
    ahg_io_dacs(information_object_id, physical_access_note,
                technical_access_note, publication_note, timestamps).
    Auto-installed by AhgInformationObjectManageServiceProvider::ensureTemplateExtensions().

  Element visibility (existing AtoM-seeded flags + one per chapter):
    dacs_identity_area              -> Chapter 2
    dacs_content_area               -> Chapter 3
    dacs_conditions_of_access_area  -> Chapter 4
    dacs_physical_access            -> 4.2 / 4.3 sub-heading
    dacs_acquisition_area           -> Chapter 5
    dacs_materials_area             -> Chapter 6
    dacs_notes_area                 -> Chapter 7
    dacs_access_points_area         -> Access points
    dacs_control_area               -> Chapter 8

  Notes for reviewers:
    - 4.5 Languages and Scripts uses $materialLanguages (the language(s)
      of the material), NOT $languages (which is the language of the
      descriptive metadata itself).
    - "Administrative/Biographical History" appears in DACS Chapter 2;
      the same Heratio field doubles as 5.1 Custodial History. Rendered
      twice with cross-references rather than splitting the column.
--}}

@extends('theme::layouts.3col')

@section('title', ($io->title ?? config('app.ui_label_informationobject', 'Archival description')))
@section('body-class', 'view informationobject template-dacs')

@section('sidebar')
  @include('ahg-menu-manage::_static-pages-menu')
  @include('ahg-information-object-manage::partials._treeview', ['io' => $io])
@endsection

@section('title-block')
  <div class="d-flex flex-column">
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge bg-secondary">DACS</span>
      <span class="text-muted small">{{ __('Described per Describing Archives: A Content Standard (SAA)') }}</span>
    </div>
    <h1 class="h3 mb-0">{{ $io->title ?? __('Untitled archival material') }}</h1>
    @if($levelName)<small class="text-muted">{{ $levelName }}</small>@endif
  </div>
@endsection

@section('before-content')
  @include('ahg-information-object-manage::partials._redaction-overlay')
  @include('ahg-information-object-manage::partials._digital-object-viewer')
@endsection


@section('content')

  @include('ahg-ric::_view-switch', ['standard' => 'DACS'])

  @php
    $translationSources = \AhgTranslation\Helpers\TranslationProvenance::forRecord((int) $io->id, app()->getLocale());
  @endphp

  {{-- ========== Chapter 2 — Identity Elements ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('dacs_identity_area'))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('Chapter 2 — Identity Elements') }}
      </h2>

      @if($io->identifier)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('2.1 Reference code') }}</h3>
          <div class="col-9 p-2">{{ $io->identifier }}</div>
        </div>
      @endif

      @if($repository)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('2.2 Name and location of repository') }}</h3>
          <div class="col-9 p-2">
            <a href="{{ route('repository.show', $repository->slug ?? '') }}">{{ $repository->authorized_form_of_name ?? $repository->name ?? '' }}</a>
            @if($repository->contact_information ?? null)
              <div class="small text-muted mt-1">{!! nl2br(e($repository->contact_information)) !!}</div>
            @endif
          </div>
        </div>
      @endif

      @if($io->title)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('2.3 Title') }}</h3>
          <div class="col-9 p-2">{{ $io->title }}@include('ahg-translation::components.badge', ['source' => $translationSources['title'] ?? null])</div>
        </div>
      @endif

      @if(isset($events) && $events->isNotEmpty())
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('2.4 Date') }}</h3>
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

      @if($io->extent_and_medium)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('2.5 Extent') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->extent_and_medium)) !!}</div>
        </div>
      @endif

      @if(isset($creators) && $creators->isNotEmpty())
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('2.6 Name of creator(s)') }}</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($creators as $creator)
                <li>
                  <a href="{{ route('actor.show', $creator->slug ?? '') }}">{{ $creator->authorized_form_of_name ?? '' }}</a>
                  @if($creator->dates_of_existence ?? null)<span class="text-muted small">({{ $creator->dates_of_existence }})</span>@endif
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @if($io->archival_history)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('2.7 Administrative / biographical history') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e(\AhgInformationObjectManage\Services\PiiMaskingService::mask((int)$io->id, $io->archival_history))) !!}@include('ahg-translation::components.badge', ['source' => $translationSources['archival_history'] ?? null])</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ========== Chapter 3 — Content and Structure ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('dacs_content_area') && ($io->scope_and_content || $io->arrangement || $io->accruals))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('Chapter 3 — Content and Structure') }}
      </h2>
      @if($io->scope_and_content)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('3.1 Scope and content') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e(\AhgInformationObjectManage\Services\PiiMaskingService::mask((int)$io->id, $io->scope_and_content))) !!}@include('ahg-translation::components.badge', ['source' => $translationSources['scope_and_content'] ?? null])</div>
        </div>
      @endif
      @if($io->arrangement)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('3.2 System of arrangement') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->arrangement)) !!}</div>
        </div>
      @endif
      @if($io->accruals)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('3.3 Accruals') }}<span class="d-block small text-muted">({{ __('also 5.4') }})</span></h3>
          <div class="col-9 p-2">{!! nl2br(e($io->accruals)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ========== Chapter 4 — Conditions of Access and Use ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('dacs_conditions_of_access_area'))
    @php
      $hasCh4 = $io->access_conditions
        || ($dacsExt && ($dacsExt->physical_access_note || $dacsExt->technical_access_note))
        || $io->physical_characteristics
        || $io->reproduction_conditions
        || (isset($materialLanguages) && (is_array($materialLanguages) ? count($materialLanguages) > 0 : $materialLanguages->isNotEmpty()))
        || $io->finding_aids;
    @endphp
    @if($hasCh4)
      <section class="border-bottom">
        <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
          {{ __('Chapter 4 — Conditions of Access and Use') }}
        </h2>

        @if($io->access_conditions)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('4.1 Conditions governing access') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->access_conditions)) !!}</div>
          </div>
        @endif

        {{-- 4.2 / 4.3 — Physical and Technical Access. DACS splits these
             but the original AtoM physical_characteristics column conflated
             them. Sidecar columns (ahg_io_dacs.physical_access_note +
             technical_access_note) hold the DACS-conformant split when
             the cataloguer has populated them; otherwise we render the
             merged AtoM column under the combined heading. --}}
        @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('dacs_physical_access'))
          @if($dacsExt && $dacsExt->physical_access_note)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('4.2 Physical access') }}</h3>
              <div class="col-9 p-2">{!! nl2br(e($dacsExt->physical_access_note)) !!}</div>
            </div>
          @endif
          @if($dacsExt && $dacsExt->technical_access_note)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('4.3 Technical access') }}</h3>
              <div class="col-9 p-2">{!! nl2br(e($dacsExt->technical_access_note)) !!}</div>
            </div>
          @endif
          @if(!($dacsExt && ($dacsExt->physical_access_note || $dacsExt->technical_access_note)) && $io->physical_characteristics)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('4.2 / 4.3 Physical and technical access') }}</h3>
              <div class="col-9 p-2">{!! nl2br(e($io->physical_characteristics)) !!}</div>
            </div>
          @endif
        @endif

        @if($io->reproduction_conditions)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('4.4 Conditions governing reproduction and use') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->reproduction_conditions)) !!}</div>
          </div>
        @endif

        @if(isset($materialLanguages) && (is_array($materialLanguages) ? count($materialLanguages) > 0 : $materialLanguages->isNotEmpty()))
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('4.5 Languages and scripts of the material') }}</h3>
            <div class="col-9 p-2">
              {{ is_array($materialLanguages) ? implode(', ', $materialLanguages) : $materialLanguages->implode(', ') }}
              @if(isset($materialScripts) && (is_array($materialScripts) ? count($materialScripts) > 0 : $materialScripts->isNotEmpty()))
                <br><span class="text-muted small">{{ __('Scripts:') }} {{ is_array($materialScripts) ? implode(', ', $materialScripts) : $materialScripts->implode(', ') }}</span>
              @endif
            </div>
          </div>
        @endif

        @if($io->finding_aids)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('4.6 Finding aids') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->finding_aids)) !!}</div>
          </div>
        @endif
      </section>
    @endif
  @endif

  {{-- ========== Chapter 5 — Acquisition and Appraisal ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('dacs_acquisition_area') && ($io->archival_history || $io->acquisition || $io->appraisal || $io->accruals))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
        {{ __('Chapter 5 — Acquisition and Appraisal') }}
      </h2>
      @if($io->archival_history)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('5.1 Custodial history') }}<span class="d-block small text-muted">({{ __('see also 2.7') }})</span></h3>
          <div class="col-9 p-2">{!! nl2br(e($io->archival_history)) !!}</div>
        </div>
      @endif
      @if($io->acquisition)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('5.2 Immediate source of acquisition') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->acquisition)) !!}</div>
        </div>
      @endif
      @if($io->appraisal)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('5.3 Appraisal, destruction and scheduling') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->appraisal)) !!}</div>
        </div>
      @endif
      @if($io->accruals)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('5.4 Accruals') }}<span class="d-block small text-muted">({{ __('also 3.3') }})</span></h3>
          <div class="col-9 p-2">{!! nl2br(e($io->accruals)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ========== Chapter 6 — Allied Materials ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('dacs_materials_area'))
    @php
      $hasCh6 = $io->location_of_originals || $io->location_of_copies || $io->related_units_of_description || ($dacsExt && $dacsExt->publication_note);
    @endphp
    @if($hasCh6)
      <section class="border-bottom">
        <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
          {{ __('Chapter 6 — Allied Materials') }}
        </h2>
        @if($io->location_of_originals)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('6.1 Existence and location of originals') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->location_of_originals)) !!}</div>
          </div>
        @endif
        @if($io->location_of_copies)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('6.2 Existence and location of copies') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->location_of_copies)) !!}</div>
          </div>
        @endif
        @if($io->related_units_of_description)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('6.3 Related archival materials') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($io->related_units_of_description)) !!}</div>
          </div>
        @endif
        @if($dacsExt && $dacsExt->publication_note)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('6.4 Publication note') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($dacsExt->publication_note)) !!}</div>
          </div>
        @endif
      </section>
    @endif
  @endif

  {{-- ========== Chapter 7 — Notes (reuses ISAD partial) ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('dacs_notes_area'))
    @include('ahg-information-object-manage::partials.sections.notes-area')
  @endif

  {{-- ========== Access Points (reuses ISAD partial) ========== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('dacs_access_points_area'))
    @include('ahg-information-object-manage::partials.sections.access-points-area')
  @endif

  {{-- ========== Chapter 8 — Description Control (auth-only) ========== --}}
  @auth
    @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('dacs_control_area') && ($io->rules || $io->sources || $io->revision_history || $descriptionStatusName))
      <section class="border-bottom">
        <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">
          {{ __('Chapter 8 — Description Control') }}
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
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Language(s) of description') }}</h3>
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

  {{-- Universal sections — share the partials with show.blade.php (these
       aren't part of DACS but they're page features the user expects). --}}
  @include('ahg-information-object-manage::partials.sections.security-classification-area')
  @include('ahg-information-object-manage::partials.sections.accession-area')
  @include('ahg-information-object-manage::partials.sections.museum-metadata')

@endsection
