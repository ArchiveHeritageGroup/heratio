@extends('theme::layouts.3col')

@section('title', $item->title ?? 'Library item')
@section('body-class', 'view library')

@push('css')
<style>
  /* Library page sidebar widths. Right column was originally 27%, then two
     33% reductions (27% → 18% → 12%), then user asked for +1cm back. Using
     calc() so it scales with viewport. Main column absorbs the inverse;
     left stays at 18%. */
  .view.library #left-column  { flex: 0 0 auto; width: 18%; max-width: 18%; }
  .view.library #main-column  { flex: 0 0 auto; width: calc(70% - 2cm); max-width: calc(70% - 2cm); }
  .view.library #right-column { flex: 0 0 auto; width: calc(12% + 2cm); max-width: calc(12% + 2cm); }
  @media (max-width: 991.98px) {
    .view.library #left-column,
    .view.library #main-column,
    .view.library #right-column { width: 100% !important; max-width: 100% !important; }
  }
</style>
@endpush

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
            <img src="{{ $repoLogoUrl }}" alt="{{ __('Repository logo') }}" class="img-fluid" style="max-height:80px;">
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
  @if($item->material_type)
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
      <i class="fas fa-book me-1"></i> {{ __('Library') }}
    </div>
    <div class="list-group list-group-flush">
      <a href="{{ route('library.browse', ['material_type' => $item->material_type]) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-filter me-1"></i> {{ __('Same material type') }}
      </a>
    </div>
  </div>
  @endif

  {{-- External links sidebar --}}
  @if($item->openlibrary_url || $item->ebook_preview_url || $item->openlibrary_id || $item->goodreads_id || $item->librarything_id)
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
        <i class="fas fa-external-link-alt me-1"></i> {{ __('External links') }}
      </div>
      <div class="list-group list-group-flush">
        @if($item->openlibrary_url)
          <a href="{{ $item->openlibrary_url }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-book-open me-1"></i> {{ __('OpenLibrary') }}
          </a>
        @elseif($item->openlibrary_id)
          <a href="https://openlibrary.org/works/{{ $item->openlibrary_id }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-book-open me-1"></i> {{ __('OpenLibrary') }}
          </a>
        @endif
        @if($item->goodreads_id)
          <a href="https://www.goodreads.com/book/show/{{ $item->goodreads_id }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-star me-1"></i> {{ __('Goodreads') }}
          </a>
        @endif
        @if($item->librarything_id)
          <a href="https://www.librarything.com/work/{{ $item->librarything_id }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-bookmark me-1"></i> {{ __('LibraryThing') }}
          </a>
        @endif
        @if($item->ebook_preview_url)
          <a href="{{ $item->ebook_preview_url }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-book-reader me-1"></i> {{ __('E-book preview') }}
          </a>
        @endif
      </div>
    </div>
  @endif

  {{-- Collections Management sidebar --}}
  @auth
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
        <i class="fas fa-clipboard-check me-1"></i> {{ __('Collections Management') }}
      </div>
      <div class="list-group list-group-flush">
        @if(\Illuminate\Support\Facades\Route::has('io.condition'))
          <a href="{{ route('io.condition', $item->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-clipboard-check me-2"></i>{{ __('Condition assessment') }}
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('io.spectrum'))
          <a href="{{ route('io.spectrum', $item->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-layer-group me-2"></i>{{ __('Spectrum data') }}
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('ahgspectrum.workflow'))
          <a href="{{ route('ahgspectrum.workflow') }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-tasks me-2"></i>{{ __('Workflow Status') }}
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('provenance.view'))
          <a href="{{ route('provenance.view', $item->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-sitemap me-2"></i>{{ __('Provenance') }}
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('research.cite'))
          <a href="{{ route('research.cite', $item->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-quote-left me-2"></i>{{ __('Cite this Record') }}
          </a>
        @endif
      </div>
    </div>
  @endauth

  {{-- Named Entity Recognition sidebar --}}
  @auth
    @if(\Illuminate\Support\Facades\Route::has('io.ai.review'))
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
          <i class="fas fa-brain me-1"></i> {{ __('Named Entity Recognition') }}
        </div>
        <div class="list-group list-group-flush">
          <a href="#" class="list-group-item list-group-item-action small" data-bs-toggle="modal" data-bs-target="#nerModal">
            <i class="fas fa-brain me-2"></i> {{ __('Extract Entities') }}
          </a>
          <a href="{{ route('io.ai.review') }}?object_id={{ $item->id }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-list-check me-2"></i> {{ __('Review Dashboard') }}
          </a>
        </div>
      </div>
    @endif
  @endauth

  {{-- Rights --}}
  @auth
  @php
    $hasExtRights = \Illuminate\Support\Facades\Schema::hasTable('extended_rights')
        && \Illuminate\Support\Facades\DB::table('extended_rights')->where('object_id', $item->id)->exists();
    $activeEmbargo = \Illuminate\Support\Facades\Schema::hasTable('embargo')
        ? \Illuminate\Support\Facades\DB::table('embargo')->where('object_id', $item->id)->where('is_active', 1)->first()
        : null;
  @endphp
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
      <i class="fas fa-copyright me-1"></i> {{ __('Rights') }}
    </div>
    {{-- Status badges --}}
    <div class="card-body py-2">
      @if($hasExtRights)
        <span class="badge bg-success me-1"><i class="fas fa-check-circle me-1"></i>{{ __('Extended rights applied') }}</span>
      @endif
      @if($activeEmbargo)
        <span class="badge bg-danger me-1"><i class="fas fa-ban me-1"></i>{{ __('Under embargo') }}</span>
      @endif
      @if(!$hasExtRights && !$activeEmbargo)
        <span class="badge bg-secondary"><i class="fas fa-info-circle me-1"></i>{{ __('No extended rights or embargo') }}</span>
      @endif
    </div>
    <div class="list-group list-group-flush">
      @if(\Illuminate\Support\Facades\Route::has('io.rights.manage'))
        <a href="{{ route('io.rights.manage', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-copyright me-1"></i> {{ ($hasExtRights || $activeEmbargo) ? 'Edit' : 'Add' }} rights
        </a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('io.rights.export'))
        <a href="{{ route('io.rights.export', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-download me-1"></i> {{ __('Export rights (JSON-LD)') }}
        </a>
      @endif
    </div>
  </div>
  @endauth

  {{-- Marketplace (Buy/Sell, gated on marketplace_enabled setting) --}}
  @includeIf('marketplace::partials._add-to-marketplace', ['ioId' => $item->id])

@endsection

{{-- ============================================================ --}}
{{-- MAIN CONTENT: Field sections (card-based like AtoM)          --}}
{{-- ============================================================ --}}
@section('content')

  @include('ahg-ric::_view-switch', ['standard' => 'Dublin Core'])
  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-view-library', ['item' => $item])
  @else

<link rel="stylesheet" href="/vendor/ahg-core/css/tts.css">
<script src="/vendor/ahg-core/js/tts.js"></script>

<div id="tts-content-area" data-tts-content>

  {{-- Digital Object Viewer (top of content area, matching AtoM layout) --}}
  @php
    $masterObj = $digitalObjects['master'] ?? null;
    $refObj = $digitalObjects['reference'] ?? null;
    $thumbObj = $digitalObjects['thumbnail'] ?? null;
    $hasDigitalObject = $masterObj || $refObj || $thumbObj;
  @endphp

  @if($masterObj || $refObj || $thumbObj)
    @php
      $masterUrl = $masterObj ? \AhgCore\Services\DigitalObjectService::getUrl($masterObj) : '';
      $refUrl = $refObj ? \AhgCore\Services\DigitalObjectService::getUrl($refObj) : '';
      $thumbUrl = $thumbObj ? \AhgCore\Services\DigitalObjectService::getUrl($thumbObj) : '';
      $masterMediaType = $masterObj ? \AhgCore\Services\DigitalObjectService::getMediaType($masterObj) : null;
      $isPdf = $masterObj && ($masterObj->mime_type ?? '') === 'application/pdf';
      $masterMime = $masterObj->mime_type ?? '';
      $is3DModel = $masterObj && in_array(strtolower($masterMime), ['model/gltf-binary', 'model/gltf+json', 'model/gltf', 'application/octet-stream']);
      if (!$is3DModel && $masterObj && ($masterObj->name ?? null)) {
          $ext = strtolower(pathinfo($masterObj->name, PATHINFO_EXTENSION));
          $is3DModel = in_array($ext, ['glb', 'gltf']);
      }
    @endphp

    <div class="digital-object-viewer text-center mb-4 p-3 border rounded" style="background:#f8f9fa;">
      @if($isPdf)
        <div class="pdf-toolbar mb-2 d-flex justify-content-between align-items-center">
          <span class="badge bg-danger"><i class="fas fa-file-pdf me-1"></i>{{ __('PDF Document') }}</span>
          <div class="btn-group btn-group-sm">
            <a href="{{ $masterUrl }}" target="_blank" class="btn atom-btn-white" title="{{ __('Open in new tab') }}"><i class="fas fa-external-link-alt"></i></a>
            <a href="{{ $masterUrl }}" download class="btn atom-btn-white" title="{{ __('Download PDF') }}"><i class="fas fa-download"></i></a>
          </div>
        </div>
        <div class="ratio" style="--bs-aspect-ratio: 75%;">
          <iframe src="{{ $masterUrl }}" style="border:none;border-radius:8px;background:#525659;" title="{{ __('PDF Viewer') }}"></iframe>
        </div>
      @elseif($masterMediaType === 'image' || in_array($masterMime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/tiff', 'image/svg+xml']))
        {{-- OpenSeadragon + Mirador resizable viewer --}}
        @php $viewerId = 'iiif-viewer-' . $item->id; $imgSrc = $masterUrl ?: $refUrl; @endphp
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="btn-group btn-group-sm" role="group">
            <button id="btn-osd-{{ $viewerId }}" class="btn atom-btn-white active" title="{{ __('OpenSeadragon Deep Zoom') }}">
              <i class="fas fa-search-plus me-1"></i>{{ __('Deep Zoom') }}
            </button>
            <button id="btn-mirador-{{ $viewerId }}" class="btn atom-btn-white" title="{{ __('Mirador IIIF Viewer') }}">
              <i class="fas fa-columns me-1"></i>{{ __('Mirador') }}
            </button>
            <button id="btn-img-{{ $viewerId }}" class="btn atom-btn-white" title="{{ __('Simple image') }}">
              <i class="fas fa-image me-1"></i>{{ __('Image') }}
            </button>
          </div>
          <div class="btn-group btn-group-sm">
            <a href="{{ $imgSrc }}" target="_blank" class="btn atom-btn-white" title="{{ __('Open full size') }}"><i class="fas fa-external-link-alt"></i></a>
            <button id="btn-fs-{{ $viewerId }}" class="btn atom-btn-white" title="{{ __('Fullscreen') }}"><i class="fas fa-expand"></i></button>
          </div>
        </div>
        <div id="osd-{{ $viewerId }}" style="width:100%;height:400px;background:#1a1a1a;border-radius:8px;"></div>
        <div id="mirador-{{ $viewerId }}" style="width:100%;height:400px;border-radius:8px;display:none;overflow:hidden;"></div>
        <div id="img-{{ $viewerId }}" style="display:none;" class="text-center">
          <a href="{{ $imgSrc }}" target="_blank">
            <img src="{{ $refUrl ?: $thumbUrl ?: $masterUrl }}" alt="{{ $item->title }}" class="img-fluid img-thumbnail" style="max-height:400px;">
          </a>
        </div>
        <script src="{{ asset('vendor/openseadragon/6.0.2/openseadragon.min.js') }}"></script>
        <script src="{{ asset('vendor/openseadragon/6.0.2/openseadragon-filtering.js') }}"></script>
        <script src="{{ asset('vendor/ahg-theme-b5/js/ahg-iiif-viewer.js') }}"></script>
        <script>document.addEventListener('DOMContentLoaded', function() { initIiifViewer('{{ $viewerId }}', '{{ url($imgSrc) }}', '{{ addslashes($item->title) }}'); });</script>
      @elseif($masterMediaType === 'video')
        <video controls class="w-100" style="max-height:500px;background:#000;" preload="metadata" @if($thumbUrl) poster="{{ $thumbUrl }}" @endif>
          <source src="{{ $masterUrl }}" type="{{ $masterMime }}">
        </video>
      @elseif($masterMediaType === 'audio')
        <audio controls class="w-100"><source src="{{ $masterUrl }}" type="{{ $masterMime }}"></audio>
      @else
        <div class="py-3">
          <i class="fas fa-file fa-3x text-muted mb-2 d-block"></i>
          <p class="text-muted mb-1">{{ $masterObj->name ?? 'Digital object' }}</p>
          @auth <a href="{{ $masterUrl }}" download class="btn btn-sm atom-btn-white"><i class="fas fa-download me-1"></i>{{ __('Download') }}</a> @endauth
        </div>
      @endif
    </div>
  @elseif($item->cover_url)
    <div class="text-center mb-4">
      <a href="{{ $item->cover_url_original ?: $item->cover_url }}" target="_blank">
        <img src="{{ $item->cover_url }}" alt="{{ $item->title }}" class="img-fluid img-thumbnail" style="max-height:350px;">
      </a>
    </div>
  @endif

  {{-- Basic Information --}}
  <section class="card mb-4">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="fas fa-book me-2"></i>{{ __('Basic Information') }}</h5>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-4">Title</dt>
        <dd class="col-sm-8">
          {{ $item->title ?: '[Untitled]' }}
          {{-- ICIP cultural-sensitivity badge (issue #36 Phase 2b). --}}
          @include('ahg-translation::components.icip-sensitivity-badge', ['uri' => $item->icip_sensitivity ?? null])
        </dd>

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
        <h5 class="mb-0"><i class="fas fa-users me-2"></i>{{ __('Creators / Authors') }}</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          @foreach($creators as $creator)
            <li class="mb-2">
              <strong>{{ $creator->name ?? '[Unknown]' }}</strong>
              <span class="badge bg-secondary ms-2">{{ ucfirst($creator->role ?? 'Author') }}</span>
              @if(!empty($creator->authority_uri))
                <a href="{{ $creator->authority_uri }}" target="_blank" class="ms-2" title="{{ __('View authority record') }}">
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
        <h5 class="mb-0"><i class="fas fa-barcode me-2"></i>{{ __('Standard Identifiers') }}</h5>
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
        <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>{{ __('Classification') }}</h5>
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

  {{-- Item Physical Location (information_object_physical_location row) --}}
  @php
    $iloc = $itemLocation ?? [];
    $hasIloc = collect(['physical_object_id','barcode','box_number','folder_number','shelf','row','position','item_number','extent_value','extent_unit','condition_status','access_status','condition_notes','notes'])
        ->contains(fn ($k) => !empty($iloc[$k]));
  @endphp
  @if($hasIloc)
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>{{ __('Item Physical Location') }}</h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          @if(!empty($iloc['physical_object_name']))
            <dt class="col-sm-4">Storage container</dt>
            <dd class="col-sm-8">
              {{ $iloc['physical_object_name'] }}
              @if(!empty($iloc['physical_object_location']))
                <span class="text-muted small">({{ $iloc['physical_object_location'] }})</span>
              @endif
            </dd>
          @endif
          @if(!empty($iloc['barcode']))
            <dt class="col-sm-4">Item barcode</dt>
            <dd class="col-sm-8"><code>{{ $iloc['barcode'] }}</code></dd>
          @endif
          @php
            $within = array_filter([
              !empty($iloc['box_number'])    ? 'Box '    . $iloc['box_number']    : null,
              !empty($iloc['folder_number']) ? 'Folder ' . $iloc['folder_number'] : null,
              !empty($iloc['shelf'])         ? 'Shelf '  . $iloc['shelf']         : null,
              !empty($iloc['row'])           ? 'Row '    . $iloc['row']           : null,
              !empty($iloc['position'])      ? 'Pos '    . $iloc['position']      : null,
              !empty($iloc['item_number'])   ? 'Item #'  . $iloc['item_number']   : null,
            ]);
          @endphp
          @if(!empty($within))
            <dt class="col-sm-4">Location within container</dt>
            <dd class="col-sm-8">{{ implode(' > ', $within) }}</dd>
          @endif
          @if(!empty($iloc['extent_value']) || !empty($iloc['extent_unit']))
            <dt class="col-sm-4">Extent</dt>
            <dd class="col-sm-8">{{ trim(($iloc['extent_value'] ?? '') . ' ' . ($iloc['extent_unit'] ?? '')) }}</dd>
          @endif
          @if(!empty($iloc['condition_status']))
            <dt class="col-sm-4">Condition</dt>
            <dd class="col-sm-8"><span class="badge bg-secondary">{{ ucfirst($iloc['condition_status']) }}</span></dd>
          @endif
          @if(!empty($iloc['access_status']))
            <dt class="col-sm-4">Access status</dt>
            <dd class="col-sm-8"><span class="badge bg-{{ $iloc['access_status'] === 'available' ? 'success' : 'warning' }}">{{ ucfirst($iloc['access_status']) }}</span></dd>
          @endif
          @if(!empty($iloc['condition_notes']))
            <dt class="col-sm-4">Condition notes</dt>
            <dd class="col-sm-8">{!! nl2br(e($iloc['condition_notes'])) !!}</dd>
          @endif
          @if(!empty($iloc['notes']))
            <dt class="col-sm-4">Location notes</dt>
            <dd class="col-sm-8">{!! nl2br(e($iloc['notes'])) !!}</dd>
          @endif
        </dl>
      </div>
    </section>
  @endif

  {{-- Publication Information --}}
  @if($item->publisher || $item->publication_place || $item->publication_date || $item->edition || $item->edition_statement || $item->series_title)
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-building me-2"></i>{{ __('Publication Information') }}</h5>
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
        <h5 class="mb-0"><i class="fas fa-ruler me-2"></i>{{ __('Physical Description') }}</h5>
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
        <h5 class="mb-0"><i class="fas fa-tags me-2"></i>{{ __('Subjects') }}</h5>
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
        <h5 class="mb-0"><i class="fas fa-align-left me-2"></i>{{ __('Content') }}</h5>
      </div>
      <div class="card-body">
        @if(!empty($summary))
          <h6>{{ __('Summary') }}</h6>
          <p>{!! nl2br(e($summary)) !!}</p>
        @endif

        @if(!empty($scopeAndContent))
          <h6>{{ __('Scope and content') }}</h6>
          <p>{!! nl2br(e($scopeAndContent)) !!}</p>
        @endif

        @if(!empty($contentsNote))
          <h6>{{ __('Table of contents') }}</h6>
          <p>{!! nl2br(e($contentsNote)) !!}</p>
        @endif
      </div>
    </section>
  @endif

  {{-- Notes --}}
  @if($item->general_note || $item->bibliography_note)
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>{{ __('Notes') }}</h5>
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

  {{-- ===== Rights & Access (combined PREMIS + Extended) ===== --}}
  @php
    $culture = app()->getLocale();

    // PREMIS rights (base — authoritative source)
    $premisRights = \Illuminate\Support\Facades\DB::table('rights')
        ->join('relation', function ($j) use ($item) {
            $j->on('rights.id', '=', 'relation.subject_id')
               ->where('relation.object_id', '=', $item->id)
               ->where('relation.type_id', '=', 168);
        })
        ->leftJoin('rights_i18n', function ($j) use ($culture) {
            $j->on('rights.id', '=', 'rights_i18n.id')->where('rights_i18n.culture', '=', $culture);
        })
        ->select('rights.*', 'rights_i18n.rights_note', 'rights_i18n.copyright_note',
                 'rights_i18n.license_terms', 'rights_i18n.license_note',
                 'rights_i18n.statute_note', 'rights_i18n.identifier_type',
                 'rights_i18n.identifier_value')
        ->get()
        ->map(function ($r) use ($culture) {
            $r->basis_name = $r->basis_id ? \Illuminate\Support\Facades\DB::table('term_i18n')->where('id', $r->basis_id)->where('culture', $culture)->value('name') : null;
            $r->copyright_status_name = $r->copyright_status_id ? \Illuminate\Support\Facades\DB::table('term_i18n')->where('id', $r->copyright_status_id)->where('culture', $culture)->value('name') : null;
            $r->rights_holder_name = $r->rights_holder_id ? \Illuminate\Support\Facades\DB::table('actor_i18n')->where('id', $r->rights_holder_id)->where('culture', $culture)->value('authorized_form_of_name') : null;
            $r->granted = \Illuminate\Support\Facades\DB::table('granted_right')->where('rights_id', $r->id)->get()->map(function ($gr) use ($culture) {
                $gr->act_name = $gr->act_id ? \Illuminate\Support\Facades\DB::table('term_i18n')->where('id', $gr->act_id)->where('culture', $culture)->value('name') : null;
                $gr->restriction_label = match((int)($gr->restriction ?? -1)) { 0 => 'Allow', 1 => 'Disallow', 2 => 'Conditional', default => '' };
                return $gr;
            });
            return $r;
        });

    // Extended rights (supplements PREMIS — CC license, TK labels, usage conditions)
    $extRights = \Illuminate\Support\Facades\Schema::hasTable('extended_rights')
        ? \Illuminate\Support\Facades\DB::table('extended_rights as er')
            ->leftJoin('extended_rights_i18n as eri', function ($j) use ($culture) {
                $j->on('eri.extended_rights_id', '=', 'er.id')->where('eri.culture', '=', $culture);
            })
            ->where('er.object_id', $item->id)->first()
        : null;

    // Resolve extended rights lookups
    $extRsName = ($extRights && $extRights->rights_statement_id) ? \Illuminate\Support\Facades\DB::table('term_i18n')->where('id', $extRights->rights_statement_id)->where('culture', $culture)->value('name') : null;
    $extCcName = ($extRights && $extRights->creative_commons_license_id) ? \Illuminate\Support\Facades\DB::table('term_i18n')->where('id', $extRights->creative_commons_license_id)->where('culture', $culture)->value('name') : null;

    // TK labels
    $tkLabels = ($extRights && \Illuminate\Support\Facades\Schema::hasTable('extended_rights_tk_label'))
        ? \Illuminate\Support\Facades\DB::table('extended_rights_tk_label')->where('extended_rights_id', $extRights->id)->get()
        : collect();

    // Embargo
    $embargo = \Illuminate\Support\Facades\Schema::hasTable('embargo')
        ? \Illuminate\Support\Facades\DB::table('embargo')->where('object_id', $item->id)->where('is_active', 1)->first()
        : null;

    // Merge: PREMIS rights_holder takes precedence, fallback to extended
    $primaryHolder = $premisRights->pluck('rights_holder_name')->filter()->first();
    $holderDisplay = $primaryHolder ?? ($extRights->rights_holder ?? null);
    $holderUri = $extRights->rights_holder_uri ?? null;

    // Merge: rights notes (combine PREMIS + extended, deduplicate)
    $allNotes = collect();
    foreach ($premisRights as $pr) { if ($pr->rights_note) $allNotes->push($pr->rights_note); }
    if ($extRights && ($extRights->rights_note ?? null) && !$allNotes->contains($extRights->rights_note)) {
        $allNotes->push($extRights->rights_note);
    }

    $hasAnyRights = $premisRights->isNotEmpty() || $extRights || $embargo;
  @endphp

  @if($hasAnyRights || auth()->check())
  <section class="card mb-4">
    <div class="card-header text-white" style="background:var(--ahg-primary);">
      <h5 class="mb-0"><i class="fas fa-copyright me-2"></i>{{ __('Rights & Access') }}</h5>
    </div>
    <div class="card-body">

      {{-- Embargo alert --}}
      @if($embargo)
        <div class="alert alert-danger d-flex align-items-center mb-3">
          <i class="fas fa-ban me-2 fa-lg"></i>
          <div>
            <strong>{{ __('Under Embargo') }}</strong> — {{ ucfirst($embargo->embargo_type ?? 'full') }} embargo since {{ $embargo->start_date }}
            @if($embargo->end_date) until {{ $embargo->end_date }} @else (no end date) @endif
          </div>
        </div>
      @endif

      {{-- Combined rights display --}}
      <dl class="row mb-0">

        {{-- Rights statement (extended only) --}}
        @if($extRsName)
          <dt class="col-sm-4">Rights statement</dt><dd class="col-sm-8">{{ $extRsName }}</dd>
        @endif

        {{-- Basis (PREMIS) --}}
        @foreach($premisRights as $pr)
          @if($pr->basis_name)<dt class="col-sm-4">Basis</dt><dd class="col-sm-8">{{ $pr->basis_name }}</dd>@endif
        @endforeach

        {{-- Rights holder (PREMIS primary, extended fallback) --}}
        @if($holderDisplay)
          <dt class="col-sm-4">Rights holder</dt>
          <dd class="col-sm-8">
            {{ $holderDisplay }}
            @if($holderUri) <a href="{{ $holderUri }}" target="_blank" class="ms-1"><i class="fas fa-external-link-alt small"></i></a> @endif
          </dd>
        @endif

        {{-- Dates (PREMIS primary, extended fallback) --}}
        @php
          $startDate = $premisRights->pluck('start_date')->filter()->first() ?? ($extRights->rights_date ?? null);
          $endDate = $premisRights->pluck('end_date')->filter()->first() ?? ($extRights->expiry_date ?? null);
        @endphp
        @if($startDate)<dt class="col-sm-4">Start date</dt><dd class="col-sm-8">{{ $startDate }}</dd>@endif
        @if($endDate)<dt class="col-sm-4">End / Expiry date</dt><dd class="col-sm-8">{{ $endDate }}</dd>@endif

        {{-- Copyright (PREMIS) --}}
        @foreach($premisRights as $pr)
          @if($pr->copyright_status_name)<dt class="col-sm-4">Copyright status</dt><dd class="col-sm-8">{{ $pr->copyright_status_name }}</dd>@endif
          @if($pr->copyright_jurisdiction)<dt class="col-sm-4">Jurisdiction</dt><dd class="col-sm-8">{{ $pr->copyright_jurisdiction }}</dd>@endif
          @if($pr->copyright_note)<dt class="col-sm-4">Copyright note</dt><dd class="col-sm-8">{{ $pr->copyright_note }}</dd>@endif
        @endforeach

        {{-- CC License (extended only — no PREMIS equivalent) --}}
        @if($extCcName)
          <dt class="col-sm-4">Creative Commons</dt><dd class="col-sm-8">{{ $extCcName }}</dd>
        @endif

        {{-- License (PREMIS) --}}
        @foreach($premisRights as $pr)
          @if($pr->license_terms)<dt class="col-sm-4">License terms</dt><dd class="col-sm-8">{{ $pr->license_terms }}</dd>@endif
          @if($pr->license_note)<dt class="col-sm-4">License note</dt><dd class="col-sm-8">{{ $pr->license_note }}</dd>@endif
        @endforeach

        {{-- Statute (PREMIS) --}}
        @foreach($premisRights as $pr)
          @if($pr->statute_note)<dt class="col-sm-4">Statute note</dt><dd class="col-sm-8">{{ $pr->statute_note }}</dd>@endif
        @endforeach

        {{-- Usage conditions (extended only — no PREMIS equivalent) --}}
        @if($extRights && ($extRights->usage_conditions ?? null))
          <dt class="col-sm-4">Usage conditions</dt><dd class="col-sm-8">{{ $extRights->usage_conditions }}</dd>
        @endif

        {{-- Copyright notice (extended only — no PREMIS equivalent) --}}
        @if($extRights && ($extRights->copyright_notice ?? null))
          <dt class="col-sm-4">Copyright notice</dt><dd class="col-sm-8">{{ $extRights->copyright_notice }}</dd>
        @endif

        {{-- Notes (merged, deduplicated) --}}
        @if($allNotes->isNotEmpty())
          <dt class="col-sm-4">Notes</dt>
          <dd class="col-sm-8">
            @foreach($allNotes as $note)
              <p class="mb-1">{{ $note }}</p>
            @endforeach
          </dd>
        @endif

        {{-- Identifier (PREMIS) --}}
        @foreach($premisRights as $pr)
          @if($pr->identifier_type || $pr->identifier_value)
            <dt class="col-sm-4">Identifier</dt>
            <dd class="col-sm-8">{{ $pr->identifier_type }}{{ $pr->identifier_type && $pr->identifier_value ? ': ' : '' }}{{ $pr->identifier_value }}</dd>
          @endif
        @endforeach
      </dl>

      {{-- TK Labels (extended only) --}}
      @if($tkLabels->isNotEmpty())
        <div class="mt-2">
          <strong class="small text-muted">{{ __('Traditional Knowledge Labels') }}</strong>
          <div class="d-flex flex-wrap gap-1 mt-1">
            @foreach($tkLabels as $tk)
              <span class="badge bg-dark">{{ $tk->label_name ?? $tk->label_code ?? '' }}</span>
            @endforeach
          </div>
        </div>
      @endif

      {{-- Granted Rights (PREMIS) --}}
      @php $allGranted = $premisRights->flatMap(fn($pr) => $pr->granted); @endphp
      @if($allGranted->isNotEmpty())
        <hr>
        <h6 class="text-muted mb-2">{{ __('Granted Rights') }}</h6>
        <table class="table table-sm table-bordered mb-0">
          <thead><tr class="table-light"><th>{{ __('Act') }}</th><th>{{ __('Restriction') }}</th><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Notes') }}</th></tr></thead>
          <tbody>
            @foreach($allGranted as $gr)
              <tr>
                <td>{{ $gr->act_name ?? '' }}</td>
                <td><span class="badge bg-{{ $gr->restriction == 0 ? 'success' : ($gr->restriction == 1 ? 'danger' : 'warning') }}">{{ $gr->restriction_label }}</span></td>
                <td>{{ $gr->start_date ?? '' }}</td>
                <td>{{ $gr->end_date ?? '' }}</td>
                <td>{{ $gr->notes ?? '' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif

      {{-- No rights --}}
      @if(!$hasAnyRights)
        <p class="text-muted mb-0">No rights records found.</p>
      @endif

      {{-- Action links --}}
      @auth
        <div class="mt-3 d-flex flex-wrap gap-2">
          <a href="{{ route('io.rights.manage', $item->slug) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-copyright me-1"></i>{{ ($premisRights->isNotEmpty() || $extRights || $embargo) ? 'Edit' : 'Add' }} rights</a>
          <a href="{{ route('io.rights.export', $item->slug) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i>{{ __('Export (JSON-LD)') }}</a>
        </div>
      @endauth
    </div>
  </section>
  @endif

</div>{{-- /tts-content-area --}}

  @if(class_exists(\AhgRic\Controllers\RicEntityController::class))
    @include('ahg-ric::_ric-entities-panel', ['record' => $item, 'recordType' => 'record'])
  @endif
  @endif {{-- end ric_view_mode toggle --}}
@endsection

{{-- ============================================================ --}}
{{-- RIGHT SIDEBAR: Digital object, Actions, Barcode, Related     --}}
{{-- ============================================================ --}}
@section('right')

<nav>

  {{-- Cover thumbnail in sidebar (small, links to viewer in main content) --}}
  @php
    $masterObj = $digitalObjects['master'] ?? null;
    $refObj = $digitalObjects['reference'] ?? null;
    $thumbObj = $digitalObjects['thumbnail'] ?? null;
    $hasDigitalObject = $masterObj || $refObj || $thumbObj;
  @endphp

  @if(false && ($masterObj || $refObj || $thumbObj))
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
        <h5 class="mb-0"><i class="fas fa-image me-2"></i>{{ __('Cover') }}</h5>
      </div>
      <div class="card-body text-center p-2">
        @if($isPdf)
          {{-- PDF: embedded iframe viewer with toolbar --}}
          <div class="pdf-viewer-container" style="overflow:hidden;">
            <div class="pdf-wrapper">
              <div class="pdf-toolbar mb-2 d-flex justify-content-between align-items-center">
                <span class="badge bg-danger">
                  <i class="fas fa-file-pdf me-1"></i>{{ __('PDF Document') }}
                </span>
                <div class="btn-group btn-group-sm">
                  <a href="{{ $masterUrl }}" target="_blank" class="btn atom-btn-white" title="{{ __('Open in new tab') }}">
                    <i class="fas fa-external-link-alt"></i>
                  </a>
                  <a href="{{ $masterUrl }}" download class="btn atom-btn-white" title="{{ __('Download PDF') }}">
                    <i class="fas fa-download"></i>
                  </a>
                </div>
              </div>
              <div class="ratio" style="--bs-aspect-ratio: 85%;">
                <iframe src="{{ $masterUrl }}" style="border:none;border-radius:8px;background:#525659;" title="{{ __('PDF Viewer') }}"></iframe>
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
                <i class="fas fa-download me-1"></i>{{ __('Download video') }}
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
              <button class="btn btn-sm btn-outline-light" id="{{ $audioPlayerId }}-back" title="{{ __('Back 10s') }}">
                <i class="fas fa-backward"></i> 10s
              </button>
              <button class="btn btn-lg btn-light rounded-circle" id="{{ $audioPlayerId }}-play" title="{{ __('Play/Pause') }}" style="width:50px;height:50px;">
                <i class="fas fa-play" id="{{ $audioPlayerId }}-play-icon"></i>
              </button>
              <button class="btn btn-sm btn-outline-light" id="{{ $audioPlayerId }}-fwd" title="{{ __('Forward 10s') }}">
                10s <i class="fas fa-forward"></i>
              </button>
              <div class="ms-3 d-flex align-items-center gap-1">
                <span class="text-white small">{{ __('Speed:') }}</span>
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
                  <i class="fas fa-download me-1"></i>{{ __('Download') }}
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
                  <i class="fas fa-download me-1"></i>{{ __('Download Original') }}
                </a>
              </div>
            </div>
          </div>

        @elseif($masterMediaType === 'image' || in_array($masterMime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/tiff', 'image/svg+xml']))
          {{-- Image: OpenSeadragon + Mirador resizable viewer (matching AtoM) --}}
          @php $viewerId = 'iiif-viewer-' . $item->id; $imgSrc = $masterUrl ?: $refUrl; @endphp

          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="btn-group btn-group-sm" role="group">
              <button id="btn-osd-{{ $viewerId }}" class="btn atom-btn-white active" title="{{ __('OpenSeadragon Deep Zoom') }}">
                <i class="fas fa-search-plus me-1"></i>{{ __('Deep Zoom') }}
              </button>
              <button id="btn-mirador-{{ $viewerId }}" class="btn atom-btn-white" title="{{ __('Mirador IIIF Viewer') }}">
                <i class="fas fa-columns me-1"></i>{{ __('Mirador') }}
              </button>
              <button id="btn-img-{{ $viewerId }}" class="btn atom-btn-white" title="{{ __('Simple image') }}">
                <i class="fas fa-image me-1"></i>{{ __('Image') }}
              </button>
            </div>
            <div class="btn-group btn-group-sm">
              <a href="{{ $imgSrc }}" target="_blank" class="btn atom-btn-white" title="{{ __('Open full size') }}">
                <i class="fas fa-external-link-alt"></i>
              </a>
              <button id="btn-fs-{{ $viewerId }}" class="btn atom-btn-white" title="{{ __('Fullscreen') }}">
                <i class="fas fa-expand"></i>
              </button>
            </div>
          </div>

          {{-- OSD container --}}
          <div id="osd-{{ $viewerId }}" style="width:100%;height:450px;background:#1a1a1a;border-radius:8px;"></div>

          {{-- Mirador container (hidden) --}}
          <div id="mirador-{{ $viewerId }}" style="width:100%;height:450px;border-radius:8px;display:none;"></div>

          {{-- Simple image (hidden) --}}
          <div id="img-{{ $viewerId }}" style="display:none;" class="text-center">
            <a href="{{ $imgSrc }}" target="_blank">
              <img src="{{ $refUrl ?: $thumbUrl ?: $masterUrl }}" alt="{{ $item->title }}" class="img-fluid img-thumbnail" style="max-height:450px;">
            </a>
          </div>

          <script src="{{ asset('vendor/openseadragon/6.0.2/openseadragon.min.js') }}"></script>
        <script src="{{ asset('vendor/openseadragon/6.0.2/openseadragon-filtering.js') }}"></script>
          <script src="{{ asset('vendor/ahg-theme-b5/js/ahg-iiif-viewer.js') }}"></script>
          <script>
          document.addEventListener('DOMContentLoaded', function() {
            initIiifViewer('{{ $viewerId }}', '{{ url($imgSrc) }}', '{{ addslashes($item->title) }}');
          });
          </script>

        @else
          {{-- Other file: show info and download --}}
          <div class="py-4">
            <i class="fas fa-file fa-3x text-muted mb-3 d-block"></i>
            <p class="text-muted">{{ $masterObj->name ?? 'Digital object' }}</p>
            @auth
              <a href="{{ $masterUrl }}" download class="btn atom-btn-white">
                <i class="fas fa-download me-1"></i>{{ __('Download file') }}
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
        <h5 class="mb-0"><i class="fas fa-image me-2"></i>{{ __('Cover') }}</h5>
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
    @include('ahg-core::clipboard._button', ['slug' => $item->slug, 'type' => 'informationObject', 'wide' => false])
    {{-- TTS --}}
    <button type="button" class="btn btn-sm btn-outline-secondary" data-tts-action="toggle" data-tts-target="#tts-content-area" title="{{ __('Read metadata aloud') }}" data-bs-toggle="tooltip"><i class="fas fa-volume-up"></i></button>
    {{-- TTS for PDF --}}
    @if($pdfDigitalObject)
      <button type="button" class="btn btn-sm btn-outline-info" data-tts-action="read-pdf" data-tts-pdf-id="{{ $pdfDigitalObject->id }}" title="{{ __('Read PDF content aloud') }}" data-bs-toggle="tooltip"><i class="fas fa-file-pdf"></i></button>
    @endif
    {{-- Print --}}
    <button type="button" class="btn btn-sm atom-btn-white" onclick="window.print();" title="{{ __('Print this page') }}" data-bs-toggle="tooltip">
      <i class="fas fa-print"></i>
    </button>
    {{-- Favorites --}}
    @auth
      @if($favoriteId)
        <a href="{{ \Illuminate\Support\Facades\Route::has('favorites.remove') ? route('favorites.remove', $favoriteId) : url('/favorites/remove/' . $favoriteId) }}" class="btn btn-xs btn-outline-danger" title="{{ __('Remove from Favorites') }}" data-bs-toggle="tooltip"><i class="fas fa-heart-broken"></i></a>
      @else
        <a href="{{ \Illuminate\Support\Facades\Route::has('favorites.add') ? route('favorites.add', $item->slug) : url('/favorites/add/' . $item->slug) }}" class="btn btn-xs btn-outline-danger" title="{{ __('Add to Favorites') }}" data-bs-toggle="tooltip"><i class="fas fa-heart"></i></a>
      @endif
    @endauth
    {{-- Feedback --}}
    @if(\Illuminate\Support\Facades\Route::has('feedback.submit'))
      <a href="{{ route('feedback.submit', $item->slug) }}" class="btn btn-xs btn-outline-secondary" title="{{ __('Item Feedback') }}" data-bs-toggle="tooltip"><i class="fas fa-comment"></i></a>
    @endif
    {{-- Request to Publish --}}
    @if($hasDigitalObject && \Illuminate\Support\Facades\Route::has('request-to-publish.submit'))
      <a href="{{ route('request-to-publish.submit', $item->slug) }}" class="btn btn-xs btn-outline-primary" title="{{ __('Request to Publish') }}" data-bs-toggle="tooltip"><i class="fas fa-paper-plane"></i></a>
    @endif
    {{-- Cart --}}
    @if($hasDigitalObject)
      @if($cartId)
        <a href="{{ \Illuminate\Support\Facades\Route::has('cart.browse') ? route('cart.browse') : url('/cart') }}" class="btn btn-xs btn-outline-success" title="{{ __('Go to Cart') }}" data-bs-toggle="tooltip"><i class="fas fa-shopping-cart"></i></a>
      @else
        <a href="{{ \Illuminate\Support\Facades\Route::has('cart.add') ? route('cart.add', $item->slug) : url('/cart/add/' . $item->slug) }}" class="btn btn-xs btn-outline-success" title="{{ __('Add to Cart') }}" data-bs-toggle="tooltip"><i class="fas fa-cart-plus"></i></a>
      @endif
    @endif
    {{-- Loans --}}
    @auth
      @if(\Illuminate\Support\Facades\Route::has('loan.create'))
        <a href="{{ route('loan.create', ['type' => 'out', 'sector' => 'library', 'object_id' => $item->id]) }}" class="btn btn-xs btn-outline-warning" title="{{ __('New Loan') }}" data-bs-toggle="tooltip"><i class="fas fa-hand-holding"></i></a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('loan.index'))
        <a href="{{ route('loan.index', ['sector' => 'library', 'object_id' => $item->id]) }}" class="btn btn-xs btn-outline-info" title="{{ __('Manage Loans') }}" data-bs-toggle="tooltip"><i class="fas fa-exchange-alt"></i></a>
      @endif
    @endauth
  </div>

  {{-- Actions card removed — actions are in the bottom bar --}}

  {{-- ISBN Barcode --}}
  @php
    $isbn = $item->isbn ?? '';
    $cleanIsbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn));
  @endphp
  @if(!empty($cleanIsbn))
    <section class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-barcode me-2"></i>{{ __('ISBN Barcode') }}</h5>
      </div>
      <div class="card-body text-center">
        <style>#isbn-barcode rect { fill: #ffffff !important; } #isbn-barcode g rect { fill: #000000 !important; }</style>
        <svg id="isbn-barcode"></svg>
        <p class="text-muted small mt-2 mb-0">{{ $isbn }}</p>
      </div>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
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

  {{-- Active Loans --}}
  @php
    $activeLoans = \Illuminate\Support\Facades\DB::table('ahg_loan')
        ->join('ahg_loan_object', 'ahg_loan.id', '=', 'ahg_loan_object.loan_id')
        ->where('ahg_loan_object.information_object_id', $item->id)
        ->whereNotIn('ahg_loan.status', ['returned', 'closed', 'cancelled'])
        ->select('ahg_loan.id', 'ahg_loan.loan_number', 'ahg_loan.loan_type', 'ahg_loan.status', 'ahg_loan.partner_institution', 'ahg_loan.end_date')
        ->get();
  @endphp
  @if($activeLoans->isNotEmpty())
    <section class="card mb-3 border-warning">
      <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Active Loans ({{ $activeLoans->count() }})</h5>
      </div>
      <div class="list-group list-group-flush">
        @foreach($activeLoans as $loan)
          @php $isOverdue = $loan->end_date && $loan->end_date < now()->toDateString(); @endphp
          <a href="{{ route('loan.show', $loan->id) }}" class="list-group-item list-group-item-action {{ $isOverdue ? 'list-group-item-danger' : '' }}">
            <div class="d-flex justify-content-between align-items-center">
              <strong>{{ $loan->loan_number }}</strong>
              <span class="badge bg-{{ $loan->loan_type === 'out' ? 'info' : 'warning' }}">{{ $loan->loan_type === 'out' ? 'Out' : 'In' }}</span>
            </div>
            <small class="text-muted">{{ $loan->partner_institution }}</small>
            <div class="mt-1">
              <span class="badge bg-{{ $isOverdue ? 'danger' : 'success' }} me-1">{{ ucwords(str_replace('_', ' ', $loan->status)) }}</span>
              @if($isOverdue)<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>{{ __('Overdue') }}</span>@endif
              @if($loan->end_date)<small class="text-muted ms-1">Due: {{ $loan->end_date }}</small>@endif
            </div>
          </a>
        @endforeach
      </div>
    </section>
  @endif

  {{-- Related Records --}}
  <section class="card mb-3">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-link me-2"></i>{{ __('Related records') }}</h5>
    </div>
    <div class="card-body">
      @if($parentItem ?? null)
        <a href="{{ route('library.show', $parentItem->slug) }}" class="btn btn-outline-secondary w-100 mb-2">
          <i class="fas fa-level-up-alt me-2"></i>{{ __('Parent record') }}
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
        <h5 class="mb-0"><i class="fas fa-warehouse me-2"></i>{{ __('Physical storage') }}</h5>
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
        <h5 class="mb-0"><i class="fas fa-tablet-alt me-2"></i>{{ __('E-book Access') }}</h5>
      </div>
      <div class="card-body">
        <a href="{{ $item->ebook_preview_url }}" target="_blank" class="btn btn-outline-primary w-100">
          <i class="fas fa-book-reader me-2"></i>{{ __('Preview on Archive.org') }}
        </a>
      </div>
    </section>
  @endif

  {{-- External Links --}}
  @if($item->openlibrary_url || $item->goodreads_id || $item->librarything_id)
    <section class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-external-link-alt me-2"></i>{{ __('External Links') }}</h5>
      </div>
      <div class="card-body">
        @if($item->openlibrary_url)
          <a href="{{ $item->openlibrary_url }}" target="_blank" class="btn btn-outline-secondary w-100 mb-2">
            <i class="fas fa-book me-2"></i>{{ __('Open Library') }}
          </a>
        @endif

        @if($item->goodreads_id)
          <a href="https://www.goodreads.com/book/show/{{ $item->goodreads_id }}" target="_blank" class="btn btn-outline-secondary w-100 mb-2">
            <i class="fas fa-star me-2"></i>{{ __('Goodreads') }}
          </a>
        @endif

        @if($item->librarything_id)
          <a href="https://www.librarything.com/work/{{ $item->librarything_id }}" target="_blank" class="btn btn-outline-secondary w-100">
            <i class="fas fa-bookmark me-2"></i>{{ __('LibraryThing') }}
          </a>
        @endif
      </div>
    </section>
  @endif

  {{-- Provenance & Chain of Custody --}}
  @if(\Illuminate\Support\Facades\Route::has('provenance.view'))
    @php
      $provRecord = \Illuminate\Support\Facades\DB::table('provenance_record as pr')
          ->leftJoin('provenance_record_i18n as pri', function($j) {
              $j->on('pr.id', '=', 'pri.id')->where('pri.culture', '=', app()->getLocale());
          })
          ->where('pr.information_object_id', $item->id)
          ->select('pr.*', 'pri.provenance_summary')
          ->first();
      $provEvents = $provRecord
          ? \Illuminate\Support\Facades\DB::table('provenance_event')
              ->where('provenance_record_id', $provRecord->id)
              ->orderBy('event_date')
              ->limit(5)
              ->get()
          : collect();
    @endphp
    @if($provRecord || auth()->check())
    <section class="card mb-3">
      <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="fas fa-history me-2"></i>{{ __('Provenance & Chain of Custody') }}</h5>
      </div>
      <div class="card-body">
        @if($provRecord)
          @if($provRecord->provenance_summary)
            <p class="mb-2">{{ $provRecord->provenance_summary }}</p>
          @endif
          @if($provRecord->current_status)
            <span class="badge bg-info me-1">{{ ucfirst($provRecord->current_status) }}</span>
          @endif
          @if($provRecord->acquisition_type)
            <span class="badge bg-secondary me-1">{{ ucfirst($provRecord->acquisition_type) }}</span>
          @endif
          @if($provEvents->isNotEmpty())
            <ul class="list-unstyled mt-2 mb-0 small">
              @foreach($provEvents as $pe)
                <li class="mb-1">
                  <i class="fas fa-circle text-muted me-1" style="font-size:0.5rem;vertical-align:middle;"></i>
                  <strong>{{ ucfirst(str_replace('_', ' ', $pe->event_type ?? '')) }}</strong>
                  @if($pe->event_date) <span class="text-muted">{{ $pe->event_date }}</span> @endif
                </li>
              @endforeach
            </ul>
          @endif
        @else
          <p class="text-muted small mb-0">No provenance recorded yet.</p>
        @endif
        @auth
          <div class="mt-2">
            <a href="{{ route('provenance.edit', $item->slug) }}" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-edit me-1"></i>{{ $provRecord ? 'Edit' : 'Add' }} Provenance
            </a>
            @if($provRecord)
              <a href="{{ route('provenance.view', $item->slug) }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-clock me-1"></i>{{ __('View Full Timeline') }}
              </a>
            @endif
          </div>
        @endauth
      </div>
    </section>
    @endif
  @endif

  {{-- Export --}}
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-file-export me-1"></i> {{ __('Export') }}
    </div>
    <div class="list-group list-group-flush">
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.dc'))
        <a href="{{ route('informationobject.export.dc', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> {{ __('Dublin Core 1.1 XML') }}
        </a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.ead'))
        <a href="{{ route('informationobject.export.ead', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> {{ __('EAD 2002 XML') }}
        </a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.ead3'))
        <a href="{{ route('informationobject.export.ead3', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> {{ __('EAD3 1.1 XML') }}
        </a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.ead4'))
        <a href="{{ route('informationobject.export.ead4', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> {{ __('EAD 4 XML') }}
        </a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.mods'))
        <a href="{{ route('informationobject.export.mods', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> {{ __('MODS 3.5 XML') }}
        </a>
      @endif
      @if(\Illuminate\Support\Facades\Route::has('informationobject.export.rico'))
        <a href="{{ route('informationobject.export.rico', $item->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> {{ __('RiC-O JSON-LD') }}
        </a>
      @endif
      @auth
        @if(\Illuminate\Support\Facades\Route::has('informationobject.export.csv'))
          <a href="{{ route('informationobject.export.csv', $item->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-file-csv me-1"></i> {{ __('Export CSV') }}
          </a>
        @endif
      @endauth
    </div>
  </div>

  {{-- Print card --}}
  <div class="card mb-3">
    <div class="card-header fw-bold">
      <i class="fas fa-print me-1"></i> {{ __('Print') }}
    </div>
    <div class="list-group list-group-flush">
      <a href="javascript:window.print();" class="list-group-item list-group-item-action small">
        <i class="fas fa-print me-1"></i> {{ __('Print this page') }}
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
  <section class="actions mb-3 nav gap-2 flex-wrap">
    <li><a href="{{ route('library.edit', $item->slug) }}" class="btn atom-btn-outline-light">Edit</a></li>
    <li>
      <form action="{{ route('library.destroy', $item->slug) }}" method="POST" class="d-inline"
            onsubmit="return confirm('Are you sure you want to delete this library item?');">
        @csrf
        <button type="submit" class="btn atom-btn-outline-danger">{{ __('Delete') }}</button>
      </form>
    </li>
    <li><a href="{{ route('library.create', ['parent' => $item->slug]) }}" class="btn atom-btn-outline-light">Add new</a></li>
    <li><a href="{{ route('informationobject.move', $item->slug) }}" class="btn atom-btn-outline-light">Move</a></li>
    <li><a href="{{ route('library.rename', $item->slug) }}" class="btn atom-btn-outline-light">Rename</a></li>
    @if($hasDigitalObject)
      @php $doRecord = \Illuminate\Support\Facades\DB::table('digital_object')->where('object_id', $item->id)->first(); @endphp
      <li><a href="{{ url('/digitalobject/' . ($doRecord->id ?? 0) . '/edit') }}" class="btn atom-btn-outline-light"><i class="fas fa-edit me-1"></i>{{ __('Edit digital object') }}</a></li>
    @else
      <li><a href="{{ route('io.digitalobject.add', $item->slug) }}" class="btn atom-btn-outline-light"><i class="fas fa-link me-1"></i>{{ __('Link digital object') }}</a></li>
    @endif
    @if(\Illuminate\Support\Facades\Route::has('io.showUpdateStatus'))
      <li><a href="{{ route('io.showUpdateStatus', $item->slug) }}" class="btn atom-btn-outline-light"><i class="fas fa-eye me-1"></i>{{ __('Publication status') }}</a></li>
    @endif
    <li>
      <div class="dropdown d-inline-block">
        <button class="btn atom-btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">{{ __('More') }}</button>
        <ul class="dropdown-menu">
          <li><a class="dropdown-item" href="{{ route('library.browse') }}"><i class="fas fa-list me-2"></i>{{ __('Browse library') }}</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="{{ route('physicalobject.link-to', $item->slug) }}"><i class="fas fa-box me-2"></i>{{ __('Link physical storage') }}</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="{{ route('io.rights.manage', $item->slug) }}"><i class="fas fa-copyright me-2"></i>{{ __('Manage Rights') }}</a></li>
          @if(\Illuminate\Support\Facades\Route::has('grap.show'))
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ route('grap.show', $item->slug) }}"><i class="fas fa-file-invoice me-2"></i>{{ __('View GRAP data') }}</a></li>
            <li><a class="dropdown-item" href="{{ route('grap.edit', $item->slug) }}"><i class="fas fa-file-invoice me-2"></i>{{ __('Edit GRAP data') }}</a></li>
          @endif
          @if(\Illuminate\Support\Facades\Route::has('io.spectrum'))
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ route('io.spectrum', $item->slug) }}"><i class="fas fa-layer-group me-2"></i>{{ __('Spectrum data') }}</a></li>
            @if(\Illuminate\Support\Facades\Route::has('ahgspectrum.workflow'))
              <li><a class="dropdown-item" href="{{ route('ahgspectrum.workflow') }}"><i class="fas fa-tasks me-2"></i>{{ __('Workflow Status') }}</a></li>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('spectrum.label'))
              <li><a class="dropdown-item" href="{{ route('spectrum.label', $item->slug) }}"><i class="fas fa-barcode me-2"></i>{{ __('Generate barcode label') }}</a></li>
            @endif
          @endif
          @if(\Illuminate\Support\Facades\Route::has('provenance.view'))
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ route('provenance.view', $item->slug) }}"><i class="fas fa-sitemap me-2"></i>{{ __('Provenance') }}</a></li>
          @endif
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="{{ url('/label/' . $item->slug) }}"><i class="fas fa-tag me-2"></i>{{ __('Generate label') }}</a></li>
          @if(\Illuminate\Support\Facades\Route::has('ahgtranslation.translate')
              && \AhgCore\Services\AclService::check($item, 'translate'))
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#ahgTranslateSbsModal-{{ $item->id }}"><i class="fas fa-columns me-2"></i>{{ __('Translate (side-by-side)') }}</a></li>
            @if(\Illuminate\Support\Facades\Schema::hasTable('museum_metadata') && \Illuminate\Support\Facades\DB::table('museum_metadata')->where('object_id', $item->id)->exists())
              <li><a class="dropdown-item text-warning" href="#" data-bs-toggle="modal" data-bs-target="#ahgTranslateCcoValuesModal-{{ $item->id }}"><i class="fas fa-landmark me-2"></i>{{ __('Translate field data values (CCO)') }}</a></li>
            @endif
          @endif
        </ul>
      </div>
    </li>
  </section>

  {{-- Side-by-side per-field translator + dedicated CCO values modal --}}
  @if(view()->exists('ahg-translation::_translate-sbs') && \AhgCore\Services\AclService::check($item, 'translate'))
    @include('ahg-translation::_translate-sbs', ['objectId' => $item->id])
    @if(\Illuminate\Support\Facades\Schema::hasTable('museum_metadata') && \Illuminate\Support\Facades\DB::table('museum_metadata')->where('object_id', $item->id)->exists())
      @include('ahg-translation::_translate-cco-values', ['objectId' => $item->id])
    @endif
  @endif
  @endauth

  {{-- NER Modal --}}
  @auth
  @if(\Illuminate\Support\Facades\Route::has('io.ai.review'))
  <div class="modal fade" id="nerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="modal-title"><i class="fas fa-brain me-2"></i>{{ __('Extract Entities (NER)') }}</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">Named Entity Recognition — extract persons, organizations, places, dates from <strong>{{ $item->title ?? 'this record' }}</strong></p>
          <div class="text-center mb-3" id="nerExtractSection">
            <button type="button" class="btn btn-primary btn-lg" id="nerExtractBtn">
              <i class="fas fa-brain me-2"></i>{{ __('Extract Entities') }}
            </button>
          </div>
          <div id="nerResults" style="display:none">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small" id="nerResultsMeta"></span>
            </div>
            <div id="nerResultsBody"></div>
          </div>
        </div>
        <div class="modal-footer">
          <a href="{{ route('io.ai.review') }}?object_id={{ $item->id }}" class="btn btn-outline-primary btn-sm" id="nerFooterReview" style="display:none">
            <i class="fas fa-list-check me-1"></i>{{ __('Review & Link') }}
          </a>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
        </div>
      </div>
    </div>
  </div>
  <script>
  (function() {
    var objectId = {{ $item->id }};
    var icons = { PERSON: 'fa-user', ORG: 'fa-building', GPE: 'fa-map-marker-alt', DATE: 'fa-calendar', LOC: 'fa-globe', NORP: 'fa-users', EVENT: 'fa-bolt', WORK_OF_ART: 'fa-palette', LANGUAGE: 'fa-language', FAC: 'fa-landmark' };
    var colors = { PERSON: 'primary', ORG: 'success', GPE: 'info', DATE: 'warning', LOC: 'info', NORP: 'secondary', EVENT: 'danger', WORK_OF_ART: 'dark', LANGUAGE: 'secondary', FAC: 'secondary' };

    document.getElementById('nerExtractBtn').addEventListener('click', function() {
      var btn = this;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Extracting...';

      fetch('/admin/ai/ner/extract/' + objectId, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          'Accept': 'application/json'
        }
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-brain me-2"></i>Re-Extract';

        if (!data.success) {
          document.getElementById('nerResultsBody').innerHTML = '<div class="alert alert-danger">' + (data.error || 'Extraction failed') + '</div>';
          document.getElementById('nerResults').style.display = '';
          return;
        }

        var entities = data.entities || {};
        var count = data.entity_count || 0;
        var time = data.processing_time_ms || 0;

        document.getElementById('nerResultsMeta').textContent = 'Found ' + count + ' entities in ' + time + 'ms';
        document.getElementById('nerResults').style.display = '';
        document.getElementById('nerFooterReview').style.display = '';

        if (count === 0) {
          document.getElementById('nerResultsBody').innerHTML = '<p class="text-muted text-center">No entities found.</p>';
          return;
        }

        var html = '';
        for (var type in entities) {
          var icon = icons[type] || 'fa-tag';
          var color = colors[type] || 'secondary';
          html += '<div class="mb-3"><h6><i class="fas ' + icon + ' me-1 text-' + color + '"></i>' + type + ' <span class="badge bg-' + color + '">' + entities[type].length + '</span></h6>';
          html += '<div class="d-flex flex-wrap gap-1">';
          entities[type].forEach(function(e) {
            html += '<span class="badge bg-' + color + ' bg-opacity-10 text-' + color + ' border border-' + color + '">' + e + '</span>';
          });
          html += '</div></div>';
        }
        document.getElementById('nerResultsBody').innerHTML = html;
      })
      .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-brain me-2"></i>Extract Entities';
        document.getElementById('nerResultsBody').innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
        document.getElementById('nerResults').style.display = '';
      });
    });
  })();
  </script>
  @endif
  @endauth
@endsection
