{{-- Map Builder - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'Map Builder')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Map Builder</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-map-marked-alt text-primary me-2"></i>Map Builder</h1>
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4"><div class="card-body p-0">
            <div id="mapContainer" style="width:100%;height:500px;background:#e8e8e8;border-radius:0.375rem;display:flex;align-items:center;justify-content:center;">
                <div class="text-muted"><i class="fas fa-map fa-3x mb-2 opacity-50"></i><p>Map will render here</p></div>
            </div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card mb-4"><div class="card-header"><h6 class="mb-0">Map Layers</h6></div>
            <ul class="list-group list-group-flush">
                @foreach($layers ?? [] as $layer)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-layer-group me-2"></i>{{ e($layer->name ?? '') }}</span>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" checked></div>
                </li>
                @endforeach
                @if(empty($layers))<li class="list-group-item text-muted small">No layers added.</li>@endif
            </ul>
        </div>
        <div class="card"><div class="card-header"><h6 class="mb-0">Add Marker</h6></div><div class="card-body">
            <form method="POST">@csrf
                <div class="mb-2"><input type="text" name="label" class="form-control form-control-sm" placeholder="Label" required></div>
                <div class="row mb-2"><div class="col"><input type="number" step="any" name="lat" class="form-control form-control-sm" placeholder="Latitude"></div><div class="col"><input type="number" step="any" name="lng" class="form-control form-control-sm" placeholder="Longitude"></div></div>
                <div class="mb-2"><textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Description"></textarea></div>
                <button type="submit" class="btn btn-sm atom-btn-white w-100"><i class="fas fa-map-pin me-1"></i>Add Marker</button>
            </form>
        </div></div>
    </div>
</div>
@endsection