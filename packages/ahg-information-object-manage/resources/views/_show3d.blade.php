@php /**
 * 3D Model Viewer Component - AHG Theme
 * Supports GLB, GLTF, OBJ, STL, PLY, USDZ
 */

$fullPath = $resource->path . $resource->name;
$ext = strtolower(pathinfo($resource->name, PATHINFO_EXTENSION));
$viewerId = 'viewer-' . uniqid(); @endphp

<div class="digitalObject3D">
  <div class="d-flex flex-column align-items-center">
    <div class="mb-2 d-flex flex-wrap gap-1 align-items-center justify-content-center">
      <span class="badge bg-primary"><i class="fas fa-cube me-1"></i>@php echo esc_entities($resource->name); @endphp (3D)</span>
      @php
        $isAiGenerated = false;
        try {
          if (isset($resource->object_id)) {
            $isAiGenerated = (bool) \Illuminate\Support\Facades\DB::table('object_3d_model')
              ->where('object_id', $resource->object_id)
              ->where(function ($q) {
                $q->where('original_filename', 'like', 'triposr_%')
                  ->orWhere('filename', 'like', 'triposr_%');
              })
              ->exists();
          }
        } catch (\Throwable $e) { /* ignore */ }
      @endphp
      @if($isAiGenerated)
        <span class="badge bg-warning text-dark" title="{{ __('Reconstructed by an AI model from a 2D source — geometry is approximate') }}">
          <i class="fas fa-flask me-1"></i>{{ __('AI-generated reconstruction') }}
        </span>
      @endif
    </div>
    
    <div id="@php echo $viewerId; @endphp-container" style="width: 100%; height: 400px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); border-radius: 8px; position: relative;">
      @if(in_array($ext, ['glb', 'gltf']))
        <script type="module" src="/plugins/ahgCorePlugin/web/js/vendor/model-viewer.min.js"></script>
        <model-viewer 
          id="@php echo $viewerId; @endphp"
          src="@php echo esc_entities($fullPath); @endphp" 
          camera-controls 
          touch-action="pan-y" 
          auto-rotate
          shadow-intensity="1"
          exposure="1"
          style="width:100%;height:100%;background:transparent;border-radius:8px;">
          <div slot="poster" class="d-flex flex-column align-items-center justify-content-center h-100 text-white">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <span>{{ __('Loading 3D model...') }}</span>
          </div>
        </model-viewer>
      @else
        <div id="@php echo $viewerId; @endphp-threejs" style="width:100%;height:100%;border-radius:8px;"></div>
        <script src="/plugins/ahg3DModelPlugin/web/vendor/threejs/three.min.js"></script>
        <script src="/plugins/ahg3DModelPlugin/web/vendor/threejs/OBJLoader.js"></script>
        <script src="/plugins/ahg3DModelPlugin/web/vendor/threejs/STLLoader.js"></script>
        <script src="/plugins/ahg3DModelPlugin/web/vendor/threejs/OrbitControls.js"></script>
        <script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
        (function() {
          const container = document.getElementById('@php echo $viewerId; @endphp-threejs');
          if (!container) return;
          const scene = new THREE.Scene();
          scene.background = new THREE.Color(0x1a1a2e);
          const camera = new THREE.PerspectiveCamera(45, container.clientWidth / container.clientHeight, 0.1, 1000);
          camera.position.set(0, 1, 3);
          const renderer = new THREE.WebGLRenderer({ antialias: true });
          renderer.setSize(container.clientWidth, container.clientHeight);
          renderer.setPixelRatio(window.devicePixelRatio);
          container.appendChild(renderer.domElement);
          const controls = new THREE.OrbitControls(camera, renderer.domElement);
          controls.enableDamping = true;
          controls.autoRotate = true;
          scene.add(new THREE.AmbientLight(0xffffff, 0.6));
          const dl = new THREE.DirectionalLight(0xffffff, 0.8);
          dl.position.set(5, 10, 7.5);
          scene.add(dl);
          
          function centerAndScale(obj) {
            const box = new THREE.Box3().setFromObject(obj);
            const center = box.getCenter(new THREE.Vector3());
            const size = box.getSize(new THREE.Vector3());
            const scale = 2 / Math.max(size.x, size.y, size.z);
            obj.scale.setScalar(scale);
            obj.position.sub(center.multiplyScalar(scale));
            obj.traverse(c => { if (c.isMesh) c.material = new THREE.MeshStandardMaterial({color:0xcccccc,roughness:0.5,metalness:0.3}); });
            scene.add(obj);
          }
          
          const ext = '@php echo $ext; @endphp';
          if (ext === 'obj') new THREE.OBJLoader().load('@php echo esc_entities($fullPath); @endphp', centerAndScale);
          else if (ext === 'stl') new THREE.STLLoader().load('@php echo esc_entities($fullPath); @endphp', g => centerAndScale(new THREE.Mesh(g)));
          
          (function animate() { requestAnimationFrame(animate); controls.update(); renderer.render(scene, camera); })();
          window.addEventListener('resize', () => { camera.aspect = container.clientWidth / container.clientHeight; camera.updateProjectionMatrix(); renderer.setSize(container.clientWidth, container.clientHeight); });
        })();
        </script>
      @endif
      
      <button onclick="open3DFullscreen('@php echo esc_entities($fullPath); @endphp', '@php echo $ext; @endphp')" class="btn btn-sm atom-btn-white position-absolute" style="bottom: 10px; right: 10px; z-index: 10;">
        <i class="fas fa-expand me-1"></i>{{ __('Fullscreen') }}
      </button>
    </div>
    
    <small class="text-muted mt-2">
      <i class="fas fa-mouse me-1"></i>{{ __('Drag to rotate') }} | <i class="fas fa-search-plus me-1"></i>{{ __('Scroll to zoom') }}
    </small>
    @if($isAiGenerated)
      <small class="text-muted mt-1 fst-italic" style="max-width:520px;text-align:center;">
        <i class="fas fa-info-circle me-1"></i>
        {{ __('This model was reconstructed by an AI from a 2D source image. Geometry is approximate and not authoritative — refer to the source image for accurate detail.') }}
      </small>
    @endif
  </div>
</div>
