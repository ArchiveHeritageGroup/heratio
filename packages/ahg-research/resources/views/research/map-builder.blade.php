{{-- Map Builder --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Map Builder')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Map Builder</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-map-marked-alt text-primary me-2"></i>Map Builder</h1>
    <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="card mb-4">
    <div class="card-body p-0">
        <div id="map-container" style="width:100%; height:500px; border-radius:0.375rem;"></div>
    </div>
</div>

{{-- Add point form --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Add Point</h6></div>
    <div class="card-body">
        <form method="POST">
            @csrf
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Latitude <span class="text-danger">*</span></label>
                    <input type="number" step="any" name="latitude" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Longitude <span class="text-danger">*</span></label>
                    <input type="number" step="any" name="longitude" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Point Type</label>
                    <select name="point_type" class="form-select">
                        <option value="location">Location</option>
                        <option value="event">Event</option>
                        <option value="residence">Residence</option>
                        <option value="landmark">Landmark</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-map-pin me-1"></i>Add</button>
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
        </form>
    </div>
</div>

{{-- Points table --}}
<div class="card">
    <div class="card-header"><h6 class="mb-0">Points</h6></div>
    <div class="card-body p-0">
        @if(!empty($points) && count($points) > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($points as $p)
                    <tr>
                        <td><strong>{{ e($p->title ?? '') }}</strong></td>
                        <td>{{ e(Str::limit($p->description ?? '', 60)) }}</td>
                        <td>{{ $p->latitude ?? '' }}</td>
                        <td>{{ $p->longitude ?? '' }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst($p->point_type ?? 'location') }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-4 text-muted">
            <p>No points added yet.</p>
        </div>
        @endif
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var map = L.map('map-container').setView([-25.7479, 28.2293], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var points = @json($points ?? []);
    points.forEach(function(p) {
        if (p.latitude && p.longitude) {
            L.marker([p.latitude, p.longitude])
                .addTo(map)
                .bindPopup('<strong>' + (p.title || '') + '</strong>' + (p.description ? '<br>' + p.description : ''));
        }
    });

    if (points.length > 0) {
        var bounds = points.filter(function(p) { return p.latitude && p.longitude; })
            .map(function(p) { return [p.latitude, p.longitude]; });
        if (bounds.length > 0) map.fitBounds(bounds, { padding: [30, 30] });
    }
});
</script>
@endsection
