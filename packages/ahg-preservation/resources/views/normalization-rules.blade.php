@extends('theme::layouts.1col')
@section('title', 'Normalization Rules')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-preservation::_menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="mb-0"><i class="fas fa-list-check me-2"></i>{{ __('Normalization Rules') }}</h1>
      <a href="{{ route('preservation.conversion') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-sync-alt me-1"></i>{{ __('Conversions') }}</a>
    </div>
    <p class="text-muted">{{ __('The format policy registry: each rule maps a source format to a preservation master or access copy and the tool that produces it. Used automatically on ingest (when Normalize is ticked) and by the ahg:normalize-existing command.') }}</p>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card mb-4">
      <div class="card-header"><strong>{{ __('Add rule') }}</strong></div>
      <div class="card-body">
        <form method="post" action="{{ route('preservation.normalization-rules.store') }}" class="row g-2 align-items-end">
          @csrf
          <div class="col-md-3">
            <label class="form-label small mb-0">{{ __('Source MIME') }}</label>
            <input name="source_mime" class="form-control form-control-sm" placeholder="image/jpeg">
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-0">{{ __('Purpose') }}</label>
            <select name="purpose" class="form-select form-select-sm">
              <option value="preservation">preservation</option>
              <option value="access">access</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-0">{{ __('Target format') }}</label>
            <input name="target_format" class="form-control form-control-sm" placeholder="TIFF" required>
          </div>
          <div class="col-md-1">
            <label class="form-label small mb-0">{{ __('Ext') }}</label>
            <input name="target_ext" class="form-control form-control-sm" placeholder="tiff" required>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-0">{{ __('Target MIME') }}</label>
            <input name="target_mime" class="form-control form-control-sm" placeholder="image/tiff">
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-0">{{ __('Tool') }}</label>
            <select name="tool" class="form-select form-select-sm">
              @foreach(['imagemagick','ghostscript','ffmpeg','libreoffice'] as $t)
                <option value="{{ strtolower($t) }}">{{ $t }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small mb-0">{{ __('PRONOM (opt)') }}</label>
            <input name="source_pronom" class="form-control form-control-sm" placeholder="fmt/43">
          </div>
          <div class="col-md-1">
            <label class="form-label small mb-0">{{ __('Priority') }}</label>
            <input name="priority" type="number" value="100" class="form-control form-control-sm">
          </div>
          <div class="col-md-2 form-check ms-2">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="add_active" checked>
            <label class="form-check-label small" for="add_active">{{ __('Active') }}</label>
          </div>
          <div class="col-md-2">
            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-plus me-1"></i>{{ __('Add') }}</button>
          </div>
        </form>
      </div>
    </div>

    <table class="table table-sm table-hover align-middle">
      <thead>
        <tr>
          <th>{{ __('Source') }}</th><th>{{ __('Purpose') }}</th><th>{{ __('Target') }}</th>
          <th>{{ __('Tool') }}</th><th>{{ __('Prio') }}</th><th>{{ __('Active') }}</th><th class="text-end">{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rules as $r)
        <tr>
          <td><code>{{ $r->source_mime ?: $r->source_pronom ?: '—' }}</code></td>
          <td><span class="badge bg-{{ $r->purpose === 'access' ? 'info' : 'success' }}">{{ $r->purpose }}</span></td>
          <td>{{ $r->target_format }} <span class="text-muted">.{{ $r->target_ext }}</span></td>
          <td>{{ $r->tool }}</td>
          <td>{{ $r->priority }}</td>
          <td>@if($r->is_active)<span class="badge bg-success">{{ __('yes') }}</span>@else<span class="badge bg-secondary">{{ __('no') }}</span>@endif</td>
          <td class="text-end text-nowrap">
            <form method="post" action="{{ route('preservation.normalization-rules.toggle', $r->id) }}" class="d-inline">@csrf
              <button class="btn btn-outline-secondary btn-sm" title="{{ __('Toggle') }}"><i class="fas fa-power-off"></i></button>
            </form>
            <form method="post" action="{{ route('preservation.normalization-rules.delete', $r->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this rule?') }}')">@csrf
              <button class="btn btn-outline-danger btn-sm" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center text-muted py-3">{{ __('No rules yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
