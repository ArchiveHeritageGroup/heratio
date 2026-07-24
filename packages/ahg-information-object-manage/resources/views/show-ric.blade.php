{{--
  ============================================================================
  Heratio archival-record show template - RiC-O (Records in Contexts, ICA).
  #1425. Selected per-record when information_object.display_standard_id -> the
  taxonomy-70 term code 'ric' (SettingHelper::resolveObjectTemplateView).

  Receives the standard show() view data plus $ricEnt - the RiC-O JSON-LD from
  AhgRic\Services\RicSerializationService::serializeRecord(), or null when the
  engine is absent (in which case the JSON-LD panel is simply omitted).
  ============================================================================
--}}
@extends('theme::layouts.3col')

@section('title', ($io->title ?? config('app.ui_label_informationobject', 'Archival description')))
@section('body-class', 'view informationobject template-ric')

@section('sidebar')
  @include('ahg-menu-manage::_static-pages-menu')
  @include('ahg-information-object-manage::partials._treeview', ['io' => $io])
@endsection

@section('title-block')
  <div class="d-flex flex-column">
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge bg-secondary">RiC-O</span>
      <span class="text-muted small">{{ __('Described per Records in Contexts (RiC-O 1.0, International Council on Archives)') }}</span>
    </div>
    <h1 class="h3 mb-0">{{ $io->title ?? __('Untitled record') }}</h1>
    @if($levelName)<small class="text-muted">{{ $levelName }} <span class="text-muted">· {{ $ricEnt['rico:type'] ?? 'Record' }}</span></small>@endif
  </div>
@endsection

@section('before-content')
  @include('ahg-information-object-manage::partials._redaction-overlay')
  @include('ahg-information-object-manage::partials._digital-object-viewer')
@endsection

@section('content')

  @include('ahg-ric::_view-switch', ['standard' => 'RiC', 'entityType' => 'information_object', 'objectId' => $io->id])

  @auth
    <div class="text-end mb-2 d-flex justify-content-end gap-2">
      {{-- #1425: add a child under this RiC record. The main button defaults to
           a RiC child (the parent is RiC); the caret offers any standard. Uses
           the ?standard=<code>&parent=<id> deep-link. --}}
      @if(\AhgCore\Services\AclService::check($io, 'create'))
        <div class="btn-group btn-group-sm">
          <a href="{{ route('informationobject.create', ['parent' => $io->id, 'standard' => 'ric']) }}" class="btn btn-outline-success">
            <i class="fas fa-sitemap me-1"></i>{{ __('Add RiC child') }}
          </a>
          <button type="button" class="btn btn-outline-success dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="visually-hidden">{{ __('Add child in a standard') }}</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">{{ __('Add child description in') }}</h6></li>
            @foreach([
              ['ric',  'RiC-O (Records in Contexts)'],
              ['isad', 'ISAD(G)'],
              ['dacs', 'DACS'],
              ['rad',  'RAD'],
              ['mods', 'MODS'],
              ['dc',   'Dublin Core'],
            ] as [$code, $label])
              <li>
                <a class="dropdown-item" href="{{ route('informationobject.create', ['parent' => $io->id] + ($code === 'isad' ? [] : ['standard' => $code])) }}">
                  <i class="fas fa-sitemap me-2 text-muted"></i>{{ __($label) }}
                </a>
              </li>
            @endforeach
          </ul>
        </div>
      @endif
      <a href="{{ route('ahgricmanage.edit', ['slug' => $io->slug ?? '']) }}" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-pencil-alt me-1"></i>{{ __('Edit (RiC-O)') }}
      </a>
    </div>
  @endauth

  @php
    $ricRow = function ($label, $ric, $value) {
        if (!$value) { return; }
        echo '<div class="field text-break row g-0">';
        echo '<h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">'.e(__($label)).' <span class="d-block small text-muted">'.e($ric).'</span></h3>';
        echo '<div class="col-9 p-2">'.nl2br(e($value)).'</div>';
        echo '</div>';
    };
  @endphp

  {{-- ===== Record identity ===== --}}
  <section class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">{{ __('Record identity') }}</h2>
    @php
      $ricRow('Identifier', 'rico:identifier', $io->identifier ?? null);
      $ricRow('Title', 'rico:title', $io->title ?? null);
    @endphp
    @if($levelName)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('RecordSet type') }} <span class="d-block small text-muted">rico:hasRecordSetType</span></h3>
        <div class="col-9 p-2">{{ $levelName }}</div>
      </div>
    @endif
    @if($repository)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Holder') }} <span class="d-block small text-muted">rico:hasOrHadHolder</span></h3>
        <div class="col-9 p-2"><a href="{{ route('repository.show', $repository->slug ?? '') }}">{{ $repository->authorized_form_of_name ?? $repository->name ?? '' }}</a></div>
      </div>
    @endif
    @if(isset($events) && $events->isNotEmpty())
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Dates') }} <span class="d-block small text-muted">rico:hasDateRangeSet</span></h3>
        <div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">
          @foreach($events as $event)
            <li>{{ $event->date_display ?? '' }}@if(!$event->date_display && ($event->start_date || $event->end_date)) ({{ $event->start_date ?? '?' }} – {{ $event->end_date ?? '?' }})@endif</li>
          @endforeach
        </ul></div>
      </div>
    @endif
    @if(isset($creators) && $creators->isNotEmpty())
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creator(s)') }} <span class="d-block small text-muted">rico:hasCreator</span></h3>
        <div class="col-9 p-2"><ul class="m-0 ms-1 ps-3">
          @foreach($creators as $creator)
            <li><a href="{{ route('actor.show', $creator->slug ?? '') }}">{{ $creator->authorized_form_of_name ?? '' }}</a></li>
          @endforeach
        </ul></div>
      </div>
    @endif
  </section>

  {{-- ===== Content and structure ===== --}}
  @if($io->scope_and_content || $io->arrangement || $io->extent_and_medium || $io->archival_history || $io->acquisition || ($io->appraisal ?? null) || ($io->accruals ?? null))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">{{ __('Content and structure') }}</h2>
      @php
        $ricRow('Scope and content', 'rico:description', $io->scope_and_content ?? null);
        $ricRow('Extent and medium', 'rico:hasExtent', $io->extent_and_medium ?? null);
        $ricRow('Arrangement', 'rico:structure', $io->arrangement ?? null);
        $ricRow('Archival / custodial history', 'rico:history', $io->archival_history ?? null);
        $ricRow('Immediate source of acquisition', 'rico:hasSourceOfAcquisition', $io->acquisition ?? null);
        $ricRow('Appraisal, destruction and scheduling', 'rico:descriptiveNote', $io->appraisal ?? null);
        $ricRow('Accruals', 'rico:descriptiveNote', $io->accruals ?? null);
      @endphp
    </section>
  @endif

  {{-- ===== Conditions of access and use ===== --}}
  @if($io->access_conditions || $io->reproduction_conditions || $io->physical_characteristics || $io->finding_aids)
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">{{ __('Conditions of access and use') }}</h2>
      @php
        $ricRow('Conditions governing access', 'rico:conditionsOfAccess', $io->access_conditions ?? null);
        $ricRow('Conditions governing reproduction', 'rico:conditionsOfUse', $io->reproduction_conditions ?? null);
        $ricRow('Physical characteristics', 'rico:physicalCharacteristics', $io->physical_characteristics ?? null);
        $ricRow('Finding aids', 'rico:hasInstantiation', $io->finding_aids ?? null);
      @endphp
    </section>
  @endif

  {{-- ===== Related materials ===== --}}
  @if($io->location_of_originals || $io->location_of_copies || $io->related_units_of_description)
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">{{ __('Related materials') }}</h2>
      @php
        $ricRow('Existence and location of originals', 'rico:hasInstantiation', $io->location_of_originals ?? null);
        $ricRow('Existence and location of copies', 'rico:hasCopy', $io->location_of_copies ?? null);
        $ricRow('Related units of description', 'rico:isRelatedTo', $io->related_units_of_description ?? null);
      @endphp
    </section>
  @endif

  {{-- ===== Access points ===== --}}
  @if((isset($subjects) && $subjects->isNotEmpty()) || (isset($places) && $places->isNotEmpty()) || (isset($genres) && $genres->isNotEmpty()) || (isset($nameAccessPoints) && $nameAccessPoints->isNotEmpty()))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">{{ __('Access points') }} <span class="small">rico:isOrWasSubjectOf / rico:isAssociatedWithPlace</span></h2>
      <div class="p-2">
        @foreach([['Subjects', $subjects ?? collect()], ['Places', $places ?? collect()], ['Genres', $genres ?? collect()]] as [$lbl, $coll])
          @if($coll->isNotEmpty())
            <div class="mb-1"><strong class="small text-muted">{{ __($lbl) }}:</strong> @foreach($coll as $t)<span class="badge bg-secondary">{{ $t->name }}</span> @endforeach</div>
          @endif
        @endforeach
        @if(isset($nameAccessPoints) && $nameAccessPoints->isNotEmpty())
          <div class="mb-1"><strong class="small text-muted">{{ __('Names') }}:</strong> @foreach($nameAccessPoints as $n)<span class="badge bg-info text-dark">{{ $n->authorized_form_of_name ?? $n->name ?? '' }}</span> @endforeach</div>
        @endif
      </div>
    </section>
  @endif

  {{-- ===== Description control ===== --}}
  @if(($io->description_identifier ?? null) || ($io->rules ?? null) || ($io->sources ?? null))
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg,#005837);color:var(--ahg-card-header-text,#fff);">{{ __('Description control') }}</h2>
      @php
        $ricRow('Description identifier', 'rico:identifier', $io->description_identifier ?? null);
        $ricRow('Rules or conventions', 'rico:descriptiveNote', $io->rules ?? null);
        $ricRow('Sources', 'rico:hasSource', $io->sources ?? null);
      @endphp
    </section>
  @endif

  {{-- ===== RiC-O linked-data (engine) ===== --}}
  @if($ricEnt)
    <section class="mt-3">
      @includeWhen(view()->exists('ahg-ric::_ric-entities-panel'), 'ahg-ric::_ric-entities-panel', ['record' => $io])
      <div class="card mt-3">
        <div class="card-header d-flex align-items-center justify-content-between" style="background:var(--ahg-primary,#10373E);color:#fff;">
          <span><i class="fas fa-project-diagram me-2"></i>{{ __('RiC-O JSON-LD') }}</span>
          <span class="badge bg-light text-dark">{{ $ricEnt['rico:type'] ?? 'Record' }}</span>
        </div>
        <div class="card-body p-0">
          <pre class="mb-0 p-3" style="max-height:460px;overflow:auto;font-size:.78rem;">{{ json_encode($ricEnt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
      </div>
    </section>
  @endif

@endsection
