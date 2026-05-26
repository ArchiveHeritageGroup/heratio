<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>IIIF Viewer - {{ $objectTitle ?? '' }}</title>
<style>html, body { margin: 0; padding: 0; height: 100%; } #mirador-mount { width: 100%; height: calc(100vh - 120px); background: #1a1a1a; }</style>
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
Mirador.viewer({
  id: 'mirador-mount',
  windows: manifestUrl ? [{ manifestId: manifestUrl }] : [],
  window: { allowClose: true, allowMaximize: true, allowFullscreen: true }
});
</script>
</body>
</html>
