@extends('theme::layouts.1col')
@section('title', 'Report Templates')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-copy me-2"></i>Report Templates</h1>
      <a href="{{ route('reports.builder.index') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
    <p class="text-muted">Browse available report templates.</p>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-copy me-2"></i>Report Templates</div>
      <div class="card-body">
        @if(isset($report))
          <h5>{{ $report->name ?? 'Report' }}</h5>
          @if($report->description ?? null)
            <p class="text-muted">{{ $report->description }}</p>
          @endif
          <table class="table table-sm">
            <tr><th width="150">ID</th><td>{{ $report->id ?? '-' }}</td></tr>
            <tr><th>Data Source</th><td>{{ ucfirst($report->data_source ?? '-') }}</td></tr>
            <tr><th>Category</th><td>{{ $report->category ?? '-' }}</td></tr>
            <tr><th>Status</th><td><span class="badge bg-secondary">{{ ucfirst($report->status ?? 'draft') }}</span></td></tr>
            <tr><th>Created</th><td>{{ $report->created_at ?? '-' }}</td></tr>
            <tr><th>Updated</th><td>{{ $report->updated_at ?? '-' }}</td></tr>
          </table>
        @else
          <p class="text-muted text-center py-4">No data available.</p>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection