@extends('theme::layouts.1col')

@section('title', $dataset->title)
@section('body-class', 'rdm datasets')

@section('content')
@php
  $statusLabel = optional($statuses->firstWhere('code', $dataset->status))->label ?? $dataset->status;
  $statusColor = optional($statuses->firstWhere('code', $dataset->status))->color ?? '#6c757d';
  $verdictColors = ['CLEAR' => '#198754', 'PERSONAL' => '#fd7e14', 'SPECIAL_CATEGORY' => '#dc3545'];
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 mb-0"><i class="fas fa-database me-2"></i>{{ $dataset->title }}</h1>
  <div class="d-flex gap-2">
    @if ($files->count() > 0)
      <form method="POST" action="{{ route('rdm.datasets.scan', $dataset->id) }}">
        @csrf
        <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-user-shield me-1"></i>{{ __('Run POPIA scan') }}</button>
      </form>
    @endif
    <a href="{{ route('rdm.datasets.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('All datasets') }}</a>
  </div>
</div>

@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@if ($dataset->status === 'scanning')
  <div class="alert alert-info d-flex align-items-center">
    <span class="spinner-border spinner-border-sm me-2"></span>
    {{ __('POPIA scan in progress — reload this page in a moment to see the findings.') }}
  </div>
@endif

<div class="card mb-3">
  <div class="card-body">
    <div class="row g-2 small">
      <div class="col-md-3"><span class="text-muted">{{ __('Status') }}:</span>
        <span class="badge" style="background:{{ $statusColor }}">{{ $statusLabel }}</span></div>
      <div class="col-md-4"><span class="text-muted">{{ __('Project') }}:</span> {{ $dataset->project_title ?? '—' }}</div>
      <div class="col-md-3"><span class="text-muted">{{ __('POPIA verdict') }}:</span>
        @if ($dataset->verdict)
          <span class="badge" style="background:{{ $verdictColors[$dataset->verdict] ?? '#6c757d' }}">{{ $dataset->verdict }}</span>
        @else <span class="text-muted">{{ __('not scanned') }}</span> @endif
      </div>
      <div class="col-md-2"><span class="text-muted">{{ __('Files') }}:</span> {{ $files->count() }}</div>
    </div>
    @if ($dataset->description)<p class="mt-2 mb-0">{{ $dataset->description }}</p>@endif
  </div>
</div>

@if ($dataset->verdict)
{{-- Human gate (#1340): disposition + the open-access block. --}}
<div class="card mb-3 border-warning">
  <div class="card-header fw-bold bg-warning-subtle"><i class="fas fa-gavel me-1"></i>{{ __('Human gate — disposition') }}</div>
  <div class="card-body">
    <p class="small mb-2">
      {{ __('Unresolved findings') }}: <strong>{{ $gate['pending'] }}</strong> ·
      {{ __('confirmed PII') }}: <strong>{{ $gate['confirmed_pii'] }}</strong> ·
      {{ __('dismissed') }}: <strong>{{ $gate['dismissed'] }}</strong>
    </p>
    @if ($dataset->disposition)
      <p class="mb-2"><span class="text-muted">{{ __('Current disposition') }}:</span>
        <span class="badge bg-dark">{{ $dataset->disposition }}</span></p>
    @endif
    @unless ($gate['can_release'])
      <div class="alert alert-warning py-2 small mb-2"><i class="fas fa-lock me-1"></i>{{ __('Open release is blocked until every PERSONAL/SPECIAL finding is resolved and none remain confirmed as PII. You can still restrict, embargo, or de-identify.') }}</div>
    @endunless
    <form method="POST" action="{{ route('rdm.datasets.disposition', $dataset->id) }}" class="row g-2 align-items-end">
      @csrf
      <div class="col-auto">
        <label for="disposition" class="form-label small mb-0">{{ __('Apply disposition') }}</label>
        <select name="disposition" id="disposition" class="form-select form-select-sm">
          @foreach ($dispositions as $d)
            <option value="{{ $d->code }}" @disabled($d->code === 'release' && ! $gate['can_release'])>{{ $d->label }}@if ($d->code === 'release' && ! $gate['can_release']) — {{ __('blocked') }}@endif</option>
          @endforeach
        </select>
      </div>
      <div class="col-auto"><button type="submit" class="btn btn-sm btn-dark">{{ __('Apply') }}</button></div>
    </form>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header fw-bold d-flex justify-content-between">
    <span><i class="fas fa-user-shield me-1"></i>{{ __('POPIA scan findings') }}</span>
    <span class="small text-muted">{{ __('AI/regex findings are suggestions — confirm or dismiss each (provenance is recorded)') }}</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0 align-middle">
      <thead><tr>
        <th>{{ __('File') }}</th><th>{{ __('Type') }}</th><th>{{ __('Category') }}</th>
        <th>{{ __('Sample') }}</th><th>{{ __('Method') }}</th><th>{{ __('Review') }}</th>
      </tr></thead>
      <tbody>
        @forelse ($findings as $f)
          <tr @class(['table-danger' => $f->category === 'special_category'])>
            <td class="small">{{ $f->file_name }}</td>
            <td><code class="small">{{ $f->type }}</code></td>
            <td>
              @if ($f->category === 'special_category')
                <span class="badge bg-danger">{{ __('special category') }}</span>
              @else <span class="badge bg-warning text-dark">{{ __('personal') }}</span>@endif
            </td>
            <td class="small"><code>{{ $f->sample }}</code></td>
            <td class="small text-muted">{{ $f->method }}@if ($f->method !== 'deterministic') <span class="text-info">({{ __('AI') }})</span>@endif</td>
            <td class="small">
              @if ($f->review_status === 'pending')
                <form method="POST" action="{{ route('rdm.datasets.finding.resolve', [$dataset->id, $f->id]) }}" class="d-inline">
                  @csrf
                  <input type="hidden" name="decision" value="confirm">
                  <button class="btn btn-outline-danger btn-sm py-0">{{ __('Confirm PII') }}</button>
                </form>
                <form method="POST" action="{{ route('rdm.datasets.finding.resolve', [$dataset->id, $f->id]) }}" class="d-inline">
                  @csrf
                  <input type="hidden" name="decision" value="dismiss">
                  <button class="btn btn-outline-secondary btn-sm py-0">{{ __('Dismiss') }}</button>
                </form>
              @else
                <span @class(['badge', 'bg-danger' => $f->review_status === 'confirmed', 'bg-secondary' => $f->review_status === 'dismissed'])>{{ $f->review_status }}</span>
                <span class="text-muted">· {{ \Illuminate\Support\Str::limit((string) $f->reviewed_at, 16, '') }}@if ($f->reviewed_by) · #{{ $f->reviewed_by }}@endif</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-success py-3"><i class="fas fa-check-circle me-1"></i>{{ __('No PII detected — verdict CLEAR.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endif

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
