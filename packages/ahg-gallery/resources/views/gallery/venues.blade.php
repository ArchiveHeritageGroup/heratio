@extends('theme::layouts.1col')
@section('title', 'Gallery Venues')
@section('body-class', 'gallery venues')
@section('title-block')<h1 class="mb-0"><i class="fas fa-building me-2"></i>Venues</h1>@endsection
@section('content')
@auth<div class="mb-3"><a href="{{ route('gallery.venues.create') }}" class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>Create Venue</a></div>@endauth
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Venue Records</h5></div>
  <div class="card-body p-0">
    @if(isset($venues) && count($venues) > 0)
    <table class="table table-striped table-hover mb-0"><thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>Name</th><th>City</th><th>Type</th><th>Actions</th></tr></thead>
    <tbody>@foreach($venues as $v)<tr><td>{{ $v->name ?? '' }}</td><td>{{ $v->city ?? '-' }}</td><td>{{ ucfirst($v->venue_type ?? '') }}</td><td><a href="{{ route('gallery.venues.show', $v->id) }}" class="btn btn-sm atom-btn-white">View</a></td></tr>@endforeach</tbody></table>
    @else<div class="text-center py-4 text-muted">No venue records found.</div>@endif
  </div>
</div>
@endsection
