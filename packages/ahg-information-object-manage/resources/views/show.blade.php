@extends('theme::layouts.3col')

@section('title', ($io->title ?? 'Archival description'))
@section('body-class', 'view informationobject')

{{-- ============================================================ --}}
{{-- LEFT SIDEBAR: Treeview / Holdings + Quick search             --}}
{{-- ============================================================ --}}
@section('sidebar')

  {{-- Dynamic treeview hierarchy --}}
  @include('ahg-io-manage::partials._treeview', ['io' => $io])

  {{-- Quick search within this collection --}}
  <div class="card mb-3">
    <div class="card-header fw-bold">
      <i class="fas fa-search me-1"></i> Search within
    </div>
    <div class="card-body p-2">
      <form action="{{ route('informationobject.browse') }}" method="GET">
        <input type="hidden" name="collection" value="{{ $io->id }}">
        <div class="input-group input-group-sm">
          <input type="text" name="subquery" class="form-control" placeholder="Search...">
          <button class="btn btn-outline-secondary" type="submit">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- ===== Authenticated-only management sections ===== --}}
  @auth

    {{-- Collections Management --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-archive me-1"></i> Collections Management
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.provenance', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-project-diagram me-1"></i> Provenance
        </a>
        <a href="{{ route('io.condition', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-clipboard-check me-1"></i> Condition assessment
        </a>
        <a href="{{ route('io.spectrum', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-chart-bar me-1"></i> Spectrum data
        </a>
        <a href="{{ route('io.heritage', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-landmark me-1"></i> Heritage Assets
        </a>
      </div>
    </div>

    {{-- Digital Preservation (OAIS) --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-shield-alt me-1"></i> Digital Preservation (OAIS)
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.preservation', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-box-open me-1"></i> Preservation packages
        </a>
      </div>
    </div>

    {{-- Cite this Record --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-quote-left me-1"></i> Cite this Record
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.research.citation', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-copy me-1"></i> Generate citation
        </a>
      </div>
    </div>

    {{-- AI Tools --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-robot me-1"></i> AI Tools
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.ai.extract', $io->id) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-brain me-1"></i> Extract Entities (NER)
        </a>
        <a href="{{ route('io.ai.summarize', $io->id) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-file-alt me-1"></i> Generate Summary
        </a>
        <a href="{{ route('io.ai.translate', $io->id) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-language me-1"></i> Translate
        </a>
      </div>
    </div>

    {{-- Review Dashboard --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-tasks me-1"></i> Review Dashboard
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.ai.review') }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-list-check me-1"></i> NER Review
        </a>
      </div>
    </div>

    {{-- Privacy & PII --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-user-shield me-1"></i> Privacy & PII
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.privacy.scan', $io->id) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-search me-1"></i> Scan for PII
        </a>
        @if(isset($digitalObjects) && $digitalObjects['master'])
          <a href="{{ route('io.privacy.redaction', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-eraser me-1"></i> Visual Redaction
          </a>
        @endif
        <a href="{{ route('io.privacy.dashboard') }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-clipboard-check me-1"></i> Privacy Dashboard
        </a>
      </div>
    </div>

    {{-- Rights --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-copyright me-1"></i> Rights
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.rights.extended', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-copyright me-1"></i> Add extended rights
        </a>
        <a href="{{ route('io.rights.embargo', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-lock me-1"></i> Add embargo
        </a>
        <a href="{{ route('io.rights.export', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-download me-1"></i> Export rights (JSON-LD)
        </a>
      </div>
    </div>

    {{-- Research Tools --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-graduation-cap me-1"></i> Research Tools
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.research.assessment', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-clipboard-check me-1"></i> Source Assessment
        </a>
        <a href="{{ route('io.research.annotations', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-highlighter me-1"></i> Annotation Studio
        </a>
        <a href="{{ route('io.research.trust', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-star-half-alt me-1"></i> Trust Score
        </a>
        <a href="{{ route('io.research.dashboard') }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-graduation-cap me-1"></i> Research Dashboard
        </a>
      </div>
    </div>

  @endauth

@endsection

{{-- ============================================================ --}}
{{-- TITLE BLOCK                                                  --}}
{{-- ============================================================ --}}
@section('title-block')

  {{-- Description header: Level Identifier - Title --}}
  <h1 class="mb-2">
    @if($levelName)<span class="text-muted">{{ $levelName }}</span>@endif
    @if($io->identifier){{ $io->identifier }} - @endif
    {{ $io->title ?: '[Untitled]' }}
  </h1>

  {{-- Breadcrumb trail --}}
  @if($io->parent_id != 1 && !empty($breadcrumbs))
    <nav aria-label="Hierarchy">
      <ol class="breadcrumb">
        @foreach($breadcrumbs as $crumb)
          <li class="breadcrumb-item">
            <a href="{{ route('informationobject.show', $crumb->slug) }}">
              {{ $crumb->title ?: '[Untitled]' }}
            </a>
          </li>
        @endforeach
        <li class="breadcrumb-item active" aria-current="page">
          {{ $io->title ?: '[Untitled]' }}
        </li>
      </ol>
    </nav>
  @endif

  {{-- Publication status badge (authenticated only) --}}
  @auth
    @if($publicationStatus)
      <span class="badge bg-info mb-2">{{ $publicationStatus }}</span>
    @endif
  @endauth

@endsection

{{-- ============================================================ --}}
{{-- BEFORE CONTENT: Digital object reference image               --}}
{{-- ============================================================ --}}
@section('before-content')

  @if(isset($digitalObjects) && ($digitalObjects['master'] || $digitalObjects['reference'] || $digitalObjects['thumbnail']))
    @php
      $masterObj = $digitalObjects['master'];
      $refObj = $digitalObjects['reference'] ?? $masterObj;
      $masterUrl = $masterObj ? \AhgCore\Services\DigitalObjectService::getUrl($masterObj) : '';
      $refUrl = $refObj ? \AhgCore\Services\DigitalObjectService::getUrl($refObj) : '';
      $masterMediaType = $masterObj ? \AhgCore\Services\DigitalObjectService::getMediaType($masterObj) : null;
      $refMediaType = $refObj ? \AhgCore\Services\DigitalObjectService::getMediaType($refObj) : null;
      $isPdf = $masterObj && $masterObj->mime_type === 'application/pdf';
    @endphp

    <div class="digital-object-reference text-center p-3 border-bottom">
      @if($isPdf)
        {{-- PDF: embedded iframe viewer with toolbar (matches AtoM) --}}
        <div class="pdf-viewer-container" style="overflow:hidden;">
          <div class="pdf-wrapper">
            <div class="pdf-toolbar mb-2 d-flex justify-content-between align-items-center">
              <span class="badge bg-danger">
                <i class="fas fa-file-pdf me-1"></i>PDF Document
              </span>
              <div class="btn-group btn-group-sm">
                <a href="{{ $masterUrl }}" target="_blank" class="btn btn-outline-secondary" title="Open in new tab">
                  <i class="fas fa-external-link-alt"></i>
                </a>
                <a href="{{ $masterUrl }}" download class="btn btn-outline-secondary" title="Download PDF">
                  <i class="fas fa-download"></i>
                </a>
              </div>
            </div>
            <div class="ratio" style="--bs-aspect-ratio: 85%;">
              <iframe src="{{ $masterUrl }}" style="border:none;border-radius:8px;background:#525659;" title="PDF Viewer"></iframe>
            </div>
          </div>
        </div>

      @elseif($masterMediaType === 'video')
        {{-- Video: HTML5 player --}}
        <video controls class="img-fluid" style="max-height:480px;">
          <source src="{{ $masterUrl }}" type="{{ $masterObj->mime_type }}">
          Your browser does not support the video tag.
        </video>

      @elseif($masterMediaType === 'audio')
        {{-- Audio: HTML5 player --}}
        <audio controls class="w-100">
          <source src="{{ $masterUrl }}" type="{{ $masterObj->mime_type }}">
          Your browser does not support the audio tag.
        </audio>

      @elseif($refUrl)
        {{-- Image or other: show reference image --}}
        <a href="{{ $masterUrl ?: $refUrl }}" target="_blank">
          <img src="{{ $refUrl }}" alt="{{ $io->title }}" class="img-fluid img-thumbnail" style="max-height:480px;">
        </a>
      @endif
    </div>
  @endif

@endsection

{{-- ============================================================ --}}
{{-- MAIN CONTENT: ISAD(G) sections                              --}}
{{-- ============================================================ --}}
@section('content')

  {{-- ===== 1. Identity area ===== --}}
  <section id="identityArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      <a class="d-flex p-3 border-bottom text-primary text-decoration-none" href="#identity-collapse">
        Identity area
      </a>
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="btn btn-sm btn-outline-secondary ms-auto">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
    </h2>
    <div id="identity-collapse">

      @if($io->identifier)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Reference code</h3>
          <div class="col-9 p-2">{{ $io->identifier }}</div>
        </div>
      @endif

      @if($io->title)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Title</h3>
          <div class="col-9 p-2">{{ $io->title }}</div>
        </div>
      @endif

      @if(isset($events) && $events->isNotEmpty())
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Date(s)</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($events as $event)
                <li>
                  {{ $event->date_display ?? '' }}
                  @if($event->start_date || $event->end_date)
                    @if(!$event->date_display)({{ $event->start_date ?? '?' }} - {{ $event->end_date ?? '?' }})@endif
                  @endif
                  @if($event->type_id && isset($eventTypeNames[$event->type_id]))
                    ({{ $eventTypeNames[$event->type_id] }})
                  @endif
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @if($levelName)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Level of description</h3>
          <div class="col-9 p-2">{{ $levelName }}</div>
        </div>
      @endif

      @if($io->extent_and_medium)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Extent and medium</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->extent_and_medium)) !!}</div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== 2. Context area ===== --}}
  <section id="contextArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      <a class="d-flex p-3 border-bottom text-primary text-decoration-none" href="#context-collapse">
        Context area
      </a>
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="btn btn-sm btn-outline-secondary ms-auto">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
    </h2>
    <div id="context-collapse">

      {{-- Creator details --}}
      @if(isset($creators) && $creators->isNotEmpty())
        <div class="creatorHistories">
          @foreach($creators as $creator)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Name of creator(s)</h3>
              <div class="col-9 p-2">
                <a href="{{ route('actor.show', $creator->slug) }}">{{ $creator->name }}</a>
              </div>
            </div>

            @if($creator->dates_of_existence)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Dates of existence</h3>
                <div class="col-9 p-2">{{ $creator->dates_of_existence }}</div>
              </div>
            @endif

            @if($creator->history)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">
                  @if(isset($creator->entity_type_id) && $creator->entity_type_id == 131)
                    Administrative history
                  @else
                    Biographical history
                  @endif
                </h3>
                <div class="col-9 p-2">{!! nl2br(e($creator->history)) !!}</div>
              </div>
            @endif
          @endforeach
        </div>
      @endif

      {{-- Related function --}}
      @if(isset($functionRelations) && (is_countable($functionRelations) ? count($functionRelations) > 0 : !empty($functionRelations)))
        <div class="relatedFunctions">
          @foreach($functionRelations as $item)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related function</h3>
              <div class="col-9 p-2">
                @if(isset($item->slug))
                  <a href="{{ route('function.show', $item->slug) }}">{{ $item->name ?? $item->title ?? '[Untitled]' }}</a>
                @else
                  {{ $item->name ?? $item->title ?? '[Untitled]' }}
                @endif
              </div>
            </div>
          @endforeach
        </div>
      @endif

      {{-- Repository --}}
      @if($repository)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Repository</h3>
          <div class="col-9 p-2">
            <a href="{{ route('repository.show', $repository->slug) }}">{{ $repository->name }}</a>
          </div>
        </div>
      @endif

      @if($io->archival_history)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Archival history</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->archival_history)) !!}</div>
        </div>
      @endif

      @if($io->acquisition)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Immediate source of acquisition or transfer</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->acquisition)) !!}</div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== 3. Content and structure area ===== --}}
  <section id="contentAndStructureArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      <a class="d-flex p-3 border-bottom text-primary text-decoration-none" href="#content-collapse">
        Content and structure area
      </a>
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="btn btn-sm btn-outline-secondary ms-auto">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
    </h2>
    <div id="content-collapse">

      @if($io->scope_and_content)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Scope and content</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->scope_and_content)) !!}</div>
        </div>
      @endif

      @if($io->appraisal)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Appraisal, destruction and scheduling</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->appraisal)) !!}</div>
        </div>
      @endif

      @if($io->accruals)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Accruals</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->accruals)) !!}</div>
        </div>
      @endif

      @if($io->arrangement)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">System of arrangement</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->arrangement)) !!}</div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== 4. Conditions of access and use area ===== --}}
  <section id="conditionsOfAccessAndUseArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      <a class="d-flex p-3 border-bottom text-primary text-decoration-none" href="#conditions-collapse">
        Conditions of access and use area
      </a>
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="btn btn-sm btn-outline-secondary ms-auto">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
    </h2>
    <div id="conditions-collapse">

      @if($io->access_conditions)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Conditions governing access</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->access_conditions)) !!}</div>
        </div>
      @endif

      @if($io->reproduction_conditions)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Conditions governing reproduction</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->reproduction_conditions)) !!}</div>
        </div>
      @endif

      @if(isset($languages) && $languages->isNotEmpty())
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Language of material</h3>
          <div class="col-9 p-2">
            @foreach($languages as $lang)
              {{ $lang->name }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @endif

      @if(isset($scriptsOfMaterial) && $scriptsOfMaterial->isNotEmpty())
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Script of material</h3>
          <div class="col-9 p-2">
            @foreach($scriptsOfMaterial as $script)
              {{ $script->name }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @elseif(isset($materialScripts) && (is_countable($materialScripts) ? count($materialScripts) > 0 : !empty($materialScripts)))
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Script of material</h3>
          <div class="col-9 p-2">
            @foreach($materialScripts as $script)
              {{ $script }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Language and script notes (note type_id 174) --}}
      @foreach($notes->where('type_id', 174) as $lnote)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Language and script notes</h3>
          <div class="col-9 p-2">{!! nl2br(e($lnote->content)) !!}</div>
        </div>
      @endforeach

      @if($io->physical_characteristics)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Physical characteristics and technical requirements</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->physical_characteristics)) !!}</div>
        </div>
      @endif

      @if($io->finding_aids)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Finding aids</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->finding_aids)) !!}</div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== 5. Allied materials area ===== --}}
  <section id="alliedMaterialsArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      <a class="d-flex p-3 border-bottom text-primary text-decoration-none" href="#allied-collapse">
        Allied materials area
      </a>
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="btn btn-sm btn-outline-secondary ms-auto">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
    </h2>
    <div id="allied-collapse">

      @if($io->location_of_originals)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Existence and location of originals</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->location_of_originals)) !!}</div>
        </div>
      @endif

      @if($io->location_of_copies)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Existence and location of copies</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->location_of_copies)) !!}</div>
        </div>
      @endif

      @if($io->related_units_of_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related units of description</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->related_units_of_description)) !!}</div>
        </div>
      @endif

      {{-- Publication notes (type_id = 141) --}}
      @if(isset($notes) && $notes->isNotEmpty())
        @foreach($notes->where('type_id', 141) as $note)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Publication note</h3>
            <div class="col-9 p-2">{!! nl2br(e($note->content)) !!}</div>
          </div>
        @endforeach
      @endif

    </div>
  </section>

  {{-- ===== 6. Notes area ===== --}}
  <section id="notesArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      <a class="d-flex p-3 border-bottom text-primary text-decoration-none" href="#notes-collapse">
        Notes area
      </a>
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="btn btn-sm btn-outline-secondary ms-auto">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
    </h2>
    <div id="notes-collapse">

      {{-- General notes (type_id = 137) --}}
      @if(isset($notes) && $notes->isNotEmpty())
        @foreach($notes->where('type_id', 137) as $note)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Note</h3>
            <div class="col-9 p-2">{!! nl2br(e($note->content)) !!}</div>
          </div>
        @endforeach
      @endif

      {{-- Alternative identifiers --}}
      @if(isset($alternativeIdentifiers) && (is_countable($alternativeIdentifiers) ? count($alternativeIdentifiers) > 0 : !empty($alternativeIdentifiers)))
        @foreach($alternativeIdentifiers as $altId)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">
              {{ $altId->label ?? 'Alternative identifier' }}
            </h3>
            <div class="col-9 p-2">{{ $altId->value ?? $altId->name ?? '' }}</div>
          </div>
        @endforeach
      @endif

    </div>
  </section>

  {{-- ===== 7. Access points ===== --}}
  <section id="accessPointsArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      <a class="d-flex p-3 border-bottom text-primary text-decoration-none" href="#access-collapse">
        Access points
      </a>
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="btn btn-sm btn-outline-secondary ms-auto">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
    </h2>
    <div id="access-collapse">

      @if(isset($subjects) && $subjects->isNotEmpty())
        <div class="field text-break row g-0 subjectAccessPoints">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Subject access points</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($subjects as $subject)
                <li>{{ $subject->name }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @if(isset($places) && $places->isNotEmpty())
        <div class="field text-break row g-0 placeAccessPoints">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Place access points</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($places as $place)
                <li>{{ $place->name }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @if(isset($nameAccessPoints) && $nameAccessPoints->isNotEmpty())
        <div class="field text-break row g-0 nameAccessPoints">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Name access points</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($nameAccessPoints as $nap)
                <li>{{ $nap->name }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

      @if(isset($genres) && $genres->isNotEmpty())
        <div class="field text-break row g-0 genreAccessPoints">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Genre access points</h3>
          <div class="col-9 p-2">
            <ul class="m-0 ms-1 ps-3">
              @foreach($genres as $genre)
                <li>{{ $genre->name }}</li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== 8. Description control area ===== --}}
  <section id="descriptionControlArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      <a class="d-flex p-3 border-bottom text-primary text-decoration-none" href="#description-collapse">
        Description control area
      </a>
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="btn btn-sm btn-outline-secondary ms-auto">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
    </h2>
    <div id="description-collapse">

      @if($io->description_identifier ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Description identifier</h3>
          <div class="col-9 p-2">{{ $io->description_identifier }}</div>
        </div>
      @endif

      @if($io->institution_responsible_identifier ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Institution identifier</h3>
          <div class="col-9 p-2">{{ $io->institution_responsible_identifier }}</div>
        </div>
      @endif

      @if($io->rules ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rules and/or conventions used</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->rules)) !!}</div>
        </div>
      @endif

      @if(isset($descriptionStatusName) && $descriptionStatusName)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Status</h3>
          <div class="col-9 p-2">{{ $descriptionStatusName }}</div>
        </div>
      @endif

      @if(isset($descriptionDetailName) && $descriptionDetailName)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Level of detail</h3>
          <div class="col-9 p-2">{{ $descriptionDetailName }}</div>
        </div>
      @endif

      @if($io->revision_history ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Dates of creation revision deletion</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->revision_history)) !!}</div>
        </div>
      @endif

      @if(isset($languagesOfDescription) && (is_countable($languagesOfDescription) ? count($languagesOfDescription) > 0 : !empty($languagesOfDescription)))
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Language(s)</h3>
          <div class="col-9 p-2">
            @foreach($languagesOfDescription as $lang)
              {{ $lang }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @endif

      @if(isset($scriptsOfDescription) && (is_countable($scriptsOfDescription) ? count($scriptsOfDescription) > 0 : !empty($scriptsOfDescription)))
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Script(s)</h3>
          <div class="col-9 p-2">
            @foreach($scriptsOfDescription as $script)
              {{ $script }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @endif

      @if($io->sources ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Sources</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->sources)) !!}</div>
        </div>
      @endif

      {{-- Archivist's note (type_id = 142) --}}
      @if(isset($notes) && $notes->isNotEmpty())
        @foreach($notes->where('type_id', 142) as $note)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Archivist's note</h3>
            <div class="col-9 p-2">{!! nl2br(e($note->content)) !!}</div>
          </div>
        @endforeach
      @endif

    </div>
  </section>

  {{-- ===== 9. Rights area (authenticated only) ===== --}}
  @auth
    <section id="rightsArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header">
        <a class="d-flex p-3 border-bottom text-primary text-decoration-none" href="#rights-collapse">
          Rights area
        </a>
      </h2>
      <div id="rights-collapse">
        @if(isset($rights) && (is_countable($rights) ? count($rights) > 0 : !empty($rights)))
          @foreach($rights as $right)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ $right->basis ?? 'Right' }}</h3>
              <div class="col-9 p-2">
                @if(isset($right->act)){{ $right->act }}@endif
                @if(isset($right->start_date) || isset($right->end_date))
                  <br><small class="text-muted">{{ $right->start_date ?? '?' }} - {{ $right->end_date ?? '?' }}</small>
                @endif
                @if(isset($right->rights_note))
                  <br>{!! nl2br(e($right->rights_note)) !!}
                @endif
              </div>
            </div>
          @endforeach
        @endif
      </div>
    </section>
  @endauth

  {{-- ===== 10. Digital object metadata ===== --}}
  @if(isset($digitalObjects) && $digitalObjects['master'])
    @php
      $doMaster = $digitalObjects['master'];
      $doReference = $digitalObjects['reference'];
      $doThumbnail = $digitalObjects['thumbnail'];
      $doMasterUrl = \AhgCore\Services\DigitalObjectService::getUrl($doMaster);
      $doRefUrl = $doReference ? \AhgCore\Services\DigitalObjectService::getUrl($doReference) : '';
      $doThumbUrl = $doThumbnail ? \AhgCore\Services\DigitalObjectService::getUrl($doThumbnail) : '';
      $doMediaTypeName = \AhgCore\Services\DigitalObjectService::getMediaType($doMaster);
    @endphp
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header">
        <a class="d-flex p-3 border-bottom text-primary text-decoration-none" href="#digital-object-collapse">
          Digital object metadata
        </a>
      </h2>
      <div id="digital-object-collapse">
        <div class="accordion" id="doMetadataAccordion">

          {{-- Master file --}}
          <div class="accordion-item">
            <h2 class="accordion-header" id="doMasterHeading">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#doMasterCollapse" aria-expanded="true">
                Master file
              </button>
            </h2>
            <div id="doMasterCollapse" class="accordion-collapse collapse show" data-bs-parent="">
              <div class="accordion-body p-0">
                <div class="field text-break row g-0">
                  <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Filename</h3>
                  <div class="col-9 p-2">
                    @auth
                      <a href="{{ $doMasterUrl }}" target="_blank">{{ $doMaster->name }}</a>
                    @else
                      {{ $doMaster->name }}
                    @endauth
                  </div>
                </div>
                @if($doMaster->media_type_id)
                  <div class="field text-break row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Media type</h3>
                    <div class="col-9 p-2">{{ ucfirst($doMediaTypeName) }}</div>
                  </div>
                @endif
                @if($doMaster->mime_type)
                  <div class="field text-break row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">MIME type</h3>
                    <div class="col-9 p-2">{{ $doMaster->mime_type }}</div>
                  </div>
                @endif
                @if($doMaster->byte_size)
                  <div class="field text-break row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Filesize</h3>
                    <div class="col-9 p-2">
                      @if($doMaster->byte_size > 1048576)
                        {{ number_format($doMaster->byte_size / 1048576, 1) }} MB
                      @else
                        {{ number_format($doMaster->byte_size / 1024, 1) }} KB
                      @endif
                    </div>
                  </div>
                @endif
                @if($doMaster->checksum)
                  <div class="field text-break row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Checksum</h3>
                    <div class="col-9 p-2"><code class="small">{{ $doMaster->checksum }}</code></div>
                  </div>
                @endif
              </div>
            </div>
          </div>

          {{-- Reference copy --}}
          @if($doReference)
            <div class="accordion-item">
              <h2 class="accordion-header" id="doRefHeading">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#doRefCollapse" aria-expanded="true">
                  Reference copy
                </button>
              </h2>
              <div id="doRefCollapse" class="accordion-collapse collapse show" data-bs-parent="">
                <div class="accordion-body p-0">
                  <div class="field text-break row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Filename</h3>
                    <div class="col-9 p-2">
                      @auth
                        <a href="{{ $doRefUrl }}" target="_blank">{{ $doReference->name }}</a>
                      @else
                        {{ $doReference->name }}
                      @endauth
                    </div>
                  </div>
                  @if($doReference->mime_type)
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">MIME type</h3>
                      <div class="col-9 p-2">{{ $doReference->mime_type }}</div>
                    </div>
                  @endif
                  @if($doReference->byte_size)
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Filesize</h3>
                      <div class="col-9 p-2">
                        @if($doReference->byte_size > 1048576)
                          {{ number_format($doReference->byte_size / 1048576, 1) }} MB
                        @else
                          {{ number_format($doReference->byte_size / 1024, 1) }} KB
                        @endif
                      </div>
                    </div>
                  @endif
                </div>
              </div>
            </div>
          @endif

          {{-- Thumbnail copy --}}
          @if($doThumbnail)
            <div class="accordion-item">
              <h2 class="accordion-header" id="doThumbHeading">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#doThumbCollapse" aria-expanded="true">
                  Thumbnail copy
                </button>
              </h2>
              <div id="doThumbCollapse" class="accordion-collapse collapse show" data-bs-parent="">
                <div class="accordion-body p-0">
                  <div class="field text-break row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Filename</h3>
                    <div class="col-9 p-2">
                      <a href="{{ $doThumbUrl }}" target="_blank">{{ $doThumbnail->name }}</a>
                    </div>
                  </div>
                  @if($doThumbnail->mime_type)
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">MIME type</h3>
                      <div class="col-9 p-2">{{ $doThumbnail->mime_type }}</div>
                    </div>
                  @endif
                  @if($doThumbnail->byte_size)
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Filesize</h3>
                      <div class="col-9 p-2">
                        @if($doThumbnail->byte_size > 1048576)
                          {{ number_format($doThumbnail->byte_size / 1048576, 1) }} MB
                        @else
                          {{ number_format($doThumbnail->byte_size / 1024, 1) }} KB
                        @endif
                      </div>
                    </div>
                  @endif
                </div>
              </div>
            </div>
          @endif

        </div>
      </div>
    </section>
  @endif

  {{-- ===== 11. Accession area ===== --}}
  <section id="accessionArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header">
      <a class="d-flex p-3 border-bottom text-primary text-decoration-none" href="#accession-collapse">
        Accession area
      </a>
    </h2>
    <div id="accession-collapse">
      @if(isset($accessions) && (is_countable($accessions) ? count($accessions) > 0 : !empty($accessions)))
        @foreach($accessions as $accession)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Accession</h3>
            <div class="col-9 p-2">
              @if(isset($accession->slug))
                <a href="{{ route('accession.show', $accession->slug) }}">{{ $accession->identifier ?? $accession->name ?? '[Untitled]' }}</a>
              @else
                {{ $accession->identifier ?? $accession->name ?? '[Untitled]' }}
              @endif
            </div>
          </div>
        @endforeach
      @endif
    </div>
  </section>

@endsection

{{-- ============================================================ --}}
{{-- RIGHT SIDEBAR                                                --}}
{{-- ============================================================ --}}
@section('right')

  <nav>
    {{-- Clipboard --}}
    <div class="mb-3">
      @include('ahg-core::clipboard._button', ['slug' => $io->slug, 'type' => 'informationObject', 'wide' => true])
    </div>

    {{-- Explore --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-cogs me-1"></i> Explore
      </div>
      <div class="list-group list-group-flush">
        <a href="#" class="list-group-item list-group-item-action small">
          <i class="fas fa-chart-pie me-1"></i> Reports
        </a>
        <a href="{{ route('informationobject.browse') }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-list me-1"></i> Browse as list
        </a>
        @if(isset($digitalObjects) && $digitalObjects['master'])
          <a href="{{ route('informationobject.browse', ['digital' => 1]) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-image me-1"></i> Browse digital objects
          </a>
        @endif
      </div>
    </div>

    {{-- Import --}}
    @auth
      <div class="card mb-3">
        <div class="card-header fw-bold">
          <i class="fas fa-upload me-1"></i> Import
        </div>
        <div class="list-group list-group-flush">
          <a href="{{ route('informationobject.import.xml', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-code me-1"></i> XML
          </a>
          <a href="{{ route('informationobject.import.csv', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-file-csv me-1"></i> CSV
          </a>
        </div>
      </div>
    @endauth

    {{-- Export --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-file-export me-1"></i> Export
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('informationobject.export.dc', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> Dublin Core 1.1 XML
        </a>
        <a href="{{ route('informationobject.export.ead', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> EAD 2002 XML
        </a>
      </div>
    </div>

    {{-- Finding aid --}}
    @auth
      <div class="card mb-3">
        <div class="card-header fw-bold">
          <i class="fas fa-book me-1"></i> Finding aid
        </div>
        <div class="list-group list-group-flush">
          <a href="{{ route('informationobject.findingaid.generate', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-file-alt me-1"></i> Generate
          </a>
          <a href="{{ route('informationobject.findingaid.upload.form', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-upload me-1"></i> Upload
          </a>
        </div>
      </div>
    @endauth

    {{-- Tasks --}}
    @auth
      <div class="card mb-3">
        <div class="card-header fw-bold">
          <i class="fas fa-tasks me-1"></i> Tasks
        </div>
        <div class="list-group list-group-flush">
          <a href="#" class="list-group-item list-group-item-action small">
            <i class="fas fa-calculator me-1"></i> Calculate dates
          </a>
          <span class="list-group-item small text-muted">
            <i class="fas fa-clock me-1"></i> Last run: Never
          </span>
        </div>
      </div>
    @endauth

    {{-- Related subjects --}}
    @if(isset($subjects) && $subjects->isNotEmpty())
      <div class="card mb-3">
        <div class="card-header fw-bold">
          <i class="fas fa-tag me-1"></i> Related subjects
        </div>
        <ul class="list-group list-group-flush">
          @foreach($subjects as $subject)
            <li class="list-group-item small">
              <a href="{{ route('informationobject.browse', ['subject' => $subject->name]) }}" class="text-decoration-none">
                {{ $subject->name }}
              </a>
            </li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- Related people and organizations --}}
    @if((isset($creators) && $creators->isNotEmpty()) || (isset($nameAccessPoints) && $nameAccessPoints->isNotEmpty()))
      <div class="card mb-3">
        <div class="card-header fw-bold">
          <i class="fas fa-users me-1"></i> Related people and organizations
        </div>
        <ul class="list-group list-group-flush">
          @if(isset($creators) && $creators->isNotEmpty())
            @foreach($creators as $creator)
              <li class="list-group-item small">
                <a href="{{ route('actor.show', $creator->slug) }}" class="text-decoration-none">{{ $creator->name }}</a>
                <span class="text-muted">(Creation)</span>
              </li>
            @endforeach
          @endif
          @if(isset($nameAccessPoints) && $nameAccessPoints->isNotEmpty())
            @foreach($nameAccessPoints as $nap)
              <li class="list-group-item small">
                @if(isset($nap->slug))
                  <a href="{{ route('actor.show', $nap->slug) }}" class="text-decoration-none">{{ $nap->name }}</a>
                @else
                  {{ $nap->name }}
                @endif
              </li>
            @endforeach
          @endif
        </ul>
      </div>
    @endif

    {{-- Related genres --}}
    @if(isset($genres) && $genres->isNotEmpty())
      <div class="card mb-3">
        <div class="card-header fw-bold">
          <i class="fas fa-masks-theater me-1"></i> Related genres
        </div>
        <ul class="list-group list-group-flush">
          @foreach($genres as $genre)
            <li class="list-group-item small">{{ $genre->name }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- Related places --}}
    @if(isset($places) && $places->isNotEmpty())
      <div class="card mb-3">
        <div class="card-header fw-bold">
          <i class="fas fa-map-marker-alt me-1"></i> Related places
        </div>
        <ul class="list-group list-group-flush">
          @foreach($places as $place)
            <li class="list-group-item small">{{ $place->name }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- Physical storage --}}
    @if(isset($physicalObjects) && (is_countable($physicalObjects) ? count($physicalObjects) > 0 : !empty($physicalObjects)))
      <div class="card mb-3">
        <div class="card-header fw-bold">
          <i class="fas fa-box me-1"></i> Physical storage
        </div>
        <ul class="list-group list-group-flush">
          @foreach($physicalObjects as $pobj)
            <li class="list-group-item small">
              @if(isset($physicalObjectTypeNames[$pobj->type_id ?? null]))
                <strong>{{ $physicalObjectTypeNames[$pobj->type_id] }}:</strong>
              @endif
              {{ $pobj->name ?? $pobj->location ?? '[Unknown]' }}
            </li>
          @endforeach
        </ul>
      </div>
    @endif

  </nav>

@endsection

{{-- ============================================================ --}}
{{-- AFTER CONTENT: Action buttons                                --}}
{{-- ============================================================ --}}
@section('after-content')
  @auth
    <ul class="actions mb-3 nav gap-2">
      <li>
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="btn atom-btn-outline-light">Edit</a>
      </li>
      <li>
        <form action="{{ route('informationobject.destroy', $io->slug) }}" method="POST"
              onsubmit="return confirm('Are you sure you want to delete this archival description?');">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn atom-btn-outline-danger">Delete</button>
        </form>
      </li>
      <li>
        <a href="{{ route('informationobject.create', ['parent_id' => $io->id]) }}" class="btn atom-btn-outline-light">Add new</a>
      </li>
    </ul>
  @endauth
@endsection
