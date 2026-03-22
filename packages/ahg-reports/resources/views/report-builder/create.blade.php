@extends('theme::layouts.1col')
@section('title', 'Create Report')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-plus me-2"></i>Create New Report</h1>

    <form method="post" action="{{ route('reports.builder.store') }}">
      @csrf
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">Report Details</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Report Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Data Source <span class="text-danger">*</span></label>
              <select name="data_source" class="form-select" required>
                <option value="information_object">Archival Descriptions</option>
                <option value="actor">Authority Records</option>
                <option value="accession">Accessions</option>
                <option value="repository">Repositories</option>
                <option value="physical_object">Physical Storage</option>
                <option value="donor">Donors</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Category</label>
              <select name="category" class="form-select">
                <option value="Archives">Archives</option>
                <option value="Collections">Collections</option>
                <option value="Heritage">Heritage</option>
                <option value="Compliance">Compliance</option>
                <option value="General">General</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Visibility</label>
              <select name="visibility" class="form-select">
                <option value="private">Private</option>
                <option value="shared">Shared</option>
                <option value="public">Public</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Create Report</button>
        <a href="{{ route('reports.builder.index') }}" class="btn atom-btn-white">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection