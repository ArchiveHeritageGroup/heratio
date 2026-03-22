@extends('theme::layouts.1col')
@section('title', 'Import AHG Settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><h4 class="mb-0"><i class="fas fa-upload me-2"></i>Import AHG Settings</h4></div>
      <div class="card-body">
        <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Upload a previously exported AHG settings JSON file to restore settings.</div>

        <form method="post" enctype="multipart/form-data" action="{{ route('settings.ahg-import') }}">
          @csrf
          <div class="mb-3">
            <label for="settings_file" class="form-label">Settings File <span class="badge bg-danger ms-1">Required</span></label>
            <input type="file" class="form-control" id="settings_file" name="settings_file" accept=".json" required>
            <div class="form-text">Select a .json file exported from AHG Settings</div>
          </div>

          <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Warning: This will overwrite existing settings with the same keys.</div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-upload me-1"></i>Import Settings</button>
            <a href="{{ route('settings.index') }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i>Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
