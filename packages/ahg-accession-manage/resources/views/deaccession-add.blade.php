@extends('theme::layouts.1col')
@section('title', __('Record deaccession'))
@section('body-class', 'admin accession')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('accession.browse') }}">{{ __('Accessions') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('accession.show', $accession->slug ?? '') }}">{{ e($accession->identifier ?? '') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Deaccession') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-1"><i class="fas fa-box-open me-2"></i>{{ __('Record deaccession') }}</h1>
<p class="text-muted">{{ e($accession->title ?? '') }}</p>

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="alert alert-warning">
  <i class="fas fa-triangle-exclamation me-1"></i>
  {{ __('A deaccession records material formally removed from the collection. It does not delete the accession or any description - it documents the removal and why it was authorised.') }}
</div>

<form method="post" action="{{ route('accession.deaccession-store', $accession->id) }}">
  @csrf
  <div class="card mb-3">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Deaccession') }}</div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label" for="identifier">{{ __('Identifier') }}</label>
          <input type="text" id="identifier" name="identifier" class="form-control" value="{{ old('identifier') }}">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="date">{{ __('Date') }}</label>
          <input type="date" id="date" name="date" class="form-control" value="{{ old('date', date('Y-m-d')) }}">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="scope_id">{{ __('Scope') }}</label>
          <select id="scope_id" name="scope_id" class="form-select">
            <option value="">{{ __('- Select -') }}</option>
            @foreach($scopes as $tid => $name)
              <option value="{{ $tid }}" {{ (string) old('scope_id') === (string) $tid ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
          </select>
          <div class="form-text">{{ __('Whether the whole accession or part of it was removed.') }}</div>
        </div>
        <div class="col-12 mb-3">
          <label class="form-label" for="reason">{{ __('Reason') }}</label>
          <textarea id="reason" name="reason" class="form-control" rows="3">{{ old('reason') }}</textarea>
        </div>
        <div class="col-12 mb-3">
          <label class="form-label" for="extent">{{ __('Extent') }}</label>
          <textarea id="extent" name="extent" class="form-control" rows="2">{{ old('extent') }}</textarea>
        </div>
        <div class="col-12 mb-3">
          <label class="form-label" for="description">{{ __('Description') }}</label>
          <textarea id="description" name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2 mb-4">
    <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Record deaccession') }}</button>
    <a href="{{ route('accession.show', $accession->slug ?? '') }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>
  </div>
</form>

@if(($deaccessions ?? collect())->count())
  <div class="card">
    <div class="card-header"><i class="fas fa-history me-2"></i>{{ __('Existing deaccessions') }}</div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th>{{ __('Identifier') }}</th>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Scope') }}</th>
            <th>{{ __('Reason') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($deaccessions as $d)
            <tr>
              <td>{{ e($d->identifier ?? '') }}</td>
              <td>{{ $d->date ?? '' }}</td>
              <td>{{ e($scopes[$d->scope_id] ?? '') }}</td>
              <td>{{ \Illuminate\Support\Str::limit($d->reason ?? '', 60) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif
@endsection
