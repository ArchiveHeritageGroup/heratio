@extends('theme::layouts.1col')
@section('title', 'Site Paths Setup')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-settings::_menu')
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-folder-open me-2"></i>Site Paths Setup</h1>

    <form method="post" action="{{ route('settings.paths') }}">
      @csrf
      <div class="card mb-3">
        <div class="card-body">
          <table class="table">
            <thead><tr><th>Name</th><th>Value</th></tr></thead>
            <tbody>
              <tr>
                <td><label class="form-label">Bulk data directory</label></td>
                <td><input type="text" name="settings[bulk]" class="form-control" value="{{ $settings['bulk'] ?? '' }}"></td>
              </tr>
              <tr>
                <td><label class="form-label">Bulk index directory</label></td>
                <td><input type="text" name="settings[bulk_index]" class="form-control" value="{{ $settings['bulk_index'] ?? '' }}"></td>
              </tr>
              <tr>
                <td><label class="form-label">Bulk optimize index directory</label></td>
                <td><input type="text" name="settings[bulk_optimize_index]" class="form-control" value="{{ $settings['bulk_optimize_index'] ?? '' }}"></td>
              </tr>
              <tr>
                <td><label class="form-label">Bulk rename directory</label></td>
                <td><input type="text" name="settings[bulk_rename]" class="form-control" value="{{ $settings['bulk_rename'] ?? '' }}"></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
