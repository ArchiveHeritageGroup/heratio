{{--
  Browse Settings – browse-settings.blade.php
  Migrated from AtoM browseSettingsSuccess.php (ahgDisplayPlugin)
  Matches AtoM exactly: same fields, same names, same options, same layout
--}}
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
    <div class="alert alert-success alert-dismissible fade show">
      <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="fas fa-exclamation-circle me-1"></i> {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="card">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Browse Preferences</h5>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('glam.browse.settings') }}">
        @csrf

        {{-- Browse Interface section --}}
        <h6 class="text-muted border-bottom pb-2 mb-3">Browse Interface</h6>

        <div class="mb-4">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="use_glam_browse" name="use_glam_browse"
                   value="1" {{ !empty($settings['use_glam_browse']) ? 'checked' : '' }}>
            <label class="form-check-label" for="use_glam_browse">
              <strong>Use GLAM Browse as default</strong> <span class="badge bg-secondary ms-1">Optional</span>
            </label>
          </div>
          <div class="form-text ms-4">
            When enabled, you'll be redirected to the GLAM browse interface instead of the standard browse.
            The GLAM browse provides faceted search, type filtering, and enhanced display options.
          </div>
        </div>

        {{-- Default Display Options section --}}
        <h6 class="text-muted border-bottom pb-2 mb-3">Default Display Options</h6>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label" for="default_view">Default View <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="default_view" id="default_view" class="form-select">
              <option value="list" {{ ($settings['default_view'] ?? 'list') === 'list' ? 'selected' : '' }}>List</option>
              <option value="card" {{ ($settings['default_view'] ?? '') === 'card' ? 'selected' : '' }}>Cards</option>
              <option value="table" {{ ($settings['default_view'] ?? '') === 'table' ? 'selected' : '' }}>Table</option>
            </select>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label" for="items_per_page">Items Per Page <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="items_per_page" id="items_per_page" class="form-select">
              @foreach([10, 20, 30, 50, 100] as $n)
                <option value="{{ $n }}" {{ ($settings['items_per_page'] ?? 30) == $n ? 'selected' : '' }}>{{ $n }}</option>
              @endforeach
            </select>
          </div>
        </div>

        {{-- Default Sorting section --}}
        <h6 class="text-muted border-bottom pb-2 mb-3">Default Sorting</h6>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label" for="default_sort_field">Sort By <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="default_sort_field" id="default_sort_field" class="form-select">
              <option value="updated_at" {{ ($settings['default_sort_field'] ?? 'updated_at') === 'updated_at' ? 'selected' : '' }}>Last Updated</option>
              <option value="title" {{ ($settings['default_sort_field'] ?? '') === 'title' ? 'selected' : '' }}>Title</option>
              <option value="identifier" {{ ($settings['default_sort_field'] ?? '') === 'identifier' ? 'selected' : '' }}>Identifier</option>
              <option value="date" {{ ($settings['default_sort_field'] ?? '') === 'date' ? 'selected' : '' }}>Date Created</option>
              <option value="startdate" {{ ($settings['default_sort_field'] ?? '') === 'startdate' ? 'selected' : '' }}>Start Date</option>
            </select>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label" for="default_sort_direction">Direction <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="default_sort_direction" id="default_sort_direction" class="form-select">
              <option value="desc" {{ ($settings['default_sort_direction'] ?? 'desc') === 'desc' ? 'selected' : '' }}>Descending (newest first)</option>
              <option value="asc" {{ ($settings['default_sort_direction'] ?? '') === 'asc' ? 'selected' : '' }}>Ascending (oldest first)</option>
            </select>
          </div>
        </div>

        {{-- Additional Options section --}}
        <h6 class="text-muted border-bottom pb-2 mb-3">Additional Options</h6>

        <div class="mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="show_facets" name="show_facets"
                   value="1" {{ ($settings['show_facets'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="show_facets">
              Show filter sidebar (facets) <span class="badge bg-secondary ms-1">Optional</span>
            </label>
          </div>
        </div>

        <div class="mb-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="remember_filters" name="remember_filters"
                   value="1" {{ ($settings['remember_filters'] ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="remember_filters">
              Remember my last used filters <span class="badge bg-secondary ms-1">Optional</span>
            </label>
          </div>
          <div class="form-text ms-4">
            When enabled, your filter selections will be saved and applied automatically on your next visit.
          </div>
        </div>

        {{-- Action buttons --}}
        <div class="d-flex gap-2">
          <button type="submit" class="btn atom-btn-outline-success">
            <i class="fas fa-check-lg me-1"></i> Save Settings
          </button>
          <a href="{{ route('glam.browse') }}" class="btn atom-btn-white">
            Cancel
          </a>
          <button type="button" class="btn atom-btn-outline-danger ms-auto" id="reset-settings">
            <i class="fas fa-undo me-1"></i> Reset to Defaults
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('js')
<script>
document.getElementById('reset-settings').addEventListener('click', function() {
  if (confirm('Reset all browse settings to defaults?')) {
    fetch('{{ route('glam.reset.settings') }}', {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Content-Type': 'application/json'
      }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        window.location.reload();
      } else {
        alert('Failed to reset settings');
      }
    })
    .catch(function() {
      // Fallback: submit as form
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '{{ route('glam.reset.settings') }}';
      var csrf = document.createElement('input');
      csrf.type = 'hidden';
      csrf.name = '_token';
      csrf.value = '{{ csrf_token() }}';
      form.appendChild(csrf);
      document.body.appendChild(form);
      form.submit();
    });
  }
});
</script>
@endpush

@endsection
