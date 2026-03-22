@extends('theme::layouts.1col')
@section('title', '3D Reports - Thumbnails')
@section('body-class', 'admin three-d-reports thumbnails')
@section('title-block')<h1 class="mb-0"><i class="fas fa-cube me-2"></i>3D Thumbnails</h1>@endsection
@section('content')
<div class="card"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Thumbnails</h5></div>
<div class="card-body p-0">
  @if(isset($items) && count($items) > 0)
  <table class="table table-striped table-hover mb-0"><thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>ID</th><th>Name</th><th>Type</th><th>Status</th><th>Date</th></tr></thead>
  <tbody>@foreach($items as $item)<tr><td>{{ $item->id ?? '' }}</td><td>{{ $item->name ?? $item->title ?? '' }}</td><td>{{ $item->type ?? '-' }}</td><td>{{ ucfirst($item->status ?? '') }}</td><td>{{ $item->created_at ?? '' }}</td></tr>@endforeach</tbody></table>
  @else<div class="text-center py-4 text-muted">No records found.</div>@endif
</div></div>
<div class="mt-3"><a href="{{ route('iiif.three-d-reports.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to 3D Reports</a></div>
@endsection
