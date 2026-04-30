@extends('theme::layouts.1col')
@section('title', 'Create Report')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-plus me-2"></i>{{ __('Create New Report') }}</h1>

    <form method="post" action="{{ route('reports.builder.store') }}">
      @csrf
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">Report Details</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Report Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Description <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
            <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Data Source <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <select name="data_source" class="form-select" required>
                <option value="information_object">{{ __('Archival Descriptions') }}</option>
                <option value="actor">{{ __('Authority Records') }}</option>
                <option value="accession">{{ __('Accessions') }}</option>
                <option value="repository">{{ __('Repositories') }}</option>
                <option value="physical_object">{{ __('Physical Storage') }}</option>
                <option value="donor">{{ __('Donors') }}</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Category <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <select name="category" class="form-select">
                <option value="Archives">{{ __('Archives') }}</option>
                <option value="Collections">{{ __('Collections') }}</option>
                <option value="Heritage">{{ __('Heritage') }}</option>
                <option value="Compliance">{{ __('Compliance') }}</option>
                <option value="General">{{ __('General') }}</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Visibility <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <select name="visibility" class="form-select">
                <option value="private">{{ __('Private') }}</option>
                <option value="shared">{{ __('Shared') }}</option>
                <option value="public">{{ __('Public') }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Create Report') }}</button>
        <a href="{{ route('reports.builder.index') }}" class="btn atom-btn-white">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection