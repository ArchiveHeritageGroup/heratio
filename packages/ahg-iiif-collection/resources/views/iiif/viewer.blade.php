<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>IIIF Viewer - {{ $objectTitle ?? '' }}</title>
<style>html, body { margin: 0; padding: 0; height: 100%; } #iiif-viewer { width: 100%; height: 100vh; background: #1a1a1a; }</style>
</head>
<body>
<div class="container-fluid py-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    @if($objectSlug ?? null)<li class="breadcrumb-item"><a href="{{ route('informationobject.show', $objectSlug) }}">Record</a></li>@endif
    <li class="breadcrumb-item active">IIIF Viewer</li>
  </ol></nav>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">{{ $objectTitle ?? '' }}</h4>
    <div class="btn-group btn-group-sm">
      @if($objectSlug ?? null)<a href="{{ route('informationobject.show', $objectSlug) }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Record</a>@endif
      <a href="{{ $manifestUrl ?? '#' }}" class="btn btn-outline-info" target="_blank"><i class="fas fa-file-code me-1"></i>Manifest JSON</a>
    </div>
  </div>
  <div id="iiif-viewer"></div>
</div>
<script src="{{ asset('vendor/openseadragon/openseadragon.min.js') }}"></script>
<script>
var manifest = @json($manifestUrl ?? '');
OpenSeadragon({ id: 'iiif-viewer', tileSources: [manifest], prefixUrl: '/vendor/openseadragon/images/', showNavigator: true });
</script>
</body>
</html>
