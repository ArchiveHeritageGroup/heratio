<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>IIIF Viewer - {{ $objectTitle ?? '' }}</title>
<style>html, body { margin: 0; padding: 0; height: 100%; } #iiif-viewer { width: 100%; height: 100vh; background: #1a1a1a; }</style>
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
  <div id="iiif-viewer"></div>
</div>
<script src="{{ asset('vendor/openseadragon/6.0.2/openseadragon.min.js') }}"></script>
<script src="{{ asset('vendor/openseadragon/6.0.2/openseadragon-filtering.js') }}"></script>
<script src="{{ asset('vendor/openseadragon/6.0.2/openseadragon-heratio-scalebar.js') }}"></script>
<script src="{{ asset('vendor/openseadragon/6.0.2/openseadragon-heratio-magnifier.js') }}"></script>
<script>
var manifest = @json($manifestUrl ?? '');
var v = OpenSeadragon({ id: 'iiif-viewer', tileSources: [manifest], prefixUrl: '/vendor/openseadragon/6.0.2/images/', showNavigator: true, drawer: 'canvas' });
v.addHeratioScalebar({ position: 'BOTTOM_LEFT' });
var loupe = v.addHeratioMagnifier({ radius: 90, zoom: 3 });
// Toolbar toggle for the magnifier in the standalone viewer page.
var bar = document.createElement('div');
bar.style.cssText = 'position:absolute;top:8px;right:8px;z-index:1100;';
bar.innerHTML = '<button id="iiif-loupe-toggle" type="button" style="border:0;border-radius:4px;background:rgba(0,0,0,.65);color:#fff;cursor:pointer;padding:6px 10px;font-size:12px;"><i class="fas fa-search"></i> Magnifier</button>';
document.getElementById('iiif-viewer').appendChild(bar);
document.getElementById('iiif-loupe-toggle').addEventListener('click', function () {
  var on = loupe.toggle();
  this.style.background = on ? '#2c3e50' : 'rgba(0,0,0,.65)';
});
</script>
</body>
</html>
