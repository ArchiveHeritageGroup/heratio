@extends('theme::layouts.3col')

@section('title', ($io->title ?? config('app.ui_label_informationobject', 'Archival description')))
@section('body-class', 'view informationobject')

{{-- ============================================================ --}}
{{-- LEFT SIDEBAR: Treeview / Holdings + Quick search             --}}
{{-- ============================================================ --}}
@section('sidebar')

  {{-- Repository logo (matching AtoM context menu) --}}
  @if(isset($repository) && $repository)
    @php
      $repoLogoPath = null;
      $repoDigitalObject = \Illuminate\Support\Facades\DB::table('digital_object')
        ->where('object_id', $repository->id)
        ->first();
      if ($repoDigitalObject) {
        $repoLogoPath = \AhgCore\Services\DigitalObjectService::getUrl($repoDigitalObject);
      }
    @endphp
    <div class="text-center mb-3">
      @if($repoLogoPath)
        <a href="{{ route('repository.show', $repository->slug) }}">
          <img src="{{ $repoLogoPath }}" alt="{{ $repository->name }}" class="img-fluid" style="max-height:80px;">
        </a>
      @else
        <a href="{{ route('repository.show', $repository->slug) }}" class="text-decoration-none">
          <strong>{{ $repository->name }}</strong>
        </a>
      @endif
    </div>
  @endif

  {{-- Static pages menu (visible to all users, matching AtoM context menu) --}}
  @include('ahg-menu-manage::_static-pages-menu')

  {{-- Dynamic treeview hierarchy --}}
  @include('ahg-io-manage::partials._treeview', ['io' => $io])

  {{-- Quick search within this collection --}}
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-search me-1"></i> Search within
    </div>
    <div class="card-body p-2">
      <form action="{{ route('informationobject.browse') }}" method="GET">
        <input type="hidden" name="collection" value="{{ $io->id }}">
        <div class="input-group input-group-sm">
          <input type="text" name="subquery" class="form-control" placeholder="Search...">
          <button class="btn atom-btn-white" type="submit">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- ===== Authenticated-only management sections ===== --}}
  @auth

    {{-- Collections Management (only if collections management controllers are available) --}}
    @if(class_exists(\AhgInformationObjectManage\Controllers\ProvenanceController::class))
    {{-- Collections Management --}}
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
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
        <a href="{{ route('io.research.citation', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-quote-left me-1"></i> Cite this Record
        </a>
      </div>
    </div>
    @endif {{-- end Collections Management package check --}}

    {{-- Digital Preservation (OAIS) --}}
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-shield-alt me-1"></i> Digital Preservation (OAIS)
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.preservation', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-box-open me-1"></i> Preservation packages
        </a>
      </div>
    </div>

    {{-- AI Tools (only if AI controller is available) --}}
    @if(class_exists(\AhgInformationObjectManage\Controllers\AiController::class))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-robot me-1"></i> AI Tools
      </div>
      <div class="list-group list-group-flush">
        <a href="#" class="list-group-item list-group-item-action small" data-bs-toggle="modal" data-bs-target="#describeModal">
          <i class="fas fa-eye me-1"></i> Describe Object/Image
        </a>
        <a href="#" class="list-group-item list-group-item-action small" data-bs-toggle="modal" data-bs-target="#nerModal">
          <i class="fas fa-brain me-1"></i> Extract Entities (NER)
        </a>
        <a href="#" class="list-group-item list-group-item-action small" data-bs-toggle="modal" data-bs-target="#summaryModal">
          <i class="fas fa-file-alt me-1"></i> Generate Summary
        </a>
        <a href="#" class="list-group-item list-group-item-action small" data-bs-toggle="modal" data-bs-target="#translateModal">
          <i class="fas fa-language me-1"></i> Translate
        </a>
        <a href="{{ route('io.ai.review') }}?object_id={{ $io->id }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-list-check me-1"></i> NER Review
        </a>
        @if(isset($nerEntityCount) && $nerEntityCount > 0)
          <a href="{{ route('io.ai.extract', $io->id) }}#entities" class="list-group-item list-group-item-action small d-flex justify-content-between align-items-center">
            <span><i class="fas fa-file-pdf me-1"></i> View PDF Entities</span>
            <span class="badge bg-success rounded-pill">{{ $nerEntityCount }}</span>
          </a>
        @endif
      </div>
    </div>
    @endif {{-- end AI Tools package check --}}

    {{-- Privacy & PII (only if privacy controller is available) --}}
    @if(class_exists(\AhgInformationObjectManage\Controllers\PrivacyController::class))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
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
    @endif {{-- end Privacy package check --}}

    {{-- Rights --}}
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-copyright me-1"></i> Rights
      </div>

      {{-- Rights Status badges --}}
      @if(auth()->check())
        <div class="card-body py-2">
          @if(isset($extendedRights) && $extendedRights->isNotEmpty())
            <span class="badge bg-success me-1"><i class="fas fa-check-circle me-1"></i>Extended rights applied</span>
          @endif
          @if(isset($activeEmbargo) && $activeEmbargo)
            <span class="badge bg-danger me-1"><i class="fas fa-ban me-1"></i>Under embargo</span>
          @endif
          @if((!isset($extendedRights) || $extendedRights->isEmpty()) && (!isset($activeEmbargo) || !$activeEmbargo))
            <span class="badge bg-secondary"><i class="fas fa-info-circle me-1"></i>No extended rights or embargo</span>
          @endif
        </div>
      @endif

      <div class="list-group list-group-flush">
        <a href="{{ route('io.rights.extended', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-copyright me-1"></i> Add extended rights
        </a>

        {{-- Conditional embargo links --}}
        @if(isset($activeEmbargo) && $activeEmbargo)
          <a href="{{ route('io.rights.embargo', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-edit me-1"></i> Edit embargo
          </a>
          <form method="POST" action="{{ route('io.rights.embargo.lift', $activeEmbargo->id) }}" class="d-inline">
            @csrf
            <button type="submit" class="list-group-item list-group-item-action small text-danger border-0 w-100 text-start"
                    onclick="return confirm('Are you sure you want to lift this embargo?')">
              <i class="fas fa-unlock me-1"></i> Lift embargo
            </button>
          </form>
        @else
          <a href="{{ route('io.rights.embargo', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-lock me-1"></i> Add embargo
          </a>
        @endif

        <a href="{{ route('io.rights.export', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-download me-1"></i> Export rights (JSON-LD)
        </a>
      </div>
    </div>

    {{-- Research Tools (only if research controller is available) --}}
    @if(class_exists(\AhgInformationObjectManage\Controllers\ResearchController::class))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
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
        <a href="{{ route('io.research.citation', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-quote-left me-1"></i> Generate citation
        </a>
      </div>
    </div>
    @endif {{-- end Research Tools package check --}}

  @endauth


@endsection

{{-- ============================================================ --}}
{{-- TITLE BLOCK                                                  --}}
{{-- ============================================================ --}}
@section('title-block')

  {{-- Validation errors / error schema (matching AtoM errorSchema display) --}}
  @if($errors->any())
    <div class="alert alert-danger" role="alert">
      <ul class="list-unstyled mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

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

  {{-- Translation links (other cultures available) --}}
  @if(isset($translationLinks) && !empty($translationLinks))
    <div class="dropdown d-inline-block mb-3 translation-links">
      <button class="btn btn-sm atom-btn-white dropdown-toggle" type="button" id="translation-links-button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-globe-europe me-1" aria-hidden="true"></i>
        Other languages available
      </button>
      <ul class="dropdown-menu mt-2" aria-labelledby="translation-links-button">
        @foreach($translationLinks as $code => $translation)
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.show', $io->slug) }}?sf_culture={{ $code }}">
              {{ $translation['language'] }} &raquo; {{ $translation['name'] }}
            </a>
          </li>
        @endforeach
      </ul>
    </div>
  @endif

@endsection

{{-- ============================================================ --}}
{{-- BEFORE CONTENT: Digital object reference image               --}}
{{-- ============================================================ --}}
@section('before-content')

  {{-- Imageflow: scrollable gallery of child IO thumbnails (matching AtoM imageflow component) --}}
  @if(isset($childThumbnails) && $childThumbnails->isNotEmpty())
    <div class="imageflow-strip border-bottom mb-2 py-2 px-1" style="background:#f8f8f8;">
      <div class="d-flex overflow-auto gap-2 px-2" style="scrollbar-width:thin;">
        @foreach($childThumbnails as $ct)
          @php
            $thumbUrl = \AhgCore\Services\DigitalObjectService::getUrl($ct);
          @endphp
          <a href="{{ route('informationobject.show', $ct->slug) }}" class="flex-shrink-0 text-center text-decoration-none" style="width:100px;" title="{{ $ct->title ?: '[Untitled]' }}">
            <img src="{{ $thumbUrl }}" alt="{{ $ct->title ?: '' }}" class="img-thumbnail" style="width:90px;height:68px;object-fit:cover;">
            <small class="d-block text-truncate text-muted mt-1" style="font-size:.7rem;">{{ Str::limit($ct->title ?: '[Untitled]', 18) }}</small>
          </a>
        @endforeach
      </div>
      @if(isset($childThumbnailTotal) && $childThumbnailTotal > $childThumbnails->count())
        <div class="text-center mt-1">
          <small class="text-muted">Showing {{ $childThumbnails->count() }} of {{ $childThumbnailTotal }} digital objects</small>
          <a href="{{ url('/informationobject/browse') }}?{{ http_build_query(['parent' => $io->slug, 'topLod' => 0]) }}" class="btn btn-sm atom-btn-white ms-2">
            <i class="fas fa-images me-1" aria-hidden="true"></i> Show all {{ $childThumbnailTotal }} items
          </a>
        </div>
      @endif
    </div>
  @endif

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

      // Non-native formats that need streaming/transcoding (matching AtoM's needs_streaming list)
      $nonNativeVideo = ['video/x-ms-wmv', 'video/x-ms-asf', 'video/x-msvideo', 'video/quicktime',
          'video/x-flv', 'video/x-matroska', 'video/mp2t', 'video/x-ms-wtv', 'video/hevc',
          'application/mxf', 'video/3gpp', 'video/avi'];
      $nonNativeAudio = ['audio/aiff', 'audio/x-aiff', 'audio/basic', 'audio/x-au',
          'audio/ac3', 'audio/x-ms-wma', 'audio/x-pn-realaudio'];
      $masterMime = $masterObj->mime_type ?? '';
      $needsStreaming = in_array($masterMime, $nonNativeVideo) || in_array($masterMime, $nonNativeAudio);

      // For non-native formats: prefer reference derivative (should be MP4/MP3), fallback to master
      $videoSrc = ($needsStreaming && $refObj) ? $refUrl : $masterUrl;
      $videoMime = ($needsStreaming && $refObj) ? ($refObj->mime_type ?? 'video/mp4') : $masterMime;

      // Check for 3D model files (GLB, GLTF, OBJ, etc.)
      $is3DModel = $masterObj && in_array(strtolower($masterMime), [
          'model/gltf-binary', 'model/gltf+json', 'model/gltf',
          'model/obj', 'application/x-tgif', 'model/stl',
          'application/octet-stream', // Some GLB files uploaded without proper MIME
      ]);
      // Also check by extension as fallback
      if (!$is3DModel && $masterObj && $masterObj->name) {
          $ext = strtolower(pathinfo($masterObj->name, PATHINFO_EXTENSION));
          $is3DModel = in_array($ext, ['glb', 'gltf', 'obj', 'stl', 'fbx', 'ply']);
      }
    @endphp

    <div class="digital-object-reference text-center p-3 border-bottom">
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
        {{-- Video: HTML5 player with streaming fallback for non-native formats --}}
        <video id="ahg-video-player" controls class="w-100" style="max-height:500px; background:#000;" preload="metadata"
               @if($thumbUrl) poster="{{ $thumbUrl }}" @endif>
          <source src="{{ $videoSrc }}" type="{{ $videoMime }}">
          @if($needsStreaming && $videoSrc !== $masterUrl)
            {{-- Also try master as fallback --}}
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
        {{-- Audio: Enhanced player with speed/skip controls (matching AtoM AhgMediaPlayer) --}}
        @php
          $audioSrc = $needsStreaming && $refObj ? $refUrl : $masterUrl;
          $audioMime = $needsStreaming && $refObj ? ($refObj->mime_type ?? 'audio/mpeg') : $masterMime;
          $audioPlayerId = 'ahg-audio-' . $io->id;
        @endphp
        <div class="ahg-media-player rounded p-3" style="background:linear-gradient(135deg,#1a1a2e,#16213e);">
          <audio id="{{ $audioPlayerId }}" preload="metadata" style="display:none;">
            <source src="{{ $audioSrc }}" type="{{ $audioMime }}">
            @if($needsStreaming && $audioSrc !== $masterUrl)
              <source src="{{ $masterUrl }}" type="{{ $masterMime }}">
            @endif
          </audio>

          {{-- Waveform / progress bar --}}
          <div id="{{ $audioPlayerId }}-progress" class="mb-3" style="cursor:pointer;height:60px;background:rgba(255,255,255,0.05);border-radius:6px;position:relative;overflow:hidden;">
            <div id="{{ $audioPlayerId }}-fill" style="height:100%;width:0%;background:linear-gradient(90deg,rgba(13,110,253,0.4),rgba(13,110,253,0.15));position:absolute;transition:width 0.1s;"></div>
            <div class="d-flex align-items-center justify-content-center h-100 position-relative">
              <i class="fas fa-music fa-2x text-white" style="opacity:0.15;"></i>
            </div>
          </div>

          {{-- Time display --}}
          <div class="d-flex justify-content-between text-white mb-2" style="font-size:0.8rem;opacity:0.7;">
            <span id="{{ $audioPlayerId }}-current">0:00</span>
            <span id="{{ $audioPlayerId }}-duration">0:00</span>
          </div>

          {{-- Controls --}}
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

          {{-- File info + download --}}
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

        <script nonce="{{ $cspNonce ?? '' }}">
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
        {{-- 3D Model viewer --}}
        @php
          $modelViewerId = 'model-3d-' . ($masterObj->id ?? uniqid());
          $modelExt = strtolower(pathinfo($masterObj->name ?? '', PATHINFO_EXTENSION));
          $isGlb = in_array($modelExt, ['glb', 'gltf']);
        @endphp
        <div class="digitalObject3D">
          <div class="d-flex flex-column align-items-center">
            <div class="mb-2">
              <span class="badge bg-primary"><i class="fas fa-cube me-1"></i>3D Model</span>
              <span class="badge bg-secondary">{{ $masterObj->name ?? '3D Model' }}</span>
              <span class="badge bg-info">{{ strtoupper($modelExt) }}</span>
            </div>

            @if($isGlb)
              {{-- GLB/GLTF: Google model-viewer --}}
              <div id="{{ $modelViewerId }}-container" style="width: 100%; height: 400px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); border-radius: 8px;">
                <model-viewer id="{{ $modelViewerId }}" src="{{ $masterUrl }}" camera-controls touch-action="pan-y" shadow-intensity="1" exposure="1" style="width:100%;height:100%;background:transparent;border-radius:8px;" alt="3D model"></model-viewer>
              </div>
            @else
              {{-- OBJ/STL/PLY/FBX: Three.js viewer --}}
              <div id="{{ $modelViewerId }}-container" style="width: 100%; height: 400px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); border-radius: 8px; position: relative;">
                <canvas id="{{ $modelViewerId }}-canvas" style="width:100%;height:100%;border-radius:8px;"></canvas>
                <div id="{{ $modelViewerId }}-loading" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:#fff;">
                  <i class="fas fa-spinner fa-spin fa-2x"></i><br><small>Loading 3D model...</small>
                </div>
              </div>
              <script type="importmap">
              { "imports": { "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js", "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/" } }
              </script>
              <script type="module" nonce="{{ $cspNonce ?? '' }}">
              import * as THREE from 'three';
              import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
              @if($modelExt === 'obj')
              import { OBJLoader } from 'three/addons/loaders/OBJLoader.js';
              @elseif($modelExt === 'stl')
              import { STLLoader } from 'three/addons/loaders/STLLoader.js';
              @endif

              var container = document.getElementById('{{ $modelViewerId }}-container');
              var canvas = document.getElementById('{{ $modelViewerId }}-canvas');
              var loading = document.getElementById('{{ $modelViewerId }}-loading');
              var w = container.clientWidth, h = container.clientHeight;

              var scene = new THREE.Scene();
              scene.background = new THREE.Color(0x1a1a2e);
              var camera = new THREE.PerspectiveCamera(45, w / h, 0.1, 1000);
              camera.position.set(0, 1, 3);

              var renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true });
              renderer.setSize(w, h);
              renderer.setPixelRatio(window.devicePixelRatio);

              var controls = new OrbitControls(camera, canvas);
              controls.enableDamping = true;

              scene.add(new THREE.AmbientLight(0xffffff, 0.6));
              var dirLight = new THREE.DirectionalLight(0xffffff, 0.8);
              dirLight.position.set(5, 10, 7);
              scene.add(dirLight);
              scene.add(new THREE.HemisphereLight(0xffffff, 0x444444, 0.4));

              @if($modelExt === 'obj')
              var loader = new OBJLoader();
              @elseif($modelExt === 'stl')
              var loader = new STLLoader();
              @endif

              loader.load('{{ $masterUrl }}', function(object) {
                @if($modelExt === 'stl')
                var material = new THREE.MeshPhongMaterial({ color: 0xcccccc });
                object = new THREE.Mesh(object, material);
                @endif

                var box = new THREE.Box3().setFromObject(object);
                var center = box.getCenter(new THREE.Vector3());
                var size = box.getSize(new THREE.Vector3());
                var maxDim = Math.max(size.x, size.y, size.z);
                var scale = 2 / maxDim;
                object.scale.multiplyScalar(scale);
                object.position.sub(center.multiplyScalar(scale));

                scene.add(object);
                loading.style.display = 'none';
                camera.position.set(0, 1, 3);
                controls.update();
              }, function(xhr) {
                if (xhr.total) loading.innerHTML = '<small style="color:#fff;">' + Math.round(xhr.loaded/xhr.total*100) + '%</small>';
              }, function(err) {
                loading.innerHTML = '<div class="text-danger"><i class="fas fa-exclamation-triangle"></i> Failed to load</div>';
                console.error('3D load error:', err);
              });

              function animate() { requestAnimationFrame(animate); controls.update(); renderer.render(scene, camera); }
              animate();

              window.addEventListener('resize', function() {
                var w2 = container.clientWidth, h2 = container.clientHeight;
                camera.aspect = w2 / h2; camera.updateProjectionMatrix();
                renderer.setSize(w2, h2);
              });
              </script>
            @endif

            <div id="{{ $modelViewerId }}-error" class="alert alert-danger mt-2 d-none" style="max-width:500px;">
              <i class="fas fa-exclamation-triangle me-1"></i>
              <span>Failed to load 3D model.</span>
              <br><small class="text-muted">File: {{ $masterObj->name ?? 'Unknown' }}</small>
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

        {{-- 3D Model Fullscreen Modal --}}
        <div class="modal fade" id="model3d-fullscreen-modal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-fullscreen" style="max-width:100vw;margin:0;">
            <div class="modal-content" style="background:#1a1a2e;height:100vh;">
              <div class="modal-header" style="background:var(--ahg-primary);color:#fff;position:absolute;top:0;left:0;right:0;z-index:10;">
                <h5 class="modal-title"><i class="fas fa-cube me-2"></i>3D Model Viewer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body p-0" style="position:absolute;top:0;left:0;right:0;bottom:0;overflow:hidden;">
                <model-viewer 
                  id="model-3d-fullscreen"
                  src="{{ $masterUrl }}" 
                  camera-controls
                  touch-action="pan-y"
                  shadow-intensity="1"
                  exposure="1"
                  style="width:100%;height:100%;background:linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);"
                  alt="3D model">
                </model-viewer>
              </div>
              <div class="modal-footer" style="background:#1a1a2e;color:#fff;position:absolute;bottom:0;left:0;right:0;z-index:10;">
                <small>
                  <i class="fas fa-mouse me-1"></i>Drag to rotate | 
                  <i class="fas fa-search-plus me-1"></i>Scroll to zoom | 
                  <i class="fas fa-redo me-1"></i>Double-click to reset
                </small>
                <a href="{{ $masterUrl }}" download class="btn btn-sm btn-outline-light">
                  <i class="fas fa-download me-1"></i>Download
                </a>
              </div>
            </div>
          </div>
        </div>

        {{-- Load model-viewer from CDN and add error handling --}}
        <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js"></script>
        <script nonce="{{ $cspNonce ?? '' }}">
        document.addEventListener('DOMContentLoaded', function() {
          var mv = document.getElementById('{{ $modelViewerId }}');
          var errorDiv = document.getElementById('{{ $modelViewerId }}-error');
          if (mv && errorDiv) {
            mv.addEventListener('error', function(e) {
              console.error('model-viewer error:', e);
              errorDiv.classList.remove('d-none');
            });
            mv.addEventListener('load', function() {
              errorDiv.classList.add('d-none');
            });
          }
        });
        </script>

      @elseif($refUrl || $thumbUrl)
        {{-- Image: OpenSeadragon + Mirador viewer (matching AtoM) --}}
        @php $viewerId = 'iiif-viewer-' . $io->id; $imgSrc = $masterUrl ?: $refUrl; @endphp

        {{-- Viewer toggle --}}
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="btn-group btn-group-sm" role="group">
            <button id="btn-osd-{{ $viewerId }}" class="btn atom-btn-white active" title="OpenSeadragon Deep Zoom">
              <i class="fas fa-search-plus me-1"></i>Deep Zoom
            </button>
            <button id="btn-mirador-{{ $viewerId }}" class="btn atom-btn-white" title="Mirador IIIF Viewer">
              <i class="fas fa-columns me-1"></i>Mirador
            </button>
            <button id="btn-img-{{ $viewerId }}" class="btn atom-btn-white" title="Simple image">
              <i class="fas fa-image me-1"></i>Image
            </button>
          </div>
          <div class="btn-group btn-group-sm">
            <a href="{{ $imgSrc }}" target="_blank" class="btn atom-btn-white" title="Open full size">
              <i class="fas fa-external-link-alt"></i>
            </a>
            <button id="btn-fs-{{ $viewerId }}" class="btn atom-btn-white" title="Fullscreen">
              <i class="fas fa-expand"></i>
            </button>
          </div>
        </div>

        {{-- OSD container --}}
        <div id="osd-{{ $viewerId }}" style="width:100%;height:500px;background:#1a1a1a;border-radius:8px;"></div>

        {{-- Mirador container (hidden) --}}
        <div id="mirador-{{ $viewerId }}" style="width:100%;height:500px;border-radius:8px;display:none;"></div>

        {{-- Simple image (hidden) --}}
        <div id="img-{{ $viewerId }}" style="display:none;" class="text-center">
          <a href="{{ $imgSrc }}" target="_blank">
            <img src="{{ $refUrl ?: $thumbUrl }}" alt="{{ $io->title }}" class="img-fluid img-thumbnail" style="max-height:500px;">
          </a>
        </div>

        <script src="{{ asset('vendor/ahg-theme-b5/js/vendor/openseadragon.min.js') }}"></script>
        <script src="{{ asset('vendor/ahg-theme-b5/js/ahg-iiif-viewer.js') }}"></script>
        <script nonce="{{ $cspNonce ?? '' }}">
        document.addEventListener('DOMContentLoaded', function() {
          initIiifViewer('{{ $viewerId }}', '{{ url($imgSrc) }}', '{{ $io->title }}');
        });
        </script>
      @elseif(($masterObj->path ?? '') && (str_contains($masterObj->path, 'sketchfab.com') || str_contains($masterObj->path, 'youtube.com') || str_contains($masterObj->path, 'youtu.be') || str_contains($masterObj->path, 'vimeo.com')))
        {{-- External embed (Sketchfab 3D, YouTube, Vimeo) with IIIF viewer toggle --}}
        @php
          $embedUrl = $masterObj->path;
          if (str_contains($masterObj->path, 'sketchfab.com/3d-models/')) {
              $modelSlug = basename(parse_url($masterObj->path, PHP_URL_PATH));
              preg_match('/([0-9a-f]{32})$/', $modelSlug, $m);
              $modelUuid = $m[1] ?? $modelSlug;
              $embedUrl = 'https://sketchfab.com/models/' . $modelUuid . '/embed';
          } elseif (str_contains($masterObj->path, 'youtube.com/watch')) {
              parse_str(parse_url($masterObj->path, PHP_URL_QUERY) ?? '', $yt);
              $embedUrl = 'https://www.youtube.com/embed/' . ($yt['v'] ?? '');
          } elseif (str_contains($masterObj->path, 'youtu.be/')) {
              $embedUrl = 'https://www.youtube.com/embed/' . basename(parse_url($masterObj->path, PHP_URL_PATH));
          } elseif (str_contains($masterObj->path, 'vimeo.com/')) {
              $embedUrl = 'https://player.vimeo.com/video/' . basename(parse_url($masterObj->path, PHP_URL_PATH));
          }
          $isSketchfab = str_contains($masterObj->path, 'sketchfab.com');
          $embedLabel = $isSketchfab ? 'Sketchfab 3D' : 'Embed';
        @endphp

        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="badge bg-primary"><i class="fas {{ $isSketchfab ? 'fa-cube' : 'fa-play' }} me-1"></i>{{ $embedLabel }}</span>
          <a href="{{ $masterObj->path }}" target="_blank" class="btn btn-sm atom-btn-white" title="Open on original site">
            <i class="fas fa-external-link-alt me-1"></i>View on {{ $isSketchfab ? 'Sketchfab' : 'original site' }}
          </a>
        </div>

        <iframe src="{{ $embedUrl }}" frameborder="0" allow="autoplay; fullscreen; xr-spatial-tracking"
                allowfullscreen mozallowfullscreen="true" webkitallowfullscreen="true"
                style="width:100%;height:500px;border-radius:8px;border:none;"></iframe>

      @else
        {{-- No displayable object: show download link --}}
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

    {{-- Media controls: Extract Metadata, Transcription, Snippets (matching AtoM) --}}
    @if(isset($digitalObjects) && ($digitalObjects['master'] ?? null))
      @php
        $doId = $digitalObjects['master']->id;
        $doMediaType = \AhgCore\Services\DigitalObjectService::getMediaType($digitalObjects['master']);
        $isMediaFile = in_array($doMediaType, ['audio', 'video']);

        // Check for existing metadata
        $mediaMetadata = \Illuminate\Support\Facades\DB::table('media_metadata')
          ->where('digital_object_id', $doId)->first();

        // Check for existing transcription
        $transcription = \Illuminate\Support\Facades\DB::table('media_transcription')
          ->where('digital_object_id', $doId)->first();

        // Get snippets
        $snippets = \Illuminate\Support\Facades\DB::table('media_snippets')
          ->where('digital_object_id', $doId)->orderBy('start_time')->get();
      @endphp

      @if($isMediaFile)
        {{-- Media Information panel --}}
        @if($mediaMetadata)
          <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#media-info-collapse" aria-expanded="false" style="background:var(--ahg-primary);color:#fff">
              <span><i class="fas fa-info-circle me-2"></i>Media Information</span>
              <span class="badge bg-{{ $doMediaType === 'audio' ? 'info' : 'primary' }}">
                {{ ucfirst($doMediaType) }}{{ $mediaMetadata->format ? ' - ' . strtoupper($mediaMetadata->format) : '' }}
              </span>
            </div>
            <div class="collapse" id="media-info-collapse">
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <h6 class="text-muted mb-2">Technical Details</h6>
                  <table class="table table-bordered table-sm table-borderless">
                    @if($mediaMetadata->duration)<tr><td class="text-muted">Duration:</td><td>{{ gmdate('H:i:s', (int) $mediaMetadata->duration) }}</td></tr>@endif
                    @if($mediaMetadata->file_size)<tr><td class="text-muted">File Size:</td><td>{{ \AhgCore\Services\DigitalObjectService::formatFileSize($mediaMetadata->file_size) }}</td></tr>@endif
                    @if($mediaMetadata->bitrate)<tr><td class="text-muted">Bitrate:</td><td>{{ number_format($mediaMetadata->bitrate / 1000) }} kbps</td></tr>@endif
                    @if($mediaMetadata->audio_codec ?? null)<tr><td class="text-muted">Audio Codec:</td><td>{{ $mediaMetadata->audio_codec }}</td></tr>@endif
                    @if($mediaMetadata->audio_sample_rate ?? null)<tr><td class="text-muted">Sample Rate:</td><td>{{ number_format($mediaMetadata->audio_sample_rate) }} Hz</td></tr>@endif
                    @if($mediaMetadata->audio_channels ?? null)<tr><td class="text-muted">Channels:</td><td>{{ $mediaMetadata->audio_channels == 1 ? 'Mono' : ($mediaMetadata->audio_channels == 2 ? 'Stereo' : $mediaMetadata->audio_channels . 'ch') }}</td></tr>@endif
                    @if($mediaMetadata->video_codec ?? null)<tr><td class="text-muted">Video Codec:</td><td>{{ $mediaMetadata->video_codec }}</td></tr>@endif
                    @if(($mediaMetadata->video_width ?? null) && ($mediaMetadata->video_height ?? null))<tr><td class="text-muted">Resolution:</td><td>{{ $mediaMetadata->video_width }} x {{ $mediaMetadata->video_height }}</td></tr>@endif
                    @if($mediaMetadata->video_frame_rate ?? null)<tr><td class="text-muted">Frame Rate:</td><td>{{ round($mediaMetadata->video_frame_rate, 2) }} fps</td></tr>@endif
                  </table>
                </div>
                <div class="col-md-6">
                  <h6 class="text-muted mb-2">Embedded Metadata</h6>
                  <table class="table table-bordered table-sm table-borderless">
                    @foreach(['title', 'artist', 'album', 'genre', 'year', 'copyright'] as $field)
                      @if($mediaMetadata->$field ?? null)<tr><td class="text-muted">{{ ucfirst($field) }}:</td><td>{{ e($mediaMetadata->$field) }}</td></tr>@endif
                    @endforeach
                  </table>
                </div>
              </div>
            </div>
            </div>{{-- /collapse --}}
          </div>
        @else
          {{-- Extract Metadata button --}}
          @auth
          <div class="card mb-3">
            <div class="card-body text-center py-4">
              <i class="fas fa-music fa-2x text-muted mb-3 d-block"></i>
              <p class="text-muted mb-3">Media metadata has not been extracted yet.</p>
              <button class="btn atom-btn-white" id="extract-btn-{{ $doId }}" data-action="extract" data-do-id="{{ $doId }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-magic me-1"></i>Extract Metadata</button>
            </div>
          </div>
          @endauth
        @endif

        {{-- Transcription panel --}}
        @if($transcription)
          @php
            $segments = json_decode($transcription->segments ?? '[]', true) ?: [];
          @endphp
          <div class="card mb-3" id="transcription-panel-{{ $doId }}">
            <div class="card-header d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#transcription-collapse" aria-expanded="false" style="background:var(--ahg-primary);color:#fff">
              <span><i class="fas fa-file-alt me-2"></i>Transcription</span>
              <div class="btn-group btn-group-sm" onclick="event.stopPropagation();">
                <a href="/media/transcription/{{ $doId }}/vtt" class="btn atom-btn-white" title="Download VTT"><i class="fas fa-closed-captioning"></i> VTT</a>
                <a href="/media/transcription/{{ $doId }}/srt" class="btn atom-btn-white" title="Download SRT"><i class="fas fa-file-video"></i> SRT</a>
                @auth
                <button class="btn atom-btn-white" title="Re-transcribe" data-action="retranscribe" data-do-id="{{ $doId }}" data-lang="{{ $transcription->language ?? 'en' }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-redo"></i></button>
                @endauth
              </div>
            </div>
            <div class="collapse" id="transcription-collapse">
            <div class="card-body py-2 bg-light border-bottom">
              <small class="text-muted">
                <i class="fas fa-language me-1"></i>{{ \Locale::getDisplayLanguage($transcription->language ?? 'en', 'en') }}
                @if($transcription->duration ?? null) &bull; <i class="fas fa-clock me-1"></i>{{ gmdate('H:i:s', (int) $transcription->duration) }}@endif
                @if(count($segments)) &bull; <i class="fas fa-paragraph me-1"></i>{{ count($segments) }} segments @endif
                @if($transcription->confidence ?? null)
                  &bull; <span class="badge bg-{{ $transcription->confidence > 70 ? 'success' : ($transcription->confidence > 50 ? 'warning' : 'danger') }}">{{ round($transcription->confidence) }}% confidence</span>
                @endif
              </small>
            </div>
            {{-- Search --}}
            <div class="card-body py-2 border-bottom">
              <div class="input-group input-group-sm">
                <input type="text" class="form-control" id="transcript-search-{{ $doId }}" placeholder="Search in transcript...">
                <button class="btn atom-btn-white" type="button" id="transcript-search-btn-{{ $doId }}"><i class="fas fa-search"></i></button>
              </div>
            </div>
            {{-- Content --}}
            <div class="card-body transcript-content" style="max-height:400px;overflow-y:auto;">
              <div class="transcript-full-text" style="white-space:pre-wrap;line-height:1.8;">{{ e($transcription->full_text ?? '') }}</div>
              <div class="transcript-segments" style="display:none;">
                @foreach($segments as $i => $seg)
                  <div class="transcript-segment" data-start="{{ $seg['start'] ?? 0 }}" data-end="{{ $seg['end'] ?? 0 }}" style="cursor:pointer;padding:4px 8px;border-radius:4px;margin:2px 0;">
                    <small class="text-muted me-2">[{{ gmdate('i:s', (int) ($seg['start'] ?? 0)) }}]</small>{{ e(trim($seg['text'] ?? '')) }}
                  </div>
                @endforeach
              </div>
            </div>
            {{-- View toggle --}}
            <div class="card-footer py-2">
              <div class="btn-group btn-group-sm">
                <button class="btn atom-btn-white active" id="btn-text-{{ $doId }}"><i class="fas fa-align-left"></i> Full Text</button>
                <button class="btn atom-btn-white" id="btn-segments-{{ $doId }}"><i class="fas fa-list"></i> Timed Segments</button>
              </div>
            </div>
            </div>{{-- /collapse --}}
          </div>
        @else
          {{-- Transcribe buttons --}}
          @auth
          <div class="card mb-3">
            <div class="card-body text-center py-4">
              <i class="fas fa-microphone fa-2x text-muted mb-3 d-block"></i>
              <p class="text-muted mb-3">This {{ $doMediaType }} has not been transcribed yet.</p>
              <div class="d-flex justify-content-center gap-2 flex-wrap">
                <button class="btn atom-btn-white" data-action="transcribe" data-do-id="{{ $doId }}" data-lang="en" data-csrf="{{ csrf_token() }}"><i class="fas fa-language me-1"></i>Transcribe (English)</button>
                <button class="btn atom-btn-white" data-action="transcribe" data-do-id="{{ $doId }}" data-lang="af" data-csrf="{{ csrf_token() }}">Afrikaans</button>
              </div>
            </div>
          </div>
          @endauth
        @endif

        {{-- Snippets --}}
        @if($snippets->isNotEmpty())
          <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
              <span><i class="fas fa-cut me-2"></i>Snippets</span>
              <span class="badge bg-secondary">{{ $snippets->count() }}</span>
            </div>
            <div class="list-group list-group-flush">
              @foreach($snippets as $snippet)
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <strong>{{ e($snippet->title ?? 'Untitled') }}</strong>
                    <small class="text-muted ms-2">[{{ gmdate('i:s', (int) $snippet->start_time) }} - {{ gmdate('i:s', (int) $snippet->end_time) }}]</small>
                    @if($snippet->notes)<br><small class="text-muted">{{ e($snippet->notes) }}</small>@endif
                  </div>
                  <button class="btn btn-sm atom-btn-white" onclick="var p=document.querySelector('audio,video');p&&(p.currentTime={{ $snippet->start_time }},p.play())" title="Play snippet">
                    <i class="fas fa-play"></i>
                  </button>
                </div>
              @endforeach
            </div>
          </div>
        @endif

        {{-- Create Snippet button --}}
        @auth
        <div class="mb-3">
          <button class="btn btn-sm atom-btn-white" id="create-snippet-btn" onclick="document.getElementById('snippet-form').style.display=document.getElementById('snippet-form').style.display==='none'?'block':'none';">
            <i class="fas fa-cut me-1"></i>Create Snippet
          </button>
          <div id="snippet-form" style="display:none;" class="card mt-2">
            <div class="card-body">
              <div class="row g-2">
                <div class="col-md-4"><input type="text" class="form-control form-control-sm" id="snippet-title" placeholder="Snippet title"></div>
                <div class="col-md-2"><input type="number" class="form-control form-control-sm" id="snippet-start" placeholder="Start (sec)" step="0.1"></div>
                <div class="col-md-2"><input type="number" class="form-control form-control-sm" id="snippet-end" placeholder="End (sec)" step="0.1"></div>
                <div class="col-md-4"><input type="text" class="form-control form-control-sm" id="snippet-notes" placeholder="Notes (optional)"></div>
              </div>
              <div class="mt-2 d-flex gap-2">
                <button class="btn btn-sm atom-btn-white" onclick="var p=document.querySelector('audio,video');p&&(document.getElementById('snippet-start').value=p.currentTime.toFixed(1))">
                  <i class="fas fa-sign-in-alt"></i> Mark IN
                </button>
                <button class="btn btn-sm atom-btn-white" onclick="var p=document.querySelector('audio,video');p&&(document.getElementById('snippet-end').value=p.currentTime.toFixed(1))">
                  <i class="fas fa-sign-out-alt"></i> Mark OUT
                </button>
                <button class="btn atom-btn-outline-light btn-sm" data-action="save-snippet" data-do-id="{{ $doId }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-save me-1"></i>Save Snippet</button>
              </div>
            </div>
          </div>
        </div>
        @endauth
      @endif

      {{-- TIFF to PDF Merge button (only for items with TIFF digital objects) --}}
      @auth
        @php
          $hasTiffDigitalObject = isset($digitalObjects) && ($digitalObjects['master'] ?? null)
            && in_array(strtolower($digitalObjects['master']->mime_type ?? ''), ['image/tiff', 'image/tif', 'image/x-tiff']);
        @endphp
        @if($hasTiffDigitalObject)
          <div class="text-center my-2">
            <a href="{{ route('tiffpdfmerge.create') }}?io_id={{ $io->id }}" class="btn atom-btn-white btn-sm">
              <i class="fas fa-file-pdf me-1"></i> Merge to PDF
            </a>
          </div>
        @endif
      @endauth
    @endif
  @endif

@endsection

{{-- ============================================================ --}}
{{-- MAIN CONTENT: ISAD(G) sections                              --}}
{{-- ============================================================ --}}
@section('content')

  @include('ahg-ric::_view-switch')

  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-view-io', ['io' => $io])
  @else

  {{-- TTS (Text-to-Speech) controls — only for text-heavy archival descriptions, not museum/3D/media objects --}}
  @if((!empty($io->scope_and_content) || !empty($io->archival_history) || !empty($io->arrangement))
      && (!isset($digitalObjects) || !($digitalObjects['master'] ?? null)
          || !in_array(\AhgCore\Services\DigitalObjectService::getMediaType($digitalObjects['master']), ['video', 'audio', 'other'])))
    @include('ahg-io-manage::_tts-controls', ['target' => '[data-tts-content]', 'style' => 'full', 'position' => 'inline'])
  @endif

  <div data-tts-content>

  {{-- User action buttons (matching AtoM: TTS, Favorites, Cart, Loan) --}}
  <div class="d-flex flex-wrap gap-1 mb-3 align-items-center">
    @auth
      @php
        $userId = auth()->id();
        $isFavorited = \Illuminate\Support\Facades\DB::table('favorites')
          ->where('user_id', $userId)->where('archival_description_id', $io->id)->exists();
        $inCart = \Illuminate\Support\Facades\DB::table('cart')
          ->where('user_id', $userId)->where('archival_description_id', $io->id)
          ->whereNull('completed_at')->exists();
        $hasDigitalObject = isset($digitalObjects) && ($digitalObjects['master'] ?? null);
      @endphp

      {{-- Favorites toggle --}}
      @if($isFavorited)
        <form method="POST" action="{{ route('favorites.remove', \Illuminate\Support\Facades\DB::table('favorites')->where('user_id', $userId)->where('archival_description_id', $io->id)->value('id')) }}" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="Remove from Favorites" data-bs-toggle="tooltip">
            <i class="fas fa-heart-broken"></i>
          </button>
        </form>
      @else
        <a href="{{ route('favorites.add', $io->slug) }}"
           class="btn btn-sm atom-btn-outline-danger" title="Add to Favorites" data-bs-toggle="tooltip">
          <i class="fas fa-heart"></i>
        </a>
      @endif

      {{-- Cart --}}
      @if($hasDigitalObject)
        @if($inCart)
          <a href="{{ route('cart.browse') }}" class="btn btn-sm atom-btn-outline-success" title="Go to Cart" data-bs-toggle="tooltip">
            <i class="fas fa-shopping-cart"></i>
          </a>
        @else
          <a href="{{ route('cart.add', $io->slug) }}" class="btn btn-sm atom-btn-outline-success" title="Add to Cart" data-bs-toggle="tooltip">
            <i class="fas fa-cart-plus"></i>
          </a>
        @endif
      @endif

      {{-- Feedback --}}
      <a href="{{ url('/feedback/submit/' . $io->slug) }}" class="btn btn-sm atom-btn-white" title="Item Feedback" data-bs-toggle="tooltip">
        <i class="fas fa-comment"></i>
      </a>

      {{-- Request to Publish --}}
      @if($hasDigitalObject)
        <a href="{{ route('cart.add', $io->slug) }}" class="btn btn-sm atom-btn-white" title="Request to Publish" data-bs-toggle="tooltip">
          <i class="fas fa-paper-plane"></i>
        </a>
      @endif

      {{-- Loan: New + Manage --}}
      <a href="{{ route('loan.create', ['object_id' => $io->id]) }}" class="btn btn-sm atom-btn-white" title="New Loan" data-bs-toggle="tooltip">
        <i class="fas fa-hand-holding"></i>
      </a>
      <a href="{{ route('loan.index', ['object_id' => $io->id]) }}" class="btn btn-sm atom-btn-white" title="Manage Loans" data-bs-toggle="tooltip">
        <i class="fas fa-exchange-alt"></i>
      </a>
    @endauth
  </div>

  {{-- ===== 1. Identity area ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_identity_area'))
  <section id="identityArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#identity-collapse">
        Identity area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#identity-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Identity area">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
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

      @if($io->alternate_title ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Alternate title</h3>
          <div class="col-9 p-2">{{ $io->alternate_title }}</div>
        </div>
      @endif

      @if($io->edition ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Edition</h3>
          <div class="col-9 p-2">{{ $io->edition }}</div>
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
  @endif {{-- end isad_identity_area visibility --}}

  {{-- ===== 2. Context area ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_context_area'))
  <section id="contextArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#context-collapse">
        Context area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#context-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Context area">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
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

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_archival_history'))
      @if($io->archival_history)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Archival history</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->archival_history)) !!}</div>
        </div>
      @endif
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_immediate_source'))
      @if($io->acquisition)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Immediate source of acquisition or transfer</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->acquisition)) !!}</div>
        </div>
      @endif
      @endif

    </div>
  </section>
  @endif {{-- end isad_context_area visibility --}}

  {{-- ===== 3. Content and structure area ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_content_and_structure_area'))
  <section id="contentAndStructureArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#content-collapse">
        Content and structure area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#content-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Content and structure area">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="content-collapse">

      @if($io->scope_and_content)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Scope and content</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->scope_and_content)) !!}</div>
        </div>
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_appraisal_destruction'))
      @if($io->appraisal)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Appraisal, destruction and scheduling</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->appraisal)) !!}</div>
        </div>
      @endif
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
  @endif {{-- end isad_content_and_structure_area visibility --}}

  {{-- ===== 4. Conditions of access and use area ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_conditions_of_access_use_area'))
  <section id="conditionsOfAccessAndUseArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#conditions-collapse">
        Conditions of access and use area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#conditions-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Conditions of access and use area">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
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

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_physical_condition'))
      @if($io->physical_characteristics)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Physical characteristics and technical requirements</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->physical_characteristics)) !!}</div>
        </div>
      @endif
      @endif

      @if($io->finding_aids)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Finding aids</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->finding_aids)) !!}</div>
        </div>
      @endif

      {{-- Finding aid link (generated or uploaded PDF) --}}
      @if(isset($findingAid) && $findingAid)
        <div class="field text-break row g-0">
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
  @endif {{-- end isad_conditions_of_access_use_area visibility --}}

  {{-- ===== 5. Allied materials area ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_allied_materials_area'))
  <section id="alliedMaterialsArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#allied-collapse">
        Allied materials area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#allied-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Allied materials area">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
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

      {{-- Related material descriptions (relation type_id = 176) --}}
      @if(isset($relatedMaterialDescriptions) && $relatedMaterialDescriptions->isNotEmpty())
        <div class="relatedMaterialDescriptions">
          <div class="field text-break row g-0">
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

      {{-- Publication notes (type_id = 120) --}}
      @if(isset($notes) && $notes->isNotEmpty())
        @foreach($notes->where('type_id', 120) as $note)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Publication note</h3>
            <div class="col-9 p-2">{!! nl2br(e($note->content)) !!}</div>
          </div>
        @endforeach
      @endif

    </div>
  </section>
  @endif {{-- end isad_allied_materials_area visibility --}}

  {{-- ===== 6. Notes area ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_notes_area'))
  <section id="notesArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#notes-collapse">
        Notes area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#notes-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Notes area">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="notes-collapse">

      {{-- General notes (type_id = 125) --}}
      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_notes'))
      @if(isset($notes) && $notes->isNotEmpty())
        @foreach($notes->where('type_id', 125) as $note)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Note</h3>
            <div class="col-9 p-2">{!! nl2br(e($note->content)) !!}</div>
          </div>
        @endforeach
      @endif
      @endif {{-- end isad_notes visibility --}}

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
  @endif {{-- end isad_notes_area visibility --}}

  {{-- ===== 7. Access points ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_access_points_area'))
  <section id="accessPointsArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#access-collapse">
        Access points
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#access-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Access points">
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
                <li>
                  @if(isset($subject->slug))
                    <a href="{{ route('informationobject.browse', ['subject' => $subject->name]) }}">{{ $subject->name }}</a>
                  @else
                    {{ $subject->name }}
                  @endif
                </li>
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
                <li>
                  @if(isset($place->slug))
                    <a href="{{ route('informationobject.browse', ['place' => $place->name]) }}">{{ $place->name }}</a>
                  @else
                    {{ $place->name }}
                  @endif
                </li>
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
                <li>
                  @if(isset($nap->slug))
                    <a href="{{ route('actor.show', $nap->slug) }}">{{ $nap->name }}</a>
                  @else
                    {{ $nap->name }}
                  @endif
                  @if(isset($nap->event_type))
                    <span class="text-muted">({{ $nap->event_type }})</span>
                  @endif
                </li>
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
                <li>
                  @if(isset($genre->slug))
                    <a href="{{ route('informationobject.browse', ['genre' => $genre->name]) }}">{{ $genre->name }}</a>
                  @else
                    {{ $genre->name }}
                  @endif
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @endif

    </div>
  </section>
  @endif {{-- end isad_access_points_area visibility --}}

  {{-- ===== 8. Description control area ===== --}}
  @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_description_control_area'))
  <section id="descriptionControlArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#description-collapse">
        Description control area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#description-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Description control area">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="description-collapse">

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_description_identifier'))
      @if($io->description_identifier ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Description identifier</h3>
          <div class="col-9 p-2">{{ $io->description_identifier }}</div>
        </div>
      @endif
      @endif {{-- end isad_control_description_identifier --}}

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_institution_identifier'))
      @if($io->institution_responsible_identifier ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Institution identifier</h3>
          <div class="col-9 p-2">{{ $io->institution_responsible_identifier }}</div>
        </div>
      @endif
      @endif {{-- end isad_control_institution_identifier --}}

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_rules_conventions'))
      @if($io->rules ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rules and/or conventions used</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->rules)) !!}</div>
        </div>
      @endif
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_status'))
      @if(isset($descriptionStatusName) && $descriptionStatusName)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Status</h3>
          <div class="col-9 p-2">{{ $descriptionStatusName }}</div>
        </div>
      @endif
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_level_of_detail'))
      @if(isset($descriptionDetailName) && $descriptionDetailName)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Level of detail</h3>
          <div class="col-9 p-2">{{ $descriptionDetailName }}</div>
        </div>
      @endif
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_dates'))
      @if($io->revision_history ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Dates of creation revision deletion</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->revision_history)) !!}</div>
        </div>
      @endif
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_languages'))
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
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_scripts'))
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
      @endif

      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_sources'))
      @if($io->sources ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Sources</h3>
          <div class="col-9 p-2">{!! nl2br(e($io->sources)) !!}</div>
        </div>
      @endif
      @endif

      {{-- Archivist's note (type_id = 124) --}}
      @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('isad_control_archivists_notes'))
      @if(isset($notes) && $notes->isNotEmpty())
        @foreach($notes->where('type_id', 124) as $note)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Archivist's note</h3>
            <div class="col-9 p-2">{!! nl2br(e($note->content)) !!}</div>
          </div>
        @endforeach
      @endif
      @endif

      @if($io->source_standard ?? null)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Source standard</h3>
          <div class="col-9 p-2">{{ $io->source_standard }}</div>
        </div>
      @endif

    </div>
  </section>
  @endif {{-- end isad_description_control_area visibility --}}

  {{-- ===== 8b. Administration area (authenticated only, matching AtoM _adminInfo.php) ===== --}}
  @auth
    <section id="administrationArea" class="border-bottom">
      <div class="accordion" id="adminAreaAccordion">
        <div class="accordion-item border-0">
          <h2 class="accordion-header position-relative" id="admin-heading">
            <button
              class="accordion-button collapsed h6 mb-0 py-2 px-3"
              type="button"
              data-bs-toggle="collapse"
              data-bs-target="#admin-collapse"
              aria-expanded="false"
              aria-controls="admin-collapse"
              style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
              Administration area
            </button>
            <a href="{{ route('informationobject.edit', $io->slug) }}#admin-collapse" class="position-absolute text-white opacity-75" style="font-size:.75rem;right:2.5rem;top:50%;transform:translateY(-50%);z-index:2;" title="Edit Administration area">
              <i class="fas fa-pencil-alt"></i>
            </a>
          </h2>
          <div id="admin-collapse" class="accordion-collapse collapse" aria-labelledby="admin-heading">
            <div class="accordion-body p-0">
              <div class="row g-0">

                <div class="col-md-6">
                  {{-- Source language --}}
                  @if(isset($sourceLanguageName) && $sourceLanguageName)
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-2">Source language</h3>
                      <div class="col-8 p-2">
                        @if($io->source_culture && $io->source_culture !== app()->getLocale())
                          <div class="default-translation">
                            <a href="{{ route('informationobject.show', $io->slug) }}?sf_culture={{ $io->source_culture }}">
                              {{ $sourceLanguageName }}
                            </a>
                          </div>
                        @else
                          {{ $sourceLanguageName }}
                        @endif
                      </div>
                    </div>
                  @endif

                  {{-- Last updated --}}
                  @if($io->updated_at)
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-2">Last updated</h3>
                      <div class="col-8 p-2">{{ \Carbon\Carbon::parse($io->updated_at)->format('j F Y') }}</div>
                    </div>
                  @endif

                  {{-- Source name (from keymap) --}}
                  @if(isset($keymapEntries) && $keymapEntries->isNotEmpty())
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-2">Source name</h3>
                      <div class="col-8 p-2">
                        @foreach($keymapEntries as $keymap)
                          <p class="mb-1">{{ $keymap->source_name }}</p>
                        @endforeach
                      </div>
                    </div>
                  @endif

                  {{-- Collection type --}}
                  @if(isset($collectionTypeName) && $collectionTypeName)
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-2">Collection type</h3>
                      <div class="col-8 p-2">{{ $collectionTypeName }}</div>
                    </div>
                  @endif
                </div>

                <div class="col-md-6">
                  {{-- Display standard (read-only on show, editable via edit page) --}}
                  @if(isset($displayStandardName) && $displayStandardName)
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-2">Display standard</h3>
                      <div class="col-8 p-2">{{ $displayStandardName }}</div>
                    </div>
                  @endif
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  @endauth

  {{-- ===== 9. Rights area (authenticated only) ===== --}}
  @auth
    <section id="rightsArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#rights-collapse">
          Rights area
        </a>
        <a href="{{ route('informationobject.edit', $io->slug) }}#rights-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Rights area">
          <i class="fas fa-pencil-alt"></i>
        </a>
      </h2>
      <div id="rights-collapse">
        {{-- Standard rights (from rights table via relation) --}}
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

        {{-- Extended rights (from extended_rights + extended_rights_i18n + extended_rights_tk_label) --}}
        @if(isset($extendedRights) && $extendedRights->isNotEmpty())
          @foreach($extendedRights as $er)
            @if($er->rights_statement_name || $er->rights_statement_code)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rights statement</h3>
                <div class="col-9 p-2">
                  @if($er->rights_statement_uri)
                    <a href="{{ $er->rights_statement_uri }}" target="_blank">{{ $er->rights_statement_name ?? $er->rights_statement_code }}</a>
                  @else
                    {{ $er->rights_statement_name ?? $er->rights_statement_code }}
                  @endif
                  @if($er->rights_statement_definition)
                    <br><small class="text-muted">{{ $er->rights_statement_definition }}</small>
                  @endif
                </div>
              </div>
            @endif
            @if($er->cc_license_code)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Creative Commons</h3>
                <div class="col-9 p-2">
                  @if($er->cc_license_uri)
                    <a href="{{ $er->cc_license_uri }}" target="_blank">CC {{ strtoupper($er->cc_license_code) }}</a>
                  @else
                    CC {{ strtoupper($er->cc_license_code) }}
                  @endif
                </div>
              </div>
            @endif
            @if($er->rights_holder)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rights holder</h3>
                <div class="col-9 p-2">
                  @if($er->rights_holder_uri)
                    <a href="{{ $er->rights_holder_uri }}" target="_blank">{{ $er->rights_holder }}</a>
                  @else
                    {{ $er->rights_holder }}
                  @endif
                </div>
              </div>
            @endif
            @if($er->copyright_notice)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Copyright notice</h3>
                <div class="col-9 p-2">{!! nl2br(e($er->copyright_notice)) !!}</div>
              </div>
            @endif
            @if($er->usage_conditions)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Usage conditions</h3>
                <div class="col-9 p-2">{!! nl2br(e($er->usage_conditions)) !!}</div>
              </div>
            @endif
            @if($er->rights_note)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rights note</h3>
                <div class="col-9 p-2">{!! nl2br(e($er->rights_note)) !!}</div>
              </div>
            @endif
            @if($er->rights_date)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rights date</h3>
                <div class="col-9 p-2">{{ $er->rights_date }}@if($er->expiry_date) &ndash; {{ $er->expiry_date }}@endif</div>
              </div>
            @endif
            @if(isset($extendedRightsTkLabels[$er->id]) && $extendedRightsTkLabels[$er->id]->isNotEmpty())
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">TK Labels</h3>
                <div class="col-9 p-2">
                  @foreach($extendedRightsTkLabels[$er->id] as $tkl)
                    <span class="badge bg-secondary me-1">{{ $tkl->code ?? $tkl->id }}</span>
                  @endforeach
                </div>
              </div>
            @endif
          @endforeach
        @endif
      </div>
    </section>
  @endauth

  {{-- ===== 9b. Extended Rights visual badges ===== --}}
  @include('ahg-io-manage::partials._rights-badges')

  {{-- ===== 9c. Provenance & Chain of Custody (from provenance_entry table) ===== --}}
  @if(isset($provenanceEntries) && $provenanceEntries->isNotEmpty())
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        Provenance &amp; Chain of Custody
      </h2>
      <div class="provenance-chain px-3 py-2">
        @foreach($provenanceEntries as $i => $entry)
          <div class="d-flex mb-2 align-items-start">
            <div class="me-3">
              <span class="badge rounded-pill bg-{{ $i === 0 ? 'primary' : 'secondary' }}">{{ $provenanceEntries->count() - $i }}</span>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between">
                <div>
                  <strong>{{ $entry->owner_name }}</strong>
                  @if($entry->owner_type && $entry->owner_type !== 'unknown')
                    <span class="badge bg-info ms-1">{{ ucfirst(str_replace('_', ' ', $entry->owner_type)) }}</span>
                  @endif
                  @if($entry->transfer_type && $entry->transfer_type !== 'unknown')
                    <span class="badge bg-secondary ms-1">{{ ucfirst(str_replace('_', ' ', $entry->transfer_type)) }}</span>
                  @endif
                </div>
                <small class="text-muted">
                  @if($entry->start_date && $entry->end_date)
                    {{ $entry->start_date }} &ndash; {{ $entry->end_date }}
                  @elseif($entry->start_date)
                    {{ $entry->start_date }} &ndash; present
                  @elseif($entry->end_date)
                    until {{ $entry->end_date }}
                  @endif
                </small>
              </div>
              @if($entry->owner_location)
                <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i>{{ $entry->owner_location }}</small>
              @endif
              @if($entry->notes)
                <p class="small text-muted mb-0 mt-1">{{ $entry->notes }}</p>
              @endif
            </div>
          </div>
        @endforeach
        @auth
          <div class="mt-2">
            <a href="{{ route('io.provenance', $io->slug) }}" class="btn btn-sm atom-btn-white">
              <i class="fas fa-edit me-1"></i>Edit provenance chain
            </a>
          </div>
        @endauth
      </div>
    </section>
  @endif

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
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#digital-object-collapse">
          Digital object metadata
        </a>
      </h2>
      <div id="digital-object-collapse">

          {{-- Master file --}}
          <h4 class="h6 py-2 px-3 mb-0 border-bottom" style="background:#f5f5f5;">Master file</h4>
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

          {{-- Reference copy --}}
          @if($doReference)
            <h4 class="h6 py-2 px-3 mb-0 border-bottom" style="background:#f5f5f5;">Reference copy</h4>
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
          @endif

          {{-- Thumbnail copy --}}
          @if($doThumbnail)
            <h4 class="h6 py-2 px-3 mb-0 border-bottom" style="background:#f5f5f5;">Thumbnail copy</h4>
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
          @endif

      </div>
    </section>
  @endif

  {{-- ===== 10b. Digital object rights (matching AtoM digitalobject/_rights.php) ===== --}}
  @auth
    @if(isset($digitalObjectRights) && !empty($digitalObjectRights))
      @foreach($digitalObjectRights as $usageKey => $doRightsData)
        <section class="border-bottom digitalObjectRights">
          <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <a class="text-decoration-none text-white" href="#do-rights-{{ $usageKey }}-collapse">
              Digital object ({{ $doRightsData['usageName'] }}) rights area
            </a>
            @if(isset($digitalObjects) && $digitalObjects['master'])
              <a href="{{ route('io.digitalobject.show', $digitalObjects['master']->id) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit digital object">
                <i class="fas fa-pencil-alt"></i>
              </a>
            @endif
          </h2>
          <div id="do-rights-{{ $usageKey }}-collapse">
            @foreach($doRightsData['rights'] as $doRight)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ $doRight->basis ?? 'Right' }}</h3>
                <div class="col-9 p-2">
                  @if(isset($doRight->act)){{ $doRight->act }}@endif
                  @if(isset($doRight->start_date) || isset($doRight->end_date))
                    <br><small class="text-muted">{{ $doRight->start_date ?? '?' }} - {{ $doRight->end_date ?? '?' }}</small>
                  @endif
                  @if(isset($doRight->rights_note) && $doRight->rights_note)
                    <br>{!! nl2br(e($doRight->rights_note)) !!}
                  @endif
                </div>
              </div>
            @endforeach
          </div>
        </section>
      @endforeach
    @endif
  @endauth

  {{-- ===== 11. Accession area ===== --}}
  <section id="accessionArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#accession-collapse">
        Accession area
      </a>
      @auth
        <a href="{{ route('informationobject.edit', $io->slug) }}#accession-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Accession area">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
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

  {{-- ===== Museum / CCO metadata (shown only if this IO has a museum_metadata row) ===== --}}
  @if(!empty($museumMetadata))
    <section id="museum-metadata" class="mb-4">
      <h2 class="fs-5 fw-bold border-bottom pb-2 mb-3">
        <i class="fas fa-landmark me-1"></i> CCO / Museum metadata
      </h2>

      {{-- Object / Work section --}}
      @if(($museumMetadata['work_type'] ?? null) || ($museumMetadata['object_type'] ?? null) || ($museumMetadata['classification'] ?? null) || ($museumMetadata['object_class'] ?? null) || ($museumMetadata['object_category'] ?? null) || ($museumMetadata['object_sub_category'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Object / Work</div>
          <div class="card-body">
            @foreach(['work_type' => 'Work type', 'object_type' => 'Object type', 'classification' => 'Classification', 'object_class' => 'Object class', 'object_category' => 'Object category', 'object_sub_category' => 'Object sub-category', 'record_type' => 'Record type', 'record_level' => 'Record level'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Creator section --}}
      @if(($museumMetadata['creator_identity'] ?? null) || ($museumMetadata['creator_role'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Creator</div>
          <div class="card-body">
            @foreach(['creator_identity' => 'Creator', 'creator_role' => 'Role', 'creator_extent' => 'Extent', 'creator_qualifier' => 'Qualifier', 'creator_attribution' => 'Attribution'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Dates section --}}
      @if(($museumMetadata['creation_date_display'] ?? null) || ($museumMetadata['creation_date_earliest'] ?? null) || ($museumMetadata['creation_date_latest'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Dates</div>
          <div class="card-body">
            @foreach(['creation_date_display' => 'Date display', 'creation_date_earliest' => 'Earliest date', 'creation_date_latest' => 'Latest date', 'creation_date_qualifier' => 'Date qualifier'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Materials / Technique section --}}
      @if(($museumMetadata['materials'] ?? null) || ($museumMetadata['techniques'] ?? null) || ($museumMetadata['technique_cco'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Materials &amp; technique</div>
          <div class="card-body">
            @foreach(['materials' => 'Materials', 'techniques' => 'Techniques', 'technique_cco' => 'Technique (CCO)', 'technique_qualifier' => 'Technique qualifier', 'facture_description' => 'Facture', 'color' => 'Color', 'physical_appearance' => 'Physical appearance'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Measurements section --}}
      @if(($museumMetadata['measurements'] ?? null) || ($museumMetadata['dimensions'] ?? null) || ($museumMetadata['orientation'] ?? null) || ($museumMetadata['shape'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Measurements</div>
          <div class="card-body">
            @foreach(['measurements' => 'Measurements', 'dimensions' => 'Dimensions', 'orientation' => 'Orientation', 'shape' => 'Shape'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Style / Period / Cultural context --}}
      @if(($museumMetadata['style_period'] ?? null) || ($museumMetadata['style'] ?? null) || ($museumMetadata['period'] ?? null) || ($museumMetadata['cultural_context'] ?? null) || ($museumMetadata['cultural_group'] ?? null) || ($museumMetadata['movement'] ?? null) || ($museumMetadata['school'] ?? null) || ($museumMetadata['dynasty'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Style / Period / Context</div>
          <div class="card-body">
            @foreach(['style_period' => 'Style/Period', 'style' => 'Style', 'period' => 'Period', 'cultural_context' => 'Cultural context', 'cultural_group' => 'Cultural group', 'movement' => 'Movement', 'school' => 'School', 'dynasty' => 'Dynasty'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Subject section --}}
      @if(($museumMetadata['subject_indexing_type'] ?? null) || ($museumMetadata['subject_display'] ?? null) || ($museumMetadata['subject_extent'] ?? null) || ($museumMetadata['historical_context'] ?? null) || ($museumMetadata['architectural_context'] ?? null) || ($museumMetadata['archaeological_context'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Subject</div>
          <div class="card-body">
            @foreach(['subject_indexing_type' => 'Indexing type', 'subject_display' => 'Subject display', 'subject_extent' => 'Subject extent', 'historical_context' => 'Historical context', 'architectural_context' => 'Architectural context', 'archaeological_context' => 'Archaeological context'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Condition / Treatment section --}}
      @if(($museumMetadata['condition_term'] ?? null) || ($museumMetadata['condition_notes'] ?? null) || ($museumMetadata['condition_description'] ?? null) || ($museumMetadata['treatment_type'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Condition &amp; treatment</div>
          <div class="card-body">
            @foreach(['condition_term' => 'Condition', 'condition_date' => 'Condition date', 'condition_description' => 'Condition description', 'condition_agent' => 'Condition agent', 'condition_notes' => 'Condition notes', 'treatment_type' => 'Treatment type', 'treatment_date' => 'Treatment date', 'treatment_agent' => 'Treatment agent', 'treatment_description' => 'Treatment description'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Inscriptions / Marks section --}}
      @if(($museumMetadata['inscription'] ?? null) || ($museumMetadata['inscriptions'] ?? null) || ($museumMetadata['inscription_transcription'] ?? null) || ($museumMetadata['mark_type'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Inscriptions &amp; marks</div>
          <div class="card-body">
            @foreach(['inscription' => 'Inscription', 'inscriptions' => 'Inscriptions', 'inscription_transcription' => 'Transcription', 'inscription_type' => 'Inscription type', 'inscription_location' => 'Inscription location', 'inscription_language' => 'Inscription language', 'inscription_translation' => 'Translation', 'mark_type' => 'Mark type', 'mark_description' => 'Mark description', 'mark_location' => 'Mark location'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Edition section --}}
      @if(($museumMetadata['edition_description'] ?? null) || ($museumMetadata['edition_number'] ?? null) || ($museumMetadata['edition_size'] ?? null) || ($museumMetadata['state_description'] ?? null) || ($museumMetadata['state_identification'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Edition / State</div>
          <div class="card-body">
            @foreach(['edition_description' => 'Edition description', 'edition_number' => 'Edition number', 'edition_size' => 'Edition size', 'state_description' => 'State description', 'state_identification' => 'State identification'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Location section --}}
      @if(($museumMetadata['current_location'] ?? null) || ($museumMetadata['current_location_repository'] ?? null) || ($museumMetadata['creation_place'] ?? null) || ($museumMetadata['discovery_place'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Location / Geography</div>
          <div class="card-body">
            @foreach(['current_location' => 'Current location', 'current_location_repository' => 'Repository', 'current_location_geography' => 'Geography', 'current_location_coordinates' => 'Coordinates', 'current_location_ref_number' => 'Reference number', 'creation_place' => 'Creation place', 'creation_place_type' => 'Creation place type', 'discovery_place' => 'Discovery place', 'discovery_place_type' => 'Discovery place type'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Related works section --}}
      @if(($museumMetadata['related_work_type'] ?? null) || ($museumMetadata['related_work_label'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Related works</div>
          <div class="card-body">
            @foreach(['related_work_type' => 'Relationship type', 'related_work_relationship' => 'Relationship', 'related_work_label' => 'Label', 'related_work_id' => 'Identifier'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{{ $museumMetadata[$field] }}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Provenance / Rights section --}}
      @if(($museumMetadata['provenance'] ?? null) || ($museumMetadata['provenance_text'] ?? null) || ($museumMetadata['ownership_history'] ?? null) || ($museumMetadata['legal_status'] ?? null) || ($museumMetadata['rights_type'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Provenance &amp; rights</div>
          <div class="card-body">
            @foreach(['provenance' => 'Provenance', 'provenance_text' => 'Provenance text', 'ownership_history' => 'Ownership history', 'legal_status' => 'Legal status', 'rights_type' => 'Rights type', 'rights_holder' => 'Rights holder', 'rights_date' => 'Rights date', 'rights_remarks' => 'Rights remarks'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

      {{-- Cataloguing section --}}
      @if(($museumMetadata['cataloger_name'] ?? null) || ($museumMetadata['cataloging_date'] ?? null) || ($museumMetadata['cataloging_institution'] ?? null))
        <div class="card mb-3">
          <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">Cataloguing</div>
          <div class="card-body">
            @foreach(['cataloger_name' => 'Cataloger', 'cataloging_date' => 'Date', 'cataloging_institution' => 'Institution', 'cataloging_remarks' => 'Remarks'] as $field => $label)
              @if($museumMetadata[$field] ?? null)
                <div class="row mb-1"><div class="col-sm-4 text-muted small">{{ $label }}</div><div class="col-sm-8">{!! nl2br(e($museumMetadata[$field])) !!}</div></div>
              @endif
            @endforeach
          </div>
        </div>
      @endif

    </section>
  @endif

  </div>{{-- /data-tts-content --}}

  @endif {{-- end heratio/ric view mode --}}

  {{-- RiC Explorer Panel + RiC Context — only visible in RiC view mode --}}
  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-panel', ['resourceId' => $io->id])

    @if(class_exists(\AhgRic\Controllers\RicEntityController::class))
      @include('ahg-ric::_ric-entities-panel', ['record' => $io])
    @endif
  @endif

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
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-cogs me-1"></i> Explore
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('informationobject.reports', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-print me-1"></i> Reports
        </a>
        @if(isset($hasChildren) && $hasChildren)
          <a href="{{ route('informationobject.inventory', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-list-alt me-1"></i> Inventory
          </a>
        @endif
        <a href="{{ route('informationobject.browse', ['collection' => $collectionRootId, 'topLod' => 0]) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-list me-1"></i> Browse as list
        </a>
        @if(isset($digitalObjects) && $digitalObjects['master'])
          <a href="{{ route('informationobject.browse', ['collection' => $collectionRootId, 'topLod' => 0, 'view' => 'card', 'onlyMedia' => 1]) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-image me-1"></i> Browse digital objects
          </a>
        @endif
      </div>
    </div>

    {{-- Import --}}
    @auth
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
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

    {{-- Active Loans --}}
    @if(\Illuminate\Support\Facades\Schema::hasTable('ahg_loan'))
    @php
      $activeLoans = \Illuminate\Support\Facades\DB::table('ahg_loan')
          ->join('ahg_loan_object', 'ahg_loan.id', '=', 'ahg_loan_object.loan_id')
          ->where('ahg_loan_object.information_object_id', $io->id)
          ->whereNotIn('ahg_loan.status', ['returned', 'closed', 'cancelled'])
          ->select('ahg_loan.id', 'ahg_loan.loan_number', 'ahg_loan.loan_type', 'ahg_loan.status', 'ahg_loan.partner_institution', 'ahg_loan.end_date')
          ->get();
    @endphp
    @if($activeLoans->isNotEmpty())
      <div class="card mb-3 border-warning">
        <div class="card-header bg-warning text-dark fw-bold">
          <i class="fas fa-exchange-alt me-1"></i> Active Loans ({{ $activeLoans->count() }})
        </div>
        <div class="list-group list-group-flush">
          @foreach($activeLoans as $al)
            @php $isOverdue = $al->end_date && $al->end_date < now()->toDateString(); @endphp
            <a href="{{ route('loan.show', $al->id) }}" class="list-group-item list-group-item-action {{ $isOverdue ? 'list-group-item-danger' : '' }}">
              <div class="d-flex justify-content-between">
                <strong>{{ $al->loan_number }}</strong>
                <span class="badge bg-{{ $al->loan_type === 'out' ? 'info' : 'warning' }}">{{ $al->loan_type === 'out' ? 'Out' : 'In' }}</span>
              </div>
              <small>{{ $al->partner_institution }}</small>
              @if($isOverdue)<span class="badge bg-danger ms-1"><i class="fas fa-exclamation-triangle"></i> Overdue</span>@endif
            </a>
          @endforeach
        </div>
      </div>
    @endif
    @endif

    {{-- Export --}}
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-file-export me-1"></i> Export
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('informationobject.export.dc', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> Dublin Core 1.1 XML
        </a>
        <a href="{{ route('informationobject.export.ead', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> EAD 2002 XML
        </a>
        <a href="{{ route('informationobject.export.ead3', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> EAD3 1.1 XML
        </a>
        <a href="{{ route('informationobject.export.ead4', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> EAD 4 XML
        </a>
        <a href="{{ route('informationobject.export.mods', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> MODS 3.5 XML
        </a>
        <a href="{{ route('informationobject.export.rico', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-code me-1"></i> RiC-O JSON-LD
        </a>
        @auth
          <a href="{{ route('informationobject.export.csv', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-file-csv me-1"></i> Export CSV
          </a>
        @endauth
      </div>
    </div>

    {{-- Finding aid --}}
    @auth
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-book me-1"></i> Finding aid
        </div>
        <div class="list-group list-group-flush">
          <a href="{{ route('informationobject.findingaid.generate', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-file-alt me-1"></i> Generate
          </a>
          <a href="{{ route('informationobject.findingaid.upload.form', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-upload me-1"></i> Upload
          </a>
          @if(isset($findingAid) && $findingAid)
            <a href="{{ route('informationobject.findingaid.download', $io->slug) }}" class="list-group-item list-group-item-action small">
              <i class="fas fa-download me-1"></i> Download
            </a>
            <form action="{{ route('informationobject.findingaid.delete', $io->slug) }}" method="POST" class="d-inline">
              @csrf
              <button type="submit" class="list-group-item list-group-item-action small text-danger border-0 text-start w-100" onclick="return confirm('{{ __('Are you sure you want to delete this finding aid?') }}')">
                <i class="fas fa-trash me-1"></i> Delete
              </button>
            </form>
          @endif
        </div>
      </div>
    @endauth

    {{-- Tasks --}}
    @auth
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-tasks me-1"></i> Tasks
        </div>
        <div class="list-group list-group-flush">
          <form action="{{ route('informationobject.calculateDates', $io->slug) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="list-group-item list-group-item-action small border-0 text-start w-100" title="Click 'Calculate dates' to recalculate the start and end dates of a parent-level description. A job runs in the background, accounting for the earliest and most recent dates across all the child descriptions. The results display in the Start and End fields of the edit page.">
              <i class="fas fa-calendar me-1"></i> Calculate dates
            </button>
          </form>
          <span class="list-group-item small text-muted">
            <i class="fas fa-clock me-1"></i> Last run: {{ $io->updated_at ? \Carbon\Carbon::parse($io->updated_at)->diffForHumans() : 'Never' }}
          </span>
        </div>
      </div>
    @endauth

    {{-- Related subjects --}}
    @if(isset($subjects) && $subjects->isNotEmpty())
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
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
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
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
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
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
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-map-marker-alt me-1"></i> Related places
        </div>
        <ul class="list-group list-group-flush">
          @foreach($places as $place)
            <li class="list-group-item small">{{ $place->name }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- Physical storage (visibility check matching AtoM) --}}
    @if(\AhgCore\Services\SettingHelper::checkFieldVisibility('physical_storage'))
    @if(isset($physicalObjects) && (is_countable($physicalObjects) ? count($physicalObjects) > 0 : !empty($physicalObjects)))
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-box me-1"></i> {{ config('app.ui_label_physicalobject', 'Physical storage') }}
        </div>
        <ul class="list-group list-group-flush">
          @foreach($physicalObjects as $pobj)
            <li class="list-group-item small">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  @if(isset($physicalObjectTypeNames[$pobj->type_id ?? null]))
                    <span class="badge bg-secondary me-1">{{ $physicalObjectTypeNames[$pobj->type_id] }}</span>
                  @endif
                  @if(isset($pobj->slug))
                    <a href="{{ route('physicalobject.show', $pobj->slug) }}" class="fw-bold text-decoration-none">{{ $pobj->name ?? '[Unknown]' }}</a>
                  @else
                    <span class="fw-bold">{{ $pobj->name ?? '[Unknown]' }}</span>
                  @endif
                </div>
              </div>
              @if(isset($pobj->location) && $pobj->location)
                <div class="mt-1 text-muted">
                  <i class="fas fa-map-marker-alt me-1"></i> {{ $pobj->location }}
                </div>
              @endif
              @if(isset($pobj->description) && $pobj->description)
                <div class="mt-1 text-muted small">
                  <i class="fas fa-info-circle me-1"></i> {{ $pobj->description }}
                </div>
              @endif
            </li>
          @endforeach
        </ul>
      </div>
    @endif
    @endif {{-- end physical_storage visibility --}}

    {{-- RiC Actions --}}
    @if(class_exists(\AhgRic\Controllers\RicEntityController::class))
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-project-diagram me-1"></i> RiC
        </div>
        <div class="list-group list-group-flush">
          <a href="{{ route('ric.explorer') }}?id={{ $io->id }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-project-diagram me-1"></i> Graph Explorer
          </a>
          <a href="{{ route('ric.export-jsonld') }}?id={{ $io->id }}" class="list-group-item list-group-item-action small" target="_blank">
            <i class="fas fa-code me-1"></i> JSON-LD Export
          </a>
          <a href="{{ route('ric.explorer') }}?id={{ $io->id }}&view=timeline" class="list-group-item list-group-item-action small">
            <i class="fas fa-clock me-1"></i> Timeline
          </a>
        </div>
      </div>
    @endif

  </nav>

@endsection

{{-- ============================================================ --}}
{{-- Describe Object/Image Modal --}}
@auth
<div class="modal fade" id="describeModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Describe Object/Image</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">AI-powered visual description of <strong>{{ $io->title ?? 'this record' }}</strong>. Analyses the digital object and generates a detailed description.</p>

        <div class="text-center mb-3">
          <button type="button" class="btn btn-primary btn-lg" id="describeBtn">
            <i class="fas fa-eye me-2"></i>Describe Object
          </button>
        </div>

        <div id="describeResults" style="display:none">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
              <span><i class="fas fa-file-alt me-1"></i>AI Description</span>
              <button type="button" class="btn btn-sm btn-light" id="describeApproveBtn" style="display:none">
                <i class="fas fa-check me-1"></i>Approve & Save
              </button>
            </div>
            <div class="card-body" id="describeResultsBody"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
(function() {
  var objectId = {{ $io->id }};
  var ioTitle = @json($io->title ?? 'Untitled');
  var ioContext = @json(($io->scope_and_content ?? '') . ' ' . ($io->extent_and_medium ?? ''));

  document.getElementById('describeBtn').addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analysing with AI...';

    fetch('/admin/ai/suggest', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify({ title: ioTitle, context: ioContext })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-eye me-2"></i>Re-Describe';

      var body = document.getElementById('describeResultsBody');
      document.getElementById('describeResults').style.display = '';

      if (!data.success) {
        body.innerHTML = '<div class="alert alert-danger">' + (data.error || 'AI description failed') + '</div>';
        return;
      }

      var desc = data.description || '';
      var time = data.processing_time_ms || 0;

      body.innerHTML = '<p class="text-muted small mb-2">Generated in ' + time + 'ms</p>' +
        '<div class="border rounded p-3 bg-white">' + desc.replace(/\n/g, '<br>') + '</div>';
      document.getElementById('describeApproveBtn').style.display = '';

      // Store for approve
      window._aiDescription = desc;
    })
    .catch(function(err) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-eye me-2"></i>Describe Object';
      document.getElementById('describeResultsBody').innerHTML = '<div class="alert alert-danger">' + err.message + '</div>';
      document.getElementById('describeResults').style.display = '';
    });
  });

  document.getElementById('describeApproveBtn').addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    // Save to ahg_ai_suggestion
    fetch('/admin/ai/suggest', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify({
        title: ioTitle,
        context: 'SAVE_SUGGESTION:' + objectId + ':scope_and_content:' + (window._aiDescription || '')
      })
    })
    .then(function() {
      // Save directly to the record
      btn.innerHTML = '<i class="fas fa-check me-1"></i>Saved!';
      setTimeout(function() { location.reload(); }, 1000);
    })
    .catch(function() {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check me-1"></i>Approve & Save';
      alert('Failed to save');
    });
  });
})();
</script>
@endauth

{{-- NER Modal --}}
@auth
<div class="modal fade" id="nerModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="modal-title"><i class="fas fa-brain me-2"></i>Extract Entities (NER)</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Named Entity Recognition — extract persons, organizations, places, dates from <strong>{{ $io->title ?? 'this record' }}</strong></p>

        {{-- Extract button --}}
        <div class="text-center mb-3" id="nerExtractSection">
          <button type="button" class="btn btn-primary btn-lg" id="nerExtractBtn">
            <i class="fas fa-brain me-2"></i>Extract Entities
          </button>
        </div>

        {{-- Results (hidden until extraction) --}}
        <div id="nerResults" style="display:none">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small" id="nerResultsMeta"></span>
            <div class="d-flex gap-1" id="nerActionBtns" style="display:none">
              <a href="{{ route('io.ai.review') }}?object_id={{ $io->id }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-list-check me-1"></i>Review & Link
              </a>
              <button type="button" class="btn btn-success btn-sm" id="nerApproveBtn">
                <i class="fas fa-check me-1"></i>Approve All
              </button>
            </div>
          </div>
          <div id="nerResultsBody"></div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="{{ route('io.ai.review') }}" class="btn btn-outline-primary btn-sm" id="nerFooterReview" style="display:none">
          <i class="fas fa-list-check me-1"></i>Review & Link
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
(function() {
  var objectId = {{ $io->id }};
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

      if (count === 0) {
        document.getElementById('nerResultsBody').innerHTML = '<p class="text-muted text-center">No entities found in this record.</p>';
        return;
      }

      var html = '';
      for (var type in entities) {
        var items = entities[type];
        if (!items || !items.length) continue;
        var icon = icons[type] || 'fa-tag';
        var color = colors[type] || 'secondary';
        html += '<div class="mb-3"><h6 class="mb-1"><i class="fas ' + icon + ' me-1"></i>' + type + ' <span class="badge bg-' + color + '">' + items.length + '</span></h6>';
        html += '<div class="d-flex flex-wrap gap-1">';
        items.forEach(function(item) {
          html += '<span class="badge bg-' + color + ' bg-opacity-75 fw-normal py-1 px-2">' + item + '</span>';
        });
        html += '</div></div>';
      }

      document.getElementById('nerResultsBody').innerHTML = html;
      document.getElementById('nerActionBtns').style.display = '';
      document.getElementById('nerFooterReview').style.display = '';
    })
    .catch(function(err) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-brain me-2"></i>Extract Entities';
      document.getElementById('nerResultsBody').innerHTML = '<div class="alert alert-danger">' + err.message + '</div>';
      document.getElementById('nerResults').style.display = '';
    });
  });

  document.getElementById('nerApproveBtn').addEventListener('click', function() {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Approving...';
    window.location.href = '{{ route("io.ai.review") }}';
  });
})();
</script>
@endauth

{{-- AFTER CONTENT: Action buttons                                --}}
{{-- ============================================================ --}}
{{-- Summary Modal --}}
@auth
<div class="modal fade" id="summaryModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>Generate Summary</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="summaryModalBody">
        <div class="text-center py-5">
          <div class="spinner-border text-success mb-3"></div>
          <p class="text-muted">Loading summary generator...</p>
        </div>
      </div>
      <div class="modal-footer">
        <a href="{{ route('io.ai.summarize', $io->id) }}" class="btn btn-sm atom-btn-white" target="_blank">
          <i class="fas fa-external-link-alt me-1"></i>Open Full Page
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
document.getElementById('summaryModal').addEventListener('shown.bs.modal', function() {
  var body = document.getElementById('summaryModalBody');
  if (body.dataset.loaded) return;
  body.dataset.loaded = '1';
  fetch('{{ route("io.ai.summarize", $io->id) }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  .then(function(r) { return r.text(); })
  .then(function(html) {
    var parser = new DOMParser();
    var doc = parser.parseFromString(html, 'text/html');
    var content = doc.querySelector('#content, [role="main"], .container');
    body.innerHTML = content ? content.innerHTML : html;
  })
  .catch(function(err) { body.innerHTML = '<div class="alert alert-danger">Failed to load: ' + err.message + '</div>'; });
});
</script>
@endauth

@section('after-content')
  @auth
  @php $isAdmin = auth()->user()->is_admin; @endphp
  <ul class="actions mb-3 nav gap-2">
    {{-- Edit button: any authenticated user can edit --}}
    <li>
      <a href="{{ route('informationobject.edit', $io->slug) }}" class="btn atom-btn-outline-light">Edit</a>
    </li>
    {{-- Delete button: admin only --}}
    @if($isAdmin)
    <li>
      <form action="{{ route('informationobject.destroy', $io->slug) }}" method="POST"
            onsubmit="return confirm('Are you sure you want to delete this archival description?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn atom-btn-outline-danger">Delete</button>
      </form>
    </li>
    @endif
    {{-- Add new: any authenticated user --}}
    <li>
      <a href="{{ route('informationobject.create', ['parent_id' => $io->id]) }}" class="btn atom-btn-outline-light">Add new</a>
    </li>
    <li>
      <a href="{{ route('informationobject.create', ['parent_id' => $io->id, 'copy_from' => $io->id]) }}" class="btn atom-btn-outline-light">Duplicate</a>
    </li>
    {{-- Move button: admin only --}}
    @if($isAdmin)
    <li>
      <a href="{{ url('/' . $io->slug . '/default/move') }}" class="btn atom-btn-outline-light">Move</a>
    </li>
    @endif
    <li>
      <div class="dropup">
        <button type="button" class="btn atom-btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          More
        </button>
        <ul class="dropdown-menu mb-2">
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.rename', $io->slug) }}">
              <i class="fas fa-i-cursor me-2"></i>Rename
            </a>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.edit', ['slug' => $io->slug, 'storage' => 1]) }}">
              <i class="fas fa-box me-2"></i>Link physical storage
            </a>
          </li>
          <li><hr class="dropdown-divider"></li>
          @if(isset($digitalObjects) && $digitalObjects['master'])
            <li>
              <a class="dropdown-item" href="{{ route('io.digitalobject.show', $digitalObjects['master']->id) }}">
                <i class="fas fa-photo-video me-2"></i>Edit digital object
              </a>
            </li>
          @else
            <li>
              <a class="dropdown-item" href="{{ route('informationobject.edit', ['slug' => $io->slug, 'upload' => 1]) }}">
                <i class="fas fa-link me-2"></i>Link digital object
              </a>
            </li>
          @endif
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.import.xml', $io->slug) }}">
              <i class="fas fa-file-import me-2"></i>Import digital objects
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ url('/' . $io->slug . '/right/edit') }}">
              <i class="fas fa-balance-scale me-2"></i>Create new rights
            </a>
          </li>
          @if(isset($hasChildren) && $hasChildren)
            <li>
              <a class="dropdown-item" href="{{ url('/' . $io->slug . '/right/manage') }}">
                <i class="fas fa-sitemap me-2"></i>Manage rights inheritance
              </a>
            </li>
          @endif
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.export.ead', $io->slug) }}">
              <i class="fas fa-file-code me-2"></i>Export EAD 2002
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.export.ead3', $io->slug) }}">
              <i class="fas fa-file-code me-2"></i>Export EAD3
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.export.ead4', $io->slug) }}">
              <i class="fas fa-file-code me-2"></i>Export EAD 4
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.export.dc', $io->slug) }}">
              <i class="fas fa-file-alt me-2"></i>Export Dublin Core
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.export.mods', $io->slug) }}">
              <i class="fas fa-file-alt me-2"></i>Export MODS
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.export.rico', $io->slug) }}">
              <i class="fas fa-file-alt me-2"></i>Export RiC-O JSON-LD
            </a>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="{{ route('io.showUpdateStatus', $io->slug ?? '') }}">
              <i class="fas fa-eye me-2"></i>Update publication status
            </a>
          </li>
          {{-- Modification history: only show if audit logging is enabled --}}
          @if(isset($auditLogEnabled) && $auditLogEnabled)
          <li>
            <a class="dropdown-item" href="{{ route('audit.browse', ['type' => 'QubitInformationObject', 'id' => $io->id ?? '']) }}">
              <i class="fas fa-history me-2"></i>Modification history
            </a>
          </li>
          @endif
        </ul>
      </div>
    </li>
    <li>
      <a href="{{ route('informationobject.print', $io->slug) }}" class="btn atom-btn-outline-light" target="_blank">
        <i class="fas fa-print me-1"></i>Print
      </a>
    </li>
  </ul>
  @endauth
  <script src="{{ asset('vendor/ahg-theme-b5/js/ahg-transcription.js') }}"></script>

  {{-- Translate Modal --}}
  @auth
  @php
    $targetLanguages = [
        'en'=>'English','af'=>'Afrikaans','zu'=>'isiZulu','xh'=>'isiXhosa','st'=>'Sesotho',
        'tn'=>'Setswana','nso'=>'Sepedi','ts'=>'Xitsonga','ss'=>'SiSwati','ve'=>'Tshivenda',
        'nr'=>'isiNdebele','nl'=>'Dutch','fr'=>'French','de'=>'German','es'=>'Spanish',
        'pt'=>'Portuguese','sw'=>'Swahili','ar'=>'Arabic',
    ];
    $allFields = [
        'title'=>'Title','alternate_title'=>'Alternate Title','scope_and_content'=>'Scope and Content',
        'archival_history'=>'Archival History','acquisition'=>'Acquisition','arrangement'=>'Arrangement',
        'access_conditions'=>'Access Conditions','reproduction_conditions'=>'Reproduction Conditions',
        'finding_aids'=>'Finding Aids','related_units_of_description'=>'Related Units',
        'appraisal'=>'Appraisal','accruals'=>'Accruals','physical_characteristics'=>'Physical Characteristics',
        'location_of_originals'=>'Location of Originals','location_of_copies'=>'Location of Copies',
        'extent_and_medium'=>'Extent and Medium','sources'=>'Sources','rules'=>'Rules',
        'revision_history'=>'Revision History',
    ];
  @endphp
  <div class="modal fade" id="translateModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header bg-secondary text-white">
          <h5 class="modal-title"><i class="fas fa-language me-2"></i>Translate Record <span class="badge bg-light text-dark ms-2 translate-step-badge">Step 1: Select Fields</span></h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="max-height:75vh;overflow-y:auto;">
          {{-- Step 1 --}}
          <div id="translate-step1">
            <div class="row mb-3">
              <div class="col-md-4">
                <label class="form-label fw-bold">Source Language</label>
                <select id="translateSource" class="form-select">
                  @foreach($targetLanguages as $code => $name)
                    <option value="{{ $code }}" @selected($code === ($io->source_culture ?? 'en'))>{{ $name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Target Language</label>
                <select id="translateTarget" class="form-select">
                  @foreach($targetLanguages as $code => $name)
                    <option value="{{ $code }}" @selected($code === 'af')>{{ $name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Options</label>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="translateSaveCulture" checked><label class="form-check-label small" for="translateSaveCulture">Save with culture code</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="translateOverwrite"><label class="form-check-label small" for="translateOverwrite">Overwrite existing</label></div>
              </div>
            </div>
            <hr>
            <div class="d-flex justify-content-between mb-2">
              <span class="fw-bold">Fields to Translate</span>
              <div><button type="button" class="btn btn-sm btn-outline-secondary" id="translateSelectAll">Select All</button> <button type="button" class="btn btn-sm btn-outline-secondary" id="translateDeselectAll">Deselect All</button></div>
            </div>
            <div class="row">
              @php $i = 0; @endphp
              @foreach($allFields as $key => $label)
                @if($i % 10 === 0)<div class="col-md-6">@endif
                <div class="form-check">
                  <input class="form-check-input translate-field-cb" type="checkbox" value="{{ $key }}" data-label="{{ $label }}" id="tf-{{ $key }}" @checked(in_array($key, ['title','scope_and_content']))>
                  <label class="form-check-label" for="tf-{{ $key }}">{{ $label }}</label>
                </div>
                @if($i % 10 === 9 || $i === count($allFields) - 1)</div>@endif
                @php $i++; @endphp
              @endforeach
            </div>
            <div class="alert alert-info py-2 mt-3 mb-0"><i class="fas fa-info-circle me-1"></i>Click "Translate" to preview translations before saving.</div>
          </div>
          {{-- Step 2 --}}
          <div id="translate-step2" style="display:none;">
            <div class="alert alert-warning py-2 mb-3"><i class="fas fa-eye me-1"></i><strong>Review Translations</strong> — Edit if needed, then click "Approve & Save".</div>
            <div id="translatePreview"></div>
          </div>
          <div class="mt-3"><div class="alert py-2 mb-0" id="translateStatus" style="display:none;"></div></div>
        </div>
        <div class="modal-footer">
          <div id="translateStep1Btns">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Close</button>
            <button type="button" class="btn btn-primary" id="translateRunBtn"><i class="fas fa-language me-1"></i>Translate</button>
          </div>
          <div id="translateStep2Btns" style="display:none;">
            <button type="button" class="btn btn-outline-secondary" id="translateBackBtn"><i class="fas fa-arrow-left me-1"></i>Back</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Cancel</button>
            <button type="button" class="btn btn-success" id="translateApproveBtn"><i class="fas fa-check me-1"></i>Approve & Save</button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
  (function(){
    var modalEl = document.getElementById('translateModal');
    if (!modalEl) return;
    var objectSlug = '{{ $io->slug }}';
    var results = [];

    document.getElementById('translateSelectAll').addEventListener('click', function(){ document.querySelectorAll('.translate-field-cb').forEach(function(cb){cb.checked=true;}); });
    document.getElementById('translateDeselectAll').addEventListener('click', function(){ document.querySelectorAll('.translate-field-cb').forEach(function(cb){cb.checked=false;}); });

    function showStep(n) {
      document.getElementById('translate-step1').style.display = n===1?'':'none';
      document.getElementById('translate-step2').style.display = n===2?'':'none';
      document.getElementById('translateStep1Btns').style.display = n===1?'':'none';
      document.getElementById('translateStep2Btns').style.display = n===2?'':'none';
      modalEl.querySelector('.translate-step-badge').textContent = n===1?'Step 1: Select Fields':'Step 2: Review & Approve';
    }

    function showStatus(msg, type) {
      var el = document.getElementById('translateStatus');
      el.style.display = 'block';
      el.className = 'alert py-2 mb-0 alert-' + (type||'secondary');
      el.textContent = msg;
    }

    function esc(t) { var d=document.createElement('div'); d.textContent=t; return d.innerHTML; }

    document.getElementById('translateRunBtn').addEventListener('click', async function(){
      var source = document.getElementById('translateSource').value;
      var target = document.getElementById('translateTarget').value;
      var fields = Array.from(document.querySelectorAll('.translate-field-cb:checked')).map(function(cb){ return {field:cb.value, label:cb.dataset.label}; });
      if (!fields.length) { showStatus('Select at least one field.','danger'); return; }
      if (source===target) { showStatus('Source and target must differ.','danger'); return; }

      this.disabled = true;
      results = [];
      var csrfToken = (document.querySelector('meta[name="csrf-token"]')||{}).content||'';

      for (var i=0; i<fields.length; i++) {
        showStatus('Translating '+(i+1)+'/'+fields.length+': '+fields[i].label+'...','info');
        try {
          var body = new URLSearchParams({field:fields[i].field, targetField:fields[i].field, source:source, target:target, apply:'0', saveCulture:'0', overwrite:'0'});
          var res = await fetch('/admin/translation/translate/'+objectSlug, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-TOKEN':csrfToken}, body:body});
          var json = await res.json();
          results.push({field:fields[i].field, label:fields[i].label, ok:json.ok||json.success, translation:json.translation||'', sourceText:json.source_text||'', draft_id:json.draft_id, error:json.error});
        } catch(e) { results.push({field:fields[i].field, label:fields[i].label, ok:false, error:e.message}); }
      }
      this.disabled = false;
      document.getElementById('translateStatus').style.display = 'none';

      var html = '';
      results.forEach(function(r,idx){
        var badge = r.ok?'<span class="badge bg-success">OK</span>':'<span class="badge bg-danger">Failed</span>';
        html += '<div class="card mb-2"><div class="card-header py-2">'+badge+' <strong class="ms-2">'+esc(r.label)+'</strong></div><div class="card-body">';
        if (r.ok) {
          html += '<div class="row"><div class="col-md-6"><label class="form-label small fw-bold text-muted">Source</label><div class="border rounded p-2 bg-light small" style="max-height:120px;overflow-y:auto;">'+esc(r.sourceText||'(empty)')+'</div></div>';
          html += '<div class="col-md-6"><label class="form-label small fw-bold text-success"><i class="fas fa-arrow-right me-1"></i>Translation</label><textarea class="form-control small translated-text" data-field="'+r.field+'" data-draft-id="'+(r.draft_id||'')+'" rows="3">'+esc(r.translation)+'</textarea></div></div>';
        } else { html += '<div class="alert alert-danger mb-0 small">'+esc(r.error||'Failed')+'</div>'; }
        html += '</div></div>';
      });
      document.getElementById('translatePreview').innerHTML = html;
      showStep(2);
    });

    document.getElementById('translateBackBtn').addEventListener('click', function(){ showStep(1); document.getElementById('translateStatus').style.display='none'; });

    document.getElementById('translateApproveBtn').addEventListener('click', async function(){
      this.disabled = true;
      var target = document.getElementById('translateTarget').value;
      var saveCulture = document.getElementById('translateSaveCulture').checked?'1':'0';
      var overwrite = document.getElementById('translateOverwrite').checked?'1':'0';
      var csrfToken = (document.querySelector('meta[name="csrf-token"]')||{}).content||'';
      var saved=0, failed=0;

      var textareas = document.querySelectorAll('.translated-text');
      for (var ta of textareas) {
        if (!ta.dataset.draftId) continue;
        showStatus('Saving '+(saved+failed+1)+'/'+textareas.length+'...','info');
        try {
          var body = new URLSearchParams({draftId:ta.dataset.draftId, overwrite:overwrite, saveCulture:saveCulture, targetCulture:target, editedText:ta.value});
          var res = await fetch('/admin/translation/apply', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-TOKEN':csrfToken}, body:body});
          var json = await res.json();
          if (json.ok||json.success) saved++; else failed++;
        } catch(e) { failed++; }
      }
      this.disabled = false;
      if (failed===0) { showStatus('Saved '+saved+' translation(s) with culture "'+target+'".','success'); setTimeout(function(){location.reload();},2000); }
      else { showStatus('Saved: '+saved+', Failed: '+failed,'warning'); }
    });

    modalEl.addEventListener('hidden.bs.modal', function(){ showStep(1); document.getElementById('translateStatus').style.display='none'; document.getElementById('translatePreview').innerHTML=''; results=[]; });
  })();
  </script>
  @endauth
@endsection
