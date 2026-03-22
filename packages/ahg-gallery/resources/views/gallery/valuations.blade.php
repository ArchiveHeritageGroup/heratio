@extends('theme::layouts.1col')
@section('title', 'Gallery Valuations')
@section('body-class', 'gallery valuations')
@section('title-block')<h1 class="mb-0"><i class="fas fa-coins me-2"></i>Valuations</h1>@endsection
@section('content')
@auth<div class="mb-3"><a href="{{ route('gallery.valuations.create') }}" class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>Create Valuation</a></div>@endauth
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Valuation Records</h5></div>
  <div class="card-body p-0">
    @if(isset($valuations) && count($valuations) > 0)
    <table class="table table-striped table-hover mb-0"><thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>Artwork</th><th>Type</th><th>Value</th><th>Date</th><th>Appraiser</th><th>Actions</th></tr></thead>
    <tbody>@foreach($valuations as $v)<tr><td>{{ $v->artwork_title ?? '' }}</td><td>{{ ucfirst(str_replace('_', ' ', $v->valuation_type ?? '')) }}</td><td>R {{ number_format($v->value ?? 0, 2) }}</td><td>{{ $v->valuation_date ?? '-' }}</td><td>{{ $v->appraiser ?? '-' }}</td><td><a href="{{ route('gallery.valuations.show', $v->id ?? 0) }}" class="btn btn-sm atom-btn-white">View</a></td></tr>@endforeach</tbody></table>
    @else<div class="text-center py-4 text-muted">No valuation records found.</div>@endif
  </div>
</div>
@endsection
