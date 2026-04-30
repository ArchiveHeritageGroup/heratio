@extends('theme::layouts.1col')
@section('title', 'Preview Report')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-search me-2"></i>Preview Report</h1>
      <a href="{{ route('reports.builder.index') }}" class="btn btn-sm atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
    <p class="text-muted">Preview report results before publishing.</p>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-search me-2"></i>Preview Report</div>
      <div class="card-body">
        @if(isset($report))
          <h5>{{ $report->name ?? 'Report' }}</h5>
          @if($report->description ?? null)
            <p class="text-muted">{{ $report->description }}</p>
          @endif
          <table class="table table-sm">
            <tr><th width="150">{{ __('ID') }}</th><td>{{ $report->id ?? '-' }}</td></tr>
            <tr><th>{{ __('Data Source') }}</th><td>{{ ucfirst($report->data_source ?? '-') }}</td></tr>
            <tr><th>{{ __('Category') }}</th><td>{{ $report->category ?? '-' }}</td></tr>
            <tr><th>{{ __('Status') }}</th><td><span class="badge bg-secondary">{{ ucfirst($report->status ?? 'draft') }}</span></td></tr>
            <tr><th>{{ __('Created') }}</th><td>{{ $report->created_at ?? '-' }}</td></tr>
            <tr><th>{{ __('Updated') }}</th><td>{{ $report->updated_at ?? '-' }}</td></tr>
          </table>
        @else
          <p class="text-muted text-center py-4">No data available.</p>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection