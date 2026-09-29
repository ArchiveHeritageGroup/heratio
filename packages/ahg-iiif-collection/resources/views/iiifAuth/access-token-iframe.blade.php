<!DOCTYPE html><html><head><title>{{ __('IIIF Auth Token') }}</title></head>
<body><script>
var tokenData = @json($tokenData ?? []);
var origin = @json($origin ?? '*');
if (window.parent && window.parent !== window) { window.parent.postMessage(tokenData, origin === '*' ? '*' : origin); }
</script></body></html>
