{{-- Block: Map (migrated from ahgLandingPagePlugin) --}}
@php
$title = $config['title'] ?? 'Our Locations';
$height = $config['height'] ?? '400px';
$zoom = $config['zoom'] ?? 10;
$locations = $data ?? [];
$mapId = 'map-' . uniqid();
@endphp

@if (!empty($title))
  <h2 class="h4 mb-4">{{ e($title) }}</h2>
@endif

@if (empty($locations))
  <p class="text-muted">No locations with coordinates available.</p>
@else
  <div id="{{ $mapId }}" style="height: {{ $height }};" class="rounded border"></div>

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <script nonce="{{ csp_nonce() }}">
  (function() {
      const locations = @json($locations);

      if (locations.length === 0) return;

      const lats = locations.map(l => parseFloat(l.latitude));
      const lngs = locations.map(l => parseFloat(l.longitude));
      const centerLat = lats.reduce((a, b) => a + b, 0) / lats.length;
      const centerLng = lngs.reduce((a, b) => a + b, 0) / lngs.length;

      const map = L.map('{{ $mapId }}').setView([centerLat, centerLng], {{ $zoom }});

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);

      locations.forEach(loc => {
          const marker = L.marker([loc.latitude, loc.longitude]).addTo(map);
          const popup = `
              <strong>${loc.name}</strong><br>
              ${loc.street_address || ''} ${loc.city || ''}<br>
              <a href="/repository/${loc.slug}">View Repository</a>
          `;
          marker.bindPopup(popup);
      });

      if (locations.length > 1) {
          const bounds = L.latLngBounds(locations.map(l => [l.latitude, l.longitude]));
          map.fitBounds(bounds, { padding: [20, 20] });
      }
  })();
  </script>
@endif
