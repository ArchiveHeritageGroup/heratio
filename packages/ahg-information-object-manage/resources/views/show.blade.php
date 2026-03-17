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
        <a href="{{ route('io.research.citation', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-quote-left me-1"></i> Generate citation
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
      <span class="badge {{ (isset($publicationStatusId) && $publicationStatusId == 159) ? 'bg-warning text-dark' : 'bg-info' }} mb-2">{{ $publicationStatus }}</span>
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
            <a href="{{ $masterUrl }}" download class="btn btn-sm btn-outline-secondary">
              <i class="fas fa-download me-1"></i>Download video
            </a>
          @endauth
        </div>

      @elseif($masterMediaType === 'audio')
        {{-- Audio: HTML5 player with streaming fallback --}}
        @php
          $audioSrc = $needsStreaming && $refObj ? $refUrl : $masterUrl;
          $audioMime = $needsStreaming && $refObj ? ($refObj->mime_type ?? 'audio/mpeg') : $masterMime;
        @endphp
        <audio id="ahg-audio-player" controls class="w-100" preload="metadata">
          <source src="{{ $audioSrc }}" type="{{ $audioMime }}">
          @if($needsStreaming && $audioSrc !== $masterUrl)
            <source src="{{ $masterUrl }}" type="{{ $masterMime }}">
          @endif
          Your browser does not support this audio format.
        </audio>
        <div class="mt-2 d-flex justify-content-between align-items-center">
          <div>
            <span class="badge bg-secondary">{{ $masterObj->name ?? '' }}</span>
            <span class="badge bg-light text-dark">{{ $masterMime }}</span>
          </div>
          @auth
            <a href="{{ $masterUrl }}" download class="btn btn-sm btn-outline-secondary">
              <i class="fas fa-download me-1"></i>Download audio
            </a>
          @endauth
        </div>

      @elseif($refUrl || $thumbUrl)
        {{-- Image: OpenSeadragon + Mirador viewer (matching AtoM) --}}
        @php $viewerId = 'iiif-viewer-' . $io->id; $imgSrc = $masterUrl ?: $refUrl; @endphp

        {{-- Viewer toggle --}}
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="btn-group btn-group-sm" role="group">
            <button id="btn-osd-{{ $viewerId }}" class="btn btn-outline-secondary active" title="OpenSeadragon Deep Zoom">
              <i class="fas fa-search-plus me-1"></i>Deep Zoom
            </button>
            <button id="btn-mirador-{{ $viewerId }}" class="btn btn-outline-secondary" title="Mirador IIIF Viewer">
              <i class="fas fa-columns me-1"></i>Mirador
            </button>
            <button id="btn-img-{{ $viewerId }}" class="btn btn-outline-secondary" title="Simple image">
              <i class="fas fa-image me-1"></i>Image
            </button>
          </div>
          <div class="btn-group btn-group-sm">
            <a href="{{ $imgSrc }}" target="_blank" class="btn btn-outline-secondary" title="Open full size">
              <i class="fas fa-external-link-alt"></i>
            </a>
            <button id="btn-fs-{{ $viewerId }}" class="btn btn-outline-primary" title="Fullscreen">
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
        <script>
        document.addEventListener('DOMContentLoaded', function() {
          var vid = '{{ $viewerId }}';
          var imgSrc = '{{ $imgSrc }}';
          var osdEl = document.getElementById('osd-' + vid);
          var mirEl = document.getElementById('mirador-' + vid);
          var imgEl = document.getElementById('img-' + vid);
          var osdViewer = null, miradorInst = null;

          function showOSD() {
            osdEl.style.display = 'block'; mirEl.style.display = 'none'; imgEl.style.display = 'none';
            document.getElementById('btn-osd-' + vid).classList.add('active');
            document.getElementById('btn-mirador-' + vid).classList.remove('active');
            document.getElementById('btn-img-' + vid).classList.remove('active');
            if (!osdViewer && typeof OpenSeadragon !== 'undefined') {
              osdViewer = OpenSeadragon({
                id: 'osd-' + vid,
                tileSources: { type: 'image', url: imgSrc },
                showNavigator: true, navigatorPosition: 'BOTTOM_RIGHT',
                prefixUrl: '/vendor/ahg-theme-b5/js/vendor/openseadragon/images/',
                gestureSettingsMouse: { clickToZoom: true },
                animationTime: 0.5, zoomPerClick: 1.5, maxZoomPixelRatio: 4,
                visibilityRatio: 0.5, constrainDuringPan: true
              });
            }
          }

          function showMirador() {
            osdEl.style.display = 'none'; mirEl.style.display = 'block'; imgEl.style.display = 'none';
            document.getElementById('btn-mirador-' + vid).classList.add('active');
            document.getElementById('btn-osd-' + vid).classList.remove('active');
            document.getElementById('btn-img-' + vid).classList.remove('active');
            if (!miradorInst) {
              var s = document.createElement('script');
              s.src = '{{ asset("vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js") }}';
              s.onload = function() {
                if (typeof Mirador !== 'undefined') {
                  miradorInst = Mirador.viewer({
                    id: 'mirador-' + vid,
                    windows: [{ manifestId: '{{ url("/manifest-collection/" . $io->slug . "/manifest.json") }}' }],
                    window: { allowClose: false, allowMaximize: false }
                  });
                } else { mirEl.innerHTML = '<div class="alert alert-warning m-3">Mirador not available.</div>'; }
              };
              document.head.appendChild(s);
            }
          }

          function showImg() {
            osdEl.style.display = 'none'; mirEl.style.display = 'none'; imgEl.style.display = 'block';
            document.getElementById('btn-img-' + vid).classList.add('active');
            document.getElementById('btn-osd-' + vid).classList.remove('active');
            document.getElementById('btn-mirador-' + vid).classList.remove('active');
          }

          document.getElementById('btn-osd-' + vid).addEventListener('click', showOSD);
          document.getElementById('btn-mirador-' + vid).addEventListener('click', showMirador);
          document.getElementById('btn-img-' + vid).addEventListener('click', showImg);
          document.getElementById('btn-fs-' + vid).addEventListener('click', function() {
            var el = osdEl.style.display !== 'none' ? osdEl : (mirEl.style.display !== 'none' ? mirEl : imgEl);
            if (el.requestFullscreen) el.requestFullscreen();
            else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
          });

          showOSD();
        });
        </script>
      @else
        {{-- No displayable object: show download link --}}
        <div class="py-4">
          <i class="fas fa-file fa-3x text-muted mb-3 d-block"></i>
          <p class="text-muted">{{ $masterObj->name ?? 'Digital object' }}</p>
          @auth
            <a href="{{ $masterUrl }}" download class="btn btn-outline-primary">
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
            <div class="card-header d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#media-info-collapse" aria-expanded="false">
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
                  <table class="table table-sm table-borderless">
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
                  <table class="table table-sm table-borderless">
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
              <button class="btn btn-primary" id="extract-btn-{{ $doId }}" data-action="extract" data-do-id="{{ $doId }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-magic me-1"></i>Extract Metadata</button>
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
            <div class="card-header d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#transcription-collapse" aria-expanded="false">
              <span><i class="fas fa-file-alt me-2"></i>Transcription</span>
              <div class="btn-group btn-group-sm" onclick="event.stopPropagation();">
                <a href="/media/transcription/{{ $doId }}/vtt" class="btn btn-outline-secondary" title="Download VTT"><i class="fas fa-closed-captioning"></i> VTT</a>
                <a href="/media/transcription/{{ $doId }}/srt" class="btn btn-outline-secondary" title="Download SRT"><i class="fas fa-file-video"></i> SRT</a>
                @auth
                <button class="btn btn-outline-warning" title="Re-transcribe" data-action="retranscribe" data-do-id="{{ $doId }}" data-lang="{{ $transcription->language ?? 'en' }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-redo"></i></button>
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
                <button class="btn btn-outline-secondary" type="button" id="transcript-search-btn-{{ $doId }}"><i class="fas fa-search"></i></button>
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
                <button class="btn btn-outline-secondary active" id="btn-text-{{ $doId }}"><i class="fas fa-align-left"></i> Full Text</button>
                <button class="btn btn-outline-secondary" id="btn-segments-{{ $doId }}"><i class="fas fa-list"></i> Timed Segments</button>
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
                <button class="btn btn-primary" data-action="transcribe" data-do-id="{{ $doId }}" data-lang="en" data-csrf="{{ csrf_token() }}"><i class="fas fa-language me-1"></i>Transcribe (English)</button>
                <button class="btn btn-outline-primary" data-action="transcribe" data-do-id="{{ $doId }}" data-lang="af" data-csrf="{{ csrf_token() }}">Afrikaans</button>
              </div>
            </div>
          </div>
          @endauth
        @endif

        {{-- Snippets --}}
        @if($snippets->isNotEmpty())
          <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
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
                  <button class="btn btn-sm btn-outline-primary" onclick="var p=document.querySelector('audio,video');p&&(p.currentTime={{ $snippet->start_time }},p.play())" title="Play snippet">
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
          <button class="btn btn-sm btn-outline-secondary" id="create-snippet-btn" onclick="document.getElementById('snippet-form').style.display=document.getElementById('snippet-form').style.display==='none'?'block':'none';">
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
                <button class="btn btn-sm btn-outline-info" onclick="var p=document.querySelector('audio,video');p&&(document.getElementById('snippet-start').value=p.currentTime.toFixed(1))">
                  <i class="fas fa-sign-in-alt"></i> Mark IN
                </button>
                <button class="btn btn-sm btn-outline-info" onclick="var p=document.querySelector('audio,video');p&&(document.getElementById('snippet-end').value=p.currentTime.toFixed(1))">
                  <i class="fas fa-sign-out-alt"></i> Mark OUT
                </button>
                <button class="btn btn-sm btn-primary" data-action="save-snippet" data-do-id="{{ $doId }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-save me-1"></i>Save Snippet</button>
              </div>
            </div>
          </div>
        </div>
        @endauth
      @endif
    @endif
  @endif

@endsection

{{-- ============================================================ --}}
{{-- MAIN CONTENT: ISAD(G) sections                              --}}
{{-- ============================================================ --}}
@section('content')

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
        <a href="{{ route('favorites.remove', \Illuminate\Support\Facades\DB::table('favorites')->where('user_id', $userId)->where('archival_description_id', $io->id)->value('id')) }}"
           class="btn btn-sm btn-outline-danger" title="Remove from Favorites" data-bs-toggle="tooltip">
          <i class="fas fa-heart-broken"></i>
        </a>
      @else
        <a href="{{ route('favorites.add', $io->slug) }}"
           class="btn btn-sm btn-outline-danger" title="Add to Favorites" data-bs-toggle="tooltip">
          <i class="fas fa-heart"></i>
        </a>
      @endif

      {{-- Cart --}}
      @if($hasDigitalObject)
        @if($inCart)
          <a href="{{ route('cart.browse') }}" class="btn btn-sm btn-outline-success" title="Go to Cart" data-bs-toggle="tooltip">
            <i class="fas fa-shopping-cart"></i>
          </a>
        @else
          <a href="{{ route('cart.add', $io->slug) }}" class="btn btn-sm btn-outline-success" title="Add to Cart" data-bs-toggle="tooltip">
            <i class="fas fa-cart-plus"></i>
          </a>
        @endif
      @endif

      {{-- Feedback --}}
      <a href="{{ url('/feedback/submit/' . $io->slug) }}" class="btn btn-sm btn-outline-secondary" title="Item Feedback" data-bs-toggle="tooltip">
        <i class="fas fa-comment"></i>
      </a>

      {{-- Request to Publish --}}
      @if($hasDigitalObject)
        <a href="{{ route('cart.add', $io->slug) }}" class="btn btn-sm btn-outline-primary" title="Request to Publish" data-bs-toggle="tooltip">
          <i class="fas fa-paper-plane"></i>
        </a>
      @endif

      {{-- Loan: New + Manage --}}
      <a href="{{ route('loan.create', ['object_id' => $io->id]) }}" class="btn btn-sm btn-outline-warning" title="New Loan" data-bs-toggle="tooltip">
        <i class="fas fa-hand-holding"></i>
      </a>
      <a href="{{ route('loan.index', ['object_id' => $io->id]) }}" class="btn btn-sm btn-outline-info" title="Manage Loans" data-bs-toggle="tooltip">
        <i class="fas fa-exchange-alt"></i>
      </a>
    @endauth
  </div>

  {{-- ===== 1. Identity area ===== --}}
  <section id="identityArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#identity-collapse">
        Identity area
      </a>
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
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
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
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
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
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
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
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
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
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

      {{-- Related material descriptions (relation type_id = 176) --}}
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
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
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
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
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
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#description-collapse">
        Description control area
      </a>
      @if(auth()->check())
        <a href="{{ route('informationobject.edit', $io->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endif
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
    <section id="rightsArea" class="border-bottom">
      <h2 class="h6 mb-0 atom-section-header">
        <a class="d-flex py-2 px-3 border-bottom text-primary text-decoration-none" href="#rights-collapse">
          Rights area
        </a>
      </h2>
      <div id="rights-collapse">
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
      <h2 class="h6 mb-0 atom-section-header">
        <a class="d-flex py-2 px-3 border-bottom text-primary text-decoration-none" href="#digital-object-collapse">
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
                  <div class="field row g-0">
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
                    <div class="field row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">MIME type</h3>
                      <div class="col-9 p-2">{{ $doReference->mime_type }}</div>
                    </div>
                  @endif
                  @if($doReference->byte_size)
                    <div class="field row g-0">
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
                  <div class="field row g-0">
                    <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Filename</h3>
                    <div class="col-9 p-2">
                      <a href="{{ $doThumbUrl }}" target="_blank">{{ $doThumbnail->name }}</a>
                    </div>
                  </div>
                  @if($doThumbnail->mime_type)
                    <div class="field row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">MIME type</h3>
                      <div class="col-9 p-2">{{ $doThumbnail->mime_type }}</div>
                    </div>
                  @endif
                  @if($doThumbnail->byte_size)
                    <div class="field row g-0">
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
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#accession-collapse">
        Accession area
      </a>
    </h2>
    <div id="accession-collapse">
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
    <li>
      <a href="{{ route('informationobject.create', ['parent_id' => $io->id, 'copy_from' => $io->id]) }}" class="btn atom-btn-outline-light">Duplicate</a>
    </li>
    <li>
      <div class="dropup">
        <button type="button" class="btn atom-btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          More
        </button>
        <ul class="dropdown-menu mb-2">
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.edit', $io->slug) }}">
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
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.export.ead', $io->slug) }}">
              <i class="fas fa-file-code me-2"></i>Export EAD
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.export.dc', $io->slug) }}">
              <i class="fas fa-file-alt me-2"></i>Export Dublin Core
            </a>
          </li>
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
@endsection
