@extends('theme::layouts.1col')
@section('title', 'RiC Places')
@section('body-class', 'admin ric')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>RiC Places</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('ric.entities.create', ['places']) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Create Place</a>
        <a href="{{ route('ric.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> RiC Dashboard</a>
    </div>
</div>
<form method="get" class="mb-3">
    <div class="input-group input-group-sm" style="max-width:400px">
        <input type="text" name="search" class="form-control" placeholder="Search places..." value="{{ $params['search'] ?? '' }}">
        <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
    </div>
</form>
<table class="table table-sm table-striped table-hover">
    <thead><tr><th>Name</th><th>Type</th><th>Coordinates</th><th>Authority</th><th>Created</th></tr></thead>
    <tbody>
        @forelse($result->items as $item)
        <tr>
            <td><a href="{{ route('ric.entities.show', ['places', $item->slug]) }}">{{ $item->name ?? 'Unnamed' }}</a></td>
            <td><span class="badge bg-success">{{ $item->type_id ?? '' }}</span></td>
            <td>{{ $item->latitude && $item->longitude ? $item->latitude . ', ' . $item->longitude : '' }}</td>
            <td>@if($item->authority_uri)<a href="{{ $item->authority_uri }}" target="_blank"><i class="fas fa-external-link-alt"></i></a>@endif</td>
            <td>{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('Y-m-d') : '' }}</td>
        </tr>
        @empty
        <tr><td colspan="5" class="text-muted">No places found</td></tr>
        @endforelse
    </tbody>
</table>
@if($result->last_page > 1)
<nav><ul class="pagination pagination-sm">
    @for($p = 1; $p <= $result->last_page; $p++)
    <li class="page-item {{ $p == $result->page ? 'active' : '' }}"><a class="page-link" href="?page={{ $p }}&search={{ $params['search'] ?? '' }}">{{ $p }}</a></li>
    @endfor
</ul></nav>
@endif
<p class="text-muted small">{{ $result->total }} place{{ $result->total !== 1 ? 's' : '' }} total</p>
@endsection
