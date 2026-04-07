{{-- Map Builder — cloned from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title) }}</a></li>
        <li class="breadcrumb-item active">Map</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Map Builder</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPointModal"><i class="fas fa-map-marker-alt me-1"></i> Add Point</button>
</div>

<div class="alert alert-info alert-dismissible fade show" id="mapClickHint">
    <i class="fas fa-info-circle me-1"></i> Click on the map to set coordinates for a new point, then fill in the form.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<div class="card mb-4">
    <div class="card-body p-0">
        <div id="map" style="width:100%; height:500px;"></div>
    </div>
</div>

{{-- Points table --}}
<div class="card">
    <div class="card-header"><h5 class="mb-0">Points ({{ count($points) }})</h5></div>
    <div class="card-body">
        @if(empty($points))
            <p class="text-muted">No map points yet.</p>
        @else
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Label</th><th>Place</th><th>Lat</th><th>Lng</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($points as $pt)
                    <tr>
                        <td>{{ e($pt->label ?? '') }}</td>
                        <td>{{ e($pt->place_name ?? '') }}</td>
                        <td>{{ $pt->latitude }}</td>
                        <td>{{ $pt->longitude }}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary edit-point-btn"
                                data-id="{{ $pt->id }}"
                                data-label="{{ e($pt->label ?? '') }}"
                                data-place="{{ e($pt->place_name ?? '') }}"
                                data-lat="{{ $pt->latitude }}"
                                data-lng="{{ $pt->longitude }}"
                                data-desc="{{ e($pt->description ?? '') }}"
                                title="Edit"><i class="fas fa-edit"></i></button>
                            <form method="post" action="{{ route('research.mapBuilder', $project->id) }}" class="d-inline" onsubmit="return confirm('Delete this point?')">
                                @csrf
                                <input type="hidden" name="form_action" value="delete_point">
                                <input type="hidden" name="point_id" value="{{ $pt->id }}">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Add Point Modal --}}
<div class="modal fade" id="addPointModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="{{ route('research.mapBuilder', $project->id) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Add Map Point</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Label <span class="text-danger">*</span></label><input type="text" name="label" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Place Name</label><input type="text" name="place_name" class="form-control"></div>
                    <div class="row mb-3">
                        <div class="col"><label class="form-label">Latitude <span class="text-danger">*</span></label><input type="number" step="any" name="latitude" id="pointLat" class="form-control" required></div>
                        <div class="col"><label class="form-label">Longitude <span class="text-danger">*</span></label><input type="number" step="any" name="longitude" id="pointLng" class="form-control" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
            </div>
        </form>
    </div>
</div>

{{-- Edit Point Modal --}}
<div class="modal fade" id="editPointModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="{{ route('research.mapBuilder', $project->id) }}">
            @csrf
            <input type="hidden" name="form_action" value="update_point">
            <input type="hidden" name="point_id" id="editPointId">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit Map Point</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Label <span class="text-danger">*</span></label><input type="text" name="label" id="editPointLabel" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Place Name</label><input type="text" name="place_name" id="editPointPlace" class="form-control"></div>
                    <div class="row mb-3">
                        <div class="col"><label class="form-label">Latitude <span class="text-danger">*</span></label><input type="number" step="any" name="latitude" id="editPointLat" class="form-control" required></div>
                        <div class="col"><label class="form-label">Longitude <span class="text-danger">*</span></label><input type="number" step="any" name="longitude" id="editPointLng" class="form-control" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="editPointDesc" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update</button></div>
            </div>
        </form>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var map = L.map('map').setView([0, 0], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution:'&copy; OpenStreetMap'}).addTo(map);

    function esc(s) { var d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; }

    @php
        $mapPoints = array_map(function($pt) {
            return ['id' => $pt->id, 'lat' => (float)$pt->latitude, 'lng' => (float)$pt->longitude, 'label' => $pt->label ?? '', 'place' => $pt->place_name ?? '', 'desc' => $pt->description ?? ''];
        }, $points);
    @endphp
    var points = {!! json_encode($mapPoints) !!};
    var bounds = [];

    points.forEach(function(pt) {
        var popup = '<strong>' + esc(pt.label) + '</strong>'
            + (pt.place ? '<br>' + esc(pt.place) : '');
        L.marker([pt.lat, pt.lng]).addTo(map).bindPopup(popup);
        bounds.push([pt.lat, pt.lng]);
    });
    if (bounds.length) map.fitBounds(bounds, {padding: [50, 50]});

    // Click on map to set coordinates and open Add modal
    map.on('click', function(e) {
        document.getElementById('pointLat').value = e.latlng.lat.toFixed(6);
        document.getElementById('pointLng').value = e.latlng.lng.toFixed(6);
        new bootstrap.Modal(document.getElementById('addPointModal')).show();
    });

    // Edit point — open modal with data
    document.querySelectorAll('.edit-point-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('editPointId').value = this.dataset.id;
            document.getElementById('editPointLabel').value = this.dataset.label;
            document.getElementById('editPointPlace').value = this.dataset.place;
            document.getElementById('editPointLat').value = this.dataset.lat;
            document.getElementById('editPointLng').value = this.dataset.lng;
            document.getElementById('editPointDesc').value = this.dataset.desc;
            new bootstrap.Modal(document.getElementById('editPointModal')).show();
        });
    });
});
</script>
@endsection
