@extends('theme::layouts.1col')

@section('title', $dataset->title)
@section('body-class', 'rdm datasets')

@section('content')
@php
  $statusLabel = optional($statuses->firstWhere('code', $dataset->status))->label ?? $dataset->status;
  $statusColor = optional($statuses->firstWhere('code', $dataset->status))->color ?? '#6c757d';
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 mb-0"><i class="fas fa-database me-2"></i>{{ $dataset->title }}</h1>
  <a href="{{ route('rdm.datasets.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('All datasets') }}</a>
</div>

@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card mb-3">
  <div class="card-body">
    <div class="row g-2 small">
      <div class="col-md-3"><span class="text-muted">{{ __('Status') }}:</span>
        <span class="badge" style="background:{{ $statusColor }}">{{ $statusLabel }}</span></div>
      <div class="col-md-4"><span class="text-muted">{{ __('Project') }}:</span> {{ $dataset->project_title ?? '—' }}</div>
      <div class="col-md-3"><span class="text-muted">{{ __('DOI') }}:</span> {{ $dataset->doi ?? '—' }}</div>
      <div class="col-md-2"><span class="text-muted">{{ __('Files') }}:</span> {{ $files->count() }}</div>
    </div>
    @if ($dataset->description)<p class="mt-2 mb-0">{{ $dataset->description }}</p>@endif
  </div>
</div>

<div class="card mb-3">
  <div class="card-header fw-bold">{{ __('Deposit files') }}</div>
  <div class="card-body">
    <form method="POST" action="{{ route('rdm.datasets.deposit', $dataset->id) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
      @csrf
      <div class="col-md-8">
        <label for="files" class="form-label">{{ __('Select files') }} <span class="text-muted small">({{ __('up to 256 MB each') }})</span></label>
        <input type="file" name="files[]" id="files" class="form-control" multiple required>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>{{ __('Deposit') }}</button>
      </div>
      <div class="form-text">{{ __('Files are stored through the standard ingest pipeline (digital objects). The POPIA scan runs on a later step.') }}</div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header fw-bold">{{ __('Deposited files') }}</div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0">
      <thead><tr><th>{{ __('File') }}</th><th>{{ __('Record') }}</th><th>{{ __('Digital object') }}</th></tr></thead>
      <tbody>
        @forelse ($files as $f)
          <tr>
            <td>{{ $f->original_name }}</td>
            <td class="small text-muted">IO #{{ $f->io_id }}</td>
            <td class="small text-muted">{{ $f->do_id ? 'DO #'.$f->do_id : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="3" class="text-center text-muted py-4">{{ __('No files deposited yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
