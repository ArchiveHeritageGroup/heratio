@extends('theme::layouts.1col')
@section('title', 'Gallery Dashboard')
@section('body-class', 'gallery dashboard')
@section('title-block')
  <h1 class="mb-0"><i class="fas fa-palette me-2"></i>Gallery Management</h1>
  <span class="small text-muted">Manage artwork and gallery items using CCO cataloguing standards</span>
@endsection
@section('content')
<div class="row mb-4">
  <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body"><h6 class="card-title mb-0">Total Items</h6><h2 class="mb-0">{{ number_format($totalItems ?? 0) }}</h2></div></div></div>
  <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body"><h6 class="card-title mb-0">With Media</h6><h2 class="mb-0">{{ number_format($itemsWithMedia ?? 0) }}</h2></div></div></div>
  <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body"><h6 class="card-title mb-0">Artists</h6><h2 class="mb-0">{{ number_format($totalArtists ?? 0) }}</h2></div></div></div>
  <div class="col-md-3"><div class="card bg-warning text-white"><div class="card-body"><h6 class="card-title mb-0">Active Loans</h6><h2 class="mb-0">{{ number_format($activeLoans ?? 0) }}</h2></div></div></div>
</div>
<div class="row">
  <div class="col-md-4 mb-3"><a href="{{ route('gallery.browse') }}" class="btn atom-btn-white w-100"><i class="fas fa-images me-1"></i>Browse Gallery</a></div>
  <div class="col-md-4 mb-3"><a href="{{ route('gallery.create') }}" class="btn atom-btn-white w-100"><i class="fas fa-plus me-1"></i>Add Artwork</a></div>
  <div class="col-md-4 mb-3"><a href="{{ route('gallery.artists') }}" class="btn atom-btn-white w-100"><i class="fas fa-user me-1"></i>Artists</a></div>
  <div class="col-md-4 mb-3"><a href="{{ route('gallery.loans') }}" class="btn atom-btn-white w-100"><i class="fas fa-exchange-alt me-1"></i>Loans</a></div>
  <div class="col-md-4 mb-3"><a href="{{ route('gallery.valuations') }}" class="btn atom-btn-white w-100"><i class="fas fa-coins me-1"></i>Valuations</a></div>
  <div class="col-md-4 mb-3"><a href="{{ route('gallery.venues') }}" class="btn atom-btn-white w-100"><i class="fas fa-building me-1"></i>Venues</a></div>
</div>
@if(isset($recentItems) && count($recentItems) > 0)
<div class="card mt-3">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Recently Added</h5></div>
  <div class="card-body p-0">
    <table class="table table-striped mb-0"><thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>Title</th><th>Artist</th><th>Date</th></tr></thead>
    <tbody>@foreach($recentItems as $item)<tr><td>{{ $item->title ?? 'Untitled' }}</td><td>{{ $item->creator_identity ?? '-' }}</td><td>{{ $item->created_at ?? '' }}</td></tr>@endforeach</tbody></table>
  </div>
</div>
@endif
@endsection
