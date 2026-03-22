@if(!defined('_3D_FULLSCREEN_MODAL_RENDERED'))
@php define('_3D_FULLSCREEN_MODAL_RENDERED', true); @endphp
<div id="fullscreen-3d-modal" class="position-fixed top-0 start-0 w-100 h-100 d-none" style="z-index: 9999; background: rgba(0,0,0,0.95);">
  <div class="position-absolute top-0 start-0 w-100 p-3 d-flex justify-content-between align-items-center" style="z-index: 10001; background: linear-gradient(180deg, rgba(0,0,0,0.8) 0%, transparent 100%);">
    <div class="text-white">
      <h5 class="mb-0"><i class="fas fa-cube me-2"></i>{{ __('3D Model Viewer') }}</h5>
    </div>
    <div>
      <button class="btn btn-outline-light btn-sm me-2" onclick="toggle3DAutoRotate()"><i class="fas fa-redo"></i></button>
      <button class="btn btn-light btn-sm" onclick="close3DFullscreen()"><i class="fas fa-times me-1"></i>{{ __('Close') }}</button>
    </div>
  </div>
  <div id="fullscreen-3d-container" class="w-100 h-100"></div>
  <div class="position-absolute bottom-0 start-0 w-100 p-3 text-center" style="z-index: 10001; background: linear-gradient(0deg, rgba(0,0,0,0.8) 0%, transparent 100%);">
    <small class="text-white-50">
      <i class="fas fa-mouse me-2"></i>{{ __('Drag to rotate') }} | <i class="fas fa-search-plus me-2"></i>{{ __('Scroll to zoom') }} | <kbd>ESC</kbd> {{ __('to close') }}
    </small>
  </div>
</div>
<script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
let fs3D = { controls: null, renderer: null };
function open3DFullscreen(modelPath, ext) {
  const modal = document.getElementById('fullscreen-3d-modal');
  const container = document.getElementById('fullscreen-3d-container');
  modal.classList.remove('d-none');
  document.body.style.overflow = 'hidden';
  container.innerHTML = '';
  if (ext === 'glb' || ext === 'gltf') {
    container.innerHTML = '<model-viewer id="fs-model-viewer" src="' + modelPath + '" camera-controls touch-action="pan-y" auto-rotate shadow-intensity="1" exposure="1" style="width:100%;height:100%;background:transparent;"></model-viewer>';
  } else {
    initFullscreenThreeJs(container, modelPath, ext);
  }
}
function close3DFullscreen() {
  document.getElementById('fullscreen-3d-modal').classList.add('d-none');
  document.body.style.overflow = '';
  if (fs3D.renderer) { fs3D.renderer.dispose(); fs3D.renderer = null; }
}
function toggle3DAutoRotate() {
  const mv = document.getElementById('fs-model-viewer');
  if (mv) mv.autoRotate = !mv.autoRotate;
  if (fs3D.controls) fs3D.controls.autoRotate = !fs3D.controls.autoRotate;
}
function _doInitFullscreenThreeJs(container, modelPath, ext) {
  const scene = new THREE.Scene();
  scene.background = new THREE.Color(0x1a1a2e);
  const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
  camera.position.set(0, 1, 3);
  fs3D.renderer = new THREE.WebGLRenderer({ antialias: true });
  fs3D.renderer.setSize(window.innerWidth, window.innerHeight);
  container.appendChild(fs3D.renderer.domElement);
  fs3D.controls = new THREE.OrbitControls(camera, fs3D.renderer.domElement);
  fs3D.controls.enableDamping = true;
  fs3D.controls.autoRotate = true;
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
  if (ext === 'obj') new THREE.OBJLoader().load(modelPath, centerAndScale);
  else if (ext === 'stl') new THREE.STLLoader().load(modelPath, g => centerAndScale(new THREE.Mesh(g)));
  (function animate() { if (!fs3D.renderer) return; requestAnimationFrame(animate); fs3D.controls.update(); fs3D.renderer.render(scene, camera); })();
}
function initFullscreenThreeJs(container, modelPath, ext) {
  if (typeof THREE !== 'undefined' && THREE.OBJLoader && THREE.OrbitControls) {
    _doInitFullscreenThreeJs(container, modelPath, ext);
    return;
  }
  var local = '/plugins/ahg3DModelPlugin/web/vendor/threejs';
  var srcs = [local+'/three.min.js', local+'/OBJLoader.js', local+'/STLLoader.js', local+'/OrbitControls.js'];
  var idx = 0;
  if (typeof THREE !== 'undefined') { idx = 1; }
  function loadNext() {
    if (idx >= srcs.length) { _doInitFullscreenThreeJs(container, modelPath, ext); return; }
    var s = document.createElement('script'); s.src = srcs[idx]; idx++;
    s.onload = loadNext; s.onerror = loadNext;
    document.head.appendChild(s);
  }
  loadNext();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') close3DFullscreen(); });
</script>
@endif
