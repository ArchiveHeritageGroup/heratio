@extends('theme::layouts.1col')
@section('title', 'Media Processing Queue')
@section('body-class', 'admin media-settings queue')
@section('title-block')<h1 class="mb-0"><i class="fas fa-tasks me-2"></i>Media Processing Queue</h1>@endsection
@section('content')
<div class="card"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Queue Status</h5></div>
<div class="card-body">
  <div class="row text-center mb-4">
    <div class="col-md-3"><h3>{{ $stats['pending'] ?? 0 }}</h3><small class="text-muted">Pending</small></div>
    <div class="col-md-3"><h3 class="text-primary">{{ $stats['processing'] ?? 0 }}</h3><small class="text-muted">Processing</small></div>
    <div class="col-md-3"><h3 class="text-success">{{ $stats['completed'] ?? 0 }}</h3><small class="text-muted">Completed</small></div>
    <div class="col-md-3"><h3 class="text-danger">{{ $stats['failed'] ?? 0 }}</h3><small class="text-muted">Failed</small></div>
  </div>
  @if(isset($jobs) && count($jobs) > 0)
  <table class="table table-striped"><thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>Object</th><th>Type</th><th>Status</th><th>Created</th></tr></thead>
  <tbody>@foreach($jobs as $j)<tr><td>#{{ $j->object_id ?? '' }}</td><td>{{ $j->job_type ?? '' }}</td><td><span class="badge bg-{{ ($j->status ?? '') === 'completed' ? 'success' : (($j->status ?? '') === 'failed' ? 'danger' : 'info') }}">{{ ucfirst($j->status ?? '') }}</span></td><td>{{ $j->created_at ?? '' }}</td></tr>@endforeach</tbody></table>
  @endif
</div></div>
@endsection
