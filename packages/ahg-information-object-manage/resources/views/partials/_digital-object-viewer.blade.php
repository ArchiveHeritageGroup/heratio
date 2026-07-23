
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

      // Redaction reroute. Non-admin viewers must never see the un-redacted
      // master — redirect master/reference URLs through the redacted-asset
      // endpoint when there are redactions on file. The endpoint streams
      // the cached/rendered redacted file; admins fall through to original.
      $__hasRedactions = false;
      try {
          if (\Illuminate\Support\Facades\Schema::hasTable('privacy_visual_redaction')) {
              $__hasRedactions = \Illuminate\Support\Facades\DB::table('privacy_visual_redaction')
                  ->where('object_id', $io->id)
                  ->whereIn('status', ['applied', 'reviewed', 'pending'])
                  ->exists();
          }
      } catch (\Throwable $e) { /* table missing — leave flag false */ }
      $__isAdminViewer = auth()->check() && auth()->user()
          && (method_exists(auth()->user(), 'isAdministrator')
              ? auth()->user()->isAdministrator()
              : (bool) (auth()->user()->is_admin ?? false));
      if ($__hasRedactions && !$__isAdminViewer) {
          $__redactedUrl = route('io.privacy.redacted-asset', $io->slug);
          $masterUrl = $__redactedUrl;
          $refUrl    = $__redactedUrl;
          // Thumbnails stay on the original — they're typically too small to
          // contain redactable content, and re-rendering thumbs is expensive.
      }

      // Public-viewer gate: a NOT-logged-in visitor must never be handed the
      // master original by the open-in-new-window / full-size / fullscreen /
      // download controls — only a derivative (reference, else thumbnail).
      // When no derivative exists those controls are HIDDEN rather than falling
      // back to the master. $__derivUrl is the best public-facing derivative;
      // $__openUrl is what the "open/download original" controls point at
      // (master for authenticated viewers, derivative for the public);
      // $__showOriginalCtl gates whether those controls render at all.
      $__canSeeOriginal = auth()->check();
      $__derivUrl = $refUrl ?: $thumbUrl;
      $__openUrl = $__canSeeOriginal ? $masterUrl : $__derivUrl;
      $__showOriginalCtl = $__canSeeOriginal ? (bool) $masterUrl : ($__derivUrl !== '');

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

      // heratio#1193 - Gaussian splat uploaded as a digital object (.splat/.ksplat, or a 3DGS
      // .ply distinguished from a mesh .ply by its header). Render it with the gs3d viewer as a
      // "3D" mode in the switcher below, REPLACING the mesh branch (so no empty mesh canvas).
      // Standardised across GLAM + DAM because this partial is the shared digital-object viewer.
      $isSplatMaster = false;
      $splatDoUrl = null;
      if ($masterObj && $masterObj->name) {
          $mext = strtolower(pathinfo($masterObj->name, PATHINFO_EXTENSION));
          if (in_array($mext, ['splat', 'ksplat'], true)) {
              $isSplatMaster = true;
          } elseif ($mext === 'ply') {
              try { $isSplatMaster = app(\AhgCore\Services\GaussianSplatService::class)->isGaussianPly($masterObj); }
              catch (\Throwable $e) { $isSplatMaster = false; }
          }
          if ($isSplatMaster) {
              $splatDoUrl = '/splat/do/'.$masterObj->id.'?embed=1';
              $is3DModel = false;   // gs3d replaces the mesh viewer for a splat .ply
          }
      }

      // heratio#1396 - encrypted-at-rest master detection. Either envelope
      // (Heratio's decryptable AHG_ENC_DERIV_v1 or the foreign AHG-ENC-V2)
      // means the master bytes on disk are ciphertext: Cantaloupe returns
      // 501 "Unsupported source format" and deep zoom can never render, so
      // the viewer must degrade deliberately instead of failing silently.
      $masterEncryptedAtRest = false;
      if ($masterObj && ($masterObj->name ?? '')) {
          try {
              $__masterDisk = \AhgCore\Services\DigitalObjectService::resolveDiskPath($masterObj);
              $masterEncryptedAtRest = $__masterDisk !== null
                  && app(\AhgCore\Services\EncryptionService::class)->isFileEncryptedAtRest($__masterDisk);
          } catch (\Throwable $e) { /* treat as plain */ }
      }
    @endphp

    <div class="digital-object-reference text-center p-3 border-bottom">
      @if($isPdf)
        @php
          // Prefer the web-optimized PDF derivative for DISPLAY (downsampled + linearized,
          // so big scans show page 1 fast); the master stays the download/open target.
          // Skipped when redactions reroute the master for non-admins - they must keep
          // streaming the redacted file, never an un-redacted web copy.
          $pdfDisplayUrl = $masterUrl;
          $__pdfIsMaster = true;   // display URL is still the raw master
          if (!($__hasRedactions && !$__isAdminViewer)) {
              $__webPdf = \AhgCore\Services\DigitalObjectService::getWebPdfUrl((int) $io->id);
              if ($__webPdf) { $pdfDisplayUrl = $__webPdf; $__pdfIsMaster = false; }
          }
          // Public gate: a not-logged-in viewer must not get the master PDF
          // inline either. A web-optimized reference PDF is a derivative and is
          // fine; a redacted stream is fine; but the genuine unredacted master
          // is blocked - the thumbnail preview is shown instead. (Redaction is
          // already handled above, so exclude that case from blocking.)
          $__pdfShowsRealMaster = $__pdfIsMaster && !($__hasRedactions && !$__isAdminViewer);
          $__pdfPublicBlocked = !$__canSeeOriginal && $__pdfShowsRealMaster;
        @endphp
        {{-- PDF: embedded iframe viewer with toolbar --}}
        <div class="pdf-viewer-container" style="overflow:hidden;">
          <div class="pdf-wrapper">
            <div class="pdf-toolbar mb-2 d-flex justify-content-between align-items-center">
              <span class="badge bg-danger">
                <i class="fas fa-file-pdf me-1"></i>{{ __('PDF Document') }}
              </span>
              <div class="btn-group btn-group-sm">
                @if($__showOriginalCtl)
                  <a href="{{ $__openUrl }}" target="_blank" class="btn atom-btn-white" title="{{ $__canSeeOriginal ? __('Open in new tab') : __('Open reference copy in new tab') }}">
                    <i class="fas fa-external-link-alt"></i>
                  </a>
                  <a href="{{ $__openUrl }}" download class="btn atom-btn-white" title="{{ $__canSeeOriginal ? __('Download PDF') : __('Download reference copy') }}">
                    <i class="fas fa-download"></i>
                  </a>
                @endif
              </div>
            </div>
            @if($__pdfPublicBlocked)
              {{-- Not logged in and no derivative PDF: show the thumbnail
                   preview, never the master document. --}}
              <div class="text-center p-4 border rounded" style="background:#f8f8f8;">
                @if($__derivUrl)
                  <img src="{{ $__derivUrl }}" alt="{{ $io->title }}" class="img-fluid img-thumbnail mb-2" style="max-height:{{ $vHeight ?? '480px' }};max-width:100%;">
                @else
                  <i class="fas fa-file-pdf fa-3x text-muted mb-2"></i>
                @endif
                <p class="text-muted small mb-0">{{ __('Log in to view or download the full document.') }}</p>
              </div>
            @else
              <div class="ratio" style="--bs-aspect-ratio: 85%;">
                <iframe src="{{ $pdfDisplayUrl }}" style="border:none;border-radius:8px;background:#525659;" title="{{ __('PDF Viewer') }}"></iframe>
              </div>
            @endif
          </div>
        </div>

      @elseif($masterMediaType === 'video')
        {{-- #106 Phase 2+4: shared Heratio video player component (the
             primary IO show-page video render). Replaces the inline
             <video controls> tag + sibling badge/download row. Uses
             $videoSrc / $videoMime so the streaming-fallback chain
             still works via $needsStreaming + $masterUrl. --}}
        @include('theme::components.media-player', [
            'type'           => 'video',
            'playerId'       => 'ahg-video-' . $io->id,
            'src'            => $videoSrc,
            'mime'           => $videoMime,
            'name'           => $masterObj->name ?? '',
            'masterUrl'      => $masterUrl,
            'masterMime'     => $masterMime,
            'byteSize'       => $masterObj->byte_size ?? null,
            'needsStreaming' => $needsStreaming,
            'showDownload'   => true,
            'poster'         => $thumbUrl ?? null,
        ])

      @elseif($masterMediaType === 'audio')
        {{-- Audio: Enhanced player with speed/skip controls (matching AtoM AhgMediaPlayer) --}}
        @php
          $audioSrc = $needsStreaming && $refObj ? $refUrl : $masterUrl;
          $audioMime = $needsStreaming && $refObj ? ($refObj->mime_type ?? 'audio/mpeg') : $masterMime;
          $audioPlayerId = 'ahg-audio-' . $io->id;
        @endphp
        {{-- Phase 1 of #106: shared Heratio media-player component, theme-owned.
             Markup + JS extracted into theme::components.media-player so future
             render sites (sector show pages, library show, museum show, etc.)
             can include the same chrome without duplicating the scrubber +
             control set. --}}
        @include('theme::components.media-player', [
            'type' => 'audio',
            'playerId' => $audioPlayerId,
            'src' => $audioSrc,
            'mime' => $audioMime,
            'name' => $masterObj->name ?? '',
            'masterUrl' => $masterUrl,
            'masterMime' => $masterMime,
            'byteSize' => $masterObj->byte_size ?? null,
            'needsStreaming' => $needsStreaming,
        ])

      @elseif($is3DModel)
        {{-- 3D Model viewer --}}
        @php
          $modelViewerId = 'model-3d-' . ($masterObj->id ?? uniqid());
          $modelExt = strtolower(pathinfo($masterObj->name ?? '', PATHINFO_EXTENSION));
          $isGlb = in_array($modelExt, ['glb', 'gltf']);
          // Check for a turntable MP4 alongside this GLB (rendered by ahg:3d-multiangle)
          $turntableMp4 = null;
          try {
            $turntableMp4 = \Illuminate\Support\Facades\DB::table('object_3d_model')
              ->where('object_id', $io->id)
              ->whereNotNull('turntable_mp4_path')
              ->orderByDesc('id')
              ->value('turntable_mp4_path');
          } catch (\Throwable $e) { /* ignore */ }
        @endphp
        <div class="digitalObject3D">
          <div class="d-flex flex-column align-items-center">
            <div class="mb-2">
              <span class="badge bg-primary"><i class="fas fa-cube me-1"></i>3D Model</span>
              <span class="badge bg-secondary">{{ \AhgCore\Support\GlobalSettings::displayFilename($masterObj->name) ?? '3D Model' }}</span>
              <span class="badge bg-info">{{ strtoupper($modelExt) }}</span>
              @if($turntableMp4)
                <span class="badge bg-dark"><i class="fas fa-video me-1"></i>{{ __('Turntable MP4') }}</span>
              @endif
            </div>

            @if($turntableMp4)
              <video src="{{ $turntableMp4 }}" autoplay muted loop playsinline
                     style="max-width:100%;max-height:380px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.2);margin-bottom:0.5rem;"
                     poster="">
                Your browser does not support video playback.
              </video>
              <div class="small text-muted mb-2">
                <i class="fas fa-video me-1"></i>{{ __('Auto-playing turntable preview') }} &middot;
                <a href="#" onclick="document.getElementById('{{ $modelViewerId }}-container').scrollIntoView({behavior:'smooth'});return false;">{{ __('view interactive 3D below') }}</a>
              </div>
            @endif


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
                  <i class="fas fa-spinner fa-spin fa-2x"></i><br><small>{{ __('Loading 3D model...') }}</small>
                </div>
              </div>
              <script type="importmap">
              { "imports": { "three": "/vendor/three/0.160.0/three.module.js", "three/addons/": "/vendor/three/0.160.0/addons/" } }
              </script>
              <script type="module" nonce="{{ csp_nonce() }}">
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
              <span>{{ __('Failed to load 3D model.') }}</span>
              <br><small class="text-muted">File: {{ \AhgCore\Support\GlobalSettings::displayFilename($masterObj->name) ?? 'Unknown' }}</small>
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

        {{-- 3D Model Fullscreen Modal --}}
        <div class="modal fade" id="model3d-fullscreen-modal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-fullscreen" style="max-width:100vw;margin:0;">
            <div class="modal-content" style="background:#1a1a2e;height:100vh;">
              <div class="modal-header" style="background:var(--ahg-primary);color:#fff;position:absolute;top:0;left:0;right:0;z-index:10;">
                <h5 class="modal-title"><i class="fas fa-cube me-2"></i>3D Model Viewer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
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
                  <i class="fas fa-download me-1"></i>{{ __('Download') }}
                </a>
              </div>
            </div>
          </div>
        </div>

        {{-- Load model-viewer (self-hosted under /vendor, no external CDN) + error handling --}}
        <script type="module" src="/vendor/model-viewer/3.3.0/model-viewer.min.js"></script>
        {{-- Self-hosted Draco + KTX2/Basis decoders so Draco-compressed meshes and
             KTX2-textured glTF/GLB render without an external decoder CDN. These are
             static on ModelViewerElement, so setting them once covers every <model-viewer>
             on the page (re-imports the already-cached module). --}}
        <script type="module" nonce="{{ csp_nonce() }}">
          import {ModelViewerElement} from '/vendor/model-viewer/3.3.0/model-viewer.min.js';
          ModelViewerElement.dracoDecoderLocation   = '/vendor/three/0.169.0/draco/';
          ModelViewerElement.ktx2TranscoderLocation = '/vendor/three/0.169.0/basis/';
        </script>
        <script nonce="{{ csp_nonce() }}">
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

      @elseif($isSplatMaster)
        {{-- heratio#1193 - Gaussian-splat master: the gs3d photoreal viewer is the renderer for
             this object (replaces the mesh branch). The id="ahg-splat-embed" marker also stops
             the InjectSplatViewer middleware from adding a second panel. Standard across GLAM+DAM. --}}
        <div id="ahg-splat-embed">
          <div class="d-flex justify-content-end mb-2">
            <a href="{{ preg_replace('/\?embed=1$/', '', $splatDoUrl) }}" target="_blank" rel="noopener" class="btn btn-sm atom-btn-white" title="{{ __('Open full screen') }}">
              <i class="fas fa-expand me-1"></i>{{ __('Full screen') }}
            </a>
          </div>
          <iframe title="{{ $io->title }} - 3D capture" src="{{ $splatDoUrl }}" loading="lazy"
            style="width:100%;height:{{ $vHeight ?? '500px' }};border:0;border-radius:8px;display:block;background:#0b0b0b" allow="fullscreen"></iframe>
        </div>

      @elseif($refUrl || $thumbUrl)
        {{-- Image: OpenSeadragon + Mirador + Carousel viewer (matching AtoM) --}}
        @php
          $viewerId = 'iiif-viewer-' . $io->id;
          // Public viewers get the derivative; authenticated viewers get the
          // master. Feeds the inline image, "Open full size" and fullscreen -
          // so none of them exposes the original to a not-logged-in visitor.
          $imgSrc = $__canSeeOriginal ? ($masterUrl ?: $refUrl) : $__derivUrl;
          // Load viewer settings from iiif_viewer_settings
          static $__vSettings = null;
          if ($__vSettings === null) {
              try {
                  $__vSettings = \Illuminate\Support\Facades\Schema::hasTable('iiif_viewer_settings')
                      ? \DB::table('iiif_viewer_settings')->pluck('setting_value', 'setting_key')->toArray()
                      : [];
              } catch (\Throwable $e) { $__vSettings = []; }
          }
          $vType = $__vSettings['viewer_type'] ?? 'openseadragon';
          // Per-IO override: ?viewer=carousel|single|mirador|openseadragon
          $__viewerOverride = request()->query('viewer');
          if (in_array($__viewerOverride, ['carousel', 'single', 'mirador', 'openseadragon'], true)) {
              $vType = $__viewerOverride;
          }
          // heratio#1396: deep zoom is impossible for an encrypted-at-rest
          // master (Cantaloupe 501s on the ciphertext), so never OPEN in a
          // deep-zoom mode - fall back to the plaintext reference image
          // instead of an empty viewer.
          if ($masterEncryptedAtRest && in_array($vType, ['openseadragon', 'mirador'], true)) {
              $vType = 'single';
          }
          $vHeight = $__vSettings['viewer_height'] ?? '500px';
          $vBg = $__vSettings['background_color'] ?? '#1a1a1a';
          $vAutoplay = (string)($__vSettings['carousel_autoplay'] ?? '1') === '1';
          $vInterval = (int)($__vSettings['carousel_interval'] ?? 5000);
          $vShowThumbs = (string)($__vSettings['carousel_show_thumbnails'] ?? '1') === '1';
          $vShowControls = (string)($__vSettings['carousel_show_controls'] ?? '1') === '1';

          // Carousel slides: this IO + direct children that have a reference/thumbnail digital object
          $carouselSlides = [];
          if ($vType === 'carousel') {
              $carouselSlides[] = [
                  'src'   => $refUrl ?: $thumbUrl,
                  'href'  => url('/' . ($io->slug ?? 'informationobject/' . $io->id)),
                  'title' => $io->title ?? '',
                  'id'    => $io->id,
              ];
              try {
                  $childIds = \DB::table('information_object')
                      ->where('parent_id', $io->id)
                      ->orderBy('lft')
                      ->limit(40)
                      ->pluck('id')->all();
                  foreach ($childIds as $cid) {
                      $childDOs = \AhgCore\Services\DigitalObjectService::getForObject($cid);
                      $childUrl = \AhgCore\Services\DigitalObjectService::getDisplayUrl($childDOs)
                          ?: \AhgCore\Services\DigitalObjectService::getThumbnailUrl($childDOs);
                      if (!$childUrl) continue;
                      $childRow = \DB::table('information_object as io')
                          ->leftJoin('information_object_i18n as i', function($j){ $j->on('i.id','=','io.id')->where('i.culture','en'); })
                          ->where('io.id', $cid)
                          ->select('io.id','io.slug','i.title')
                          ->first();
                      $carouselSlides[] = [
                          'src'   => $childUrl,
                          'href'  => url('/' . ($childRow->slug ?? 'informationobject/' . $cid)),
                          'title' => $childRow->title ?? '',
                          'id'    => $cid,
                      ];
                      if (count($carouselSlides) >= 20) break;
                  }
              } catch (\Throwable $e) { /* keep just the parent slide */ }
          }
        @endphp

        @php
          // heratio#1193 - "3D" viewer mode. Prefer a splat uploaded as a DIGITAL OBJECT on this
          // record ($splatDoUrl, set above); fall back to a splat linked from the legacy manager.
          $splatRow = \Illuminate\Support\Facades\Schema::hasTable('ahg_gaussian_splat')
            ? \Illuminate\Support\Facades\DB::table('ahg_gaussian_splat')
                ->where('information_object_id', $io->id)->where('status', 'ready')
                ->whereNotNull('file_name')->orderByDesc('id')->first()
            : null;
          $splatEmbedUrl = ($splatDoUrl ?? null) ?: ($splatRow ? '/splat/'.$splatRow->slug.'?embed=1' : null);
        @endphp

        @if($masterEncryptedAtRest)
          @auth
          {{-- heratio#1396: surface the IIIF incompatibility to staff instead of a silently blank deep-zoom pane --}}
          <div class="alert alert-warning py-2 mb-2 text-start" role="alert">
            <i class="fas fa-lock me-1" aria-hidden="true"></i>
            {{ __('The master file is encrypted at rest, so IIIF deep zoom (OpenSeadragon / Mirador) cannot render it. Showing the reference image instead.') }}
          </div>
          @endauth
        @endif

        {{-- Viewer toggle --}}
        <div class="d-flex justify-content-between align-items-center mb-2" style="position:relative;z-index:10;">
          <div class="btn-group btn-group-sm" role="group">
            <button id="btn-osd-{{ $viewerId }}" class="btn atom-btn-white {{ $vType === 'openseadragon' ? 'active' : '' }}" title="{{ __('OpenSeadragon Deep Zoom') }}">
              <i class="fas fa-search-plus me-1"></i>{{ __('Deep Zoom') }}
            </button>
            <button id="btn-mirador-{{ $viewerId }}" class="btn atom-btn-white {{ $vType === 'mirador' ? 'active' : '' }}" title="{{ __('Mirador IIIF Viewer') }}">
              <i class="fas fa-columns me-1"></i>{{ __('Mirador') }}
            </button>
            <button id="btn-img-{{ $viewerId }}" class="btn atom-btn-white {{ in_array($vType, ['single', 'carousel']) ? 'active' : '' }}" title="{{ __('Simple image') }}">
              <i class="fas fa-image me-1"></i>{{ __('Image') }}
            </button>
            @if($splatEmbedUrl)
            <button id="btn-splat-{{ $viewerId }}" class="btn atom-btn-white" title="{{ __('Photoreal 3D capture (Gaussian splat)') }}">
              <i class="fas fa-cube me-1"></i>{{ __('3D') }}
            </button>
            @endif
          </div>
          <div class="btn-group btn-group-sm">
            {{-- Hidden for a not-logged-in visitor when no derivative exists, so
                 Open-full-size / Fullscreen never fall back to the master. --}}
            @if($__canSeeOriginal || $imgSrc !== '')
              <a href="{{ $imgSrc }}" target="_blank" class="btn atom-btn-white" title="{{ $__canSeeOriginal ? __('Open full size') : __('Open reference copy') }}">
                <i class="fas fa-external-link-alt"></i>
              </a>
              <button id="btn-fs-{{ $viewerId }}" class="btn atom-btn-white" title="{{ __('Fullscreen') }}">
                <i class="fas fa-expand"></i>
              </button>
            @endif
          </div>
        </div>

        {{-- OSD container --}}
        <div id="osd-{{ $viewerId }}" style="position:relative;width:100%;height:{{ $vHeight }};background:{{ $vBg }};border-radius:8px;overflow:hidden;{{ $vType !== 'openseadragon' ? 'display:none;' : '' }}"></div>

        {{-- Mirador container --}}
        <div id="mirador-{{ $viewerId }}" style="position:relative;width:100%;height:{{ $vHeight }};border-radius:8px;overflow:hidden;{{ $vType !== 'mirador' ? 'display:none;' : '' }}"></div>

        {{-- Simple image (vertically centred within the viewer box, #1193) --}}
        <div id="img-{{ $viewerId }}" style="{{ $vType !== 'single' ? 'display:none;' : '' }}" class="text-center">
          <div style="display:flex;align-items:center;justify-content:center;height:{{ $vHeight }};">
            @if($imgSrc !== '')
              <a href="{{ $imgSrc }}" target="_blank">
                <img src="{{ $refUrl ?: $thumbUrl }}" alt="{{ $io->title }}" class="img-fluid img-thumbnail" style="max-height:{{ $vHeight }};max-width:100%;">
              </a>
            @else
              {{-- Public viewer, no derivative available: show the derivative
                   image (if any) but do not link through to the master. --}}
              <img src="{{ $refUrl ?: $thumbUrl }}" alt="{{ $io->title }}" class="img-fluid img-thumbnail" style="max-height:{{ $vHeight }};max-width:100%;">
            @endif
          </div>
        </div>

        @if($splatEmbedUrl)
          {{-- heratio#1193: Gaussian-splat capture, shown in the same media area when "3D" is picked --}}
          <div id="splat-{{ $viewerId }}" style="display:none;position:relative;width:100%;height:{{ $vHeight }};border-radius:8px;overflow:hidden;background:#0b0b0b;">
            <iframe id="splat-frame-{{ $viewerId }}" title="{{ $io->title }} - 3D capture" loading="lazy"
              style="width:100%;height:100%;border:0;display:block" allow="fullscreen" data-src="{{ $splatEmbedUrl }}"></iframe>
          </div>
        @endif

        {{-- Bootstrap 5 carousel (wired to iiif_viewer_settings: carousel_autoplay / carousel_interval / carousel_show_thumbnails / carousel_show_controls) --}}
        @if($vType === 'carousel')
          <div id="carousel-{{ $viewerId }}" class="carousel slide{{ $vAutoplay ? ' carousel-fade' : '' }}"
               style="background:{{ $vBg }};border-radius:8px;"
               data-bs-ride="{{ $vAutoplay ? 'carousel' : 'false' }}"
               data-bs-interval="{{ $vAutoplay ? $vInterval : 'false' }}"
               data-bs-pause="hover">
            @if($vShowThumbs && count($carouselSlides) > 1)
              <div class="carousel-indicators" style="position:relative;margin:0;padding:.5rem;background:rgba(0,0,0,.6);bottom:auto;">
                @foreach($carouselSlides as $i => $s)
                  <button type="button" data-bs-target="#carousel-{{ $viewerId }}" data-bs-slide-to="{{ $i }}"
                          class="{{ $i === 0 ? 'active' : '' }}"
                          style="width:auto;height:auto;background:none;border:2px solid {{ $i === 0 ? '#fff' : 'transparent' }};border-radius:4px;margin:2px;text-indent:0;opacity:1;"
                          aria-label="Slide {{ $i + 1 }}">
                    <img src="{{ $s['src'] }}" alt="" style="height:48px;width:auto;display:block;border-radius:2px;">
                  </button>
                @endforeach
              </div>
            @endif
            <div class="carousel-inner" style="border-radius:8px;">
              @foreach($carouselSlides as $i => $s)
                <div class="carousel-item {{ $i === 0 ? 'active' : '' }}" style="text-align:center;">
                  <a href="{{ $s['href'] }}">
                    <img src="{{ $s['src'] }}" alt="{{ $s['title'] }}" class="d-block mx-auto" style="max-height:{{ $vHeight }};max-width:100%;object-fit:contain;">
                  </a>
                  @if(!empty($s['title']))
                    <div class="carousel-caption d-none d-md-block" style="background:rgba(0,0,0,.55);border-radius:4px;padding:.25rem .5rem;bottom:.5rem;left:25%;right:25%;">
                      <small>{{ $s['title'] }}</small>
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
            @if($vShowControls && count($carouselSlides) > 1)
              <button class="carousel-control-prev" type="button" data-bs-target="#carousel-{{ $viewerId }}" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">{{ __('Previous') }}</span>
              </button>
              <button class="carousel-control-next" type="button" data-bs-target="#carousel-{{ $viewerId }}" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">{{ __('Next') }}</span>
              </button>
            @endif
          </div>
        @endif

        <script src="{{ asset('vendor/openseadragon/6.0.2/openseadragon.min.js') }}"></script>
        <script src="{{ asset('vendor/openseadragon/6.0.2/openseadragon-filtering.js') }}"></script>
        <script src="{{ asset('vendor/ahg-theme-b5/js/ahg-iiif-viewer.js') }}"></script>
        <script nonce="{{ csp_nonce() }}">
        document.addEventListener('DOMContentLoaded', function() {
          var __vid = '{{ $viewerId }}';
          var __imgUrl = '{{ url($imgSrc) }}';
          initIiifViewer(__vid, __imgUrl, {!! json_encode($io->title) !!}, '{{ $vType }}');

          // TIFF / no-Cantaloupe fallback: the OSD + Mirador deep-zoom modes need a
          // IIIF image server. When none is reachable (e.g. a box without
          // Cantaloupe), the deep-zoom viewer 404s on info.json; degrade to the
          // simple-image pane (the JPEG reference) so the image still renders.
          // Probes this image's info.json and only degrades on a hard failure, so
          // a working Cantaloupe is completely unaffected.
          @php $__fallbackJpg = $refUrl ?: $thumbUrl; @endphp
          @if($__fallbackJpg && in_array($vType, ['openseadragon', 'mirador']))
          (function () {
            var info;
            try {
              var rel = new URL(__imgUrl, window.location.origin).pathname.replace(/^\//, '');
              info = window.location.origin + '/iiif/3/' + rel.replace(/\//g, '_SL_') + '/info.json';
            } catch (e) { return; }
            function degradeToImage() {
              ['osd-', 'mirador-'].forEach(function (p) {
                var el = document.getElementById(p + __vid); if (el) { el.style.display = 'none'; }
              });
              var img = document.getElementById('img-' + __vid); if (img) { img.style.display = ''; }
              var bi = document.getElementById('btn-img-' + __vid); if (bi) { bi.classList.add('active'); }
              ['btn-osd-', 'btn-mirador-'].forEach(function (b) {
                var el = document.getElementById(b + __vid); if (el) { el.classList.remove('active'); }
              });
            }
            fetch(info, { method: 'GET' })
              .then(function (r) { if (!r.ok) { degradeToImage(); } })
              .catch(function () { degradeToImage(); });
          })();
          @endif
        });
        </script>
        @if($splatEmbedUrl)
        <script nonce="{{ $cspNonce ?? '' }}">
        document.addEventListener('DOMContentLoaded', function () {
          var vid = '{{ $viewerId }}';
          var splatBtn = document.getElementById('btn-splat-' + vid);
          var splatPane = document.getElementById('splat-' + vid);
          var frame = document.getElementById('splat-frame-' + vid);
          if (!splatBtn || !splatPane) { return; }
          var imageBtns = ['btn-osd-' + vid, 'btn-mirador-' + vid, 'btn-img-' + vid].map(function (id) { return document.getElementById(id); }).filter(Boolean);
          var imagePanes = ['osd-' + vid, 'mirador-' + vid, 'img-' + vid].map(function (id) { return document.getElementById(id); }).filter(Boolean);

          splatBtn.addEventListener('click', function () {
            if (frame && !frame.src) { frame.src = frame.getAttribute('data-src'); }   // lazy-load on first open
            imagePanes.forEach(function (p) { p.style.display = 'none'; });
            splatPane.style.display = 'block';
            imageBtns.forEach(function (b) { b.classList.remove('active'); });
            splatBtn.classList.add('active');
          });
          // Switching back to any image viewer hides the splat.
          imageBtns.forEach(function (b) {
            b.addEventListener('click', function () { splatPane.style.display = 'none'; splatBtn.classList.remove('active'); });
          });
        });
        </script>
        @endif
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
          <div>
            <span class="badge bg-primary"><i class="fas {{ $isSketchfab ? 'fa-cube' : 'fa-play' }} me-1"></i>{{ $embedLabel }}</span>
            <br><small class="text-muted"><a href="{{ $masterObj->path }}" target="_blank" class="text-muted text-decoration-none">{{ $masterObj->path }} <i class="fas fa-external-link-alt ms-1"></i></a></small>
          </div>
          <a href="{{ $masterObj->path }}" target="_blank" class="btn btn-sm atom-btn-white" title="{{ __('Open on original site') }}">
            <i class="fas fa-external-link-alt me-1"></i>View on {{ $isSketchfab ? 'Sketchfab' : 'original site' }}
          </a>
        </div>

        <iframe src="{{ $embedUrl }}" frameborder="0" allow="autoplay; fullscreen; xr-spatial-tracking"
                allowfullscreen mozallowfullscreen="true" webkitallowfullscreen="true"
                style="width:100%;height:500px;border-radius:8px;border:none;"></iframe>

      @else
        {{-- No displayable object: show download link --}}
        <div class="py-4">
          <i class="fas {{ $masterEncryptedAtRest ? 'fa-lock' : 'fa-file' }} fa-3x text-muted mb-3 d-block"></i>
          <p class="text-muted">{{ \AhgCore\Support\GlobalSettings::displayFilename($masterObj->name) ?? 'Digital object' }}</p>
          @if($masterEncryptedAtRest)
            {{-- heratio#1396: say WHY nothing renders instead of failing silently --}}
            <p class="text-muted mb-2"><small>{{ __('This master file is encrypted at rest, so it cannot be previewed or served through the IIIF deep-zoom pipeline.') }}</small></p>
          @endif
          @auth
            <a href="{{ $masterUrl }}" download class="btn atom-btn-white">
              <i class="fas fa-download me-1"></i>{{ __('Download file') }}
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
              <span><i class="fas fa-info-circle me-2"></i>{{ __('Media Information') }}</span>
              <span class="badge bg-{{ $doMediaType === 'audio' ? 'info' : 'primary' }}">
                {{ ucfirst($doMediaType) }}{{ $mediaMetadata->format ? ' - ' . strtoupper($mediaMetadata->format) : '' }}
              </span>
            </div>
            <div class="collapse" id="media-info-collapse">
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <h6 class="text-muted mb-2">{{ __('Technical Details') }}</h6>
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
                  <h6 class="text-muted mb-2">{{ __('Embedded Metadata') }}</h6>
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
              <button class="btn atom-btn-white" id="extract-btn-{{ $doId }}" data-action="extract" data-do-id="{{ $doId }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-magic me-1"></i>{{ __('Extract Metadata') }}</button>
            </div>
          </div>
          @endauth
        @endif

        {{-- Transcription panel. Issue #102: gated by media_transcription_enabled
             from /admin/ahgSettings/media. The panel + 'transcribe this' CTA both
             disappear when the operator has the toggle off, even when a row
             already exists in media_transcription. --}}
        @if(\App\Support\MediaSettings::transcriptionEnabled())
        @if($transcription)
          @php
            $segments = json_decode($transcription->segments ?? '[]', true) ?: [];
          @endphp
          <div class="card mb-3" id="transcription-panel-{{ $doId }}">
            <div class="card-header d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#transcription-collapse" aria-expanded="false" style="background:var(--ahg-primary);color:#fff">
              <span><i class="fas fa-file-alt me-2"></i>{{ __('Transcription') }}</span>
              <div class="btn-group btn-group-sm" onclick="event.stopPropagation();">
                <a href="/media/transcription/{{ $doId }}/vtt" class="btn atom-btn-white" title="{{ __('Download VTT') }}"><i class="fas fa-closed-captioning"></i> {{ __('VTT') }}</a>
                <a href="/media/transcription/{{ $doId }}/srt" class="btn atom-btn-white" title="{{ __('Download SRT') }}"><i class="fas fa-file-video"></i> {{ __('SRT') }}</a>
                @auth
                <button class="btn atom-btn-white" title="{{ __('Re-transcribe') }}" data-action="retranscribe" data-do-id="{{ $doId }}" data-lang="{{ $transcription->language ?? 'en' }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-redo"></i></button>
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
                <input type="text" class="form-control" id="transcript-search-{{ $doId }}" placeholder="{{ __('Search in transcript...') }}">
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
                <button class="btn atom-btn-white active" id="btn-text-{{ $doId }}"><i class="fas fa-align-left"></i> {{ __('Full Text') }}</button>
                <button class="btn atom-btn-white" id="btn-segments-{{ $doId }}"><i class="fas fa-list"></i> {{ __('Timed Segments') }}</button>
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
                <button class="btn atom-btn-white" data-action="transcribe" data-do-id="{{ $doId }}" data-lang="en" data-csrf="{{ csrf_token() }}"><i class="fas fa-language me-1"></i>{{ __('Transcribe (English)') }}</button>
                <button class="btn atom-btn-white" data-action="transcribe" data-do-id="{{ $doId }}" data-lang="af" data-csrf="{{ csrf_token() }}">{{ __('Afrikaans') }}</button>
              </div>
            </div>
          </div>
          @endauth
        @endif
        @endif {{-- /media_transcription_enabled (#102) --}}

        {{-- Snippets --}}
        @if($snippets->isNotEmpty())
          <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
              <span><i class="fas fa-cut me-2"></i>{{ __('Snippets') }}</span>
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
                  <button class="btn btn-sm atom-btn-white" onclick="var p=document.querySelector('audio,video');p&&(p.currentTime={{ $snippet->start_time }},p.play())" title="{{ __('Play snippet') }}">
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
            <i class="fas fa-cut me-1"></i>{{ __('Create Snippet') }}
          </button>
          <div id="snippet-form" style="display:none;" class="card mt-2">
            <div class="card-body">
              <div class="row g-2">
                <div class="col-md-4"><input type="text" class="form-control form-control-sm" id="snippet-title" placeholder="{{ __('Snippet title') }}"></div>
                <div class="col-md-2"><input type="number" class="form-control form-control-sm" id="snippet-start" placeholder="{{ __('Start (sec)') }}" step="0.1"></div>
                <div class="col-md-2"><input type="number" class="form-control form-control-sm" id="snippet-end" placeholder="{{ __('End (sec)') }}" step="0.1"></div>
                <div class="col-md-4"><input type="text" class="form-control form-control-sm" id="snippet-notes" placeholder="{{ __('Notes (optional)') }}"></div>
              </div>
              <div class="mt-2 d-flex gap-2">
                <button class="btn btn-sm atom-btn-white" onclick="var p=document.querySelector('audio,video');p&&(document.getElementById('snippet-start').value=p.currentTime.toFixed(1))">
                  <i class="fas fa-sign-in-alt"></i> {{ __('Mark IN') }}
                </button>
                <button class="btn btn-sm atom-btn-white" onclick="var p=document.querySelector('audio,video');p&&(document.getElementById('snippet-end').value=p.currentTime.toFixed(1))">
                  <i class="fas fa-sign-out-alt"></i> {{ __('Mark OUT') }}
                </button>
                <button class="btn atom-btn-outline-light btn-sm" data-action="save-snippet" data-do-id="{{ $doId }}" data-csrf="{{ csrf_token() }}"><i class="fas fa-save me-1"></i>{{ __('Save Snippet') }}</button>
              </div>
            </div>
          </div>
        </div>
        @endauth
      @endif

      {{-- Issue #746: image-typed DOs get an EXIF / IPTC / XMP panel. The
           partial is self-suppressing when all three sidecar tables are
           empty for this DO, so it is safe to include unconditionally for
           non-media types. --}}
      @if(!$isMediaFile)
        @includeIf('ahg-information-object-manage::partials._image-metadata-panel', ['do' => $digitalObjects['master']])
      @endif

    @endif
  @endif
