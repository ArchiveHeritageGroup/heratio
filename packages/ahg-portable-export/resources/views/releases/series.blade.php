@extends('theme::layouts.1col')
@section('title', $series->title)
@section('body-class', 'admin portable-export')
@section('content')
<div class="container-fluid py-4">
  <a href="{{ route('portable-export.releases.index') }}" class="btn btn-outline-secondary btn-sm mb-3"><i class="fas fa-arrow-left me-1"></i>{{ __('Versioned releases') }}</a>
  <h1 class="h3">{{ $series->title }}</h1>
  <p class="mb-1">{{ __('Concept DOI') }}: <code>{{ $series->concept_doi ?: __('assigned at the first freeze') }}</code> <span class="text-muted">({{ $series->concept_doi_state }})</span></p>
  <p class="text-muted">{{ $series->creator_name }} - {{ $series->publisher }}{{ $series->license ? ' - '.$series->license : '' }}</p>
  @include('ahg-portable-export::releases._flash')

  @if ($freezes->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">{{ __('Recent freezes') }}</div>
      <div class="card-body small">
        @foreach ($freezes as $f)
          <div class="mb-2">
            {{ __('Export') }} #{{ $f->export_id }} - <span class="badge bg-secondary">{{ $freezeLabels[$f->status] ?? $f->status }}</span> {{ $f->finished_at ?? $f->started_at ?? $f->created_at }}
            @if ($f->status === 'failed')
              <ul class="text-danger mb-0">@foreach (array_slice((array) json_decode((string) $f->failures, true), 0, 10) as $failure)<li>{{ $failure }}</li>@endforeach</ul>
            @endif
          </div>
        @endforeach
      </div>
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">{{ __('Versions') }}</div>
    <div class="card-body">
      @forelse ($versions as $v)
        <div class="border rounded p-3 mb-3">
          <div class="d-flex justify-content-between flex-wrap">
            <strong>v{{ $v->version_number }}</strong>
            <span class="badge bg-secondary">{{ $statusLabels[$v->status] ?? $v->status }}</span>
          </div>
          <div class="small mt-2">
            <div>{{ __('Version DOI') }}: <code>{{ $v->version_doi }}</code> ({{ $v->doi_state }})</div>
            <div>{{ __('Records') }}: {{ number_format($v->record_count) }} - {{ __('Files') }}: {{ number_format($v->file_count) }} - {{ number_format($v->package_bytes / 1048576, 1) }} MB</div>
            <div class="text-break">SHA-256: <code>{{ $v->package_sha256 }}</code></div>
            @if ($v->target_name)<div>{{ __('Deposit') }}: {{ $v->target_name }} - {{ $depositLabels[$v->deposit_status] ?? $v->deposit_status }}@if ($v->external_url && str_starts_with($v->external_url, 'https://')) - <a href="{{ $v->external_url }}" rel="noopener">{{ __('record') }}</a>@endif</div>@endif
          </div>
          @if ($v->last_error)<div class="alert alert-danger small mt-2 mb-0">{{ $v->last_error }}</div>@endif
          @if ($v->status === 'frozen')
            <form method="POST" action="{{ route('portable-export.releases.approve', $v->id) }}" class="mt-3">@csrf
              @if ($targets->isEmpty())
                <p class="text-muted mb-0">{{ __('Add a deposit target before approving.') }}</p>
              @else
                <label class="form-label" for="target_{{ $v->id }}">{{ __('Deposit target') }}</label>
                <select class="form-select mb-2" id="target_{{ $v->id }}" name="target_id" required>@foreach ($targets as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm_{{ $v->id }}" required><label class="form-check-label" for="confirm_{{ $v->id }}">{{ __('I approve this deposit. Registered DOIs and published external deposits cannot be undone.') }}</label></div>
                <button class="btn atom-btn-white"><i class="fas fa-check me-1"></i>{{ __('Approve and publish') }}</button>
              @endif
            </form>
          @elseif ($v->status === 'approved')
            <form method="POST" action="{{ route('portable-export.releases.retry', $v->id) }}" class="mt-3">@csrf<button class="btn btn-sm atom-btn-white"><i class="fas fa-redo me-1"></i>{{ __('Retry publishing') }}</button></form>
          @elseif ($v->status === 'released')
            <a class="btn btn-sm atom-btn-white mt-3" href="{{ route('portable-release.landing', ['key' => $series->series_key, 'version' => $v->version_number]) }}">{{ __('Public page') }}</a>
          @elseif ($v->status === 'abandoned')
            <div class="small text-muted mt-2">{{ __('Abandoned') }} {{ $v->abandoned_at }}: {{ $v->abandon_reason }}</div>
          @endif
          @if (in_array($v->status, ['frozen', 'approved'], true) && $v->doi_state !== 'findable')
            <details class="mt-3"><summary class="small">{{ __('Abandon this version') }}</summary>
              <form method="POST" action="{{ route('portable-export.releases.abandon', $v->id) }}" class="mt-2">@csrf
                <p class="form-text">{{ __('Use this when the version must not go public, for example when a record in it has become restricted. Its number is not reused, its corrections return to the pending list, and any unpublished draft deposit is deleted.') }}</p>
                <label class="form-label" for="abandon_reason_{{ $v->id }}">{{ __('Reason') }}</label>
                <textarea class="form-control mb-2" id="abandon_reason_{{ $v->id }}" name="reason" rows="2" maxlength="2000" required></textarea>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirm_abandon" value="1" id="confirm_abandon_{{ $v->id }}" required><label class="form-check-label" for="confirm_abandon_{{ $v->id }}">{{ __('I understand this version can never be published.') }}</label></div>
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-ban me-1"></i>{{ __('Abandon version') }}</button>
              </form>
            </details>
          @endif
        </div>
      @empty
        <p class="text-muted mb-0">{{ __('No versions yet.') }}</p>
      @endforelse
    </div>
  </div>

  <div class="row">
    <div class="col-lg-6 mb-4"><div class="card h-100">
      <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">{{ __('Freeze the next version') }}</div>
      <div class="card-body">
        <form method="POST" action="{{ route('portable-export.releases.freeze', $series->id) }}">@csrf
          <div class="mb-3"><label class="form-label" for="export_id">{{ __('Completed export') }}</label>
            <select class="form-select" id="export_id" name="export_id" required>@foreach ($exports as $x)<option value="{{ $x->id }}">#{{ $x->id }} - {{ $x->title }} ({{ $x->scope_type }}, {{ $x->total_descriptions }})</option>@endforeach</select></div>
          <div class="mb-3"><label class="form-label" for="release_notes">{{ __('Release notes') }}</label><textarea class="form-control" id="release_notes" name="release_notes" rows="3"></textarea></div>
          <button class="btn atom-btn-white"><i class="fas fa-lock me-1"></i>{{ __('Check and freeze') }}</button>
        </form>
      </div>
    </div></div>
    <div class="col-lg-6 mb-4"><div class="card h-100">
      <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">{{ __('Corrections for the next version') }}</div>
      <div class="card-body">
        @foreach ($pending as $c)
          <div class="small border-bottom pb-1 mb-2"><strong>{{ $correctionTypes[$c->correction_type] ?? $c->correction_type }}</strong>{{ $c->record_ref ? ' - '.$c->record_ref : '' }}: {{ $c->reason }}</div>
        @endforeach
        <form method="POST" action="{{ route('portable-export.releases.corrections.store', $series->id) }}">@csrf
          <p class="form-text">{{ __('Corrections are published in the changelog. Do not enter restricted or personal information.') }}</p>
          <div class="mb-2"><label class="form-label" for="correction_type">{{ __('Type') }}</label><select class="form-select" id="correction_type" name="correction_type" required>@foreach ($correctionTypes as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
          <div class="mb-2"><label class="form-label" for="record_ref">{{ __('Record reference') }}</label><input class="form-control" id="record_ref" name="record_ref" maxlength="255"></div>
          <div class="mb-2"><label class="form-label" for="field_name">{{ __('Field or figure') }}</label><input class="form-control" id="field_name" name="field_name" maxlength="255"></div>
          <div class="row"><div class="col mb-2"><label class="form-label" for="old_value">{{ __('Was') }}</label><input class="form-control" id="old_value" name="old_value"></div><div class="col mb-2"><label class="form-label" for="new_value">{{ __('Now') }}</label><input class="form-control" id="new_value" name="new_value"></div></div>
          <div class="mb-3"><label class="form-label" for="reason">{{ __('Reason') }}</label><textarea class="form-control" id="reason" name="reason" rows="2" required></textarea></div>
          <button class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>{{ __('Log correction') }}</button>
        </form>
      </div>
    </div></div>
  </div>
</div>
@endsection
