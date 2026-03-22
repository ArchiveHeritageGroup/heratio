<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IIIF Comparison Viewer</title>
<style>html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; background: #1e1e1e; } #mirador-compare { position: absolute; top: 0; left: 0; right: 0; bottom: 0; }</style>
</head>
<body>
<div id="mirador-compare"></div>
<script src="{{ asset('vendor/mirador/mirador.min.js') }}"></script>
<script>
var manifests = @json($manifests ?? []);
var windows = manifests.map(function(url) { return { manifestId: url, canvasIndex: 0 }; });
Mirador.viewer({ id: 'mirador-compare', windows: windows, window: { allowClose: true, allowMaximize: true, allowFullscreen: true } });
</script>
</body>
</html>
