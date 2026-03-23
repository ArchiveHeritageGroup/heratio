@extends('theme::layouts.3col')

@section('title', ($io->title ?? 'Archival description'))
@section('body-class', 'view informationobject')

{{-- ============================================================ --}}
{{-- LEFT SIDEBAR: Context menu (logo/treeview/static pages)      --}}
{{-- Matches AtoM _contextMenu.php                                --}}
{{-- ============================================================ --}}
@section('sidebar')

  {{-- Repository logo --}}
  @if(isset($repository) && $repository)
    @include('ahg-repository-manage::_logo', ['repository' => $repository])
  @endif

  {{-- Dynamic treeview hierarchy --}}
  @include('ahg-io-manage::partials._treeview', ['io' => $io])

@endsection

{{-- ============================================================ --}}
{{-- TITLE BLOCK                                                  --}}
{{-- Matches AtoM indexSuccess.php title slot                     --}}
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
      <span class="badge {{ (isset($publicationStatusId) && $publicationStatusId == 159) ? 'bg-warning text-dark' : 'bg-info' }} mb-2">{{ $publicationStatus }}</span>
    @endif
  @endauth

@endsection

{{-- ============================================================ --}}
{{-- BEFORE CONTENT: Digital object display                       --}}
{{-- Matches AtoM: imageflow + digitalobject show component       --}}
{{-- ============================================================ --}}
@section('before-content')

  @if(isset($digitalObjects) && ($digitalObjects['master'] || $digitalObjects['reference'] || $digitalObjects['thumbnail']))
    @php
      $masterObj = $digitalObjects['master'];
      $refObj = $digitalObjects['reference'] ?? null;
      $thumbObj = $digitalObjects['thumbnail'] ?? null;
      $masterUrl = $masterObj ? \AhgCore\Services\DigitalObjectService::getUrl($masterObj) : '';
      $refUrl = $refObj ? \AhgCore\Services\DigitalObjectService::getUrl($refObj) : '';
      $thumbUrl = $thumbObj ? \AhgCore\Services\DigitalObjectService::getUrl($thumbObj) : '';
      $masterMediaType = $masterObj ? \AhgCore\Services\DigitalObjectService::getMediaType($masterObj) : null;
      $isPdf = $masterObj && $masterObj->mime_type === 'application/pdf';
    @endphp

    <div class="digital-object-reference text-center p-3 border-bottom">
      @if($isPdf)
        {{-- PDF: embedded iframe viewer --}}
        <div class="ratio" style="--bs-aspect-ratio: 85%;">
          <iframe src="{{ $masterUrl }}" style="border:none;border-radius:8px;background:#525659;" title="PDF Viewer"></iframe>
        </div>

      @elseif($masterMediaType === 'video')
        {{-- Video: HTML5 player --}}
        <video controls class="w-100" style="max-height:500px; background:#000;" preload="metadata"
               @if($thumbUrl) poster="{{ $thumbUrl }}" @endif>
          <source src="{{ $masterUrl }}" type="{{ $masterObj->mime_type ?? 'video/mp4' }}">
          Your browser does not support this video format.
        </video>

      @elseif($masterMediaType === 'audio')
        {{-- Audio: HTML5 player --}}
        <audio controls class="w-100" preload="metadata">
          <source src="{{ $masterUrl }}" type="{{ $masterObj->mime_type ?? 'audio/mpeg' }}">
          Your browser does not support this audio format.
        </audio>

      @elseif($refUrl || $thumbUrl)
        {{-- Image: clickable reference image --}}
        <a href="{{ $masterUrl ?: $refUrl }}" target="_blank">
          <img src="{{ $refUrl ?: $thumbUrl }}" alt="{{ $io->title }}" class="img-fluid img-thumbnail" style="max-height:500px;">
        </a>

      @else
        {{-- Generic icon --}}
        <div class="py-4">
          <i class="fas fa-file fa-3x text-muted mb-3 d-block"></i>
          <p class="text-muted">{{ $masterObj->name ?? 'Digital object' }}</p>
        </div>
      @endif
    </div>
  @endif

@endsection

{{-- ============================================================ --}}
{{-- MAIN CONTENT: ISAD(G) sections                              --}}
{{-- Matches AtoM sfIsadPlugin/indexSuccess.php exactly           --}}
{{-- ============================================================ --}}
@section('content')

  {{-- ===== 1. Identity area ===== --}}
  <section id="identityArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#identity-collapse">
        Identity area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="identity-collapse">

      @if($io->identifier)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Reference code</h3>
          <div class="col-9 p-2">{{ $io->identifier }}</div>
        </div>
      @endif

      @if($io->title)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Title</h3>
          <div class="col-9 p-2">{{ $io->title }}</div>
        </div>
      @endif

      @if(isset($events) && $events->isNotEmpty())
        <div class="field row g-0">
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
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Level of description</h3>
          <div class="col-9 p-2">{{ $levelName }}</div>
        </div>
      @endif

      @if($io->extent_and_medium)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Extent and medium</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->extent_and_medium)) !!}</div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== 2. Context area ===== --}}
  <section id="contextArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#context-collapse">
        Context area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="context-collapse">

      {{-- Creator details --}}
      @if(isset($creators) && $creators->isNotEmpty())
        <div class="creatorHistories">
          @foreach($creators as $creator)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Name of creator(s)</h3>
              <div class="col-9 p-2">
                <a href="{{ route('actor.show', $creator->slug) }}">{{ $creator->name }}</a>
              </div>
            </div>

            @if($creator->dates_of_existence)
              <div class="field row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Dates of existence</h3>
                <div class="col-9 p-2">{{ $creator->dates_of_existence }}</div>
              </div>
            @endif

            @if($creator->history)
              <div class="field row g-0">
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
            <div class="field row g-0">
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
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Repository</h3>
          <div class="col-9 p-2">
            <a href="{{ route('repository.show', $repository->slug) }}">{{ $repository->name }}</a>
          </div>
        </div>
      @endif

      @if($io->archival_history)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Archival history</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->archival_history)) !!}</div>
        </div>
      @endif

      @if($io->acquisition)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Immediate source of acquisition or transfer</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->acquisition)) !!}</div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== 3. Content and structure area ===== --}}
  <section id="contentAndStructureArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#content-collapse">
        Content and structure area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="content-collapse">

      @if($io->scope_and_content)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Scope and content</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->scope_and_content)) !!}</div>
        </div>
      @endif

      @if($io->appraisal)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Appraisal, destruction and scheduling</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->appraisal)) !!}</div>
        </div>
      @endif

      @if($io->accruals)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Accruals</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->accruals)) !!}</div>
        </div>
      @endif

      @if($io->arrangement)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">System of arrangement</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->arrangement)) !!}</div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== 4. Conditions of access and use area ===== --}}
  <section id="conditionsOfAccessAndUseArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#conditions-collapse">
        Conditions of access and use area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="conditions-collapse">

      @if($io->access_conditions)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Conditions governing access</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->access_conditions)) !!}</div>
        </div>
      @endif

      @if($io->reproduction_conditions)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Conditions governing reproduction</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->reproduction_conditions)) !!}</div>
        </div>
      @endif

      @if(isset($languages) && $languages->isNotEmpty())
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Language of material</h3>
          <div class="col-9 p-2">
            @foreach($languages as $lang)
              {{ $lang->name }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @endif

      @if(isset($scriptsOfMaterial) && $scriptsOfMaterial->isNotEmpty())
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Script of material</h3>
          <div class="col-9 p-2">
            @foreach($scriptsOfMaterial as $script)
              {{ $script->name }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @elseif(isset($materialScripts) && (is_countable($materialScripts) ? count($materialScripts) > 0 : !empty($materialScripts)))
        <div class="field row g-0">
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
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Language and script notes</h3>
          <div class="col-9 p-2">{!! nl2br(e($lnote->content)) !!}</div>
        </div>
      @endforeach

      @if($io->physical_characteristics)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Physical characteristics and technical requirements</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->physical_characteristics)) !!}</div>
        </div>
      @endif

      @if($io->finding_aids)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Finding aids</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->finding_aids)) !!}</div>
        </div>
      @endif

      {{-- Finding aid link (generated or uploaded PDF) --}}
      @if(isset($findingAid) && $findingAid)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ $findingAid->label }}</h3>
          <div class="findingAidLink col-9 p-2">
            <a href="{{ route('informationobject.findingaid.download', $findingAid->slug) }}" target="_blank">
              <i class="fas fa-file-pdf me-1"></i>{{ $findingAid->slug }}.pdf
            </a>
          </div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== 5. Allied materials area ===== --}}
  <section id="alliedMaterialsArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#allied-collapse">
        Allied materials area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="allied-collapse">

      @if($io->location_of_originals)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Existence and location of originals</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->location_of_originals)) !!}</div>
        </div>
      @endif

      @if($io->location_of_copies)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Existence and location of copies</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->location_of_copies)) !!}</div>
        </div>
      @endif

      @if($io->related_units_of_description)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related units of description</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->related_units_of_description)) !!}</div>
        </div>
      @endif

      {{-- Related material descriptions --}}
      @if(isset($relatedMaterialDescriptions) && $relatedMaterialDescriptions->isNotEmpty())
        <div class="relatedMaterialDescriptions">
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related descriptions</h3>
            <div class="col-9 p-2">
              <ul class="m-0 ms-1 ps-3">
                @foreach($relatedMaterialDescriptions as $relatedDesc)
                  <li>
                    <a href="{{ route('informationobject.show', $relatedDesc->slug) }}">
                      {{ $relatedDesc->title ?: '[Untitled]' }}
                    </a>
                  </li>
                @endforeach
              </ul>
            </div>
          </div>
        </div>
      @endif

      {{-- Publication notes (type_id = 141) --}}
      @if(isset($notes) && $notes->isNotEmpty())
        @foreach($notes->where('type_id', 141) as $note)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Publication note</h3>
            <div class="col-9 p-2">{!! nl2br(e($note->content)) !!}</div>
          </div>
        @endforeach
      @endif

    </div>
  </section>

  {{-- ===== 6. Notes area ===== --}}
  <section id="notesArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#notes-collapse">
        Notes area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="notes-collapse">

      {{-- General notes (type_id = 137) --}}
      @if(isset($notes) && $notes->isNotEmpty())
        @foreach($notes->where('type_id', 137) as $note)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Note</h3>
            <div class="col-9 p-2">{!! nl2br(e($note->content)) !!}</div>
          </div>
        @endforeach
      @endif

      {{-- Alternative identifiers --}}
      @if(isset($alternativeIdentifiers) && (is_countable($alternativeIdentifiers) ? count($alternativeIdentifiers) > 0 : !empty($alternativeIdentifiers)))
        @foreach($alternativeIdentifiers as $altId)
          <div class="field row g-0">
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
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#access-collapse">
        Access points
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
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
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#description-collapse">
        Description control area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="description-collapse">

      @if($io->description_identifier ?? null)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Description identifier</h3>
          <div class="col-9 p-2">{{ $io->description_identifier }}</div>
        </div>
      @endif

      @if($io->institution_responsible_identifier ?? null)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Institution identifier</h3>
          <div class="col-9 p-2">{{ $io->institution_responsible_identifier }}</div>
        </div>
      @endif

      @if($io->rules ?? null)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rules and/or conventions used</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->rules)) !!}</div>
        </div>
      @endif

      @if(isset($descriptionStatusName) && $descriptionStatusName)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Status</h3>
          <div class="col-9 p-2">{{ $descriptionStatusName }}</div>
        </div>
      @endif

      @if(isset($descriptionDetailName) && $descriptionDetailName)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Level of detail</h3>
          <div class="col-9 p-2">{{ $descriptionDetailName }}</div>
        </div>
      @endif

      @if($io->revision_history ?? null)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Dates of creation revision deletion</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->revision_history)) !!}</div>
        </div>
      @endif

      @if(isset($languagesOfDescription) && (is_countable($languagesOfDescription) ? count($languagesOfDescription) > 0 : !empty($languagesOfDescription)))
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Language(s)</h3>
          <div class="col-9 p-2">
            @foreach($languagesOfDescription as $lang)
              {{ $lang }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @endif

      @if(isset($scriptsOfDescription) && (is_countable($scriptsOfDescription) ? count($scriptsOfDescription) > 0 : !empty($scriptsOfDescription)))
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Script(s)</h3>
          <div class="col-9 p-2">
            @foreach($scriptsOfDescription as $script)
              {{ $script }}@if(!$loop->last), @endif
            @endforeach
          </div>
        </div>
      @endif

      @if($io->sources ?? null)
        <div class="field row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Sources</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->sources)) !!}</div>
        </div>
      @endif

      {{-- Archivist's note (type_id = 142) --}}
      @if(isset($notes) && $notes->isNotEmpty())
        @foreach($notes->where('type_id', 142) as $note)
          <div class="field row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Archivist's note</h3>
            <div class="col-9 p-2">{!! nl2br(e($note->content)) !!}</div>
          </div>
        @endforeach
      @endif

    </div>
  </section>

  {{-- ===== 9. Rights area (authenticated only) ===== --}}
  @auth
    <div class="section border-bottom" id="rightsArea">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        Rights area
      </h2>
      <div class="relatedRights">
        @if(isset($rights) && (is_countable($rights) ? count($rights) > 0 : !empty($rights)))
          @foreach($rights as $right)
            <div class="field row g-0">
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
    </div>
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
    <div class="digitalObjectMetadata">
      <section class="border-bottom">
        <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          Digital object metadata
        </h2>
        <div>
          <div class="field row g-0">
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
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Media type</h3>
              <div class="col-9 p-2">{{ ucfirst($doMediaTypeName) }}</div>
            </div>
          @endif
          @if($doMaster->mime_type)
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">MIME type</h3>
              <div class="col-9 p-2">{{ $doMaster->mime_type }}</div>
            </div>
          @endif
          @if($doMaster->byte_size)
            <div class="field row g-0">
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
            <div class="field row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Checksum</h3>
              <div class="col-9 p-2"><code class="small">{{ $doMaster->checksum }}</code></div>
            </div>
          @endif
        </div>
      </section>
    </div>

    <div class="digitalObjectRights">
      {{-- Digital object rights display here if any --}}
    </div>
  @endif

  {{-- ===== 11. Accession area ===== --}}
  <section id="accessionArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      Accession area
    </h2>
    <div class="accessions">
      @if(isset($accessions) && (is_countable($accessions) ? count($accessions) > 0 : !empty($accessions)))
        @foreach($accessions as $accession)
          <div class="field row g-0">
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
{{-- RIGHT SIDEBAR (context-menu)                                 --}}
{{-- Matches AtoM _actionIcons.php + _contextMenu.php partials    --}}
{{-- ============================================================ --}}
@section('right')

  <nav>

    {{-- Clipboard (matches AtoM _actionIcons.php) --}}
    <section id="action-icons">
      <h4 class="h5 mb-2">Clipboard</h4>
      <ul class="list-unstyled">
        <li>
          @include('ahg-core::clipboard._button', ['slug' => $io->slug, 'type' => 'informationObject', 'wide' => true])
        </li>
      </ul>

      <h4 class="h5 mb-2">Explore</h4>
      <ul class="list-unstyled">
        <li>
          <a class="atom-icon-link" href="{{ route('informationobject.reports', $io->slug) }}">
            <i class="fas fa-fw fa-print me-1" aria-hidden="true"></i>Reports
          </a>
        </li>
        @if(isset($hasChildren) && $hasChildren)
          <li>
            <a class="atom-icon-link" href="{{ route('informationobject.inventory', $io->slug) }}">
              <i class="fas fa-fw fa-list-alt me-1" aria-hidden="true"></i>Inventory
            </a>
          </li>
        @endif
        <li>
          <a class="atom-icon-link" href="{{ route('informationobject.browse', ['collection' => $collectionRootId, 'topLod' => 0]) }}">
            <i class="fas fa-fw fa-list me-1" aria-hidden="true"></i>Browse as list
          </a>
        </li>
        @if(isset($digitalObjects) && $digitalObjects['master'])
          <li>
            <a class="atom-icon-link" href="{{ route('informationobject.browse', ['collection' => $collectionRootId, 'topLod' => 0, 'view' => 'card', 'onlyMedia' => 1]) }}">
              <i class="fas fa-fw fa-image me-1" aria-hidden="true"></i>Browse digital objects
            </a>
          </li>
        @endif
      </ul>

      @auth
        <h4 class="h5 mb-2">Import</h4>
        <ul class="list-unstyled">
          <li>
            <a class="atom-icon-link" href="{{ route('informationobject.import.xml', $io->slug) }}">
              <i class="fas fa-fw fa-download me-1" aria-hidden="true"></i>XML
            </a>
          </li>
          <li>
            <a class="atom-icon-link" href="{{ route('informationobject.import.csv', $io->slug) }}">
              <i class="fas fa-fw fa-download me-1" aria-hidden="true"></i>CSV
            </a>
          </li>
        </ul>
      @endauth

      <h4 class="h5 mb-2">Export</h4>
      <ul class="list-unstyled">
        <li>
          <a class="atom-icon-link" href="{{ route('informationobject.export.dc', $io->slug) }}">
            <i class="fas fa-fw fa-upload me-1" aria-hidden="true"></i>Dublin Core 1.1 XML
          </a>
        </li>
        <li>
          <a class="atom-icon-link" href="{{ route('informationobject.export.ead', $io->slug) }}">
            <i class="fas fa-fw fa-upload me-1" aria-hidden="true"></i>EAD 2002 XML
          </a>
        </li>
      </ul>

      {{-- Finding aid --}}
      @auth
        <h4 class="h5 mb-2">Finding aid</h4>
        <ul class="list-unstyled">
          <li>
            <a class="atom-icon-link" href="{{ route('informationobject.findingaid.generate', $io->slug) }}">
              <i class="fas fa-fw fa-cogs me-1" aria-hidden="true"></i>Generate
            </a>
          </li>
          <li>
            <a class="atom-icon-link" href="{{ route('informationobject.findingaid.upload.form', $io->slug) }}">
              <i class="fas fa-fw fa-upload me-1" aria-hidden="true"></i>Upload
            </a>
          </li>
          @if(isset($findingAid) && $findingAid)
            <li>
              <a class="atom-icon-link" href="{{ route('informationobject.findingaid.delete', $io->slug) }}">
                <i class="fas fa-fw fa-times me-1" aria-hidden="true"></i>Delete
              </a>
            </li>
            <li>
              <a class="btn atom-btn-white" href="{{ route('informationobject.findingaid.download', $io->slug) }}" target="_blank">
                <i class="fas fa-lg fa-file-pdf" aria-hidden="true"></i>
                <span class="ms-2">Download</span>
              </a>
            </li>
          @endif
        </ul>
      @endauth

      {{-- Tasks (matches AtoM _calculateDatesLink.php) --}}
      @auth
        <h4 class="h5 mb-2">Tasks</h4>
        <ul class="list-unstyled">
          <li>
            <a class="atom-icon-link" href="{{ route('informationobject.calculateDates', $io->slug) }}" title="Click 'Calculate dates' to recalculate the start and end dates of a parent-level description. A job runs in the background, accounting for the earliest and most recent dates across all the child descriptions. The results display in the Start and End fields of the edit page.">
              <i class="fas fa-fw fa-calendar me-1" aria-hidden="true"></i>Calculate dates
            </a>
          </li>
          <li>
            <i class="fas fa-fw fa-clock me-1 text-muted" aria-hidden="true"></i>Last run: {{ $io->updated_at ? \Carbon\Carbon::parse($io->updated_at)->diffForHumans() : 'Never' }}
          </li>
        </ul>
      @endauth
    </section>

    {{-- Subject access points (sidebar) --}}
    @if(isset($subjects) && $subjects->isNotEmpty())
      <h4 class="h5 mb-2">Subject access points</h4>
      <ul class="list-unstyled">
        @foreach($subjects as $subject)
          <li>
            <a href="{{ route('informationobject.browse', ['subject' => $subject->name]) }}">{{ $subject->name }}</a>
          </li>
        @endforeach
      </ul>
    @endif

    {{-- Name access points (sidebar) --}}
    @if(isset($nameAccessPoints) && $nameAccessPoints->isNotEmpty())
      <h4 class="h5 mb-2">Name access points</h4>
      <ul class="list-unstyled">
        @foreach($nameAccessPoints as $nap)
          <li>
            @if(isset($nap->slug))
              <a href="{{ route('actor.show', $nap->slug) }}">{{ $nap->name }}</a>
            @else
              {{ $nap->name }}
            @endif
          </li>
        @endforeach
      </ul>
    @endif

    {{-- Genre access points (sidebar) --}}
    @if(isset($genres) && $genres->isNotEmpty())
      <h4 class="h5 mb-2">Genre access points</h4>
      <ul class="list-unstyled">
        @foreach($genres as $genre)
          <li>{{ $genre->name }}</li>
        @endforeach
      </ul>
    @endif

    {{-- Place access points (sidebar) --}}
    @if(isset($places) && $places->isNotEmpty())
      <h4 class="h5 mb-2">Place access points</h4>
      <ul class="list-unstyled">
        @foreach($places as $place)
          <li>{{ $place->name }}</li>
        @endforeach
      </ul>
    @endif

    {{-- Physical storage --}}
    @if(isset($physicalObjects) && (is_countable($physicalObjects) ? count($physicalObjects) > 0 : !empty($physicalObjects)))
      <h4 class="h5 mb-2">Physical storage</h4>
      <ul class="list-unstyled">
        @foreach($physicalObjects as $pobj)
          <li>
            @if(isset($physicalObjectTypeNames[$pobj->type_id ?? null]))
              <strong>{{ $physicalObjectTypeNames[$pobj->type_id] }}:</strong>
            @endif
            {{ $pobj->name ?? $pobj->location ?? '[Unknown]' }}
          </li>
        @endforeach
      </ul>
    @endif

  </nav>

@endsection

{{-- ============================================================ --}}
{{-- AFTER CONTENT: Action buttons                                --}}
{{-- Matches AtoM _actions.php exactly                            --}}
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
    <li>
      <a href="{{ route('informationobject.create', ['parent_id' => $io->id, 'copy_from' => $io->id]) }}" class="btn atom-btn-outline-light">Duplicate</a>
    </li>
    <li>
      <a href="{{ url('/' . $io->slug . '/default/move') }}" class="btn atom-btn-outline-light">Move</a>
    </li>
    <li>
      <div class="dropup">
        <button type="button" class="btn atom-btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          More
        </button>
        <ul class="dropdown-menu mb-2">
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.rename', $io->slug) }}">Rename</a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ url('/' . $io->slug . '/informationobject/updatePublicationStatus') }}">Update publication status</a>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.edit', ['slug' => $io->slug, 'storage' => 1]) }}">Link physical storage</a>
          </li>
          <li><hr class="dropdown-divider"></li>
          @if(isset($digitalObjects) && $digitalObjects['master'])
            <li>
              <a class="dropdown-item" href="{{ route('io.digitalobject.show', $digitalObjects['master']->id) }}">Edit digital object</a>
            </li>
          @else
            <li>
              <a class="dropdown-item" href="{{ route('informationobject.edit', ['slug' => $io->slug, 'upload' => 1]) }}">Link digital object</a>
            </li>
          @endif
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.import.xml', $io->slug) }}">Import digital objects</a>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="{{ url('/' . $io->slug . '/right/edit') }}">Create new rights</a>
          </li>
          @if(isset($hasChildren) && $hasChildren)
            <li>
              <a class="dropdown-item" href="{{ url('/' . $io->slug . '/right/manage') }}">Manage rights inheritance</a>
            </li>
          @endif
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="{{ route('audit.browse', ['type' => 'QubitInformationObject', 'id' => $io->id]) }}">View modification history</a>
          </li>
        </ul>
      </div>
    </li>
  </ul>
  @endauth
@endsection
