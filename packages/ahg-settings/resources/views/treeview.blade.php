@extends('theme::layouts.1col')
@section('title', 'Treeview Settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-sitemap me-2"></i>Treeview Settings</h1>

    <form method="post" action="{{ route('settings.treeview') }}">
      @csrf
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">General</div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Type</label>
              <select name="settings[treeview_type]" class="form-select">
                <option value="sidebar" {{ ($settings['treeview_type'] ?? '') == 'sidebar' ? 'selected' : '' }}>Sidebar</option>
                <option value="full" {{ ($settings['treeview_type'] ?? '') == 'full' ? 'selected' : '' }}>Full width</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Show browse hierarchy page</label>
              <select name="settings[show_browse_hierarchy_page]" class="form-select">
                <option value="1" {{ ($settings['show_browse_hierarchy_page'] ?? '') == '1' ? 'selected' : '' }}>Yes</option>
                <option value="0" {{ ($settings['show_browse_hierarchy_page'] ?? '') == '0' ? 'selected' : '' }}>No</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Make full width treeview collapsed on description pages</label>
              <select name="settings[allow_full_width_treeview_collapse]" class="form-select">
                <option value="1" {{ ($settings['allow_full_width_treeview_collapse'] ?? '') == '1' ? 'selected' : '' }}>Yes</option>
                <option value="0" {{ ($settings['allow_full_width_treeview_collapse'] ?? '') == '0' ? 'selected' : '' }}>No</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">Sidebar</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Sort (information object)</label>
            <select name="settings[sort]" class="form-select">
              <option value="" {{ ($settings['sort'] ?? '') == '' ? 'selected' : '' }}>Manual</option>
              <option value="title" {{ ($settings['sort'] ?? '') == 'title' ? 'selected' : '' }}>Title</option>
              <option value="identifier" {{ ($settings['sort'] ?? '') == 'identifier' ? 'selected' : '' }}>Identifier</option>
            </select>
            <small class="text-muted">Sort siblings in the treeview</small>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">Full width</div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-4 mb-3">
              <div class="form-check">
                <input type="hidden" name="settings[treeview_show_identifier]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[treeview_show_identifier]" value="1" id="tv_id" {{ ($settings['treeview_show_identifier'] ?? '') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="tv_id">Show identifier</label>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <div class="form-check">
                <input type="hidden" name="settings[treeview_show_level_of_description]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[treeview_show_level_of_description]" value="1" id="tv_level" {{ ($settings['treeview_show_level_of_description'] ?? '') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="tv_level">Show level of description</label>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <div class="form-check">
                <input type="hidden" name="settings[treeview_show_dates]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[treeview_show_dates]" value="1" id="tv_dates" {{ ($settings['treeview_show_dates'] ?? '') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="tv_dates">Show dates</label>
              </div>
            </div>
            <div class="col-md-12 mb-3">
              <label class="form-label">Items per page</label>
              <input type="number" name="settings[treeview_items_per_page]" class="form-control" value="{{ $settings['treeview_items_per_page'] ?? '50' }}" min="10" max="10000">
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
