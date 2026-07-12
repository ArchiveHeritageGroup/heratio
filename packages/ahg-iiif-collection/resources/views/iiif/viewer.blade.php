<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>IIIF Viewer - {{ $objectTitle ?? '' }}</title>
<style>html, body { margin: 0; padding: 0; height: 100%; } #mirador-mount { position: relative; width: 100%; height: calc(100vh - 120px); background: #1a1a1a; }</style>
</head>
<body>
<div class="container-fluid py-3">
  <nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb">
    @if($objectSlug ?? null)<li class="breadcrumb-item"><a href="{{ route('informationobject.show', $objectSlug) }}">Record</a></li>@endif
    <li class="breadcrumb-item active">IIIF Viewer</li>
  </ol></nav>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">{{ $objectTitle ?? '' }}</h4>
    <div class="btn-group btn-group-sm">
      @if($objectSlug ?? null)<a href="{{ route('informationobject.show', $objectSlug) }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Record') }}</a>@endif
      <a href="{{ $manifestUrl ?? '#' }}" class="btn btn-outline-info" target="_blank"><i class="fas fa-file-code me-1"></i>{{ __('Manifest JSON') }}</a>
    </div>
  </div>
  <div id="mirador-mount"></div>
</div>
<script src="{{ asset('vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js') }}"></script>
<script>
// The /iiif-viewer/{slug} route is Heratio's canonical Mirador surface.
// The IO show page keeps OpenSeadragon for single-image deep zoom; this
// page mounts the full Mirador workspace so the bundle-compiled plugins
// (heratio-av-overlay, heratio-compare-overlay, heratio-mirador-workspace,
// heratio-scalebar, heratio-loupe) are reachable.
var manifestUrl = @json($manifestUrl ?? '');
var miradorConfig = {
  id: 'mirador-mount',
  windows: manifestUrl ? [{ manifestId: manifestUrl }] : [],
  window: { allowClose: true, allowMaximize: true, allowFullscreen: true }
};
// The bundle-compiled annotation plugin (mirador-annotation-editor, wired
// in tools/mirador-build/src/index.js) dereferences config.annotation.adapter
// unconditionally during init, so the annotation block must always be
// defined (#1397). Mirror the adapter wiring from ahg-iiif-viewer.js —
// HeratioAnnotationAdapter is exposed on window by the same bundle; fall
// back to a no-op adapter if a stale bundle ever omits it.
miradorConfig.annotation = {
  adapter: function (canvasId) {
    if (typeof window.HeratioAnnotationAdapter === 'function') {
      return new window.HeratioAnnotationAdapter(canvasId);
    }
    return {
      canvasId: canvasId,
      annotationPageId: window.location.origin + '/api/annotations/page/' + encodeURIComponent(canvasId),
      all: function () { return Promise.resolve({ id: this.annotationPageId, type: 'AnnotationPage', items: [] }); },
      create: function (annotation) { return Promise.resolve(annotation); },
      update: function (annotation) { return Promise.resolve(annotation); },
      delete: function () { return Promise.resolve(); }
    };
  },
  exportLocalStorageAnnotations: false
};
Mirador.viewer(miradorConfig);
</script>
</body>
</html>
