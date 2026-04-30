@extends('theme::layouts.2col')
@section('title', 'Site Paths Setup')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1><i class="fas fa-folder-open me-2"></i>Site Paths Setup</h1>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.paths') }}">
      @csrf
      <div class="card mb-3">
        <div class="card-body">
          <table class="table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Value') }}</th></tr></thead>
            <tbody>
              <tr>
                <td><label class="form-label">Bulk data directory <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label></td>
                <td><input type="text" name="settings[bulk]" class="form-control" value="{{ $settings['bulk'] ?? '' }}"></td>
              </tr>
              <tr>
                <td><label class="form-label">Bulk index directory <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label></td>
                <td><input type="text" name="settings[bulk_index]" class="form-control" value="{{ $settings['bulk_index'] ?? '' }}"></td>
              </tr>
              <tr>
                <td><label class="form-label">Bulk optimize index directory <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label></td>
                <td><input type="text" name="settings[bulk_optimize_index]" class="form-control" value="{{ $settings['bulk_optimize_index'] ?? '' }}"></td>
              </tr>
              <tr>
                <td><label class="form-label">Bulk rename directory <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label></td>
                <td><input type="text" name="settings[bulk_rename]" class="form-control" value="{{ $settings['bulk_rename'] ?? '' }}"></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
@endsection
