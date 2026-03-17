@extends('theme::layouts.master')

@section('title', 'Browse Settings')
@section('body-class', 'admin display browse-settings')

@section('breadcrumbs')
  <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
  <li class="breadcrumb-item"><a href="{{ route('glam.browse') }}">GLAM Browse</a></li>
  <li class="breadcrumb-item active" aria-current="page">Settings</li>
@endsection

@section('layout-content')
<div id="main-column" role="main">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center">
      <i class="fas fa-3x fa-cog me-3 text-primary" aria-hidden="true"></i>
      <div>
        <h1 class="mb-0">Browse Settings</h1>
        <span class="small text-muted">Configure default browse behaviour and display preferences</span>
      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Browse Preferences</h5>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('glam.save.settings') }}">
        @csrf

        {{-- GLAM browse toggle --}}
        <div class="mb-4">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="glam_browse_enabled" name="glam_browse_enabled"
                   value="1" {{ !empty($settings['glam_browse_enabled']) ? 'checked' : '' }}>
            <label class="form-check-label" for="glam_browse_enabled">
              <strong>Enable GLAM Browse</strong>
              <small class="d-block text-muted">Use the enhanced GLAM browse interface instead of the standard browse</small>
            </label>
          </div>
        </div>

        <hr>

        {{-- Default View --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="default_view" class="form-label"><strong>Default View</strong></label>
            <select name="default_view" id="default_view" class="form-select">
              <option value="list" {{ ($settings['default_view'] ?? 'list') === 'list' ? 'selected' : '' }}>
                List View
              </option>
              <option value="grid" {{ ($settings['default_view'] ?? '') === 'grid' ? 'selected' : '' }}>
                Grid View
              </option>
              <option value="gallery" {{ ($settings['default_view'] ?? '') === 'gallery' ? 'selected' : '' }}>
                Gallery View
              </option>
              <option value="timeline" {{ ($settings['default_view'] ?? '') === 'timeline' ? 'selected' : '' }}>
                Timeline View
              </option>
              <option value="tree" {{ ($settings['default_view'] ?? '') === 'tree' ? 'selected' : '' }}>
                Tree View
              </option>
            </select>
          </div>

          {{-- Items Per Page --}}
          <div class="col-md-6">
            <label for="items_per_page" class="form-label"><strong>Items Per Page</strong></label>
            <select name="items_per_page" id="items_per_page" class="form-select">
              @foreach([10, 20, 30, 50, 100] as $count)
                <option value="{{ $count }}" {{ ($settings['items_per_page'] ?? 30) == $count ? 'selected' : '' }}>
                  {{ $count }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        {{-- Sort By / Direction --}}
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="sort_by" class="form-label"><strong>Sort By</strong></label>
            <select name="sort_by" id="sort_by" class="form-select">
              <option value="title" {{ ($settings['sort_by'] ?? 'title') === 'title' ? 'selected' : '' }}>
                Title
              </option>
              <option value="identifier" {{ ($settings['sort_by'] ?? '') === 'identifier' ? 'selected' : '' }}>
                Identifier
              </option>
              <option value="date" {{ ($settings['sort_by'] ?? '') === 'date' ? 'selected' : '' }}>
                Date
              </option>
              <option value="level" {{ ($settings['sort_by'] ?? '') === 'level' ? 'selected' : '' }}>
                Level of Description
              </option>
              <option value="type" {{ ($settings['sort_by'] ?? '') === 'type' ? 'selected' : '' }}>
                Type
              </option>
              <option value="updated" {{ ($settings['sort_by'] ?? '') === 'updated' ? 'selected' : '' }}>
                Last Updated
              </option>
            </select>
          </div>
          <div class="col-md-6">
            <label for="sort_direction" class="form-label"><strong>Direction</strong></label>
            <select name="sort_direction" id="sort_direction" class="form-select">
              <option value="asc" {{ ($settings['sort_direction'] ?? 'asc') === 'asc' ? 'selected' : '' }}>
                Ascending (A-Z)
              </option>
              <option value="desc" {{ ($settings['sort_direction'] ?? '') === 'desc' ? 'selected' : '' }}>
                Descending (Z-A)
              </option>
            </select>
          </div>
        </div>

        <hr>

        {{-- Checkboxes --}}
        <div class="mb-3">
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="show_facets" name="show_facets"
                   value="1" {{ !empty($settings['show_facets']) ? 'checked' : '' }}>
            <label class="form-check-label" for="show_facets">
              <strong>Show facets</strong>
              <small class="d-block text-muted">Display filter facets in the sidebar when browsing</small>
            </label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="remember_filters" name="remember_filters"
                   value="1" {{ !empty($settings['remember_filters']) ? 'checked' : '' }}>
            <label class="form-check-label" for="remember_filters">
              <strong>Remember filters</strong>
              <small class="d-block text-muted">Persist selected filters across browse sessions</small>
            </label>
          </div>
        </div>

        <hr>

        {{-- Action buttons --}}
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Settings
          </button>
          <a href="{{ route('glam.browse') }}" class="btn btn-outline-secondary">
            Cancel
          </a>
          <button type="submit" formaction="{{ route('glam.reset.settings') }}" class="btn btn-outline-danger ms-auto">
            <i class="fas fa-undo me-1"></i> Reset to Defaults
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
