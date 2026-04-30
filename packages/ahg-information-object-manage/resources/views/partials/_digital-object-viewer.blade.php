
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

        <script nonce="{{ csp_nonce() }}">
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
              <span class="badge bg-secondary">{{ $masterObj->name ?? '3D Model' }}</span>
              <span class="badge bg-info">{{ strtoupper($modelExt) }}</span>
              @if($turntableMp4)
                <span class="badge bg-dark"><i class="fas fa-video me-1"></i>Turntable MP4</span>
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
                  <i class="fas fa-spinner fa-spin fa-2x"></i><br><small>Loading 3D model...</small>
                </div>
              </div>
              <script type="importmap">
              { "imports": { "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js", "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/" } }
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
                  <i class="fas fa-download me-1"></i>Download
                </a>
              </div>
            </div>
          </div>
        </div>

        {{-- Load model-viewer from CDN and add error handling --}}
        <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js"></script>
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

      @elseif($refUrl || $thumbUrl)
        {{-- Image: OpenSeadragon + Mirador + Carousel viewer (matching AtoM) --}}
        @php
          $viewerId = 'iiif-viewer-' . $io->id;
          $imgSrc = $masterUrl ?: $refUrl;
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

        {{-- Viewer toggle --}}
        <div class="d-flex justify-content-between align-items-center mb-2" style="position:relative;z-index:10;">
          <div class="btn-group btn-group-sm" role="group">
            <button id="btn-osd-{{ $viewerId }}" class="btn atom-btn-white {{ $vType === 'openseadragon' ? 'active' : '' }}" title="{{ __('OpenSeadragon Deep Zoom') }}">
              <i class="fas fa-search-plus me-1"></i>Deep Zoom
            </button>
            <button id="btn-mirador-{{ $viewerId }}" class="btn atom-btn-white {{ $vType === 'mirador' ? 'active' : '' }}" title="{{ __('Mirador IIIF Viewer') }}">
              <i class="fas fa-columns me-1"></i>Mirador
            </button>
            <button id="btn-img-{{ $viewerId }}" class="btn atom-btn-white {{ in_array($vType, ['single', 'carousel']) ? 'active' : '' }}" title="{{ __('Simple image') }}">
              <i class="fas fa-image me-1"></i>Image
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
        <div id="osd-{{ $viewerId }}" style="position:relative;width:100%;height:{{ $vHeight }};background:{{ $vBg }};border-radius:8px;overflow:hidden;{{ $vType !== 'openseadragon' ? 'display:none;' : '' }}"></div>

        {{-- Mirador container --}}
        <div id="mirador-{{ $viewerId }}" style="position:relative;width:100%;height:{{ $vHeight }};border-radius:8px;overflow:hidden;{{ $vType !== 'mirador' ? 'display:none;' : '' }}"></div>

        {{-- Simple image --}}
        <div id="img-{{ $viewerId }}" style="{{ $vType !== 'single' ? 'display:none;' : '' }}" class="text-center">
          <a href="{{ $imgSrc }}" target="_blank">
            <img src="{{ $refUrl ?: $thumbUrl }}" alt="{{ $io->title }}" class="img-fluid img-thumbnail" style="max-height:{{ $vHeight }};">
          </a>
        </div>

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
                <span class="visually-hidden">Previous</span>
              </button>
              <button class="carousel-control-next" type="button" data-bs-target="#carousel-{{ $viewerId }}" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
              </button>
            @endif
          </div>
        @endif

        <script src="{{ asset('vendor/ahg-theme-b5/js/vendor/openseadragon.min.js') }}"></script>
        <script src="{{ asset('vendor/ahg-theme-b5/js/ahg-iiif-viewer.js') }}"></script>
        <script nonce="{{ csp_nonce() }}">
        document.addEventListener('DOMContentLoaded', function() {
          initIiifViewer('{{ $viewerId }}', '{{ url($imgSrc) }}', '{{ $io->title }}', '{{ $vType }}');
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
                <a href="/media/transcription/{{ $doId }}/vtt" class="btn atom-btn-white" title="{{ __('Download VTT') }}"><i class="fas fa-closed-captioning"></i> VTT</a>
                <a href="/media/transcription/{{ $doId }}/srt" class="btn atom-btn-white" title="{{ __('Download SRT') }}"><i class="fas fa-file-video"></i> SRT</a>
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
                <button class="btn atom-btn-white" data-action="transcribe" data-do-id="{{ $doId }}" data-lang="af" data-csrf="{{ csrf_token() }}">{{ __('Afrikaans') }}</button>
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
            <i class="fas fa-cut me-1"></i>Create Snippet
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
