@extends('theme::layouts.1col')
@section('title', __('Accession rights'))
@section('body-class', 'admin accession')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('accession.browse') }}">{{ __('Accessions') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('accession.show', $accession->slug ?? '') }}">{{ e($accession->identifier ?? '') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Rights') }}</li>
  </ol>
</nav>

<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link" href="{{ route('accession.containers', $accession->id ?? 0) }}">{{ __('Containers') }}</a></li>
  <li class="nav-item"><a class="nav-link active" href="{{ route('accession.rights', $accession->id ?? 0) }}">{{ __('Rights') }}</a></li>
</ul>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card mb-4">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">{{ __('Rights Records') }}</h5>
  </div>
  <div class="card-body p-0">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>{{ __('Basis') }}</th>
          <th>{{ __('Holder') }}</th>
          <th>{{ __('Restriction') }}</th>
          <th>{{ __('Start') }}</th>
          <th>{{ __('End') }}</th>
          <th>{{ __('Inherits') }}</th>
          <th>{{ __('Notes') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($rights ?? [] as $r)
          <tr>
            <td>{{ ucfirst($r->rights_basis ?? '') }}</td>
            <td>{{ e($r->rights_holder ?? '') }}</td>
            <td>{{ ucfirst($r->restriction_type ?? '') }}</td>
            <td>{{ $r->start_date ?? '' }}</td>
            <td>{{ $r->end_date ?? '' }}</td>
            <td>{!! ($r->inherit_to_children ?? 0) ? '<i class="fas fa-check text-success"></i>' : '' !!}</td>
            <td>{{ \Illuminate\Support\Str::limit($r->notes ?? '', 50) }}</td>
            <td class="text-end">
              <form method="post" action="{{ route('accession.rights-destroy', [$accession->id, $r->id]) }}"
                    onsubmit="return confirm({{ Js::from(__('Remove this rights record?')) }});" class="d-inline">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Remove') }}">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-muted text-center py-3">{{ __('No rights records.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="fas fa-plus me-2"></i>{{ __('Add rights record') }}</div>
  <div class="card-body">
    <form method="post" action="{{ route('accession.rights-store', $accession->id) }}">
      @csrf
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label" for="rights_basis">{{ __('Basis') }} <span class="text-danger">*</span></label>
          <select id="rights_basis" name="rights_basis" class="form-select" required>
            @foreach($bases as $b)
              <option value="{{ $b }}" {{ old('rights_basis') === $b ? 'selected' : '' }}>{{ __(ucfirst($b)) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="restriction_type">{{ __('Restriction') }} <span class="text-danger">*</span></label>
          <select id="restriction_type" name="restriction_type" class="form-select" required>
            @foreach($restrictions as $r)
              <option value="{{ $r }}" {{ old('restriction_type') === $r ? 'selected' : '' }}>{{ __(ucfirst($r)) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="rights_holder">{{ __('Rights holder') }}</label>
          <input type="text" id="rights_holder" name="rights_holder" class="form-control" value="{{ old('rights_holder') }}">
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label" for="start_date">{{ __('Start date') }}</label>
          <input type="date" id="start_date" name="start_date" class="form-control" value="{{ old('start_date') }}">
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label" for="end_date">{{ __('End date') }}</label>
          <input type="date" id="end_date" name="end_date" class="form-control" value="{{ old('end_date') }}">
        </div>
        <div class="col-md-6 mb-3 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" id="inherit_to_children"
                   name="inherit_to_children" {{ old('inherit_to_children') ? 'checked' : '' }}>
            <label class="form-check-label" for="inherit_to_children">
              {{ __('Apply to descriptions created from this accession') }}
            </label>
          </div>
        </div>
        <div class="col-12 mb-3">
          <label class="form-label" for="conditions">{{ __('Conditions') }}</label>
          <textarea id="conditions" name="conditions" class="form-control" rows="2">{{ old('conditions') }}</textarea>
        </div>
        <div class="col-12 mb-3">
          <label class="form-label" for="notes">{{ __('Notes') }}</label>
          <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Add rights record') }}</button>
        <a href="{{ route('accession.show', $accession->slug ?? '') }}" class="btn atom-btn-white">{{ __('Back to accession') }}</a>
      </div>
    </form>
  </div>
</div>
@endsection
