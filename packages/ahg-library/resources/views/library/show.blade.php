@extends('theme::layouts.3col')

@section('title', $item->title ?? 'Library item')
@section('body-class', 'view library')

{{-- ============================================================ --}}
{{-- LEFT SIDEBAR: Holdings / navigation                          --}}
{{-- ============================================================ --}}
@section('sidebar')

  {{-- Repository logo (if available) --}}
  @if($item->repository_id)
    @php
      $repoSlug = \Illuminate\Support\Facades\DB::table('slug')
        ->where('object_id', $item->repository_id)
        ->where('slug', '!=', '')
        ->value('slug');
    @endphp
    @if($repoSlug)
      @php
        $repoLogo = \Illuminate\Support\Facades\DB::table('digital_object')
          ->where('object_id', $item->repository_id)
          ->first();
        $repoLogoUrl = $repoLogo ? \AhgCore\Services\DigitalObjectService::getUrl($repoLogo) : null;
      @endphp
      @if($repoLogoUrl)
        <div class="text-center mb-3">
          <a href="{{ route('repository.show', $repoSlug) }}">
            <img src="{{ $repoLogoUrl }}" alt="Repository logo" class="img-fluid" style="max-height:80px;">
          </a>
        </div>
      @endif
    @endif
  @endif

  {{-- Holdings / hierarchy navigation --}}
  @php
    $children = \Illuminate\Support\Facades\DB::table('information_object')
      ->where('parent_id', $item->id)
      ->get();
    $totalChildren = $children->count();
    $hasMany = $totalChildren > 10;
  @endphp
  <section class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
      <i class="fas fa-book me-1"></i> Holdings
      @if($totalChildren > 0)
        <span class="badge bg-light text-dark float-end">{{ $totalChildren }}</span>
      @endif
    </div>
    <div class="card-body p-0">
      {{-- Parent link --}}
      @if($parentItem)
        <div class="list-group list-group-flush mb-0">
          <a href="{{ route('library.show', $parentItem->slug) }}" class="list-group-item list-group-item-action">
            <i class="fas fa-level-up-alt me-2"></i>
            {{ $parentItem->title ?? '[Untitled]' }}
          </a>
        </div>
      @endif

      {{-- Current item header --}}
      <div class="list-group-item active">
        <i class="fas fa-book me-2"></i>
        {{ $item->title ?? '[Untitled]' }}
      </div>

      {{-- Children --}}
      @if($totalChildren > 0)
        <div class="{{ $hasMany ? 'has-scroll' : '' }}" style="{{ $hasMany ? 'max-height: 300px; overflow-y: auto;' : '' }}">
          <ul class="list-group list-group-flush">
            @foreach($children as $child)
              @php
                $childSlug = \Illuminate\Support\Facades\DB::table('slug')
                  ->where('object_id', $child->id)
                  ->value('slug');
                $childTitle = \Illuminate\Support\Facades\DB::table('information_object_i18n')
                  ->where('id', $child->id)
                  ->value('title') ?: '[Untitled]';
              @endphp
              <li class="list-group-item list-group-item-action ps-4 py-2">
                <i class="fas fa-file me-2 text-muted"></i>
                @if($childSlug)
                  <a href="{{ route('library.show', $childSlug) }}">{{ $childTitle }}</a>
                @else
                  {{ $childTitle }}
                @endif
              </li>
            @endforeach
          </ul>
        </div>
        @if($hasMany)
          <div class="text-center text-muted small mt-2 mb-2">
            <i class="fas fa-arrows-alt-v me-1"></i>Scroll to see all {{ $totalChildren }} items
          </div>
        @endif
      @endif
    </div>
  </section>

  {{-- Library navigation --}}
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
      <i class="fas fa-book me-1"></i> Library
    </div>
    <div class="list-group list-group-flush">
      <a href="{{ route('library.browse') }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-list me-1"></i> Browse all items
      </a>
      @if($item->material_type)
        <a href="{{ route('library.browse', ['material_type' => $item->material_type]) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-filter me-1"></i> Same material type
        </a>
      @endif
    </div>
  </div>

  {{-- Creators sidebar --}}
  @if($creators->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
        <i class="fas fa-user me-1"></i> Creators
      </div>
      <div class="list-group list-group-flush">
        @foreach($creators as $creator)
          <a href="{{ $creator->slug ? route('actor.show', $creator->slug) : '#' }}" class="list-group-item list-group-item-action small">
            {{ $creator->name ?? '[Unknown]' }}
            @if($creator->role)
              <span class="badge bg-secondary float-end">{{ ucfirst($creator->role) }}</span>
            @endif
          </a>
        @endforeach
      </div>
    </div>
  @endif

  {{-- Subjects sidebar --}}
  @if($subjects->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
        <i class="fas fa-tags me-1"></i> Subjects
      </div>
      <div class="list-group list-group-flush">
        @foreach($subjects as $subject)
          <span class="list-group-item small">{{ $subject->name ?? '[Unknown]' }}</span>
        @endforeach
      </div>
    </div>
  @endif

  {{-- External links sidebar --}}
  @if($item->openlibrary_url || $item->ebook_preview_url || $item->openlibrary_id || $item->goodreads_id || $item->librarything_id)
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
        <i class="fas fa-external-link-alt me-1"></i> External links
      </div>
      <div class="list-group list-group-flush">
        @if($item->openlibrary_url)
          <a href="{{ $item->openlibrary_url }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-book-open me-1"></i> OpenLibrary
          </a>
        @elseif($item->openlibrary_id)
          <a href="https://openlibrary.org/works/{{ $item->openlibrary_id }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-book-open me-1"></i> OpenLibrary
          </a>
        @endif
        @if($item->goodreads_id)
          <a href="https://www.goodreads.com/book/show/{{ $item->goodreads_id }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-star me-1"></i> Goodreads
          </a>
        @endif
        @if($item->librarything_id)
          <a href="https://www.librarything.com/work/{{ $item->librarything_id }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-bookmark me-1"></i> LibraryThing
          </a>
        @endif
        @if($item->ebook_preview_url)
          <a href="{{ $item->ebook_preview_url }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-book-reader me-1"></i> E-book preview
          </a>
        @endif
      </div>
    </div>
  @endif

  {{-- Collections Management sidebar --}}
  @auth
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
        <i class="fas fa-clipboard-check me-1"></i> Collections Management
      </div>
      <div class="list-group list-group-flush">
        @if(\Illuminate\Support\Facades\Route::has('condition.check'))
          <a href="{{ route('condition.check', $item->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-clipboard-check me-2"></i>Condition assessment
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('spectrum.show'))
          <a href="{{ route('spectrum.show', $item->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-layer-group me-2"></i>Spectrum data
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('spectrum.workflow'))
          <a href="{{ route('spectrum.workflow', $item->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-tasks me-2"></i>Workflow Status
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('provenance.view'))
          <a href="{{ route('provenance.view', $item->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-sitemap me-2"></i>Provenance
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('research.cite'))
          <a href="{{ route('research.cite', $item->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-quote-left me-2"></i>Cite this Record
          </a>
        @endif
      </div>
    </div>
  @endauth

  {{-- Named Entity Recognition sidebar --}}
  @auth
    @if(\Illuminate\Support\Facades\Route::has('ner.review'))
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
          <i class="fas fa-brain me-1"></i> Named Entity Recognition
        </div>
        <div class="list-group list-group-flush">
          <a href="#" onclick="if(typeof extractEntities==='function')extractEntities({{ $item->id }});return false;" class="list-group-item list-group-item-action small">
            <i class="bi bi-cpu me-1"></i> Extract Entities
          </a>
          <a href="{{ route('ner.review') }}" class="list-group-item list-group-item-action small">
            <i class="bi bi-list-check me-1"></i> Review Dashboard
          </a>
        </div>
      </div>
    @endif
  @endauth

@endsection

{{-- ============================================================ --}}
{{-- MAIN CONTENT: Field sections (card-based like AtoM)          --}}
{{-- ============================================================ --}}
@section('content')

<link rel="stylesheet" href="/vendor/ahg-core/css/tts.css">
<script src="/vendor/ahg-core/js/tts.js"></script>

<div id="tts-content-area" data-tts-content>

  {{-- Basic Information --}}
  <section class="card mb-4">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="fas fa-book me-2"></i>Basic Information</h5>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-4">Title</dt>
        <dd class="col-sm-8">{{ $item->title ?: '[Untitled]' }}</dd>

        @if($item->subtitle)
          <dt class="col-sm-4">Subtitle</dt>
          <dd class="col-sm-8">{{ $item->subtitle }}</dd>
        @endif

        @if($item->responsibility_statement)
          <dt class="col-sm-4">Statement of responsibility</dt>
          <dd class="col-sm-8">{{ $item->responsibility_statement }}</dd>
        @endif

        @if($item->identifier)
          <dt class="col-sm-4">Identifier</dt>
          <dd class="col-sm-8">{{ $item->identifier }}</dd>
        @endif

        @if($levelName)
          <dt class="col-sm-4">Level of description</dt>
          <dd class="col-sm-8">{{ $levelName }}</dd>
        @endif

        @if($item->material_type)
          <dt class="col-sm-4">Material type</dt>
          <dd class="col-sm-8">{{ ucfirst($item->material_type) }}</dd>
        @endif

        @if($item->language)
          <dt class="col-sm-4">Language</dt>
          <dd class="col-sm-8">{{ $item->language }}</dd>
        @endif
      </dl>
    </div>
  </section>

  {{-- Creators / Authors --}}
  @if($creators->isNotEmpty())
    <section class="card mb-4">
      <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Creators / Authors</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          @foreach($creators as $creator)
            <li class="mb-2">
              <strong>{{ $creator->name ?? '[Unknown]' }}</strong>
              <span class="badge bg-secondary ms-2">{{ ucfirst($creator->role ?? 'Author') }}</span>
              @if(!empty($creator->authority_uri))
                <a href="{{ $creator->authority_uri }}" target="_blank" class="ms-2" title="View authority record">
                  <i class="fas fa-external-link-alt"></i>
                </a>
              @endif
            </li>
          @endforeach
        </ul>
      </div>
    </section>
  @endif

  {{-- Standard Identifiers --}}
  @if($item->isbn || $item->issn || $item->doi || $item->lccn || $item->oclc_number || $item->barcode || $item->openlibrary_id)
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-barcode me-2"></i>Standard Identifiers</h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          @if($item->isbn)
            <dt class="col-sm-4">ISBN</dt>
            <dd class="col-sm-8"><code>{{ $item->isbn }}</code></dd>
          @endif

          @if($item->issn)
            <dt class="col-sm-4">ISSN</dt>
            <dd class="col-sm-8"><code>{{ $item->issn }}</code></dd>
          @endif

          @if($item->doi)
            <dt class="col-sm-4">DOI</dt>
            <dd class="col-sm-8">
              <a href="https://doi.org/{{ $item->doi }}" target="_blank">{{ $item->doi }}</a>
            </dd>
          @endif

          @if($item->lccn)
            <dt class="col-sm-4">LCCN</dt>
            <dd class="col-sm-8">{{ $item->lccn }}</dd>
          @endif

          @if($item->oclc_number)
            <dt class="col-sm-4">OCLC</dt>
            <dd class="col-sm-8">
              <a href="https://www.worldcat.org/oclc/{{ $item->oclc_number }}" target="_blank">{{ $item->oclc_number }}</a>
            </dd>
          @endif

          @if($item->barcode)
            <dt class="col-sm-4">Barcode</dt>
            <dd class="col-sm-8"><code>{{ $item->barcode }}</code></dd>
          @endif

          @if($item->openlibrary_id)
            <dt class="col-sm-4">Open Library</dt>
            <dd class="col-sm-8">
              <a href="https://openlibrary.org/books/{{ $item->openlibrary_id }}" target="_blank">{{ $item->openlibrary_id }}</a>
            </dd>
          @endif
        </dl>
      </div>
    </section>
  @endif

  {{-- Classification --}}
  @if($item->call_number || $item->dewey_decimal || $item->classification_scheme || $item->shelf_location || $item->copy_number || $item->volume_designation || $item->classification_number || $item->cutter_number)
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Classification</h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          @if($item->call_number)
            <dt class="col-sm-4">Call number</dt>
            <dd class="col-sm-8"><code>{{ $item->call_number }}</code></dd>
          @endif

          @if($item->dewey_decimal)
            <dt class="col-sm-4">Dewey Decimal</dt>
            <dd class="col-sm-8"><code>{{ $item->dewey_decimal }}</code></dd>
          @endif

          @if($item->classification_scheme)
            <dt class="col-sm-4">Classification scheme</dt>
            <dd class="col-sm-8">{{ strtoupper($item->classification_scheme) }}</dd>
          @endif

          @if($item->classification_number)
            <dt class="col-sm-4">Classification number</dt>
            <dd class="col-sm-8">{{ $item->classification_number }}</dd>
          @endif

          @if($item->cutter_number)
            <dt class="col-sm-4">Cutter number</dt>
            <dd class="col-sm-8">{{ $item->cutter_number }}</dd>
          @endif

          @if($item->shelf_location)
            <dt class="col-sm-4">Shelf location</dt>
            <dd class="col-sm-8">{{ $item->shelf_location }}</dd>
          @endif

          @if($item->copy_number)
            <dt class="col-sm-4">Copy</dt>
            <dd class="col-sm-8">{{ $item->copy_number }}</dd>
          @endif

          @if($item->volume_designation)
            <dt class="col-sm-4">Volume</dt>
            <dd class="col-sm-8">{{ $item->volume_designation }}</dd>
          @endif
        </dl>
      </div>
    </section>
  @endif

  {{-- Publication Information --}}
  @if($item->publisher || $item->publication_place || $item->publication_date || $item->edition || $item->edition_statement || $item->series_title)
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-building me-2"></i>Publication Information</h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          @if($item->publisher)
            <dt class="col-sm-4">Publisher</dt>
            <dd class="col-sm-8">{{ $item->publisher }}</dd>
          @endif

          @if($item->publication_place)
            <dt class="col-sm-4">Place</dt>
            <dd class="col-sm-8">{{ $item->publication_place }}</dd>
          @endif

          @if($item->publication_date)
            <dt class="col-sm-4">Date</dt>
            <dd class="col-sm-8">{{ $item->publication_date }}</dd>
          @endif

          @if($item->edition)
            <dt class="col-sm-4">Edition</dt>
            <dd class="col-sm-8">{{ $item->edition }}</dd>
          @endif

          @if($item->edition_statement)
            <dt class="col-sm-4">Edition statement</dt>
            <dd class="col-sm-8">{{ $item->edition_statement }}</dd>
          @endif

          @if($item->series_title)
            <dt class="col-sm-4">Series</dt>
            <dd class="col-sm-8">
              {{ $item->series_title }}
              @if($item->series_number)
                <span class="text-muted">({{ $item->series_number }})</span>
              @endif
            </dd>
          @endif
        </dl>
      </div>
    </section>
  @endif

  {{-- Physical Description --}}
  @if($item->pagination || $item->dimensions || $item->physical_details)
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-ruler me-2"></i>Physical Description</h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          @if($item->pagination)
            <dt class="col-sm-4">Extent</dt>
            <dd class="col-sm-8">{{ $item->pagination }}</dd>
          @endif

          @if($item->dimensions)
            <dt class="col-sm-4">Dimensions</dt>
            <dd class="col-sm-8">{{ $item->dimensions }}</dd>
          @endif

          @if($item->physical_details)
            <dt class="col-sm-4">Physical details</dt>
            <dd class="col-sm-8">{{ $item->physical_details }}</dd>
          @endif
        </dl>
      </div>
    </section>
  @endif

  {{-- Subjects --}}
  @if($subjects->isNotEmpty())
    <section class="card mb-4">
      <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Subjects</h5>
      </div>
      <div class="card-body">
        @foreach($subjects as $subject)
          @if($subject->uri ?? null)
            <a href="{{ $subject->uri }}" target="_blank" class="badge bg-secondary text-decoration-none me-1 mb-1">
              {{ $subject->name ?? '[Unknown]' }}
            </a>
          @elseif($subject->slug ?? null)
            <a href="{{ route('term.show', $subject->slug) }}" class="badge bg-secondary me-1 mb-1 text-decoration-none">{{ $subject->name ?? '[Unknown]' }}</a>
          @else
            <span class="badge bg-secondary me-1 mb-1">{{ $subject->name ?? '[Unknown]' }}</span>
          @endif
        @endforeach
      </div>
    </section>
  @endif

  {{-- Content --}}
  @php
    $summary = $item->summary ?? '';
    $scopeAndContent = $item->scope_and_content ?? '';
    $contentsNote = $item->contents_note ?? '';
  @endphp
  @if(!empty($summary) || !empty($scopeAndContent) || !empty($contentsNote))
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-align-left me-2"></i>Content</h5>
      </div>
      <div class="card-body">
        @if(!empty($summary))
          <h6>Summary</h6>
          <p>{!! nl2br(e($summary)) !!}</p>
        @endif

        @if(!empty($scopeAndContent))
          <h6>Scope and content</h6>
          <p>{!! nl2br(e($scopeAndContent)) !!}</p>
        @endif

        @if(!empty($contentsNote))
          <h6>Table of contents</h6>
          <p>{!! nl2br(e($contentsNote)) !!}</p>
        @endif
      </div>
    </section>
  @endif

  {{-- Notes --}}
  @if($item->general_note || $item->bibliography_note)
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes</h5>
      </div>
      <div class="card-body">
        @if($item->general_note)
          <p>{!! nl2br(e($item->general_note)) !!}</p>
        @endif

        @if($item->bibliography_note)
          <p class="text-muted"><em>{!! nl2br(e($item->bibliography_note)) !!}</em></p>
        @endif
      </div>
    </section>
  @endif

</div>{{-- /tts-content-area --}}

@endsection

{{-- ============================================================ --}}
{{-- RIGHT SIDEBAR: Digital object, Actions, Barcode, Related     --}}
{{-- ============================================================ --}}
@section('right')

<nav>

  {{-- Digital Object Viewer --}}
  @php
    $doRecords = \Illuminate\Support\Facades\DB::table('digital_object')
      ->where('object_id', $item->id)
      ->get();
    $masterObj = null;
    $refObj = null;
    $thumbObj = null;
    foreach ($doRecords as $doRec) {
      if (($doRec->usage_id ?? null) == 166) $masterObj = $doRec;      // QubitTerm::MASTER_ID
      elseif (($doRec->usage_id ?? null) == 167) $refObj = $doRec;     // QubitTerm::REFERENCE_ID
      elseif (($doRec->usage_id ?? null) == 168) $thumbObj = $doRec;   // QubitTerm::THUMBNAIL_ID
      elseif (!$masterObj) $masterObj = $doRec;
    }
    $hasDigitalObject = $doRecords->isNotEmpty();
  @endphp

  @if($masterObj || $refObj || $thumbObj)
    @php
      $masterUrl = $masterObj ? \AhgCore\Services\DigitalObjectService::getUrl($masterObj) : '';
      $refUrl = $refObj ? \AhgCore\Services\DigitalObjectService::getUrl($refObj) : '';
      $thumbUrl = $thumbObj ? \AhgCore\Services\DigitalObjectService::getUrl($thumbObj) : '';
      $masterMediaType = $masterObj ? \AhgCore\Services\DigitalObjectService::getMediaType($masterObj) : null;
      $isPdf = $masterObj && ($masterObj->mime_type ?? '') === 'application/pdf';

      $nonNativeVideo = ['video/x-ms-wmv', 'video/x-ms-asf', 'video/x-msvideo', 'video/quicktime',
          'video/x-flv', 'video/x-matroska', 'video/mp2t', 'video/x-ms-wtv', 'video/hevc',
          'application/mxf', 'video/3gpp', 'video/avi'];
      $nonNativeAudio = ['audio/aiff', 'audio/x-aiff', 'audio/basic', 'audio/x-au',
          'audio/ac3', 'audio/x-ms-wma', 'audio/x-pn-realaudio'];
      $masterMime = $masterObj->mime_type ?? '';
      $needsStreaming = in_array($masterMime, $nonNativeVideo) || in_array($masterMime, $nonNativeAudio);
      $videoSrc = ($needsStreaming && $refObj) ? $refUrl : $masterUrl;
      $videoMime = ($needsStreaming && $refObj) ? ($refObj->mime_type ?? 'video/mp4') : $masterMime;

      $is3DModel = $masterObj && in_array(strtolower($masterMime), [
          'model/gltf-binary', 'model/gltf+json', 'model/gltf', 'application/octet-stream',
      ]);
      if (!$is3DModel && $masterObj && ($masterObj->name ?? null)) {
          $ext = strtolower(pathinfo($masterObj->name, PATHINFO_EXTENSION));
          $is3DModel = in_array($ext, ['glb', 'gltf']);
      }
    @endphp

    <section class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff;">
        <h5 class="mb-0"><i class="fas fa-image me-2"></i>Cover</h5>
      </div>
      <div class="card-body text-center p-2">
        @if($isPdf)
          {{-- PDF: embedded iframe viewer with toolbar --}}
          <div class="pdf-viewer-container" style="overflow:hidden;">
            <div class="pdf-wrapper">
              <div class="pdf-toolbar mb-2 d-flex justify-content-between align-items-center">
                <span class="badge bg-danger">
                  <i class="fas fa-file-pdf me-1"></i>PDF Document
                </span>
                <div class="btn-group btn-group-sm">
                  <a href="{{ $masterUrl }}" target="_blank" class="btn atom-btn-white" title="Open in new tab">
                    <i class="fas fa-external-link-alt"></i>
                  </a>
                  <a href="{{ $masterUrl }}" download class="btn atom-btn-white" title="Download PDF">
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
          <video id="ahg-video-player" controls class="w-100" style="max-height:500px; background:#000;" preload="metadata"
                 @if($thumbUrl) poster="{{ $thumbUrl }}" @endif>
            <source src="{{ $videoSrc }}" type="{{ $videoMime }}">
            @if($needsStreaming && $videoSrc !== $masterUrl)
              <source src="{{ $masterUrl }}" type="{{ $masterMime }}">
            @endif
            Your browser does not support this video format.
          </video>
          <div class="mt-2 d-flex justify-content-between align-items-center">
            <div>
              <span class="badge bg-secondary">{{ $masterObj->name ?? '' }}</span>
              <span class="badge bg-light text-dark">{{ $masterMime }}</span>
              @if($masterObj->byte_size ?? 0)
                <span class="badge bg-light text-dark">{{ \AhgCore\Services\DigitalObjectService::formatFileSize($masterObj->byte_size) }}</span>
              @endif
            </div>
            @auth
              <a href="{{ $masterUrl }}" download class="btn btn-sm atom-btn-white">
                <i class="fas fa-download me-1"></i>Download video
              </a>
            @endauth
          </div>

        @elseif($masterMediaType === 'audio')
          {{-- Audio: Enhanced player --}}
          @php
            $audioSrc = $needsStreaming && $refObj ? $refUrl : $masterUrl;
            $audioMime = $needsStreaming && $refObj ? ($refObj->mime_type ?? 'audio/mpeg') : $masterMime;
            $audioPlayerId = 'ahg-audio-' . $item->id;
          @endphp
          <div class="ahg-media-player rounded p-3" style="background:linear-gradient(135deg,#1a1a2e,#16213e);">
            <audio id="{{ $audioPlayerId }}" preload="metadata" style="display:none;">
              <source src="{{ $audioSrc }}" type="{{ $audioMime }}">
              @if($needsStreaming && $audioSrc !== $masterUrl)
                <source src="{{ $masterUrl }}" type="{{ $masterMime }}">
              @endif
            </audio>
            <div id="{{ $audioPlayerId }}-progress" class="mb-3" style="cursor:pointer;height:60px;background:rgba(255,255,255,0.05);border-radius:6px;position:relative;overflow:hidden;">
              <div id="{{ $audioPlayerId }}-fill" style="height:100%;width:0%;background:linear-gradient(90deg,rgba(13,110,253,0.4),rgba(13,110,253,0.15));position:absolute;transition:width 0.1s;"></div>
              <div class="d-flex align-items-center justify-content-center h-100 position-relative">
                <i class="fas fa-music fa-2x text-white" style="opacity:0.15;"></i>
              </div>
            </div>
            <div class="d-flex justify-content-between text-white mb-2" style="font-size:0.8rem;opacity:0.7;">
              <span id="{{ $audioPlayerId }}-current">0:00</span>
              <span id="{{ $audioPlayerId }}-duration">0:00</span>
            </div>
            <div class="d-flex align-items-center justify-content-center gap-2">
              <button class="btn btn-sm btn-outline-light" id="{{ $audioPlayerId }}-back" title="Back 10s">
                <i class="fas fa-backward"></i> 10s
              </button>
              <button class="btn btn-lg btn-light rounded-circle" id="{{ $audioPlayerId }}-play" title="Play/Pause" style="width:50px;height:50px;">
                <i class="fas fa-play" id="{{ $audioPlayerId }}-play-icon"></i>
              </button>
              <button class="btn btn-sm btn-outline-light" id="{{ $audioPlayerId }}-fwd" title="Forward 10s">
                10s <i class="fas fa-forward"></i>
              </button>
              <div class="ms-3 d-flex align-items-center gap-1">
                <span class="text-white small">Speed:</span>
                <select id="{{ $audioPlayerId }}-speed" class="form-select form-select-sm" style="width:70px;background:rgba(255,255,255,0.1);color:#fff;border-color:rgba(255,255,255,0.2);">
                  <option value="0.5">0.5x</option>
                  <option value="0.75">0.75x</option>
                  <option value="1" selected>1x</option>
                  <option value="1.25">1.25x</option>
                  <option value="1.5">1.5x</option>
                  <option value="2">2x</option>
                </select>
              </div>
              <div class="ms-2 d-flex align-items-center gap-1">
                <i class="fas fa-volume-up text-white" style="opacity:0.7;"></i>
                <input type="range" id="{{ $audioPlayerId }}-vol" class="form-range" style="width:80px;" min="0" max="1" step="0.05" value="1">
              </div>
            </div>
            <div class="mt-3 d-flex justify-content-between align-items-center">
              <div>
                <span class="badge bg-secondary">{{ $masterObj->name ?? '' }}</span>
                <span class="badge" style="background:rgba(255,255,255,0.1);color:#ccc;">{{ $masterMime }}</span>
                @if($masterObj->byte_size ?? 0)
                  <span class="badge" style="background:rgba(255,255,255,0.1);color:#ccc;">{{ \AhgCore\Services\DigitalObjectService::formatFileSize($masterObj->byte_size) }}</span>
                @endif
              </div>
              @auth
                <a href="{{ $masterUrl }}" download class="btn btn-sm btn-outline-light">
                  <i class="fas fa-download me-1"></i>Download
                </a>
              @endauth
            </div>
          </div>
          <script>
          document.addEventListener('DOMContentLoaded', function() {
            var audio = document.getElementById('{{ $audioPlayerId }}');
            var playBtn = document.getElementById('{{ $audioPlayerId }}-play');
            var playIcon = document.getElementById('{{ $audioPlayerId }}-play-icon');
            var backBtn = document.getElementById('{{ $audioPlayerId }}-back');
            var fwdBtn = document.getElementById('{{ $audioPlayerId }}-fwd');
            var speedSel = document.getElementById('{{ $audioPlayerId }}-speed');
            var volRange = document.getElementById('{{ $audioPlayerId }}-vol');
            var progress = document.getElementById('{{ $audioPlayerId }}-progress');
            var fill = document.getElementById('{{ $audioPlayerId }}-fill');
            var curTime = document.getElementById('{{ $audioPlayerId }}-current');
            var durTime = document.getElementById('{{ $audioPlayerId }}-duration');
            if (!audio) return;
            function fmt(s) { var m = Math.floor(s/60); return m + ':' + String(Math.floor(s%60)).padStart(2,'0'); }
            audio.addEventListener('loadedmetadata', function() { durTime.textContent = fmt(audio.duration); });
            audio.addEventListener('timeupdate', function() {
              curTime.textContent = fmt(audio.currentTime);
              if (audio.duration) fill.style.width = (audio.currentTime / audio.duration * 100) + '%';
            });
            audio.addEventListener('ended', function() { playIcon.className = 'fas fa-play'; });
            playBtn.addEventListener('click', function() {
              if (audio.paused) { audio.play(); playIcon.className = 'fas fa-pause'; }
              else { audio.pause(); playIcon.className = 'fas fa-play'; }
            });
            backBtn.addEventListener('click', function() { audio.currentTime = Math.max(0, audio.currentTime - 10); });
            fwdBtn.addEventListener('click', function() { audio.currentTime = Math.min(audio.duration || 0, audio.currentTime + 10); });
            speedSel.addEventListener('change', function() { audio.playbackRate = parseFloat(this.value); });
            volRange.addEventListener('input', function() { audio.volume = parseFloat(this.value); });
            progress.addEventListener('click', function(e) {
              var rect = this.getBoundingClientRect();
              var pct = (e.clientX - rect.left) / rect.width;
              if (audio.duration) audio.currentTime = pct * audio.duration;
            });
          });
          </script>

        @elseif($is3DModel)
          {{-- 3D Model: model-viewer --}}
          @php $modelViewerId = 'model-3d-' . ($masterObj->id ?? uniqid()); @endphp
          <div class="digitalObject3D">
            <div class="d-flex flex-column align-items-center">
              <div class="mb-2">
                <span class="badge bg-primary"><i class="fas fa-cube me-1"></i>3D Model</span>
                <span class="badge bg-secondary">{{ $masterObj->name ?? '3D Model' }}</span>
              </div>
              <div id="{{ $modelViewerId }}-container" style="width:100%;height:400px;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);border-radius:8px;position:relative;">
                <model-viewer
                  id="{{ $modelViewerId }}"
                  src="{{ $masterUrl }}"
                  camera-controls
                  touch-action="pan-y"
                  shadow-intensity="1"
                  exposure="1"
                  style="width:100%;height:100%;background:transparent;border-radius:8px;"
                  alt="3D model">
                </model-viewer>
              </div>
              <small class="text-muted mt-2">
                <i class="fas fa-mouse me-1"></i>Drag to rotate | <i class="fas fa-search-plus me-1"></i>Scroll to zoom
              </small>
              <div class="mt-2 d-flex gap-2">
                <a href="{{ $masterUrl }}" download class="btn btn-sm btn-outline-secondary">
                  <i class="fas fa-download me-1"></i>Download Original
                </a>
              </div>
            </div>
          </div>

        @elseif($masterMediaType === 'image' || in_array($masterMime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/tiff', 'image/svg+xml']))
          {{-- Image: img with lightbox link --}}
          <a href="{{ $masterUrl }}" target="_blank">
            <img src="{{ $refUrl ?: $masterUrl }}" alt="{{ $item->title }}" class="img-fluid img-thumbnail" style="max-height:400px;">
          </a>

        @else
          {{-- Other file: show info and download --}}
          <div class="py-4">
            <i class="fas fa-file fa-3x text-muted mb-3 d-block"></i>
            <p class="text-muted">{{ $masterObj->name ?? 'Digital object' }}</p>
            @auth
              <a href="{{ $masterUrl }}" download class="btn atom-btn-white">
                <i class="fas fa-download me-1"></i>Download file
              </a>
            @endauth
          </div>
        @endif
      </div>
    </section>

  @elseif($item->cover_url)
    {{-- No digital object but cover_url exists: show cover image --}}
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff;">
        <h5 class="mb-0"><i class="fas fa-image me-2"></i>Cover</h5>
      </div>
      <div class="card-body text-center p-2">
        <a href="{{ $item->cover_url_original ?: $item->cover_url }}" target="_blank">
          <img src="{{ $item->cover_url }}" alt="{{ $item->title }}" class="img-fluid img-thumbnail" style="max-height:300px;">
        </a>
      </div>
    </div>
  @endif

  {{-- User Actions (compact with tooltips) --}}
  @php
    $userId = auth()->id();
    $favoriteId = null;
    $cartId = null;
    $pdfDigitalObject = \Illuminate\Support\Facades\DB::table('digital_object')
      ->where('object_id', $item->id)
      ->where('mime_type', 'application/pdf')
      ->first();
    if ($userId) {
      $favoriteId = \Illuminate\Support\Facades\DB::table('favorites')
        ->where('user_id', $userId)
        ->where('archival_description_id', $item->id)
        ->value('id');
      $cartId = \Illuminate\Support\Facades\DB::table('cart')
        ->where('user_id', $userId)
        ->where('archival_description_id', $item->id)
        ->whereNull('completed_at')
        ->value('id');
    }
  @endphp
  <div class="d-flex flex-wrap gap-1 mb-3 align-items-center">
    {{-- Clipboard --}}
    @include('ahg-core::clipboard._button', ['slug' => $item->slug, 'type' => 'library', 'wide' => false])
    {{-- TTS --}}
    <button type="button" class="btn btn-sm btn-outline-secondary" data-tts-action="toggle" data-tts-target="#tts-content-area" title="Read metadata aloud" data-bs-toggle="tooltip"><i class="fas fa-volume-up"></i></button>
    {{-- TTS for PDF --}}
    @if($pdfDigitalObject)
      <button type="button" class="btn btn-sm btn-outline-info" data-tts-action="read-pdf" data-tts-pdf-id="{{ $pdfDigitalObject->id }}" title="Read PDF content aloud" data-bs-toggle="tooltip"><i class="fas fa-file-pdf"></i></button>
    @endif
    {{-- Print --}}
    <button type="button" class="btn btn-sm atom-btn-white" onclick="window.print();" title="Print this page" data-bs-toggle="tooltip">
      <i class="fas fa-print"></i>
    </button>
    {{-- Favorites --}}
    @auth
      @if($favoriteId)
        <a href="{{ \Illuminate\Support\Facades\Route::has('favorites.remove') ? route('favorites.remove', $favoriteId) : url('/favorites/remove/' . $favoriteId) }}" class="btn btn-xs btn-outline-danger" title="Remove from Favorites" data-bs-toggle="tooltip"><i class="fas fa-heart-broken"></i></a>
      @else
        <a href="{{ \Illuminate\Support\Facades\Route::has('favorites.add') ? route('favorites.add', $item->slug) : url('/favorites/add/' . $item->slug) }}" class="btn btn-xs btn-outline-danger" title="Add to Favorites" data-bs-toggle="tooltip"><i class="fas fa-heart"></i></a>
      @endif
    @endauth
    {{-- Feedback --}}
    @if(\Illuminate\Support\Facades\Route::has('feedback.submit'))
      <a href="{{ route('feedback.submit', $item->slug) }}" class="btn btn-xs btn-outline-secondary" title="Item Feedback" data-bs-toggle="tooltip"><i class="fas fa-comment"></i></a>
    @endif
    {{-- Request to Publish --}}
    @if($hasDigitalObject && \Illuminate\Support\Facades\Route::has('request-to-publish.submit'))
      <a href="{{ route('request-to-publish.submit', $item->slug) }}" class="btn btn-xs btn-outline-primary" title="Request to Publish" data-bs-toggle="tooltip"><i class="fas fa-paper-plane"></i></a>
    @endif
    {{-- Cart --}}
    @if($hasDigitalObject)
      @if($cartId)
        <a href="{{ \Illuminate\Support\Facades\Route::has('cart.browse') ? route('cart.browse') : url('/cart') }}" class="btn btn-xs btn-outline-success" title="Go to Cart" data-bs-toggle="tooltip"><i class="fas fa-shopping-cart"></i></a>
      @else
        <a href="{{ \Illuminate\Support\Facades\Route::has('cart.add') ? route('cart.add', $item->slug) : url('/cart/add/' . $item->slug) }}" class="btn btn-xs btn-outline-success" title="Add to Cart" data-bs-toggle="tooltip"><i class="fas fa-cart-plus"></i></a>
      @endif
    @endif
    {{-- Loans --}}
    @auth
      @if(\Illuminate\Support\Facades\Route::has('loan.add'))
        <a href="{{ route('loan.add', ['type' => 'out', 'sector' => 'library', 'object_id' => $item->id]) }}" class="btn btn-xs btn-outline-warning" title="New Loan" data-bs-toggle="tooltip"><i class="fas fa-hand-holding"></i></a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('loan.index'))
        <a href="{{ route('loan.index', ['sector' => 'library', 'object_id' => $item->id]) }}" class="btn btn-xs btn-outline-info" title="Manage Loans" data-bs-toggle="tooltip"><i class="fas fa-exchange-alt"></i></a>
      @endif
    @endauth
  </div>

  {{-- Actions (authenticated users only) --}}
  @auth
    <section class="card mb-3">
      <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Actions</h5>
      </div>
      <div class="card-body">
        <a href="{{ route('library.edit', $item->slug) }}" class="btn btn-primary w-100 mb-2">
          <i class="fas fa-edit me-2"></i>Edit
        </a>
        <a href="{{ route('library.create', ['parent' => $item->slug]) }}" class="btn btn-success w-100 mb-2">
          <i class="fas fa-plus me-2"></i>Add new
        </a>
        <form action="{{ route('library.destroy', $item->slug) }}" method="POST"
              onsubmit="return confirm('Are you sure you want to delete this library item?');">
          @csrf
          <button type="submit" class="btn btn-danger w-100 mb-2">
            <i class="fas fa-trash me-2"></i>Delete
          </button>
        </form>
        <a href="{{ url('/' . $item->slug . '/default/move') }}" class="btn btn-success w-100 mb-2">
          <i class="fas fa-arrows-alt me-2"></i>Move
        </a>
        <a href="{{ route('library.browse') }}" class="btn btn-outline-secondary w-100 mb-2">
          <i class="fas fa-list me-2"></i>Browse library
        </a>
        <div class="dropdown">
          <button type="button" class="btn btn-outline-dark w-100 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-ellipsis-h me-2"></i>More
          </button>
          <ul class="dropdown-menu dropdown-menu-end w-100">
            <li><a class="dropdown-item" href="{{ route('library.rename', $item->slug) }}"><i class="fas fa-i-cursor me-2"></i>Rename</a></li>
            <li><a class="dropdown-item" href="{{ url('/' . $item->slug . '/informationobject/updatePublicationStatus') }}"><i class="fas fa-eye me-2"></i>Update publication status</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ url('/' . $item->slug . '/object/editPhysicalObjects') }}"><i class="fas fa-box me-2"></i>Link physical storage</a></li>
            <li><hr class="dropdown-divider"></li>
            @if($hasDigitalObject)
              @php
                $doRecord = \Illuminate\Support\Facades\DB::table('digital_object')
                  ->where('object_id', $item->id)->first();
              @endphp
              <li><a class="dropdown-item" href="{{ url('/digitalobject/edit/' . ($doRecord->id ?? 0)) }}"><i class="fas fa-edit me-2"></i>Edit digital object</a></li>
            @else
              <li><a class="dropdown-item" href="{{ url('/' . $item->slug . '/object/addDigitalObject') }}"><i class="fas fa-file-upload me-2"></i>Link digital object</a></li>
            @endif
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ url('/' . $item->slug . '/right/edit') }}"><i class="fas fa-copyright me-2"></i>Create new rights</a></li>
            @if(\Illuminate\Support\Facades\Route::has('extended-rights.edit'))
              <li><a class="dropdown-item" href="{{ route('extended-rights.edit', $item->slug) }}"><i class="fas fa-balance-scale me-2"></i>Extended Rights</a></li>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('grap.show'))
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="{{ route('grap.show', $item->slug) }}"><i class="fas fa-file-invoice me-2"></i>View GRAP data</a></li>
              <li><a class="dropdown-item" href="{{ route('grap.edit', $item->slug) }}"><i class="fas fa-file-invoice me-2"></i>Edit GRAP data</a></li>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('spectrum.show'))
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="{{ route('spectrum.show', $item->slug) }}"><i class="fas fa-layer-group me-2"></i>Spectrum data</a></li>
              @if(\Illuminate\Support\Facades\Route::has('spectrum.workflow'))
                <li><a class="dropdown-item" href="{{ route('spectrum.workflow', $item->slug) }}"><i class="fas fa-tasks me-2"></i>Workflow Status</a></li>
              @endif
              @if(\Illuminate\Support\Facades\Route::has('spectrum.label'))
                <li><a class="dropdown-item" href="{{ route('spectrum.label', $item->slug) }}"><i class="fas fa-barcode me-2"></i>Generate barcode label</a></li>
              @endif
            @endif
            @if(\Illuminate\Support\Facades\Route::has('provenance.view'))
              <li><a class="dropdown-item" href="{{ route('provenance.view', $item->slug) }}"><i class="fas fa-sitemap me-2"></i>Provenance</a></li>
            @endif
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ url('/label/' . $item->slug) }}"><i class="fas fa-tag me-2"></i>Generate label</a></li>
            <li><hr class="dropdown-divider"></li>
          </ul>
        </div>
      </div>
    </section>
  @endauth

  {{-- ISBN Barcode --}}
  @php
    $isbn = $item->isbn ?? '';
    $cleanIsbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn));
  @endphp
  @if(!empty($cleanIsbn))
    <section class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-barcode me-2"></i>ISBN Barcode</h5>
      </div>
      <div class="card-body text-center">
        <style>#isbn-barcode rect { fill: #ffffff !important; } #isbn-barcode g rect { fill: #000000 !important; }</style>
        <svg id="isbn-barcode"></svg>
        <p class="text-muted small mt-2 mb-0">{{ $isbn }}</p>
      </div>
    </section>
    <script src="/vendor/JsBarcode.all.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      try {
        JsBarcode("#isbn-barcode", "{{ $cleanIsbn }}", {
          format: "CODE128",
          background: "#ffffff",
          lineColor: "#000000",
          width: 2,
          height: 60,
          displayValue: false,
          margin: 10
        });
        document.querySelector("#isbn-barcode > rect").style.setProperty("fill", "#ffffff", "important");
        document.querySelectorAll("#isbn-barcode g rect").forEach(r => r.style.setProperty("fill", "#000000", "important"));
      } catch(e) {
        JsBarcode("#isbn-barcode", "{{ $cleanIsbn }}", {
          format: "CODE128",
          background: "#ffffff",
          lineColor: "#000000",
          width: 2,
          height: 60,
          displayValue: false,
          margin: 10
        });
        document.querySelector("#isbn-barcode > rect").style.setProperty("fill", "#ffffff", "important");
        document.querySelectorAll("#isbn-barcode g rect").forEach(r => r.style.setProperty("fill", "#000000", "important"));
      }
    });
    </script>
  @endif

  {{-- Related Records --}}
  <section class="card mb-3">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-link me-2"></i>Related records</h5>
    </div>
    <div class="card-body">
      @if($parentItem ?? null)
        <a href="{{ route('library.show', $parentItem->slug) }}" class="btn btn-outline-secondary w-100 mb-2">
          <i class="fas fa-level-up-alt me-2"></i>Parent record
        </a>
      @endif
      @if(($childCount ?? 0) > 0)
        <a href="{{ route('informationobject.browse', ['collection' => $item->id, 'topLod' => 0]) }}" class="btn btn-outline-secondary w-100">
          <i class="fas fa-sitemap me-2"></i>{{ $childCount }} child records
        </a>
      @endif
      @if(!($parentItem ?? null) && ($childCount ?? 0) === 0)
        <p class="text-muted small mb-0">No related records found.</p>
      @endif
    </div>
  </section>

  {{-- Physical Storage --}}
  @php
    $physicalObjects = \Illuminate\Support\Facades\DB::table('relation')
      ->where('relation.object_id', $item->id)
      ->where('relation.type_id', 161)
      ->join('physical_object', 'relation.subject_id', '=', 'physical_object.id')
      ->leftJoin('physical_object_i18n', function($join) {
        $join->on('physical_object.id', '=', 'physical_object_i18n.id')
             ->where('physical_object_i18n.culture', '=', app()->getLocale());
      })
      ->select('physical_object.*', 'physical_object_i18n.name as po_name', 'physical_object_i18n.location as po_location')
      ->get();
  @endphp
  @if($physicalObjects->isNotEmpty())
    <section class="card mb-3">
      <div class="card-header bg-warning">
        <h5 class="mb-0"><i class="fas fa-warehouse me-2"></i>Physical storage</h5>
      </div>
      <div class="card-body">
        @foreach($physicalObjects as $po)
          @php
            $poSlug = \Illuminate\Support\Facades\DB::table('slug')
              ->where('object_id', $po->id)->value('slug');
            $poTypeName = null;
            if ($po->type_id ?? null) {
              $poTypeName = \Illuminate\Support\Facades\DB::table('term_i18n')
                ->where('id', $po->type_id)
                ->where('culture', app()->getLocale())
                ->value('name');
            }
            $extData = [];
            if (\Illuminate\Support\Facades\Schema::hasTable('physical_object_extended')) {
              $extData = (array) \Illuminate\Support\Facades\DB::table('physical_object_extended')
                ->where('physical_object_id', $po->id)->first();
            }
          @endphp
          <div class="mb-3 pb-3 border-bottom">
            <strong>
              @if($poSlug)
                <a href="{{ route('physicalobject.show', $poSlug) }}">{{ $po->po_name ?: '[Unnamed]' }}</a>
              @else
                {{ $po->po_name ?: '[Unnamed]' }}
              @endif
            </strong>
            @if($poTypeName)
              <span class="badge bg-secondary ms-2">{{ $poTypeName }}</span>
            @endif
            @if(!empty($extData['status']) && $extData['status'] !== 'active')
              <span class="badge bg-{{ $extData['status'] === 'full' ? 'danger' : 'warning' }} ms-1">{{ ucfirst($extData['status']) }}</span>
            @endif

            @if(!empty($extData))
              <div class="mt-2">
                @php
                  $locationParts = array_filter([
                    $extData['building'] ?? null,
                    !empty($extData['floor']) ? 'Floor ' . $extData['floor'] : null,
                    !empty($extData['room']) ? 'Room ' . $extData['room'] : null,
                  ]);
                  $shelfParts = array_filter([
                    !empty($extData['aisle']) ? 'Aisle ' . $extData['aisle'] : null,
                    !empty($extData['bay']) ? 'Bay ' . $extData['bay'] : null,
                    !empty($extData['rack']) ? 'Rack ' . $extData['rack'] : null,
                    !empty($extData['shelf']) ? 'Shelf ' . $extData['shelf'] : null,
                    !empty($extData['position']) ? 'Pos ' . $extData['position'] : null,
                  ]);
                @endphp
                @if(!empty($locationParts))
                  <small class="text-muted d-block"><i class="fas fa-building me-1"></i>{!! implode(' &gt; ', array_map('e', $locationParts)) !!}</small>
                @endif
                @if(!empty($shelfParts))
                  <small class="text-primary d-block"><i class="fas fa-th me-1"></i>{!! implode(' &gt; ', array_map('e', $shelfParts)) !!}</small>
                @endif
                @if(!empty($extData['barcode']))
                  <small class="text-muted d-block"><i class="fas fa-barcode me-1"></i>{{ $extData['barcode'] }}</small>
                @endif
                @if(!empty($extData['total_capacity']))
                  @php
                    $used = (int)($extData['used_capacity'] ?? 0);
                    $total = (int)$extData['total_capacity'];
                    $available = $total - $used;
                    $percent = $total > 0 ? round(($used / $total) * 100) : 0;
                    $barClass = $percent >= 90 ? 'bg-danger' : ($percent >= 70 ? 'bg-warning' : 'bg-success');
                  @endphp
                  <div class="mt-1">
                    <small class="text-muted">Capacity: {{ $used }}/{{ $total }} {{ $extData['capacity_unit'] ?? 'items' }}</small>
                    <div class="progress" style="height: 8px;">
                      <div class="progress-bar {{ $barClass }}" style="width: {{ $percent }}%;"></div>
                    </div>
                  </div>
                @endif
                @if(!empty($extData['climate_controlled']))
                  <small class="text-info d-block mt-1"><i class="fas fa-thermometer-half me-1"></i>Climate controlled</small>
                @endif
                @if(!empty($extData['security_level']))
                  <small class="text-danger d-block"><i class="fas fa-lock me-1"></i>{{ ucfirst($extData['security_level']) }}</small>
                @endif
              </div>
            @elseif($po->po_location)
              <small class="text-muted d-block mt-1"><i class="fas fa-map-marker-alt me-1"></i>{{ $po->po_location }}</small>
            @endif
          </div>
        @endforeach
      </div>
    </section>
  @endif

  {{-- E-book Access --}}
  @if($item->ebook_preview_url)
    <section class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-tablet-alt me-2"></i>E-book Access</h5>
      </div>
      <div class="card-body">
        <a href="{{ $item->ebook_preview_url }}" target="_blank" class="btn btn-outline-primary w-100">
          <i class="fas fa-book-reader me-2"></i>Preview on Archive.org
        </a>
      </div>
    </section>
  @endif

  {{-- External Links --}}
  @if($item->openlibrary_url || $item->goodreads_id || $item->librarything_id)
    <section class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-external-link-alt me-2"></i>External Links</h5>
      </div>
      <div class="card-body">
        @if($item->openlibrary_url)
          <a href="{{ $item->openlibrary_url }}" target="_blank" class="btn btn-outline-secondary w-100 mb-2">
            <i class="fas fa-book me-2"></i>Open Library
          </a>
        @endif

        @if($item->goodreads_id)
          <a href="https://www.goodreads.com/book/show/{{ $item->goodreads_id }}" target="_blank" class="btn btn-outline-secondary w-100 mb-2">
            <i class="fas fa-star me-2"></i>Goodreads
          </a>
        @endif

        @if($item->librarything_id)
          <a href="https://www.librarything.com/work/{{ $item->librarything_id }}" target="_blank" class="btn btn-outline-secondary w-100">
            <i class="fas fa-bookmark me-2"></i>LibraryThing
          </a>
        @endif
      </div>
    </section>
  @endif

  {{-- Provenance & Chain of Custody --}}
  @if(\Illuminate\Support\Facades\Route::has('provenance.view'))
    <section class="card mb-3">
      <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Provenance & Chain of Custody</h5>
      </div>
      <div class="card-body">
        @if(\Illuminate\Support\Facades\Route::has('provenance.display'))
          @include('ahg-provenance::_provenanceDisplay', ['objectId' => $item->id])
        @endif
        @auth
          <div class="mt-3">
            <a href="{{ route('provenance.edit', $item->slug) }}" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-edit me-1"></i>Edit Provenance
            </a>
            <a href="{{ route('provenance.view', $item->slug) }}" class="btn btn-sm btn-outline-secondary">
              <i class="fas fa-clock me-1"></i>View Full Timeline
            </a>
          </div>
        @endauth
      </div>
    </section>
  @endif

  {{-- Export --}}
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-file-export me-1"></i> Export
    </div>
    <div class="list-group list-group-flush">
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.dc'))
        <a href="{{ route('informationobject.export.dc', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> Dublin Core 1.1 XML
        </a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.ead'))
        <a href="{{ route('informationobject.export.ead', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> EAD 2002 XML
        </a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.ead3'))
        <a href="{{ route('informationobject.export.ead3', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> EAD3 1.1 XML
        </a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.ead4'))
        <a href="{{ route('informationobject.export.ead4', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> EAD 4 XML
        </a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.mods'))
        <a href="{{ route('informationobject.export.mods', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> MODS 3.5 XML
        </a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.rico'))
        <a href="{{ route('informationobject.export.rico', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> RiC-O JSON-LD
        </a>
      @endif
      @auth
        @if(\Illuminate\Support\Facades\Route::has('informationobject.export.csv'))
          <a href="{{ route('informationobject.export.csv', $item->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-file-csv me-1"></i> Export CSV
          </a>
        @endif
      @endauth
    </div>
  </div>

  {{-- Print card --}}
  <div class="card mb-3">
    <div class="card-header fw-bold">
      <i class="fas fa-print me-1"></i> Print
    </div>
    <div class="list-group list-group-flush">
      <a href="javascript:window.print();" class="list-group-item list-group-item-action small">
        <i class="fas fa-print me-1"></i> Print this page
      </a>
    </div>
  </div>

</nav>

@endsection

{{-- ============================================================ --}}
{{-- AFTER CONTENT: Actions bar                                   --}}
{{-- ============================================================ --}}
@section('after-content')
  @auth
  <section class="actions mb-3 nav gap-2">
    <li>
      <a href="{{ route('library.edit', $item->slug) }}" class="btn atom-btn-outline-light">Edit</a>
    </li>
    <li>
      <form action="{{ route('library.destroy', $item->slug) }}" method="POST"
            onsubmit="return confirm('Are you sure you want to delete this library item?');">
        @csrf
        <button type="submit" class="btn atom-btn-outline-danger">Delete</button>
      </form>
    </li>
    <li>
      <a href="{{ route('library.create') }}" class="btn atom-btn-outline-light">Add new</a>
    </li>
    <li>
      <a href="{{ url('/' . $item->slug . '/default/move') }}" class="btn atom-btn-outline-light">Move</a>
    </li>
    <li>
      <a href="{{ url('/' . $item->slug . '/object/addDigitalObject') }}" class="btn atom-btn-outline-light">
        <i class="fas fa-upload me-1"></i>Add digital object
      </a>
    </li>
  </section>
  @endauth
@endsection
